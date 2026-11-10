<?php
declare(strict_types=1);

/**
 * Tes instalasi dari nol dan alur Admin (Tahap 4): php tests/admin_install_test.php
 * Menguji install.php sekali jalan pada folder bersih, penolakan jalan kedua kali,
 * kelola akun BK (buat, reset sandi, aktif/nonaktif, cegah hapus diri sendiri/admin terakhir),
 * kelola kategori, statistik agregat tanpa isi laporan, backup database, dan pemisahan peran BK vs Admin.
 */

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sa_adm_' . bin2hex(random_bytes(4));
mkdir($tmp);
$cfgPath = $tmp . '/config.php';
$dataDir = $tmp . '/data';

putenv('SA_CONFIG_FILE=' . $cfgPath);
putenv('SA_INSTALL_DATA_DIR=' . $dataDir);

$basePath = dirname(__DIR__);
$port     = 8100;
$base     = "http://127.0.0.1:$port";
$server   = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $basePath . '/public'],
    [1 => ['file', $tmp . '/server.log', 'w'], 2 => ['file', $tmp . '/server.err', 'w']],
    $pipes
);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
    usleep(100000);
}

$failures = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo ($ok ? '[OK]    ' : '[GAGAL] ') . $name . ($ok || $detail === '' ? '' : "  -> $detail") . "\n";
    $failures += $ok ? 0 : 1;
}

function http(string $method, string $path, array $post = []): array
{
    global $base, $jar;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_PROXY          => '',
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw  = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hs   = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [$code, substr($raw, 0, $hs), substr($raw, $hs)];
}

function new_session(): void
{
    global $jar, $tmp;
    $jar = $tmp . '/jar_' . bin2hex(random_bytes(3)) . '.txt';
}

function csrf_from(string $path): string
{
    [, , $html] = http('GET', $path);
    preg_match('/name="_csrf" value="([0-9a-f]+)"/', $html, $m);
    return $m[1] ?? '';
}

// --- 1. Instalasi dari nol di folder bersih ---
new_session();
check('prasyarat: config.php belum ada', !is_file($cfgPath));
[$code, , $htmlInstall] = http('GET', '/install.php');
check('GET /install.php: 200, cek versi PHP & ekstensi siap', $code === 200 && str_contains($htmlInstall, 'Instalasi Suara Aman') && !str_contains($htmlInstall, '<dt>Kurang</dt>'));

preg_match('/name="_csrf" value="([0-9a-f]+)"/', $htmlInstall, $m);
$csrf = $m[1] ?? '';
[$code, , $htmlDone] = http('POST', '/install.php', [
    '_csrf'             => $csrf,
    'username'          => 'admin_utama',
    'password'          => 'AdminSandi-2026!',
    'password_confirm'  => 'AdminSandi-2026!',
    'reset_pin'         => '135790',
    'reset_pin_confirm' => '135790',
]);
clearstatcache();
check('POST /install.php: 200, membuat config.php & menampilkan instruksi hapus install.php',
    $code === 200 && is_file($cfgPath) && str_contains($htmlDone, 'Instalasi selesai') && str_contains($htmlDone, 'public/install.php'));

$cfg = require $cfgPath;
$rawKey = base64_decode((string) ($cfg['encryption_key'] ?? ''), true);
check('config.php: kunci enkripsi 32 byte & pepper 64 hex',
    is_string($rawKey) && strlen($rawKey) === 32 && preg_match('/^[0-9a-f]{64}$/', (string) ($cfg['pepper'] ?? '')) === 1);

// --- 2. install.php menolak dijalankan kedua kali ---
[$code, , $htmlLocked] = http('GET', '/install.php');
check('GET /install.php kedua kali ditolak 403', $code === 403 && str_contains($htmlLocked, 'Aplikasi sudah terpasang'));
[$code] = http('POST', '/install.php', ['_csrf' => $csrf, 'username' => 'hacker', 'password' => '12345678', 'password_confirm' => '12345678']);
check('POST /install.php kedua kali ditolak 403', $code === 403);

// --- 3. Masuk sebagai Admin pertama ---
new_session();
[$code, $headers] = http('POST', '/bk/login', [
    '_csrf'    => csrf_from('/bk/login'),
    'username' => 'admin_utama',
    'password' => 'AdminSandi-2026!',
]);
check('login admin pertama -> 303 ke /admin/statistik', $code === 303 && str_contains($headers, 'Location: /admin/statistik'));

// --- 4. /admin/akun: proteksi admin terakhir, buat akun BK, reset sandi, nonaktifkan/aktifkan ---
[$code, , $htmlAcc] = http('GET', '/admin/akun');
check('GET /admin/akun: 200 dan menampilkan admin_utama', $code === 200 && str_contains($htmlAcc, 'admin_utama'));

// Coba nonaktifkan diri sendiri (ID 1)
http('POST', '/admin/akun/1/status', ['_csrf' => csrf_from('/admin/akun')]);
[, , $htmlSelf] = http('GET', '/admin/akun');
check('admin tidak bisa menonaktifkan dirinya sendiri', str_contains($htmlSelf, 'tidak dapat menonaktifkan akunmu sendiri'));

// Buat akun BK baru
[$code, $headers] = http('POST', '/admin/akun/buat', [
    '_csrf'    => csrf_from('/admin/akun'),
    'username' => 'bk_yabika',
    'password' => 'SandiAwalBK-123',
    'role'     => 'bk',
]);
check('admin membuat akun BK baru -> 303', $code === 303);
[, , $htmlAcc2] = http('GET', '/admin/akun');
check('akun bk_yabika tampil di daftar akun', str_contains($htmlAcc2, 'bk_yabika'));

// Reset password akun BK (ID 2)
http('POST', '/admin/akun/2/password', ['_csrf' => csrf_from('/admin/akun'), 'password' => 'SandiBaruBK-456']);
[, , $htmlAcc3] = http('GET', '/admin/akun');
check('reset password akun BK berhasil', str_contains($htmlAcc3, 'berhasil direset'));

// Nonaktifkan lalu aktifkan kembali akun BK
http('POST', '/admin/akun/2/status', ['_csrf' => csrf_from('/admin/akun')]);
[, , $htmlAcc4] = http('GET', '/admin/akun');
check('nonaktifkan akun BK berhasil', str_contains($htmlAcc4, 'Nonaktif'));
http('POST', '/admin/akun/2/status', ['_csrf' => csrf_from('/admin/akun')]);
[, , $htmlAcc5] = http('GET', '/admin/akun');
check('aktifkan kembali akun BK berhasil', !str_contains($htmlAcc5, '>Nonaktif<'));

// --- 5. /admin/kategori: seed 6 kategori, tambah, ubah nama, nonaktifkan ---
[$code, , $htmlCat] = http('GET', '/admin/kategori');
$seedFound = 0;
foreach (['Kekerasan fisik', 'Perundungan verbal', 'Perundungan online (siber)', 'Pengucilan', 'Pemerasan atau pemalakan', 'Lainnya'] as $cName) {
    $seedFound += str_contains($htmlCat, $cName) ? 1 : 0;
}
check('GET /admin/kategori: 200 dan memuat 6 kategori seed awal', $code === 200 && $seedFound === 6);

http('POST', '/admin/kategori/tambah', ['_csrf' => csrf_from('/admin/kategori'), 'name' => 'Intimidasi ekstrakurikuler']);
[, , $htmlCat2] = http('GET', '/admin/kategori');
check('tambah kategori baru berhasil', str_contains($htmlCat2, 'Intimidasi ekstrakurikuler'));

http('POST', '/admin/kategori/7/ubah', ['_csrf' => csrf_from('/admin/kategori'), 'name' => 'Perundungan kegiatan sekolah']);
[, , $htmlCat3] = http('GET', '/admin/kategori');
check('ubah nama kategori berhasil', str_contains($htmlCat3, 'Perundungan kegiatan sekolah'));

http('POST', '/admin/kategori/7/status', ['_csrf' => csrf_from('/admin/kategori')]);
[, , $htmlCat4] = http('GET', '/admin/kategori');
check('nonaktifkan kategori berhasil', str_contains($htmlCat4, 'Nonaktif'));

// --- 6. Kirim 1 laporan rahasia dari sisi pelapor, lalu cek /admin/statistik & /admin/backup ---
$adminJar = $jar;
new_session();
[, , $formHtml] = http('GET', '/lapor');
preg_match('/name="_csrf" value="([0-9a-f]+)"/', $formHtml, $cm);
preg_match('/Berapa (\d) (ditambah|dikurangi) (\d)\?/', $formHtml, $qm);
$ans = (string) (($qm[2] ?? '') === 'ditambah' ? ((int) $qm[1] + (int) $qm[3]) : ((int) $qm[1] - (int) $qm[3]));
http('POST', '/lapor', [
    '_csrf'    => $cm[1] ?? '',
    'peran'    => 'korban',
    'kategori' => '1',
    'waktu'    => date('Y-m-d\TH:i', strtotime('-1 hour')),
    'lokasi'   => 'RAHASIA-ADMIN-TIDAK-BOLEH-BACA',
    'uraian'   => 'RAHASIA-ADMIN-ISI-LAPORAN-SANGAT-RAHASIA',
    'terlibat' => 'RAHASIA-PIHAK-TERLIBAT',
    'jawaban'  => $ans,
]);

// Kembali ke sesi Admin
$jar = $adminJar;
[$code, , $htmlStats] = http('GET', '/admin/statistik');
check('GET /admin/statistik: 200, menampilkan agregat (total 1)',
    $code === 200 && str_contains($htmlStats, 'Statistik agregat') && str_contains($htmlStats, '>1<'));
check('/admin/statistik tidak memuat teks laporan atau tautan ke /bk/laporan/',
    !str_contains($htmlStats, 'RAHASIA-ADMIN') && !str_contains($htmlStats, '/bk/laporan/'));

$adminCode = (string) file_get_contents($basePath . '/app/pages_admin.php');
check('kode pages_admin.php tidak mengandung kolom *_enc maupun panggilan decrypt()',
    !str_contains($adminCode, '_enc') && !str_contains($adminCode, 'decrypt('));

// Backup database
[$code, , $htmlBackup] = http('GET', '/admin/backup');
check('GET /admin/backup: 200', $code === 200 && str_contains($htmlBackup, 'Unduh database'));
[$code] = http('POST', '/admin/backup/unduh', []);
check('POST /admin/backup/unduh tanpa CSRF -> 403', $code === 403);
[$code, $headers, $sqliteBytes] = http('POST', '/admin/backup/unduh', ['_csrf' => csrf_from('/admin/backup')]);
check('POST /admin/backup/unduh dengan CSRF -> 200 file SQLite valid & terenkripsi',
    $code === 200 && str_starts_with($sqliteBytes, 'SQLite format 3') && !str_contains($sqliteBytes, 'RAHASIA-ADMIN'));

// --- 7. Login sebagai BK yang dibuat Admin & coba akses semua rute Admin (harus 403) ---
new_session();
[$code, $headers] = http('POST', '/bk/login', [
    '_csrf'    => csrf_from('/bk/login'),
    'username' => 'bk_yabika',
    'password' => 'SandiBaruBK-456',
]);
check('login sebagai BK (akun buatan admin) berhasil -> 303 ke /bk/laporan',
    $code === 303 && str_contains($headers, 'Location: /bk/laporan'));

$bkCsrf = csrf_from('/bk/laporan');
foreach (['/admin/akun', '/admin/kategori', '/admin/statistik', '/admin/backup'] as $r) {
    [$code] = http('GET', $r);
    check("BK GET $r -> 403", $code === 403, "kode $code");
}
foreach (['/admin/akun/buat', '/admin/akun/1/status', '/admin/akun/1/password', '/admin/kategori/tambah', '/admin/kategori/1/ubah', '/admin/kategori/1/status', '/admin/backup/unduh'] as $r) {
    [$code] = http('POST', $r, ['_csrf' => $bkCsrf]);
    check("BK POST $r -> 403", $code === 403, "kode $code");
}

// --- Bersih-bersih ---
proc_terminate($server);
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmp, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $item) {
    $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
}
@rmdir($tmp);

echo $failures === 0 ? "\nSemua tes Admin & Instalasi (Tahap 4) lolos.\n" : "\n$failures tes gagal.\n";
exit($failures === 0 ? 0 : 1);

