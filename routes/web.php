<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KeteranganController;
use App\Http\Controllers\Admin\LiburController;
use App\Http\Controllers\Admin\ManualController;
use App\Http\Controllers\Admin\PengaturanController;
use App\Http\Controllers\Admin\RekapController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\AbsenController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// ===== Karyawan =====
Route::get('/login', [AuthController::class, 'form'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('karyawan')->group(function () {
    Route::get('/absen', [AbsenController::class, 'index'])->name('absen');
    Route::post('/absen/mulai', [AbsenController::class, 'mulai'])->name('absen.mulai');
    Route::post('/absen', [AbsenController::class, 'store'])->middleware('throttle:20,1')->name('absen.store');
    Route::get('/absen/foto', [AbsenController::class, 'foto'])->name('absen.foto');
});

// ===== Admin =====
Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'adminForm'])->name('admin.login');
        Route::post('login', [AuthController::class, 'adminLogin'])->middleware('throttle:10,1')->name('admin.login.post');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'adminLogout'])->name('admin.logout');

        Route::get('/', [DashboardController::class, 'index'])->name('admin.index');
        Route::view('karyawan', 'admin.karyawan')->name('admin.karyawan');
        Route::get('absensi', [RekapController::class, 'index'])->name('admin.absensi');
        Route::get('absensi/export', [RekapController::class, 'export'])->name('admin.absensi.export');
        Route::get('foto/{absensi}', [RekapController::class, 'foto'])->name('admin.foto');
        Route::get('report', [ReportController::class, 'index'])->name('admin.report');
        Route::view('keterangan', 'admin.keterangan')->name('admin.keterangan');
        Route::view('libur', 'admin.libur')->name('admin.libur');
        Route::get('template', [PengaturanController::class, 'template'])->name('admin.template');

        Route::prefix('api')->group(function () {
            Route::get('pengaturan', [PengaturanController::class, 'index']);
            Route::get('pengaturan/{nik}', [PengaturanController::class, 'show']);
            Route::post('pengaturan', [PengaturanController::class, 'simpan']);
            Route::patch('pengaturan/{nik}/aktif', [PengaturanController::class, 'toggle']);
            Route::delete('pengaturan/{nik}', [PengaturanController::class, 'destroy']);
            Route::get('induk', [PengaturanController::class, 'induk']);
            Route::post('import', [PengaturanController::class, 'import']);

            Route::get('report', [ReportController::class, 'data']);
            Route::get('cari-karyawan', [ManualController::class, 'cari']);
            Route::post('manual', [ManualController::class, 'store']);

            Route::get('keterangan', [KeteranganController::class, 'index']);
            Route::post('keterangan', [KeteranganController::class, 'simpan']);
            Route::patch('keterangan/{id}/aktif', [KeteranganController::class, 'toggle']);
            Route::delete('keterangan/{id}', [KeteranganController::class, 'destroy']);

            Route::get('libur', [LiburController::class, 'index']);
            Route::post('libur', [LiburController::class, 'sync']);
        });
    });
});
