<?php

// Simpan sebagai: config/wa.php

return [
    'provider'   => env('WA_PROVIDER', 'watzap'),

    // Alamat dasar API Watzap (endpoint kirim: {url}/v1/send_message)
    'url'        => env('WA_URL', 'https://api.watzap.id/v1/'),

    // API Key akun Watzap (Member Area). Isi di .env, jangan di sini.
    'token'      => env('WA_TOKEN', ''),

    // Number Key nomor pengirim (tab API di app.watzap.id). "ALL" = pilih acak dari nomor API yang Online.
    'number_key' => env('WA_NUMBER_KEY', 'ALL'),

    // Jeda antar pesan (detik), dipakai RapatController::antrekan
    'jeda'       => (int) env('WA_JEDA', 3),

    'timeout'    => (int) env('WA_TIMEOUT', 20),
];