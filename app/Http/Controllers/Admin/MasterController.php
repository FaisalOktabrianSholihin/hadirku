<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Models\Jabatan;
use App\Models\Karyawan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Data master: HANYA melihat data dari db_indukk (tidak ada tambah / ubah / hapus). */
class MasterController extends Controller
{
    private function dir(Request $r): string
    {
        return strtolower((string) $r->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
    }

    public function karyawan(Request $r): JsonResponse
    {
        $q = trim((string) $r->query('q', ''));
        $divisi = (int) $r->query('divisi', 0);
        $status = (string) $r->query('status', '');
        $dir = $this->dir($r);

        $urut = [
            'nik' => 'k.nik', 'nama' => 'k.nama', 'jabatan' => 'j.nama_jabatan', 'divisi' => 'd.nama_divisi',
            'status' => 'k.status_aktif', 'presensi' => 'coalesce(p.aktif, -1)',
        ];
        $kolom = $urut[$r->query('sort')] ?? 'k.nama';

        $page = DB::table(Karyawan::tabel().' as k')
            ->leftJoin(Divisi::tabel().' as d', 'd.id', '=', 'k.divisi_id')
            ->leftJoin(Jabatan::tabel().' as j', 'j.id', '=', 'k.jabatan_id')
            ->leftJoin('pengaturan_absen as p', 'p.nik', '=', 'k.nik')
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('k.nama', 'like', "%{$q}%")->orWhere('k.nik', 'like', "%{$q}%")))
            ->when($divisi > 0, fn ($x) => $x->where('k.divisi_id', $divisi))
            ->when($status === 'aktif', fn ($x) => $x->where('k.status_aktif', '1'))
            ->when($status === 'nonaktif', fn ($x) => $x->where('k.status_aktif', '!=', '1'))
            ->orderByRaw("{$kolom} {$dir}")->orderBy('k.nama')
            ->select('k.nik', 'k.nama', 'k.status_aktif', 'j.nama_jabatan', 'd.nama_divisi', 'p.id as pid', 'p.aktif as p_aktif')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($x) => [
                'nik' => $x->nik, 'nama' => $x->nama, 'jabatan' => $x->nama_jabatan ?: '-', 'divisi' => $x->nama_divisi ?: '-',
                'aktif' => (string) $x->status_aktif === '1',
                'presensi' => $x->pid ? ((bool) $x->p_aktif ? 'aktif' : 'nonaktif') : 'belum',
            ])->values(),
            'meta' => ['halaman' => $page->currentPage(), 'terakhir' => $page->lastPage(), 'total' => $page->total()],
            'divisi' => Divisi::orderBy('nama_divisi')->get(['id', 'nama_divisi']),
        ]);
    }

    public function divisi(Request $r): JsonResponse
    {
        $q = trim((string) $r->query('q', ''));
        $dir = $this->dir($r);
        $urut = ['nama' => 'd.nama_divisi', 'total' => 'total', 'aktif' => 'aktif', 'terdaftar' => 'terdaftar'];
        $kolom = $urut[$r->query('sort')] ?? 'd.nama_divisi';

        $rows = DB::table(Divisi::tabel().' as d')
            ->leftJoin(Karyawan::tabel().' as k', 'k.divisi_id', '=', 'd.id')
            ->leftJoin('pengaturan_absen as p', 'p.nik', '=', 'k.nik')
            ->when($q !== '', fn ($x) => $x->where('d.nama_divisi', 'like', "%{$q}%"))
            ->groupBy('d.id', 'd.nama_divisi')
            ->selectRaw("d.id, d.nama_divisi, count(k.nik) as total, coalesce(sum(case when k.status_aktif = '1' then 1 else 0 end), 0) as aktif, count(p.id) as terdaftar")
            ->orderByRaw("{$kolom} {$dir}")->orderBy('d.nama_divisi')
            ->limit(1000)->get();

        return response()->json(['data' => $rows->map(fn ($x) => [
            'id' => $x->id, 'nama' => $x->nama_divisi, 'total' => (int) $x->total, 'aktif' => (int) $x->aktif, 'terdaftar' => (int) $x->terdaftar,
        ])->values()]);
    }
}
