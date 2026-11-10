<?php
declare(strict_types=1);

const UPLOAD_MAX_FILES  = 3;
const UPLOAD_MAX_BYTES  = 5 * 1024 * 1024;
const UPLOAD_MAX_PIXELS = 16000000; // batas memori GD saat decode
const UPLOAD_TYPES      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

/** Kumpulkan file yang benar-benar dipilih dari input multiple ($_FILES[$field]). */
function upload_collect(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!is_array($f) || !is_array($f['name'] ?? null)) {
        return [];
    }
    $files = [];
    foreach (array_keys($f['name']) as $i) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $files[] = ['tmp_name' => (string) $f['tmp_name'][$i], 'error' => (int) $f['error'][$i], 'size' => (int) $f['size'][$i]];
    }
    return $files;
}

/**
 * Pemeriksaan murah sebelum CAPTCHA: jumlah, ukuran, MIME asli (finfo), dimensi.
 * Return [file valid beserta MIME, daftar pesan galat]. Nama file dari pengguna tidak dipakai.
 */
function upload_precheck(array $files): array
{
    if (count($files) > UPLOAD_MAX_FILES) {
        return [[], ['Maksimal ' . UPLOAD_MAX_FILES . ' gambar.']];
    }
    $finfo  = new finfo(FILEINFO_MIME_TYPE);
    $valid  = [];
    $errors = [];
    foreach ($files as $i => $file) {
        $label = 'Gambar ' . ($i + 1) . ': ';
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > UPLOAD_MAX_BYTES) {
            $errors[] = $label . 'ukuran lebih dari 5 MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = $label . 'gagal diunggah. Coba pilih ulang.';
        } else {
            $mime = (string) $finfo->file($file['tmp_name']);
            $info = isset(UPLOAD_TYPES[$mime]) ? @getimagesize($file['tmp_name']) : false;
            if ($info === false) {
                $errors[] = $label . 'bukan gambar JPG, PNG, atau WebP yang valid.';
            } elseif ($info[0] * $info[1] > UPLOAD_MAX_PIXELS) {
                $errors[] = $label . 'resolusi terlalu tinggi.';
            } else {
                $valid[] = ['tmp_name' => $file['tmp_name'], 'mime' => $mime];
            }
        }
    }
    return [$valid, $errors];
}

/**
 * Decode lalu encode ulang lewat GD. Hanya piksel yang dibawa ke file baru,
 * jadi EXIF, GPS, komentar, dan metadata lain ikut terbuang.
 * File disimpan di data/uploads (di luar public/) dengan nama acak.
 * Return ['stored_name', 'mime', 'size'] atau null bila gambar rusak.
 */
function upload_reencode(string $tmp, string $mime): ?array
{
    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png'  => @imagecreatefrompng($tmp),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        default      => false,
    };
    if ($img === false) {
        return null;
    }
    if ($mime === 'image/jpeg') {
        $img = upload_apply_orientation($img, $tmp);
    }

    ensure_dir(DATA_PATH . '/uploads');
    $name = bin2hex(random_bytes(16)) . '.' . UPLOAD_TYPES[$mime];
    $path = DATA_PATH . '/uploads/' . $name;

    if ($mime === 'image/png') {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $ok = imagepng($img, $path);
    } elseif ($mime === 'image/webp') {
        $ok = imagewebp($img, $path, 85);
    } else {
        $ok = imagejpeg($img, $path, 85);
    }
    if (!$ok) {
        @unlink($path);
        return null;
    }
    chmod($path, 0600);
    return ['stored_name' => $name, 'mime' => $mime, 'size' => (int) filesize($path)];
}

/** Putar gambar sesuai tag orientasi EXIF sebelum tag itu dibuang (bila ekstensi exif tersedia). */
function upload_apply_orientation(GdImage $img, string $tmp): GdImage
{
    if (!function_exists('exif_read_data')) {
        return $img;
    }
    $exif  = @exif_read_data($tmp);
    $angle = match ((int) ($exif['Orientation'] ?? 1)) {
        3 => 180,
        6 => -90,
        8 => 90,
        default => 0,
    };
    if ($angle === 0) {
        return $img;
    }
    return imagerotate($img, $angle, 0) ?: $img;
}

/** Hapus file hasil upload (dipakai saat proses dibatalkan). */
function upload_delete(array $stored): void
{
    foreach ($stored as $file) {
        @unlink(DATA_PATH . '/uploads/' . $file['stored_name']);
    }
}

