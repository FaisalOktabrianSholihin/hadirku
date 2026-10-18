<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Deteksi kecurangan absen di sisi server (otoritas). Browser tidak bisa membaca flag "isMock" Android,
 * jadi Fake GPS dideteksi lewat pola data lokasi (heuristik berbobot) dan waktu perangkat dibandingkan jam server.
 */
final class Kecurangan
{
    public static function toleransiWaktu(): int
    {
        return max(10, (int) config('hadirku.toleransi_waktu'));
    }

    /** Selisih detik: waktu perangkat - waktu server (positif = jam HP lebih maju). */
    public static function selisihWaktu(mixed $deviceMs): ?float
    {
        if (! is_numeric($deviceMs)) {
            return null;
        }

        return ((float) $deviceMs - (float) now()->format('Uv')) / 1000;
    }

    /** @return array<string,mixed>|null null = aman */
    public static function waktuDiubah(mixed $deviceMs): ?array
    {
        $s = self::selisihWaktu($deviceMs);
        if ($s === null || abs($s) <= self::toleransiWaktu()) {
            return null;
        }
        $tz = config('app.timezone');

        return [
            'selisih_detik' => (int) round($s),
            'selisih_teks' => self::teksDurasi(abs($s)),
            'arah' => $s > 0 ? 'lebih maju' : 'lebih mundur',
            'waktu_perangkat' => Carbon::createFromTimestampMs((int) $deviceMs)->setTimezone($tz)->format('d/m/Y H:i:s'),
            'waktu_server' => now()->format('d/m/Y H:i:s'),
        ];
    }

    public static function teksDurasi(float $detik): string
    {
        $d = (int) round($detik);
        $h = intdiv($d, 86400);
        $j = intdiv($d % 86400, 3600);
        $m = intdiv($d % 3600, 60);
        $s = $d % 60;
        $p = [];
        if ($h) {
            $p[] = "{$h} hari";
        }
        if ($j) {
            $p[] = "{$j} jam";
        }
        if ($m) {
            $p[] = "{$m} menit";
        }
        if (! $h && ! $j && ($s || ! $m)) {
            $p[] = "{$s} detik";
        }

        return implode(' ', $p);
    }

    private static function desimal(float $v): int
    {
        $t = rtrim(sprintf('%.12F', abs($v)), '0');
        $p = strpos($t, '.');

        return $p === false ? 0 : strlen($t) - $p - 1;
    }

    /**
     * @param  array<int,array<string,mixed>>  $sampel  [{lat,lng,acc,alt,ts}, ...] (urut waktu)
     * @return array{skor:int,alasan:list<string>}|null null = tidak mencurigakan
     */
    public static function fakeGps(array $sampel, bool $geoAsli = true): ?array
    {
        if (! config('hadirku.deteksi_gps')) {
            return null;
        }
        $s = array_values(array_filter($sampel, fn ($x) => is_array($x) && isset($x['lat'], $x['lng']) && is_numeric($x['lat']) && is_numeric($x['lng'])));
        $skor = 0;
        $alasan = [];

        if (! $geoAsli) {
            $skor += 4;
            $alasan[] = 'Fungsi lokasi di browser dimodifikasi (bukan GPS asli perangkat).';
        }

        $n = count($s);

        // 1) Teleportasi: lompat jauh dalam waktu sangat singkat
        for ($i = 1; $i < $n; $i++) {
            $j = Geo::jarak((float) $s[$i - 1]['lat'], (float) $s[$i - 1]['lng'], (float) $s[$i]['lat'], (float) $s[$i]['lng']);
            $dt = (isset($s[$i]['ts'], $s[$i - 1]['ts']) && is_numeric($s[$i]['ts']) && is_numeric($s[$i - 1]['ts']))
                ? max(0.5, ((float) $s[$i]['ts'] - (float) $s[$i - 1]['ts']) / 1000) : 1.0;
            $toleransi = 300 + (float) ($s[$i - 1]['acc'] ?? 0) + (float) ($s[$i]['acc'] ?? 0); // fix kasar (jaringan) -> fix GPS bukan teleportasi
            if ($j > $toleransi && $j / $dt > 100) {
                $skor += 4;
                $alasan[] = 'Lokasi berpindah '.round($j).' m dalam '.round($dt, 1).' detik (tidak wajar).';
                break;
            }
        }

        // 2) Koordinat identik persis pada semua pembacaan (GPS asli selalu bergeser sedikit)
        if ($n >= 5) {
            $unik = count(array_unique(array_map(fn ($x) => round((float) $x['lat'], 9).','.round((float) $x['lng'], 9), $s)));
            if ($unik === 1) {
                $skor += 2;
                $alasan[] = 'Koordinat identik persis pada '.$n.' pembacaan berturut-turut.';
            }
        }

        // 3) Presisi koordinat sangat rendah (diketik manual / aplikasi fake)
        if ($n >= 1) {
            $maks = 0;
            foreach ($s as $x) {
                $maks = max($maks, self::desimal((float) $x['lat']), self::desimal((float) $x['lng']));
            }
            if ($maks <= 4) {
                $skor += 2;
                $alasan[] = 'Presisi koordinat terlalu rendah (maks '.$maks.' desimal), pola lokasi buatan.';
            }
        }

        // 4) Akurasi mustahil (<= 1 m) pada semua pembacaan
        $akurasi = array_values(array_filter(array_map(fn ($x) => isset($x['acc']) && is_numeric($x['acc']) ? (float) $x['acc'] : null, $s), fn ($v) => $v !== null));
        if ($akurasi && max($akurasi) <= 1.0) {
            $skor += 2;
            $alasan[] = 'Akurasi GPS ±'.max($akurasi).' m (tidak mungkin pada GPS ponsel).';
        }

        // 5) Ketinggian persis 0 pada semua pembacaan (nilai bawaan banyak aplikasi Fake GPS)
        $alt = array_values(array_filter(array_map(fn ($x) => array_key_exists('alt', $x) && is_numeric($x['alt']) ? (float) $x['alt'] : null, $s), fn ($v) => $v !== null));
        if (count($alt) >= 3 && count(array_filter($alt, fn ($v) => $v != 0.0)) === 0) {
            $skor += 1;
            $alasan[] = 'Ketinggian selalu tepat 0 m.';
        }

        return $skor >= (int) config('hadirku.skor_fake_gps') ? ['skor' => $skor, 'alasan' => $alasan] : null;
    }
}
