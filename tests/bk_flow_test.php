<?php
declare(strict_types=1);

/**
 * Tes alur Guru BK end-to-end (Tahap 3): php tests/bk_flow_test.php
 * Menguji login, rate limit login, middleware peran, daftar + filter, detail + dekripsi,
 * balasan BK ke pelapor, ubah status & urgensi, catatan internal terenkripsi,
 * access_log, proteksi gambar bukti, hapus permanen 2 langkah, dan rekap bulanan.
 */

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sa_bk_' . bin2hex(random_bytes(4));
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
    ->execute(['guru_bk', password_hash('SandiBK-Aman123', PASSWORD_BCRYPT), 'bk', $now]);
$pdo->prepare('INSERT INTO users (username, password_hash, role, is_active, created_at) VALUES (?, ?, ?, 1, ?)')
    ->execute(['admin_uji', password_hash('SandiAdmin-123', PASSWORD_BCRYPT), 'admin', $now]);

// Buat 2 laporan uji + 1 lampiran fisik
ensure_dir(DATA_PATH . '/uploads');
$attName = bin2hex(random_bytes(16)) . '.jpg';
$attPath = DATA_PATH . '/uploads/' . $attName;
$im = imagecreatetruecolor(20, 20);
imagejpeg($im, $attPath, 85);

$ticket1 = generate_ticket();
$id1 = report_create([
    'kategori' => '1',
    'peran'    => 'korban',
    'incident' => '2026-03-10 09:30',
    'lokasi'   => 'LOKASI-RAHASIA-BK1',
    'uraian'   => 'URAIAN-RAHASIA-BK1 dipukul di belakang kelas.',
    'terlibat' => 'TERLIBAT-RAHASIA-BK1',
], [['stored_name' => $attName, 'mime' => 'image/jpeg', 'size' => (int) filesize($attPath)]], $ticket1);

$ticket2 = generate_ticket();
$id2 = report_create([
    'kategori' => '2',
    'peran'    => 'saksi',
    'incident' => '2026-03-11 10:00',
    'lokasi'   => 'Kantin sekolah',
    'uraian'   => 'Dia mengancam mau bunuh diri karena terus diejek.',
    'terlibat' => '',
], [], $ticket2);

// Tutup koneksi PDO di proses induk sementara server berjalan agar tidak menahan lock
$ref = new ReflectionFunction('db');
$pdo = db_connect($dbPath);

$port   = 8099;
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

// --- 1. Proteksi sebelum login ---
new_session();
foreach (['/bk/laporan', "/bk/laporan/$id1", '/bk/rekap', '/admin/statistik'] as $protected) {
    [$code, $headers] = http('GET', $protected);
    check("tanpa login GET $protected -> 303 ke /bk/login", $code === 303 && str_contains($headers, 'Location: /bk/login'), "kode $code");
}
[$code, $headers] = http('GET', '/attachment.php?f=' . $attName);
check('tanpa login GET /attachment.php -> dialihkan ke /bk/login (bukan 200)', $code === 303 && str_contains($headers, 'Location: /bk/login'), "kode $code");

// --- 2. Login salah dan rate limit (5 gagal) ---
new_session();
[$code, , $htmlUserWrong] = http('POST', '/bk/login', ['_csrf' => csrf_from('/bk/login'), 'username' => 'tidak_ada', 'password' => 'salah']);
[$code2, , $htmlPassWrong] = http('POST', '/bk/login', ['_csrf' => csrf_from('/bk/login'), 'username' => 'guru_bk', 'password' => 'salah']);
preg_match('/role="alert">(.*?)<\/div>/s', $htmlUserWrong, $m1);
preg_match('/role="alert">(.*?)<\/div>/s', $htmlPassWrong, $m2);
check('login gagal: pesan generik identik untuk user tidak ada vs sandi salah',
    $code === 200 && $code2 === 200 && ($m1[1] ?? '') !== '' && ($m1[1] ?? '') === ($m2[1] ?? ''));

for ($i = 3; $i <= 5; $i++) {
    http('POST', '/bk/login', ['_csrf' => csrf_from('/bk/login'), 'username' => 'guru_bk', 'password' => 'salah']);
}
[$code, , $htmlBlocked] = http('POST', '/bk/login', ['_csrf' => csrf_from('/bk/login'), 'username' => 'guru_bk', 'password' => 'SandiBK-Aman123']);
check('percobaan login ke-6 diblokir 429 walau sandi benar', $code === 429 && str_contains($htmlBlocked, 'Terlalu banyak percobaan'));

// Bersihkan rate_limits untuk lanjut menguji login sukses
$pdo->exec('DELETE FROM rate_limits');

// --- 3. Login Admin & pemisahan peran (Admin ditolak 403 di /bk/*) ---
new_session();
[$code, $headers] = http('POST', '/bk/login', ['_csrf' => csrf_from('/bk/login'), 'username' => 'admin_uji', 'password' => 'SandiAdmin-123']);
check('login admin sukses -> 303 ke /admin/statistik', $code === 303 && str_contains($headers, 'Location: /admin/statistik') && str_contains($headers, 'Set-Cookie: sa_sid='), "kode $code");
foreach (['/bk/laporan', "/bk/laporan/$id1", '/bk/rekap', '/attachment.php?f=' . $attName] as $bkRoute) {
    [$code] = http('GET', $bkRoute);
    check("admin membuka $bkRoute -> 403", $code === 403, "kode $code");
}

// --- 4. Login BK sukses & pemisahan peran (BK ditolak 403 di /admin/*) ---
new_session();
[$code, $headers] = http('POST', '/bk/login', ['_csrf' => csrf_from('/bk/login'), 'username' => 'guru_bk', 'password' => 'SandiBK-Aman123']);
check('login BK sukses -> 303 ke /bk/laporan + regenerasi session', $code === 303 && str_contains($headers, 'Location: /bk/laporan') && str_contains($headers, 'Set-Cookie: sa_sid='));
foreach (['/admin/akun', '/admin/kategori', '/admin/statistik', '/admin/backup'] as $admRoute) {
    [$code] = http('GET', $admRoute);
    check("BK membuka $admRoute -> 403", $code === 403, "kode $code");
}

// --- 5. Daftar laporan & filter ---
[$code, , $htmlList] = http('GET', '/bk/laporan');
check('GET /bk/laporan: 200 dan menampilkan kedua laporan', $code === 200 && str_contains($htmlList, "#$id1") && str_contains($htmlList, "#$id2"));
$posUrgent = strpos($htmlList, "#$id2");
$posNormal = strpos($htmlList, "#$id1");
check('urut default: laporan Mendesak (#2) tampil sebelum Ringan (#1)', $posUrgent !== false && $posNormal !== false && $posUrgent < $posNormal);
check('baris Mendesak diberi class sa-row-urgent', str_contains($htmlList, 'sa-row-urgent'));

[, , $htmlFilterUrg] = http('GET', '/bk/laporan?urgency=mendesak');
check('filter urgency=mendesak hanya menampilkan laporan mendesak', str_contains($htmlFilterUrg, "#$id2") && !str_contains($htmlFilterUrg, "#$id1"));
[, , $htmlFilterCat] = http('GET', '/bk/laporan?category=1');
check('filter category=1 hanya menampilkan kategori 1', str_contains($htmlFilterCat, "#$id1") && !str_contains($htmlFilterCat, "#$id2"));

// --- 6. Detail laporan, dekripsi, access_log, dan gambar bukti ---
[$code, , $htmlDetail] = http('GET', "/bk/laporan/$id1");
check('GET /bk/laporan/{id}: 200 dan mendekripsi lokasi, uraian, pihak terlibat',
    $code === 200 && str_contains($htmlDetail, 'LOKASI-RAHASIA-BK1') && str_contains($htmlDetail, 'URAIAN-RAHASIA-BK1') && str_contains($htmlDetail, 'TERLIBAT-RAHASIA-BK1'));

$logStmt = $pdo->prepare("SELECT * FROM access_log WHERE report_id = ? AND action = 'view'");
$logStmt->execute([$id1]);
$logs = $logStmt->fetchAll();
$logStmt->closeCursor();
check('pembukaan detail tercatat di access_log tanpa isi laporan', count($logs) === 1 && !str_contains(json_encode($logs), 'RAHASIA'));

[$code, $headers, $imgBytes] = http('GET', '/attachment.php?f=' . $attName);
check('gambar bukti dapat dibuka oleh BK yang login', $code === 200 && str_contains($headers, 'Content-Type: image/jpeg') && strlen($imgBytes) > 50);

// --- 7. Balasan BK, ubah status, ubah urgensi, catatan internal ---
$csrf = csrf_from("/bk/laporan/$id1");
[$code] = http('POST', "/bk/laporan/$id1/balas", ['_csrf' => $csrf, 'body' => 'Kami sedang menindaklanjuti laporan kamu.']);
check('BK mengirim balasan -> 303', $code === 303);

// Cek balasan muncul di /status sisi pelapor
$bkJar = $jar;
new_session();
[$code, , $htmlStatus] = http('POST', '/status', ['_csrf' => csrf_from('/status'), 'kode' => $ticket1]);
check('balasan BK tampil di /status pelapor', $code === 200 && str_contains($htmlStatus, 'Kami sedang menindaklanjuti laporan kamu.') && str_contains($htmlStatus, 'Ditinjau'));
$jar = $bkJar;

// Ubah urgensi & tambah catatan internal
[$code] = http('POST', "/bk/laporan/$id1/urgensi", ['_csrf' => csrf_from("/bk/laporan/$id1"), 'urgency' => 'sedang']);
check('ubah urgensi ke sedang -> 303', $code === 303);

[$code] = http('POST', "/bk/laporan/$id1/catatan", ['_csrf' => csrf_from("/bk/laporan/$id1"), 'note' => 'CATATAN-INTERNAL-RAHASIA sudah panggil wali kelas']);
check('tambah catatan internal -> 303', $code === 303);
[, , $htmlDetailAfter] = http('GET', "/bk/laporan/$id1");
check('catatan internal tampil di halaman detail BK', str_contains($htmlDetailAfter, 'CATATAN-INTERNAL-RAHASIA'));

$noteStmt = $pdo->query("SELECT note_enc FROM internal_notes WHERE report_id = $id1");
$noteRow  = $noteStmt->fetch();
$noteStmt->closeCursor();
check('catatan internal terenkripsi di database', $noteRow && !str_contains((string) $noteRow['note_enc'], 'CATATAN-INTERNAL') && decrypt((string) $noteRow['note_enc']) === 'CATATAN-INTERNAL-RAHASIA sudah panggil wali kelas');

// --- 8. Rekap bulanan ---
[$code, , $htmlRekap] = http('GET', '/bk/rekap?tahun=' . date('Y'));
check('GET /bk/rekap: 200, memuat tabel kategori, status, dan waktu respons',
    $code === 200 && str_contains($htmlRekap, 'Jumlah laporan per kategori') && str_contains($htmlRekap, 'Jumlah laporan per status') && str_contains($htmlRekap, 'Rata-rata waktu respons'));

// --- 9. Hapus permanen (hanya status Selesai, konfirmasi 2 langkah) ---
[$code, $headers] = http('GET', "/bk/laporan/$id1/hapus");
check('hapus ditolak bila status belum Selesai', $code === 303 && str_contains($headers, "Location: /bk/laporan/$id1"));

http('POST', "/bk/laporan/$id1/status", ['_csrf' => csrf_from("/bk/laporan/$id1"), 'status' => 'selesai']);
[$code, , $htmlConfirm] = http('GET', "/bk/laporan/$id1/hapus");
check('langkah 1 hapus (status Selesai): halaman konfirmasi 200', $code === 200 && str_contains($htmlConfirm, 'hapus-permanen'));

[$code] = http('POST', "/bk/laporan/$id1/hapus", ['_csrf' => csrf_from("/bk/laporan/$id1/hapus"), 'confirm' => 'salah']);
check('langkah 2 hapus dengan konfirmasi salah -> 422 dan laporan belum terhapus', $code === 422 && is_file($attPath));

[$code, $headers] = http('POST', "/bk/laporan/$id1/hapus", ['_csrf' => csrf_from("/bk/laporan/$id1/hapus"), 'confirm' => 'hapus-permanen']);
check('langkah 2 hapus dengan konfirmasi benar -> 303 ke /bk/laporan', $code === 303 && str_contains($headers, 'Location: /bk/laporan'));

$countWhere = function (string $sql) use ($pdo): int {
    $s = $pdo->query($sql);
    $v = (int) $s->fetchColumn();
    $s->closeCursor();
    return $v;
};
clearstatcache();
$remReport = $countWhere("SELECT COUNT(*) FROM reports WHERE id = $id1");
$remMsg    = $countWhere("SELECT COUNT(*) FROM messages WHERE report_id = $id1");
$remAtt    = $countWhere("SELECT COUNT(*) FROM attachments WHERE report_id = $id1");
$remNote   = $countWhere("SELECT COUNT(*) FROM internal_notes WHERE report_id = $id1");
check('hapus permanen membersihkan reports, messages, attachments, internal_notes, dan file fisik',
    $remReport === 0 && $remMsg === 0 && $remAtt === 0 && $remNote === 0 && !is_file($attPath),
    "r=$remReport m=$remMsg a=$remAtt n=$remNote file=" . (is_file($attPath) ? 'ada' : 'hilang'));

// --- 10. Logout dengan CSRF ---
[$code] = http('POST', '/bk/logout', []);
check('logout tanpa CSRF -> 403', $code === 403);
[$code, $headers] = http('POST', '/bk/logout', ['_csrf' => csrf_from('/bk/laporan')]);
check('logout dengan CSRF -> 303 ke /bk/login', $code === 303 && str_contains($headers, 'Location: /bk/login'));
[$code] = http('GET', '/bk/laporan');
check('setelah logout tidak bisa membuka /bk/laporan lagi', $code === 303);

// --- Bersih-bersih ---
unset($pdo);
proc_terminate($server);
@unlink($attPath);
foreach (glob($tmp . '/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmp);

echo $failures === 0 ? "\nSemua tes BK (Tahap 3) lolos.\n" : "\n$failures tes gagal.\n";
exit($failures === 0 ? 0 : 1);
