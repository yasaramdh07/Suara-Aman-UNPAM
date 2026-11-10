<?php
$logoUrl   = unpam_logo_url();
$infoIcons = ['bi-mortarboard', 'bi-people', 'bi-book', 'bi-person-badge'];
?>
<?php if ($logoUrl !== null): ?>
    <div class="mb-3">
        <img class="sa-logo-page" src="<?= e($logoUrl) ?>" alt="Logo Universitas Pamulang" data-hide-broken>
    </div>
<?php endif; ?>

<h1 class="sa-title"><?= e($penyusun['judul']) ?></h1>

<div class="sa-penyusun-info sa-prose mb-4">
    <?php foreach ($penyusun['baris_info'] as $idx => $baris): ?>
        <?php $icon = $infoIcons[$idx] ?? 'bi-info-circle'; ?>
        <p class="sa-penyusun-line">
            <i class="bi <?= e($icon) ?> sa-penyusun-icon" aria-hidden="true"></i>
            <span><?= e($baris) ?></span>
        </p>
    <?php endforeach; ?>
</div>

<h2 class="h3 mb-2">
    <i class="bi bi-people me-1" aria-hidden="true"></i>Daftar anggota
</h2>
<ol class="sa-member-list">
    <?php foreach ($penyusun['anggota'] as $m): ?>
        <li class="sa-member-item">
            <span class="sa-member-name"><?= e($m['nama']) ?></span>
            <span class="sa-member-nim sa-mono"><?= e($m['nim']) ?></span>
        </li>
    <?php endforeach; ?>
</ol>
