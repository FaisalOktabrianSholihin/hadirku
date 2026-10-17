<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Models\Karyawan;
use App\Models\Keterangan;
use App\Models\Pengaturan;
use App\Support\Kalender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Input keterangan manual oleh admin (izin, sakit, cuti, dinas, koreksi, dst.) */
class ManualController extends Controller
{
    public function cari(Request $r): JsonResponse
    {
        $q = trim((string) $r->query('q', ''));

        return response()->json(DB::table('pengaturan_absen as p')
            ->join(Karyawan::tabel().' as k', 'k.nik', '=', 'p.nik')
            ->leftJoin(Divisi::tabel().' as d', 'd.id', '=', 'k.divisi_id')
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w
                ->where('k.nama', 'like', "%{$q}%")->orWhere('k.nik', 'like', "%{$q}%")))
            ->orderBy('k.nama')->limit(20)
            ->get(['k.nik', 'k.nama', 'd.nama_divisi as divisi']));
    }

    public function store(Request $r): JsonResponse
    {
        $d = $r->validate([
            'niks' => 'required|array|min:1|max:500',
            'niks.*' => 'string',
            'dari' => 'required|date_format:Y-m-d',
            'sampai' => 'required|date_format:Y-m-d|after_or_equal:dari',
            'keterangan_id' => 'required|integer|min:0',
            'menit' => 'nullable|integer|min:0|max:1440',
            'catatan' => 'nullable|string|max:255',
        ]);

        if (strtotime($d['sampai']) - strtotime($d['dari']) > 62 * 86400) {
            throw ValidationException::withMessages(['sampai' => 'Rentang maksimal 62 hari per input.']);
        }
        $niks = Pengaturan::whereIn('nik', $d['niks'])->pluck('nik')->all();
        if (! $niks) {
            throw ValidationException::withMessages(['niks' => 'Karyawan tidak valid.']);
        }

        // keterangan_id = 0 berarti hapus keterangan manual pada rentang tsb
        if ((int) $d['keterangan_id'] === 0) {
            $n = DB::table('kehadiran_manual')->whereIn('nik', $niks)->whereBetween('tanggal', [$d['dari'], $d['sampai']])->delete();

            return response()->json(['ok' => true, 'pesan' => "{$n} data keterangan manual dihapus."]);
        }

        $ket = Keterangan::where('id', $d['keterangan_id'])->where('aktif', true)->first();
        if (! $ket || $ket->sistem === 'libur') {
            throw ValidationException::withMessages(['keterangan_id' => 'Keterangan tidak valid.']);
        }

        $kal = Kalender::rentang($d['dari'], $d['sampai']);
        $hari = [];
        for ($t = $d['dari']; $t <= $d['sampai']; $t = date('Y-m-d', strtotime($t.' +1 day'))) {
            if (! isset($kal[$t])) { // hari libur dilewati
                $hari[] = $t;
            }
        }
        if (count($hari) * count($niks) > 5000) {
            throw ValidationException::withMessages(['niks' => 'Terlalu banyak data sekaligus (maks 5000 hari-karyawan).']);
        }

        $now = now();
        $rows = [];
        foreach ($niks as $nik) {
            foreach ($hari as $t) {
                $rows[] = ['nik' => $nik, 'tanggal' => $t, 'keterangan_id' => $ket->id,
                    'menit' => $ket->pakai_menit ? ($d['menit'] ?? null) : null,
                    'catatan' => $d['catatan'] ?? null, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('kehadiran_manual')->upsert($chunk, ['nik', 'tanggal'], ['keterangan_id', 'menit', 'catatan', 'updated_at']);
        }

        return response()->json(['ok' => true, 'pesan' => count($rows).' data keterangan tersimpan (hari libur dilewati).']);
    }
}
