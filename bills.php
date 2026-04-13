<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'staff']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0 && $action === 'mark_paid') {
        $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'cash'));
        $paymentNote = trim((string) ($_POST['payment_note'] ?? ''));
        $paymentDate = paymentDateToDateTime($_POST['payment_date'] ?? null);
        $discountAmount = normalizeCurrencyInput($_POST['discount_amount'] ?? 0);

        if (!in_array($paymentMethod, ['cash', 'transfer', 'qris'], true)) {
            $paymentMethod = 'cash';
        }

        $pdo->prepare('UPDATE invoices SET status = "paid", paid_at = :paid_at, payment_method = :payment_method,
            payment_note = :payment_note, discount_amount = :discount_amount WHERE id = :id')->execute([
            ':id' => $id,
            ':paid_at' => $paymentDate,
            ':payment_method' => $paymentMethod,
            ':payment_note' => $paymentNote,
            ':discount_amount' => $discountAmount,
        ]);
        flash('success', 'Invoice ditandai lunas.');
    }

    if ($id > 0 && $action === 'mark_unpaid') {
        $pdo->prepare('UPDATE invoices SET status = "unpaid", paid_at = NULL, payment_method = NULL,
            payment_note = NULL, discount_amount = 0 WHERE id = :id')->execute([':id' => $id]);
        flash('success', 'Invoice dikembalikan ke belum lunas.');
    }

    header('Location: bills.php');
    exit;
}

$filterStatus = trim((string) ($_GET['status'] ?? ''));
$filterYear = (int) ($_GET['year'] ?? 0);
$filterMonth = (int) ($_GET['month'] ?? 0);
$search = trim((string) ($_GET['q'] ?? ''));

$where = [];
$params = [];
if (in_array($filterStatus, ['paid', 'unpaid'], true)) {
    $where[] = 'i.status = :status';
    $params[':status'] = $filterStatus;
}
if ($filterYear > 0) {
    $where[] = 'i.period_year = :year';
    $params[':year'] = $filterYear;
}
if ($filterMonth >= 1 && $filterMonth <= 12) {
    $where[] = 'i.period_month = :month';
    $params[':month'] = $filterMonth;
}
if ($search !== '') {
    $where[] = '(c.full_name LIKE :q OR c.area LIKE :q OR i.invoice_no LIKE :q OR p.name LIKE :q OR c.service_username LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

$sql = 'SELECT i.*, c.full_name AS customer_name, c.address, c.phone, c.area, c.customer_no,
        c.service_username, c.mikrotik_profile_name, c.isolated,
        p.name AS package_name, p.speed
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN packages p ON p.id = i.package_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY i.period_year DESC, i.period_month DESC, i.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();

$totalUnpaid = 0;
$unpaidCustomerIds = [];
foreach ($bills as $bill) {
    if (($bill['status'] ?? '') === 'unpaid') {
        $totalUnpaid += invoiceNetAmount($bill);
        $unpaidCustomerIds[(int) ($bill['customer_id'] ?? 0)] = true;
    }
}
$unpaidCustomerCount = count($unpaidCustomerIds);
$paidInvoiceCount = 0;
foreach ($bills as $billRow) {
    if ((string) ($billRow['status'] ?? '') === 'paid') {
        $paidInvoiceCount++;
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-ornament page-ornament--rose mb-4">
  <div class="page-ornament-kicker"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Pusat Invoice</div>
  <h1 class="page-ornament-title">Invoice dan Pembayaran</h1>
  <p class="page-ornament-text">Filter tagihan, monitor piutang, dan proses pembayaran dalam tampilan elegan dengan sentuhan batik emas.</p>
</section>

<section class="isp-mini-grid mb-4">
  <div class="isp-mini-card isp-mini-card--red">
    <div class="isp-mini-card__label">Invoice Belum Lunas</div>
    <div class="isp-mini-card__value"><?= $unpaidCount ?></div>
    <div class="isp-mini-card__note">Fokus follow up piutang</div>
  </div>
  <div class="isp-mini-card isp-mini-card--blue">
    <div class="isp-mini-card__label">Invoice Lunas</div>
    <div class="isp-mini-card__value"><?= $paidInvoiceCount ?></div>
    <div class="isp-mini-card__note">Sudah berhasil ditagihkan</div>
  </div>
  <div class="isp-mini-card isp-mini-card--amber">
    <div class="isp-mini-card__label">Pelanggan Menunggak</div>
    <div class="isp-mini-card__value"><?= $unpaidCustomerCount ?></div>
    <div class="isp-mini-card__note">Perlu pengingat pembayaran</div>
  </div>
  <div class="isp-mini-card isp-mini-card--emerald">
    <div class="isp-mini-card__label">Total Piutang</div>
    <div class="isp-mini-card__value"><?= e(rupiah($totalUnpaid)) ?></div>
    <div class="isp-mini-card__note">Akumulasi filter saat ini</div>
  </div>
</section>

<section class="bg-white rounded-xl shadow p-4 mb-4 luxe-card luxe-card--table">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="font-semibold mb-0">Daftar Invoice RT/RW Net</h2>
    <div class="small text-secondary">Filter pelanggan belum bayar per bulan, lalu lanjut cetak dan proses pembayaran.</div>
  </div>
  <form class="grid md:grid-cols-5 gap-2 text-sm">
    <select name="status" class="border rounded px-3 py-2">
      <option value="">Semua Status</option>
      <option value="paid" <?= $filterStatus === 'paid' ? 'selected' : '' ?>>Lunas</option>
      <option value="unpaid" <?= $filterStatus === 'unpaid' ? 'selected' : '' ?>>Belum Lunas</option>
    </select>
    <input type="number" name="year" value="<?= $filterYear ?: '' ?>" placeholder="Tahun" class="border rounded px-3 py-2">
    <select name="month" class="border rounded px-3 py-2">
      <option value="">Semua Bulan</option>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $filterMonth === $m ? 'selected' : '' ?>><?= e(monthName($m)) ?></option>
      <?php endfor; ?>
    </select>
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari customer / PPPoE / invoice" class="border rounded px-3 py-2">
    <div class="flex gap-2">
      <button class="btn btn-primary px-4 py-2 w-full"><i class="fa-solid fa-filter me-1"></i>Filter</button>
      <a href="bills.php" class="btn btn-outline-secondary px-4 py-2"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
    </div>
  </form>

  <div class="mt-4 stats-grid-4 text-sm">
    <div class="info-card"><div class="info-label">Total Invoice</div><div class="info-value"><?= count($bills) ?></div></div>
    <div class="info-card"><div class="info-label">Pelanggan Belum Bayar</div><div class="info-value text-amber-600"><?= $unpaidCustomerCount ?></div></div>
    <div class="info-card"><div class="info-label">Total Piutang Filter</div><div class="info-value text-amber-600"><?= e(rupiah($totalUnpaid)) ?></div></div>
    <div class="info-card"><div class="info-label">Status App</div><div class="info-note">Billing inti aktif</div></div>
  </div>
</section>

<section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--table">
  <div class="overflow-auto table-wrap">
    <table class="min-w-full text-sm js-data-table table-soft table-card-mode" data-page-size="5">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Invoice</th>
          <th class="py-2 pr-3">Customer</th>
          <th class="py-2 pr-3">Paket / PPPoE</th>
          <th class="py-2 pr-3">Periode</th>
          <th class="py-2 pr-3">Tagihan</th>
          <th class="py-2 pr-3">Status</th>
          <th class="py-2 pr-3">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bills as $bill): ?>
          <tr class="border-b align-top">
            <td class="py-2 pr-3">
              <div class="font-semibold"><?= e(ensureInvoiceNumber($pdo, (int) $bill['id'])) ?></div>
              <div class="text-xs text-slate-500">Jatuh tempo: <?= e(formatDateId((string) ($bill['due_date'] ?? ''), '-')) ?></div>
            </td>
            <td class="py-2 pr-3">
              <div class="font-semibold"><?= e((string) ($bill['customer_name'] ?? '-')) ?></div>
              <div class="text-xs text-slate-500">Area: <?= e((string) ($bill['area'] ?? '-')) ?></div>
              <div class="text-xs text-slate-500">No/HP: <?= e((string) (($bill['customer_no'] ?: '-') . ' • ' . ($bill['phone'] ?: '-'))) ?></div>
            </td>
            <td class="py-2 pr-3">
              <div><?= e(packageLabel($bill)) ?></div>
              <div class="text-xs text-slate-500">Layanan: <?= e((string) ($bill['service_username'] ?: '-')) ?></div>
              <div class="text-xs text-slate-500">Catatan: <?= e((string) ($bill['mikrotik_profile_name'] ?: '-')) ?></div>
            </td>
            <td class="py-2 pr-3"><?= e(periodLabel((int) $bill['period_month'], (int) $bill['period_year'])) ?></td>
            <td class="py-2 pr-3">
              <div><?= e(rupiah((int) $bill['amount'])) ?></div>
              <div class="text-xs text-slate-500">Diskon: <?= e(rupiah(normalizeCurrencyInput($bill['discount_amount'] ?? 0))) ?></div>
              <div class="font-semibold">Net: <?= e(rupiah(invoiceNetAmount($bill))) ?></div>
            </td>
            <td class="py-2 pr-3">
              <span class="badge <?= ($bill['status'] ?? '') === 'paid' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= ($bill['status'] ?? '') === 'paid' ? 'Lunas' : 'Belum Lunas' ?></span>
              <div class="text-xs text-slate-500 mt-1">Bayar: <?= e(formatDateId((string) ($bill['paid_at'] ?? ''), '-')) ?></div>
              <div class="text-xs mt-1"><span class="badge <?= (($bill['status'] ?? '') === 'suspended') ? 'text-bg-danger' : 'text-bg-success' ?>"><?= (($bill['status'] ?? '') === 'suspended') ? 'Suspend' : 'Normal' ?></span></div>
            </td>
            <td class="py-2 pr-3">
              <a class="btn btn-sm btn-outline-secondary inline-block mb-1" href="bill_print.php?id=<?= (int) $bill['id'] ?>" target="_blank"><i class="fa-solid fa-print me-1"></i>Cetak Invoice</a>
              <?php if (($bill['status'] ?? '') === 'paid'): ?>
                <a class="btn btn-sm btn-outline-primary inline-block mb-1" href="receipt.php?id=<?= (int) $bill['id'] ?>" target="_blank"><i class="fa-solid fa-receipt me-1"></i>Kwitansi</a>
              <?php endif; ?>

              <?php if (($bill['status'] ?? '') === 'unpaid'): ?>
                <form method="post" class="space-y-1 mt-2">
                  <input type="hidden" name="action" value="mark_paid">
                  <input type="hidden" name="id" value="<?= (int) $bill['id'] ?>">
                  <input type="date" name="payment_date" class="border rounded px-2 py-1 w-full" value="<?= e(date('Y-m-d')) ?>">
                  <select name="payment_method" class="border rounded px-2 py-1 w-full">
                    <option value="cash">Cash</option>
                    <option value="transfer">Transfer</option>
                    <option value="qris">QRIS</option>
                  </select>
                  <input type="number" min="0" name="discount_amount" class="border rounded px-2 py-1 w-full" placeholder="Diskon opsional">
                  <input type="text" name="payment_note" class="border rounded px-2 py-1 w-full" placeholder="Catatan bayar opsional">
                  <button class="btn btn-primary btn-sm"><i class="fa-solid fa-circle-check me-1"></i>Tandai Lunas</button>
                </form>
              <?php else: ?>
                <form method="post" class="mt-2 inline-block" onsubmit="return confirm('Kembalikan invoice ke belum lunas?')">
                  <input type="hidden" name="action" value="mark_unpaid">
                  <input type="hidden" name="id" value="<?= (int) $bill['id'] ?>">
                  <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i>Batal Lunas</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$bills): ?>
          <tr><td colspan="7" class="py-4 text-slate-500">Belum ada invoice. Generate billing dulu dari menu Generate Billing.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
