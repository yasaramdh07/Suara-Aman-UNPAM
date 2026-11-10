<?php
declare(strict_types=1);

/**
 * Skrip CLI sekali pakai untuk mengatur atau memperbarui hash PIN reset (6 digit) di config.php.
 * Hapus berkas tools/set_reset_pin.php ini segera setelah selesai digunakan.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Skrip ini hanya dapat dijalankan dari baris perintah (CLI).\n");
}

$basePath   = dirname(__DIR__);
$configFile = getenv('SA_CONFIG_FILE') ?: ($basePath . '/config.php');

if (!is_file($configFile)) {
    fwrite(STDERR, "Galat: config.php tidak ditemukan di {$configFile}.\n");
    exit(1);
}

/**
 * Baca input baris perintah tanpa menampilkan karakter di layar (bila terminal interaktif).
 */
function read_secret_cli(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $isTty = function_exists('stream_isatty') && @stream_isatty(STDIN);
    if (!$isTty) {
        $line = fgets(STDIN);
        fwrite(STDOUT, "\n");
        return trim((string) $line);
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        $cmd = 'powershell -NoProfile -Command "$p = Read-Host -AsSecureString; '
             . '$b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($p); '
             . '[Runtime.InteropServices.Marshal]::PtrToStringAuto($b)"';
        $out = shell_exec($cmd);
        fwrite(STDOUT, "\n");
        return trim((string) $out);
    }

    @shell_exec('stty -echo 2>/dev/null');
    $line = fgets(STDIN);
    @shell_exec('stty echo 2>/dev/null');
    fwrite(STDOUT, "\n");
    return trim((string) $line);
}

$pin = read_secret_cli('Masukkan PIN reset baru (6 digit angka): ');
if (preg_match('/^\d{6}$/', $pin) !== 1) {
    fwrite(STDERR, "Galat: PIN reset harus tepat 6 digit angka (0-9).\n");
    exit(1);
}

$confirm = read_secret_cli('Ulangi PIN reset baru (6 digit angka): ');
if (!hash_equals($pin, $confirm)) {
    fwrite(STDERR, "Galat: Konfirmasi PIN tidak cocok.\n");
    exit(1);
}

$cfg = require $configFile;
if (!is_array($cfg)) {
    fwrite(STDERR, "Galat: Format config.php tidak valid.\n");
    exit(1);
}

$cfg['reset_pin_hash'] = password_hash($pin, PASSWORD_BCRYPT);
$code = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($cfg, true) . ";\n";

if (file_put_contents($configFile, $code, LOCK_EX) === false) {
    fwrite(STDERR, "Galat: Gagal menulis ke {$configFile}.\n");
    exit(1);
}
@chmod($configFile, 0600);

fwrite(STDOUT, "Berhasil: Hash PIN reset telah disimpan ke config.php.\n");
fwrite(STDOUT, "PENTING: Segera hapus berkas tools/set_reset_pin.php ini dari server!\n");
