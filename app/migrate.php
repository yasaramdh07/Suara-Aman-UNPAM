<?php
declare(strict_types=1);

/**
 * Migrasi skema (PRD bagian 9). Idempoten: aman dijalankan berulang kali.
 * Kolom *_enc berisi ciphertext base64 (nonce + secretbox).
 *
 * Privasi: reports dan messages sengaja tidak punya kolom IP, user agent,
 * atau identitas perangkat. Waktu di kedua tabel itu diisi lewat now_hour().
 */
function migrate(PDO $pdo): void
{
    $pdo->beginTransaction();
    try {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL CHECK (role IN ('bk', 'admin')),
    is_active     INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    created_at    TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS categories (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    name      TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1))
);

-- AUTOINCREMENT: ID laporan yang dihapus tidak dipakai ulang (access_log merujuk ID).
CREATE TABLE IF NOT EXISTS reports (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_hash       TEXT    NOT NULL UNIQUE,
    category_id       INTEGER NOT NULL REFERENCES categories (id),
    reporter_role     TEXT    NOT NULL CHECK (reporter_role IN ('korban', 'saksi')),
    incident_date     TEXT    NOT NULL,
    location_enc      TEXT,
    description_enc   TEXT    NOT NULL,
    involved_enc      TEXT,
    urgency           TEXT    NOT NULL DEFAULT 'ringan'
                              CHECK (urgency IN ('ringan', 'sedang', 'mendesak')),
    urgency_auto_flag INTEGER NOT NULL DEFAULT 0 CHECK (urgency_auto_flag IN (0, 1)),
    status            TEXT    NOT NULL DEFAULT 'baru'
                              CHECK (status IN ('baru', 'ditinjau', 'ditindaklanjuti', 'selesai', 'dieskalasi')),
    created_at        TEXT    NOT NULL,
    updated_at        TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS messages (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id  INTEGER NOT NULL REFERENCES reports (id) ON DELETE CASCADE,
    sender     TEXT    NOT NULL CHECK (sender IN ('pelapor', 'bk')),
    body_enc   TEXT    NOT NULL,
    created_at TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS attachments (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id   INTEGER NOT NULL REFERENCES reports (id) ON DELETE CASCADE,
    stored_name TEXT    NOT NULL UNIQUE,
    mime        TEXT    NOT NULL,
    size        INTEGER NOT NULL CHECK (size > 0),
    created_at  TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS internal_notes (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id  INTEGER NOT NULL REFERENCES reports (id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users (id),
    note_enc   TEXT    NOT NULL,
    created_at TEXT    NOT NULL
);

-- Tanpa isi laporan. report_id sengaja tanpa foreign key agar log tetap ada
-- setelah laporan dihapus permanen.
CREATE TABLE IF NOT EXISTS access_log (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users (id),
    report_id  INTEGER,
    action     TEXT    NOT NULL,
    created_at TEXT    NOT NULL
);

-- Hanya hash bersalt harian, tidak pernah terhubung ke laporan. Dibersihkan otomatis.
CREATE TABLE IF NOT EXISTS rate_limits (
    key_hash   TEXT    NOT NULL,
    bucket     TEXT    NOT NULL,
    count      INTEGER NOT NULL DEFAULT 0,
    expires_at INTEGER NOT NULL,
    PRIMARY KEY (key_hash, bucket)
);

-- Catatan kejadian tingkat sistem (factory_reset, akun_diubah) tanpa data sensitif.
CREATE TABLE IF NOT EXISTS system_events (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    event      TEXT    NOT NULL,
    user_id    INTEGER,
    created_at TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_reports_status     ON reports (status);
CREATE INDEX IF NOT EXISTS idx_reports_urgency    ON reports (urgency);
CREATE INDEX IF NOT EXISTS idx_reports_created_at ON reports (created_at);
CREATE INDEX IF NOT EXISTS idx_reports_category   ON reports (category_id);
CREATE INDEX IF NOT EXISTS idx_messages_report    ON messages (report_id, id);
CREATE INDEX IF NOT EXISTS idx_attachments_report ON attachments (report_id);
CREATE INDEX IF NOT EXISTS idx_notes_report       ON internal_notes (report_id);
CREATE INDEX IF NOT EXISTS idx_access_log_report  ON access_log (report_id);
CREATE INDEX IF NOT EXISTS idx_access_log_user    ON access_log (user_id);
CREATE INDEX IF NOT EXISTS idx_rate_limits_expiry ON rate_limits (expires_at);
CREATE INDEX IF NOT EXISTS idx_system_events_ev   ON system_events (event, id);
SQL);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Kategori awal (PRD/Tahap 4). Idempoten: nama yang sudah ada dilewati. */
function seed_categories(PDO $pdo): void
{
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO categories (name) VALUES (?)');
    foreach (['Kekerasan fisik', 'Perundungan verbal', 'Perundungan online (siber)', 'Pengucilan', 'Pemerasan atau pemalakan', 'Lainnya'] as $name) {
        $stmt->execute([$name]);
    }
}

// Jalankan langsung dari terminal: php app/migrate.php
if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['argv'][0] ?? '')) === __FILE__) {
    require_once __DIR__ . '/bootstrap.php';
    migrate(db());
    seed_categories(db());
    echo "Migrasi selesai.\n";
}

