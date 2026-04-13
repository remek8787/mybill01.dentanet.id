<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'staff']);

$pdo = db();
$today = date('Y-m-d');
$currentMonth = (int) date('n');
$currentYear = (int) date('Y');
$range = trim((string) ($_GET['range'] ?? 'month'));
$format = trim((string) ($_GET['format'] ?? ''));
if (!in_array($range, ['today', 'month'], true)) {
    $range = 'month';
}

$todayStmt = $pdo->prepare("SELECT COALESCE(SUM(amount - discount_amount),0) FROM invoices WHERE status = 'paid' AND date(paid_at) = :today");
$todayStmt->execute([':today' => $today]);
$incomeToday = (int) $todayStmt->fetchColumn();

$monthStmt = $pdo->prepare("SELECT COALESCE(SUM(amount - discount_amount),0) FROM invoices WHERE status = 'paid' AND period_month = :month AND period_year = :year");
$monthStmt->execute([':month' => $currentMonth, ':year' => $currentYear]);
$incomeMonth = (int) $monthStmt->fetchColumn();

$sql = 'SELECT i.*, c.full_name AS customer_name, c.area, p.name AS package_name, p.speed
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN packages p ON p.id = i.package_id
    WHERE i.status = "paid"';
$params = [];
if ($range === 'today') {
    $sql .= ' AND date(i.paid_at) = :today';
    $params[':today'] = $today;
} else {
    $sql .= ' AND i.period_month = :month AND i.period_year = :year';
    $params[':month'] = $currentMonth;
    $params[':year'] = $currentYear;
}
$sql .= ' ORDER BY i.paid_at DESC, i.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$dayLabels = [];
$dayValues = [];
if ($range === 'month') {
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
    $chartMap = array_fill(1, $daysInMonth, 0);
    $chartStmt = $pdo->prepare("SELECT strftime('%d', paid_at) AS day_num, COALESCE(SUM(amount - discount_amount),0) AS total
        FROM invoices WHERE status = 'paid' AND period_month = :month AND period_year = :year
        GROUP BY strftime('%d', paid_at)");
    $chartStmt->execute([':month' => $currentMonth, ':year' => $currentYear]);
    foreach ($chartStmt->fetchAll() as $row) {
        $day = (int) ($row['day_num'] ?? 0);
        if ($day >= 1 && $day <= $daysInMonth) {
            $chartMap[$day] = (int) ($row['total'] ?? 0);
        }
    }
    foreach ($chartMap as $day => $value) {
        $dayLabels[] = (string) $day;
        $dayValues[] = $value;
    }
}

if ($format === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-pemasukan-' . $range . '.xls"');
    echo "<table border='1'><tr><th>Tanggal Bayar</th><th>Pelanggan</th><th>Paket</th><th>Area</th><th>Nominal</th><th>Invoice</th></tr>";
    foreach ($rows as $row) {
        echo '<tr>'
            . '<td>' . e(formatDateTimeId((string) ($row['paid_at'] ?? ''), '-')) . '</td>'
            . '<td>' . e((string) ($row['customer_name'] ?? '-')) . '</td>'
            . '<td>' . e(packageLabel($row)) . '</td>'
            . '<td>' . e((string) ($row['area'] ?? '-')) . '</td>'
            . '<td>' . e(rupiah(invoiceNetAmount($row))) . '</td>'
            . '<td>' . e(ensureInvoiceNumber($pdo, (int) $row['id'])) . '</td>'
            . '</tr>';
    }
    echo '</table>';
    exit;
}

if ($format === 'pdf') {
    ?>
    <!doctype html>
    <html lang="id">
    <head>
      <meta charset="utf-8">
      <title>Laporan Pemasukan</title>
      <style>
        body{font-family:Arial,Helvetica,sans-serif;padding:24px;color:#111827}
        h1{margin:0 0 8px}.muted{color:#64748b;margin-bottom:18px}
        table{width:100%;border-collapse:collapse;font-size:13px}th,td{border:1px solid #cbd5e1;padding:8px;text-align:left}
        th{background:#f1f5f9} .summary{margin-bottom:16px}
      </style>
    </head>
    <body onload="window.print()">
      <h1>Laporan Pemasukan <?= $range === 'today' ? 'Hari Ini' : 'Bulan Ini' ?></h1>
      <div class="muted"><?= e(companyName()) ?></div>
      <div class="summary">Pemasukan hari ini: <b><?= e(rupiah($incomeToday)) ?></b> | Pemasukan bulan ini: <b><?= e(rupiah($incomeMonth)) ?></b></div>
      <table>
        <tr><th>Tanggal Bayar</th><th>Pelanggan</th><th>Paket</th><th>Area</th><th>Nominal</th><th>Invoice</th></tr>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= e(formatDateTimeId((string) ($row['paid_at'] ?? ''), '-')) ?></td>
            <td><?= e((string) ($row['customer_name'] ?? '-')) ?></td>
            <td><?= e(packageLabel($row)) ?></td>
            <td><?= e((string) ($row['area'] ?? '-')) ?></td>
            <td><?= e(rupiah(invoiceNetAmount($row))) ?></td>
            <td><?= e(ensureInvoiceNumber($pdo, (int) $row['id'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </body>
    </html>
    <?php
    exit;
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-ornament page-ornament--gold mb-4">
  <div class="page-ornament-kicker"><i class="fa-solid fa-wallet me-2"></i>Laporan Pemasukan</div>
  <h1 class="page-ornament-title">Pemasukan Hari Ini dan Bulan Ini</h1>
  <p class="page-ornament-text">Pantau nominal pembayaran masuk untuk hari ini dan akumulasi pada bulan berjalan, lengkap dengan chart dan export.</p>
</section>

<section class="isp-mini-grid mb-4">
  <a class="isp-mini-card isp-mini-card--emerald text-decoration-none" href="income_report.php?range=today">
    <div class="isp-mini-card__label">Pemasukan Hari Ini</div>
    <div class="isp-mini-card__value"><?= e(rupiah($incomeToday)) ?></div>
    <div class="isp-mini-card__note">Tanggal <?= e(formatDateId($today, '-')) ?></div>
  </a>
  <a class="isp-mini-card isp-mini-card--blue text-decoration-none" href="income_report.php?range=month">
    <div class="isp-mini-card__label">Pemasukan Bulan Ini</div>
    <div class="isp-mini-card__value"><?= e(rupiah($incomeMonth)) ?></div>
    <div class="isp-mini-card__note">Periode <?= e(periodLabel($currentMonth, $currentYear)) ?></div>
  </a>
</section>

<?php if ($range === 'month'): ?>
<section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--table mb-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="font-semibold mb-0">Chart Pemasukan Harian</h2>
    <div class="small text-secondary">Periode <?= e(periodLabel($currentMonth, $currentYear)) ?></div>
  </div>
  <canvas id="incomeChart" height="110"></canvas>
</section>
<?php endif; ?>

<section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--table">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="font-semibold mb-0"><?= $range === 'today' ? 'Transaksi Lunas Hari Ini' : 'Transaksi Lunas Bulan Ini' ?></h2>
    <div class="d-flex gap-2 flex-wrap">
      <a href="income_report.php?range=today" class="btn btn-sm <?= $range === 'today' ? 'btn-primary' : 'btn-outline-secondary' ?>">Hari Ini</a>
      <a href="income_report.php?range=month" class="btn btn-sm <?= $range === 'month' ? 'btn-primary' : 'btn-outline-secondary' ?>">Bulan Ini</a>
      <a href="income_report.php?range=<?= e($range) ?>&format=xls" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-file-excel me-1"></i>Excel</a>
      <a href="income_report.php?range=<?= e($range) ?>&format=pdf" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-file-pdf me-1"></i>PDF</a>
    </div>
  </div>

  <div class="overflow-auto table-wrap">
    <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Tanggal Bayar</th>
          <th class="py-2 pr-3">Pelanggan</th>
          <th class="py-2 pr-3">Paket</th>
          <th class="py-2 pr-3">Area</th>
          <th class="py-2 pr-3">Nominal</th>
          <th class="py-2 pr-3">Invoice</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr class="border-b align-top">
            <td class="py-2 pr-3"><?= e(formatDateTimeId((string) ($row['paid_at'] ?? ''), '-')) ?></td>
            <td class="py-2 pr-3">
              <div class="font-semibold"><?= e((string) ($row['customer_name'] ?? '-')) ?></div>
              <div class="text-xs text-slate-500">Area: <?= e((string) ($row['area'] ?? '-')) ?></div>
            </td>
            <td class="py-2 pr-3"><?= e(packageLabel($row)) ?></td>
            <td class="py-2 pr-3"><?= e((string) ($row['area'] ?? '-')) ?></td>
            <td class="py-2 pr-3"><?= e(rupiah(invoiceNetAmount($row))) ?></td>
            <td class="py-2 pr-3"><?= e(ensureInvoiceNumber($pdo, (int) $row['id'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="py-4 text-slate-500">Belum ada pemasukan pada rentang ini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($range === 'month'): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(() => {
  const canvas = document.getElementById('incomeChart');
  if (!canvas || !window.Chart) return;
  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: <?= json_encode($dayLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      datasets: [{
        label: 'Pemasukan',
        data: <?= json_encode($dayValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        backgroundColor: 'rgba(59, 130, 246, 0.72)',
        borderColor: 'rgba(37, 99, 235, 1)',
        borderWidth: 1,
        borderRadius: 8,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: (value) => 'Rp ' + Number(value).toLocaleString('id-ID')
          }
        }
      },
      plugins: {
        legend: { display: false }
      }
    }
  });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
