<?php

namespace App\Support;

use App\Models\LogAktivitas;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Pencatat log aktivitas. Kegagalan mencatat tidak boleh mengganggu proses utama. */
final class Aktivitas
{
    public static function catat(string $modul, string $aksi, string $deskripsi, string $level = 'info', array $data = [], ?string $pelaku = null, ?string $tipe = null): void
    {
        try {
            $u = Auth::user();
            $req = request();
            LogAktivitas::create([
                'user_id' => $u?->id,
                'pelaku' => Str::limit($pelaku ?? $u?->name ?? 'Sistem', 100, ''),
                'tipe_pelaku' => $tipe ?? ($u ? 'admin' : 'sistem'),
                'modul' => $modul,
                'aksi' => $aksi,
                'level' => $level,
                'deskripsi' => Str::limit($deskripsi, 500, ''),
                'data' => $data ?: null,
                'ip' => $req?->ip(),
                'user_agent' => Str::limit((string) $req?->userAgent(), 250, ''),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
