<?php
$catMatrix = [];
foreach ($byCat as $row) {
    $catMatrix[(int) $row['bulan']][(int) $row['category_id']] = (int) $row['jumlah'];
}

$statusMatrix = [];
foreach ($byStatus as $row) {
    $statusMatrix[(int) $row['bulan']][(string) $row['status']] = (int) $row['jumlah'];
}

$avgMap = [];
foreach ($avgReply as $row) {
    $avgMap[(int) $row['bulan']] = [
        'terjawab' => (int) $row['terjawab'],
        'avg_jam'  => (float) $row['avg_jam'],
    ];
}
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 d-print-none">
    <h1 class="sa-title mb-0">Rekap bulanan &middot; <?= (int) $year ?></h1>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="get" action="/bk/rekap" class="d-flex align-items-center gap-2">
            <label class="form-label mb-0" for="tahun">Tahun</label>
            <select id="tahun" name="tahun" class="form-select sa-input-short">
                <?php foreach ($years as $y): ?>
                    <option value="<?= (int) $y ?>"<?= (int) $y === $year ? ' selected' : '' ?>><?= (int) $y ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-primary">Tampilkan</button>
        </form>
        <button type="button" class="btn btn-primary" data-print>
            <i class="bi bi-printer me-1" aria-hidden="true"></i>Cetak rekap
        </button>
    </div>
</div>

<div class="d-none d-print-block mb-3">
    <h1 class="h2 mb-1">Rekap bulanan pengaduan Suara Aman &middot; Tahun <?= (int) $year ?></h1>
    <p class="small text-muted mb-0">MA YABIKA &middot; Dicetak pada <?= e(format_date_id(date('Y-m-d'))) ?></p>
</div>

<section class="mb-4" aria-labelledby="rekap-kategori">
    <h2 class="h2 mb-2" id="rekap-kategori">Jumlah laporan per kategori</h2>
    <div class="sa-table-wrap">
        <table class="table mb-0 sa-table">
            <thead>
                <tr>
                    <th scope="col">Bulan</th>
                    <?php foreach ($categories as $cat): ?>
                        <th scope="col"><?= e($cat['name']) ?></th>
                    <?php endforeach; ?>
                    <th scope="col">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <?php $rowTotal = 0; ?>
                    <tr>
                        <td><?= e(MONTHS_FULL_ID[$m]) ?></td>
                        <?php foreach ($categories as $cat): ?>
                            <?php
                                $cid = (int) $cat['id'];
                                $val = $catMatrix[$m][$cid] ?? 0;
                                $rowTotal += $val;
                            ?>
                            <td><?= $val ?></td>
                        <?php endforeach; ?>
                        <td class="fw-semibold"><?= $rowTotal ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="mb-4" aria-labelledby="rekap-status">
    <h2 class="h2 mb-2" id="rekap-status">Jumlah laporan per status</h2>
    <div class="sa-table-wrap">
        <table class="table mb-0 sa-table">
            <thead>
                <tr>
                    <th scope="col">Bulan</th>
                    <?php foreach (REPORT_STATUSES as $label): ?>
                        <th scope="col"><?= e($label) ?></th>
                    <?php endforeach; ?>
                    <th scope="col">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <?php $rowTotal = 0; ?>
                    <tr>
                        <td><?= e(MONTHS_FULL_ID[$m]) ?></td>
                        <?php foreach (REPORT_STATUSES as $sKey => $label): ?>
                            <?php
                                $val = $statusMatrix[$m][$sKey] ?? 0;
                                $rowTotal += $val;
                            ?>
                            <td><?= $val ?></td>
                        <?php endforeach; ?>
                        <td class="fw-semibold"><?= $rowTotal ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</section>

<section aria-labelledby="rekap-waktu">
    <h2 class="h2 mb-1" id="rekap-waktu">Rata-rata waktu respons pertama BK</h2>
    <p class="small text-muted mb-2">Dihitung dari waktu laporan masuk hingga balasan pertama Guru BK (dalam jam).</p>
    <div class="sa-table-wrap">
        <table class="table mb-0 sa-table">
            <thead>
                <tr>
                    <th scope="col">Bulan</th>
                    <th scope="col">Laporan dibalas</th>
                    <th scope="col">Rata-rata waktu respons</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <?php $info = $avgMap[$m] ?? null; ?>
                    <tr>
                        <td><?= e(MONTHS_FULL_ID[$m]) ?></td>
                        <td><?= $info ? $info['terjawab'] : 0 ?></td>
                        <td>
                            <?php if ($info === null): ?>
                                &mdash;
                            <?php else: ?>
                                <?= e(number_format($info['avg_jam'], 1, ',', '.')) ?> jam
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</section>
