<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Suara Aman') ?> | Suara Aman</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/theme.css">
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
<?php
    $logoUrl    = unpam_logo_url();
    $reqPath    = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $isStaff    = ($layout ?? '') === 'staff';
    $showNotice = !$isStaff
        && !str_starts_with($reqPath, '/bk/')
        && !str_starts_with($reqPath, '/admin/')
        && !str_starts_with($reqPath, '/akun');
?>
<?php if ($showNotice): ?>
    <script src="/assets/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script src="/assets/js/notice.js" defer></script>
<?php endif; ?>
</head>
<?php if ($isStaff): ?>
<?php
    $staffUser = auth_user();
    $staffRole = $staffUser['role'] ?? '';
    $staffHome = $staffRole === 'admin' ? '/admin/statistik' : '/bk/laporan';
?>
<body class="d-flex flex-column min-vh-100">
    <header class="sa-header d-print-none">
        <div class="sa-staff-container sa-staff-header">
            <div class="sa-brand">
                <?php if ($logoUrl !== null): ?>
                    <img class="sa-logo-header" src="<?= e($logoUrl) ?>" alt="Logo Universitas Pamulang" data-hide-broken>
                    <span class="sa-brand-sep" aria-hidden="true"></span>
                <?php endif; ?>
                <a class="sa-wordmark" href="<?= e($staffHome) ?>">Suara <span class="sa-wordmark-accent">Aman</span><span class="sa-dot" aria-hidden="true"></span></a>
            </div>
            <button type="button" class="btn btn-outline-primary sa-nav-toggle d-md-none" data-nav-toggle="staff-menu" aria-expanded="false" aria-controls="staff-menu">
                <i class="bi bi-list me-1" aria-hidden="true"></i>Menu
            </button>
            <div class="sa-staff-collapse" id="staff-menu">
                <nav class="sa-staff-nav" aria-label="Menu utama">
                    <?php if ($staffRole === 'bk'): ?>
                        <a class="sa-nav-link<?= str_starts_with($reqPath, '/bk/laporan') ? ' is-active' : '' ?>" href="/bk/laporan">
                            <i class="bi bi-inbox me-1" aria-hidden="true"></i>Laporan
                        </a>
                        <a class="sa-nav-link<?= str_starts_with($reqPath, '/bk/rekap') ? ' is-active' : '' ?>" href="/bk/rekap">
                            <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>Rekap
                        </a>
                        <a class="sa-nav-link<?= str_starts_with($reqPath, '/akun') ? ' is-active' : '' ?>" href="/akun">
                            <i class="bi bi-gear me-1" aria-hidden="true"></i>Akun saya
                        </a>
                    <?php elseif ($staffRole === 'admin'): ?>
                        <a class="sa-nav-link<?= str_starts_with($reqPath, '/admin/statistik') ? ' is-active' : '' ?>" href="/admin/statistik">
                            <i class="bi bi-bar-chart me-1" aria-hidden="true"></i>Statistik
                        </a>
                        <a class="sa-nav-link<?= str_starts_with($reqPath, '/admin/akun') ? ' is-active' : '' ?>" href="/admin/akun">
                            <i class="bi bi-people me-1" aria-hidden="true"></i>Akun BK
                        </a>
                        <a class="sa-nav-link<?= str_starts_with($reqPath, '/admin/kategori') ? ' is-active' : '' ?>" href="/admin/kategori">
                            <i class="bi bi-tags me-1" aria-hidden="true"></i>Kategori
                        </a>
                        <a class="sa-nav-link<?= str_starts_with($reqPath, '/admin/backup') ? ' is-active' : '' ?>" href="/admin/backup">
                            <i class="bi bi-hdd me-1" aria-hidden="true"></i>Backup
                        </a>
                        <a class="sa-nav-link<?= str_starts_with($reqPath, '/akun') ? ' is-active' : '' ?>" href="/akun">
                            <i class="bi bi-gear me-1" aria-hidden="true"></i>Akun saya
                        </a>
                        <span class="sa-nav-sep" aria-hidden="true"></span>
                        <a class="sa-nav-link sa-nav-danger<?= str_starts_with($reqPath, '/admin/reset') ? ' is-active' : '' ?>" href="/admin/reset">
                            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Reset data
                        </a>
                    <?php endif; ?>
                </nav>
                <?php if ($staffUser): ?>
                <div class="sa-staff-user">
                    <span class="sa-staff-username"><i class="bi bi-person-badge me-1" aria-hidden="true"></i><?= e($staffUser['username']) ?></span>
                    <form method="post" action="/bk/logout" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-primary sa-btn-compact">
                            <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Keluar
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="flex-grow-1">
        <div class="sa-staff-container sa-page">
            <?= $content ?>
        </div>
    </main>

    <footer class="sa-footer d-print-none">
        <div class="sa-staff-container">
            <p class="mb-0">Suara Aman &middot; MA YABIKA</p>
        </div>
    </footer>
</body>
<?php else: ?>
<?php
    $pubUser = auth_user();
    $pubDash = ($pubUser['role'] ?? '') === 'admin' ? '/admin/statistik' : '/bk/laporan';
    $notice  = $showNotice ? (require BASE_PATH . '/app/content_notice.php') : null;
?>
<body class="d-flex flex-column min-vh-100">
    <header class="sa-header">
        <div class="sa-container sa-pub-header">
            <div class="sa-brand">
                <?php if ($logoUrl !== null): ?>
                    <img class="sa-logo-header" src="<?= e($logoUrl) ?>" alt="Logo Universitas Pamulang" data-hide-broken>
                    <span class="sa-brand-sep" aria-hidden="true"></span>
                <?php endif; ?>
                <a class="sa-wordmark" href="/">Suara <span class="sa-wordmark-accent">Aman</span><span class="sa-dot" aria-hidden="true"></span></a>
            </div>
            <button type="button" class="btn btn-outline-primary sa-btn-compact d-md-none" data-nav-toggle="pub-menu" aria-expanded="false" aria-controls="pub-menu">
                <i class="bi bi-list me-1" aria-hidden="true"></i>Menu
            </button>
            <div class="sa-pub-collapse" id="pub-menu">
                <a class="sa-nav-link<?= $reqPath === '/penyusun' ? ' is-active' : '' ?>" href="/penyusun">Penyusun</a>
                <?php if ($pubUser !== null): ?>
                    <a class="btn btn-outline-primary sa-btn-compact" href="<?= e($pubDash) ?>">Dashboard</a>
                    <form method="post" action="/bk/logout" class="m-0 d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="sa-link sa-btn-link">Keluar</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-outline-primary sa-btn-compact" href="/bk/login">Masuk Guru BK / Admin</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="flex-grow-1">
        <?php if ($notice !== null): ?>
        <noscript>
            <div class="sa-container pt-4">
                <div class="sa-note sa-note-warn sa-noscript-notice" role="region" aria-label="<?= e($notice['judul']) ?>">
                    <h2 class="h3 mb-2">
                        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= e($notice['judul']) ?>
                    </h2>
                    <p class="mb-2"><?= e($notice['paragraf_1']) ?></p>
                    <p class="mb-3"><strong><?= e($notice['paragraf_2']) ?></strong></p>
                    <h3 class="small fw-semibold mb-2"><?= e($notice['subjudul']) ?></h3>
                    <ul class="small mb-2">
                        <?php foreach ($notice['butir'] as $butir): ?>
                            <li><?= e($butir) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="small text-muted mb-0"><?= e($notice['catatan']) ?></p>
                </div>
            </div>
        </noscript>
        <?php endif; ?>

        <div class="sa-container sa-page">
            <?= $content ?>
        </div>
    </main>

    <footer class="sa-footer">
        <div class="sa-container">
            <p class="mb-2">
                <i class="bi bi-lock me-1" aria-hidden="true"></i>Kami tidak menyimpan nama, alamat IP, atau data perangkat kamu.
            </p>
            <p class="mb-3 sa-footer-links">
                <a href="/privasi"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>Privasi</a>
                <span aria-hidden="true">&middot;</span>
                <a href="/status"><i class="bi bi-search me-1" aria-hidden="true"></i>Cek status laporan</a>
                <span aria-hidden="true">&middot;</span>
                <a href="/penyusun"><i class="bi bi-people me-1" aria-hidden="true"></i>Penyusun</a>
                <span aria-hidden="true">&middot;</span>
                <span>MA YABIKA</span>
            </p>
            <div class="sa-footer-project">
                <?php if ($logoUrl !== null): ?>
                    <img class="sa-logo-footer" src="<?= e($logoUrl) ?>" alt="Logo Universitas Pamulang" data-hide-broken>
                <?php endif; ?>
                <span>Proyek Mata Kuliah Pendidikan Pancasila, Universitas Pamulang</span>
            </div>
        </div>
    </footer>

    <?php if ($notice !== null): ?>
    <div class="modal fade" id="sa-notice-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="sa-notice-title" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content sa-modal-content">
                <div class="modal-header sa-modal-header">
                    <h2 class="h2 modal-title d-flex align-items-center gap-2 mb-0" id="sa-notice-title">
                        <span class="sa-chip sa-chip-warn" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
                        <span><?= e($notice['judul']) ?></span>
                    </h2>
                </div>
                <div class="modal-body sa-modal-body">
                    <p class="mb-2"><?= e($notice['paragraf_1']) ?></p>
                    <p class="sa-notice-reassure mb-3"><strong><?= e($notice['paragraf_2']) ?></strong></p>
                    <h3 class="h3 mb-2"><?= e($notice['subjudul']) ?></h3>
                    <ul class="sa-notice-list mb-3">
                        <?php foreach ($notice['butir'] as $butir): ?>
                            <li><?= e($butir) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="small text-muted mb-0"><?= e($notice['catatan']) ?></p>
                </div>
                <div class="modal-footer sa-modal-footer">
                    <button type="button" class="btn sa-btn-modal-accept" id="sa-notice-accept">
                        <i class="bi bi-check2 me-1" aria-hidden="true"></i><?= e($notice['tombol']) ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
<?php endif; ?>
</html>
