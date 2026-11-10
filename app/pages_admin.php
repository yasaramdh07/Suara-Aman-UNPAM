<?php
declare(strict_types=1);

/**
 * Halaman area Admin (/admin/*).
 * Aturan privasi mutlak (PRD bagian 3, 7, 11):
 * - Admin TIDAK BOLEH membaca isi laporan, pesan, atau catatan internal.
 * - Seluruh query di berkas ini hanya membaca metadata agregat, akun, dan kategori.
 */

/** Daftar akun BK dan Admin, serta form pembuatan akun baru. */
function page_admin_accounts(): void
{
    $current = auth_require('admin');
    $users   = db()->query('SELECT id, username, role, is_active, created_at FROM users ORDER BY role DESC, id ASC')->fetchAll();

    render_staff('admin_accounts', [
        'title'   => 'Kelola akun',
        'users'   => $users,
        'current' => $current,
        'flash'   => flash_take(),
        'error'   => flash_take_error(),
    ]);
}

/** Buat akun baru (BK atau Admin). */
function page_admin_account_create(): void
{
    auth_require('admin');

    $username = clean_text($_POST['username'] ?? '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $role     = clean_text($_POST['role'] ?? 'bk');

    if (!in_array($role, ['bk', 'admin'], true)) {
        $role = 'bk';
    }
    if (preg_match('/^[a-zA-Z0-9_.-]{3,32}$/', $username) !== 1) {
        flash_set_error('Username harus 3–32 karakter (huruf, angka, titik, garis bawah, atau tanda hubung).');
        redirect('/admin/akun');
    }
    if (mb_strlen($password) < 8 || mb_strlen($password) > 128) {
        flash_set_error('Kata sandi awal minimal 8 karakter.');
        redirect('/admin/akun');
    }

    $exists = db()->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $exists->execute([$username]);
    if ((int) $exists->fetchColumn() > 0) {
        flash_set_error('Username "' . $username . '" sudah digunakan.');
        redirect('/admin/akun');
    }

    db()->prepare('INSERT INTO users (username, password_hash, role, is_active, created_at) VALUES (?, ?, ?, 1, ?)')
        ->execute([$username, password_hash($password, PASSWORD_BCRYPT), $role, now_full()]);

    flash_set('Akun "' . $username . '" berhasil dibuat.');
    redirect('/admin/akun');
}

/** Aktifkan atau nonaktifkan akun. Admin tidak boleh menonaktifkan dirinya sendiri atau admin terakhir. */
function page_admin_account_toggle(array $params): void
{
    $current = auth_require('admin');
    $id      = (int) ($params['id'] ?? 0);

    $stmt = db()->prepare('SELECT id, username, role, is_active FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();
    if (!$target) {
        abort(404);
    }

    $newStatus = (int) $target['is_active'] === 1 ? 0 : 1;

    if ($newStatus === 0) {
        if ((int) $target['id'] === (int) $current['id']) {
            flash_set_error('Kamu tidak dapat menonaktifkan akunmu sendiri.');
            redirect('/admin/akun');
        }
        if ($target['role'] === 'admin') {
            $activeAdmins = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
            if ($activeAdmins <= 1) {
                flash_set_error('Tidak dapat menonaktifkan admin aktif terakhir.');
                redirect('/admin/akun');
            }
        }
    }

    db()->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$newStatus, $id]);
    flash_set('Status akun "' . $target['username'] . '" diperbarui.');
    redirect('/admin/akun');
}

/** Reset kata sandi akun. */
function page_admin_account_password(array $params): void
{
    auth_require('admin');
    $id = (int) ($params['id'] ?? 0);

    $stmt = db()->prepare('SELECT id, username FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();
    if (!$target) {
        abort(404);
    }

    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (mb_strlen($password) < 8 || mb_strlen($password) > 128) {
        flash_set_error('Kata sandi baru untuk "' . $target['username'] . '" minimal 8 karakter.');
        redirect('/admin/akun');
    }

    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_BCRYPT), $id]);

    flash_set('Kata sandi untuk "' . $target['username'] . '" berhasil direset.');
    redirect('/admin/akun');
}

/** Daftar kategori kejadian. */
function page_admin_categories(): void
{
    auth_require('admin');

    $categories = db()->query(
        'SELECT c.id, c.name, c.is_active, COUNT(r.id) AS report_count
         FROM categories c
         LEFT JOIN reports r ON r.category_id = c.id
         GROUP BY c.id, c.name, c.is_active
         ORDER BY c.id ASC'
    )->fetchAll();

    render_staff('admin_categories', [
        'title'      => 'Kelola kategori',
        'categories' => $categories,
        'flash'      => flash_take(),
        'error'      => flash_take_error(),
    ]);
}

/** Tambah kategori baru. */
function page_admin_category_create(): void
{
    auth_require('admin');

    $name = clean_text($_POST['name'] ?? '');
    $len  = mb_strlen($name);
    if ($len < 2 || $len > 80) {
        flash_set_error('Nama kategori harus 2–80 karakter.');
        redirect('/admin/kategori');
    }

    $exists = db()->prepare('SELECT COUNT(*) FROM categories WHERE name = ?');
    $exists->execute([$name]);
    if ((int) $exists->fetchColumn() > 0) {
        flash_set_error('Kategori "' . $name . '" sudah ada.');
        redirect('/admin/kategori');
    }

    db()->prepare('INSERT INTO categories (name, is_active) VALUES (?, 1)')->execute([$name]);
    flash_set('Kategori "' . $name . '" berhasil ditambahkan.');
    redirect('/admin/kategori');
}

/** Ubah nama kategori. */
function page_admin_category_update(array $params): void
{
    auth_require('admin');
    $id = (int) ($params['id'] ?? 0);

    $stmt = db()->prepare('SELECT id, name FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        abort(404);
    }

    $name = clean_text($_POST['name'] ?? '');
    $len  = mb_strlen($name);
    if ($len < 2 || $len > 80) {
        flash_set_error('Nama kategori harus 2–80 karakter.');
        redirect('/admin/kategori');
    }

    $dup = db()->prepare('SELECT COUNT(*) FROM categories WHERE name = ? AND id != ?');
    $dup->execute([$name, $id]);
    if ((int) $dup->fetchColumn() > 0) {
        flash_set_error('Nama kategori "' . $name . '" sudah dipakai.');
        redirect('/admin/kategori');
    }

    db()->prepare('UPDATE categories SET name = ? WHERE id = ?')->execute([$name, $id]);
    flash_set('Nama kategori berhasil diperbarui menjadi "' . $name . '".');
    redirect('/admin/kategori');
}

/** Aktifkan atau nonaktifkan kategori (minimal 1 kategori harus tetap aktif). */
function page_admin_category_toggle(array $params): void
{
    auth_require('admin');
    $id = (int) ($params['id'] ?? 0);

    $stmt = db()->prepare('SELECT id, name, is_active FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $cat = $stmt->fetch();
    if (!$cat) {
        abort(404);
    }

    $newStatus = (int) $cat['is_active'] === 1 ? 0 : 1;
    if ($newStatus === 0) {
        $activeCount = (int) db()->query('SELECT COUNT(*) FROM categories WHERE is_active = 1')->fetchColumn();
        if ($activeCount <= 1) {
            flash_set_error('Minimal harus ada satu kategori aktif untuk form pelaporan.');
            redirect('/admin/kategori');
        }
    }

    db()->prepare('UPDATE categories SET is_active = ? WHERE id = ?')->execute([$newStatus, $id]);
    flash_set('Status kategori "' . $cat['name'] . '" diperbarui.');
    redirect('/admin/kategori');
}

/**
 * Statistik agregat untuk Admin.
 * Hanya jumlah laporan per bulan, per kategori, dan per status.
 * Tidak ada daftar laporan, tidak ada isi laporan, tidak ada tautan ke detail laporan.
 */
function page_admin_stats(): void
{
    auth_require('admin');

    $years = db()->query(
        "SELECT DISTINCT CAST(strftime('%Y', created_at) AS INTEGER) AS y FROM reports ORDER BY y DESC"
    )->fetchAll(PDO::FETCH_COLUMN);

    $currentYear = (int) date('Y');
    if ($years === []) {
        $years = [$currentYear];
    }
    $reqYear = clean_text($_GET['tahun'] ?? '');
    $year    = (ctype_digit($reqYear) && (int) $reqYear >= 2000 && (int) $reqYear <= 2100)
        ? (int) $reqYear
        : (int) $years[0];

    $totalStmt = db()->prepare("SELECT COUNT(*) FROM reports WHERE strftime('%Y', created_at) = ?");
    $totalStmt->execute([(string) $year]);
    $totalYear = (int) $totalStmt->fetchColumn();

    $byMonthStmt = db()->prepare(
        "SELECT CAST(strftime('%m', created_at) AS INTEGER) AS bulan, COUNT(*) AS jumlah
         FROM reports
         WHERE strftime('%Y', created_at) = ?
         GROUP BY bulan
         ORDER BY bulan"
    );
    $byMonthStmt->execute([(string) $year]);
    $byMonthRows = $byMonthStmt->fetchAll();
    $byMonth     = [];
    foreach ($byMonthRows as $r) {
        $byMonth[(int) $r['bulan']] = (int) $r['jumlah'];
    }

    $byCatStmt = db()->prepare(
        "SELECT c.name AS kategori, COUNT(r.id) AS jumlah
         FROM categories c
         LEFT JOIN reports r ON r.category_id = c.id AND strftime('%Y', r.created_at) = ?
         GROUP BY c.id, c.name
         ORDER BY c.id"
    );
    $byCatStmt->execute([(string) $year]);
    $byCategory = $byCatStmt->fetchAll();

    $byStatusStmt = db()->prepare(
        "SELECT status, COUNT(*) AS jumlah
         FROM reports
         WHERE strftime('%Y', created_at) = ?
         GROUP BY status"
    );
    $byStatusStmt->execute([(string) $year]);
    $byStatusRows = $byStatusStmt->fetchAll();
    $byStatus     = [];
    foreach ($byStatusRows as $r) {
        $byStatus[(string) $r['status']] = (int) $r['jumlah'];
    }

    render_staff('admin_stats', [
        'title'      => 'Statistik agregat',
        'year'       => $year,
        'years'      => $years,
        'totalYear'  => $totalYear,
        'byMonth'    => $byMonth,
        'byCategory' => $byCategory,
        'byStatus'   => $byStatus,
        'flash'      => flash_take(),
    ]);
}

/** Halaman unduh backup database. */
function page_admin_backup(): void
{
    auth_require('admin');
    $dbPath = (string) config('db_path');
    $size   = is_file($dbPath) ? (int) filesize($dbPath) : 0;

    render_staff('admin_backup', [
        'title'  => 'Backup database',
        'dbSize' => $size,
    ]);
}

/** Proses unduh salinan database SQLite (POST + CSRF + login admin). */
function page_admin_backup_download(): never
{
    auth_require('admin');

    $pdo     = db();
    $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sa_backup_' . bin2hex(random_bytes(8)) . '.sqlite';

    // VACUUM INTO menghasilkan satu file SQLite yang utuh dan konsisten walau mode WAL aktif.
    $quoted = $pdo->quote($tmpFile);
    $pdo->exec("VACUUM INTO $quoted");

    if (!is_file($tmpFile)) {
        abort(500, 'Gagal menyiapkan salinan database.');
    }

    $filename = 'suara-aman-backup-' . date('Ymd-His') . '.sqlite';
    header('Content-Type: application/vnd.sqlite3');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($tmpFile));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    readfile($tmpFile);
    @unlink($tmpFile);
    exit;
}

const ADMIN_RESET_BUCKET    = 'admin_reset';
const ADMIN_RESET_MAX_FAILS = 5;

/** Halaman Factory Reset (/admin/reset, hanya role admin). */
function page_admin_reset_form(): void
{
    auth_require('admin');

    render_staff('admin_reset', [
        'title'        => 'Reset data sistem',
        'mathQuestion' => reset_math_generate(),
        'deleteBk'     => false,
        'error'        => null,
    ]);
}

/** Eksekusi Factory Reset (/admin/reset POST, hanya role admin). */
function page_admin_reset_execute(): void
{
    $admin   = auth_require('admin');
    $rateKey = rate_user_key('reset-admin', (int) $admin['id']);

    $deleteBk = ($_POST['delete_bk'] ?? '') === '1';

    if (rate_is_blocked(ADMIN_RESET_BUCKET, ADMIN_RESET_MAX_FAILS, $rateKey)) {
        render_staff('admin_reset', [
            'title'        => 'Reset data sistem',
            'mathQuestion' => reset_math_generate(),
            'deleteBk'     => $deleteBk,
            'error'        => 'Terlalu banyak percobaan reset yang gagal. Fitur reset dikunci selama 15 menit.',
        ], 429);
        return;
    }

    $pin          = trim((string) ($_POST['pin'] ?? ''));
    $mathAnswer   = $_POST['math_answer'] ?? null;
    $confirmText  = trim((string) ($_POST['confirm_text'] ?? ''));
    $confirmCheck = ($_POST['confirm_check'] ?? '') === '1';

    try {
        $pinHash = (string) config('reset_pin_hash');
    } catch (Throwable) {
        $pinHash = '';
    }
    $hashToCheck = $pinHash !== '' ? $pinHash : LOGIN_DUMMY_HASH;

    $pinFormatOk = preg_match('/^\d{6}$/', $pin) === 1;
    $pinVerifyOk = password_verify($pin, $hashToCheck);
    $pinOk       = $pinHash !== '' && $pinFormatOk && $pinVerifyOk;
    $mathOk      = reset_math_verify($mathAnswer);
    $textOk      = $confirmText === 'HAPUS SEMUA';

    if (!$pinOk || !$mathOk || !$textOk || !$confirmCheck) {
        rate_register_failure(ADMIN_RESET_BUCKET, $rateKey);
        $blocked = rate_is_blocked(ADMIN_RESET_BUCKET, ADMIN_RESET_MAX_FAILS, $rateKey);

        render_staff('admin_reset', [
            'title'        => 'Reset data sistem',
            'mathQuestion' => reset_math_generate(),
            'deleteBk'     => $deleteBk,
            'error'        => $blocked
                ? 'Terlalu banyak percobaan reset yang gagal. Fitur reset dikunci selama 15 menit.'
                : 'Verifikasi reset tidak sesuai. Periksa kembali PIN, jawaban hitung, teks konfirmasi, dan kotak persetujuan.',
        ], $blocked ? 429 : 422);
        return;
    }

    require_once __DIR__ . '/migrate.php';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM internal_notes');
        $pdo->exec('DELETE FROM messages');
        $pdo->exec('DELETE FROM attachments');
        $pdo->exec('DELETE FROM reports');
        $pdo->exec('DELETE FROM access_log');
        $pdo->exec('DELETE FROM rate_limits');
        $pdo->exec('DELETE FROM categories');

        if ($deleteBk) {
            $pdo->exec("DELETE FROM users WHERE role = 'bk'");
        }

        $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('reports', 'messages', 'attachments', 'internal_notes', 'access_log', 'categories')");
        seed_categories($pdo);

        $pdo->prepare("INSERT INTO system_events (event, user_id, created_at) VALUES ('factory_reset', ?, ?)")
            ->execute([(int) $admin['id'], now_full()]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $uploadDir = DATA_PATH . '/uploads';
    if (is_dir($uploadDir)) {
        foreach (scandir($uploadDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.htaccess') {
                continue;
            }
            $filePath = $uploadDir . '/' . $entry;
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
        clearstatcache();
        foreach (scandir($uploadDir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..' && $entry !== '.htaccess' && is_file($uploadDir . '/' . $entry)) {
                throw new RuntimeException('Gagal mengosongkan berkas di folder uploads.');
            }
        }
    }

    $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    $pdo->exec('VACUUM');

    flash_set('Reset selesai');
    redirect('/admin/statistik');
}
