<?php

namespace Database\Seeders;

use App\Models\HariLibur;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Akun admin (dibuat sekali). GANTI password setelah login pertama.
        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'administrator@gmail.com')],
            ['name' => 'Administrator', 'password' => env('ADMIN_PASSWORD', 'password'), 'role_id' => Role::where('slug', 'super_admin')->value('id'), 'aktif' => true],
        );

        // Libur nasional TETAP setiap tahun. Libur yang tanggalnya berubah-ubah
        // (Idul Fitri, Idul Adha, Nyepi, Waisak, dll.) diisi admin lewat menu Kalender Libur.
        foreach ([2026, 2027] as $th) {
            foreach ([
                "$th-01-01" => 'Tahun Baru Masehi',
                "$th-05-01" => 'Hari Buruh Internasional',
                "$th-06-01" => 'Hari Lahir Pancasila',
                "$th-08-17" => 'Hari Kemerdekaan RI',
                "$th-12-25" => 'Hari Raya Natal',
            ] as $tgl => $nama) {
                HariLibur::firstOrCreate(['tanggal' => $tgl], ['nama' => $nama, 'jenis' => 'nasional']);
            }
        }
    }
}
