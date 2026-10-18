<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Models\Karyawan;
use App\Models\Pengaturan;
use App\Services\PengaturanService;
use App\Support\Aktivitas;
use App\Support\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengaturanController extends Controller
{
    public function __construct(private PengaturanService $svc) {}

    public function index(Request $r): JsonResponse
    {
        $q = trim((string) $r->query('q', ''));
        $divisi = (int) $r->query('divisi', 0);

        $dir = strtolower((string) $r->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $kt = '`'.str_replace('.', '`.`', Karyawan::tabel()).'`';
        $dt = '`'.str_replace('.', '`.`', Divisi::tabel()).'`';
        // jumlah titik yang berlaku (titik sendiri, atau titik asmen jika manager ikut asmen)
        $jml = '(select count(*) from titik_lahan tl join pengaturan_absen pa on pa.id = tl.pengaturan_id where pa.nik = coalesce(pengaturan_absen.ikut_nik, pengaturan_absen.nik))';
        $urut = [
            'nama' => "(select k.nama from {$kt} k where k.nik = pengaturan_absen.nik limit 1) {$dir}",
            'divisi' => "(select d.nama_divisi from {$kt} k join {$dt} d on d.id = k.divisi_id where k.nik = pengaturan_absen.nik limit 1) {$dir}",
            'jam' => "pengaturan_absen.jam_batas is null, pengaturan_absen.jam_batas {$dir}",
            'titik1' => "case when pengaturan_absen.tipe_titik = 'single' and pengaturan_absen.ikut_nik is null then {$jml} else -1 end {$dir}",
            'titikmulti' => "case when pengaturan_absen.ikut_nik is not null or pengaturan_absen.tipe_titik = 'multi' then {$jml} else -1 end {$dir}",
            'aktif' => "pengaturan_absen.aktif {$dir}",
        ];

        $page = Pengaturan::query()->with(['karyawan.jabatan', 'karyawan.divisi'])->withCount('titik')
            ->when($q !== '', fn ($x) => $x->whereHas('karyawan', fn ($i) => $i->where(fn ($w) => $w
                ->where('nama', 'like', "%{$q}%")->orWhere('nik', 'like', "%{$q}%"))))
            ->when($divisi > 0, fn ($x) => $x->whereHas('karyawan', fn ($i) => $i->where('divisi_id', $divisi)))
            ->when(isset($urut[$r->query('sort')]), fn ($x) => $x->orderByRaw($urut[$r->query('sort')]))
            ->orderByDesc('id')->paginate(20);

        $ikut = $page->getCollection()->pluck('ikut_nik')->filter()->unique()->values();
        $namaIkut = Karyawan::whereIn('nik', $ikut)->pluck('nama', 'nik');
        $jmlIkut = Pengaturan::withCount('titik')->whereIn('nik', $ikut)->pluck('titik_count', 'nik');

        $data = $page->getCollection()->map(fn ($p) => [
            'nik' => $p->nik,
            'nama' => $p->karyawan->nama ?? $p->nik,
            'jabatan' => $p->karyawan->jabatan->nama_jabatan ?? '-',
            'divisi' => $p->karyawan->divisi->nama_divisi ?? '-',
            'telat' => $p->telat,
            'jam_batas' => $p->jamBatas(),
            'jam_tutup' => $p->jamTutup(),
            'tipe' => $p->tipe_titik,
            'ikut_nik' => $p->ikut_nik,
            'ikut_nama' => $p->ikut_nik ? ($namaIkut[$p->ikut_nik] ?? $p->ikut_nik) : null,
            'jumlah_titik' => $p->ikut_nik ? ($jmlIkut[$p->ikut_nik] ?? 0) : $p->titik_count,
            'aktif' => $p->aktif,
        ])->values();

        return response()->json([
            'data' => $data,
            'meta' => ['halaman' => $page->currentPage(), 'terakhir' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Karyawan aktif dari db_indukk yang belum punya pengaturan + daftar asmen untuk opsi manager */
    public function induk(Request $r): JsonResponse
    {
        $q = trim((string) $r->query('q', ''));
        $tersedia = Karyawan::query()->with(['jabatan', 'divisi'])->aktif()
            ->whereNotIn('nik', Pengaturan::select('nik'))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w
                ->where('nama', 'like', "%{$q}%")->orWhere('nik', 'like', "%{$q}%")))
            ->orderBy('nama')->limit(50)->get()
            ->map(fn ($k) => ['nik' => $k->nik, 'nama' => $k->nama, 'jabatan' => $k->namaJabatan(),
                'divisi' => $k->divisi->nama_divisi ?? '-', 'is_manager' => $k->isManager()]);

        $asmen = Pengaturan::with('karyawan.jabatan')->withCount('titik')->whereNull('ikut_nik')->get()
            ->filter(fn ($p) => $p->karyawan && $p->karyawan->isAsmen())
            ->map(fn ($p) => ['nik' => $p->nik, 'nama' => $p->karyawan->nama, 'jabatan' => $p->karyawan->namaJabatan(),
                'jumlah_titik' => $p->titik_count])
            ->values();

        return response()->json([
            'tersedia' => $tersedia,
            'asmen' => $asmen,
            'divisi' => Divisi::orderBy('nama_divisi')->get(['id', 'nama_divisi']),
        ]);
    }

    public function show(string $nik): JsonResponse
    {
        $p = Pengaturan::with(['karyawan.jabatan', 'karyawan.divisi', 'titik'])->where('nik', $nik)->firstOrFail();

        return response()->json([
            'nik' => $p->nik,
            'nama' => $p->karyawan->nama ?? $p->nik,
            'jabatan' => $p->karyawan?->namaJabatan() ?: '-',
            'divisi' => $p->karyawan->divisi->nama_divisi ?? '-',
            'is_manager' => (bool) ($p->karyawan && $p->karyawan->isManager()),
            'telat' => $p->telat,
            'jam_batas' => $p->jamBatas(),
            'jam_tutup' => $p->jamTutup(),
            'tipe' => $p->tipe_titik,
            'ikut_nik' => $p->ikut_nik,
            'titik' => $p->titik->map(fn ($t) => [
                'nama' => $t->nama, 'src' => $t->lat.','.$t->lng, 'radius' => $t->radius,
            ])->values(),
        ]);
    }

    public function simpan(Request $r): JsonResponse
    {
        $d = $r->validate([
            'nik' => ['required', 'string', 'exists:indukk.karyawans,nik'],
            'telat' => ['required', 'boolean'],
            'jam_batas' => [Rule::requiredIf(fn () => $r->boolean('telat')), 'nullable', 'date_format:H:i'],
            'jam_tutup' => ['nullable', 'date_format:H:i'],
            'tipe' => ['required', Rule::in(['single', 'multi'])],
            'sumber' => ['required', Rule::in(['baru', 'asmen'])],
            'ikut_nik' => ['nullable', 'string'],
            'titik' => ['nullable', 'array', 'max:5000'],
            'titik.*.nama' => ['nullable', 'string', 'max:100'],
            'titik.*.src' => ['nullable', 'string', 'max:1000'],
            'titik.*.radius' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], [
            'jam_batas.required' => 'Jam batas telat wajib diisi jika memilih Telat.',
            'jam_batas.date_format' => 'Format jam harus HH:MM.',
            'jam_tutup.date_format' => 'Format jam tutup harus HH:MM.',
            'nik.exists' => 'Karyawan tidak ditemukan di db_indukk.',
        ]);

        if (! empty($d['jam_tutup']) && ! empty($d['jam_batas']) && $d['telat'] && $d['jam_tutup'] <= $d['jam_batas']) {
            throw ValidationException::withMessages(['jam_tutup' => 'Jam tutup harus lebih besar dari jam batas telat.']);
        }

        $k = Karyawan::with('jabatan')->where('nik', $d['nik'])->firstOrFail();
        $ikut = null;
        $titik = [];

        if ($d['sumber'] === 'asmen') {
            if (! $k->isManager()) {
                throw ValidationException::withMessages(['sumber' => 'Hanya Manager yang bisa memakai titik asmen.']);
            }
            $ikut = $d['ikut_nik'] ?? null;
            $asmen = $ikut ? Pengaturan::where('nik', $ikut)->whereNull('ikut_nik')->first() : null;
            if (! $asmen || $ikut === $k->nik) {
                throw ValidationException::withMessages(['ikut_nik' => 'Asmen belum terdaftar atau tidak valid.']);
            }
        } else {
            $rows = $d['titik'] ?? [];
            if (! count($rows)) {
                throw ValidationException::withMessages(['titik' => 'Isi minimal 1 titik.']);
            }
            if ($d['tipe'] === 'single') {
                $rows = array_slice($rows, 0, 1);
            }
            foreach ($rows as $i => $row) {
                $c = Geo::parse($row['src'] ?? '');
                if (! $c) {
                    throw ValidationException::withMessages(['titik' => 'Titik ke-'.($i + 1).': koordinat/link tidak valid.']);
                }
                $nama = trim((string) ($row['nama'] ?? '')) ?: 'Titik '.($i + 1);
                $titik[] = $c + ['nama' => $nama, 'radius' => (int) ($row['radius'] ?? 0) ?: config('hadirku.radius_default')];
            }
        }

        $baru = ! Pengaturan::where('nik', $d['nik'])->exists();
        $this->svc->simpan($d['nik'], (bool) $d['telat'], $d['jam_batas'] ?? null, $d['jam_tutup'] ?? null, $d['tipe'], $ikut, $titik);
        Aktivitas::catat('presensi', $baru ? 'tambah' : 'ubah', ($baru ? 'Menambah' : 'Mengubah')." pengaturan presensi {$k->nama} ({$k->nik})".($ikut ? ", titik ikut asmen {$ikut}" : ', '.count($titik).' titik'));

        return response()->json(['ok' => true]);
    }

    public function toggle(string $nik): JsonResponse
    {
        $p = Pengaturan::where('nik', $nik)->firstOrFail();
        $p->update(['aktif' => ! $p->aktif]);
        $nama = Karyawan::where('nik', $nik)->value('nama') ?? $nik;
        Aktivitas::catat('presensi', 'status', ($p->aktif ? 'Mengaktifkan' : 'Menonaktifkan')." login/presensi karyawan {$nama} ({$nik})", $p->aktif ? 'info' : 'warning');

        return response()->json(['aktif' => $p->aktif]);
    }

    public function destroy(string $nik): JsonResponse
    {
        $p = Pengaturan::where('nik', $nik)->firstOrFail();
        $pakai = Pengaturan::where('ikut_nik', $nik)->pluck('nik');
        if ($pakai->isNotEmpty()) {
            $nama = Karyawan::whereIn('nik', $pakai)->pluck('nama')->take(3)->implode(', ');

            return response()->json(['message' => "Tidak bisa dihapus, titiknya dipakai manager: {$nama}."], 422);
        }
        $nama = Karyawan::where('nik', $nik)->value('nama') ?? $nik;
        $p->delete();
        Aktivitas::catat('presensi', 'hapus', "Menghapus pengaturan presensi {$nama} ({$nik})", 'warning');

        return response()->json(['ok' => true]);
    }

    // ---------- Import Excel/CSV (dibaca di browser, divalidasi di sini) ----------
    private const ALIAS = [
        'nik' => 'nik', 'jambatas' => 'jam', 'jam' => 'jam', 'jamtutup' => 'tutup', 'telat' => 'telat',
        'namatitik' => 'nama', 'nama' => 'nama', 'koordinatataulink' => 'src', 'koordinat' => 'src',
        'link' => 'src', 'radiusm' => 'radius', 'radius' => 'radius', 'ikutnikasmen' => 'ikut', 'ikutnik' => 'ikut',
    ];

    /** NIK karyawan 8 digit. Excel sering membuang nol di depan, jadi dilengkapi lagi. */
    private function nik(mixed $v): string
    {
        $n = preg_replace('/\D/', '', (string) $v);

        return ($n !== '' && strlen($n) < 8) ? str_pad($n, 8, '0', STR_PAD_LEFT) : $n;
    }

    private function jam(mixed $v): ?string
    {
        if (is_numeric($v) && (float) $v > 0 && (float) $v < 1) { // pecahan hari dari Excel
            $mnt = (int) round((float) $v * 1440);

            return sprintf('%02d:%02d', intdiv($mnt, 60) % 24, $mnt % 60);
        }
        $v = trim((string) $v);
        if (preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?$/', $v, $m) && $m[1] < 24 && $m[2] < 60) {
            return sprintf('%02d:%02d', $m[1], $m[2]);
        }

        return null;
    }

    public function import(Request $r): JsonResponse
    {
        $r->validate(['rows' => 'required|array|min:1|max:5000']);

        $groups = [];
        $err = [];
        $bad = [];

        foreach ($r->input('rows') as $i => $raw) {
            $line = $i + 2;
            $row = [];
            foreach ((array) $raw as $k => $v) {
                $key = self::ALIAS[strtolower(preg_replace('/[^a-z]/i', '', (string) $k))] ?? null;
                if ($key) {
                    $row[$key] = is_scalar($v) ? $v : '';
                }
            }
            $nik = $this->nik($row['nik'] ?? '');
            if ($nik === '') {
                continue; // baris kosong
            }
            $key = 'n'.$nik; // hindari kunci numerik otomatis jadi int
            $g = &$groups[$key];
            $g ??= ['nik' => $nik, 'telat' => null, 'jam' => null, 'tutup' => null, 'ikut' => null, 'titik' => [], 'line' => $line];

            if ($g['telat'] === null) {
                $jamRaw = $row['jam'] ?? '';
                $telatRaw = strtolower(trim((string) ($row['telat'] ?? '')));
                $g['telat'] = $telatRaw === '' ? ($jamRaw !== '' && $jamRaw !== null)
                    : in_array($telatRaw, ['ya', 'y', '1', 'true', 'telat'], true);
                if ($g['telat']) {
                    $g['jam'] = $this->jam($jamRaw);
                    if (! $g['jam']) {
                        $err[] = "Baris {$line}: Jam_Batas wajib berformat HH:MM jika Telat = Ya.";
                        $bad[$key] = true;
                    }
                }
                $tutupRaw = $row['tutup'] ?? '';
                if ($tutupRaw !== '' && $tutupRaw !== null) {
                    $g['tutup'] = $this->jam($tutupRaw);
                    if (! $g['tutup']) {
                        $err[] = "Baris {$line}: Jam_Tutup harus berformat HH:MM.";
                        $bad[$key] = true;
                    }
                }
                $ik = $this->nik($row['ikut'] ?? '');
                $g['ikut'] = $ik ?: null;
            }

            if (! $g['ikut']) {
                $c = Geo::parse((string) ($row['src'] ?? ''));
                if (! $c) {
                    $err[] = "Baris {$line}: koordinat/link tidak valid (NIK {$nik}).";
                    $bad[$key] = true;
                } else {
                    $rad = (int) ($row['radius'] ?? 0);
                    $g['titik'][] = $c + [
                        'nama' => trim((string) ($row['nama'] ?? '')) ?: 'Titik '.(count($g['titik']) + 1),
                        'radius' => $rad > 0 ? min($rad, 100000) : config('hadirku.radius_default'),
                    ];
                }
            }
            unset($g);
        }

        $kar = Karyawan::with('jabatan')->whereIn('nik', array_column($groups, 'nik'))->get()->keyBy('nik');
        $berhasil = 0;

        DB::transaction(function () use (&$groups, &$err, &$bad, $kar, &$berhasil) {
            foreach ($groups as $key => $g) {
                if (isset($bad[$key])) {
                    continue;
                }
                $nik = $g['nik'];
                $k = $kar[$nik] ?? null;
                if (! $k) {
                    $err[] = "Baris {$g['line']}: NIK {$nik} tidak ada di db_indukk.";
                    continue;
                }
                if ($g['ikut']) {
                    $as = Pengaturan::where('nik', $g['ikut'])->whereNull('ikut_nik')->first();
                    if (! $k->isManager() || ! $as || $g['ikut'] === $nik) {
                        $err[] = "Baris {$g['line']}: Ikut_NIK_Asmen hanya untuk Manager dan asmen harus sudah terdaftar.";
                        continue;
                    }
                }
                if ($g['tutup'] && $g['telat'] && $g['tutup'] <= $g['jam']) {
                    $err[] = "Baris {$g['line']}: Jam_Tutup harus lebih besar dari Jam_Batas.";
                    continue;
                }
                $tipe = count($g['titik']) > 1 ? 'multi' : 'single';
                $this->svc->simpan($nik, (bool) $g['telat'], $g['jam'], $g['tutup'], $tipe, $g['ikut'], $g['titik']);
                $berhasil++;
            }
        });

        Aktivitas::catat('presensi', 'import', "Import pengaturan presensi: {$berhasil} karyawan berhasil, ".(count($groups) - $berhasil).' dilewati', count($err) ? 'warning' : 'info');

        return response()->json([
            'berhasil' => $berhasil,
            'gagal' => count($groups) - $berhasil,
            'errors' => array_slice($err, 0, 50),
            'errors_lainnya' => max(0, count($err) - 50),
        ]);
    }

    public function template()
    {
        $nik = fn ($n) => '="'.$n.'"'; // agar Excel tidak membuang nol di depan NIK
        $rows = [
            ['NIK', 'Jam_Batas', 'Telat', 'Jam_Tutup', 'Nama_Titik', 'Koordinat_atau_Link', 'Radius_m', 'Ikut_NIK_Asmen'],
            [$nik('99720014'), '08:00', 'Ya', '17:00', 'Lahan A', '-8.2192,114.3691', 150, ''],
            [$nik('99720014'), '08:00', 'Ya', '17:00', 'Lahan B', 'https://www.google.com/maps/@-8.2301,114.3802,17z', 200, ''],
            [$nik('02820009'), '', 'Tidak', '', 'Lahan A', '-8.2105,114.3555', 100, ''],
            [$nik('96700008'), '', 'Tidak', '', '', '', '', $nik('99720014')],
        ];

        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($f, $row);
            }
            fclose($f);
        }, 'template_titik_presensi.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
