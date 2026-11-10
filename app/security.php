<?php
declare(strict_types=1);

/** Escape untuk output HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** Header keamanan untuk semua respons. CSP tidak mengizinkan sumber eksternal. */
function send_security_headers(): void
{
    header_remove('X-Powered-By');
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; "
        . "img-src 'self' data:; font-src 'self'; connect-src 'self'; "
        . "form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

/**
 * Mulai session dengan cookie aman. Dipanggil sebelum ada output.
 * SameSite=Strict dipakai untuk semua halaman (termasuk area BK).
 * Berkas session disimpan di data/sessions, bukan folder temp bersama.
 * Session cache limiter bawaan ("nocache") membuat halaman tidak di-cache browser.
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ensure_dir(DATA_PATH . '/sessions');
    session_save_path(DATA_PATH . '/sessions');
    session_name('sa_sid');
    ini_set('session.use_strict_mode', '1');   // tolak session ID buatan penyerang
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', '7200');
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/** Token CSRF per session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Input hidden untuk form POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Bandingkan token dengan waktu konstan. */
function csrf_verify(mixed $token): bool
{
    return is_string($token)
        && $token !== ''
        && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

