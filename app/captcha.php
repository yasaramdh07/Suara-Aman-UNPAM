<?php
declare(strict_types=1);

/** Pertanyaan hitung acak (tanpa layanan pihak ketiga). Jawaban hanya disimpan di session. */
function captcha_regenerate(): void
{
    $a = random_int(2, 9);
    $b = random_int(2, 9);
    if (random_int(0, 1) === 1) {
        $_SESSION['captcha'] = ['q' => "Berapa $a ditambah $b?", 'a' => $a + $b];
    } else {
        [$a, $b] = [max($a, $b), min($a, $b)];
        $_SESSION['captcha'] = ['q' => "Berapa $a dikurangi $b?", 'a' => $a - $b];
    }
}

/** Pertanyaan yang sedang berlaku (dibuat bila belum ada). */
function captcha_question(): string
{
    if (empty($_SESSION['captcha'])) {
        captcha_regenerate();
    }
    return $_SESSION['captcha']['q'];
}

/** Periksa jawaban. Pertanyaan selalu dibuang setelah dicek, jadi tiap pertanyaan hanya satu kesempatan. */
function captcha_check(mixed $input): bool
{
    $expected = $_SESSION['captcha']['a'] ?? null;
    unset($_SESSION['captcha']);
    return $expected !== null
        && is_string($input)
        && preg_match('/^\s*\d{1,3}\s*$/', $input) === 1
        && (int) trim($input) === $expected;
}

/**
 * Buat soal matematika acak untuk halaman factory reset admin:
 * penjumlahan atau pengurangan bilangan 1-20 (hasil tidak negatif),
 * disimpan di session dengan masa berlaku 5 menit (300 detik).
 */
function reset_math_generate(): string
{
    $a = random_int(1, 20);
    $b = random_int(1, 20);
    if (random_int(0, 1) === 1) {
        $question = "Berapa hasil $a + $b?";
        $answer   = $a + $b;
    } else {
        [$a, $b]  = [max($a, $b), min($a, $b)];
        $question = "Berapa hasil $a - $b?";
        $answer   = $a - $b;
    }
    $_SESSION['reset_math'] = [
        'q'   => $question,
        'a'   => $answer,
        'exp' => time() + 300,
    ];
    return $question;
}

/**
 * Periksa jawaban soal matematika reset admin.
 * Dipakai sekali (langsung dihapus dari session setelah diperiksa) dan kedaluwarsa setelah 5 menit.
 */
function reset_math_verify(mixed $input): bool
{
    $data = $_SESSION['reset_math'] ?? null;
    unset($_SESSION['reset_math']);
    if (!is_array($data) || !isset($data['a'], $data['exp'])) {
        return false;
    }
    if (time() > (int) $data['exp']) {
        return false;
    }
    return is_string($input)
        && preg_match('/^\s*\d{1,3}\s*$/', $input) === 1
        && (int) trim($input) === (int) $data['a'];
}


