<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Keterangan extends Model
{
    protected $table = 'keterangan_absen';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['pakai_menit' => 'boolean', 'aktif' => 'boolean', 'urutan' => 'integer'];
    }
}
