<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    protected $table = 'absensi';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['tanggal' => 'date:Y-m-d'];
    }
}
