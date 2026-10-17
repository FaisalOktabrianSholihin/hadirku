<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HariLibur;
use App\Models\LiburRutin;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LiburController extends Controller
{
    private function bulan(Request $r): Carbon
    {
        $b = (string) $r->input('bulan', now()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $b), 422, 'Format bulan salah.');

        return Carbon::createFromFormat('Y-m-d', $b.'-01')->startOfDay();
    }

    public function index(Request $r): JsonResponse
    {
        $awal = $this->bulan($r);
        $akhir = $awal->copy()->endOfMonth();

        return response()->json([
            'bulan' => $awal->format('Y-m'),
            'rutin' => LiburRutin::orderBy('hari')->get(['hari', 'nama', 'aktif']),
            'khusus' => HariLibur::whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
                ->orderBy('tanggal')->get()
                ->map(fn ($h) => ['tanggal' => $h->tanggal->toDateString(), 'nama' => $h->nama, 'jenis' => $h->jenis]),
        ]);
    }

    /** Simpan seluruh catatan satu bulan + pengaturan libur rutin sekaligus */
    public function sync(Request $r): JsonResponse
    {
        $awal = $this->bulan($r);
        $akhir = $awal->copy()->endOfMonth();

        $d = $r->validate([
            'items' => 'present|array|max:100',
            'items.*.tanggal' => 'required|date_format:Y-m-d',
            'items.*.nama' => 'required|string|max:100',
            'items.*.jenis' => ['required', Rule::in(['nasional', 'cuti_bersama', 'perusahaan'])],
            'rutin' => 'present|array',
            'rutin.*' => 'integer|between:0,6',
        ], ['items.*.nama.required' => 'Nama/keterangan libur wajib diisi.']);

        $rows = [];
        foreach ($d['items'] as $it) {
            if ($it['tanggal'] < $awal->toDateString() || $it['tanggal'] > $akhir->toDateString()) {
                throw ValidationException::withMessages(['items' => "Tanggal {$it['tanggal']} di luar bulan yang sedang diedit."]);
            }
            $rows[$it['tanggal']] = ['tanggal' => $it['tanggal'], 'nama' => trim($it['nama']), 'jenis' => $it['jenis'],
                'created_at' => now(), 'updated_at' => now()];
        }

        DB::transaction(function () use ($awal, $akhir, $rows, $d) {
            HariLibur::whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])->delete();
            if ($rows) {
                HariLibur::insert(array_values($rows));
            }
            LiburRutin::query()->update(['aktif' => false]);
            if ($d['rutin']) {
                LiburRutin::whereIn('hari', $d['rutin'])->update(['aktif' => true]);
            }
        });

        return response()->json(['ok' => true]);
    }
}
