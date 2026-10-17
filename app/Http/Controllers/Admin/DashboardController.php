<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Divisi;
use App\Models\Karyawan;
use App\Models\Pengaturan;
use App\Models\Titik;
use App\Support\Kalender;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $hari = now()->toDateString();
        $total = Pengaturan::where('aktif', true)->count();
        $libur = Kalender::info($hari);

        $hadirHariIni = Absensi::where('tanggal', $hari)->count();
        $telatHariIni = Absensi::where('tanggal', $hari)->whereIn('status', ['telat', 'terlambat'])->count();
        $manual = DB::table('kehadiran_manual')->where('tanggal', $hari)->count();
        $belum = $libur ? 0 : max(0, $total - $hadirHariIni - $manual);

        // 14 hari terakhir
        $dari = now()->subDays(13)->toDateString();
        $perHari = Absensi::selectRaw("tanggal, count(*) as total, sum(case when status in ('telat','terlambat') then 1 else 0 end) as telat")
            ->whereBetween('tanggal', [$dari, $hari])->groupBy('tanggal')->get()->keyBy(fn ($x) => substr((string) $x->tanggal, 0, 10));
        $seri = [];
        for ($i = 13; $i >= 0; $i--) {
            $t = now()->subDays($i);
            $row = $perHari[$t->toDateString()] ?? null;
            $seri[] = ['tgl' => $t->format('d/m'), 'hadir' => (int) ($row->total ?? 0), 'telat' => (int) ($row->telat ?? 0)];
        }

        $terbaru = DB::table('absensi as a')->join(Karyawan::tabel().' as k', 'k.nik', '=', 'a.nik')
            ->leftJoin(Divisi::tabel().' as d', 'd.id', '=', 'k.divisi_id')
            ->where('a.tanggal', $hari)->orderByDesc('a.jam')->limit(8)
            ->get(['k.nama', 'd.nama_divisi', 'a.jam', 'a.status', 'a.menit_telat', 'a.titik_nama']);

        return view('admin.index', [
            'stat' => [
                'karyawan' => Karyawan::aktif()->count(),
                'divisi' => Divisi::count(),
                'titik' => Titik::count(),
                'terdaftar' => $total,
                'hadir' => $hadirHariIni,
                'telat' => $telatHariIni,
                'belum' => $belum,
            ],
            'libur' => $libur,
            'terbaru' => $terbaru,
            'chart' => [
                'donut' => [
                    ['label' => 'Tepat waktu', 'nilai' => max(0, $hadirHariIni - $telatHariIni), 'warna' => '#16a34a'],
                    ['label' => 'Telat', 'nilai' => $telatHariIni, 'warna' => '#f59e0b'],
                    ['label' => 'Izin/Sakit/dll', 'nilai' => $manual, 'warna' => '#3b82f6'],
                    ['label' => 'Belum absen', 'nilai' => $belum, 'warna' => '#e11d48'],
                ],
                'seri' => $seri,
            ],
        ]);
    }
}
