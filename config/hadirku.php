<?php

return [
    // Akurasi GPS maksimal (meter). 0 = tidak dicek
    'maks_akurasi' => (int) env('HADIRKU_MAKS_AKURASI', 200),

    // Boleh absen pada hari libur (nasional/rutin)? Default: tidak
    'izinkan_absen_libur' => (bool) env('HADIRKU_ABSEN_LIBUR', false),

    // ===== Anti-kecurangan =====
    // Deteksi Fake GPS / Mock Location berbasis pola data lokasi (browser tidak bisa membaca flag isMock Android)
    'deteksi_gps' => (bool) env('HADIRKU_DETEKSI_GPS', true),
    // Skor minimal agar dianggap Fake GPS (semakin kecil semakin ketat, bisa memicu salah deteksi)
    'skor_fake_gps' => (int) env('HADIRKU_SKOR_FAKE_GPS', 3),
    // Toleransi selisih jam perangkat vs jam server (detik). Lebih dari ini = dianggap mengubah waktu HP
    'toleransi_waktu' => (int) env('HADIRKU_TOLERANSI_WAKTU', 120),

    // Import Excel berjalan di latar belakang lewat queue (jalankan: php artisan queue:work).
    // Set HADIRKU_IMPORT_QUEUE=false bila tidak memakai worker: import dijalankan sesudah respons dikirim.
    'import_queue' => (bool) env('HADIRKU_IMPORT_QUEUE', true),

    'radius_default' => 150,
    'maks_foto_kb' => 2048,
    'nonce_menit' => 15,
];
