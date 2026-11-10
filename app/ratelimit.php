<?php
declare(strict_types=1);

const RATE_MAX_FAILS = 10;
const RATE_WINDOW    = 900; // 15 menit

/**
 * Kunci sumber untuk rate limit: HMAC dari IP dengan salt yang berganti tiap hari.
 * IP mentah tidak pernah disimpan; hash hari kemarin tidak bisa dicocokkan dengan hari ini.
 * Hanya REMOTE_ADDR yang dipakai (header X-Forwarded-For bisa dipalsukan).
 */
function rate_source_key(): string
{
    $daily_salt = hash_hmac('sha256', 'rate-salt|' . date('Y-m-d'), crypto_pepper());
    return hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $daily_salt);
}

/** Hapus entri kedaluwarsa. */
function rate_cleanup(): void
{
    db()->prepare('DELETE FROM rate_limits WHERE expires_at <= ?')->execute([time()]);
}

/** Apakah kunci ini sudah mencapai batas kegagalan pada bucket tersebut. */
function rate_is_blocked(string $bucket, int $max = RATE_MAX_FAILS, ?string $key = null): bool
{
    rate_cleanup();
    $stmt = db()->prepare('SELECT count FROM rate_limits WHERE key_hash = ? AND bucket = ? AND expires_at > ?');
    $stmt->execute([$key ?? rate_source_key(), $bucket, time()]);
    return (int) $stmt->fetchColumn() >= $max;
}

/** Kunci per-username untuk rate limit login (tidak menyimpan username mentah). */
function rate_username_key(string $username): string
{
    return hash_hmac('sha256', 'login-user|' . mb_strtolower($username), crypto_pepper());
}

/** Kunci per-ID pengguna untuk rate limit aksi sensitif (reset data, ubah akun). */
function rate_user_key(string $scope, int $userId): string
{
    return hash_hmac('sha256', $scope . '|' . $userId, crypto_pepper());
}

/** Catat satu kegagalan; jendela 15 menit dimulai dari kegagalan pertama. */
function rate_register_failure(string $bucket, ?string $key = null): void
{
    $now = time();
    db()->prepare(
        'INSERT INTO rate_limits (key_hash, bucket, count, expires_at) VALUES (:key, :bucket, 1, :expires)
         ON CONFLICT (key_hash, bucket) DO UPDATE SET
             count      = CASE WHEN expires_at > :now THEN count + 1 ELSE 1 END,
             expires_at = CASE WHEN expires_at > :now THEN expires_at ELSE :expires END'
    )->execute([
        'key'     => $key ?? rate_source_key(),
        'bucket'  => $bucket,
        'expires' => $now + RATE_WINDOW,
        'now'     => $now,
    ]);
}

