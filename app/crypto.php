<?php
declare(strict_types=1);

const TICKET_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

/** Kunci enkripsi dari config: base64 dari tepat 32 byte. */
function crypto_key(): string
{
    $key = base64_decode((string) config('encryption_key'), true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('encryption_key harus base64 dari tepat 32 byte.');
    }
    return $key;
}

/** Pepper HMAC kode tiket dari config. */
function crypto_pepper(): string
{
    $pepper = (string) config('pepper');
    if (strlen($pepper) < 32) {
        throw new RuntimeException('pepper minimal 32 karakter.');
    }
    return $pepper;
}

/** Enkripsi teks: base64(nonce acak + secretbox). Nonce baru setiap panggilan. */
function encrypt(string $plain): string
{
    $key   = crypto_key();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $box   = sodium_crypto_secretbox($plain, $nonce, $key);
    sodium_memzero($key);
    return base64_encode($nonce . $box);
}

/** Dekripsi hasil encrypt(). Melempar exception bila data rusak, dimanipulasi, atau kunci salah. */
function decrypt(string $cipher): string
{
    $raw = base64_decode($cipher, true);
    if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
        throw new RuntimeException('Ciphertext tidak valid.');
    }
    $key   = crypto_key();
    $plain = sodium_crypto_secretbox_open(
        substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        $key
    );
    sodium_memzero($key);
    if ($plain === false) {
        throw new RuntimeException('Dekripsi gagal.');
    }
    return $plain;
}

/** Normalisasi kode tiket: huruf besar, tanpa tanda hubung/spasi. */
function ticket_normalize(string $code): string
{
    return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
}

/**
 * Hash kode tiket (HMAC-SHA256 + pepper). Hanya hash ini yang disimpan.
 * Kode dinormalisasi dulu agar "abcd-efgh-jklm" dan "ABCDEFGHJKLM"
 * menghasilkan hash yang sama.
 */
function ticket_hash(string $code): string
{
    return hash_hmac('sha256', ticket_normalize($code), crypto_pepper());
}

/** Kode tiket acak kriptografis: XXXX-XXXX-XXXX dari alfabet tanpa karakter mirip. */
function generate_ticket(): string
{
    $max   = strlen(TICKET_ALPHABET) - 1;
    $chars = '';
    for ($i = 0; $i < 12; $i++) {
        $chars .= TICKET_ALPHABET[random_int(0, $max)];
    }
    return implode('-', str_split($chars, 4));
}

