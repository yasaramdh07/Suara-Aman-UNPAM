<?php
declare(strict_types=1);

/** Halaman login untuk Guru BK dan Admin. */
function page_bk_login_form(): void
{
    $user = auth_user();
    if ($user !== null) {
        redirect($user['role'] === 'admin' ? '/admin/statistik' : '/bk/laporan');
    }
    render('bk_login', [
        'title'    => 'Masuk',
        'error'    => null,
        'username' => '',
    ]);
}

/** Pemrosesan form login BK dan Admin. */
function page_bk_login_submit(): void
{
    $username = clean_text($_POST['username'] ?? '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    $result = auth_attempt($username, $password);
    if (!$result['ok']) {
        render('bk_login', [
            'title'    => 'Masuk',
            'error'    => $result['error'],
            'username' => $username,
        ], $result['status'] ?? 200);
        return;
    }

    redirect($result['role'] === 'admin' ? '/admin/statistik' : '/bk/laporan');
}

/** Logout dengan CSRF (diverifikasi di Router). */
function page_bk_logout(): void
{
    auth_logout();
}

const ACCOUNT_CHANGE_BUCKET    = 'account_change';
const ACCOUNT_CHANGE_MAX_FAILS = 5;

/** Halaman "Akun saya" (/akun) untuk role bk dan admin. */
function page_account_form(array $state = [], int $status = 200): void
{
    $user = auth_require_staff();

    render_staff('account', [
        'title'         => 'Akun saya',
        'user'          => $user,
        'newUsername'   => $state['newUsername'] ?? $user['username'],
        'usernameError' => $state['usernameError'] ?? null,
        'passwordError' => $state['passwordError'] ?? null,
        'flash'         => flash_take(),
    ], $status);
}

/** Pemrosesan ubah username atau kata sandi di /akun (menerima aksi dari /akun, /akun/username, /akun/password). */
function page_account_submit(?string $forcedAction = null): void
{
    $user    = auth_require_staff();
    $uid     = (int) $user['id'];
    $action  = $forcedAction ?? clean_text($_POST['action'] ?? '');
    $rateKey = rate_user_key('account-user', $uid);

    if (rate_is_blocked(ACCOUNT_CHANGE_BUCKET, ACCOUNT_CHANGE_MAX_FAILS, $rateKey)) {
        $msg = 'Terlalu banyak percobaan yang gagal. Coba lagi dalam 15 menit.';
        page_account_form([
            'newUsername'   => clean_text($_POST['username'] ?? $user['username']),
            'usernameError' => $action === 'username' ? $msg : null,
            'passwordError' => $action === 'password' ? $msg : $msg,
        ], 429);
        return;
    }

    $stmt = db()->prepare('SELECT id, username, password_hash, role FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$uid]);
    $dbUser = $stmt->fetch();
    $stmt->closeCursor();
    if (!$dbUser) {
        auth_logout();
    }

    $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $currentOk       = $currentPassword !== '' && password_verify($currentPassword, (string) $dbUser['password_hash']);

    if ($action === 'username') {
        $newUsername = clean_text($_POST['username'] ?? '');

        if (!$currentOk) {
            rate_register_failure(ACCOUNT_CHANGE_BUCKET, $rateKey);
            $blocked = rate_is_blocked(ACCOUNT_CHANGE_BUCKET, ACCOUNT_CHANGE_MAX_FAILS, $rateKey);
            page_account_form([
                'newUsername'   => $newUsername,
                'usernameError' => $blocked
                    ? 'Terlalu banyak percobaan yang gagal. Coba lagi dalam 15 menit.'
                    : 'Kata sandi saat ini salah.',
            ], $blocked ? 429 : 422);
            return;
        }

        if (preg_match('/^[a-zA-Z0-9_.-]{4,32}$/', $newUsername) !== 1) {
            page_account_form([
                'newUsername'   => $newUsername,
                'usernameError' => 'Username baru harus 4–32 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.',
            ], 422);
            return;
        }

        $dup = db()->prepare('SELECT COUNT(*) FROM users WHERE username = ? COLLATE NOCASE AND id != ?');
        $dup->execute([$newUsername, $uid]);
        $isTaken = (int) $dup->fetchColumn() > 0;
        $dup->closeCursor();

        if ($isTaken) {
            page_account_form([
                'newUsername'   => $newUsername,
                'usernameError' => 'Username tersebut sudah digunakan. Pilih username lain.',
            ], 422);
            return;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$newUsername, $uid]);
            $pdo->prepare("INSERT INTO system_events (event, user_id, created_at) VALUES ('akun_diubah', ?, ?)")
                ->execute([$uid, now_full()]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        session_regenerate_id(true);
        $_SESSION['user']['username'] = $newUsername;
        flash_set('Perubahan disimpan');
        redirect('/akun');
    }

    if ($action === 'password') {
        $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $confirmPw   = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

        if (!$currentOk) {
            rate_register_failure(ACCOUNT_CHANGE_BUCKET, $rateKey);
            $blocked = rate_is_blocked(ACCOUNT_CHANGE_BUCKET, ACCOUNT_CHANGE_MAX_FAILS, $rateKey);
            page_account_form([
                'passwordError' => $blocked
                    ? 'Terlalu banyak percobaan yang gagal. Coba lagi dalam 15 menit.'
                    : 'Kata sandi saat ini salah.',
            ], $blocked ? 429 : 422);
            return;
        }

        if (mb_strlen($newPassword) < 10 || mb_strlen($newPassword) > 128) {
            page_account_form([
                'passwordError' => 'Kata sandi baru minimal 10 karakter.',
            ], 422);
            return;
        }

        if (mb_strtolower($newPassword) === mb_strtolower((string) $dbUser['username'])) {
            page_account_form([
                'passwordError' => 'Kata sandi baru tidak boleh sama dengan username.',
            ], 422);
            return;
        }

        if (password_verify($newPassword, (string) $dbUser['password_hash'])) {
            page_account_form([
                'passwordError' => 'Kata sandi baru tidak boleh sama dengan kata sandi lama.',
            ], 422);
            return;
        }

        if ($newPassword !== $confirmPw) {
            page_account_form([
                'passwordError' => 'Konfirmasi kata sandi baru tidak cocok.',
            ], 422);
            return;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($newPassword, PASSWORD_BCRYPT), $uid]);
            $pdo->prepare("INSERT INTO system_events (event, user_id, created_at) VALUES ('akun_diubah', ?, ?)")
                ->execute([$uid, now_full()]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        session_regenerate_id(true);
        flash_set('Perubahan disimpan');
        redirect('/akun');
    }

    abort(422, 'Permintaan tidak valid.');
}

function page_account_username(): void
{
    page_account_submit('username');
}

function page_account_password(): void
{
    page_account_submit('password');
}

/** Daftar laporan dengan filter status, urgensi, kategori, dan rentang tanggal. */
function page_bk_list(): void
{
    auth_require('bk');

    $filter = [
        'status'    => clean_text($_GET['status'] ?? ''),
        'urgency'   => clean_text($_GET['urgency'] ?? ($_GET['urgensi'] ?? '')),
        'category'  => clean_text($_GET['category'] ?? ($_GET['kategori'] ?? '')),
        'date_from' => clean_text($_GET['date_from'] ?? ($_GET['dari'] ?? '')),
        'date_to'   => clean_text($_GET['date_to'] ?? ($_GET['sampai'] ?? '')),
    ];

    $where = ['1=1'];
    $binds = [];

    if ($filter['status'] !== '' && isset(REPORT_STATUSES[$filter['status']])) {
        $where[] = 'r.status = ?';
        $binds[] = $filter['status'];
    } else {
        $filter['status'] = '';
    }

    if ($filter['urgency'] !== '' && isset(REPORT_URGENCIES[$filter['urgency']])) {
        $where[] = 'r.urgency = ?';
        $binds[] = $filter['urgency'];
    } else {
        $filter['urgency'] = '';
    }

    if ($filter['category'] !== '' && ctype_digit($filter['category'])) {
        $where[] = 'r.category_id = ?';
        $binds[] = (int) $filter['category'];
    } else {
        $filter['category'] = '';
    }

    if ($filter['date_from'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter['date_from']) === 1) {
        $where[] = 'r.created_at >= ?';
        $binds[] = $filter['date_from'] . ' 00:00:00';
    } else {
        $filter['date_from'] = '';
    }

    if ($filter['date_to'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter['date_to']) === 1) {
        $where[] = 'r.created_at <= ?';
        $binds[] = $filter['date_to'] . ' 23:59:59';
    } else {
        $filter['date_to'] = '';
    }

    // Urut default: Mendesak dulu, lalu Sedang, lalu Ringan, kemudian terbaru.
    $sql = "SELECT r.id, r.reporter_role, r.urgency, r.urgency_auto_flag, r.status, r.created_at,
                   c.name AS category
            FROM reports r
            JOIN categories c ON c.id = r.category_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY CASE r.urgency WHEN 'mendesak' THEN 3 WHEN 'sedang' THEN 2 ELSE 1 END DESC,
                     r.created_at DESC, r.id DESC
            LIMIT 200";
    $stmt = db()->prepare($sql);
    $stmt->execute($binds);
    $reports = $stmt->fetchAll();

    $categories = db()->query('SELECT id, name FROM categories ORDER BY id')->fetchAll();

    $resetStmt   = db()->query("SELECT created_at FROM system_events WHERE event = 'factory_reset' ORDER BY id DESC LIMIT 1");
    $lastResetAt = $resetStmt->fetchColumn();
    $resetStmt->closeCursor();

    render_staff('bk_list', [
        'title'       => 'Daftar laporan',
        'reports'     => $reports,
        'categories'  => $categories,
        'filter'      => $filter,
        'flash'       => flash_take(),
        'lastResetAt' => is_string($lastResetAt) && $lastResetAt !== '' ? $lastResetAt : null,
    ]);
}

/** Detail laporan: dekripsi saat ditampilkan, catat pembukaan di access_log. */
function page_bk_detail(array $params): void
{
    $user   = auth_require('bk');
    $id     = (int) ($params['id'] ?? 0);
    $report = bk_find_report($id);

    $report['location']    = $report['location_enc'] !== null ? decrypt((string) $report['location_enc']) : '';
    $report['description'] = decrypt((string) $report['description_enc']);
    $report['involved']    = $report['involved_enc'] !== null ? decrypt((string) $report['involved_enc']) : null;

    $attStmt = db()->prepare('SELECT id, stored_name, mime, size FROM attachments WHERE report_id = ? ORDER BY id');
    $attStmt->execute([$id]);
    $attachments = $attStmt->fetchAll();

    // Catat setiap pembukaan halaman detail ke access_log (tanpa isi).
    bk_log_access((int) $user['id'], $id, 'view');

    render_staff('bk_detail', [
        'title'       => 'Laporan #' . $id,
        'report'      => $report,
        'messages'    => report_messages($id),
        'notes'       => bk_report_notes($id),
        'attachments' => $attachments,
        'flash'       => flash_take(),
    ]);
}

/** Kirim balasan BK ke pelapor (dienkripsi). */
function page_bk_reply(array $params): void
{
    $user   = auth_require('bk');
    $id     = (int) ($params['id'] ?? 0);
    $report = bk_find_report($id);

    $body = clean_text($_POST['body'] ?? ($_POST['pesan'] ?? ''));
    if ($body === '') {
        flash_set('Tulis balasan terlebih dahulu.');
        redirect('/bk/laporan/' . $id);
    }
    if (mb_strlen($body) > MESSAGE_MAX_CHARS) {
        flash_set('Balasan terlalu panjang (maksimal ' . MESSAGE_MAX_CHARS . ' karakter).');
        redirect('/bk/laporan/' . $id);
    }
    if (bk_is_duplicate_submission('reply:' . $id . ':' . $body)) {
        flash_set('Balasan sudah terkirim.');
        redirect('/bk/laporan/' . $id);
    }

    report_add_message($id, 'bk', $body);

    if ($report['status'] === 'baru') {
        db()->prepare("UPDATE reports SET status = 'ditinjau', updated_at = ? WHERE id = ?")
            ->execute([now_hour(), $id]);
    }

    bk_log_access((int) $user['id'], $id, 'reply');
    flash_set('Balasan terkirim ke pelapor.');
    redirect('/bk/laporan/' . $id);
}

/** Ubah status laporan. */
function page_bk_update_status(array $params): void
{
    $user = auth_require('bk');
    $id   = (int) ($params['id'] ?? 0);
    bk_find_report($id);

    $status = clean_text($_POST['status'] ?? '');
    if (!isset(REPORT_STATUSES[$status])) {
        flash_set('Status tidak valid.');
        redirect('/bk/laporan/' . $id);
    }

    db()->prepare('UPDATE reports SET status = ?, updated_at = ? WHERE id = ?')
        ->execute([$status, now_hour(), $id]);
    bk_log_access((int) $user['id'], $id, 'status:' . $status);
    flash_set('Status laporan diperbarui menjadi ' . REPORT_STATUSES[$status] . '.');
    redirect('/bk/laporan/' . $id);
}

/** Ubah urgensi laporan. */
function page_bk_update_urgency(array $params): void
{
    $user = auth_require('bk');
    $id   = (int) ($params['id'] ?? 0);
    bk_find_report($id);

    $urgency = clean_text($_POST['urgency'] ?? '');
    if (!isset(REPORT_URGENCIES[$urgency])) {
        flash_set('Urgensi tidak valid.');
        redirect('/bk/laporan/' . $id);
    }

    db()->prepare('UPDATE reports SET urgency = ?, urgency_auto_flag = 0, updated_at = ? WHERE id = ?')
        ->execute([$urgency, now_hour(), $id]);
    bk_log_access((int) $user['id'], $id, 'urgency:' . $urgency);
    flash_set('Urgensi laporan diperbarui menjadi ' . REPORT_URGENCIES[$urgency] . '.');
    redirect('/bk/laporan/' . $id);
}

/** Tambah catatan internal terenkripsi. */
function page_bk_add_note(array $params): void
{
    $user = auth_require('bk');
    $id   = (int) ($params['id'] ?? 0);
    bk_find_report($id);

    $note = clean_text($_POST['note'] ?? ($_POST['catatan'] ?? ''));
    if ($note === '') {
        flash_set('Tulis isi catatan terlebih dahulu.');
        redirect('/bk/laporan/' . $id);
    }
    if (mb_strlen($note) > 3000) {
        flash_set('Catatan terlalu panjang (maksimal 3.000 karakter).');
        redirect('/bk/laporan/' . $id);
    }
    if (bk_is_duplicate_submission('note:' . $id . ':' . $note)) {
        flash_set('Catatan internal disimpan.');
        redirect('/bk/laporan/' . $id);
    }

    db()->prepare('INSERT INTO internal_notes (report_id, user_id, note_enc, created_at) VALUES (?, ?, ?, ?)')
        ->execute([$id, (int) $user['id'], encrypt($note), now_full()]);
    bk_log_access((int) $user['id'], $id, 'note');
    flash_set('Catatan internal disimpan.');
    redirect('/bk/laporan/' . $id);
}

/** Sajikan gambar bukti hanya untuk BK yang sudah login. */
function page_bk_attachment(array $params): void
{
    auth_require('bk');
    $attId = (int) ($params['id'] ?? 0);
    $stmt  = db()->prepare('SELECT stored_name, mime FROM attachments WHERE id = ?');
    $stmt->execute([$attId]);
    $att = $stmt->fetch();
    if (!$att) {
        abort(404);
    }
    bk_serve_upload_file((string) $att['stored_name']);
}

/** Sajikan file dari data/uploads dengan pemeriksaan nama file ketat. */
function bk_serve_upload_file(string $storedName): never
{
    $name = basename($storedName);
    if (preg_match('/^[0-9a-f]{32}\.(jpg|png|webp)$/', $name) !== 1) {
        abort(404);
    }
    $path = DATA_PATH . '/uploads/' . $name;
    if (!is_file($path)) {
        abort(404);
    }
    $ext  = pathinfo($name, PATHINFO_EXTENSION);
    $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: inline; filename="' . $name . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

/** Langkah 1 hapus permanen: halaman konfirmasi khusus (hanya bila status Selesai). */
function page_bk_delete_confirm(array $params): void
{
    auth_require('bk');
    $id     = (int) ($params['id'] ?? 0);
    $report = bk_find_report($id);

    if ($report['status'] !== 'selesai') {
        flash_set('Hanya laporan berstatus Selesai yang dapat dihapus permanen.');
        redirect('/bk/laporan/' . $id);
    }

    render_staff('bk_delete_confirm', [
        'title'  => 'Hapus laporan #' . $id,
        'report' => $report,
        'error'  => null,
    ]);
}

/** Langkah 2 hapus permanen: verifikasi konfirmasi, hapus baris dan file fisik. */
function page_bk_delete_execute(array $params): void
{
    $user   = auth_require('bk');
    $id     = (int) ($params['id'] ?? 0);
    $report = bk_find_report($id);

    if ($report['status'] !== 'selesai') {
        abort(403, 'Hanya laporan berstatus Selesai yang dapat dihapus permanen.');
    }

    $confirm = clean_text($_POST['confirm'] ?? '');
    if ($confirm !== 'hapus-permanen') {
        render_staff('bk_delete_confirm', [
            'title'  => 'Hapus laporan #' . $id,
            'report' => $report,
            'error'  => 'Ketik tepat "hapus-permanen" untuk mengonfirmasi penghapusan.',
        ], 422);
        return;
    }

    $attStmt = db()->prepare('SELECT stored_name FROM attachments WHERE report_id = ?');
    $attStmt->execute([$id]);
    $files = $attStmt->fetchAll();

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM internal_notes WHERE report_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM messages WHERE report_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM attachments WHERE report_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM reports WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    upload_delete($files);
    bk_log_access((int) $user['id'], $id, 'delete');
    flash_set('Laporan #' . $id . ' beserta pesan, catatan, dan file bukti telah dihapus permanen.');
    redirect('/bk/laporan');
}

/** Rekap bulanan: per kategori, per status, dan rata-rata waktu respons pertama. */
function page_bk_rekap(): void
{
    auth_require('bk');

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

    $categories = db()->query('SELECT id, name FROM categories ORDER BY id')->fetchAll();

    $byCatStmt = db()->prepare(
        "SELECT CAST(strftime('%m', r.created_at) AS INTEGER) AS bulan,
                r.category_id, COUNT(*) AS jumlah
         FROM reports r
         WHERE strftime('%Y', r.created_at) = ?
         GROUP BY bulan, r.category_id"
    );
    $byCatStmt->execute([(string) $year]);
    $byCat = $byCatStmt->fetchAll();

    $byStatusStmt = db()->prepare(
        "SELECT CAST(strftime('%m', r.created_at) AS INTEGER) AS bulan,
                r.status, COUNT(*) AS jumlah
         FROM reports r
         WHERE strftime('%Y', r.created_at) = ?
         GROUP BY bulan, r.status"
    );
    $byStatusStmt->execute([(string) $year]);
    $byStatus = $byStatusStmt->fetchAll();

    $avgStmt = db()->prepare(
        "SELECT CAST(strftime('%m', r.created_at) AS INTEGER) AS bulan,
                COUNT(*) AS terjawab,
                AVG(MAX(0, (julianday(m.first_reply) - julianday(r.created_at)) * 24.0)) AS avg_jam
         FROM reports r
         JOIN (
             SELECT report_id, MIN(created_at) AS first_reply
             FROM messages
             WHERE sender = 'bk'
             GROUP BY report_id
         ) m ON m.report_id = r.id
         WHERE strftime('%Y', r.created_at) = ?
         GROUP BY bulan"
    );
    $avgStmt->execute([(string) $year]);
    $avgReply = $avgStmt->fetchAll();

    render_staff('bk_rekap', [
        'title'      => 'Rekap bulanan',
        'year'       => $year,
        'years'      => $years,
        'categories' => $categories,
        'byCat'      => $byCat,
        'byStatus'   => $byStatus,
        'avgReply'   => $avgReply,
    ]);
}

/** Ambil satu laporan atau hentikan dengan 404. */
function bk_find_report(int $id): array
{
    if ($id <= 0) {
        abort(404);
    }
    $stmt = db()->prepare(
        'SELECT r.id, r.status, r.urgency, r.urgency_auto_flag, r.reporter_role,
                r.incident_date, r.location_enc, r.description_enc, r.involved_enc,
                r.created_at, r.updated_at,
                c.name AS category, c.id AS category_id
         FROM reports r
         JOIN categories c ON c.id = r.category_id
         WHERE r.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        abort(404);
    }
    return $row;
}

/** Ambil daftar catatan internal laporan (didekripsi). */
function bk_report_notes(int $reportId): array
{
    $stmt = db()->prepare(
        'SELECT n.id, n.note_enc, n.created_at, u.username
         FROM internal_notes n
         JOIN users u ON u.id = n.user_id
         WHERE n.report_id = ?
         ORDER BY n.id'
    );
    $stmt->execute([$reportId]);
    return array_map(
        static fn (array $n): array => [
            'id'         => (int) $n['id'],
            'note'       => decrypt((string) $n['note_enc']),
            'created_at' => (string) $n['created_at'],
            'username'   => (string) $n['username'],
        ],
        $stmt->fetchAll()
    );
}

/** Catat akses BK ke access_log (tanpa isi laporan). */
function bk_log_access(int $userId, ?int $reportId, string $action): void
{
    db()->prepare('INSERT INTO access_log (user_id, report_id, action, created_at) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $reportId, $action, now_full()]);
}

/** Cegah pengiriman ganda (klik dua kali) dalam 5 detik untuk aksi yang identik. */
function bk_is_duplicate_submission(string $payload): bool
{
    $sig  = hash('sha256', $payload);
    $prev = $_SESSION['last_bk_post'] ?? null;
    $now  = time();
    if (is_array($prev) && ($prev['sig'] ?? '') === $sig && ($now - (int) ($prev['ts'] ?? 0)) <= 5) {
        return true;
    }
    $_SESSION['last_bk_post'] = ['sig' => $sig, 'ts' => $now];
    return false;
}
