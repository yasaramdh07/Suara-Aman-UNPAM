<h1 class="sa-title">Daftar laporan</h1>

<?php if (($lastResetAt ?? null) !== null): ?>
    <p class="small text-muted mb-3">
        <i class="bi bi-clock-history me-1" aria-hidden="true"></i>Data sistem terakhir direset pada <?= e(format_datetime_id((string) $lastResetAt)) ?>
    </p>
<?php endif; ?>

<?php if ($flash !== null): ?>
    <div class="sa-note" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<form method="get" action="/bk/laporan" class="sa-filter-box mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label" for="f-status">Status</label>
            <select id="f-status" name="status" class="form-select">
                <option value="">Semua status</option>
                <?php foreach (REPORT_STATUSES as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $filter['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label" for="f-urgency">Urgensi</label>
            <select id="f-urgency" name="urgency" class="form-select">
                <option value="">Semua urgensi</option>
                <?php foreach (REPORT_URGENCIES as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $filter['urgency'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label" for="f-category">Kategori</label>
            <select id="f-category" name="category" class="form-select">
                <option value="">Semua kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>"<?= $filter['category'] === (string) $cat['id'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-3 col-lg-2">
            <label class="form-label" for="f-from">Dari tanggal</label>
            <input type="date" id="f-from" name="date_from" class="form-control" value="<?= e($filter['date_from']) ?>">
        </div>
        <div class="col-6 col-sm-3 col-lg-2">
            <label class="form-label" for="f-to">Sampai tanggal</label>
            <input type="date" id="f-to" name="date_to" class="form-control" value="<?= e($filter['date_to']) ?>">
        </div>
        <div class="col-12 col-lg-1 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100">Terapkan</button>
        </div>
    </div>
    <?php if (array_filter($filter) !== []): ?>
        <div class="mt-2">
            <a href="/bk/laporan" class="small">Atur ulang filter</a>
        </div>
    <?php endif; ?>
</form>

<?php if ($reports === []): ?>
    <p class="text-muted">Belum ada laporan.</p>
<?php else: ?>
    <div class="sa-table-wrap d-none d-md-block">
        <table class="table table-hover mb-0 sa-table">
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Kategori</th>
                    <th scope="col">Peran pelapor</th>
                    <th scope="col">Urgensi</th>
                    <th scope="col">Status</th>
                    <th scope="col">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $r): ?>
                    <?php $isUrgent = $r['urgency'] === 'mendesak'; ?>
                    <tr class="<?= $isUrgent ? 'sa-row-urgent' : '' ?>">
                        <td class="sa-mono">#<?= (int) $r['id'] ?></td>
                        <td><?= e(format_date_id((string) $r['created_at'])) ?></td>
                        <td><?= e($r['category']) ?></td>
                        <td><?= $r['reporter_role'] === 'korban' ? 'Korban' : 'Saksi' ?></td>
                        <td>
                            <?= urgency_badge((string) $r['urgency']) ?>
                            <?php if ((int) $r['urgency_auto_flag'] === 1): ?>
                                <span class="sa-auto-tag">Usulan sistem</span>
                            <?php endif; ?>
                        </td>
                        <td><?= status_badge((string) $r['status']) ?></td>
                        <td>
                            <a class="btn btn-outline-primary sa-btn-compact" href="/bk/laporan/<?= (int) $r['id'] ?>">Buka laporan</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-md-none sa-card-list">
        <?php foreach ($reports as $r): ?>
            <?php $isUrgent = $r['urgency'] === 'mendesak'; ?>
            <a href="/bk/laporan/<?= (int) $r['id'] ?>" class="sa-report-card<?= $isUrgent ? ' sa-row-urgent' : '' ?>">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="sa-mono fw-semibold">#<?= (int) $r['id'] ?></span>
                    <div class="d-flex gap-2 align-items-center">
                        <?= urgency_badge((string) $r['urgency']) ?>
                        <?= status_badge((string) $r['status']) ?>
                    </div>
                </div>
                <div class="fw-semibold mb-1"><?= e($r['category']) ?></div>
                <div class="small text-muted">
                    <?= e(format_date_id((string) $r['created_at'])) ?> &middot; <?= $r['reporter_role'] === 'korban' ? 'Korban' : 'Saksi' ?>
                    <?php if ((int) $r['urgency_auto_flag'] === 1): ?>
                        &middot; Usulan sistem
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
