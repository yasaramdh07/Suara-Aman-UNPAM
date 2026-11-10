<?php
declare(strict_types=1);

/**
 * Instalasi sekali jalan Suara Aman.
 * Hapus berkas public/install.php ini segera setelah instalasi selesai.
 */

define('BASE_PATH', dirname(__DIR__));
$dataDir = getenv('SA_INSTALL_DATA_DIR') ?: (BASE_PATH . '/data');
define('DATA_PATH', $dataDir);

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Folder tidak bisa dibuat: ' . $dir);
    }
}

require_once BASE_PATH . '/app/security.php';
require_once BASE_PATH . '/app/db.php';
require_once BASE_PATH . '/app/migrate.php';

date_default_timezone_set('Asia/Jakarta');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
send_security_headers();

$configFile = defined('SA_CONFIG_FILE')
    ? (string) SA_CONFIG_FILE
    : (getenv('SA_CONFIG_FILE') ?: (BASE_PATH . '/config.php'));

// Tolak berjalan jika config.php sudah ada.
if (is_file($configFile)) {
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Instalasi dikunci | Suara Aman</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/theme.css">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="sa-header">
        <div class="sa-container sa-header-inner">
            <a class="sa-wordmark" href="/">Suara <span class="sa-wordmark-accent">Aman</span><span class="sa-dot" aria-hidden="true"></span></a>
        </div>
    </header>
    <main class="flex-grow-1">
        <div class="sa-container sa-page">
            <h1 class="sa-title">Aplikasi sudah terpasang</h1>
            <div class="sa-note sa-note-error" role="alert">
                Berkas <code class="sa-mono">config.php</code> sudah ada. Demi keamanan, penginstal ini tidak dapat dijalankan lagi.
            </div>
            <p class="sa-prose">Segera hapus berkas <code class="sa-mono">public/install.php</code> dari server hosting kamu.</p>
            <div class="sa-actions">
                <a class="btn btn-primary" href="/bk/login">Ke halaman masuk</a>
                <a class="sa-link" href="/">Ke beranda</a>
            </div>
        </div>
    </main>
</body>
</html>
    <?php
    exit;
}

ensure_dir(DATA_PATH . '/sessions');
start_session();

$checks = [
    'PHP 8.1 atau lebih baru (terdeteksi ' . PHP_VERSION . ')' => PHP_VERSION_ID >= 80100,
    'Ekstensi pdo_sqlite'                                      => extension_loaded('pdo_sqlite'),
    'Ekstensi sodium (enkripsi)'                               => extension_loaded('sodium'),
    'Ekstensi gd (sanitasi gambar)'                            => extension_loaded('gd'),
    'Ekstensi mbstring'                                        => extension_loaded('mbstring'),
    'Ekstensi fileinfo'                                        => extension_loaded('fileinfo'),
    'Folder konfigurasi dapat ditulis'                         => is_writable(dirname($configFile)),
];
$allRequirementsMet = !in_array(false, $checks, true);

$errors   = [];
$username = '';
$done     = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        $errors[] = 'Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif (!$allRequirementsMet) {
        http_response_code(422);
        $errors[] = 'Prasyarat server belum terpenuhi semua.';
    } else {
        $username   = trim((string) ($_POST['username'] ?? ''));
        $password   = (string) ($_POST['password'] ?? '');
        $confirm    = (string) ($_POST['password_confirm'] ?? '');
        $resetPin   = trim((string) ($_POST['reset_pin'] ?? ''));
        $pinConfirm = trim((string) ($_POST['reset_pin_confirm'] ?? ''));

        if (preg_match('/^[a-zA-Z0-9_.-]{3,32}$/', $username) !== 1) {
            $errors[] = 'Username admin harus 3–32 karakter (huruf, angka, titik, garis bawah, atau tanda hubung).';
        }
        if (mb_strlen($password) < 8 || mb_strlen($password) > 128) {
            $errors[] = 'Kata sandi minimal 8 karakter.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Konfirmasi kata sandi tidak sama.';
        }
        if (preg_match('/^\d{6}$/', $resetPin) !== 1) {
            $errors[] = 'PIN reset harus tepat 6 digit angka (0–9).';
        } elseif ($resetPin !== $pinConfirm) {
            $errors[] = 'Konfirmasi PIN reset tidak sama.';
        }

        if ($errors === []) {
            ensure_dir(DATA_PATH);
            ensure_dir(DATA_PATH . '/uploads');
            ensure_dir(DATA_PATH . '/sessions');
            ensure_dir(DATA_PATH . '/logs');
            if (!is_file(DATA_PATH . '/.htaccess')) {
                @file_put_contents(
                    DATA_PATH . '/.htaccess',
                    "Options -Indexes -ExecCGI\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n"
                );
            }

            $dbPath = DATA_PATH . '/suara-aman.sqlite';
            $encKey = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
            $pepper = bin2hex(random_bytes(32));

            $cfgArray = [
                'db_path'        => $dbPath,
                'encryption_key' => $encKey,
                'pepper'         => $pepper,
                'reset_pin_hash' => password_hash($resetPin, PASSWORD_BCRYPT),
                'debug'          => false,
                'timezone'       => 'Asia/Jakarta',
            ];
            $cfgCode = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($cfgArray, true) . ";\n";

            if (file_put_contents($configFile, $cfgCode, LOCK_EX) === false) {
                $errors[] = 'Gagal menulis config.php.';
            } else {
                @chmod($configFile, 0600);
                try {
                    $pdo = db_connect($dbPath);
                    migrate($pdo);
                    seed_categories($pdo);

                    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, is_active, created_at) VALUES (?, ?, \'admin\', 1, ?)');
                    $stmt->execute([$username, password_hash($password, PASSWORD_BCRYPT), now_full()]);
                    $done = true;
                } catch (Throwable $e) {
                    error_log(sprintf('Install error: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
                    @unlink($configFile);
                    $errors[] = 'Gagal menyiapkan database SQLite. Periksa izin tulis pada folder data/.';
                }
            }
        }

        if ($errors !== []) {
            http_response_code(422);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Instalasi | Suara Aman</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/theme.css">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="sa-header">
        <div class="sa-container sa-header-inner">
            <span class="sa-wordmark">Suara <span class="sa-wordmark-accent">Aman</span><span class="sa-dot" aria-hidden="true"></span></span>
        </div>
    </header>

    <main class="flex-grow-1">
        <div class="sa-container sa-page">
            <?php if ($done): ?>
                <h1 class="sa-title">Instalasi selesai</h1>
                <div class="sa-note" role="status">
                    Konfigurasi kunci enkripsi, database SQLite, kategori awal, dan akun admin <strong><?= e($username) ?></strong> berhasil dibuat.
                </div>
                <div class="sa-note sa-note-warn">
                    <strong>Langkah wajib sebelum aplikasi digunakan:</strong>
                    <ul class="mb-0">
                        <li>Hapus berkas <code class="sa-mono">public/install.php</code> dari server sebelum aplikasi dibuka untuk siswa.</li>
                        <li>Simpan cadangan berkas <code class="sa-mono">config.php</code> di tempat terpisah yang aman. Tanpa kunci enkripsi di dalamnya, laporan tidak dapat dibaca.</li>
                    </ul>
                </div>
                <div class="sa-actions">
                    <a class="btn btn-primary" href="/bk/login">Masuk sebagai Admin</a>
                    <a class="sa-link" href="/">Lihat beranda</a>
                </div>
            <?php else: ?>
                <h1 class="sa-title">Instalasi Suara Aman</h1>
                <p class="sa-prose text-muted">Halaman ini memeriksa kesiapan server, membuat kunci enkripsi acak, menyiapkan database SQLite, dan mendaftarkan akun Admin pertama.</p>

                <?php if ($errors !== []): ?>
                    <div class="sa-note sa-note-error" role="alert">
                        <strong>Periksa kembali:</strong>
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <h2 class="h2 mt-4 mb-2">1. Pemeriksaan server</h2>
                <dl class="sa-meta mb-4">
                    <?php foreach ($checks as $label => $ok): ?>
                        <div>
                            <dt><?= $ok ? 'Siap' : 'Kurang' ?></dt>
                            <dd><?= e($label) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>

                <?php if ($allRequirementsMet): ?>
                    <h2 class="h2 mb-3">2. Buat akun Admin pertama</h2>
                    <form method="post" action="/install.php" class="sa-login-form" novalidate>
                        <?= csrf_field() ?>
                        <div class="sa-field">
                            <label class="form-label" for="username">Username Admin</label>
                            <input type="text" id="username" name="username" class="form-control"
                                   value="<?= e($username) ?>" minlength="3" maxlength="32" autocomplete="username" required>
                        </div>
                        <div class="sa-field">
                            <label class="form-label" for="password">Kata sandi</label>
                            <input type="password" id="password" name="password" class="form-control"
                                   minlength="8" maxlength="128" autocomplete="new-password" aria-describedby="help-inst-pw" required>
                            <p class="sa-help" id="help-inst-pw">Minimal 8 karakter.</p>
                        </div>
                        <div class="sa-field">
                            <label class="form-label" for="password_confirm">Ulangi kata sandi</label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                                   minlength="8" maxlength="128" autocomplete="new-password" required>
                        </div>
                        <div class="sa-field">
                            <label class="form-label" for="reset_pin">PIN reset data (6 digit angka)</label>
                            <input type="password" id="reset_pin" name="reset_pin" class="form-control sa-input-short"
                                   inputmode="numeric" pattern="\d{6}" minlength="6" maxlength="6"
                                   autocomplete="off" aria-describedby="help-inst-pin" required>
                            <p class="sa-help" id="help-inst-pin">Dipakai khusus saat mereset seluruh data sistem. Simpan baik-baik.</p>
                        </div>
                        <div class="sa-field">
                            <label class="form-label" for="reset_pin_confirm">Ulangi PIN reset data</label>
                            <input type="password" id="reset_pin_confirm" name="reset_pin_confirm" class="form-control sa-input-short"
                                   inputmode="numeric" pattern="\d{6}" minlength="6" maxlength="6"
                                   autocomplete="off" required>
                        </div>
                        <div class="sa-actions">
                            <button type="submit" class="btn btn-primary">Pasang Suara Aman</button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

