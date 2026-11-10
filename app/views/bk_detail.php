<p class="mb-2"><a href="/bk/laporan"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke daftar laporan</a></p>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="sa-title mb-0">Laporan <span class="sa-mono">#<?= (int) $report['id'] ?></span></h1>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <?= urgency_badge((string) $report['urgency']) ?>
        <?= status_badge((string) $report['status']) ?>
    </div>
</div>

<?php if ($flash !== null): ?>
    <div class="sa-note" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($report['urgency'] === 'mendesak'): ?>
    <div class="sa-note sa-note-error">
        <strong>Laporan ini berstatus urgensi Mendesak.</strong>
        <?= (int) $report['urgency_auto_flag'] === 1
            ? 'Sistem menandai laporan ini secara otomatis karena memuat kata kunci risiko. Guru BK tetap menentukan keputusan akhir.'
            : 'Urgensi Mendesak ditetapkan oleh Guru BK.' ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-7">
        <section class="sa-panel mb-4" aria-labelledby="sec-detail">
            <h2 class="h2 mb-3" id="sec-detail">Rincian laporan</h2>
            <dl class="sa-meta mb-3">
                <div><dt>ID laporan</dt><dd class="sa-mono">#<?= (int) $report['id'] ?></dd></div>
                <div><dt>Tanggal masuk</dt><dd><?= e(format_datetime_id((string) $report['created_at'])) ?></dd></div>
                <div><dt>Kategori</dt><dd><?= e($report['category']) ?></dd></div>
                <div><dt>Peran pelapor</dt><dd><?= $report['reporter_role'] === 'korban' ? 'Korban' : 'Saksi' ?></dd></div>
                <div><dt>Waktu kejadian</dt><dd><?= e(format_datetime_id((string) $report['incident_date'])) ?></dd></div>
                <div><dt>Lokasi</dt><dd><?= e($report['location']) ?></dd></div>
                <?php if ($report['involved'] !== null && $report['involved'] !== ''): ?>
                    <div><dt>Pihak terlibat</dt><dd><?= e($report['involved']) ?></dd></div>
                <?php endif; ?>
            </dl>

            <h3 class="h3 mb-2">Uraian kejadian</h3>
            <div class="sa-report-body"><?= e($report['description']) ?></div>

            <?php if ($attachments !== []): ?>
                <h3 class="h3 mt-4 mb-2">Bukti gambar</h3>
                <div class="sa-attachments">
                    <?php foreach ($attachments as $i => $att): ?>
                        <a class="sa-attachment-link" href="/attachment.php?f=<?= e(urlencode((string) $att['stored_name'])) ?>" target="_blank" rel="noopener">
                            <img class="sa-attachment-thumb" src="/attachment.php?f=<?= e(urlencode((string) $att['stored_name'])) ?>" alt="Bukti gambar <?= $i + 1 ?>">
                            <span class="small d-block mt-1">Bukti <?= $i + 1 ?> (<?= (int) max(1, round(((int) $att['size']) / 1024)) ?> KB)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="sa-panel mb-4" aria-labelledby="sec-thread">
            <h2 class="h2 mb-2" id="sec-thread">Percakapan dengan pelapor</h2>
            <p class="small text-muted mb-3">Balasan di sini akan tampil saat pelapor mengecek status dengan kode tiketnya.</p>

            <?php if ($messages === []): ?>
                <p class="text-muted">Belum ada pesan.</p>
            <?php else: ?>
                <div class="sa-thread">
                    <?php foreach ($messages as $m): ?>
                        <?php $fromBk = $m['sender'] === 'bk'; ?>
                        <div class="sa-msg <?= $fromBk ? 'sa-msg-bk' : 'sa-msg-me' ?>">
                            <p class="sa-msg-who"><?= $fromBk ? 'Guru BK' : 'Pelapor' ?></p>
                            <p class="sa-msg-body"><?= e($m['body']) ?></p>
                            <p class="sa-msg-time"><?= e(format_datetime_id((string) $m['created_at'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/bk/laporan/<?= (int) $report['id'] ?>/balas" data-once>
                <?= csrf_field() ?>
                <div class="sa-field">
                    <label class="form-label" for="body">Balas pelapor</label>
                    <textarea class="form-control" id="body" name="body" rows="4" maxlength="<?= MESSAGE_MAX_CHARS ?>" aria-describedby="help-bk-body" required></textarea>
                    <p class="sa-help" id="help-bk-body">Maksimal 2.000 karakter.</p>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-send me-1" aria-hidden="true"></i>Kirim balasan
                </button>
            </form>
        </section>
    </div>

    <div class="col-12 col-lg-5">
        <section class="sa-panel mb-4" aria-labelledby="sec-status">
            <h2 class="h2 mb-3" id="sec-status">Status dan urgensi</h2>

            <form method="post" action="/bk/laporan/<?= (int) $report['id'] ?>/status" class="mb-3" data-once>
                <?= csrf_field() ?>
                <label class="form-label" for="status">Status laporan</label>
                <div class="d-flex gap-2">
                    <select id="status" name="status" class="form-select">
                        <?php foreach (REPORT_STATUSES as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= $report['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary">Simpan perubahan</button>
                </div>
            </form>

            <form method="post" action="/bk/laporan/<?= (int) $report['id'] ?>/urgensi" data-once>
                <?= csrf_field() ?>
                <label class="form-label" for="urgency">Tingkat urgensi</label>
                <div class="d-flex gap-2">
                    <select id="urgency" name="urgency" class="form-select">
                        <?php foreach (REPORT_URGENCIES as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= $report['urgency'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary">Simpan perubahan</button>
                </div>
            </form>
        </section>

        <section class="sa-panel mb-4" aria-labelledby="sec-notes">
            <h2 class="h2 mb-2" id="sec-notes">Catatan internal BK</h2>
            <p class="small text-muted mb-3">Catatan ini dienkripsi dan tidak pernah ditampilkan kepada pelapor.</p>

            <?php if ($notes === []): ?>
                <p class="text-muted">Belum ada catatan internal.</p>
            <?php else: ?>
                <div class="sa-notes mb-3">
                    <?php foreach ($notes as $n): ?>
                        <div class="sa-note-item">
                            <div class="sa-note-meta"><?= e($n['username']) ?> &middot; <?= e(format_datetime_id((string) $n['created_at'])) ?></div>
                            <div class="sa-note-body"><?= e($n['note']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/bk/laporan/<?= (int) $report['id'] ?>/catatan" data-once>
                <?= csrf_field() ?>
                <div class="sa-field">
                    <label class="form-label" for="note">Tambah catatan internal</label>
                    <textarea class="form-control" id="note" name="note" rows="3" maxlength="3000" required></textarea>
                </div>
                <button type="submit" class="btn btn-outline-primary">Simpan catatan</button>
            </form>
        </section>

        <?php if ($report['status'] === 'selesai'): ?>
            <section class="sa-panel" aria-labelledby="sec-delete">
                <h2 class="h3 mb-2" id="sec-delete">Hapus permanen</h2>
                <p class="small text-muted mb-3">Laporan berstatus Selesai dapat dihapus permanen beserta pesan, catatan internal, dan file bukti.</p>
                <a class="btn sa-btn-danger" href="/bk/laporan/<?= (int) $report['id'] ?>/hapus">
                    <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Hapus permanen laporan
                </a>
            </section>
        <?php endif; ?>
    </div>
</div>
