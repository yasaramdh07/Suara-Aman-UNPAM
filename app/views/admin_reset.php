<h1 class="sa-title">Reset data sistem</h1>

<?php if ($error !== null): ?>
    <div class="sa-note sa-note-error" role="alert">
        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= e($error) ?>
    </div>
<?php endif; ?>

<div class="sa-danger-zone mb-4">
    <h2 class="h2 sa-danger-title mb-2">
        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Zona berbahaya: penghapusan menyeluruh
    </h2>
    <p class="sa-prose mb-2">Tindakan ini akan menghapus seluruh data operasional secara permanen dan tidak dapat dipulihkan:</p>
    <ul class="sa-prose mb-3">
        <li>Semua <strong>laporan</strong> yang pernah dikirim pelapor</li>
        <li>Semua <strong>pesan percakapan</strong> antara pelapor dan Guru BK</li>
        <li>Semua <strong>lampiran bukti</strong> di database beserta <strong>file fisik</strong> di folder <code class="sa-mono">data/uploads</code></li>
        <li>Semua <strong>catatan internal</strong> Guru BK</li>
        <li>Semua riwayat <strong>log akses</strong> (<code class="sa-mono">access_log</code>) dan data <strong>rate limit</strong> (<code class="sa-mono">rate_limits</code>)</li>
        <li>Daftar <strong>kategori kejadian</strong> dikembalikan ke 6 kategori bawaan (seed awal)</li>
    </ul>
    <p class="sa-prose mb-3">
        Akun <strong>Admin yang sedang login</strong> selalu dipertahankan agar kamu tidak terkunci dari sistem.
        Pencatatan bahwa sistem telah direset akan ditampilkan pada dashboard Guru BK.
    </p>
    <div>
        <a class="btn btn-outline-primary" href="/admin/backup">
            <i class="bi bi-download me-1" aria-hidden="true"></i>Unduh backup dulu
        </a>
    </div>
</div>

<section class="sa-panel sa-panel-narrow" aria-labelledby="reset-form-heading">
    <h2 class="h2 mb-3" id="reset-form-heading">Verifikasi berlapis</h2>

    <form method="post" action="/admin/reset" novalidate data-single-submit>
        <?= csrf_field() ?>

        <div class="sa-field">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="delete_bk" name="delete_bk" value="1"<?= !empty($deleteBk) ? ' checked' : '' ?>>
                <label class="form-check-label" for="delete_bk">Hapus juga akun guru BK</label>
            </div>
            <p class="sa-help">Jika dicentang, seluruh akun berperanan Guru BK akan dihapus (sesi mereka langsung dihentikan).</p>
        </div>

        <div class="sa-field">
            <label class="form-label" for="pin">PIN reset (6 digit)</label>
            <input type="password" id="pin" name="pin" class="form-control sa-input-short sa-mono"
                   inputmode="numeric" pattern="\d{6}" minlength="6" maxlength="6"
                   autocomplete="off" aria-describedby="help-pin" required>
            <p class="sa-help" id="help-pin">Masukkan 6 digit angka PIN reset instalasi.</p>
        </div>

        <div class="sa-field">
            <label class="form-label" for="math_answer"><?= e($mathQuestion) ?></label>
            <input type="text" id="math_answer" name="math_answer" class="form-control sa-input-short"
                   inputmode="numeric" pattern="\d{1,3}" maxlength="3"
                   autocomplete="off" aria-describedby="help-math" required>
            <p class="sa-help" id="help-math">Soal berlaku selama 5 menit dan berganti setiap percobaan.</p>
        </div>

        <div class="sa-field">
            <label class="form-label" for="confirm_text">Ketik <code class="sa-mono">HAPUS SEMUA</code> untuk konfirmasi</label>
            <input type="text" id="confirm_text" name="confirm_text" class="form-control sa-input-short sa-mono"
                   autocomplete="off" spellcheck="false" required>
        </div>

        <div class="sa-field">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="confirm_check" name="confirm_check" value="1" required>
                <label class="form-check-label" for="confirm_check">Saya mengerti ini tidak bisa dibatalkan</label>
            </div>
        </div>

        <div class="sa-actions">
            <button type="submit" class="btn sa-btn-danger">
                <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Reset seluruh data sekarang
            </button>
            <a class="sa-link" href="/admin/statistik">Batal</a>
        </div>
    </form>
</section>
