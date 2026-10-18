<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $t) {
                $t->id();
                $t->string('nama', 50)->unique();
                $t->string('slug', 60)->unique();
                $t->string('deskripsi', 255)->nullable();
                $t->json('izin');                          // ["*"] = semua menu, atau daftar kunci izin
                $t->boolean('sistem')->default(false);     // role bawaan (Super Admin) tidak bisa diubah / dihapus
                $t->timestamps();
            });
        }

        if (! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->unsignedBigInteger('role_id')->nullable()->after('password')->index();
                $t->boolean('aktif')->default(true)->after('role_id');           // false = tidak bisa login
                $t->timestamp('terakhir_login')->nullable()->after('aktif');
            });
        }

        if (! Schema::hasTable('log_aktivitas')) {
            Schema::create('log_aktivitas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable()->index();
                $t->string('pelaku', 100);
                $t->string('tipe_pelaku', 10)->default('admin');   // admin | karyawan | sistem
                $t->string('modul', 30)->index();
                $t->string('aksi', 40);
                $t->string('level', 10)->default('info');          // info | warning | danger
                $t->string('deskripsi', 500);
                $t->json('data')->nullable();
                $t->string('ip', 45)->nullable();
                $t->string('user_agent', 255)->nullable();
                $t->timestamp('created_at')->useCurrent()->index();
                $t->index('level');
            });
        }

        $now = now();
        $semua = ['dashboard', 'presensi.karyawan', 'presensi.absensi', 'laporan.report', 'master.karyawan', 'master.divisi',
            'master.user', 'pengaturan.keterangan', 'pengaturan.libur', 'sistem.role', 'sistem.log'];

        if (! DB::table('roles')->where('slug', 'super_admin')->exists()) {
            DB::table('roles')->insert([
                ['nama' => 'Super Admin', 'slug' => 'super_admin', 'deskripsi' => 'Akses penuh ke seluruh menu', 'izin' => json_encode(['*']), 'sistem' => true, 'created_at' => $now, 'updated_at' => $now],
                ['nama' => 'Admin', 'slug' => 'admin', 'deskripsi' => 'Mengelola presensi, laporan, dan pengaturan (tanpa User, Roles, Log)',
                    'izin' => json_encode(array_values(array_diff($semua, ['master.user', 'sistem.role', 'sistem.log']))), 'sistem' => false, 'created_at' => $now, 'updated_at' => $now],
                ['nama' => 'Viewer', 'slug' => 'viewer', 'deskripsi' => 'Hanya melihat dashboard, absensi harian, dan report',
                    'izin' => json_encode(['dashboard', 'presensi.absensi', 'laporan.report']), 'sistem' => false, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        // Semua akun admin yang sudah ada otomatis menjadi Super Admin
        $super = DB::table('roles')->where('slug', 'super_admin')->value('id');
        DB::table('users')->whereNull('role_id')->update(['role_id' => $super]);
    }

    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role_id', 'aktif', 'terakhir_login']));
        }
        Schema::dropIfExists('roles');
    }
};
