<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportPresensiJob;
use App\Models\Divisi;
use App\Models\ImportJob;
use App\Models\Karyawan;
use App\Models\Pengaturan;
use App\Services\PengaturanService;
use App\Support\Aktivitas;
use App\Support\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $kt = '`' . str_replace('.', '`.`', Karyawan::tabel()) . '`';
        $dt = '`' . str_replace('.', '`.`', Divisi::tabel()) . '`';
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
            ->when($q !== '', fn($x) => $x->whereHas('karyawan', fn($i) => $i->where(fn($w) => $w
                ->where('nama', 'like', "%{$q}%")->orWhere('nik', 'like', "%{$q}%"))))
            ->when($divisi > 0, fn($x) => $x->whereHas('karyawan', fn($i) => $i->where('divisi_id', $divisi)))
            ->when(isset($urut[$r->query('sort')]), fn($x) => $x->orderByRaw($urut[$r->query('sort')]))
            ->orderByDesc('id')->paginate(20);

        $ikut = $page->getCollection()->pluck('ikut_nik')->filter()->unique()->values();
        $namaIkut = Karyawan::whereIn('nik', $ikut)->pluck('nama', 'nik');
        $jmlIkut = Pengaturan::withCount('titik')->whereIn('nik', $ikut)->pluck('titik_count', 'nik');

        $data = $page->getCollection()->map(fn($p) => [
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
            ->when($q !== '', fn($x) => $x->where(fn($w) => $w
                ->where('nama', 'like', "%{$q}%")->orWhere('nik', 'like', "%{$q}%")))
            ->orderBy('nama')->limit(50)->get()
            ->map(fn($k) => [
                'nik' => $k->nik,
                'nama' => $k->nama,
                'jabatan' => $k->namaJabatan(),
                'divisi' => $k->divisi->nama_divisi ?? '-',
                'is_manager' => $k->isManager()
            ]);

        $asmen = Pengaturan::with('karyawan.jabatan')->withCount('titik')->whereNull('ikut_nik')->get()
            ->filter(fn($p) => $p->karyawan && $p->karyawan->isAsmen())
            ->map(fn($p) => [
                'nik' => $p->nik,
                'nama' => $p->karyawan->nama,
                'jabatan' => $p->karyawan->namaJabatan(),
                'jumlah_titik' => $p->titik_count
            ])
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
            'titik' => $p->titik->map(fn($t) => [
                'nama' => $t->nama,
                'src' => $t->lat . ',' . $t->lng,
                'radius' => $t->radius,
            ])->values(),
        ]);
    }

    public function simpan(Request $r): JsonResponse
    {
        $d = $r->validate([
            'nik' => ['required', 'string', 'exists:indukk.karyawans,nik'],
            'telat' => ['required', 'boolean'],
            'jam_batas' => [Rule::requiredIf(fn() => $r->boolean('telat')), 'nullable', 'date_format:H:i'],
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
                    throw ValidationException::withMessages(['titik' => 'Titik ke-' . ($i + 1) . ': koordinat/link tidak valid.']);
                }
                $nama = trim((string) ($row['nama'] ?? '')) ?: 'Titik ' . ($i + 1);
                $titik[] = $c + ['nama' => $nama, 'radius' => (int) ($row['radius'] ?? 0) ?: config('hadirku.radius_default')];
            }
        }

        $baru = ! Pengaturan::where('nik', $d['nik'])->exists();
        $this->svc->simpan($d['nik'], (bool) $d['telat'], $d['jam_batas'] ?? null, $d['jam_tutup'] ?? null, $d['tipe'], $ikut, $titik);
        Aktivitas::catat('presensi', $baru ? 'tambah' : 'ubah', ($baru ? 'Menambah' : 'Mengubah') . " pengaturan presensi {$k->nama} ({$k->nik})" . ($ikut ? ", titik ikut asmen {$ikut}" : ', ' . count($titik) . ' titik'));

        return response()->json(['ok' => true]);
    }

    public function toggle(string $nik): JsonResponse
    {
        $p = Pengaturan::where('nik', $nik)->firstOrFail();
        $p->update(['aktif' => ! $p->aktif]);
        $nama = Karyawan::where('nik', $nik)->value('nama') ?? $nik;
        Aktivitas::catat('presensi', 'status', ($p->aktif ? 'Mengaktifkan' : 'Menonaktifkan') . " login/presensi karyawan {$nama} ({$nik})", $p->aktif ? 'info' : 'warning');

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

    // ---------- Import Excel (dibaca di browser, diproses di latar belakang lewat queue) ----------

    /** Terima baris Excel, simpan sebagai pekerjaan latar belakang, langsung balas dengan id untuk dipantau progresnya. */
    public function import(Request $r): JsonResponse
    {
        $r->validate([
            'rows' => 'required|array|min:1|max:5000',
            'nama_file' => 'nullable|string|max:150',
        ], [
            'rows.required' => 'File kosong.',
            'rows.max' => 'Maksimal 5.000 baris per file.',
        ]);

        $sibuk = ImportJob::where('user_id', $r->user()->id)->whereIn('status', ['menunggu', 'berjalan'])
            ->where('updated_at', '>', now()->subMinutes(15))->exists();
        if ($sibuk) {
            return response()->json(['message' => 'Masih ada proses import yang berjalan. Tunggu sampai selesai.'], 422);
        }

        $job = ImportJob::create([
            'user_id' => $r->user()->id,
            'tipe' => 'presensi',
            'nama_file' => $r->input('nama_file'),
            'status' => 'menunggu',
            'total' => count($r->input('rows')),
        ]);
        $job->update(['path' => "imports/import-{$job->id}.json"]);
        Storage::disk('local')->put($job->path, json_encode(array_values($r->input('rows')), JSON_UNESCAPED_UNICODE));

        config('hadirku.import_queue', true)
            ? ImportPresensiJob::dispatch($job->id)
            : ImportPresensiJob::dispatchAfterResponse($job->id); // tanpa worker: jalan setelah respons dikirim

        return response()->json($job->ringkas(), 202);
    }

    /** Import milik user yang masih berjalan, atau yang sudah selesai tapi hasilnya belum ditutup (untuk widget di semua menu). */
    public function importAktif(Request $r): JsonResponse
    {
        $job = ImportJob::where('user_id', $r->user()->id)
            ->where(fn($q) => $q->whereIn('status', ['menunggu', 'berjalan'])->orWhere('dilihat', false))
            ->latest('id')->first();

        if ($job && $job->aktif() && $job->updated_at->lt(now()->subMinutes(15))) {
            $job->update([
                'status' => 'gagal',
                'selesai_at' => now(),
                'pesan' => 'Proses terhenti. Pastikan queue worker berjalan (php artisan queue:work) lalu coba lagi.'
            ]);
        }

        return response()->json(['job' => $job?->fresh()->ringkas()]);
    }

    public function importShow(Request $r, int $id): JsonResponse
    {
        return response()->json(ImportJob::where('user_id', $r->user()->id)->findOrFail($id)->ringkas());
    }

    public function importTutup(Request $r, int $id): JsonResponse
    {
        ImportJob::where('user_id', $r->user()->id)->whereKey($id)->whereNotIn('status', ['menunggu', 'berjalan'])->update(['dilihat' => true]);

        return response()->json(['ok' => true]);
    }

    /** Template Excel kosong (tanpa contoh data); keterangan kolom ada di sheet "Petunjuk". */
    public function template()
    {
        return response()->download(
            public_path('template/template-import-presensi.xlsx'),
            'template-import-presensi.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
