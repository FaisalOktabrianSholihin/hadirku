<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model untuk tabel milik database db_indukk (koneksi "indukk").
 * Data karyawan/divisi/jabatan HANYA dibaca dari db_indukk, sedangkan data
 * Hadirku (absensi, pengaturan, keterangan, libur, dst.) disimpan di database hadirku.
 *
 * Nama tabel dikualifikasi dengan nama database (mis. `db_indukk`.`karyawans`)
 * agar join / whereHas dari tabel hadirku tetap berjalan (kedua database
 * berada di server MySQL yang sama).
 */
abstract class IndukkModel extends Model
{
    protected $connection = 'indukk';

    public function getTable(): string
    {
        $table = parent::getTable();

        return str_contains($table, '.') ? $table : static::namaDatabase().'.'.$table;
    }

    /** Nama tabel lengkap, untuk dipakai di DB::table()->join(), mis. Karyawan::tabel().' as k' */
    public static function tabel(): string
    {
        return (new static)->getTable();
    }

    public static function namaDatabase(): string
    {
        return (string) config('database.connections.indukk.database', 'db_indukk');
    }
}
