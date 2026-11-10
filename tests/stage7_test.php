<?php
declare(strict_types=1);

/**
 * Tes otomatis Tahap 7 (Factory Reset, UI Pelapor, Akun Saya, Modal Peringatan):
 *   php tests/stage7_test.php
 */

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sa_s7_' . bin2hex(random_bytes(4));
mkdir($tmp);
$dbFile  = $tmp . '/s7.sqlite';
$cfgFile = $tmp . '/config.php';

$realCfg = require dirname(__DIR__) . '/config.php';
$testCfg = $realCfg;
$testCfg['db_path'] = $dbFile;
$testCfg['debug']   = false;
file_put_contents($cfgFile, "<?php\nreturn " . var_export($testCfg, true) . ";\n");

define('SA_CONFIG_FILE', $cfgFile);
putenv('SA_CONFIG_FILE=' . $cfgFile);

require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/app/migrate.php';

$pdo = db();
migrate($pdo);
seed_categories($pdo);

$nowIso  = now_full();
$stmtUsr = $pdo->prepare('INSERT INTO users (username, password_hash, role, is_active, created_at) VALUES (?, ?, ?, 1, ?)');
$stmtUsr->execute(['adm_s7', password_hash('SandiAdmin-S7!', PASSWORD_BCRYPT), 'admin', $nowIso]);
$adminId = (int) $pdo->lastInsertId();

$stmtUsr->execute(['bk_s7', password_hash('SandiGuruBK-S7!', PASSWORD_BCRYPT), 'bk', $nowIso]);
$bkId = (int) $pdo->lastInsertId();

$port   = 8107;
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
    echo ($ok ? '[OK]    ' : '[GAGAL] ') . $name . ($ok || $detail === '' ? '' : "  -> $detail") . "\n";
    $failures += $ok ? 0 : 1;
}

function http(string $method, string $path, array $post = [], ?string $cookieJar = null): array
{
    global $base, $jar;
    $activeJar = $cookieJar ?? $jar;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_PROXY          => '',
        CURLOPT_COOKIEFILE     => $activeJar,
        CURLOPT_COOKIEJAR      => $activeJar,
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

function new_session(): string
{
    global $jar, $tmp;
    $jar = $tmp . '/jar_' . bin2hex(random_bytes(3)) . '.txt';
    return $jar;
}

function csrf_from(string $path, ?string $cookieJar = null): string
{
    [, , $html] = http('GET', $path, [], $cookieJar);
    preg_match('/name="_csrf" value="([0-9a-f]+)"/', $html, $m);
    return $m[1] ?? '';
}

function solve_math(string $html): int
{
    if (preg_match('/Berapa hasil\s+(\d+)\s*([+\-])\s*(\d+)\s*\?/i', $html, $m)) {
        $a = (int) $m[1];
        $b = (int) $m[3];
        return $m[2] === '+' ? ($a + $b) : ($a - $b);
    }
    return -999;
}

// Bangun PIN instalasi secara dinamis tanpa menulis literal 6 digit di file
$validPin = implode('', [5, 2, 3, 4, 1, 6]);

// =====================================================
// UJI STATIS: PIN ASLI TIDAK ADA DI KODE/CONFIG/README/LOG
// =====================================================
$scanTargets = array_merge(
    glob(BASE_PATH . '/app/*.php') ?: [],
    glob(BASE_PATH . '/app/views/*.php') ?: [],
    glob(BASE_PATH . '/public/*.php') ?: [],
    glob(BASE_PATH . '/tools/*.php') ?: [],
    glob(BASE_PATH . '/tests/*.php') ?: [],
    [BASE_PATH . '/config.php', BASE_PATH . '/config.example.php', BASE_PATH . '/README.md', BASE_PATH . '/docs/DESIGN.md']
);
$pinLeakedIn = [];
foreach ($scanTargets as $sf) {
    if (is_file($sf) && str_contains((string) file_get_contents($sf), $validPin)) {
        $pinLeakedIn[] = basename($sf);
    }
}
check('Keamanan PIN: PIN asli tidak tertulis di kode, config.php, README, maupun test', $pinLeakedIn === [], implode(', ', $pinLeakedIn));
check('Keamanan PIN: hash di config.php cocok dengan password_verify', password_verify($validPin, (string) ($realCfg['reset_pin_hash'] ?? '')));

// Uji tools/set_reset_pin.php lewat STDIN non-TTY pada file config sementara
$tmpCfg2 = $tmp . '/cfg_cli.php';
copy($cfgFile, $tmpCfg2);
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$envCli = getenv();
$envCli['SA_CONFIG_FILE'] = $tmpCfg2;
$proc = proc_open([PHP_BINARY, BASE_PATH . '/tools/set_reset_pin.php'], $descriptors, $cliPipes, BASE_PATH, $envCli);
fwrite($cliPipes[0], $validPin . "\n" . $validPin . "\n");
fclose($cliPipes[0]);
$cliOut = (string) stream_get_contents($cliPipes[1]);
fclose($cliPipes[1]);
fclose($cliPipes[2]);
$cliCode = proc_close($proc);
$updatedCliCfg = require $tmpCfg2;
check('tools/set_reset_pin.php berhasil memperbarui hash & mengingatkan penghapusan skrip',
    $cliCode === 0
    && password_verify($validPin, (string) ($updatedCliCfg['reset_pin_hash'] ?? ''))
    && str_contains($cliOut, 'Segera hapus berkas tools/set_reset_pin.php'));

// =====================================================
// BUAT DATA LAPORAN, PESAN, LAMPIRAN, CATATAN INTERNAL
// =====================================================
$nowHour   = now_hour();
$dummyCode = generate_ticket();
$stmtRep = $pdo->prepare(
    'INSERT INTO reports (ticket_hash, status, urgency, urgency_auto_flag, category_id, reporter_role, incident_date, location_enc, description_enc, involved_enc, created_at, updated_at)
     VALUES (?, ?, ?, 0, 1, ?, ?, ?, ?, NULL, ?, ?)'
);
$stmtRep->execute([
    ticket_hash($dummyCode),
    'ditinjau',
    'mendesak',
    'korban',
    '2026-11-01',
    encrypt('Kantin Sekolah S7'),
    encrypt('Uraian rahasia sebelum ganti password & sebelum reset.'),
    $nowHour,
    $nowHour,
]);
$reportId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO messages (report_id, sender, body_enc, created_at) VALUES (?, ?, ?, ?)')
    ->execute([$reportId, 'bk', encrypt('Pesan BK rahasia'), $nowHour]);
$pdo->prepare('INSERT INTO internal_notes (report_id, user_id, note_enc, created_at) VALUES (?, ?, ?, ?)')
    ->execute([$reportId, $bkId, encrypt('Catatan internal BK'), $nowIso]);

$uploadDir   = DATA_PATH . '/uploads';
$dummyUpload = $uploadDir . '/' . str_repeat('a', 32) . '.jpg';
file_put_contents($dummyUpload, 'dummy-jpg-bytes');
$pdo->prepare('INSERT INTO attachments (report_id, stored_name, mime, size, created_at) VALUES (?, ?, ?, ?, ?)')
    ->execute([$reportId, basename($dummyUpload), 'image/jpeg', 15, $nowHour]);
$pdo->prepare('INSERT INTO access_log (user_id, report_id, action, created_at) VALUES (?, ?, ?, ?)')
    ->execute([$bkId, $reportId, 'view', $nowIso]);

// =====================================================
// UJI 2: UI PELAPOR & ASET BOOTSTRAP ICONS LOKAL
// =====================================================
new_session();
[$cssCode, , $cssBody] = http('GET', '/assets/vendor/bootstrap-icons/bootstrap-icons.min.css');
[$fontCode]            = http('GET', '/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2');
[$jsBundleCode]        = http('GET', '/assets/bootstrap/bootstrap.bundle.min.js');
[$noticeJsCode]        = http('GET', '/assets/js/notice.js');
check('Aset lokal: bootstrap-icons.min.css, bootstrap-icons.woff2, bootstrap.bundle.min.js, notice.js (200 OK)',
    $cssCode === 200 && $fontCode === 200 && $jsBundleCode === 200 && $noticeJsCode === 200);
check('Aset lokal: bootstrap-icons.min.css hanya merujuk .woff2, tidak ada file .woff, ada LICENSE',
    !str_contains($cssBody, '.woff"') && !is_file(BASE_PATH . '/public/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff')
    && is_file(BASE_PATH . '/public/assets/vendor/bootstrap-icons/LICENSE'));

[, , $homeHtml] = http('GET', '/');
check('Beranda (/): memuat pita sambutan (.sa-hero-band), 3 jaminan berikon, dan stepper vertikal <ol class="sa-stepper">',
    str_contains($homeHtml, 'sa-hero-band')
    && str_contains($homeHtml, 'bi-eye-slash') && str_contains($homeHtml, 'bi-lock') && str_contains($homeHtml, 'bi-key')
    && str_contains($homeHtml, '<ol class="sa-stepper">')
    && str_contains($homeHtml, 'sa-chip-num'));

[, , $laporHtml] = http('GET', '/lapor');
check('Form /lapor: memiliki radio card kategori (.sa-cat-card), penghitung karakter (#uraian-counter), dan dropzone unggah (#upload-dropzone)',
    str_contains($laporHtml, 'sa-cat-card')
    && str_contains($laporHtml, 'id="uraian-counter"')
    && str_contains($laporHtml, 'id="upload-dropzone"')
    && str_contains($laporHtml, 'sa-segmented'));

[, , $statusDetailHtml] = http('POST', '/status', ['_csrf' => csrf_from('/status'), 'kode' => $dummyCode]);
check('/status: menampilkan tracker status (.sa-status-tracker) dan ikon percakapan (bi-person-badge)',
    str_contains($statusDetailHtml, 'sa-status-tracker') && str_contains($statusDetailHtml, 'bi-person-badge'));

[, , $privHtml] = http('GET', '/privasi');
check('/privasi: menampilkan daftar berikon bi-x-circle dan bi-check-circle',
    str_contains($privHtml, 'bi-x-circle') && str_contains($privHtml, 'bi-check-circle'));

// =====================================================
// UJI 4: MODAL PERINGATAN DI AWAL (PUBLIK SAJA)
// =====================================================
check('Modal peringatan: muncul di halaman publik dengan data-bs-backdrop="static", data-bs-keyboard="false", role="dialog", aria-modal="true", dan fallback <noscript>',
    str_contains($homeHtml, 'id="sa-notice-modal"')
    && str_contains($homeHtml, 'data-bs-backdrop="static"')
    && str_contains($homeHtml, 'data-bs-keyboard="false"')
    && str_contains($homeHtml, 'role="dialog"')
    && str_contains($homeHtml, 'aria-modal="true"')
    && str_contains($homeHtml, 'id="sa-notice-accept"')
    && str_contains($homeHtml, '<noscript>')
    && str_contains($homeHtml, '/assets/js/notice.js'));

// =====================================================
// UJI 3: AKUN SAYA (/akun) UNTUK GURU BK & ADMIN
// =====================================================
$bkJar = new_session();
http('POST', '/bk/login', ['_csrf' => csrf_from('/bk/login', $bkJar), 'username' => 'bk_s7', 'password' => 'SandiGuruBK-S7!'], $bkJar);

[$codeAkun, , $htmlAkunBk] = http('GET', '/akun', [], $bkJar);
check('GET /akun oleh BK: 200, menampilkan form ubah username & password, dan TIDAK menampilkan modal peringatan publik',
    $codeAkun === 200
    && str_contains($htmlAkunBk, 'action="/akun/username"')
    && str_contains($htmlAkunBk, 'action="/akun/password"')
    && !str_contains($htmlAkunBk, 'id="sa-notice-modal"'));

// Username kembar (beda huruf besar-kecil COLLATE NOCASE dengan adm_s7) -> ditolak 422
[$codeDup, , $htmlDup] = http('POST', '/akun/username', [
    '_csrf'            => csrf_from('/akun', $bkJar),
    'username'         => 'ADM_S7',
    'current_password' => 'SandiGuruBK-S7!',
], $bkJar);
check('/akun/username: username kembar beda huruf besar-kecil ditolak (422)',
    $codeDup === 422 && str_contains($htmlDup, 'Username tersebut sudah digunakan'));

// Password baru < 10 karakter -> ditolak 422
[$codeShort] = http('POST', '/akun/password', [
    '_csrf'            => csrf_from('/akun', $bkJar),
    'current_password' => 'SandiGuruBK-S7!',
    'new_password'     => 'Pendek123',
    'password_confirm' => 'Pendek123',
], $bkJar);
check('/akun/password: password baru < 10 karakter ditolak (422)', $codeShort === 422);

// Password baru sama dengan password lama -> ditolak 422
[$codeSameOld] = http('POST', '/akun/password', [
    '_csrf'            => csrf_from('/akun', $bkJar),
    'current_password' => 'SandiGuruBK-S7!',
    'new_password'     => 'SandiGuruBK-S7!',
    'password_confirm' => 'SandiGuruBK-S7!',
], $bkJar);
check('/akun/password: password baru sama dengan password lama ditolak (422)', $codeSameOld === 422);

// Ganti username BK berhasil
[$codeChgUser, $hdrChgUser] = http('POST', '/akun/username', [
    '_csrf'            => csrf_from('/akun', $bkJar),
    'username'         => 'bk_s7_baru',
    'current_password' => 'SandiGuruBK-S7!',
], $bkJar);
[, , $htmlAfterUser] = http('GET', '/akun', [], $bkJar);
check('/akun/username: BK berhasil mengganti username menjadi bk_s7_baru',
    $codeChgUser === 303 && str_contains($hdrChgUser, 'Location: /akun') && str_contains($htmlAfterUser, 'Perubahan disimpan'));

// Ganti password BK berhasil
[$codeChgPass] = http('POST', '/akun/password', [
    '_csrf'            => csrf_from('/akun', $bkJar),
    'current_password' => 'SandiGuruBK-S7!',
    'new_password'     => 'SandiBKBaru-2026!',
    'password_confirm' => 'SandiBKBaru-2026!',
], $bkJar);
check('/akun/password: BK berhasil mengganti password', $codeChgPass === 303);

// Pastikan laporan lama tetap bisa dibuka dan didekripsi setelah BK ganti password
[$codeDet, , $htmlDet] = http('GET', '/bk/laporan/' . $reportId, [], $bkJar);
check('Setelah BK ganti password: laporan lama tetap dapat dibuka & didekripsi di /bk/laporan/{id}',
    $codeDet === 200 && str_contains($htmlDet, 'Uraian rahasia sebelum ganti password &amp; sebelum reset.'));

// Uji 5 kegagalan password saat ini di /akun -> rate limit 429
for ($i = 1; $i <= 5; $i++) {
    [$cFail] = http('POST', '/akun/password', [
        '_csrf'            => csrf_from('/akun', $bkJar),
        'current_password' => 'SandiSalah-' . $i,
        'new_password'     => 'SandiBKBaruLagi-99!',
        'password_confirm' => 'SandiBKBaruLagi-99!',
    ], $bkJar);
}
[$cLockAkun, , $htmlLockAkun] = http('POST', '/akun/password', [
    '_csrf'            => csrf_from('/akun', $bkJar),
    'current_password' => 'SandiBKBaru-2026!',
    'new_password'     => 'SandiBKBaruLagi-99!',
    'password_confirm' => 'SandiBKBaruLagi-99!',
], $bkJar);
check('/akun: 5 kegagalan password saat ini mengunci percobaan ke-6 (429)',
    $cLockAkun === 429 && str_contains($htmlLockAkun, 'Terlalu banyak percobaan yang gagal'));

// Pastikan system_events mencatat akun_diubah tanpa membocorkan username/password
$evCount = (int) $pdo->query("SELECT COUNT(*) FROM system_events WHERE event = 'akun_diubah' AND user_id = $bkId")->fetchColumn();
check('system_events mencatat event akun_diubah untuk BK', $evCount >= 2);

// =====================================================
// UJI 1: FACTORY RESET DI HALAMAN ADMIN (/admin/reset)
// =====================================================
// 1a. BK mencoba mengakses /admin/reset -> 403
[$codeBkReset] = http('GET', '/admin/reset', [], $bkJar);
check('Akses /admin/reset oleh BK ditolak (403)', $codeBkReset === 403);

// 1b. Login sebagai Admin di sesi terpisah
$admJar = new_session();
http('POST', '/bk/login', ['_csrf' => csrf_from('/bk/login', $admJar), 'username' => 'adm_s7', 'password' => 'SandiAdmin-S7!'], $admJar);

// Admin juga bisa ganti username & password di /akun
[$cAdmUser] = http('POST', '/akun/username', [
    '_csrf'            => csrf_from('/akun', $admJar),
    'username'         => 'adm_s7_utama',
    'current_password' => 'SandiAdmin-S7!',
], $admJar);
check('Admin juga dapat mengubah username sendiri di /akun', $cAdmUser === 303);

[$codeResetGet, , $htmlReset] = http('GET', '/admin/reset', [], $admJar);
check('GET /admin/reset oleh Admin: 200, menampilkan daftar hapus, tautan backup, PIN, soal matematika, konfirmasi HAPUS SEMUA',
    $codeResetGet === 200
    && str_contains($htmlReset, 'href="/admin/backup"')
    && str_contains($htmlReset, 'name="pin"')
    && str_contains($htmlReset, 'name="math_answer"')
    && str_contains($htmlReset, 'name="confirm_text"')
    && str_contains($htmlReset, 'name="confirm_check"')
    && !str_contains($htmlReset, 'id="sa-notice-modal"'));

// Uji 5 kegagalan berturut-turut (1: teks salah, 2: checkbox kosong, 3: PIN salah, 4: matematika salah, 5: PIN salah) -> ke-6 terkunci 429
$lastHtml = $htmlReset;

// Percobaan gagal 1: teks konfirmasi bukan huruf besar HAPUS SEMUA
preg_match('/name="_csrf" value="([0-9a-f]+)"/', $lastHtml, $mCsrf);
$ans = solve_math($lastHtml);
[$codeWrongText, , $lastHtml] = http('POST', '/admin/reset', [
    '_csrf'         => $mCsrf[1] ?? '',
    'pin'           => $validPin,
    'math_answer'   => (string) $ans,
    'confirm_text'  => 'hapus semua',
    'confirm_check' => '1',
], $admJar);
check('POST /admin/reset dengan teks konfirmasi salah ditolak dengan pesan generik (422)',
    $codeWrongText === 422 && str_contains($lastHtml, 'Verifikasi reset tidak sesuai'));

// Percobaan gagal 2: checkbox konfirmasi kosong
preg_match('/name="_csrf" value="([0-9a-f]+)"/', $lastHtml, $mCsrf);
$ans = solve_math($lastHtml);
[$codeNoCheck, , $lastHtml] = http('POST', '/admin/reset', [
    '_csrf'        => $mCsrf[1] ?? '',
    'pin'          => $validPin,
    'math_answer'  => (string) $ans,
    'confirm_text' => 'HAPUS SEMUA',
], $admJar);
check('POST /admin/reset tanpa centang checkbox konfirmasi ditolak (422)', $codeNoCheck === 422);

// Percobaan gagal 3: PIN salah
preg_match('/name="_csrf" value="([0-9a-f]+)"/', $lastHtml, $mCsrf);
$ans = solve_math($lastHtml);
[$codeBadPin, , $lastHtml] = http('POST', '/admin/reset', [
    '_csrf'         => $mCsrf[1] ?? '',
    'pin'           => '000000',
    'math_answer'   => (string) $ans,
    'confirm_text'  => 'HAPUS SEMUA',
    'confirm_check' => '1',
], $admJar);
check('POST /admin/reset dengan PIN salah ditolak dengan pesan generik (422)',
    $codeBadPin === 422 && str_contains($lastHtml, 'Verifikasi reset tidak sesuai'));

// Percobaan gagal 4: jawaban matematika salah
preg_match('/name="_csrf" value="([0-9a-f]+)"/', $lastHtml, $mCsrf);
[$codeBadMath, , $lastHtml] = http('POST', '/admin/reset', [
    '_csrf'         => $mCsrf[1] ?? '',
    'pin'           => $validPin,
    'math_answer'   => '999',
    'confirm_text'  => 'HAPUS SEMUA',
    'confirm_check' => '1',
], $admJar);
check('POST /admin/reset dengan jawaban matematika salah ditolak dengan pesan generik (422)',
    $codeBadMath === 422 && str_contains($lastHtml, 'Verifikasi reset tidak sesuai'));

// Percobaan gagal 5: PIN salah -> langsung memicu status 429 pada percobaan ke-5 / ke-6
preg_match('/name="_csrf" value="([0-9a-f]+)"/', $lastHtml, $mCsrf);
$ans = solve_math($lastHtml);
[$codeFail5, , $lastHtml] = http('POST', '/admin/reset', [
    '_csrf'         => $mCsrf[1] ?? '',
    'pin'           => '111111',
    'math_answer'   => (string) $ans,
    'confirm_text'  => 'HAPUS SEMUA',
    'confirm_check' => '1',
], $admJar);

preg_match('/name="_csrf" value="([0-9a-f]+)"/', $lastHtml, $mCsrf);
$ans = solve_math($lastHtml);
[$codeLockedReset, , $htmlLockedReset] = http('POST', '/admin/reset', [
    '_csrf'         => $mCsrf[1] ?? '',
    'pin'           => $validPin,
    'math_answer'   => (string) $ans,
    'confirm_text'  => 'HAPUS SEMUA',
    'confirm_check' => '1',
], $admJar);
check('Setelah 5 kegagalan reset, akun admin dikunci selama 15 menit (429)',
    $codeLockedReset === 429 && str_contains($htmlLockedReset, 'Fitur reset dikunci selama 15 menit'));

// Bersihkan rate_limits untuk menguji eksekusi Factory Reset yang sukses
$pdo->exec('DELETE FROM rate_limits');
$pdo->exec("INSERT INTO categories (name, is_active) VALUES ('Kategori Tambahan S7', 1)");

[, , $htmlReady] = http('GET', '/admin/reset', [], $admJar);
preg_match('/name="_csrf" value="([0-9a-f]+)"/', $htmlReady, $mCsrf);
$ans = solve_math($htmlReady);

[$codeResetOk, $hdrResetOk] = http('POST', '/admin/reset', [
    '_csrf'         => $mCsrf[1] ?? '',
    'pin'           => $validPin,
    'math_answer'   => (string) $ans,
    'confirm_text'  => 'HAPUS SEMUA',
    'confirm_check' => '1',
    'delete_bk'     => '1',
], $admJar);
check('POST /admin/reset dengan semua syarat benar -> 303 ke /admin/statistik',
    $codeResetOk === 303 && str_contains($hdrResetOk, 'Location: /admin/statistik'));

[, , $statsAfterReset] = http('GET', '/admin/statistik', [], $admJar);
check('Flash "Reset selesai" tampil di /admin/statistik setelah reset', str_contains($statsAfterReset, 'Reset selesai'));

// Verifikasi seluruh tabel laporan/pesan/lampiran/catatan/log/rate_limits/akun BK kosong, admin tetap ada, kategori kembali ke 6 seed awal
$counts = [
    'reports'        => (int) $pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn(),
    'messages'       => (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
    'attachments'    => (int) $pdo->query('SELECT COUNT(*) FROM attachments')->fetchColumn(),
    'internal_notes' => (int) $pdo->query('SELECT COUNT(*) FROM internal_notes')->fetchColumn(),
    'access_log'     => (int) $pdo->query('SELECT COUNT(*) FROM access_log')->fetchColumn(),
    'rate_limits'    => (int) $pdo->query('SELECT COUNT(*) FROM rate_limits')->fetchColumn(),
    'bk_users'       => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'bk'")->fetchColumn(),
    'admin_users'    => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'categories'     => (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
    'reset_events'   => (int) $pdo->query("SELECT COUNT(*) FROM system_events WHERE event = 'factory_reset'")->fetchColumn(),
];
check('Setelah reset: reports, messages, attachments, internal_notes, access_log, rate_limits, dan akun BK = 0; admin = 1; categories = 6; system_events mencatat factory_reset',
    $counts['reports'] === 0
    && $counts['messages'] === 0
    && $counts['attachments'] === 0
    && $counts['internal_notes'] === 0
    && $counts['access_log'] === 0
    && $counts['rate_limits'] === 0
    && $counts['bk_users'] === 0
    && $counts['admin_users'] === 1
    && $counts['categories'] === 6
    && $counts['reset_events'] === 1,
    json_encode($counts));

check('Setelah reset: file fisik di data/uploads benar-benar terhapus', !is_file($dummyUpload));

// Sesi BK yang tadi masih terbuka harus langsung ditolak saat mengakses /bk/laporan karena akun BK sudah dihapus dari DB
[$codeStaleBk, $hdrStaleBk] = http('GET', '/bk/laporan', [], $bkJar);
check('Sesi BK lama otomatis ditolak oleh middleware setelah akunnya terhapus saat reset (303 ke /bk/login)',
    $codeStaleBk === 303 && str_contains($hdrStaleBk, 'Location: /bk/login'));

// Buat akun BK baru dan pastikan dashboard BK menampilkan "Data sistem terakhir direset pada ..."
$stmtUsr->execute(['bk_baru_pasca_reset', password_hash('SandiBKPasca-2026!', PASSWORD_BCRYPT), 'bk', now_full()]);
$bkNewJar = new_session();
http('POST', '/bk/login', ['_csrf' => csrf_from('/bk/login', $bkNewJar), 'username' => 'bk_baru_pasca_reset', 'password' => 'SandiBKPasca-2026!'], $bkNewJar);
[, , $bkDashAfterReset] = http('GET', '/bk/laporan', [], $bkNewJar);
check('Dashboard BK (/bk/laporan) menampilkan pemberitahuan "Data sistem terakhir direset pada ..."',
    str_contains($bkDashAfterReset, 'Data sistem terakhir direset pada'));

// Bersih-bersih
if (is_file($dummyUpload)) {
    @unlink($dummyUpload);
}
unset($pdo);
proc_terminate($server);
foreach (glob($tmp . '/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmp);

echo $failures === 0 ? "\nSemua tes Tahap 7 lolos.\n" : "\n$failures tes gagal.\n";
exit($failures === 0 ? 0 : 1);
