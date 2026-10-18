<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\Pengaturan;
use App\Support\Aktivitas;
use App\Support\Geo;
use App\Support\Kalender;
use App\Support\Kecurangan;
use App\Support\Waktu;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AbsenController extends Controller
{
    private function bukti(?Absensi $a): ?array
    {
        if (! $a) {
            return null;
        }

        return [
            'jam' => substr($a->jam, 0, 8),
            'titik' => $a->titik_nama,
            'jarak' => $a->jarak,
            'radius' => $a->radius,
            'status' => $a->status === 'terlambat' ? 'telat' : $a->status,
            'menit_telat' => (int) $a->menit_telat,
            'foto_url' => route('absen.foto'),
        ];
    }

    private function namaKaryawan(Request $r): string
    {
        $nik = (string) $r->session()->get('nik');

        return Karyawan::where('nik', $nik)->value('nama') ?? $nik;
    }

    /** Respons standar saat kecurangan terdeteksi (HTTP 422 + kode agar front-end menampilkan pop up khusus) */
    private function curang(Request $r, string $kode, string $pesan, array $bukti, string $logAksi, string $logDeskripsi): JsonResponse
    {
        $r->session()->forget('absen_nonce');
        $nama = $this->namaKaryawan($r);
        Aktivitas::catat('absen', $logAksi, "{$nama} ({$r->session()->get('nik')}): {$logDeskripsi}", 'danger', $bukti, $nama, 'karyawan');

        return response()->json(['message' => $pesan, 'kode' => $kode, 'bukti' => $bukti], 422);
    }

    public function index(Request $r): View
    {
        $nik = $r->session()->get('nik');
        $k = Karyawan::with(['jabatan', 'divisi'])->where('nik', $nik)->firstOrFail();
        $p = Pengaturan::where('nik', $nik)->firstOrFail();
        $now = now();
        $hari = $now->toDateString();
        $sudah = Absensi::where('nik', $nik)->where('tanggal', $hari)->first();
        $libur = Kalender::info($hari);

        $ditutup = $p->jamTutup() && $now->format('H:i') > $p->jamTutup();

        return view('absen', [
            'k' => $k,
            'p' => $p,
            'jumlahTitik' => $p->titikEfektif()->count(),
            'sudah' => $sudah,
            'libur' => (! config('hadirku.izinkan_absen_libur')) ? $libur : null,
            'ditutup' => ! $sudah && $ditutup,
            'boot' => [
                'serverNow' => (int) $now->format('Uv'),
                'tz' => config('app.timezone'),
                'sudah' => $this->bukti($sudah),
                'toleransi' => Kecurangan::toleransiWaktu(),
                'deteksiGps' => (bool) config('hadirku.deteksi_gps'),
                'skorGps' => (int) config('hadirku.skor_fake_gps'),
            ],
        ]);
    }

    /**
     * Sinkron waktu: server mengembalikan jamnya sendiri dan membandingkan dengan jam perangkat (?d=epoch ms).
     * Dipakai front-end untuk mendeteksi perubahan jam HP kapan pun (saat buka halaman, kembali ke tab, dll).
     */
    public function waktu(Request $r): JsonResponse
    {
        $out = ['ms' => (int) now()->format('Uv'), 'curang' => false];
        $bukti = Kecurangan::waktuDiubah($r->query('d'));
        if ($bukti) {
            $out['curang'] = true;
            $out['bukti'] = $bukti;
            // catat paling sering 1x per 5 menit per sesi agar log tidak penuh
            $terakhir = (int) $r->session()->get('waktu_log', 0);
            if (now()->timestamp - $terakhir > 300) {
                $r->session()->put('waktu_log', now()->timestamp);
                $nama = $this->namaKaryawan($r);
                Aktivitas::catat('absen', 'waktu_diubah', "{$nama} ({$r->session()->get('nik')}): jam perangkat {$bukti['arah']} {$bukti['selisih_teks']} dari server (saat membuka halaman absen)", 'danger', $bukti, $nama, 'karyawan');
            }
        }

        return response()->json($out);
    }

    /** Dipanggil saat kamera dibuka: menerbitkan token sekali pakai */
    public function mulai(Request $r): JsonResponse
    {
        $d = $r->validate(['waktu_perangkat' => 'required|numeric']);

        if ($bukti = Kecurangan::waktuDiubah($d['waktu_perangkat'])) {
            return $this->curang($r, 'waktu_diubah', 'Sistem mendeteksi kecurangan: waktu perangkat diubah.', $bukti,
                'waktu_diubah', "jam perangkat {$bukti['arah']} {$bukti['selisih_teks']} dari server (mencoba membuka kamera absen)");
        }

        $token = Str::random(40);
        $r->session()->put('absen_nonce', ['t' => $token, 'at' => now()->timestamp]);

        return response()->json(['nonce' => $token]);
    }

    public function store(Request $r): JsonResponse
    {
        $gps = (bool) config('hadirku.deteksi_gps');
        $d = $r->validate([
            'foto' => 'required|string',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'akurasi' => 'nullable|numeric|min:0',
            'nonce' => 'required|string',
            'waktu_perangkat' => 'required|numeric',
            'geo_asli' => 'nullable|boolean',
            'sampel' => [Rule::requiredIf($gps), 'nullable', 'array', 'min:3', 'max:12'],
            'sampel.*.lat' => 'required|numeric|between:-90,90',
            'sampel.*.lng' => 'required|numeric|between:-180,180',
            'sampel.*.acc' => 'nullable|numeric',
            'sampel.*.alt' => 'nullable|numeric',
            'sampel.*.ts' => 'nullable|numeric',
        ], [
            'sampel.required' => 'Verifikasi lokasi belum lengkap. Ulangi ambil foto.',
            'sampel.min' => 'Verifikasi lokasi belum lengkap. Ulangi ambil foto.',
        ]);

        $nik = $r->session()->get('nik');
        $now = now();
        $hari = $now->toDateString();

        if ($sudah = Absensi::where('nik', $nik)->where('tanggal', $hari)->first()) {
            return response()->json(['message' => 'Anda sudah absen hari ini pukul '.substr($sudah->jam, 0, 5).'.'], 409);
        }

        $libur = Kalender::info($hari);
        if ($libur && ! config('hadirku.izinkan_absen_libur')) {
            return response()->json(['message' => 'Hari ini libur'.($libur['jenis'] === 'rutin' ? ' rutin' : ': '.$libur['nama']).'. Absen tidak diperlukan.'], 403);
        }

        // ===== Anti-kecurangan 1: perubahan waktu perangkat =====
        if ($bukti = Kecurangan::waktuDiubah($d['waktu_perangkat'])) {
            return $this->curang($r, 'waktu_diubah', 'Sistem mendeteksi kecurangan: waktu perangkat diubah.', $bukti,
                'waktu_diubah', "absen ditolak, jam perangkat {$bukti['arah']} {$bukti['selisih_teks']} dari server (HP {$bukti['waktu_perangkat']}, server {$bukti['waktu_server']})");
        }

        // ===== Anti-kecurangan 2: Fake GPS / Mock Location =====
        if ($gps && ($hasil = Kecurangan::fakeGps($d['sampel'] ?? [], (bool) ($d['geo_asli'] ?? true)))) {
            return $this->curang($r, 'fake_gps', 'Sistem mendeteksi kecurangan: Fake GPS / Mock Location.', $hasil,
                'fake_gps', 'absen ditolak, terdeteksi Fake GPS (skor '.$hasil['skor'].'): '.implode(' ', $hasil['alasan']));
        }

        // Token kamera sekali pakai
        $n = $r->session()->pull('absen_nonce');
        if (! $n || ! hash_equals($n['t'], $d['nonce'])
            || $now->timestamp - $n['at'] > config('hadirku.nonce_menit') * 60) {
            return response()->json(['message' => 'Sesi kamera tidak valid atau kedaluwarsa. Buka kamera lagi.'], 422);
        }

        $p = Pengaturan::where('nik', $nik)->where('aktif', true)->firstOrFail();
        $jamNow = $now->format('H:i');

        // Kunci waktu (jam server)
        if ($p->jamTutup() && $jamNow > $p->jamTutup()) {
            return response()->json(['message' => 'Absen sudah ditutup pukul '.$p->jamTutup().'.'], 403);
        }
        $status = 'hadir';
        $menitTelat = 0;
        if ($p->telat && $p->jamBatas() && $jamNow > $p->jamBatas()) {
            $status = 'telat';
            $menitTelat = Waktu::menit($jamNow) - Waktu::menit($p->jamBatas());
        }

        // Akurasi GPS
        $max = (int) config('hadirku.maks_akurasi');
        if ($max > 0 && isset($d['akurasi']) && $d['akurasi'] > $max) {
            return response()->json(['message' => 'Akurasi GPS rendah (±'.round($d['akurasi']).' m). Pindah ke area terbuka lalu coba lagi.'], 422);
        }

        // Titik terdekat
        $titik = $p->titikEfektif();
        if ($titik->isEmpty()) {
            return response()->json(['message' => 'Belum ada titik lahan untuk akun Anda. Hubungi admin.'], 422);
        }
        $best = null;
        $bestJarak = INF;
        foreach ($titik as $t) {
            $j = Geo::jarak((float) $d['lat'], (float) $d['lng'], $t->lat, $t->lng);
            if ($j < $bestJarak) {
                $bestJarak = $j;
                $best = $t;
            }
        }
        $jarak = (int) round($bestJarak);

        if ($jarak > $best->radius) {
            $nama = $this->namaKaryawan($r);
            Aktivitas::catat('absen', 'di_luar_radius', "{$nama} ({$nik}): absen ditolak, {$jarak} m dari {$best->nama} (radius {$best->radius} m)", 'warning', [], $nama, 'karyawan');

            return response()->json([
                'message' => 'Anda berada di luar radius lahan.',
                'detail' => ['titik' => $best->nama, 'jarak' => $jarak, 'radius' => $best->radius],
            ], 422);
        }

        // Foto: wajib JPEG valid dari kamera (dikirim base64)
        if (! preg_match('#^data:image/jpeg;base64,#', $d['foto'])) {
            return response()->json(['message' => 'Format foto tidak valid.'], 422);
        }
        $bin = base64_decode(substr($d['foto'], strpos($d['foto'], ',') + 1), true);
        if ($bin === false || strlen($bin) > config('hadirku.maks_foto_kb') * 1024
            || ! str_starts_with($bin, "\xFF\xD8")
            || ! ($info = @getimagesizefromstring($bin)) || $info['mime'] !== 'image/jpeg') {
            return response()->json(['message' => 'Foto tidak valid atau terlalu besar.'], 422);
        }

        $path = 'absensi/'.$now->format('Y-m').'/'.$nik.'-'.$now->format('Ymd-His').'.jpg';
        Storage::disk('local')->put($path, $bin);

        try {
            $a = Absensi::create([
                'nik' => $nik,
                'tanggal' => $hari,
                'jam' => $now->format('H:i:s'),
                'lat' => $d['lat'],
                'lng' => $d['lng'],
                'akurasi' => isset($d['akurasi']) ? (int) round($d['akurasi']) : null,
                'titik_nama' => $best->nama,
                'jarak' => $jarak,
                'radius' => $best->radius,
                'status' => $status,
                'menit_telat' => $menitTelat,
                'foto' => $path,
            ]);
        } catch (UniqueConstraintViolationException) {
            Storage::disk('local')->delete($path);

            return response()->json(['message' => 'Anda sudah absen hari ini.'], 409);
        }

        $nama = $this->namaKaryawan($r);
        Aktivitas::catat('absen', 'absen', "{$nama} ({$nik}) absen pukul ".$now->format('H:i:s').' di '.$best->nama.($status === 'telat' ? " (telat {$menitTelat} menit)" : ''), 'info', [], $nama, 'karyawan');

        return response()->json(['ok' => true, 'data' => $this->bukti($a)]);
    }

    public function foto(Request $r)
    {
        $a = Absensi::where('nik', $r->session()->get('nik'))
            ->where('tanggal', now()->toDateString())->firstOrFail();

        return Storage::disk('local')->response($a->foto);
    }
}
