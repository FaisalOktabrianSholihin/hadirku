<?php

namespace App\Support;

use App\Models\HariLibur;
use App\Models\LiburRutin;
use Carbon\Carbon;

/** Gabungan libur rutin mingguan + libur khusus (nasional, cuti bersama, perusahaan) */
final class Kalender
{
    /** @return array<string, array{nama:string, jenis:string}> kunci = Y-m-d */
    public static function rentang(string $dari, string $sampai): array
    {
        $rutin = LiburRutin::where('aktif', true)->pluck('nama', 'hari')->all();
        $khusus = HariLibur::whereBetween('tanggal', [$dari, $sampai])->get()
            ->keyBy(fn ($h) => $h->tanggal->toDateString());

        $out = [];
        $d = Carbon::parse($dari)->startOfDay();
        $akhir = Carbon::parse($sampai)->startOfDay();
        while ($d->lte($akhir)) {
            $k = $d->toDateString();
            if (isset($khusus[$k])) {
                $out[$k] = ['nama' => $khusus[$k]->nama, 'jenis' => $khusus[$k]->jenis];
            } elseif (isset($rutin[$d->dayOfWeek])) {
                $out[$k] = ['nama' => 'Libur rutin', 'jenis' => 'rutin'];
            }
            $d->addDay();
        }

        return $out;
    }

    public static function info(string $tanggal): ?array
    {
        return self::rentang($tanggal, $tanggal)[$tanggal] ?? null;
    }
}
