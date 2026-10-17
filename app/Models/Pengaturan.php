<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Pengaturan extends Model
{
    protected $table = 'pengaturan_absen';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['telat' => 'boolean', 'aktif' => 'boolean'];
    }

    public function titik(): HasMany
    {
        return $this->hasMany(Titik::class, 'pengaturan_id');
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'nik', 'nik');
    }

    public function jamBatas(): ?string
    {
        return $this->jam_batas ? substr($this->jam_batas, 0, 5) : null;
    }

    public function jamTutup(): ?string
    {
        return $this->jam_tutup ? substr($this->jam_tutup, 0, 5) : null;
    }

    /** Titik yang berlaku: titik sendiri, atau titik asmen jika manager memilih ikut asmen */
    public function titikEfektif(): Collection
    {
        if ($this->ikut_nik) {
            $src = static::with('titik')->where('nik', $this->ikut_nik)->first();
            return $src ? $src->titik : collect();
        }
        return $this->titik;
    }
}
