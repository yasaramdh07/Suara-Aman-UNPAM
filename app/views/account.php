<h1 class="sa-title">Akun saya</h1>
<p class="sa-prose text-muted mb-4">
    Peran aktif: <strong><?= ($user['role'] ?? '') === 'admin' ? 'Admin' : 'Guru BK' ?></strong>.
    Kamu hanya dapat mengubah username dan kata sandi akunmu sendiri.
</p>

<?php if ($flash !== null): ?>
    <div class="sa-note" role="status">
        <i class="bi bi-check-circle me-1" aria-hidden="true"></i><?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <section class="sa-panel h-100" aria-labelledby="sec-chg-username">
            <h2 class="h2 mb-3" id="sec-chg-username">
                <i class="bi bi-person me-1" aria-hidden="true"></i>Ubah username
            </h2>

            <?php if ($usernameError !== null): ?>
                <div class="sa-note sa-note-error" role="alert">
                    <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i><?= e($usernameError) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/akun/username" data-once novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="username">

                <div class="sa-field">
                    <label class="form-label" for="acc-username">Username baru</label>
                    <input type="text" id="acc-username" name="username" class="form-control"
                           value="<?= e($newUsername) ?>" minlength="4" maxlength="32"
                           pattern="[a-zA-Z0-9_.\-]{4,32}" autocomplete="username"
                           aria-describedby="help-acc-username" required>
                    <p class="sa-help" id="help-acc-username">4–32 karakter; hanya huruf, angka, titik (.), garis bawah (_), atau strip (-).</p>
                </div>

                <div class="sa-field">
                    <label class="form-label" for="acc-user-curpw">Kata sandi saat ini</label>
                    <div class="input-group">
                        <input type="password" id="acc-user-curpw" name="current_password" class="form-control"
                               autocomplete="current-password" required>
                        <button type="button" class="btn btn-outline-primary sa-pw-toggle" data-pw-toggle="acc-user-curpw" aria-label="Tampilkan atau sembunyikan kata sandi">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2 me-1" aria-hidden="true"></i>Simpan perubahan
                </button>
            </form>
        </section>
    </div>

    <div class="col-12 col-lg-6">
        <section class="sa-panel h-100" aria-labelledby="sec-chg-password">
            <h2 class="h2 mb-3" id="sec-chg-password">
                <i class="bi bi-key me-1" aria-hidden="true"></i>Ubah kata sandi
            </h2>

            <?php if ($passwordError !== null): ?>
                <div class="sa-note sa-note-error" role="alert">
                    <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i><?= e($passwordError) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/akun/password" data-once novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">

                <div class="sa-field">
                    <label class="form-label" for="acc-pw-current">Kata sandi saat ini</label>
                    <div class="input-group">
                        <input type="password" id="acc-pw-current" name="current_password" class="form-control"
                               autocomplete="current-password" required>
                        <button type="button" class="btn btn-outline-primary sa-pw-toggle" data-pw-toggle="acc-pw-current" aria-label="Tampilkan atau sembunyikan kata sandi">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="sa-field">
                    <label class="form-label" for="acc-pw-new">Kata sandi baru</label>
                    <div class="input-group">
                        <input type="password" id="acc-pw-new" name="new_password" class="form-control"
                               minlength="10" maxlength="128" autocomplete="new-password"
                               data-pw-strength="pw-strength-text" aria-describedby="help-acc-pw pw-strength-text" required>
                        <button type="button" class="btn btn-outline-primary sa-pw-toggle" data-pw-toggle="acc-pw-new" aria-label="Tampilkan atau sembunyikan kata sandi">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <p class="sa-help" id="help-acc-pw">Minimal 10 karakter, tidak boleh sama dengan username atau kata sandi lama.</p>
                    <p class="sa-pw-meter small mb-0" id="pw-strength-text" aria-live="polite">Kekuatan sandi: gunakan minimal 10 karakter dengan kombinasi huruf besar, kecil, dan angka.</p>
                </div>

                <div class="sa-field">
                    <label class="form-label" for="acc-pw-confirm">Ulangi kata sandi baru</label>
                    <div class="input-group">
                        <input type="password" id="acc-pw-confirm" name="password_confirm" class="form-control"
                               minlength="10" maxlength="128" autocomplete="new-password" required>
                        <button type="button" class="btn btn-outline-primary sa-pw-toggle" data-pw-toggle="acc-pw-confirm" aria-label="Tampilkan atau sembunyikan kata sandi">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2 me-1" aria-hidden="true"></i>Simpan perubahan
                </button>
            </form>
        </section>
    </div>
</div>
