<?php
declare(strict_types=1);

const STATUS_BUCKET = 'status_fail';

function page_home(): void
{
    $edukasi = require BASE_PATH . '/app/content_edukasi.php';
    render('home', ['title' => 'Beranda', 'edukasi' => $edukasi]);
}

function page_privacy(): void
{
    render('privacy', ['title' => 'Privasi']);
}

function page_penyusun(): void
{
    $penyusun = require BASE_PATH . '/app/content_penyusun.php';
    render('penyusun', ['title' => $penyusun['judul'], 'penyusun' => $penyusun]);
}

function report_form_data(array $old = [], array $errors = []): array
{
    return [
        'title'      => 'Buat laporan',
        'categories' => report_categories(),
        'old'        => $old + ['peran' => '', 'kategori' => '', 'waktu' => '', 'lokasi' => '', 'uraian' => '', 'terlibat' => ''],
        'errors'     => $errors,
        'captcha'    => captcha_question(),
        'max_time'   => date('Y-m-d\TH:i'),
    ];
}

function page_report_form(): void
{
    render('report_form', report_form_data());
}

function page_report_submit(): void
{
    $categories = report_categories();
    [$data, $errors] = report_validate($_POST, array_column($categories, 'id'));
    [$files, $fileErrors] = upload_precheck(upload_collect('bukti'));
    if ($fileErrors !== []) {
        $errors['bukti'] = implode(' ', $fileErrors);
    }

    // CAPTCHA diperiksa terakhir: pertanyaan tidak terbuang bila isian lain masih salah.
    if ($errors === [] && !captcha_check($_POST['jawaban'] ?? null)) {
        $errors['jawaban'] = 'Jawaban salah. Coba jawab pertanyaan yang baru.';
    }

    // Decode gambar (mahal) hanya setelah semua pemeriksaan lolos.
    $stored = [];
    if ($errors === []) {
        foreach ($files as $i => $file) {
            $saved = upload_reencode($file['tmp_name'], $file['mime']);
            if ($saved === null) {
                upload_delete($stored);
                $errors['bukti'] = 'Gambar ' . ($i + 1) . ' tidak bisa diproses. Pastikan file adalah gambar yang utuh.';
                break;
            }
            $stored[] = $saved;
        }
    }

    if ($errors !== []) {
        render('report_form', report_form_data($data, $errors), 422);
        return;
    }

    $ticket = generate_ticket();
    try {
        report_create($data, $stored, $ticket);
    } catch (Throwable $e) {
        upload_delete($stored);
        throw $e;
    }

    $_SESSION['new_ticket'] = $ticket; // ditampilkan sekali di /lapor/sukses
    redirect('/lapor/sukses');
}

function page_report_success(): void
{
    $ticket = $_SESSION['new_ticket'] ?? null;
    unset($_SESSION['new_ticket']); // sekali tampil: hilang dari session setelah dibaca
    render('report_success', ['title' => 'Laporan terkirim', 'ticket' => is_string($ticket) ? $ticket : null]);
}

function page_status_form(): void
{
    render('status_form', ['title' => 'Cek status laporan', 'code' => '', 'error' => null]);
}

/** Cek kode tiket, tampilkan percakapan, dan terima balasan pelapor. */
function page_status_lookup(): void
{
    $code = mb_substr(clean_text($_POST['kode'] ?? ''), 0, 40);
    $form = ['title' => 'Cek status laporan', 'code' => $code, 'error' => null];

    // Sumber yang sudah diblokir tidak diperiksa kodenya sama sekali.
    if (rate_is_blocked(STATUS_BUCKET)) {
        $form['error'] = 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.';
        render('status_form', $form, 429);
        return;
    }

    $report = report_lookup($code);
    if ($report === null) {
        rate_register_failure(STATUS_BUCKET);
        $form['error'] = 'Kode tidak ditemukan. Periksa kembali kode kamu, lalu coba lagi.'; // sama untuk semua kegagalan
        render('status_form', $form);
        return;
    }

    $notice = $replyError = null;
    $draft  = '';
    if (($_POST['aksi'] ?? '') === 'balas') {
        $draft    = clean_text($_POST['pesan'] ?? '');
        $expected = (string) ($_SESSION['reply_nonce'] ?? '');
        if ($draft === '') {
            $replyError = 'Tulis pesan dulu.';
        } elseif (mb_strlen($draft) > MESSAGE_MAX_CHARS) {
            $replyError = 'Pesan terlalu panjang (maksimal 2.000 karakter).';
        } elseif ($expected === '' || !hash_equals($expected, (string) ($_POST['nonce'] ?? ''))) {
            // Kirim ulang form yang sama (refresh atau klik ganda): jangan simpan dua kali.
            $notice = 'Pesan tidak dikirim ulang. Periksa percakapan di bawah. Bila pesan kamu belum ada, kirim sekali lagi.';
            $draft  = '';
        } else {
            report_add_message((int) $report['id'], 'pelapor', $draft);
            $notice = 'Pesan terkirim.';
            $draft  = '';
        }
    }

    $_SESSION['reply_nonce'] = bin2hex(random_bytes(16));
    render('status_view', [
        'title'       => 'Status laporan',
        'report'      => $report,
        'code'        => implode('-', str_split(ticket_normalize($code), 4)),
        'messages'    => report_messages((int) $report['id']),
        'nonce'       => $_SESSION['reply_nonce'],
        'notice'      => $notice,
        'reply_error' => $replyError,
        'draft'       => $draft,
    ]);
}
