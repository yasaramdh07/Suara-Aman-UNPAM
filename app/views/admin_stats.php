<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <h1 class="sa-title mb-0">Statistik agregat &middot; <?= (int) $year ?></h1>
    <form method="get" action="/admin/statistik" class="d-flex align-items-center gap-2">
        <label class="form-label mb-0" for="tahun">Tahun</label>
        <select id="tahun" name="tahun" class="form-select sa-input-short">
            <?php foreach ($years as $y): ?>
                <option value="<?= (int) $y ?>"<?= (int) $y === $year ? ' selected' : '' ?>><?= (int) $y ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline-primary">Tampilkan</button>
    </form>
</div>

<?php if (($flash ?? null) !== null): ?>
    <div class="sa-note mb-3" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<div class="sa-note mb-4">
    Demi privasi pelapor, halaman pengelola hanya menampilkan jumlah agregat. Admin tidak memiliki akses ke daftar maupun isi laporan.
</div>

<p class="mb-4">Total laporan masuk pada tahun <strong><?= (int) $year ?></strong>: <strong class="sa-mono"><?= (int) $totalYear ?></strong></p>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <section aria-labelledby="stat-bulan">
            <h2 class="h2 mb-2" id="stat-bulan">Per bulan</h2>
            <div class="sa-table-wrap">
                <table class="table mb-0 sa-table">
                    <thead>
                        <tr>
                            <th scope="col">Bulan</th>
                            <th scope="col">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <tr>
                                <td><?= e(MONTHS_FULL_ID[$m]) ?></td>
                                <td class="sa-mono"><?= (int) ($byMonth[$m] ?? 0) ?></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-12 col-lg-4">
        <section aria-labelledby="stat-kategori">
            <h2 class="h2 mb-2" id="stat-kategori">Per kategori</h2>
            <div class="sa-table-wrap">
                <table class="table mb-0 sa-table">
                    <thead>
                        <tr>
                            <th scope="col">Kategori</th>
                            <th scope="col">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byCategory as $row): ?>
                            <tr>
                                <td><?= e($row['kategori']) ?></td>
                                <td class="sa-mono"><?= (int) $row['jumlah'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-12 col-lg-4">
        <section aria-labelledby="stat-status">
            <h2 class="h2 mb-2" id="stat-status">Per status</h2>
            <div class="sa-table-wrap">
                <table class="table mb-0 sa-table">
                    <thead>
                        <tr>
                            <th scope="col">Status</th>
                            <th scope="col">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (REPORT_STATUSES as $key => $label): ?>
                            <tr>
                                <td><?= status_badge($key) ?></td>
                                <td class="sa-mono"><?= (int) ($byStatus[$key] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

