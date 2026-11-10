<h1 class="sa-title">Buat laporan</h1>

<div class="sa-privacy-box mb-4">
    <span class="sa-chip" aria-hidden="true"><i class="bi bi-lock"></i></span>
    <div>
        <strong class="d-block">Privasi kamu terlindungi</strong>
        <span>Kami tidak menyimpan nama, alamat IP, atau data perangkat kamu. Hanya guru BK yang bisa membaca laporan ini.</span>
    </div>
</div>

<?php if ($errors !== []): ?>
    <div class="sa-note sa-note-error" role="alert">
        <strong><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Laporan belum terkirim.</strong> Periksa isian berikut:
        <ul class="mb-0">
            <?php foreach ($errors as $key => $message): ?>
                <li><a href="#f-<?= e($key) ?>"><?= e($message) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($categories === []): ?>
    <div class="sa-note sa-note-warn">Form belum bisa dipakai karena jenis kejadian belum diatur. Hubungi guru BK.</div>
<?php else: ?>
<form method="post" action="/lapor" enctype="multipart/form-data" data-once>
    <?= csrf_field() ?>

    <section class="sa-form-section" aria-labelledby="sec-step-1">
        <div class="sa-form-step-head">
            <span class="sa-chip sa-chip-num" aria-hidden="true">1</span>
            <h2 class="h2 mb-0" id="sec-step-1">Kejadian</h2>
        </div>

        <fieldset class="sa-field"<?= field_attrs($errors, 'peran') ?>>
            <legend class="form-label" id="f-peran">Kamu melapor sebagai</legend>
            <div class="sa-segmented" role="radiogroup" aria-labelledby="f-peran">
                <label class="sa-seg-option">
                    <input type="radio" name="peran" value="korban" required <?= $old['peran'] === 'korban' ? 'checked' : '' ?>>
                    <i class="bi bi-person" aria-hidden="true"></i>
                    <span>Korban</span>
                </label>
                <label class="sa-seg-option">
                    <input type="radio" name="peran" value="saksi" required <?= $old['peran'] === 'saksi' ? 'checked' : '' ?>>
                    <i class="bi bi-eye" aria-hidden="true"></i>
                    <span>Saksi</span>
                </label>
            </div>
            <?= field_error($errors, 'peran') ?>
        </fieldset>

        <fieldset class="sa-field" id="f-kategori"<?= field_attrs($errors, 'kategori') ?>>
            <legend class="form-label">Jenis kejadian</legend>
            <div class="sa-cat-grid" role="radiogroup" aria-label="Jenis kejadian">
                <?php foreach ($categories as $c): ?>
                    <?php $cid = (string) $c['id']; ?>
                    <label class="sa-cat-card">
                        <input type="radio" name="kategori" value="<?= e($cid) ?>" required <?= $cid === (string) $old['kategori'] ? 'checked' : '' ?>>
                        <span class="sa-chip" aria-hidden="true"><i class="bi <?= e(category_icon((string) $c['name'])) ?>"></i></span>
                        <span class="sa-cat-label"><?= e($c['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?= field_error($errors, 'kategori') ?>
        </fieldset>

        <div class="sa-field">
            <label class="form-label" for="f-waktu">Kapan kejadiannya</label>
            <input class="form-control" type="datetime-local" id="f-waktu" name="waktu" max="<?= e($max_time) ?>" value="<?= e($old['waktu']) ?>" required<?= field_attrs($errors, 'waktu', 'help-waktu') ?>>
            <p class="sa-help" id="help-waktu">Perkiraan waktu diperbolehkan.</p>
            <?= field_error($errors, 'waktu') ?>
        </div>

        <div class="sa-field mb-0">
            <label class="form-label" for="f-lokasi">Di mana kejadiannya</label>
            <input class="form-control" type="text" id="f-lokasi" name="lokasi" maxlength="200" value="<?= e($old['lokasi']) ?>" required<?= field_attrs($errors, 'lokasi', 'help-lokasi') ?>>
            <p class="sa-help" id="help-lokasi">Contoh: kantin, kelas XI-A, koridor lantai 2, atau grup WhatsApp.</p>
            <?= field_error($errors, 'lokasi') ?>
        </div>
    </section>

    <section class="sa-form-section" aria-labelledby="sec-step-2">
        <div class="sa-form-step-head">
            <span class="sa-chip sa-chip-num" aria-hidden="true">2</span>
            <h2 class="h2 mb-0" id="sec-step-2">Cerita</h2>
        </div>

        <div class="sa-field">
            <div class="d-flex justify-content-between align-items-baseline gap-2">
                <label class="form-label" for="f-uraian">Ceritakan apa yang terjadi</label>
                <span class="small text-muted sa-mono" id="uraian-counter" aria-live="polite">0 / 5000</span>
            </div>
            <textarea class="form-control" id="f-uraian" name="uraian" rows="7" maxlength="5000" data-char-counter="uraian-counter" required<?= field_attrs($errors, 'uraian', 'help-uraian') ?>><?= e($old['uraian']) ?></textarea>
            <p class="sa-help" id="help-uraian">Tulis apa adanya. Maksimal 5.000 karakter.</p>
            <?= field_error($errors, 'uraian') ?>
        </div>

        <div class="sa-field mb-0">
            <label class="form-label" for="f-terlibat">Pihak yang terlibat (boleh dikosongkan)</label>
            <textarea class="form-control" id="f-terlibat" name="terlibat" rows="3" maxlength="1000"<?= field_attrs($errors, 'terlibat', 'help-terlibat') ?>><?= e($old['terlibat']) ?></textarea>
            <p class="sa-help" id="help-terlibat">Nama atau ciri-ciri pelaku, korban, atau saksi lain.</p>
            <?= field_error($errors, 'terlibat') ?>
        </div>
    </section>

    <section class="sa-form-section" aria-labelledby="sec-step-3">
        <div class="sa-form-step-head">
            <span class="sa-chip sa-chip-num" aria-hidden="true">3</span>
            <h2 class="h2 mb-0" id="sec-step-3">Bukti</h2>
        </div>

        <div class="sa-field">
            <label class="form-label" for="f-bukti">Bukti gambar (boleh dikosongkan)</label>
            <div class="sa-upload-dropzone" id="upload-dropzone">
                <span class="sa-chip mb-2" aria-hidden="true"><i class="bi bi-image"></i></span>
                <p class="mb-1 fw-medium">Pilih atau tarik gambar bukti ke kotak ini</p>
                <p class="small text-muted mb-2" id="help-bukti">Maksimal 3 gambar, masing-masing 5 MB (JPG, PNG, atau WebP). Data lokasi &amp; kamera otomatis dihapus.</p>
                <input class="form-control sa-upload-input" type="file" id="f-bukti" name="bukti[]" accept="image/jpeg,image/png,image/webp" multiple data-max-files="3" data-max-bytes="5242880" data-error-id="client-bukti" data-preview-id="bukti-preview"<?= field_attrs($errors, 'bukti', 'help-bukti') ?>>
            </div>
            <div class="sa-upload-previews" id="bukti-preview" aria-live="polite"></div>
            <p class="sa-field-error" id="client-bukti" role="alert" hidden></p>
            <?= field_error($errors, 'bukti') ?>
        </div>

        <div class="sa-field mb-0">
            <label class="form-label" for="f-jawaban">Pertanyaan keamanan: <?= e($captcha) ?></label>
            <input class="form-control sa-input-short" type="text" id="f-jawaban" name="jawaban" inputmode="numeric" maxlength="3" autocomplete="off" required<?= field_attrs($errors, 'jawaban', 'help-jawaban') ?>>
            <p class="sa-help" id="help-jawaban">Ini untuk menahan pesan otomatis (spam).</p>
            <?= field_error($errors, 'jawaban') ?>
        </div>
    </section>

    <div class="sa-actions">
        <button class="btn btn-primary" type="submit" data-loading-text="Mengirim laporan...">
            <i class="bi bi-send me-2" aria-hidden="true" data-submit-icon></i>
            <span data-submit-label>Kirim laporan</span>
        </button>
    </div>
</form>
<?php endif; ?>
