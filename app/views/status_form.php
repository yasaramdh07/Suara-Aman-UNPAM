<h1 class="sa-title">Cek status laporan</h1>
<p class="sa-prose">Masukkan kode tiket yang kamu dapat setelah mengirim laporan.</p>

<?php if ($error !== null): ?>
    <div class="sa-note sa-note-error" role="alert">
        <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i><?= e($error) ?>
    </div>
<?php endif; ?>

<form method="post" action="/status" data-once>
    <?= csrf_field() ?>
    <div class="sa-field">
        <label class="form-label" for="f-kode">Kode tiket</label>
        <div class="input-group sa-ticket-group">
            <span class="input-group-text sa-input-addon" aria-hidden="true">
                <i class="bi bi-key"></i>
            </span>
            <input class="form-control sa-ticket-input" type="text" id="f-kode" name="kode" value="<?= e($code) ?>" maxlength="40" placeholder="XXXX-XXXX-XXXX" autocomplete="off" autocapitalize="characters" spellcheck="false" required aria-describedby="help-kode">
        </div>
        <p class="sa-help" id="help-kode">Kode terdiri dari 12 huruf dan angka, misalnya ABCD-EFGH-JKLM.</p>
    </div>
    <button class="btn btn-primary" type="submit" data-loading-text="Memeriksa...">
        <i class="bi bi-search me-2" aria-hidden="true" data-submit-icon></i>
        <span data-submit-label>Lihat status</span>
    </button>
</form>
