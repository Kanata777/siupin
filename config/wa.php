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

    // Jeda tetap lama (detik). Tidak dipakai lagi oleh antrekan(), disimpan agar kompatibel.
    'jeda'       => (int) env('WA_JEDA', 3),

    // ---- Jeda antar pesan: [minimum, maksimum] detik, dipilih acak untuk tiap pesan ----
    'profil' => [
        'cepat'  => [3, 6],
        'normal' => [6, 12],
        'aman'   => [15, 30],
    ],

    // Profil bawaan bila form tidak mengirim pilihan (mis. saat "Kirim Ulang")
    'profil_bawaan' => env('WA_PROFIL', 'normal'),

    // Setiap N pesan, tambahkan istirahat panjang (detik). Isi 0 untuk mematikan.
    'per_batch'  => (int) env('WA_PER_BATCH', 20),
    'jeda_batch' => (int) env('WA_JEDA_BATCH', 90),

    'timeout'    => (int) env('WA_TIMEOUT', 20),
];