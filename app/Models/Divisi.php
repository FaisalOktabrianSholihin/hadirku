<?php

namespace App\Models;

/** Tabel milik db_indukk (read-only dari sisi Hadirku) */
class Divisi extends IndukkModel
{
    protected $table = 'divisis';
    protected $guarded = [];
}
