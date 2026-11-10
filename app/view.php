<?php
declare(strict_types=1);

/**
 * Tampilkan view di dalam layout. Nama view hanya dari kode (bukan input pengguna).
 * Variabel $data tersedia di view dan layout.
 */
function render(string $view, array $data = [], int $status = 200): void
{
    http_response_code($status);
    extract($data, EXTR_SKIP);
    ob_start();
    require BASE_PATH . '/app/views/' . $view . '.php';
    $content = (string) ob_get_clean();
    require BASE_PATH . '/app/views/layout.php';
}

/** Alihkan dengan 303 (setelah POST) lalu hentikan. */
function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

const MONTHS_ID      = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const MONTHS_FULL_ID = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function format_date_id(string $timestamp): string
{
    $t = strtotime($timestamp);
    return date('j', $t) . ' ' . MONTHS_ID[(int) date('n', $t)] . ' ' . date('Y', $t);
}

function format_datetime_id(string $timestamp): string
{
    return format_date_id($timestamp) . ', ' . date('H.i', strtotime($timestamp));
}

/** Badge status sesuai DESIGN.md (teks selalu ada, tidak hanya warna). */
function status_badge(string $status): string
{
    return '<span class="sa-badge sa-badge-' . e($status) . '">' . e(REPORT_STATUSES[$status] ?? $status) . '</span>';
}

/** Badge urgensi: Mendesak memakai ikon segitiga peringatan kecil (DESIGN.md bagian 6). */
function urgency_badge(string $urgency): string
{
    $icon = $urgency === 'mendesak'
        ? '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>'
        : '';
    return '<span class="sa-badge sa-urg-' . e($urgency) . '">' . $icon . e(REPORT_URGENCIES[$urgency] ?? $urgency) . '</span>';
}

/** Pilih nama kelas Bootstrap Icon (outline) yang sesuai untuk nama kategori kejadian. */
function category_icon(string $name): string
{
    $lower = mb_strtolower($name);
    if (str_contains($lower, 'fisik')) {
        return 'bi-exclamation-octagon';
    }
    if (str_contains($lower, 'verbal')) {
        return 'bi-chat-left-dots';
    }
    if (str_contains($lower, 'online') || str_contains($lower, 'siber') || str_contains($lower, 'cyber')) {
        return 'bi-phone';
    }
    if (str_contains($lower, 'kucil') || str_contains($lower, 'sosial')) {
        return 'bi-people';
    }
    if (str_contains($lower, 'peras') || str_contains($lower, 'palak')) {
        return 'bi-cash';
    }
    return 'bi-three-dots';
}

/** Render di layout staf (BK dan admin): header dengan menu dan tombol keluar. */
function render_staff(string $view, array $data = [], int $status = 200): void
{
    render($view, $data + ['layout' => 'staff'], $status);
}

/**
 * URL relatif logo Universitas Pamulang bila berkasnya ada di public/assets/img/,
 * atau null bila berkas tidak ditemukan agar halaman tidak menampilkan gambar rusak.
 */
function unpam_logo_url(): ?string
{
    foreach (['logo-unpam.png', 'logo unpam.png'] as $filename) {
        $path = BASE_PATH . '/public/assets/img/' . $filename;
        if (is_file($path) && filesize($path) > 0) {
            return '/assets/img/' . rawurlencode($filename);
        }
    }
    return null;
}

/** Pesan satu kali tampil (setelah redirect). */
function flash_set(string $message): void
{
    $_SESSION['flash'] = $message;
}

function flash_take(): ?string
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_string($message) ? $message : null;
}

function flash_set_error(string $message): void
{
    $_SESSION['flash_error'] = $message;
}

function flash_take_error(): ?string
{
    $message = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_error']);
    return is_string($message) ? $message : null;
}

/** Atribut ARIA untuk input: kaitkan teks bantuan dan pesan galat, tandai bila tidak valid. */
function field_attrs(array $errors, string $key, string $helpId = ''): string
{
    $ids = array_filter([$helpId, isset($errors[$key]) ? 'err-' . $key : '']);
    $out = $ids === [] ? '' : ' aria-describedby="' . e(implode(' ', $ids)) . '"';
    return isset($errors[$key]) ? $out . ' aria-invalid="true"' : $out;
}

/** Pesan galat di bawah input dengan ikon peringatan kecil (teks, bukan hanya warna). */
function field_error(array $errors, string $key): string
{
    return isset($errors[$key])
        ? '<p class="sa-field-error" id="err-' . e($key) . '"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>' . e($errors[$key]) . '</p>'
        : '';
}

/** Hentikan permintaan dengan halaman galat yang rapi. */
function abort(int $code, ?string $message = null): never
{
    $pages = [
        403 => ['Akses ditolak', 'Kamu tidak punya akses ke halaman ini.'],
        404 => ['Halaman tidak ditemukan', 'Alamat yang kamu buka tidak ada atau sudah dipindahkan.'],
        405 => ['Metode tidak diizinkan', 'Halaman ini tidak bisa dibuka dengan cara tersebut.'],
        413 => ['Unggahan terlalu besar', 'Ukuran total gambar melebihi batas. Kurangi jumlah atau kecilkan gambar, lalu coba lagi.'],
        500 => ['Terjadi kesalahan', 'Ada masalah di sisi kami. Coba lagi beberapa saat lagi.'],
    ];
    if (!isset($pages[$code])) {
        $code = 500;
    }
    [$title, $default] = $pages[$code];
    render('error', ['title' => $title, 'message' => $message ?? $default], $code);
    exit;
}

