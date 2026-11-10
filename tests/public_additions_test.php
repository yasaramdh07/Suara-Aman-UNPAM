<?php
declare(strict_types=1);

/**
 * Tes tambahan halaman publik (Tahap 6): php tests/public_additions_test.php
 * Menguji logo Unpam (tampil & fallback saat file tidak ada), tombol Masuk/Dashboard/Keluar
 * di header publik untuk anonim/BK/admin, bagian edukasi (#jenis, #pancasila, #hukum),
 * serta halaman /penyusun.
 */

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sa_pub6_' . bin2hex(random_bytes(4));
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
    ->execute(['bk_t6', password_hash('SandiBK-T6', PASSWORD_BCRYPT), 'bk', $now]);
$pdo->prepare('INSERT INTO users (username, password_hash, role, is_active, created_at) VALUES (?, ?, ?, 1, ?)')
    ->execute(['adm_t6', password_hash('SandiAdm-T6', PASSWORD_BCRYPT), 'admin', $now]);

$port   = 8102;
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
    echo ($ok ? '[OK]    ' : '[GAGAL] ') . $name . ($ok || $detail === '' ? '' : " -> $detail") . "\n";
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

// --- 1. Beranda (/): Logo Unpam, tombol login sekunder, dan bagian edukasi ---
new_session();
[$code, $headers, $homeHtml] = http('GET', '/');
check('GET /: 200', $code === 200);
check('Logo Unpam tampil di header (.sa-logo-header) dan footer (.sa-logo-footer)',
    str_contains($homeHtml, 'class="sa-logo-header"')
    && str_contains($homeHtml, 'class="sa-logo-footer"')
    && str_contains($homeHtml, 'alt="Logo Universitas Pamulang"')
    && str_contains($homeHtml, 'Proyek Mata Kuliah Pendidikan Pancasila, Universitas Pamulang'));

[$imgCode, $imgHdr] = http('GET', '/assets/img/logo-unpam.png');
check('File /assets/img/logo-unpam.png dapat diakses (200 image/png)', $imgCode === 200 && str_contains($imgHdr, 'image/png'));

check('Tombol "Buat laporan" tetap satu-satunya .btn-primary di beranda',
    substr_count($homeHtml, 'btn-primary') === 1 && str_contains($homeHtml, 'class="btn btn-primary" href="/lapor"') && str_contains($homeHtml, 'Buat laporan'));

check('Tombol sekunder "Masuk Guru BK / Admin" mengarah ke /bk/login',
    str_contains($homeHtml, 'href="/bk/login"') && str_contains($homeHtml, 'Masuk Guru BK / Admin</a>') && !str_contains($homeHtml, '>Dashboard<'));

check('Baris tautan anchor edukasi (#jenis, #pancasila, #hukum) tampil',
    str_contains($homeHtml, 'href="#jenis"') && str_contains($homeHtml, 'Jenis-jenis</a>')
    && str_contains($homeHtml, 'href="#pancasila"') && str_contains($homeHtml, 'Kaitan Pancasila</a>')
    && str_contains($homeHtml, 'href="#hukum"') && str_contains($homeHtml, 'Dasar hukum</a>'));

check('Bagian #jenis memuat <dl> dengan 7 jenis perundungan',
    str_contains($homeHtml, 'id="jenis"')
    && str_contains($homeHtml, 'Perundungan fisik')
    && str_contains($homeHtml, 'Perundungan verbal')
    && str_contains($homeHtml, 'Pengucilan (sosial)')
    && str_contains($homeHtml, 'Perundungan siber')
    && str_contains($homeHtml, 'Pemalakan atau pemerasan')
    && str_contains($homeHtml, 'Diskriminasi dan intoleransi')
    && str_contains($homeHtml, 'Kekerasan seksual'));

check('Bagian #pancasila memuat tabel 5 sila',
    str_contains($homeHtml, 'id="pancasila"')
    && str_contains($homeHtml, 'Ketuhanan Yang Maha Esa')
    && str_contains($homeHtml, 'Keadilan sosial bagi seluruh rakyat Indonesia'));

check('Bagian #hukum memuat 6 peraturan & catatan JDIH',
    str_contains($homeHtml, 'id="hukum"')
    && str_contains($homeHtml, 'UU No. 35 Tahun 2014')
    && str_contains($homeHtml, 'Permendikbudristek No. 46 Tahun 2023')
    && str_contains($homeHtml, 'UU No. 11 Tahun 2012')
    && str_contains($homeHtml, 'UU No. 1 Tahun 2023 tentang KUHP')
    && str_contains($homeHtml, 'UU No. 1 Tahun 2024')
    && str_contains($homeHtml, 'UU No. 12 Tahun 2022')
    && str_contains($homeHtml, 'peraturan.go.id'));

// --- 2. Login sebagai BK & Admin -> tombol di beranda berubah menjadi Dashboard & Keluar ---
http('POST', '/bk/login', ['_csrf' => csrf_from('/bk/login'), 'username' => 'bk_t6', 'password' => 'SandiBK-T6']);
[, , $homeBk] = http('GET', '/');
check('Saat login BK: header publik menampilkan Dashboard (/bk/laporan) & Keluar, tanpa membocorkan username',
    str_contains($homeBk, 'href="/bk/laporan"') && str_contains($homeBk, 'Dashboard</a>')
    && str_contains($homeBk, 'action="/bk/logout"')
    && !str_contains($homeBk, 'Masuk Guru BK / Admin')
    && !str_contains($homeBk, 'bk_t6'));

new_session();
http('POST', '/bk/login', ['_csrf' => csrf_from('/bk/login'), 'username' => 'adm_t6', 'password' => 'SandiAdm-T6']);
[, , $homeAdm] = http('GET', '/');
check('Saat login Admin: header publik menampilkan Dashboard (/admin/statistik) & Keluar',
    str_contains($homeAdm, 'href="/admin/statistik"') && str_contains($homeAdm, 'Dashboard</a>')
    && str_contains($homeAdm, 'action="/bk/logout"')
    && !str_contains($homeAdm, 'Masuk Guru BK / Admin'));

// --- 3. Halaman /penyusun ---
new_session();
[$codeP, , $htmlP] = http('GET', '/penyusun');
check('GET /penyusun: 200 & menampilkan logo Unpam ukuran 56px (.sa-logo-page)',
    $codeP === 200 && str_contains($htmlP, 'class="sa-logo-page"'));
check('/penyusun memuat informasi prodi, kelompok, mata kuliah, dan dosen pengampu',
    str_contains($htmlP, 'Disusun dan dirancang oleh Mahasiswa Prodi Teknik Informatika')
    && str_contains($htmlP, 'Kelompok 2 — Kelas 01TPLP009')
    && str_contains($htmlP, 'Mata Kuliah Pendidikan Pancasila')
    && str_contains($htmlP, 'Dosen Pengampu: Ibu Setiawati'));

$expectedMembers = [
    ['Ilyasa Ramadhany Ekaputra Ismail', '261011401191'],
    ['Siti Aisah',                       '261011400454'],
    ['Mutiara Rizki Ramadhani',          '261011400373'],
    ['Rifqi Aditiya',                    '261011400435'],
    ['Muhammad Syefril.H',               '261011401423'],
    ['Amri median',                      '261011401247'],
];
$allMembersMatch = true;
foreach ($expectedMembers as [$nama, $nim]) {
    $allMembersMatch = $allMembersMatch && str_contains($htmlP, $nama) && str_contains($htmlP, $nim);
}
check('/penyusun memuat 6 nama dan NIM persis sesuai spesifikasi', $allMembersMatch);

// --- 4. Simulasi hapus sementara logo-unpam.png: tidak ada gambar rusak ---
$logoFile = BASE_PATH . '/public/assets/img/logo-unpam.png';
$logoBak  = BASE_PATH . '/public/assets/img/logo-unpam.png.bak';
rename($logoFile, $logoBak);
try {
    [, , $homeNoLogo] = http('GET', '/');
    [, , $penyusunNoLogo] = http('GET', '/penyusun');
    check('Saat logo-unpam.png tidak ada: halaman tetap rapi tanpa elemen <img> logo yang rusak',
        !str_contains($homeNoLogo, 'sa-logo-header')
        && !str_contains($homeNoLogo, 'sa-logo-footer')
        && !str_contains($penyusunNoLogo, 'sa-logo-page'));
} finally {
    if (is_file($logoBak)) {
        rename($logoBak, $logoFile);
    }
}

// --- 5. Tanpa permintaan jaringan ke domain luar ---
check('Halaman / dan /penyusun tidak memuat URL eksternal (http/https)',
    preg_match('#(src|href|action)=["\']https?://#i', $homeHtml . $htmlP) === 0);

// Bersih-bersih
unset($pdo);
proc_terminate($server);
foreach (glob($tmp . '/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmp);

echo $failures === 0 ? "\nSemua tes Tahap 6 lolos.\n" : "\n$failures tes gagal.\n";
exit($failures === 0 ? 0 : 1);

