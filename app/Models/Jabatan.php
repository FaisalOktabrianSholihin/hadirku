<?php

namespace App\Models;

/** Tabel milik db_indukk (read-only dari sisi Hadirku) */
class Jabatan extends IndukkModel
{
    protected $table = 'jabatans';
    protected $guarded = [];
}
