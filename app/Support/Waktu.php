<?php

namespace App\Support;

final class Waktu
{
    /** "08:30" atau "08:30:15" -> menit sejak 00:00 */
    public static function menit(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hhmm, 0, 5)));

        return $h * 60 + $m;
    }
}
