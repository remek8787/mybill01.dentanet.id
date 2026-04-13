<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'staff']);

$pdo = db();
$today = date('Y-m-d');
$currentMonth = (int) date('n');
$currentYear = (int) date('Y');
$range = trim((string) ($_GET['range'] ?? 'month'));
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

require __DIR__ . '/includes/header.php';
?>

<section class="page-ornament page-ornament--gold mb-4">
  <div class="page-ornament-kicker"><i class="fa-solid fa-wallet me-2"></i>Laporan Pemasukan</div>
  <h1 class="page-ornament-title">Pemasukan Hari Ini dan Bulan Ini</h1>
  <p class="page-ornament-text">Pantau nominal pembayaran masuk untuk hari ini dan akumulasi pada bulan berjalan.</p>
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

<section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--table">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="font-semibold mb-0"><?= $range === 'today' ? 'Transaksi Lunas Hari Ini' : 'Transaksi Lunas Bulan Ini' ?></h2>
    <div class="d-flex gap-2 flex-wrap">
      <a href="income_report.php?range=today" class="btn btn-sm <?= $range === 'today' ? 'btn-primary' : 'btn-outline-secondary' ?>">Hari Ini</a>
      <a href="income_report.php?range=month" class="btn btn-sm <?= $range === 'month' ? 'btn-primary' : 'btn-outline-secondary' ?>">Bulan Ini</a>
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

<?php require __DIR__ . '/includes/footer.php'; ?>
