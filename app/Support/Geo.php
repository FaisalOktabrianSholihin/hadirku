<?php

namespace App\Support;

final class Geo
{
    /** Jarak haversine dalam meter */
    public static function jarak(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /**
     * Baca koordinat dari "lat,lng" atau link Google Maps panjang.
     * Link pendek (maps.app.goo.gl) tidak didukung karena butuh akses internet keluar.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function parse(?string $s): ?array
    {
        $s = trim(urldecode((string) $s));
        if ($s === '') {
            return null;
        }
        $n = '(-?\d+(?:\.\d+)?)';
        $pola = [
            "/@{$n},{$n}/",
            "/!3d{$n}!4d{$n}/",
            "/[?&](?:q|ll|query|destination)={$n},\s*{$n}/",
            "/^{$n}\s*[,;\s]\s*{$n}$/",
        ];
        foreach ($pola as $p) {
            if (preg_match($p, $s, $m)) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
                if (abs($lat) <= 90 && abs($lng) <= 180) {
                    return ['lat' => $lat, 'lng' => $lng];
                }
            }
        }

        return null;
    }
}
