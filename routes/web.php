<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RapatController;
use App\Http\Controllers\JadwalRapatController;
use App\Http\Controllers\NotulensiController;
use App\Http\Controllers\KelompokRapatController;
use App\Http\Controllers\LaporanController;

Route::redirect('/', '/dashboard');

// Dashboard + tab rekap
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/unit',      [DashboardController::class, 'unit'])->name('unit');
    Route::get('/kehadiran', [DashboardController::class, 'kehadiran'])->name('kehadiran');
    Route::get('/notulensi', [DashboardController::class, 'notulensi'])->name('notulensi');
    Route::get('/ruang',     [DashboardController::class, 'ruang'])->name('ruang');
});

Route::prefix('rapat')->name('rapat.')->group(function () {
    // Buat rapat
    Route::get('/buat',       [RapatController::class, 'create'])->name('create');
    Route::post('/',          [RapatController::class, 'store'])->name('store');
    Route::get('/cek-tempat', [RapatController::class, 'cekTempat'])->name('cek-tempat');
    Route::get('/pegawai',    [RapatController::class, 'pegawai'])->name('pegawai');

    // Jadwal rapat
    Route::get('/jadwal',     [JadwalRapatController::class, 'index'])->name('jadwal');
    Route::get('/saya',       [RapatController::class, 'saya'])->name('saya');

    Route::get('/{id}',           [JadwalRapatController::class, 'show'])->whereNumber('id')->name('show');
    Route::put('/{id}',           [JadwalRapatController::class, 'update'])->whereNumber('id')->name('update');
    Route::post('/{id}/batal',    [JadwalRapatController::class, 'batal'])->whereNumber('id')->name('batal');
    Route::post('/{id}/selesai',  [JadwalRapatController::class, 'selesai'])->whereNumber('id')->name('selesai');
    Route::post('/{id}/aktifkan', [JadwalRapatController::class, 'aktifkan'])->whereNumber('id')->name('aktifkan');
    Route::get('/{id}/salin',     [JadwalRapatController::class, 'salin'])->whereNumber('id')->name('salin');

    // Status pengiriman undangan WA
    Route::get('/{id}/status-wa',    [RapatController::class, 'statusWa'])->whereNumber('id')->name('status-wa');
    Route::post('/{id}/kirim-ulang', [RapatController::class, 'kirimUlang'])->whereNumber('id')->name('kirim-ulang');
});

Route::get('/notulensi', [NotulensiController::class, 'index'])->name('notulensi.index');

// Kelompok rapat
Route::get('/kelompok',                  [KelompokRapatController::class, 'index'])->name('kelompok.index');
Route::get('/kelompok/anggota-semua',    [KelompokRapatController::class, 'anggotaSemua'])->name('kelompok.anggota.semua');
Route::post('/kelompok/baru',            [KelompokRapatController::class, 'buat'])->name('kelompok.buat');
Route::post('/kelompok',                 [KelompokRapatController::class, 'simpan'])->name('kelompok.simpan');
Route::get('/kelompok/{id}/anggota',     [KelompokRapatController::class, 'anggota'])->whereNumber('id')->name('kelompok.anggota');
Route::get('/kelompok/{id}/detail',      [KelompokRapatController::class, 'detail'])->whereNumber('id')->name('kelompok.detail');
Route::put('/kelompok/{id}',             [KelompokRapatController::class, 'ubah'])->whereNumber('id')->name('kelompok.ubah');
Route::delete('/kelompok/{id}',          [KelompokRapatController::class, 'hapus'])->whereNumber('id')->name('kelompok.hapus');
Route::post('/kelompok/{id}/anggota',    [KelompokRapatController::class, 'tambahAnggota'])->whereNumber('id')->name('kelompok.anggota.tambah');
Route::delete('/kelompok/{id}/anggota/{detail}', [KelompokRapatController::class, 'hapusAnggota'])->whereNumber('id')->whereNumber('detail')->name('kelompok.anggota.hapus');

// Laporan & Rekap
Route::prefix('laporan')->name('laporan.')->group(function () {
    Route::get('/',          [LaporanController::class, 'index'])->name('index');
    Route::get('/pratinjau', [LaporanController::class, 'pratinjau'])->name('pratinjau');
    Route::get('/unduh',     [LaporanController::class, 'unduh'])->name('unduh');
    Route::get('/cetak',     [LaporanController::class, 'cetak'])->name('cetak');
});