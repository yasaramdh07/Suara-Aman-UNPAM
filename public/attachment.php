<?php
declare(strict_types=1);

/**
 * Skrip penyaji gambar bukti dari data/uploads/ (di luar document root).
 * Hanya dapat diakses oleh Guru BK yang sudah login.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

send_security_headers();
start_session();
auth_require('bk');

if (isset($_GET['id']) && ctype_digit((string) $_GET['id'])) {
    page_bk_attachment(['id' => (string) $_GET['id']]);
    exit;
}

bk_serve_upload_file((string) ($_GET['f'] ?? ''));
