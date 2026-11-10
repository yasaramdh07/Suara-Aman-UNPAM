<section class="sa-hero-band" aria-labelledby="h-hero">
    <h1 class="sa-title" id="h-hero">Laporkan bullying tanpa menyebut nama</h1>
    <p class="sa-prose mb-0">
        Suara Aman adalah kanal pengaduan perundungan di MA YABIKA agar kamu dapat menyampaikan kejadian dengan tenang tanpa perlu login.
    </p>

    <div class="sa-actions">
        <a class="btn btn-primary" href="/lapor">
            <i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Buat laporan
        </a>
        <a class="btn btn-outline-primary" href="/status">
            <i class="bi bi-search me-2" aria-hidden="true"></i>Cek status laporan
        </a>
        <a class="sa-link" href="/privasi">
            <i class="bi bi-shield-check me-1" aria-hidden="true"></i>Privasi
        </a>
    </div>
</section>

<section class="sa-trust-strip" aria-label="Jaminan privasi singkat">
    <div class="sa-trust-item">
        <span class="sa-chip" aria-hidden="true"><i class="bi bi-eye-slash"></i></span>
        <div>
            <strong class="d-block">Tanpa nama</strong>
            <span class="small text-muted">Tidak perlu akun, nama, alamat IP, atau identitas perangkat.</span>
        </div>
    </div>
    <div class="sa-trust-item">
        <span class="sa-chip" aria-hidden="true"><i class="bi bi-lock"></i></span>
        <div>
            <strong class="d-block">Hanya dibaca Guru BK</strong>
            <span class="small text-muted">Isi laporan dienkripsi dan tidak dapat dibuka oleh pengelola teknis.</span>
        </div>
    </div>
    <div class="sa-trust-item">
        <span class="sa-chip sa-chip-lime" aria-hidden="true"><i class="bi bi-key"></i></span>
        <div>
            <strong class="d-block">Cek balasan lewat kode tiket</strong>
            <span class="small text-muted">Pantau tindak lanjut dan balas pesan Guru BK dengan kode acak.</span>
        </div>
    </div>
</section>

<section class="sa-how-section" aria-labelledby="h-cara-kerja">
    <h2 class="h2 mb-3" id="h-cara-kerja">Cara kerja</h2>
    <ol class="sa-stepper">
        <li class="sa-stepper-item">
            <span class="sa-stepper-marker" aria-hidden="true"><i class="bi bi-pencil-square"></i></span>
            <div class="sa-stepper-body">
                <h3 class="h3 mb-1">1. Tulis laporan</h3>
                <p class="mb-0 text-muted">Pilih peran dan jenis kejadian, lalu ceritakan apa yang terjadi dalam waktu kurang dari 3 menit.</p>
            </div>
        </li>
        <li class="sa-stepper-item">
            <span class="sa-stepper-marker" aria-hidden="true"><i class="bi bi-key"></i></span>
            <div class="sa-stepper-body">
                <h3 class="h3 mb-1">2. Simpan kode tiket</h3>
                <p class="mb-0 text-muted">Salin atau unduh kode 12 karakter yang muncul sekali setelah laporan berhasil dikirim.</p>
            </div>
        </li>
        <li class="sa-stepper-item">
            <span class="sa-stepper-marker" aria-hidden="true"><i class="bi bi-chat-left-text"></i></span>
            <div class="sa-stepper-body">
                <h3 class="h3 mb-1">3. Cek balasan</h3>
                <p class="mb-0 text-muted">Masukkan kode tiket di halaman Cek status untuk melihat perkembangan dan berdiskusi dengan Guru BK.</p>
            </div>
        </li>
    </ol>
</section>

<?php if (!empty($edukasi)): ?>
<div class="sa-edu-wrap">
    <nav class="sa-anchor-nav" aria-label="Navigasi edukasi">
        <a href="#jenis">Jenis-jenis</a>
        <span class="sa-anchor-sep" aria-hidden="true">|</span>
        <a href="#pancasila">Kaitan Pancasila</a>
        <span class="sa-anchor-sep" aria-hidden="true">|</span>
        <a href="#hukum">Dasar hukum</a>
    </nav>

    <section class="sa-edu-section" id="jenis" aria-labelledby="h-jenis">
        <h2 class="h2 mb-3" id="h-jenis">Jenis-jenis bullying</h2>
        <p class="sa-prose"><?= e($edukasi['jenis_pengantar']) ?></p>

        <dl class="sa-edu-dl">
            <?php foreach ($edukasi['jenis'] as $item): ?>
                <div class="sa-edu-dl-item sa-edu-dl-with-icon">
                    <span class="sa-chip" aria-hidden="true">
                        <i class="bi <?= e($item['ikon'] ?? 'bi-exclamation-circle') ?>"></i>
                    </span>
                    <div class="sa-edu-dl-text">
                        <dt><?= e($item['istilah']) ?></dt>
                        <dd><?= e($item['penjelasan']) ?></dd>
                    </div>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>

    <section class="sa-edu-section" id="pancasila" aria-labelledby="h-pancasila">
        <h2 class="h2 mb-3" id="h-pancasila">Kaitan dengan Pancasila</h2>
        <div class="sa-table-wrap">
            <table class="table mb-0 sa-table sa-table-stack">
                <thead>
                    <tr>
                        <th scope="col">Sila</th>
                        <th scope="col">Kaitannya dengan perundungan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($edukasi['pancasila'] as $idx => $row): ?>
                        <?php $no = (int) ($row['nomor'] ?? ($idx + 1)); ?>
                        <tr>
                            <td class="fw-semibold" data-label="Sila">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="sa-chip sa-chip-lime sa-chip-num" aria-hidden="true"><?= $no ?></span>
                                    <span><?= e($row['sila']) ?></span>
                                </div>
                            </td>
                            <td data-label="Kaitannya dengan perundungan"><?= e($row['kaitan']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="sa-edu-section" id="hukum" aria-labelledby="h-hukum">
        <h2 class="h2 mb-3" id="h-hukum">Dasar hukum yang berlaku</h2>
        <div class="sa-table-wrap">
            <table class="table mb-0 sa-table sa-table-stack">
                <thead>
                    <tr>
                        <th scope="col">Peraturan</th>
                        <th scope="col">Isi singkat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($edukasi['hukum'] as $row): ?>
                        <tr>
                            <td class="fw-semibold" data-label="Peraturan">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-file-earmark-text sa-doc-icon mt-1" aria-hidden="true"></i>
                                    <span><?= e($row['peraturan']) ?></span>
                                </div>
                            </td>
                            <td data-label="Isi singkat"><?= e($row['isi']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-muted mt-3 mb-0 sa-prose"><?= e($edukasi['hukum_catatan']) ?></p>
    </section>
</div>
<?php endif; ?>
