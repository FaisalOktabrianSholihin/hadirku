<?php

namespace App\Services;

use App\Models\ImportJob;
use App\Models\Karyawan;
use App\Models\Pengaturan;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\Geo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Memproses baris Excel import pengaturan presensi dan memperbarui progres (persen) di tabel import_jobs.
 * Dijalankan oleh ImportPresensiJob sehingga aman walau user berpindah menu / menutup popup.
 */
class ImportPresensiService
{
    private const ALIAS = [
        'nik' => 'nik', 'jambatas' => 'jam', 'jam' => 'jam', 'jamtutup' => 'tutup', 'telat' => 'telat',
        'namatitik' => 'nama', 'nama' => 'nama', 'koordinatataulink' => 'src', 'koordinat' => 'src',
        'link' => 'src', 'radiusm' => 'radius', 'radius' => 'radius', 'ikutnikasmen' => 'ikut', 'ikutnik' => 'ikut',
    ];

    private const MAKS_ERROR = 50;

    /** @var array<int, string> */
    private array $err = [];
    private int $errLebih = 0;

    public function __construct(private PengaturanService $svc) {}

    /** NIK karyawan 8 digit. Excel sering membuang nol di depan, jadi dilengkapi lagi. */
    public function nik(mixed $v): string
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

    private function catatError(string $pesan): void
    {
        if (count($this->err) < self::MAKS_ERROR) {
            $this->err[] = $pesan;
        } else {
            $this->errLebih++;
        }
    }

    public function jalankan(ImportJob $job): void
    {
        $this->err = [];
        $this->errLebih = 0;

        try {
            $job->update(['status' => 'berjalan', 'mulai_at' => now(), 'persen' => 0]);

            $rows = json_decode((string) Storage::disk('local')->get((string) $job->path), true);
            if (! is_array($rows) || ! count($rows)) {
                throw new \RuntimeException('Data import tidak ditemukan atau kosong.');
            }

            [$groups, $bad, $gagalPra] = $this->kelompokkan(array_values($rows));
            $total = count($groups);
            $job->update(['total' => $total, 'gagal' => $gagalPra, 'persen' => $total ? 2 : 100]);

            $this->simpanSemua($job, $groups, $bad, $gagalPra);
        } catch (Throwable $e) {
            report($e);
            $job->update(['status' => 'gagal', 'pesan' => Str::limit($e->getMessage(), 250, ''), 'selesai_at' => now(),
                'errors' => $this->err ?: null, 'errors_lainnya' => $this->errLebih]);
        } finally {
            if ($job->path) {
                Storage::disk('local')->delete($job->path);
            }
        }
    }

    /**
     * Tahap 1: baca semua baris, kelompokkan per NIK, validasi format.
     *
     * @return array{0: array<string, array>, 1: array<string, bool>, 2: int}
     */
    private function kelompokkan(array $rows): array
    {
        $groups = [];
        $bad = [];
        $gagalPra = 0;

        foreach ($rows as $i => $raw) {
            $line = (int) (((array) $raw)['__baris'] ?? $i + 2); // nomor baris asli di Excel
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
            if (strlen($nik) !== 8) {
                $this->catatError("Baris {$line}: NIK {$nik} harus tepat 8 digit.");
                $gagalPra++;
                continue;
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
                        $this->catatError("Baris {$line}: Jam_Batas wajib berformat HH:MM jika Telat = Ya.");
                        $bad[$key] = true;
                    }
                }
                $tutupRaw = $row['tutup'] ?? '';
                if ($tutupRaw !== '' && $tutupRaw !== null) {
                    $g['tutup'] = $this->jam($tutupRaw);
                    if (! $g['tutup']) {
                        $this->catatError("Baris {$line}: Jam_Tutup harus berformat HH:MM.");
                        $bad[$key] = true;
                    }
                }
                $ik = $this->nik($row['ikut'] ?? '');
                if ($ik !== '' && strlen($ik) !== 8) {
                    $this->catatError("Baris {$line}: Ikut_NIK_Asmen {$ik} harus tepat 8 digit.");
                    $bad[$key] = true;
                }
                $g['ikut'] = $ik ?: null;
            }

            if (! $g['ikut']) {
                $c = Geo::parse((string) ($row['src'] ?? ''));
                if (! $c) {
                    $this->catatError("Baris {$line}: koordinat/link tidak valid (NIK {$nik}).");
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

        return [$groups, $bad, $gagalPra];
    }

    /** Tahap 2: simpan per karyawan sambil memperbarui persen. */
    private function simpanSemua(ImportJob $job, array $groups, array $bad, int $gagalPra): void
    {
        $kar = collect();
        foreach (array_chunk(array_column($groups, 'nik'), 1000) as $chunk) {
            $kar = $kar->merge(Karyawan::with('jabatan')->whereIn('nik', $chunk)->get()->keyBy('nik'));
        }

        $total = count($groups);
        $berhasil = 0;
        $gagal = $gagalPra;
        $n = 0;

        foreach ($groups as $key => $g) {
            $n++;
            $ok = $this->simpanSatu($g, isset($bad[$key]), $kar[$g['nik']] ?? null);
            $ok ? $berhasil++ : $gagal++;

            if ($n % 5 === 0 || $n === $total) {
                $job->update([
                    'diproses' => $n, 'berhasil' => $berhasil, 'gagal' => $gagal,
                    'persen' => $total ? min(99, (int) floor($n / $total * 100)) : 100,
                ]);
            }
        }

        $job->update([
            'status' => 'selesai', 'persen' => 100, 'diproses' => $total, 'berhasil' => $berhasil, 'gagal' => $gagal,
            'errors' => $this->err ?: null, 'errors_lainnya' => $this->errLebih, 'selesai_at' => now(),
        ]);

        $pelaku = $job->user_id ? User::whereKey($job->user_id)->value('name') : null;
        Aktivitas::catat('presensi', 'import', "Import pengaturan presensi: {$berhasil} karyawan berhasil, {$gagal} dilewati",
            $this->err || $this->errLebih ? 'warning' : 'info', [], $pelaku, 'admin');
    }

    private function simpanSatu(array $g, bool $invalid, ?Karyawan $k): bool
    {
        if ($invalid) {
            return false; // error formatnya sudah dicatat saat tahap 1
        }
        $nik = $g['nik'];
        if (! $k) {
            $this->catatError("Baris {$g['line']}: NIK {$nik} tidak ada di db_indukk.");

            return false;
        }
        if ($g['ikut']) {
            $as = Pengaturan::where('nik', $g['ikut'])->whereNull('ikut_nik')->first();
            if (! $k->isManager() || ! $as || $g['ikut'] === $nik) {
                $this->catatError("Baris {$g['line']}: Ikut_NIK_Asmen hanya untuk Manager dan asmen harus sudah terdaftar.");

                return false;
            }
        }
        if ($g['tutup'] && $g['telat'] && $g['tutup'] <= $g['jam']) {
            $this->catatError("Baris {$g['line']}: Jam_Tutup harus lebih besar dari Jam_Batas.");

            return false;
        }

        try {
            $tipe = count($g['titik']) > 1 ? 'multi' : 'single';
            $this->svc->simpan($nik, (bool) $g['telat'], $g['jam'], $g['tutup'], $tipe, $g['ikut'], $g['titik']);
        } catch (Throwable $e) {
            report($e);
            $this->catatError("Baris {$g['line']}: gagal menyimpan NIK {$nik}.");

            return false;
        }

        return true;
    }
}
