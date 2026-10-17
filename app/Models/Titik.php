<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Titik extends Model
{
    protected $table = 'titik_lahan';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'radius' => 'integer'];
    }
}
