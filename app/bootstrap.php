<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');

/** Buat folder (izin 0700) bila belum ada. */
function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Folder tidak bisa dibuat: ' . $dir);
    }
}

/** Ambil nilai dari config.php (SA_CONFIG_FILE, konstanta atau env, hanya dipakai oleh tes). */
function config(string $key): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $file = defined('SA_CONFIG_FILE') ? SA_CONFIG_FILE : (getenv('SA_CONFIG_FILE') ?: BASE_PATH . '/config.php');
        if (!is_file($file)) {
            throw new RuntimeException('config.php tidak ditemukan. Salin dari config.example.php.');
        }
        $cfg = require $file;
    }
    if (!array_key_exists($key, $cfg)) {
        throw new RuntimeException('Kunci konfigurasi tidak ada: ' . $key);
    }
    return $cfg[$key];
}

/** Penanganan galat tak terduga: catat ke log, tampilkan halaman 500 yang generik. */
function handle_uncaught(Throwable $e): void
{
    // Log hanya kelas, pesan, dan lokasi; tidak pernah isi laporan.
    error_log(sprintf('%s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    try {
        $debug = (bool) config('debug');
    } catch (Throwable) {
        $debug = false;
    }
    if ($debug) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $e;
        return;
    }
    try {
        abort(500);
    } catch (Throwable) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Terjadi kesalahan.';
    }
}

foreach (['db', 'crypto', 'security', 'view', 'router', 'ratelimit', 'captcha', 'upload', 'reports', 'pages_reporter', 'auth', 'pages_bk', 'pages_admin'] as $module) {
    require_once __DIR__ . '/' . $module . '.php';
}

set_exception_handler('handle_uncaught');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('zend.exception_ignore_args', '1'); // cegah argumen sensitif masuk ke stack trace
ensure_dir(DATA_PATH . '/logs');
ini_set('error_log', DATA_PATH . '/logs/error.log');

date_default_timezone_set((string) config('timezone'));
ini_set('display_errors', config('debug') ? '1' : '0');

