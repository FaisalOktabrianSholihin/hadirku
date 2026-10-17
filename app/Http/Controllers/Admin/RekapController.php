<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Divisi;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Pengaturan;
use App\Support\Kalender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** Absensi harian: daftar siapa sudah/belum absen pada satu tanggal, lengkap dengan foto */
class RekapController extends Controller
{
    public function index(Request $r): View
    {
        $tgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r->query('tanggal')) ? $r->query('tanggal') : now()->toDateString();
        $q = trim((string) $r->query('q', ''));
        $status = $r->query('status', '');
        $divisi = (int) $r->query('divisi', 0);

        $rows = DB::table('pengaturan_absen as p')
            ->join(Karyawan::tabel().' as k', 'k.nik', '=', 'p.nik')
            ->leftJoin(Divisi::tabel().' as d', 'd.id', '=', 'k.divisi_id')
            ->leftJoin(Jabatan::tabel().' as j', 'j.id', '=', 'k.jabatan_id')
            ->leftJoin('absensi as a', fn ($jn) => $jn->on('a.nik', '=', 'p.nik')->where('a.tanggal', $tgl))
            ->where('p.aktif', true)
            ->when($divisi > 0, fn ($x) => $x->where('k.divisi_id', $divisi))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w
                ->where('k.nama', 'like', "%{$q}%")->orWhere('k.nik', 'like', "%{$q}%")))
            ->when($status === 'hadir', fn ($x) => $x->whereNotNull('a.id'))
            ->when($status === 'telat', fn ($x) => $x->whereIn('a.status', ['telat', 'terlambat']))
            ->when($status === 'belum', fn ($x) => $x->whereNull('a.id'))
            ->orderBy('k.nama')
            ->select('k.nama', 'k.nik', 'j.nama_jabatan', 'd.nama_divisi', 'a.id as absen_id', 'a.jam',
                'a.titik_nama', 'a.jarak', 'a.status', 'a.menit_telat')
            ->simplePaginate(25)->withQueryString();

        $aktif = Pengaturan::where('aktif', true)->count();
        $hadir = Absensi::where('tanggal', $tgl)->count();
        $telat = Absensi::where('tanggal', $tgl)->whereIn('status', ['telat', 'terlambat'])->count();
        $libur = Kalender::info($tgl);
        $daftarDivisi = Divisi::orderBy('nama_divisi')->get(['id', 'nama_divisi']);

        return view('admin.absensi', compact('rows', 'tgl', 'q', 'status', 'divisi', 'aktif', 'hadir', 'telat', 'libur', 'daftarDivisi'));
    }

    public function export(Request $r)
    {
        $dari = $r->query('dari', now()->toDateString());
        $sampai = $r->query('sampai', $dari);

        return response()->streamDownload(function () use ($dari, $sampai) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Tanggal', 'Jam', 'NIK', 'Nama', 'Divisi', 'Jabatan', 'Lahan', 'Jarak (m)', 'Radius (m)', 'Latitude', 'Longitude', 'Status', 'Telat (menit)']);
            DB::table('absensi as a')->leftJoin(Karyawan::tabel().' as k', 'k.nik', '=', 'a.nik')
                ->leftJoin(Divisi::tabel().' as d', 'd.id', '=', 'k.divisi_id')->leftJoin(Jabatan::tabel().' as j', 'j.id', '=', 'k.jabatan_id')
                ->whereBetween('a.tanggal', [$dari, $sampai])->orderBy('a.tanggal')->orderBy('a.jam')
                ->select('a.*', 'k.nama', 'd.nama_divisi', 'j.nama_jabatan')->cursor()->each(function ($a) use ($f) {
                    fputcsv($f, [$a->tanggal, $a->jam, '="'.$a->nik.'"', $a->nama, $a->nama_divisi, $a->nama_jabatan, $a->titik_nama,
                        $a->jarak, $a->radius, $a->lat, $a->lng, $a->status, $a->menit_telat]);
                });
            fclose($f);
        }, "absensi_{$dari}_{$sampai}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function foto(Absensi $absensi)
    {
        return Storage::disk('local')->response($absensi->foto);
    }
}
