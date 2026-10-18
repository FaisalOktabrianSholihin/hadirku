<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KeteranganController;
use App\Http\Controllers\Admin\LiburController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\ManualController;
use App\Http\Controllers\Admin\MasterController;
use App\Http\Controllers\Admin\PengaturanController;
use App\Http\Controllers\Admin\RekapController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
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
    Route::get('/absen/waktu', [AbsenController::class, 'waktu'])->middleware('throttle:60,1')->name('absen.waktu');
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

        Route::get('/', [DashboardController::class, 'index'])->middleware('izin:dashboard')->name('admin.index');

        // Presensi
        Route::middleware('izin:presensi.karyawan')->group(function () {
            Route::view('karyawan', 'admin.karyawan')->name('admin.karyawan');
            Route::get('template', [PengaturanController::class, 'template'])->name('admin.template');
        });
        Route::middleware('izin:presensi.absensi')->group(function () {
            Route::get('absensi', [RekapController::class, 'index'])->name('admin.absensi');
            Route::get('absensi/export', [RekapController::class, 'export'])->name('admin.absensi.export');
            Route::get('foto/{absensi}', [RekapController::class, 'foto'])->name('admin.foto');
        });

        // Laporan
        Route::get('report', [ReportController::class, 'index'])->middleware('izin:laporan.report')->name('admin.report');

        // Data master (hanya lihat db_indukk) + User
        Route::view('master/karyawan', 'admin.master-karyawan')->middleware('izin:master.karyawan')->name('admin.master.karyawan');
        Route::view('master/divisi', 'admin.master-divisi')->middleware('izin:master.divisi')->name('admin.master.divisi');
        Route::view('users', 'admin.users')->middleware('izin:master.user')->name('admin.users');

        // Pengaturan
        Route::view('keterangan', 'admin.keterangan')->middleware('izin:pengaturan.keterangan')->name('admin.keterangan');
        Route::view('libur', 'admin.libur')->middleware('izin:pengaturan.libur')->name('admin.libur');

        // Sistem
        Route::view('roles', 'admin.roles')->middleware('izin:sistem.role')->name('admin.roles');
        Route::view('log', 'admin.log')->middleware('izin:sistem.log')->name('admin.log');

        Route::prefix('api')->group(function () {
            Route::middleware('izin:presensi.karyawan')->group(function () {
                Route::get('pengaturan', [PengaturanController::class, 'index']);
                Route::get('pengaturan/{nik}', [PengaturanController::class, 'show']);
                Route::post('pengaturan', [PengaturanController::class, 'simpan']);
                Route::patch('pengaturan/{nik}/aktif', [PengaturanController::class, 'toggle']);
                Route::delete('pengaturan/{nik}', [PengaturanController::class, 'destroy']);
                Route::get('induk', [PengaturanController::class, 'induk']);
                Route::post('import', [PengaturanController::class, 'import']);
            });

            Route::middleware('izin:laporan.report')->group(function () {
                Route::get('report', [ReportController::class, 'data']);
                Route::get('cari-karyawan', [ManualController::class, 'cari']);
                Route::post('manual', [ManualController::class, 'store']);
            });

            // Daftar keterangan dipakai Report (input manual) dan menu Keterangan Absen
            Route::get('keterangan', [KeteranganController::class, 'index'])->middleware('izin:laporan.report,pengaturan.keterangan');
            Route::middleware('izin:pengaturan.keterangan')->group(function () {
                Route::post('keterangan', [KeteranganController::class, 'simpan']);
                Route::patch('keterangan/{id}/aktif', [KeteranganController::class, 'toggle']);
                Route::delete('keterangan/{id}', [KeteranganController::class, 'destroy']);
            });

            Route::middleware('izin:pengaturan.libur')->group(function () {
                Route::get('libur', [LiburController::class, 'index']);
                Route::post('libur', [LiburController::class, 'sync']);
            });

            Route::get('master/karyawan', [MasterController::class, 'karyawan'])->middleware('izin:master.karyawan');
            Route::get('master/divisi', [MasterController::class, 'divisi'])->middleware('izin:master.divisi');

            Route::middleware('izin:master.user')->group(function () {
                Route::get('users', [UserController::class, 'index']);
                Route::post('users', [UserController::class, 'simpan']);
                Route::patch('users/{id}/aktif', [UserController::class, 'toggle']);
                Route::delete('users/{id}', [UserController::class, 'destroy']);
            });

            Route::middleware('izin:sistem.role')->group(function () {
                Route::get('roles', [RoleController::class, 'index']);
                Route::post('roles', [RoleController::class, 'simpan']);
                Route::delete('roles/{id}', [RoleController::class, 'destroy']);
            });

            Route::get('log', [LogController::class, 'index'])->middleware('izin:sistem.log');
        });
    });
});
