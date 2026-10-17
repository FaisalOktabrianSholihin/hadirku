<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data karyawan/divisi/jabatan memakai tabel milik db_indukk (karyawans, divisis, jabatans)

        Schema::create('pengaturan_absen', function (Blueprint $t) {
            $t->id();
            $t->string('nik', 20)->unique();
            $t->boolean('telat')->default(false);
            $t->time('jam_batas')->nullable();
            $t->string('tipe_titik', 10)->default('single'); // single | multi
            $t->string('ikut_nik', 20)->nullable();           // manager ikut titik asmen
            $t->boolean('aktif')->default(true);
            $t->timestamps();
        });

        // Tanpa batas jumlah titik: 1 baris per titik
        Schema::create('titik_lahan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pengaturan_id')->constrained('pengaturan_absen')->cascadeOnDelete();
            $t->string('nama', 100);
            $t->decimal('lat', 10, 7);
            $t->decimal('lng', 10, 7);
            $t->unsignedInteger('radius')->default(150);
            $t->timestamps();
        });

        Schema::create('absensi', function (Blueprint $t) {
            $t->id();
            $t->string('nik', 20)->index();
            $t->date('tanggal');
            $t->time('jam');
            $t->decimal('lat', 10, 7);
            $t->decimal('lng', 10, 7);
            $t->unsignedInteger('akurasi')->nullable();
            $t->string('titik_nama', 100)->nullable();
            $t->unsignedInteger('jarak');
            $t->unsignedInteger('radius');
            $t->string('status', 15)->default('hadir'); // hadir | terlambat
            $t->string('foto');
            $t->timestamps();
            $t->unique(['nik', 'tanggal']); // 1x per hari, dijaga di level database
            $t->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi');
        Schema::dropIfExists('titik_lahan');
        Schema::dropIfExists('pengaturan_absen');
    }
};
