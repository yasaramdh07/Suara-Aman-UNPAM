<?php if ($ticket !== null): ?>
    <div class="mb-3">
        <span class="sa-chip sa-chip-lime sa-chip-lg" aria-hidden="true">
            <i class="bi bi-check2-circle"></i>
        </span>
    </div>

    <h1 class="sa-title">Laporan terkirim</h1>
    <p class="sa-prose">Terima kasih sudah melapor. Ini kode tiket kamu. Pakai kode ini untuk melihat status dan membaca balasan guru BK.</p>

    <div class="ticket-box" id="ticket-code"><?= e($ticket) ?></div>

    <div class="sa-ticket-actions">
        <button class="btn btn-outline-primary" type="button" data-copy-target="ticket-code" data-copy-status="copy-status">
            <i class="bi bi-clipboard" aria-hidden="true" data-copy-icon></i>
            <span class="ms-2" data-copy-label>Salin kode</span>
        </button>
        <button class="btn btn-outline-primary" type="button" data-download-ticket="ticket-code">
            <i class="bi bi-download me-2" aria-hidden="true"></i>Unduh sebagai .txt
        </button>
        <span class="small text-muted" id="copy-status" role="status" aria-live="polite"></span>
    </div>

    <div class="sa-note sa-note-warn">
        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
        <strong>Simpan kode ini sekarang.</strong> Kode hanya tampil sekali dan tidak bisa dipulihkan. Bila hilang, kamu tidak bisa membuka laporan ini lagi.
    </div>

    <div class="sa-actions">
        <a class="btn btn-outline-primary" href="/status">
            <i class="bi bi-search me-2" aria-hidden="true"></i>Cek status laporan
        </a>
    </div>
<?php else: ?>
    <h1 class="sa-title">Kode tiket tidak ditampilkan lagi</h1>
    <p class="sa-prose">Kode tiket hanya muncul satu kali setelah laporan dikirim. Bila kamu belum menyimpannya, kode itu tidak bisa dipulihkan. Kamu bisa membuat laporan baru.</p>
    <div class="sa-actions">
        <a class="btn btn-primary" href="/lapor">
            <i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Buat laporan
        </a>
        <a class="sa-link" href="/status">
            <i class="bi bi-search me-1" aria-hidden="true"></i>Cek status laporan
        </a>
    </div>
<?php endif; ?>
