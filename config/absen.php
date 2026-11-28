<?php

// Simpan sebagai: config/absen.php (ganti SELURUH isi file lama)

/*
| Ambil hanya nama host dari isian .env. Isian seperti "http://localhost:8000/absen"
| otomatis menjadi "localhost" (skema, port, dan path dibuang).
*/
$bersihkan = function ($nilai): string {
    $nilai = trim((string) $nilai);
    if ($nilai === '') {
        return '';
    }

    return strtolower((string) parse_url(str_contains($nilai, '://') ? $nilai : '//' . $nilai, PHP_URL_HOST));
};

$hostApp   = $bersihkan(env('SIUPIN_HOST'));
$hostAbsen = $bersihkan(env('ABSEN_HOST'));

return [

    /*
    | Mode dua domain dalam satu project (isi KEDUANYA dengan nama host yang BERBEDA):
    |   SIUPIN_HOST=siupinv2.nhsolo.com      -> aplikasi SIUPIN (login, dashboard, dst.)
    |   ABSEN_HOST=siupinabsen.nhsolo.com    -> hanya halaman absensi peserta
    | Bila salah satunya kosong, atau keduanya sama, semuanya memakai satu domain
    | dan absensi berada di path /absen.
    */
    'app_host' => $hostApp !== '' ? $hostApp : null,
    'host'     => ($hostAbsen !== '' && $hostAbsen !== $hostApp) ? $hostAbsen : null,

    /*
    | Form absen hanya terbuka lewat scan QR di lokasi rapat (link di WhatsApp hanya membuka scanner).
    | qr_berganti : true  -> QR di layar panitia berganti otomatis (tidak bisa dicetak/diteruskan)
    |               false -> QR tetap (boleh dicetak), tetap harus di-scan untuk membuka form
    | qr_detik    : lama satu kode QR sebelum berganti (detik)
    | izin_menit  : lama form tetap terbuka setelah QR di-scan (menit)
    */
    'qr_berganti' => filter_var(env('ABSEN_QR_BERGANTI', true), FILTER_VALIDATE_BOOLEAN),
    'qr_detik'    => (int) env('ABSEN_QR_DETIK', 60),
    'izin_menit'  => (int) env('ABSEN_IZIN_MENIT', 10),

];