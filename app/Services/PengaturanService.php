<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\Titik;
use Illuminate\Support\Facades\DB;

class PengaturanService
{
    /**
     * Simpan (buat/ubah) pengaturan satu karyawan.
     *
     * @param array<int, array{nama:string, lat:float, lng:float, radius:int}> $titik
     */
    public function simpan(string $nik, bool $telat, ?string $jam, ?string $tutup, string $tipe, ?string $ikutNik, array $titik): Pengaturan
    {
        return DB::transaction(function () use ($nik, $telat, $jam, $tutup, $tipe, $ikutNik, $titik) {
            $p = Pengaturan::firstOrNew(['nik' => $nik]);
            $p->telat = $telat;
            $p->jam_batas = $telat ? $jam : null;
            $p->jam_tutup = $tutup ?: null;
            $p->tipe_titik = $tipe;
            $p->ikut_nik = $ikutNik ?: null;
            if (! $p->exists) {
                $p->aktif = true;
            }
            $p->save();

            $p->titik()->delete();
            if (! $ikutNik) {
                $now = now();
                $rows = array_map(fn ($t) => [
                    'pengaturan_id' => $p->id,
                    'nama' => $t['nama'],
                    'lat' => $t['lat'],
                    'lng' => $t['lng'],
                    'radius' => $t['radius'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $titik);
                foreach (array_chunk($rows, 500) as $chunk) {
                    Titik::insert($chunk);
                }
            }

            return $p;
        });
    }
}
