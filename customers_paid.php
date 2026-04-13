<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'staff']);

$pdo = db();
$month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));
$year = max(2024, (int) ($_GET['year'] ?? date('Y')));
$search = trim((string) ($_GET['q'] ?? ''));
$format = trim((string) ($_GET['format'] ?? ''));

$params = [':month' => $month, ':year' => $year];
$whereSearch = '';
if ($search !== '') {
    $whereSearch = ' AND (c.full_name LIKE :q OR c.area LIKE :q OR c.phone LIKE :q OR c.customer_no LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

$sql = 'SELECT c.*, p.name AS package_name, p.speed,
        COUNT(i.id) AS paid_invoice_count,
        COALESCE(SUM(i.amount - i.discount_amount),0) AS paid_total,
        MAX(i.paid_at) AS last_paid_at
    FROM customers c
    INNER JOIN invoices i ON i.customer_id = c.id AND i.status = "paid" AND i.period_month = :month AND i.period_year = :year
    LEFT JOIN packages p ON p.id = c.package_id
    WHERE 1=1 ' . $whereSearch . '
    GROUP BY c.id
    ORDER BY c.full_name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$paidTotal = 0;
foreach ($rows as $row) {
    $paidTotal += (int) ($row['paid_total'] ?? 0);
}

if ($format === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="pelanggan-lunas-' . $month . '-' . $year . '.xls"');
    echo "<table border='1'><tr><th>Pelanggan</th><th>Paket</th><th>Area</th><th>Invoice Lunas</th><th>Total Bayar</th><th>Bayar Terakhir</th></tr>";
    foreach ($rows as $row) {
        echo '<tr>'
            . '<td>' . e((string) $row['full_name']) . '</td>'
            . '<td>' . e(packageLabel($row)) . '</td>'
            . '<td>' . e((string) ($row['area'] ?: '-')) . '</td>'
            . '<td>' . (int) ($row['paid_invoice_count'] ?? 0) . '</td>'
            . '<td>' . e(rupiah((int) ($row['paid_total'] ?? 0))) . '</td>'
            . '<td>' . e(formatDateTimeId((string) ($row['last_paid_at'] ?? ''), '-')) . '</td>'
            . '</tr>';
    }
    echo '</table>';
    exit;
}

if ($format === 'pdf') {
    ?><!doctype html><html lang="id"><head><meta charset="utf-8"><title>Pelanggan Lunas</title><style>body{font-family:Arial;padding:24px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cbd5e1;padding:8px;text-align:left}th{background:#f1f5f9}</style></head><body onload="window.print()"><h1>Pelanggan Lunas <?= e(periodLabel($month, $year)) ?></h1><table><tr><th>Pelanggan</th><th>Paket</th><th>Area</th><th>Invoice Lunas</th><th>Total Bayar</th><th>Bayar Terakhir</th></tr><?php foreach ($rows as $row): ?><tr><td><?= e((string) $row['full_name']) ?></td><td><?= e(packageLabel($row)) ?></td><td><?= e((string) ($row['area'] ?: '-')) ?></td><td><?= (int) ($row['paid_invoice_count'] ?? 0) ?></td><td><?= e(rupiah((int) ($row['paid_total'] ?? 0))) ?></td><td><?= e(formatDateTimeId((string) ($row['last_paid_at'] ?? ''), '-')) ?></td></tr><?php endforeach; ?></table></body></html><?php
    exit;
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-ornament page-ornament--violet mb-4">
  <div class="page-ornament-kicker"><i class="fa-solid fa-circle-check me-2"></i>Data Pelanggan Lunas</div>
  <h1 class="page-ornament-title">Pelanggan Lunas <?= e(periodLabel($month, $year)) ?></h1>
  <p class="page-ornament-text">Daftar pelanggan yang sudah menyelesaikan pembayaran pada periode terpilih.</p>
</section>

<section class="isp-mini-grid mb-4">
  <div class="isp-mini-card isp-mini-card--emerald">
    <div class="isp-mini-card__label">Pelanggan Lunas</div>
    <div class="isp-mini-card__value"><?= count($rows) ?></div>
    <div class="isp-mini-card__note">Periode <?= e(periodLabel($month, $year)) ?></div>
  </div>
  <div class="isp-mini-card isp-mini-card--blue">
    <div class="isp-mini-card__label">Total Pemasukan</div>
    <div class="isp-mini-card__value"><?= e(rupiah($paidTotal)) ?></div>
    <div class="isp-mini-card__note">Akumulasi invoice lunas</div>
  </div>
</section>

<section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--table">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="font-semibold mb-0">Daftar Pelanggan Lunas</h2>
    <div class="d-flex gap-2 flex-wrap">
      <a href="customers_paid.php?month=<?= $month ?>&year=<?= $year ?>&q=<?= urlencode($search) ?>&format=xls" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-file-excel me-1"></i>Excel</a>
      <a href="customers_paid.php?month=<?= $month ?>&year=<?= $year ?>&q=<?= urlencode($search) ?>&format=pdf" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-file-pdf me-1"></i>PDF</a>
      <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
    </div>
  </div>

  <form class="grid md:grid-cols-4 gap-2 text-sm mb-4">
    <select name="month" class="border rounded px-3 py-2">
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>><?= e(monthName($m)) ?></option>
      <?php endfor; ?>
    </select>
    <input type="number" name="year" value="<?= $year ?>" class="border rounded px-3 py-2" placeholder="Tahun">
    <input type="text" name="q" value="<?= e($search) ?>" class="border rounded px-3 py-2" placeholder="Cari nama / area / no hp">
    <button class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>Filter</button>
  </form>

  <div class="overflow-auto table-wrap">
    <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Pelanggan</th>
          <th class="py-2 pr-3">Paket</th>
          <th class="py-2 pr-3">Area</th>
          <th class="py-2 pr-3">Invoice Lunas</th>
          <th class="py-2 pr-3">Total Bayar</th>
          <th class="py-2 pr-3">Bayar Terakhir</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr class="border-b align-top">
            <td class="py-2 pr-3">
              <div class="font-semibold"><?= e((string) $row['full_name']) ?></div>
              <div class="text-xs text-slate-500">No: <?= e((string) ($row['customer_no'] ?: '-')) ?> • HP: <?= e((string) ($row['phone'] ?: '-')) ?></div>
            </td>
            <td class="py-2 pr-3"><?= e(packageLabel($row)) ?></td>
            <td class="py-2 pr-3"><?= e((string) ($row['area'] ?: '-')) ?></td>
            <td class="py-2 pr-3"><?= (int) ($row['paid_invoice_count'] ?? 0) ?></td>
            <td class="py-2 pr-3"><?= e(rupiah((int) ($row['paid_total'] ?? 0))) ?></td>
            <td class="py-2 pr-3"><?= e(formatDateTimeId((string) ($row['last_paid_at'] ?? ''), '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="py-4 text-slate-500">Belum ada pelanggan lunas di periode ini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
