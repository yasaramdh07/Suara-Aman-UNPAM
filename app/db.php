<?php
declare(strict_types=1);

/** Buka koneksi SQLite: WAL, foreign key aktif, galat berupa exception. */
function db_connect(string $path): PDO
{
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    // Data yang dihapus ditimpa nol, bukan dibiarkan di halaman file.
    $pdo->exec('PRAGMA secure_delete = ON');
    return $pdo;
}

/** Koneksi bersama untuk satu permintaan. */
function db(): PDO
{
    static $pdo = null;
    return $pdo ??= db_connect((string) config('db_path'));
}

/**
 * Waktu untuk reports dan messages: hanya sampai jam (menit dan detik 00),
 * supaya waktu kirim tidak bisa dicocokkan dengan log lain.
 */
function now_hour(): string
{
    return date('Y-m-d H:00:00');
}

/** Waktu lengkap untuk catatan aktivitas BK/admin (users, internal_notes, access_log). */
function now_full(): string
{
    return date('Y-m-d H:i:s');
}

