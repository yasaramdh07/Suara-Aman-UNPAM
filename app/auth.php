<?php
declare(strict_types=1);

const LOGIN_MAX_FAILS   = 5;
const LOGIN_BUCKET_SRC  = 'login_src';
const LOGIN_BUCKET_USER = 'login_user';

// Hash dummy bcrypt untuk menjaga waktu verifikasi tetap seragam saat username tidak ditemukan.
const LOGIN_DUMMY_HASH = '$2y$10$PwDS/ULLkbu733LWQwuIru3NGIii3VmlvodwNeQSRXTyI6itzMXB6';

/**
 * Ambil data pengguna yang sedang login dari session dan pastikan masih ada
 * serta is_active = 1 di database pada setiap request.
 */
function auth_user(): ?array
{
    $sessionUser = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : null;
    if ($sessionUser === null || !isset($sessionUser['id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, username, role, is_active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $sessionUser['id']]);
    $user = $stmt->fetch();
    $stmt->closeCursor();

    if (!$user || (int) $user['is_active'] !== 1) {
        unset($_SESSION['user']);
        return null;
    }

    $_SESSION['user'] = [
        'id'       => (int) $user['id'],
        'username' => (string) $user['username'],
        'role'     => (string) $user['role'],
    ];

    return $_SESSION['user'];
}

/**
 * Middleware akses area BK dan admin.
 * - Belum login atau akun sudah dihapus/dinonaktifkan -> keluarkan dan alihkan ke /bk/login.
 * - Role tidak sesuai (mis. admin membuka /bk/* atau BK membuka /admin/*) -> 403.
 */
function auth_require(string $role): array
{
    $user = auth_user();
    if ($user === null) {
        redirect('/bk/login');
    }

    if ($user['role'] !== $role) {
        abort(403);
    }

    return $user;
}

/**
 * Middleware untuk rute bersama staf (role bk maupun admin, mis. /akun).
 */
function auth_require_staff(): array
{
    $user = auth_user();
    if ($user === null) {
        redirect('/bk/login');
    }
    if (!in_array($user['role'], ['bk', 'admin'], true)) {
        abort(403);
    }
    return $user;
}

/**
 * Verifikasi login dengan batas 5 percobaan gagal per 15 menit (per sumber dan per username).
 * Semua kegagalan kredensial mengembalikan pesan generik yang sama.
 *
 * @return array{ok: bool, status?: int, error?: string, role?: string}
 */
function auth_attempt(string $username, string $password): array
{
    $userKey = $username !== '' ? rate_username_key($username) : null;

    if (
        rate_is_blocked(LOGIN_BUCKET_SRC, LOGIN_MAX_FAILS)
        || ($userKey !== null && rate_is_blocked(LOGIN_BUCKET_USER, LOGIN_MAX_FAILS, $userKey))
    ) {
        return [
            'ok'     => false,
            'status' => 429,
            'error'  => 'Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit.',
        ];
    }

    $user = null;
    if ($username !== '' && mb_strlen($username) <= 60) {
        $stmt = db()->prepare('SELECT id, username, password_hash, role, is_active FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch() ?: null;
    }

    $hash  = ($user && (int) $user['is_active'] === 1) ? (string) $user['password_hash'] : LOGIN_DUMMY_HASH;
    $valid = $password !== '' && password_verify($password, $hash);

    if (!$valid || !$user || (int) $user['is_active'] !== 1) {
        rate_register_failure(LOGIN_BUCKET_SRC);
        if ($userKey !== null) {
            rate_register_failure(LOGIN_BUCKET_USER, $userKey);
        }
        return [
            'ok'     => false,
            'status' => 200,
            'error'  => 'Username atau kata sandi salah.',
        ];
    }

    // Sukses: ganti ID session untuk mencegah session fixation.
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'       => (int) $user['id'],
        'username' => (string) $user['username'],
        'role'     => (string) $user['role'],
    ];

    return [
        'ok'   => true,
        'role' => (string) $user['role'],
    ];
}

/** Hapus session login dan ganti ID session. */
function auth_logout(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    redirect('/bk/login');
}
