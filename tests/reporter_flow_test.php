<?php
declare(strict_types=1);

/**
 * Tes alur pelapor end-to-end: php tests/reporter_flow_test.php
 * Menjalankan php -S sungguhan (port 8098) dengan config dan database SEMENTARA.
 * Berkas upload tes ditulis ke data/uploads lalu dihapus di akhir.
 */

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sa_flow_' . bin2hex(random_bytes(4));
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
putenv('SA_CONFIG_FILE=' . SA_CONFIG_FILE); // diwarisi proses server

require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/app/migrate.php';
migrate(db());
seed_categories(db());

$port   = 8098;
$base   = "http://127.0.0.1:$port";
$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', BASE_PATH . '/public', '-d', 'upload_max_filesize=6M', '-d', 'post_max_size=20M'],
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

/** Permintaan HTTP dengan cookie jar. Return [kode, header, isi]. */
function http(string $method, string $path, array $post = []): array
{
    global $base, $jar;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_PROXY => '',
        CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar, CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'SuaraAmanTest/1.0 marker-UA',
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
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

/** Ambil token CSRF dan jawaban CAPTCHA dari form. */
function form_tokens(string $path = '/lapor'): array
{
    [, , $html] = http('GET', $path);
    preg_match('/name="_csrf" value="([0-9a-f]+)"/', $html, $c);
    $answer = '';
    if (preg_match('/Berapa (\d) (ditambah|dikurangi) (\d)\?/', $html, $m)) {
        $answer = (string) ($m[2] === 'ditambah' ? $m[1] + $m[3] : $m[1] - $m[3]);
    }
    return [$c[1] ?? '', $answer];
}

function submit_report(array $override = [], array $files = []): array
{
    [$csrf, $answer] = form_tokens();
    $fields = array_merge([
        '_csrf' => $csrf, 'peran' => 'korban', 'kategori' => '1',
        'waktu' => date('Y-m-d\TH:i', strtotime('-2 hours')),
        'lokasi' => 'RAHASIA-LOKASI-XYZ', 'uraian' => 'RAHASIA-URAIAN-XYZ saya dikucilkan teman sekelas.',
        'terlibat' => 'RAHASIA-TERLIBAT-XYZ', 'jawaban' => $answer,
    ], $override);
    foreach ($files as $i => $file) {
        $fields["bukti[$i]"] = $file;
    }
    return http('POST', '/lapor', $fields);
}

/** Kirim laporan valid lalu ambil kode tiket dari halaman sukses. */
function submit_and_get_ticket(array $override = [], array $files = []): ?string
{
    [$code, $headers] = submit_report($override, $files);
    if ($code !== 303 || !str_contains($headers, 'Location: /lapor/sukses')) {
        return null;
    }
    [, , $html] = http('GET', '/lapor/sukses');
    return preg_match('/id="ticket-code">([2-9A-Z-]{14})</', $html, $m) ? $m[1] : null;
}

function jpeg_with_exif(): string
{
    $im = imagecreatetruecolor(40, 20);
    imagefill($im, 0, 0, imagecolorallocate($im, 200, 40, 40));
    ob_start();
    imagejpeg($im);
    $jpg  = (string) ob_get_clean();
    $make = "SECRETCAMERA\0";
    $tiff = "MM\x00\x2A" . pack('N', 8) . pack('n', 2)
        . pack('nnN', 0x010F, 2, strlen($make)) . pack('N', 8 + 2 + 24 + 4)
        . pack('nnN', 0x0112, 3, 1) . pack('nn', 6, 0) // Orientation = 6 (putar 90 derajat)
        . pack('N', 0) . $make;
    $app1    = "\xFF\xE1" . pack('n', 2 + 6 + strlen($tiff)) . "Exif\0\0" . $tiff;
    $comment = "\xFF\xFE" . pack('n', 2 + 13) . 'SECRETCOMMENT';
    return substr($jpg, 0, 2) . $app1 . $comment . substr($jpg, 2);
}

function png_with_text(): string
{
    $im = imagecreatetruecolor(30, 30);
    ob_start();
    imagepng($im);
    $png   = (string) ob_get_clean();
    $data  = "Comment\0SECRETTEXT";
    $chunk = pack('N', strlen($data)) . 'tEXt' . $data . pack('N', crc32('tEXt' . $data));
    return substr($png, 0, 33) . $chunk . substr($png, 33);
}

function webp_image(): string
{
    ob_start();
    imagewebp(imagecreatetruecolor(30, 30));
    return (string) ob_get_clean();
}

function temp_file(string $bytes, string $name): string
{
    global $tmp;
    $path = $tmp . '/' . $name;
    file_put_contents($path, $bytes);
    return $path;
}

function upload(string $path, string $mime): CURLFile
{
    return new CURLFile($path, $mime, basename($path));
}

function error_text(string $html): string
{
    return preg_match('/sa-note-error" role="alert">\s*(.*?)\s*<\/div>/s', $html, $m) ? trim(strip_tags($m[1])) : '';
}

$pdo = db();
$scalar = function (string $sql) use ($pdo): int {
    $s = $pdo->query($sql);
    $v = (int) $s->fetchColumn();
    $s->closeCursor();
    return $v;
};
new_session();

// --- Halaman publik ---
foreach (['/' => 'Buat laporan', '/privasi' => 'Arti kode tiket', '/lapor' => 'Pertanyaan keamanan', '/status' => 'Kode tiket'] as $path => $needle) {
    [$code, $headers, $html] = http('GET', $path);
    check("GET $path: 200 dan memuat \"$needle\"", $code === 200 && str_contains($html, $needle));
    check("GET $path: tanpa sumber eksternal", preg_match('#(src|href|action)="https?://#', $html) === 0);
}
new_session();
[, $headers, $html] = http('GET', '/lapor');
check('header: CSP, nosniff, cookie HttpOnly + SameSite=Strict',
    str_contains($headers, "Content-Security-Policy: default-src 'none'") && str_contains($headers, 'nosniff')
    && str_contains($headers, 'HttpOnly') && str_contains($headers, 'SameSite=Strict'));
$categoriesShown = 0;
foreach (['Kekerasan fisik', 'Perundungan verbal', 'Perundungan online (siber)', 'Pengucilan', 'Pemerasan atau pemalakan', 'Lainnya'] as $name) {
    $categoriesShown += str_contains($html, e($name)) ? 1 : 0;
}
check('form memuat 6 kategori dari tabel categories', $categoriesShown === 6);
check('form: multipart + input file multiple + CAPTCHA', str_contains($html, 'multipart/form-data') && str_contains($html, 'name="bukti[]"') && str_contains($html, 'name="jawaban"'));

// --- CSRF dan validasi ---
[$code] = http('POST', '/lapor', ['peran' => 'korban']);
check('POST /lapor tanpa CSRF: 403', $code === 403);
[$code] = http('POST', '/lapor', ['_csrf' => 'salah', 'peran' => 'korban']);
check('POST /lapor CSRF salah: 403', $code === 403);

[$csrf] = form_tokens();
[$code, , $html] = http('POST', '/lapor', ['_csrf' => $csrf]);
check('form kosong: 422 dengan pesan jelas', $code === 422 && str_contains($html, 'Pilih apakah kamu korban') && str_contains($html, 'Ceritakan apa yang terjadi') && str_contains($html, 'aria-invalid="true"'));
[$code, , $html] = submit_report(['waktu' => date('Y-m-d\TH:i', strtotime('+3 days'))]);
check('waktu di masa depan ditolak', $code === 422 && str_contains($html, 'tidak boleh di masa depan'));
[$code, , $html] = submit_report(['waktu' => '2026-02-31T10:00']);
check('tanggal mustahil ditolak', $code === 422 && str_contains($html, 'tanggal dan jam kejadian dengan benar'));
[$code, , $html] = submit_report(['uraian' => str_repeat('a', 5001)]);
check('uraian 5001 karakter ditolak', $code === 422 && str_contains($html, 'Maksimal 5.000 karakter'));
[$code, , $html] = submit_report(['kategori' => '999']);
check('kategori tidak dikenal ditolak', $code === 422 && str_contains($html, 'Pilih jenis kejadian'));
[$code, , $html] = submit_report(['jawaban' => '0', 'uraian' => 'Isian lain sudah benar semua <script>alert(1)</script>']);
check('CAPTCHA salah: 422', $code === 422 && str_contains($html, 'Jawaban salah'));
check('isian dipertahankan dan di-escape setelah galat', str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html, '<script>alert(1)'));

// --- Kirim tanpa gambar ---
$ticket = submit_and_get_ticket();
check('kirim tanpa gambar: 303 lalu kode tiket XXXX-XXXX-XXXX', $ticket !== null && preg_match('/^[2-9A-HJ-NP-Z]{4}(-[2-9A-HJ-NP-Z]{4}){2}$/', $ticket) === 1, (string) $ticket);
[, , $html] = http('GET', '/lapor/sukses');
check('/lapor/sukses kedua kali: kode tidak tampil lagi', !str_contains($html, 'ticket-code') && str_contains($html, 'tidak ditampilkan lagi'));
$fetchReport = function (string $t) use ($pdo): array|false {
    $s = $pdo->prepare('SELECT * FROM reports WHERE ticket_hash = ?');
    $s->execute([ticket_hash($t)]);
    $r = $s->fetch();
    $s->closeCursor();
    return $r;
};
$report = $fetchReport((string) $ticket);
check('laporan tersimpan: hanya hash tiket, status baru, urgensi ringan tanpa penanda', $report && $report['status'] === 'baru' && $report['urgency'] === 'ringan' && (int) $report['urgency_auto_flag'] === 0 && !str_contains(json_encode($report), (string) $ticket));
check('waktu laporan dibulatkan ke jam', str_ends_with((string) $report['created_at'], ':00:00'));
check('uraian/lokasi/pihak terdekripsi sesuai', decrypt($report['description_enc']) === 'RAHASIA-URAIAN-XYZ saya dikucilkan teman sekelas.' && decrypt($report['location_enc']) === 'RAHASIA-LOKASI-XYZ' && decrypt($report['involved_enc']) === 'RAHASIA-TERLIBAT-XYZ');

// --- Urgensi otomatis ---
$urgent = submit_and_get_ticket(['uraian' => 'Dia bilang mau BUNUH-diri kalau saya cerita.', 'terlibat' => '']);
$urgentRow = $fetchReport((string) $urgent);
check('kata kunci risiko: urgensi mendesak + penanda', $urgentRow && $urgentRow['urgency'] === 'mendesak' && (int) $urgentRow['urgency_auto_flag'] === 1);
check('pihak terlibat kosong disimpan NULL', $urgentRow && $urgentRow['involved_enc'] === null);

// --- Upload gambar ---
$jpg  = temp_file(jpeg_with_exif(), 'foto.jpg');
$png  = temp_file(png_with_text(), 'gambar.png');
$webp = temp_file(webp_image(), 'gambar.webp');
if (function_exists('exif_read_data')) {
    check('prasyarat: JPEG uji memang berisi EXIF', (@exif_read_data($jpg)['Make'] ?? '') === 'SECRETCAMERA');
}
check('prasyarat: file mentah memuat penanda rahasia', str_contains((string) file_get_contents($jpg), 'SECRETCOMMENT') && str_contains((string) file_get_contents($png), 'SECRETTEXT'));

$withImages = submit_and_get_ticket([], [upload($jpg, 'image/jpeg'), upload($png, 'image/png'), upload($webp, 'image/webp')]);
check('kirim dengan 3 gambar (jpg+png+webp): berhasil', $withImages !== null);
$imgReport = $fetchReport((string) $withImages);
$atts = $pdo->prepare('SELECT * FROM attachments WHERE report_id = ? ORDER BY id');
$atts->execute([$imgReport['id']]);
$attachments = $atts->fetchAll();
check('3 lampiran tercatat', count($attachments) === 3);
$finfo = new finfo(FILEINFO_MIME_TYPE);
$allClean = true;
$names    = [];
foreach ($attachments as $a) {
    $path = DATA_PATH . '/uploads/' . $a['stored_name'];
    $names[] = $path;
    $bytes   = is_file($path) ? (string) file_get_contents($path) : '';
    $clean   = $bytes !== '' && !str_contains($bytes, 'Exif') && !str_contains($bytes, 'SECRET')
        && $finfo->file($path) === $a['mime'] && getimagesize($path) !== false
        && preg_match('/^[0-9a-f]{32}\.(jpg|png|webp)$/', $a['stored_name']) === 1 && (int) $a['size'] === strlen($bytes);
    $allClean = $allClean && $clean;
}
check('lampiran: nama acak, MIME sesuai, tanpa EXIF/komentar/tEXt', $allClean);
if (function_exists('exif_read_data') && isset($names[0])) {
    $exifAfter = @exif_read_data($names[0]);
    check('lampiran JPEG: tidak ada EXIF tersisa', $exifAfter === false || !isset($exifAfter['Make']));
    $size = getimagesize($names[0]);
    check('orientasi EXIF diterapkan sebelum dibuang (40x20 jadi 20x40)', $size && $size[0] === 20 && $size[1] === 40, $size ? "{$size[0]}x{$size[1]}" : '');
}
check('folder upload ada di luar public/', !is_dir(BASE_PATH . '/public/uploads') && str_starts_with(realpath($names[0]), realpath(BASE_PATH . '/data')));
[$code] = http('GET', '/data/uploads/' . $attachments[0]['stored_name']);
check('file bukti tidak bisa dibuka lewat URL', $code === 404);

// --- Penolakan upload ---
$before = $scalar('SELECT COUNT(*) FROM reports');
$filesBefore = count(glob(DATA_PATH . '/uploads/*') ?: []);
$cases = [
    'skrip PHP bernama .jpg'   => [[upload(temp_file('<?php echo "x"; ?>', 'evil.jpg'), 'image/jpeg')], 'bukan gambar'],
    'teks bernama .png'        => [[upload(temp_file('hanya teks biasa', 'teks.png'), 'image/png')], 'bukan gambar'],
    'GIF (tipe tidak diizinkan)' => [[upload(temp_file(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 'a.gif'), 'image/gif')], 'bukan gambar'],
    'JPEG terpotong/rusak'     => [[upload(temp_file(substr(jpeg_with_exif(), 0, 120), 'rusak.jpg'), 'image/jpeg')], 'gambar'],
    'file > 5 MB'              => [[upload(temp_file(random_bytes(5_500_000), 'besar.jpg'), 'image/jpeg')], 'lebih dari 5 MB'],
    '4 gambar'                 => [[upload($png, 'image/png'), upload($png, 'image/png'), upload($png, 'image/png'), upload($png, 'image/png')], 'Maksimal 3 gambar'],
];
foreach ($cases as $label => [$files, $message]) {
    [$code, , $html] = submit_report([], $files);
    check("upload ditolak: $label", $code === 422 && str_contains($html, $message), "kode $code");
}
check('penolakan tidak membuat laporan atau file baru', $scalar('SELECT COUNT(*) FROM reports') === $before && count(glob(DATA_PATH . '/uploads/*') ?: []) === $filesBefore);
[$csrf, $answer] = form_tokens();
[$code, , $html] = http('POST', '/lapor', ['_csrf' => $csrf, 'bukti[0]' => upload(temp_file(random_bytes(21_000_000), 'raksasa.jpg'), 'image/jpeg')]);
check('POST melebihi post_max_size: 413 (bukan 403)', $code === 413, "kode $code");

// --- Cek status dan percakapan ---
new_session();
$wrong = 'AAAA-AAAA-AAAA';
[, , $htmlWrong]  = http('POST', '/status', ['_csrf' => form_tokens('/status')[0], 'kode' => $wrong]);
[, , $htmlShort]  = http('POST', '/status', ['_csrf' => form_tokens('/status')[0], 'kode' => 'abc']);
[, , $htmlEmpty]  = http('POST', '/status', ['_csrf' => form_tokens('/status')[0], 'kode' => '']);
check('kode salah, format salah, kosong: pesan generik identik', error_text($htmlWrong) !== '' && error_text($htmlWrong) === error_text($htmlShort) && error_text($htmlShort) === error_text($htmlEmpty));
check('kode salah tidak membocorkan data laporan', !str_contains($htmlWrong, 'Percakapan') && !str_contains($htmlWrong, 'Kekerasan'));

$lower = strtolower(str_replace('-', '', (string) $ticket));
$post = fn (array $f) => http('POST', '/status', ['_csrf' => form_tokens('/status')[0]] + $f);
[$code, , $html] = $post(['kode' => $lower]);
check('kode benar (huruf kecil tanpa tanda hubung): status, kategori, tanggal, form balasan',
    $code === 200 && str_contains($html, 'Status laporan') && str_contains($html, 'sa-badge-baru') && str_contains($html, 'Kekerasan fisik')
    && str_contains($html, format_date_id(date('Y-m-d H:00:00'))) && str_contains($html, 'name="pesan"') && str_contains($html, 'Belum ada pesan'));
check('halaman status tidak menampilkan uraian laporan', !str_contains($html, 'RAHASIA-URAIAN'));

$pdo->prepare('INSERT INTO messages (report_id, sender, body_enc, created_at) VALUES (?, \'bk\', ?, ?)')
    ->execute([$report['id'], encrypt('Halo, ini balasan BK'), date('Y-m-d H:00:00')]);
[, , $html] = $post(['kode' => (string) $ticket]);
check('balasan BK muncul di /status pelapor', str_contains($html, 'Halo, ini balasan BK') && str_contains($html, 'sa-msg-bk'));

preg_match('/name="nonce" value="([0-9a-f]+)"/', $html, $n);
$reply = ['kode' => (string) $ticket, 'aksi' => 'balas', 'pesan' => "Terima kasih \u{1F64F} <b>bu</b>\nbaris dua", 'nonce' => $n[1] ?? ''];
[$code, , $html] = $post($reply);
check('balasan pelapor terkirim dan tampil (di-escape)', $code === 200 && str_contains($html, 'Pesan terkirim.') && str_contains($html, 'Terima kasih') && str_contains($html, '&lt;b&gt;bu&lt;/b&gt;') && !str_contains($html, '<b>bu</b>'));
$count = fn () => $scalar("SELECT COUNT(*) FROM messages WHERE sender = 'pelapor'");
check('pesan pelapor tersimpan satu kali', $count() === 1);
[, , $html] = $post($reply);
check('kirim ulang dengan nonce lama tidak menggandakan pesan', $count() === 1 && str_contains($html, 'tidak dikirim ulang'));
preg_match('/name="nonce" value="([0-9a-f]+)"/', $html, $n);
[, , $html] = $post(['aksi' => 'balas', 'kode' => (string) $ticket, 'pesan' => '   ', 'nonce' => $n[1]]);
check('balasan kosong ditolak', str_contains($html, 'Tulis pesan dulu.') && $count() === 1);
preg_match('/name="nonce" value="([0-9a-f]+)"/', $html, $n);
[, , $html] = $post(['aksi' => 'balas', 'kode' => (string) $ticket, 'pesan' => str_repeat('x', 2001), 'nonce' => $n[1]]);
check('balasan > 2000 karakter ditolak', str_contains($html, 'terlalu panjang') && $count() === 1);
[, , $html] = $post(['aksi' => 'balas', 'kode' => $wrong, 'pesan' => 'tanpa kode benar', 'nonce' => 'x']);
check('balasan dengan kode salah ditolak generik, tidak disimpan', error_text($html) === error_text($htmlWrong) && $count() === 1);

// --- Rate limit ---
$pdo->exec('DELETE FROM rate_limits');
new_session();
$generic = true;
for ($i = 1; $i <= 10; $i++) {
    [$code, , $html] = $post(['kode' => sprintf('AAAA-AAAA-%04d', 2222 + $i)]);
    $generic = $generic && $code === 200 && error_text($html) === error_text($htmlWrong);
}
check('10 kegagalan pertama: 200 + pesan generik', $generic);
[$code, , $html] = $post(['kode' => (string) $ticket]);
check('percobaan ke-11 diblokir (429) walau kode benar', $code === 429 && str_contains($html, 'Terlalu banyak percobaan') && !str_contains($html, 'Percakapan'));
$limits = $pdo->query('SELECT * FROM rate_limits')->fetchAll();
check('rate_limits: 1 baris, hash 64 hex, bukan IP mentah', count($limits) === 1 && preg_match('/^[0-9a-f]{64}$/', $limits[0]['key_hash']) === 1 && (int) $limits[0]['count'] === 10 && !str_contains(json_encode($limits), '127.0.0.1'));
check('kunci rate limit = HMAC bersalt harian (bukan SHA-256 IP polos)', $limits[0]['key_hash'] !== hash('sha256', '127.0.0.1'));
$pdo->exec('UPDATE rate_limits SET expires_at = ' . (time() - 1));
[$code, , $html] = $post(['kode' => (string) $ticket]);
check('setelah jendela kedaluwarsa: akses pulih dan entri dibersihkan otomatis', $code === 200 && str_contains($html, 'Percakapan') && $scalar('SELECT COUNT(*) FROM rate_limits') === 0);

// --- Audit privasi isi database ---
$pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
$dump = '';
foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $dump .= json_encode($pdo->query("SELECT * FROM $table")->fetchAll(), JSON_UNESCAPED_UNICODE);
}
$raw = (string) file_get_contents($dbPath) . (is_file($dbPath . '-wal') ? (string) file_get_contents($dbPath . '-wal') : '');
foreach (['RAHASIA-URAIAN', 'RAHASIA-LOKASI', 'RAHASIA-TERLIBAT', 'Terima kasih', 'ini balasan BK', 'dikucilkan', 'BUNUH', 'bunuh', (string) $ticket, str_replace('-', '', (string) $ticket), '127.0.0.1', 'SuaraAmanTest', 'marker-UA', 'Mozilla'] as $needle) {
    check("database (isi tabel + file mentah) tidak memuat \"$needle\"", !str_contains($dump, $needle) && !str_contains($raw, $needle));
}
foreach (['reports', 'messages'] as $table) {
    $cols = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
    check("$table tanpa kolom ip/agent/device", preg_grep('/(^|_)ip(_|$)|agent|device/i', $cols) === []);
}
check('log error server tidak memuat isi laporan', !str_contains((string) @file_get_contents($tmp . '/server.err'), 'RAHASIA') && !str_contains((string) @file_get_contents(DATA_PATH . '/logs/error.log'), 'RAHASIA'));

// --- Bersih-bersih ---
unset($pdo, $row, $atts);
proc_terminate($server);
foreach ($names as $path) {
    @unlink($path);
}
foreach (glob($tmp . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($tmp);

echo $failures === 0 ? "\nSemua tes lolos.\n" : "\n$failures tes gagal.\n";
exit($failures === 0 ? 0 : 1);