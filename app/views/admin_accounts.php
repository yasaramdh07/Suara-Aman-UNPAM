<h1 class="sa-title">Kelola akun</h1>
<p class="sa-prose text-muted mb-4">Buat akun Guru BK baru, atur status aktif, atau atur ulang kata sandi.</p>

<?php if ($flash !== null): ?>
    <div class="sa-note" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($error !== null): ?>
    <div class="sa-note sa-note-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <section class="sa-panel" aria-labelledby="sec-new-user">
            <h2 class="h2 mb-3" id="sec-new-user">Buat akun baru</h2>
            <form method="post" action="/admin/akun/buat" data-once novalidate>
                <?= csrf_field() ?>
                <div class="sa-field">
                    <label class="form-label" for="new-username">Username</label>
                    <input type="text" id="new-username" name="username" class="form-control"
                           minlength="3" maxlength="32" autocomplete="off" aria-describedby="help-new-user" required>
                    <p class="sa-help" id="help-new-user">3–32 karakter (huruf, angka, titik, garis bawah, tanda hubung).</p>
                </div>
                <div class="sa-field">
                    <label class="form-label" for="new-password">Kata sandi awal</label>
                    <input type="password" id="new-password" name="password" class="form-control"
                           minlength="8" maxlength="128" autocomplete="new-password" aria-describedby="help-new-pw" required>
                    <p class="sa-help" id="help-new-pw">Minimal 8 karakter.</p>
                </div>
                <div class="sa-field">
                    <label class="form-label" for="new-role">Peran</label>
                    <select id="new-role" name="role" class="form-select">
                        <option value="bk">Guru BK</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Buat akun</button>
            </form>
        </section>
    </div>

    <div class="col-12 col-lg-8">
        <section aria-labelledby="sec-user-list">
            <h2 class="h2 mb-3" id="sec-user-list">Daftar akun</h2>
            <div class="sa-table-wrap">
                <table class="table mb-0 sa-table">
                    <thead>
                        <tr>
                            <th scope="col">Username</th>
                            <th scope="col">Peran</th>
                            <th scope="col">Status</th>
                            <th scope="col">Reset kata sandi</th>
                            <th scope="col">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <?php
                                $uid    = (int) $u['id'];
                                $active = (int) $u['is_active'] === 1;
                                $isSelf = $uid === (int) $current['id'];
                            ?>
                            <tr>
                                <td>
                                    <span class="fw-semibold"><?= e($u['username']) ?></span>
                                    <?php if ($isSelf): ?>
                                        <span class="small text-muted">(kamu)</span>
                                    <?php endif; ?>
                                    <div class="small text-muted">Dibuat <?= e(format_date_id((string) $u['created_at'])) ?></div>
                                </td>
                                <td><?= $u['role'] === 'admin' ? 'Admin' : 'Guru BK' ?></td>
                                <td>
                                    <span class="sa-badge <?= $active ? 'sa-badge-selesai' : 'sa-badge-dieskalasi' ?>">
                                        <?= $active ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" action="/admin/akun/<?= $uid ?>/password" class="d-flex gap-2 align-items-center" data-once>
                                        <?= csrf_field() ?>
                                        <label class="visually-hidden" for="pw-<?= $uid ?>">Kata sandi baru untuk <?= e($u['username']) ?></label>
                                        <input type="password" id="pw-<?= $uid ?>" name="password" class="form-control sa-input-short"
                                               placeholder="Sandi baru" minlength="8" maxlength="128" autocomplete="new-password" required>
                                        <button type="submit" class="btn btn-outline-primary sa-btn-compact">Simpan perubahan</button>
                                    </form>
                                </td>
                                <td>
                                    <?php if (!$isSelf): ?>
                                        <form method="post" action="/admin/akun/<?= $uid ?>/status" class="d-inline" data-once>
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn <?= $active ? 'sa-btn-danger' : 'btn-outline-primary' ?> sa-btn-compact">
                                                <?= $active ? 'Nonaktifkan' : 'Aktifkan' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

