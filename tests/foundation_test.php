<?php
declare(strict_types=1);

/**
 * Tes fondasi (tanpa framework): php tests/foundation_test.php
 * Memakai config dan database sementara; tidak menyentuh config.php maupun data/ asli.
 */

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sa_test_' . bin2hex(random_bytes(4));
mkdir($tmp);
file_put_contents($tmp . '/config.php', '<?php return ' . var_export([
    'db_path'        => $tmp . '/test.sqlite',
    'encryption_key' => base64_encode(random_bytes(32)),
    'pepper'         => bin2hex(random_bytes(32)),
    'debug'          => true,
    'timezone'       => 'Asia/Jakarta',
], true) . ';');
define('SA_CONFIG_FILE', $tmp . '/config.php');

require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/app/migrate.php';

$failures = 0;
function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? '[OK]    ' : '[GAGAL] ') . $name . "\n";
    $failures += $ok ? 0 : 1;
}

function throws(callable $fn): bool
{
    try {
        $fn();
    } catch (Throwable) {
        return true;
    }
    return false;
}

// --- Migrasi dan database ---
$pdo = db_connect($tmp . '/test.sqlite');
migrate($pdo);
migrate($pdo); // kedua kali tidak boleh galat
check('migrasi dua kali tanpa galat', true);

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
    ->fetchAll(PDO::FETCH_COLUMN);
check('8 tabel terbentuk', $tables === ['access_log', 'attachments', 'categories', 'internal_notes', 'messages', 'rate_limits', 'reports', 'users']);
check('journal_mode WAL', strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn()) === 'wal');
check('foreign_keys ON', (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() === 1);

foreach (['reports', 'messages'] as $table) {
    $cols = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
    check("$table tanpa kolom ip/agent/device", preg_grep('/(^|_)ip(_|$)|agent|device/i', $cols) === []);
}

$now = now_hour();
$pdo->exec("INSERT INTO categories (name) VALUES ('Uji')");
$insertReport = $pdo->prepare(
    'INSERT INTO reports (ticket_hash, category_id, reporter_role, incident_date, description_enc, urgency, status, created_at, updated_at)
     VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?)'
);
check('laporan valid diterima', $insertReport->execute(['h1', 'korban', $now, 'x', 'ringan', 'baru', $now, $now]));
check('CHECK reporter_role menolak nilai asing', throws(fn () => $insertReport->execute(['h2', 'guru', $now, 'x', 'ringan', 'baru', $now, $now])));
check('CHECK urgency menolak nilai asing', throws(fn () => $insertReport->execute(['h3', 'korban', $now, 'x', 'kritis', 'baru', $now, $now])));
check('CHECK status menolak nilai asing', throws(fn () => $insertReport->execute(['h4', 'korban', $now, 'x', 'ringan', 'tutup', $now, $now])));
check('ticket_hash UNIQUE', throws(fn () => $insertReport->execute(['h1', 'korban', $now, 'x', 'ringan', 'baru', $now, $now])));
check('foreign key menolak pesan yatim', throws(fn () => $pdo->exec("INSERT INTO messages (report_id, sender, body_enc, created_at) VALUES (999, 'bk', 'x', '$now')")));
$pdo->exec("INSERT INTO messages (report_id, sender, body_enc, created_at) VALUES (1, 'bk', 'x', '$now')");
$pdo->exec('DELETE FROM reports WHERE id = 1');
check('hapus laporan ikut menghapus pesan (CASCADE)', (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn() === 0);
check('now_hour() berakhir :00:00', str_ends_with($now, ':00:00'));
unset($pdo, $insertReport);

// --- Enkripsi ---
$text = "Uraian laporan \u{1F512} dengan Unicode: ñ, 日本語, <tag> & \"kutip\"";
$enc  = encrypt($text);
check('encrypt/decrypt bolak-balik', decrypt($enc) === $text);
check('ciphertext tidak memuat plaintext', !str_contains($enc, 'Uraian') && !str_contains(base64_decode($enc), 'Uraian'));
check('nonce acak: dua hasil berbeda', encrypt($text) !== $enc);
check('string kosong bisa dienkripsi', decrypt(encrypt('')) === '');
$raw = base64_decode($enc);
$raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] ^ "\x01";
check('data dimanipulasi ditolak', throws(fn () => decrypt(base64_encode($raw))));
check('ciphertext sampah ditolak', throws(fn () => decrypt('bukan-base64!!')) && throws(fn () => decrypt('QUJD')));

// --- Kode tiket ---
$ok = true;
for ($i = 0; $i < 2000; $i++) {
    $ok = $ok && preg_match('/^[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/', generate_ticket()) === 1;
}
check('generate_ticket: format dan alfabet (2000 sampel)', $ok);
check('generate_ticket: tidak berulang', generate_ticket() !== generate_ticket());
$code = 'ABCD-EFGH-JKLM';
check('ticket_hash: 64 hex', preg_match('/^[0-9a-f]{64}$/', ticket_hash($code)) === 1);
check('ticket_hash: deterministik', ticket_hash($code) === ticket_hash($code));
check('ticket_hash: huruf kecil/tanpa tanda hubung sama', ticket_hash('abcd efgh jklm') === ticket_hash($code) && ticket_hash('ABCDEFGHJKLM') === ticket_hash($code));
check('ticket_hash: kode lain beda hash', ticket_hash('ABCD-EFGH-JKLN') !== ticket_hash($code));
check('ticket_hash: bukan hash polos tanpa pepper', ticket_hash($code) !== hash('sha256', 'ABCDEFGHJKLM'));

// --- Keamanan ---
check('e() meng-escape HTML', e('<a href="x">\'&') === '&lt;a href=&quot;x&quot;&gt;&#039;&amp;');
$_SESSION = [];
$token = csrf_token();
check('csrf: token 64 hex dan stabil', preg_match('/^[0-9a-f]{64}$/', $token) === 1 && csrf_token() === $token);
check('csrf: token benar lolos', csrf_verify($token));
check('csrf: salah/kosong/null/array ditolak', !csrf_verify('salah') && !csrf_verify('') && !csrf_verify(null) && !csrf_verify([$token]));
check('csrf_field memuat token', str_contains(csrf_field(), $token));

// Bersihkan berkas sementara
foreach (glob($tmp . '/*') ?: [] as $file) {
    unlink($file);
}
rmdir($tmp);

echo $failures === 0 ? "\nSemua tes lolos.\n" : "\n$failures tes gagal.\n";
exit($failures === 0 ? 0 : 1);
