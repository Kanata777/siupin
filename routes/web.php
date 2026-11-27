<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RapatController;
use App\Http\Controllers\JadwalRapatController;
use App\Http\Controllers\NotulensiController;
use App\Http\Controllers\KelompokRapatController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\TempatRapatController;
use App\Http\Controllers\StatusWaController;
use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AbsenPublikController;

// ----------------------------------------------------------------------
// Dua domain dalam satu project (lihat config/absen.php):
//   SIUPIN_HOST  -> aplikasi SIUPIN (login, dashboard, rapat, dst.)   mis. siupinv2.nhsolo.com
//   ABSEN_HOST   -> hanya halaman absensi peserta (tanpa login)       mis. siupinabsen.nhsolo.com
// Bila salah satunya kosong (mis. di komputer lokal), semua berjalan di satu domain
// dan absensi berada di path /absen.
// ----------------------------------------------------------------------
$hostApp   = trim((string) config('absen.app_host'));
$hostAbsen = trim((string) config('absen.host'));
$duaDomain = $hostApp !== '' && $hostAbsen !== '';

// ----------------------------------------------------------------------
// A. Absensi peserta (TANPA login): scan QR rapat -> isi nomor WA -> tercatat hadir.
// Link {id}/{kode} hanya valid bila {kode} cocok dengan HMAC id rapat.
// ----------------------------------------------------------------------
$ruteAbsen = function () {
    Route::get('/', [AbsenPublikController::class, 'scan'])->name('absen.scan');
    Route::get('/{id}/{kode}', [AbsenPublikController::class, 'form'])
        ->whereNumber('id')->where('kode', '[a-f0-9]{16}')->name('absen.form');
    Route::post('/{id}/{kode}', [AbsenPublikController::class, 'simpan'])
        ->whereNumber('id')->where('kode', '[a-f0-9]{16}')->middleware('throttle:20,1')->name('absen.simpan');
};

// ----------------------------------------------------------------------
// B. Aplikasi SIUPIN
// ----------------------------------------------------------------------
$ruteApp = function () {

    // ----------------------------------------------------------------------
    // Login / logout
    // ----------------------------------------------------------------------
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'tampil'])->name('login');
        Route::post('/login', [AuthController::class, 'masuk'])->name('login.proses');
    });
    Route::post('/logout', [AuthController::class, 'keluar'])->middleware('auth')->name('logout');

    // ----------------------------------------------------------------------
    // Seluruh halaman SIUPIN wajib login.
    //
    // Pembagian akses:
    //   - User biasa  : Dashboard, Buat Rapat, Rapat Saya, Daftar Hadir, Notulensi, Status WA
    //                   (semuanya hanya data rapat yang dia buat, dibatasi di controller
    //                   lewat Rapat::terlihat() / milikSaya()).
    //                   + Laporan & Rekap (hanya data rapat yang dia buat).
    //   - Super Admin : semua di atas (melihat semua data) + Jadwal Rapat, Tempat Rapat,
    //                   Kelompok Rapat (kelola), dan laporan data master.
    // ----------------------------------------------------------------------
    Route::middleware('auth')->group(function () {

        Route::redirect('/', '/dashboard');

        // Dashboard + tab rekap (data otomatis dibatasi per user di DashboardController)
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::get('/unit', [DashboardController::class, 'unit'])->name('unit');
            Route::get('/kehadiran', [DashboardController::class, 'kehadiran'])->name('kehadiran');
            Route::get('/notulensi', [DashboardController::class, 'notulensi'])->name('notulensi');
            Route::get('/ruang', [DashboardController::class, 'ruang'])->name('ruang');
        });

        Route::prefix('rapat')->name('rapat.')->group(function () {
            // Buat rapat
            Route::get('/buat', [RapatController::class, 'create'])->name('create');
            Route::post('/', [RapatController::class, 'store'])->name('store');
            Route::get('/cek-tempat', [RapatController::class, 'cekTempat'])->name('cek-tempat');
            Route::get('/status-tempat', [RapatController::class, 'statusTempat'])->name('status-tempat');
            Route::get('/pegawai', [RapatController::class, 'pegawai'])->name('pegawai');

            // Jadwal rapat global: Super Admin saja (User biasa dialihkan ke Rapat Saya oleh controller)
            Route::get('/jadwal', [JadwalRapatController::class, 'index'])->name('jadwal');
            // Rapat Saya: rapat yang dibuat user yang sedang login
            Route::get('/saya', [JadwalRapatController::class, 'saya'])->name('saya');

            // Detail / ubah / aksi: controller hanya mengizinkan rapat milik user (Super Admin: semua)
            Route::get('/{id}', [JadwalRapatController::class, 'show'])->whereNumber('id')->name('show');
            Route::put('/{id}', [JadwalRapatController::class, 'update'])->whereNumber('id')->name('update');
            Route::post('/{id}/batal', [JadwalRapatController::class, 'batal'])->whereNumber('id')->name('batal');
            Route::post('/{id}/selesai', [JadwalRapatController::class, 'selesai'])->whereNumber('id')->name('selesai');
            Route::post('/{id}/aktifkan', [JadwalRapatController::class, 'aktifkan'])->whereNumber('id')->name('aktifkan');
            Route::get('/{id}/salin', [JadwalRapatController::class, 'salin'])->whereNumber('id')->name('salin');

            // Status pengiriman undangan WA (per rapat, dipakai halaman Buat Rapat)
            Route::get('/{id}/status-wa', [RapatController::class, 'statusWa'])->whereNumber('id')->name('status-wa');
            Route::post('/{id}/kirim-ulang', [RapatController::class, 'kirimUlang'])->whereNumber('id')->name('kirim-ulang');
        });

        // Notulensi rapat (controller membatasi ke rapat milik user)
        Route::prefix('notulensi')->name('notulensi.')->group(function () {
            Route::get('/', [NotulensiController::class, 'index'])->name('index');
            Route::get('/berkas/{idBerkas}', [NotulensiController::class, 'unduh'])->whereNumber('idBerkas')->name('unduh');
            Route::get('/{id}', [NotulensiController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('/{id}', [NotulensiController::class, 'update'])->whereNumber('id')->name('update');
            Route::post('/{id}/buka', [NotulensiController::class, 'buka'])->whereNumber('id')->name('buka');
            Route::get('/{id}/cetak', [NotulensiController::class, 'cetak'])->whereNumber('id')->name('cetak');
        });

        // Daftar Hadir: QR absensi + peserta yang sudah/belum hadir (rapat milik user; Super Admin: semua)
        Route::prefix('daftar-hadir')->name('hadir.')->group(function () {
            Route::get('/', [AbsensiController::class, 'index'])->name('index');
            Route::get('/{id}', [AbsensiController::class, 'hadir'])->whereNumber('id')->name('show');
            Route::get('/{id}/data', [AbsensiController::class, 'data'])->whereNumber('id')->name('data');
        });

        // Kelompok rapat: hanya route ini yang dipakai halaman Buat Rapat (memuat anggota kelompok tersimpan)
        Route::get('/kelompok/{id}/anggota', [KelompokRapatController::class, 'anggota'])->whereNumber('id')->name('kelompok.anggota');

        // Laporan & Rekap: User biasa hanya melihat data rapat yang dia buat (dibatasi di LaporanService)
        Route::prefix('laporan')->name('laporan.')->group(function () {
            Route::get('/', [LaporanController::class, 'index'])->name('index');
            Route::get('/pratinjau', [LaporanController::class, 'pratinjau'])->name('pratinjau');
            Route::get('/unduh', [LaporanController::class, 'unduh'])->name('unduh');
            Route::get('/cetak', [LaporanController::class, 'cetak'])->name('cetak');
        });

        // Status pengiriman WA (monitoring): Super Admin = seluruh rapat, User = rapat miliknya
        Route::prefix('status-wa')->name('wa.')->group(function () {
            Route::get('/', [StatusWaController::class, 'index'])->name('index');
            Route::post('/kirim-ulang', [StatusWaController::class, 'kirimUlang'])->name('kirim-ulang');
        });

        // ------------------------------------------------------------------
        // Khusus Super Admin
        // ------------------------------------------------------------------
        Route::middleware('super_admin')->group(function () {

            // Kelompok rapat: halaman kelola
            Route::get('/kelompok', [KelompokRapatController::class, 'index'])->name('kelompok.index');
            Route::get('/kelompok/anggota-semua', [KelompokRapatController::class, 'anggotaSemua'])->name('kelompok.anggota.semua');
            Route::post('/kelompok/baru', [KelompokRapatController::class, 'buat'])->name('kelompok.buat');
            Route::post('/kelompok', [KelompokRapatController::class, 'simpan'])->name('kelompok.simpan');
            Route::get('/kelompok/{id}/detail', [KelompokRapatController::class, 'detail'])->whereNumber('id')->name('kelompok.detail');
            Route::put('/kelompok/{id}', [KelompokRapatController::class, 'ubah'])->whereNumber('id')->name('kelompok.ubah');
            Route::delete('/kelompok/{id}', [KelompokRapatController::class, 'hapus'])->whereNumber('id')->name('kelompok.hapus');
            Route::post('/kelompok/{id}/anggota', [KelompokRapatController::class, 'tambahAnggota'])->whereNumber('id')->name('kelompok.anggota.tambah');
            Route::delete('/kelompok/{id}/anggota/{detail}', [KelompokRapatController::class, 'hapusAnggota'])->whereNumber('id')->whereNumber('detail')->name('kelompok.anggota.hapus');

            // Tempat rapat
            Route::get('/tempat-rapat', [TempatRapatController::class, 'index'])->name('tempat.index');
            Route::post('/tempat-rapat', [TempatRapatController::class, 'store'])->name('tempat.store');
            Route::put('/tempat-rapat/{tempat}', [TempatRapatController::class, 'update'])->name('tempat.update');
            Route::delete('/tempat-rapat/{tempat}', [TempatRapatController::class, 'destroy'])->name('tempat.destroy');
        });
    });
};

// ----------------------------------------------------------------------
// Pendaftaran
// ----------------------------------------------------------------------
if ($duaDomain) {
    Route::domain($hostAbsen)->group($ruteAbsen);   // siupinabsen.nhsolo.com
    Route::domain($hostApp)->group($ruteApp);       // siupinv2.nhsolo.com
} else {
    Route::prefix('absen')->group($ruteAbsen);      // satu domain: /absen
    $ruteApp();
}