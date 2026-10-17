<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pengaturan_absen', 'jam_tutup')) {
            Schema::table('pengaturan_absen', fn (Blueprint $t) => $t->time('jam_tutup')->nullable()->after('jam_batas'));
        }
        if (! Schema::hasColumn('absensi', 'menit_telat')) {
            Schema::table('absensi', fn (Blueprint $t) => $t->unsignedInteger('menit_telat')->default(0)->after('status'));
        }

        // Master keterangan (Hadir, Telat, Alpa, Izin, ... bisa ditambah admin)
        Schema::create('keterangan_absen', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 50);
            $t->string('kode', 6)->unique();
            $t->string('sistem', 10)->nullable()->unique(); // hadir | telat | alpa | libur (dipakai mesin absen)
            $t->string('warna', 7)->default('#64748b');
            $t->boolean('pakai_menit')->default(false);     // tampilkan kolom waktu (menit) di report
            $t->boolean('aktif')->default(true);
            $t->unsignedInteger('urutan')->default(0);
            $t->timestamps();
        });

        // Keterangan yang diinput admin (izin, sakit, cuti, koreksi, dst.)
        Schema::create('kehadiran_manual', function (Blueprint $t) {
            $t->id();
            $t->string('nik', 20);
            $t->date('tanggal');
            $t->foreignId('keterangan_id')->constrained('keterangan_absen')->restrictOnDelete();
            $t->unsignedInteger('menit')->nullable();
            $t->string('catatan')->nullable();
            $t->timestamps();
            $t->unique(['nik', 'tanggal']);
            $t->index('tanggal');
        });

        Schema::create('hari_libur', function (Blueprint $t) {
            $t->id();
            $t->date('tanggal')->unique();
            $t->string('nama', 100);
            $t->string('jenis', 20)->default('nasional'); // nasional | cuti_bersama | perusahaan
            $t->timestamps();
        });

        Schema::create('libur_rutin', function (Blueprint $t) {
            $t->unsignedTinyInteger('hari')->primary(); // 0=Minggu ... 6=Sabtu
            $t->string('nama', 20);
            $t->boolean('aktif')->default(false);
        });

        $now = now();
        DB::table('keterangan_absen')->insert([
            ['nama' => 'Hadir', 'kode' => 'H', 'sistem' => 'hadir', 'warna' => '#16a34a', 'pakai_menit' => false, 'urutan' => 1],
            ['nama' => 'Telat', 'kode' => 'T', 'sistem' => 'telat', 'warna' => '#f59e0b', 'pakai_menit' => true, 'urutan' => 2],
            ['nama' => 'Alpa', 'kode' => 'A', 'sistem' => 'alpa', 'warna' => '#dc2626', 'pakai_menit' => false, 'urutan' => 3],
            ['nama' => 'Libur', 'kode' => 'L', 'sistem' => 'libur', 'warna' => '#64748b', 'pakai_menit' => false, 'urutan' => 99],
            ['nama' => 'Izin', 'kode' => 'I', 'sistem' => null, 'warna' => '#3b82f6', 'pakai_menit' => false, 'urutan' => 4],
            ['nama' => 'Sakit', 'kode' => 'S', 'sistem' => null, 'warna' => '#8b5cf6', 'pakai_menit' => false, 'urutan' => 5],
            ['nama' => 'Cuti', 'kode' => 'C', 'sistem' => null, 'warna' => '#14b8a6', 'pakai_menit' => false, 'urutan' => 6],
            ['nama' => 'Dinas Luar', 'kode' => 'DL', 'sistem' => null, 'warna' => '#0ea5e9', 'pakai_menit' => false, 'urutan' => 7],
        ]);
        DB::table('keterangan_absen')->update(['created_at' => $now, 'updated_at' => $now]);

        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        DB::table('libur_rutin')->insert(array_map(
            fn ($i) => ['hari' => $i, 'nama' => $hari[$i], 'aktif' => $i === 0], range(0, 6)
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('libur_rutin');
        Schema::dropIfExists('hari_libur');
        Schema::dropIfExists('kehadiran_manual');
        Schema::dropIfExists('keterangan_absen');
        if (Schema::hasColumn('absensi', 'menit_telat')) {
            Schema::table('absensi', fn (Blueprint $t) => $t->dropColumn('menit_telat'));
        }
        if (Schema::hasColumn('pengaturan_absen', 'jam_tutup')) {
            Schema::table('pengaturan_absen', fn (Blueprint $t) => $t->dropColumn('jam_tutup'));
        }
    }
};
