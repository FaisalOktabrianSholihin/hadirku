<?php

return [
    // Akurasi GPS maksimal (meter). 0 = tidak dicek
    'maks_akurasi' => (int) env('HADIRKU_MAKS_AKURASI', 200),

    // Boleh absen pada hari libur (nasional/rutin)? Default: tidak
    'izinkan_absen_libur' => (bool) env('HADIRKU_ABSEN_LIBUR', false),

    'radius_default' => 150,
    'maks_foto_kb' => 2048,
    'nonce_menit' => 15,
];
