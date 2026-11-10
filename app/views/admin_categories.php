<h1 class="sa-title">Kelola kategori</h1>
<p class="sa-prose text-muted mb-4">Kategori yang aktif akan ditampilkan sebagai pilihan jenis kejadian pada formulir pelapor.</p>

<?php if ($flash !== null): ?>
    <div class="sa-note" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($error !== null): ?>
    <div class="sa-note sa-note-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <section class="sa-panel" aria-labelledby="sec-new-cat">
            <h2 class="h2 mb-3" id="sec-new-cat">Tambah kategori</h2>
            <form method="post" action="/admin/kategori/tambah" data-once novalidate>
                <?= csrf_field() ?>
                <div class="sa-field">
                    <label class="form-label" for="new-cat-name">Nama kategori</label>
                    <input type="text" id="new-cat-name" name="name" class="form-control"
                           minlength="2" maxlength="80" required>
                </div>
                <button type="submit" class="btn btn-primary">Tambah kategori</button>
            </form>
        </section>
    </div>

    <div class="col-12 col-lg-8">
        <section aria-labelledby="sec-cat-list">
            <h2 class="h2 mb-3" id="sec-cat-list">Daftar kategori</h2>
            <div class="sa-table-wrap">
                <table class="table mb-0 sa-table">
                    <thead>
                        <tr>
                            <th scope="col">Ubah nama kategori</th>
                            <th scope="col">Laporan</th>
                            <th scope="col">Status</th>
                            <th scope="col">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <?php
                                $cid    = (int) $cat['id'];
                                $active = (int) $cat['is_active'] === 1;
                            ?>
                            <tr>
                                <td>
                                    <form method="post" action="/admin/kategori/<?= $cid ?>/ubah" class="d-flex gap-2 align-items-center" data-once>
                                        <?= csrf_field() ?>
                                        <label class="visually-hidden" for="cat-<?= $cid ?>">Nama kategori <?= $cid ?></label>
                                        <input type="text" id="cat-<?= $cid ?>" name="name" class="form-control"
                                               value="<?= e($cat['name']) ?>" minlength="2" maxlength="80" required>
                                        <button type="submit" class="btn btn-outline-primary sa-btn-compact">Simpan perubahan</button>
                                    </form>
                                </td>
                                <td class="sa-mono"><?= (int) $cat['report_count'] ?></td>
                                <td>
                                    <span class="sa-badge <?= $active ? 'sa-badge-selesai' : 'sa-badge-dieskalasi' ?>">
                                        <?= $active ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" action="/admin/kategori/<?= $cid ?>/status" class="d-inline" data-once>
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn <?= $active ? 'sa-btn-danger' : 'btn-outline-primary' ?> sa-btn-compact">
                                            <?= $active ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

