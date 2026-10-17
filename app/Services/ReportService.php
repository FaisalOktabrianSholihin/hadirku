<?php

namespace App\Services;

use App\Models\Divisi;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Keterangan;
use App\Support\Kalender;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Rekap kehadiran per karyawan, dikelompokkan per divisi.
     * Prioritas per hari: keterangan manual admin > absen asli (hadir/telat) > libur (dilewati) > alpa.
     * Hari ini baru dihitung alpa jika karyawan punya jam tutup dan sudah lewat.
     */
    public function build(string $dari, string $sampai, ?int $divisi = null, string $q = ''): array
    {
        $today = now()->toDateString();
        $kolom = Keterangan::where('aktif', true)
            ->where(fn ($w) => $w->whereNull('sistem')->orWhere('sistem', '!=', 'libur'))
            ->orderBy('urutan')->orderBy('id')->get();
        $sis = Keterangan::whereIn('sistem', ['hadir', 'telat', 'alpa'])->pluck('id', 'sistem')->all();
        $kal = Kalender::rentang($dari, $sampai);

        $emp = DB::table('pengaturan_absen as p')
            ->join(Karyawan::tabel().' as k', 'k.nik', '=', 'p.nik')
            ->leftJoin(Divisi::tabel().' as d', 'd.id', '=', 'k.divisi_id')
            ->leftJoin(Jabatan::tabel().' as j', 'j.id', '=', 'k.jabatan_id')
            ->where('p.aktif', true)
            ->when($divisi, fn ($x) => $x->where('k.divisi_id', $divisi))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w
                ->where('k.nama', 'like', "%{$q}%")->orWhere('k.nik', 'like', "%{$q}%")))
            ->orderBy('d.nama_divisi')->orderBy('k.nama')
            ->select('k.nik', 'k.nama', 'k.tanggal_masuk', 'd.id as divisi_id', 'd.nama_divisi',
                'j.nama_jabatan', 'p.created_at as mulai', 'p.jam_tutup')
            ->get();

        $abs = [];
        foreach (DB::table('absensi')->whereBetween('tanggal', [$dari, $sampai])
            ->select('nik', 'tanggal', 'status', 'menit_telat')->cursor() as $a) {
            $abs[$a->nik][substr($a->tanggal, 0, 10)] = ['status' => $a->status, 'menit' => (int) $a->menit_telat];
        }
        $man = [];
        foreach (DB::table('kehadiran_manual')->whereBetween('tanggal', [$dari, $sampai])
            ->select('nik', 'tanggal', 'keterangan_id', 'menit')->cursor() as $m) {
            $man[$m->nik][substr($m->tanggal, 0, 10)] = ['k' => (int) $m->keterangan_id, 'menit' => (int) $m->menit];
        }

        $akhir = min($sampai, $today);
        $jamSekarang = now()->format('H:i');
        $grup = [];

        foreach ($emp as $e) {
            $mulai = max($dari, substr((string) $e->mulai, 0, 10),
                $e->tanggal_masuk ? substr($e->tanggal_masuk, 0, 10) : $dari);
            $cnt = [];
            $mnt = [];

            for ($d = $mulai; $d <= $akhir; $d = date('Y-m-d', strtotime($d.' +1 day'))) {
                if ($m = $man[$e->nik][$d] ?? null) {
                    $k = $m['k'];
                    $cnt[$k] = ($cnt[$k] ?? 0) + 1;
                    if ($m['menit']) {
                        $mnt[$k] = ($mnt[$k] ?? 0) + $m['menit'];
                    }
                    continue;
                }
                if ($a = $abs[$e->nik][$d] ?? null) {
                    $telat = in_array($a['status'], ['telat', 'terlambat'], true);
                    $k = $telat ? ($sis['telat'] ?? 0) : ($sis['hadir'] ?? 0);
                    $cnt[$k] = ($cnt[$k] ?? 0) + 1;
                    if ($telat) {
                        $mnt[$k] = ($mnt[$k] ?? 0) + $a['menit'];
                    }
                    continue;
                }
                if (isset($kal[$d])) {
                    continue;
                }
                if ($d === $today && ! ($e->jam_tutup && $jamSekarang > substr($e->jam_tutup, 0, 5))) {
                    continue;
                }
                $k = $sis['alpa'] ?? 0;
                $cnt[$k] = ($cnt[$k] ?? 0) + 1;
            }

            $gid = (int) ($e->divisi_id ?? 0);
            $grup[$gid] ??= ['id' => $gid, 'nama' => $e->nama_divisi ?? 'Tanpa Divisi', 'jumlah' => 0,
                'hitung' => [], 'menit' => [], 'karyawan' => []];
            $grup[$gid]['jumlah']++;
            foreach ($cnt as $k => $v) {
                $grup[$gid]['hitung'][$k] = ($grup[$gid]['hitung'][$k] ?? 0) + $v;
            }
            foreach ($mnt as $k => $v) {
                $grup[$gid]['menit'][$k] = ($grup[$gid]['menit'][$k] ?? 0) + $v;
            }
            $grup[$gid]['karyawan'][] = ['nik' => $e->nik, 'nama' => $e->nama,
                'jabatan' => $e->nama_jabatan ?? '-', 'hitung' => $cnt, 'menit' => $mnt];
        }

        uasort($grup, fn ($a, $b) => ($a['id'] === 0) <=> ($b['id'] === 0) ?: strcmp($a['nama'], $b['nama']));

        $kerja = 0;
        $libur = 0;
        for ($d = $dari; $d <= $sampai; $d = date('Y-m-d', strtotime($d.' +1 day'))) {
            isset($kal[$d]) ? $libur++ : $kerja++;
        }

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'hari_kerja' => $kerja,
            'hari_libur' => $libur,
            'jumlah_karyawan' => $emp->count(),
            'kolom' => $kolom->map(fn ($k) => ['id' => $k->id, 'kode' => $k->kode, 'nama' => $k->nama,
                'warna' => $k->warna, 'menit' => $k->pakai_menit])->values(),
            'divisi' => array_values($grup),
        ];
    }
}
