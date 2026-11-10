<?php
$trackerSteps = [
    'baru'            => 'Baru',
    'ditinjau'        => 'Ditinjau',
    'ditindaklanjuti' => 'Ditindaklanjuti',
    'selesai'         => 'Selesai',
];
$stepOrder = array_keys($trackerSteps);
$curStatus = (string) ($report['status'] ?? 'baru');
$effective = $curStatus === 'dieskalasi' ? 'ditindaklanjuti' : $curStatus;
$curIndex  = (int) array_search($effective, $stepOrder, true);
?>
<h1 class="sa-title">Status laporan</h1>

<ol class="sa-status-tracker mb-4" aria-label="Tahapan penanganan laporan">
    <?php foreach ($stepOrder as $idx => $sKey): ?>
        <?php
            $isPast   = $idx < $curIndex || ($curStatus === 'selesai' && $idx === $curIndex);
            $isActive = $idx === $curIndex && $curStatus !== 'selesai';
            $cls      = $isActive ? 'is-active' : ($isPast ? 'is-done' : '');
        ?>
        <li class="sa-tracker-step <?= $cls ?>"<?= $isActive ? ' aria-current="step"' : '' ?>>
            <span class="sa-tracker-dot" aria-hidden="true">
                <?php if ($isPast): ?>
                    <i class="bi bi-check2"></i>
                <?php else: ?>
                    <?= $idx + 1 ?>
                <?php endif; ?>
            </span>
            <span class="sa-tracker-label"><?= e($trackerSteps[$sKey]) ?></span>
        </li>
    <?php endforeach; ?>
</ol>

<dl class="sa-meta">
    <div><dt>Status</dt><dd><?= status_badge($report['status']) ?></dd></div>
    <div><dt>Jenis kejadian</dt><dd><?= e($report['category']) ?></dd></div>
    <div><dt>Dikirim</dt><dd><?= e(format_date_id($report['created_at'])) ?></dd></div>
</dl>

<h2 class="mt-4">
    <i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>Percakapan dengan guru BK
</h2>

<?php if ($messages === []): ?>
    <p class="sa-prose">Belum ada pesan. Balasan guru BK akan muncul di sini.</p>
<?php else: ?>
    <div class="sa-thread">
        <?php foreach ($messages as $m): ?>
            <?php $fromBk = $m['sender'] === 'bk'; ?>
            <div class="sa-msg <?= $fromBk ? 'sa-msg-bk' : 'sa-msg-me' ?>">
                <p class="sa-msg-who">
                    <i class="bi <?= $fromBk ? 'bi-person-badge' : 'bi-person' ?> me-1" aria-hidden="true"></i><?= $fromBk ? 'Guru BK' : 'Kamu' ?>
                </p>
                <p class="sa-msg-body"><?= e($m['body']) ?></p>
                <p class="sa-msg-time"><?= e(format_datetime_id($m['created_at'])) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($notice !== null): ?>
    <div class="sa-note" role="status"><?= e($notice) ?></div>
<?php endif; ?>

<form method="post" action="/status" data-once>
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="balas">
    <input type="hidden" name="kode" value="<?= e($code) ?>">
    <input type="hidden" name="nonce" value="<?= e($nonce) ?>">
    <?php $errors = $reply_error === null ? [] : ['pesan' => $reply_error]; ?>
    <div class="sa-field">
        <label class="form-label" for="f-pesan">Balas guru BK</label>
        <textarea class="form-control" id="f-pesan" name="pesan" rows="4" maxlength="2000" required<?= field_attrs($errors, 'pesan', 'help-pesan') ?>><?= e($draft) ?></textarea>
        <p class="sa-help" id="help-pesan">Maksimal 2.000 karakter.</p>
        <?= field_error($errors, 'pesan') ?>
    </div>
    <button class="btn btn-primary" type="submit" data-loading-text="Mengirim pesan...">
        <i class="bi bi-send me-2" aria-hidden="true" data-submit-icon></i>
        <span data-submit-label>Kirim pesan</span>
    </button>
</form>

<p class="mt-4">
    <a class="sa-link" href="/status"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Cek kode lain</a>
</p>
