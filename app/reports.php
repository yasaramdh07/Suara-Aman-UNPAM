<?php
declare(strict_types=1);

const MESSAGE_MAX_CHARS = 2000;
const REPORT_STATUSES    = ['baru' => 'Baru', 'ditinjau' => 'Ditinjau', 'ditindaklanjuti' => 'Ditindaklanjuti', 'selesai' => 'Selesai', 'dieskalasi' => 'Dieskalasi'];
const REPORT_URGENCIES   = ['ringan' => 'Ringan', 'sedang' => 'Sedang', 'mendesak' => 'Mendesak'];

/** Kategori aktif untuk form. */
function report_categories(): array
{
    return db()->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY id')->fetchAll();
}

/** Bersihkan teks input: UTF-8 valid, baris baru seragam, tanpa karakter kontrol, tanpa spasi pinggir. */
function clean_text(mixed $value): string
{
    $s = is_string($value) && mb_check_encoding($value, 'UTF-8') ? $value : '';
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    return trim(preg_replace('/[^\P{C}\n\t]/u', '', $s) ?? '');
}

/**
 * Validasi sisi server untuk form laporan.
 * Return [data bersih (juga dipakai untuk mengisi ulang form), galat per kolom].
 */
function report_validate(array $in, array $categoryIds): array
{
    $d = [
        'peran'    => clean_text($in['peran'] ?? ''),
        'kategori' => clean_text($in['kategori'] ?? ''),
        'waktu'    => clean_text($in['waktu'] ?? ''),
        'lokasi'   => clean_text($in['lokasi'] ?? ''),
        'uraian'   => clean_text($in['uraian'] ?? ''),
        'terlibat' => clean_text($in['terlibat'] ?? ''),
    ];
    $errors = [];

    if (!in_array($d['peran'], ['korban', 'saksi'], true)) {
        $errors['peran'] = 'Pilih apakah kamu korban atau saksi.';
    }
    if (!ctype_digit($d['kategori']) || !in_array((int) $d['kategori'], array_map('intval', $categoryIds), true)) {
        $errors['kategori'] = 'Pilih jenis kejadian.';
    }

    $normalized = str_replace('T', ' ', $d['waktu']);
    $when       = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $normalized);
    if ($when === false || $when->format('Y-m-d H:i') !== $normalized) {
        $errors['waktu'] = 'Isi tanggal dan jam kejadian dengan benar.';
    } elseif ($when > new DateTimeImmutable('+1 hour')) {
        $errors['waktu'] = 'Waktu kejadian tidak boleh di masa depan.';
    } elseif ($when < new DateTimeImmutable('-10 years')) {
        $errors['waktu'] = 'Tanggal kejadian terlalu lama. Periksa kembali tahunnya.';
    } else {
        $d['incident'] = $normalized;
    }

    if ($d['lokasi'] === '') {
        $errors['lokasi'] = 'Isi lokasi kejadian.';
    } elseif (mb_strlen($d['lokasi']) > 200) {
        $errors['lokasi'] = 'Lokasi terlalu panjang (maksimal 200 karakter).';
    }

    $len = mb_strlen($d['uraian']);
    if ($len === 0) {
        $errors['uraian'] = 'Ceritakan apa yang terjadi.';
    } elseif ($len < 10) {
        $errors['uraian'] = 'Ceritanya terlalu singkat. Tulis minimal 10 karakter.';
    } elseif ($len > 5000) {
        $errors['uraian'] = "Cerita terlalu panjang ($len karakter). Maksimal 5.000 karakter.";
    }

    if (mb_strlen($d['terlibat']) > 1000) {
        $errors['terlibat'] = 'Terlalu panjang (maksimal 1.000 karakter).';
    }

    return [$d, $errors];
}

/** Ubah teks ke huruf kecil tanpa tanda baca, supaya "bunuh-diri" cocok dengan "bunuh diri". */
function risk_normalize(string $text): string
{
    $text = mb_strtolower($text);
    return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text));
}

/** Apakah ada kata kunci risiko di salah satu teks. Hanya usulan untuk BK. */
function risk_flagged(string ...$texts): bool
{
    static $keywords = null;
    $keywords ??= require BASE_PATH . '/app/risk_keywords.php';
    $haystack = risk_normalize(implode(' ', $texts));
    foreach ($keywords as $keyword) {
        if (str_contains($haystack, $keyword)) {
            return true;
        }
    }
    return false;
}

/**
 * Simpan laporan + lampiran dalam satu transaksi. Teks dienkripsi, hanya ticket_hash yang
 * disimpan. Tidak ada IP, user agent, atau data perangkat di sini.
 */
function report_create(array $d, array $attachments, string $ticket): int
{
    $pdo  = db();
    $now  = now_hour();
    $flag = risk_flagged($d['lokasi'], $d['uraian'], $d['terlibat']);

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO reports (ticket_hash, category_id, reporter_role, incident_date, location_enc,
                                  description_enc, involved_enc, urgency, urgency_auto_flag, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'baru\', ?, ?)'
        )->execute([
            ticket_hash($ticket),
            (int) $d['kategori'],
            $d['peran'],
            $d['incident'],
            encrypt($d['lokasi']),
            encrypt($d['uraian']),
            $d['terlibat'] === '' ? null : encrypt($d['terlibat']),
            $flag ? 'mendesak' : 'ringan',
            $flag ? 1 : 0,
            $now,
            $now,
        ]);
        $id = (int) $pdo->lastInsertId();

        $att = $pdo->prepare('INSERT INTO attachments (report_id, stored_name, mime, size, created_at) VALUES (?, ?, ?, ?, ?)');
        foreach ($attachments as $a) {
            $att->execute([$id, $a['stored_name'], $a['mime'], $a['size'], $now]);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Cari laporan dari kode tiket. Null untuk semua kegagalan (format salah atau tidak ada),
 * supaya pemanggil tidak bisa membedakannya. Tidak mengambil kolom *_enc.
 */
function report_lookup(string $code): ?array
{
    $normalized = ticket_normalize($code);
    if (preg_match('/^[' . TICKET_ALPHABET . ']{12}$/', $normalized) !== 1) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT r.id, r.status, r.created_at, c.name AS category
         FROM reports r JOIN categories c ON c.id = r.category_id
         WHERE r.ticket_hash = ?'
    );
    $stmt->execute([ticket_hash($normalized)]);
    return $stmt->fetch() ?: null;
}

/** Percakapan laporan (didekripsi), urut dari yang lama. */
function report_messages(int $reportId): array
{
    $stmt = db()->prepare('SELECT sender, body_enc, created_at FROM messages WHERE report_id = ? ORDER BY id');
    $stmt->execute([$reportId]);
    return array_map(
        static fn (array $m): array => ['sender' => $m['sender'], 'body' => decrypt($m['body_enc']), 'created_at' => $m['created_at']],
        $stmt->fetchAll()
    );
}

/** Tambah pesan ke percakapan ('pelapor' atau 'bk'). Isi dienkripsi. */
function report_add_message(int $reportId, string $sender, string $body): void
{
    $pdo = db();
    $now = now_hour();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO messages (report_id, sender, body_enc, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$reportId, $sender, encrypt($body), $now]);
        $pdo->prepare('UPDATE reports SET updated_at = ? WHERE id = ?')->execute([$now, $reportId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

