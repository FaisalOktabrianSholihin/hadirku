<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('import_jobs')) {
            return;
        }

        Schema::create('import_jobs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('tipe', 20)->default('presensi');
            $t->string('nama_file', 150)->nullable();
            $t->string('status', 12)->default('menunggu')->index();   // menunggu | berjalan | selesai | gagal
            $t->unsignedInteger('total')->default(0);                 // jumlah karyawan (NIK unik) yang diproses
            $t->unsignedInteger('diproses')->default(0);
            $t->unsignedInteger('berhasil')->default(0);
            $t->unsignedInteger('gagal')->default(0);
            $t->unsignedTinyInteger('persen')->default(0);
            $t->json('errors')->nullable();
            $t->unsignedInteger('errors_lainnya')->default(0);
            $t->string('pesan', 255)->nullable();
            $t->string('path', 200)->nullable();                      // file sementara berisi baris Excel
            $t->boolean('dilihat')->default(false);                   // hasil sudah ditutup oleh user
            $t->timestamp('mulai_at')->nullable();
            $t->timestamp('selesai_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
