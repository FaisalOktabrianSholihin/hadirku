<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogAktivitas extends Model
{
    protected $table = 'log_aktivitas';
    protected $guarded = [];
    public $timestamps = false;

    protected function casts(): array
    {
        return ['data' => 'array', 'created_at' => 'datetime'];
    }
}
