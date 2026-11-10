<h1 class="sa-title">Masuk</h1>
<p class="sa-prose text-muted">Halaman khusus Guru BK dan pengelola sistem MA YABIKA.</p>

<?php if ($error !== null): ?>
    <div class="sa-note sa-note-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="/bk/login" class="sa-login-form" data-once novalidate>
    <?= csrf_field() ?>

    <div class="sa-field">
        <label class="form-label" for="username">Username</label>
        <input class="form-control" type="text" id="username" name="username"
               value="<?= e($username) ?>" maxlength="60" autocomplete="username" required autofocus>
    </div>

    <div class="sa-field">
        <label class="form-label" for="password">Kata sandi</label>
        <input class="form-control" type="password" id="password" name="password"
               autocomplete="current-password" required>
    </div>

    <div class="sa-actions">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>Masuk
        </button>
        <a class="sa-link" href="/">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke beranda
        </a>
    </div>
</form>
