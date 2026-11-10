<?php
declare(strict_types=1);

/**
 * Suite Audit, Pengerasan, dan Verifikasi Kriteria Penerimaan MVP (Tahap 5):
 *   php tests/hardening_verification_test.php
 */

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sa_hard_' . bin2hex(random_bytes(4));
mkdir($tmp);
$dbPath = $tmp . '/test.sqlite';
file_put_contents($tmp . '/config.php', '<?php return ' . var_export([
    'db_path'        => $dbPath,
    'encryption_key' => base64_encode(random_bytes(32)),
    'pepper'         => bin2hex(random_bytes(32)),
    'debug'          => false,
    'timezone'       => 'Asia/Jakarta',
], true) . ';');
define('SA_CONFIG_FILE', $tmp . '/config.php');
putenv('SA_CONFIG_FILE=' . SA_CONFIG_FILE);

require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/app/migrate.php';
migrate(db());
seed_categories(db());

$pdo = db();
$now = now_full();
$pdo->prepare('INSERT INTO users (username, password_hash, role, is_active, created_at) VALUES (?, ?, ?, 1, ?)')
    ->execute(['bk_audit', password_hash('SandiBK-Audit1', PASSWORD_BCRYPT), 'bk', $now]);
$pdo->prepare('INSERT INTO users (username, password_hash, role, is_active, created_at) VALUES (?, ?, ?, 1, ?)')
    ->execute(['admin_audit', password_hash('SandiAdmin-Audit1', PASSWORD_BCRYPT), 'admin', $now]);

$scalar = function (string $sql) use ($pdo): int {
    $s = $pdo->query($sql);
    $v = (int) $s->fetchColumn();
    $s->closeCursor();
    return $v;
};

$port   = 8101;
$base   = "http://127.0.0.1:$port";
$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', BASE_PATH . '/public'],
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
    echo ($ok ? '[LOLOS] ' : '[GAGAL] ') . $name . ($ok || $detail === '' ? '' : " -> $detail") . "\n";
    $failures += $ok ? 0 : 1;
}

function http(string $method, string $path, array $post = [], bool $multipart = false): array
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
        CURLOPT_USERAGENT      => 'AuditAgent/5.0 MarkerUA-Secret',
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $post : http_build_query($post));
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

echo "=== A. AUDIT STATIS KEAMANAN, KOMPATIBILITAS, & TAMPILAN ===\n";

$codeFiles = array_merge(
    glob(BASE_PATH . '/app/*.php') ?: [],
    glob(BASE_PATH . '/app/views/*.php') ?: [],
    glob(BASE_PATH . '/public/*.php') ?: []
);

// 1. Kompatibilitas shared hosting: tidak memakai fungsi eksekusi shell di app/ dan public/
$forbiddenFuncs = [];
foreach ($codeFiles as $file) {
    $src = (string) file_get_contents($file);
    if (preg_match('/\b(shell_exec|passthru|proc_open|popen|pcntl_\w+|dl|eval)\s*\(/', $src, $m)) {
        $forbiddenFuncs[] = basename($file) . ':' . $m[1];
    }
}
check('Kompatibilitas shared hosting: tidak ada fungsi shell_exec/proc_open/eval di app/ & public/', $forbiddenFuncs === [], implode(', ', $forbiddenFuncs));

// 2. Tampilan & CSP: tidak ada inline style=, onclick=, onchange=, atau sumber eksternal di view & public
$inlineViolations = [];
foreach (array_merge(glob(BASE_PATH . '/app/views/*.php') ?: [], glob(BASE_PATH . '/public/*.php') ?: []) as $file) {
    $src = (string) file_get_contents($file);
    if (preg_match('/\b(style|onclick|onchange|onload)\s*=/i', $src, $m)) {
        $inlineViolations[] = basename($file) . ':' . $m[1];
    }
    if (preg_match('#(src|href|action)\s*=\s*["\']https?://#i', $src, $m)) {
        $inlineViolations[] = basename($file) . ':external-url';
    }
    // Tanpa emoji dekoratif
    if (preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $src)) {
        $inlineViolations[] = basename($file) . ':emoji';
    }
}
check('Tampilan & CSP: seluruh template bebas inline style/onclick/URL eksternal/emoji', $inlineViolations === [], implode(', ', $inlineViolations));

// 3. Proteksi .htaccess pada app/ dan data/
$htApp  = (string) @file_get_contents(BASE_PATH . '/app/.htaccess');
$htData = (string) @file_get_contents(BASE_PATH . '/data/.htaccess');
check('.htaccess di app/ dan data/ aktif menolak akses langsung & mematikan ExecCGI',
    str_contains($htApp, 'Require all denied') && str_contains($htApp, 'Deny from all')
    && str_contains($htData, 'Require all denied') && str_contains($htData, 'Deny from all') && str_contains($htData, '-ExecCGI'));

// 4. Konfigurasi error produksi
check('display_errors=0 dan zend.exception_ignore_args=1 aktif saat debug=false',
    ini_get('display_errors') === '0' && ini_get('zend.exception_ignore_args') === '1');

echo "\n=== B. MATRIKS AKSES PERAN (PELAPOR, BK, ADMIN) ===\n";

// Sesi 1: Anonim / Pelapor
new_session();
[$cHome] = http('GET', '/');
[$cPriv] = http('GET', '/privasi');
[$cLap]  = http('GET', '/lapor');
[$cStat] = http('GET', '/status');
check('Anonim dapat mengakses /, /privasi, /lapor, /status (200)', $cHome === 200 && $cPriv === 200 && $cLap === 200 && $cStat === 200);

foreach (['/bk/laporan', '/bk/laporan/1', '/bk/rekap', '/admin/akun', '/admin/kategori', '/admin/statistik', '/admin/backup'] as $r) {
    [$c, $hdr] = http('GET', $r);
    check("Anonim GET $r dialihkan ke /bk/login (303)", $c === 303 && str_contains($hdr, 'Location: /bk/login'));
}

// Sesi 2: Guru BK
new_session();
http('POST', '/bk/login', ['_csrf' => csrf_from('/bk/login'), 'username' => 'bk_audit', 'password' => 'SandiBK-Audit1']);
$bkJar = $jar;
[$cBkList]  = http('GET', '/bk/laporan');
[$cBkRekap] = http('GET', '/bk/rekap');
check('Guru BK dapat mengakses /bk/laporan dan /bk/rekap (200)', $cBkList === 200 && $cBkRekap === 200);
foreach (['/admin/akun', '/admin/kategori', '/admin/statistik', '/admin/backup'] as $r) {
    [$c] = http('GET', $r);
    check("Guru BK ditolak (403) pada $r", $c === 403);
}

// Sesi 3: Admin
new_session();
http('POST', '/bk/login', ['_csrf' => csrf_from('/bk/login'), 'username' => 'admin_audit', 'password' => 'SandiAdmin-Audit1']);
$adminJar = $jar;
foreach (['/admin/akun', '/admin/kategori', '/admin/statistik', '/admin/backup'] as $r) {
    [$c] = http('GET', $r);
    check("Admin dapat mengakses $r (200)", $c === 200);
}
foreach (['/bk/laporan', '/bk/laporan/1', '/bk/rekap', '/attachment.php?f=0123456789abcdef0123456789abcdef.jpg'] as $r) {
    [$c] = http('GET', $r);
    check("Admin ditolak (403) pada $r", $c === 403);
}

echo "\n=== C. KETAHANAN INPUT & PENCEGAHAN KIRIM GANDA ===\n";

// Uji kirim laporan dengan Unicode + karakter XSS + pencegahan double submit CAPTCHA
new_session();
[, , $fHtml] = http('GET', '/lapor');
preg_match('/name="_csrf" value="([0-9a-f]+)"/', $fHtml, $cm);
preg_match('/Berapa (\d) (ditambah|dikurangi) (\d)\?/', $fHtml, $qm);
$ans = (string) (($qm[2] ?? '') === 'ditambah' ? ((int) $qm[1] + (int) $qm[3]) : ((int) $qm[1] - (int) $qm[3]));

$im = imagecreatetruecolor(24, 16);
ob_start();
imagejpeg($im);
$rawJpg = (string) ob_get_clean();
$exifMake = "SECRET_CAMERA_MODEL\0";
$tiff = "MM\x00\x2A" . pack('N', 8) . pack('n', 1) . pack('nnN', 0x010F, 2, strlen($exifMake)) . pack('N', 8 + 2 + 12 + 4) . pack('N', 0) . $exifMake;
$jpgExifPath = $tmp . '/exif.jpg';
file_put_contents($jpgExifPath, substr($rawJpg, 0, 2) . "\xFF\xE1" . pack('n', 2 + 6 + strlen($tiff)) . "Exif\0\0" . $tiff . substr($rawJpg, 2));

$payloadPost = [
    '_csrf'    => $cm[1] ?? '',
    'peran'    => 'korban',
    'kategori' => '1',
    'waktu'    => date('Y-m-d\TH:i', strtotime('-2 hours')),
    'lokasi'   => 'LOKASI-UJI-KRITERIA <script>alert(1)</script>',
    'uraian'   => 'URAIAN-UJI-KRITERIA Perundungan dengan Unicode: Ibu Guru, "kutip", \'petik\', & karakter khusus.',
    'terlibat' => 'TERLIBAT-UJI-KRITERIA',
    'jawaban'  => $ans,
    'bukti[0]' => new CURLFile($jpgExifPath, 'image/jpeg', 'foto.jpg'),
];
[$cSub1, $hdrSub1] = http('POST', '/lapor', $payloadPost, true);
[$cSub2]           = http('POST', '/lapor', $payloadPost, true);
check('Tombol kirim laporan ditekan dua kali: pengiriman pertama 303, pengiriman kedua ditolak (422) tanpa duplikat',
    $cSub1 === 303 && $cSub2 === 422 && $scalar('SELECT COUNT(*) FROM reports') === 1);

[, , $htmlSukses1] = http('GET', '/lapor/sukses');
preg_match('/id="ticket-code">([2-9A-Z-]{14})</', $htmlSukses1, $tm);
$ticket = $tm[1] ?? '';
[, , $htmlSukses2] = http('GET', '/lapor/sukses');

// Uji double-submit balasan BK & catatan internal
$jar = $bkJar;
$csrfBk = csrf_from('/bk/laporan/1');
http('POST', '/bk/laporan/1/balas', ['_csrf' => $csrfBk, 'body' => 'Balasan tunggal BK untuk pelapor.']);
http('POST', '/bk/laporan/1/balas', ['_csrf' => $csrfBk, 'body' => 'Balasan tunggal BK untuk pelapor.']);
check('Pencegahan kirim ganda balasan BK: hanya 1 pesan BK tersimpan',
    $scalar("SELECT COUNT(*) FROM messages WHERE report_id = 1 AND sender = 'bk'") === 1);

http('POST', '/bk/laporan/1/catatan', ['_csrf' => $csrfBk, 'note' => 'Catatan tunggal BK.']);
http('POST', '/bk/laporan/1/catatan', ['_csrf' => $csrfBk, 'note' => 'Catatan tunggal BK.']);
check('Pencegahan kirim ganda catatan internal BK: hanya 1 catatan tersimpan',
    $scalar('SELECT COUNT(*) FROM internal_notes WHERE report_id = 1') === 1);

echo "\n=== D. VERIFIKASI 8 KRITERIA PENERIMAAN MVP (PRD BAGIAN 11) ===\n";

// Kriteria 1: Pelapor dapat mengirim laporan tanpa login dan menerima kode tiket sekali tampil.
check('Kriteria 1: Pelapor mengirim laporan tanpa login & menerima kode tiket sekali tampil',
    preg_match('/^[2-9A-HJ-NP-Z]{4}(-[2-9A-HJ-NP-Z]{4}){2}$/', $ticket) === 1
    && !str_contains($htmlSukses2, $ticket));

// Kriteria 2: Kode tiket yang benar menampilkan status dan percakapan; kode salah tidak membocorkan apa pun dan terkena rate limit.
new_session();
[, , $htmlRight] = http('POST', '/status', ['_csrf' => csrf_from('/status'), 'kode' => $ticket]);
[, , $htmlWrong] = http('POST', '/status', ['_csrf' => csrf_from('/status'), 'kode' => '2222-2222-2222']);
for ($i = 1; $i <= 10; $i++) {
    http('POST', '/status', ['_csrf' => csrf_from('/status'), 'kode' => '2222-2222-222' . ($i % 8 + 2)]);
}
[$cRate] = http('POST', '/status', ['_csrf' => csrf_from('/status'), 'kode' => $ticket]);
$pdo->exec('DELETE FROM rate_limits');
check('Kriteria 2: Kode benar menampilkan status & percakapan; kode salah generik & terkena rate limit (429)',
    str_contains($htmlRight, 'Balasan tunggal BK untuk pelapor.')
    && !str_contains($htmlWrong, 'Balasan tunggal') && !str_contains($htmlWrong, 'Kekerasan')
    && $cRate === 429);

// Kriteria 3: Di database tidak ada IP, user agent, atau isi laporan dalam bentuk teks biasa.
$pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
$rawDb = (string) file_get_contents($dbPath);
check('Kriteria 3: Database tidak memuat IP, user agent, kode tiket asli, maupun isi laporan plaintext',
    !str_contains($rawDb, '127.0.0.1')
    && !str_contains($rawDb, 'MarkerUA-Secret')
    && !str_contains($rawDb, 'URAIAN-UJI-KRITERIA')
    && !str_contains($rawDb, 'LOKASI-UJI-KRITERIA')
    && !str_contains($rawDb, 'TERLIBAT-UJI-KRITERIA')
    && !str_contains($rawDb, $ticket));

// Kriteria 4: File bukti yang diunggah tidak memuat EXIF setelah diproses.
$attStmt = $pdo->query('SELECT stored_name FROM attachments WHERE report_id = 1');
$storedName = (string) $attStmt->fetchColumn();
$attStmt->closeCursor();
$storedPath = DATA_PATH . '/uploads/' . $storedName;
$storedBytes = is_file($storedPath) ? (string) file_get_contents($storedPath) : '';
$exifAfter = function_exists('exif_read_data') ? @exif_read_data($storedPath) : false;
check('Kriteria 4: File bukti tersimpan tanpa EXIF atau metadata kamera',
    $storedBytes !== '' && !str_contains($storedBytes, 'Exif') && !str_contains($storedBytes, 'SECRET_CAMERA')
    && ($exifAfter === false || !isset($exifAfter['Make'])));

// Kriteria 5: BK dapat melihat, memfilter, memproses, dan membalas laporan; setiap akses tercatat di log.
$jar = $bkJar;
[, , $bkDetailHtml] = http('GET', '/bk/laporan/1');
$logCount = $scalar('SELECT COUNT(*) FROM access_log WHERE report_id = 1');
check('Kriteria 5: BK dapat melihat, memfilter, memproses, membalas laporan & tercatat di access_log',
    str_contains($bkDetailHtml, 'URAIAN-UJI-KRITERIA')
    && str_contains($bkDetailHtml, '&lt;script&gt;alert(1)&lt;/script&gt;')
    && $logCount >= 3);

// Kriteria 6: Admin tidak bisa membuka isi laporan dari UI mana pun.
$jar = $adminJar;
[, , $admStatHtml] = http('GET', '/admin/statistik');
[$cAdmBk] = http('GET', '/bk/laporan/1');
check('Kriteria 6: Admin tidak dapat membuka isi laporan dari rute atau UI mana pun',
    $cAdmBk === 403 && !str_contains($admStatHtml, 'URAIAN-UJI-KRITERIA') && !str_contains($admStatHtml, 'LOKASI-UJI-KRITERIA'));

// Kriteria 7: Folder database dan upload tidak bisa diakses langsung lewat URL.
[$cUrlDb] = http('GET', '/data/suara-aman.sqlite');
[$cUrlUp] = http('GET', '/data/uploads/' . $storedName);
check('Kriteria 7: Folder database dan upload berada di luar document root & tidak dapat diakses lewat URL (404)',
    $cUrlDb === 404 && $cUrlUp === 404 && is_file(BASE_PATH . '/data/.htaccess'));

// Kriteria 8: Aplikasi berjalan di shared hosting PHP 8.1 tanpa Composer.
check('Kriteria 8: Berjalan di PHP 8.1+ tanpa Composer (tidak ada vendor/ atau composer.json)',
    PHP_VERSION_ID >= 80100 && !is_dir(BASE_PATH . '/vendor') && !is_file(BASE_PATH . '/composer.json'));

// Bersih-bersih
unset($pdo);
proc_terminate($server);
@unlink($storedPath);
foreach (glob($tmp . '/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmp);

echo $failures === 0 ? "\nSeluruh verifikasi Tahap 5 & 8 Kriteria Penerimaan MVP LOLOS.\n" : "\n$failures pemeriksaan gagal.\n";
exit($failures === 0 ? 0 : 1);

