<h1 class="sa-title">Backup database</h1>

<div class="sa-panel sa-prose">
    <h2 class="h2 mb-2">Unduh salinan database</h2>
    <p>Berkas database SQLite memuat seluruh data aplikasi. Seluruh isi laporan, lokasi, pihak terlibat, percakapan, dan catatan internal di dalam berkas ini sudah terenkripsi dengan <code class="sa-mono">sodium_crypto_secretbox</code>.</p>
    <p class="small text-muted">Ukuran database saat ini: <strong class="sa-mono"><?= (int) max(1, round($dbSize / 1024)) ?> KB</strong></p>

    <div class="sa-note sa-note-warn">
        <strong>Penting:</strong> Tanpa kunci enkripsi (<code class="sa-mono">encryption_key</code> di dalam <code class="sa-mono">config.php</code>), isi laporan di dalam cadangan database ini tidak dapat didekripsi. Simpan cadangan <code class="sa-mono">config.php</code> di tempat terpisah yang aman.
    </div>

    <form method="post" action="/admin/backup/unduh">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-download me-2" aria-hidden="true"></i>Unduh database (.sqlite)
        </button>
    </form>
</div>

