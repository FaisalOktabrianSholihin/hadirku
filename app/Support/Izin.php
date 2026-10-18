<?php

namespace App\Support;

/** Katalog izin menu untuk Roles. Kunci dipakai di middleware "izin:kunci" dan di sidebar. */
final class Izin
{
    public const DAFTAR = [
        'Umum' => ['dashboard' => 'Dashboard'],
        'Presensi' => [
            'presensi.karyawan' => 'Data Karyawan (pengaturan presensi)',
            'presensi.absensi' => 'Absensi Harian',
        ],
        'Laporan' => ['laporan.report' => 'Report & input keterangan'],
        'Data Master' => [
            'master.karyawan' => 'Karyawan (db_indukk)',
            'master.divisi' => 'Divisi (db_indukk)',
            'master.user' => 'User',
        ],
        'Pengaturan' => [
            'pengaturan.keterangan' => 'Keterangan Absen',
            'pengaturan.libur' => 'Kalender Libur',
        ],
        'Sistem' => [
            'sistem.role' => 'Roles',
            'sistem.log' => 'Log Aktivitas',
        ],
    ];

    /** Urutan pencarian halaman awal setelah login */
    public const RUTE = [
        'dashboard' => 'admin.index',
        'presensi.absensi' => 'admin.absensi',
        'laporan.report' => 'admin.report',
        'presensi.karyawan' => 'admin.karyawan',
        'master.karyawan' => 'admin.master.karyawan',
        'master.divisi' => 'admin.master.divisi',
        'master.user' => 'admin.users',
        'pengaturan.keterangan' => 'admin.keterangan',
        'pengaturan.libur' => 'admin.libur',
        'sistem.role' => 'admin.roles',
        'sistem.log' => 'admin.log',
    ];

    /** @return list<string> */
    public static function semua(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::DAFTAR)));
    }

    /** Hanya kunci yang dikenal */
    public static function bersihkan(array $kunci): array
    {
        return array_values(array_intersect(self::semua(), $kunci));
    }

    /** Untuk dikirim ke JS */
    public static function katalog(): array
    {
        $out = [];
        foreach (self::DAFTAR as $grup => $item) {
            $out[] = ['grup' => $grup, 'item' => collect($item)->map(fn ($l, $k) => ['kunci' => $k, 'label' => $l])->values()->all()];
        }

        return $out;
    }
}
