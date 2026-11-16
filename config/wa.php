<?php

return [
    'url'   => env('WA_URL', 'https://api.fonnte.com/send'),
    'token' => env('WA_TOKEN'),
    'jeda'  => (int) env('WA_JEDA', 3),   // detik antar pesan, supaya nomor tidak diblokir
];