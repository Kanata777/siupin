<?php

// Simpan sebagai: config/absen.php (ganti SELURUH isi file lama)

return [

    /*
    | Mode dua domain dalam satu project.
    | Isi KEDUANYA (tanpa https://) agar aplikasi dan absensi dilayani domain yang berbeda:
    |   SIUPIN_HOST=siupinv2.nhsolo.com      -> aplikasi SIUPIN (login, dashboard, dst.)
    |   ABSEN_HOST=siupinabsen.nhsolo.com    -> hanya halaman absensi peserta
    | Bila salah satunya kosong, semuanya memakai satu domain dan absensi ada di path /absen.
    */
    'app_host' => env('SIUPIN_HOST'),
    'host'     => env('ABSEN_HOST'),

];