<p class="mb-2"><a href="/bk/laporan/<?= (int) $report['id'] ?>">&larr; Kembali ke detail laporan</a></p>

<h1 class="sa-title">Hapus permanen laporan <span class="sa-mono">#<?= (int) $report['id'] ?></span></h1>

<div class="sa-note sa-note-error" role="alert">
    <strong>Tindakan ini tidak dapat dibatalkan.</strong>
    Laporan #<?= (int) $report['id'] ?>, seluruh percakapan, catatan internal, dan file gambar bukti akan dihapus permanen dari sistem.
</div>

<?php if ($error !== null): ?>
    <div class="sa-note sa-note-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<dl class="sa-meta mb-4">
    <div><dt>ID laporan</dt><dd class="sa-mono">#<?= (int) $report['id'] ?></dd></div>
    <div><dt>Kategori</dt><dd><?= e($report['category']) ?></dd></div>
    <div><dt>Tanggal masuk</dt><dd><?= e(format_date_id((string) $report['created_at'])) ?></dd></div>
    <div><dt>Status</dt><dd><?= status_badge((string) $report['status']) ?></dd></div>
</dl>

<form method="post" action="/bk/laporan/<?= (int) $report['id'] ?>/hapus" class="sa-login-form" data-once novalidate>
    <?= csrf_field() ?>
    <div class="sa-field">
        <label class="form-label" for="confirm">Ketik <code class="sa-mono">hapus-permanen</code> untuk mengonfirmasi</label>
        <input type="text" id="confirm" name="confirm" class="form-control sa-mono" autocomplete="off" required>
    </div>
    <div class="sa-actions">
        <button type="submit" class="btn sa-btn-danger">Hapus permanen</button>
        <a class="btn btn-outline-primary" href="/bk/laporan/<?= (int) $report['id'] ?>">Batal</a>
    </div>
</form>
