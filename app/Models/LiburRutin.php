<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiburRutin extends Model
{
    protected $table = 'libur_rutin';
    protected $primaryKey = 'hari';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
