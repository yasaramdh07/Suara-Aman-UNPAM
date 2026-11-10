<?php
/**
 * Contoh konfigurasi Suara Aman.
 *
 * Salin menjadi config.php (di luar public/), lalu isi nilai rahasianya.
 * JANGAN commit config.php. Simpan cadangan kunci enkripsi di tempat terpisah:
 * tanpa kunci ini, isi laporan tidak bisa dibaca lagi.
 *
 * Membuat kunci enkripsi (32 byte, base64):
 *   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
 * Membuat pepper (64 karakter hex):
 *   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
 */
return [
    // Lokasi file database SQLite. Harus di luar public/.
    'db_path' => __DIR__ . '/data/suara-aman.sqlite',

    // Kunci enkripsi sodium_crypto_secretbox: base64 dari tepat 32 byte.
    'encryption_key' => '',

    // Rahasia untuk HMAC kode tiket (minimal 32 karakter). Beda dari kunci enkripsi.
    'pepper' => '',

    // Hash bcrypt dari PIN 6 digit untuk fitur reset data admin (jangan simpan PIN asli).
    'reset_pin_hash' => '',

    // Mode debug: tampilkan galat di layar. WAJIB false di produksi.
    'debug' => false,

    'timezone' => 'Asia/Jakarta',
];

