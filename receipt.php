<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'staff']);

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Kwitansi tidak ditemukan.');
    header('Location: bills.php');
    exit;
}

$stmt = $pdo->prepare('SELECT i.*, c.full_name AS customer_name, c.phone, c.area, c.customer_no,
        p.name AS package_name, p.speed
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN packages p ON p.id = i.package_id
    WHERE i.id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flash('error', 'Kwitansi tidak ditemukan.');
    header('Location: bills.php');
    exit;
}

if (($invoice['status'] ?? 'unpaid') !== 'paid') {
    flash('error', 'Kwitansi hanya tersedia untuk invoice yang sudah lunas.');
    header('Location: bills.php');
    exit;
}

$invoiceNo = ensureInvoiceNumber($pdo, (int) $invoice['id']);
$receiptNo = 'KWT/' . substr($invoiceNo, 4);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kwitansi <?= e($receiptNo) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8fafc; color: #0f172a; }
    .receipt-card { max-width: 900px; margin: 24px auto; background: #fff; border-radius: 20px; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12); overflow: hidden; }
    .receipt-head { background: linear-gradient(135deg, #0f172a, #1d4ed8); color: #fff; padding: 28px; }
    .receipt-body { padding: 28px; }
    .label { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
    .value { font-weight: 600; }
    .summary-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; }
    @media print { body { background: #fff; } .no-print { display: none !important; } .receipt-card { margin: 0; box-shadow: none; border-radius: 0; } }
  </style>
</head>
<body>
  <div class="receipt-card">
    <div class="receipt-head d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <div class="fs-3 fw-bold">Kwitansi Pembayaran</div>
        <div class="opacity-75"><?= e(companyName()) ?></div>
      </div>
      <div class="text-md-end">
        <div class="small opacity-75">No. Kwitansi</div>
        <div class="fs-5 fw-semibold"><?= e($receiptNo) ?></div>
        <div class="small mt-2">Tanggal Bayar: <?= e(formatDateId((string) ($invoice['paid_at'] ?? ''), '-')) ?></div>
      </div>
    </div>
    <div class="receipt-body">
      <div class="d-flex justify-content-between gap-2 flex-wrap mb-4 no-print">
        <a href="bills.php" class="btn btn-outline-secondary">← Kembali</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print / Simpan PDF</button>
      </div>
      <div class="row g-4 mb-4">
        <div class="col-md-6">
          <div class="summary-box h-100">
            <div class="label">Pelanggan</div>
            <div class="value fs-5"><?= e((string) ($invoice['customer_name'] ?? '-')) ?></div>
            <div class="mt-2"><span class="label">No Pelanggan</span><div><?= e((string) ($invoice['customer_no'] ?: '-')) ?></div></div>
            <div class="mt-2"><span class="label">Area</span><div><?= e((string) ($invoice['area'] ?: '-')) ?></div></div>
            <div class="mt-2"><span class="label">No HP</span><div><?= e((string) ($invoice['phone'] ?: '-')) ?></div></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="summary-box h-100">
            <div class="label">Detail Pembayaran</div>
            <div class="mt-2"><span class="label">Invoice</span><div class="value"><?= e($invoiceNo) ?></div></div>
            <div class="mt-2"><span class="label">Periode</span><div><?= e(periodLabel((int) $invoice['period_month'], (int) $invoice['period_year'])) ?></div></div>
            <div class="mt-2"><span class="label">Paket</span><div><?= e(packageLabel($invoice)) ?></div></div>
            <div class="mt-2"><span class="label">Metode Pembayaran</span><div><?= e(strtoupper((string) ($invoice['payment_method'] ?: '-'))) ?></div></div>
            <div class="mt-2"><span class="label">Catatan</span><div><?= e((string) ($invoice['payment_note'] ?: '-')) ?></div></div>
          </div>
        </div>
      </div>
      <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr><th>Uraian</th><th class="text-end">Nominal</th></tr>
          </thead>
          <tbody>
            <tr><td>Total Tagihan</td><td class="text-end"><?= e(rupiah((int) $invoice['amount'])) ?></td></tr>
            <tr><td>Diskon Pembayaran</td><td class="text-end text-danger">- <?= e(rupiah(normalizeCurrencyInput($invoice['discount_amount'] ?? 0))) ?></td></tr>
            <tr class="table-success fw-bold"><td>Total Dibayar</td><td class="text-end"><?= e(rupiah(invoiceNetAmount($invoice))) ?></td></tr>
          </tbody>
        </table>
      </div>
      <div class="row g-4">
        <div class="col-md-7">
          <div class="small text-secondary">Kwitansi ini menjadi bukti pembayaran sah untuk layanan internet periode <b><?= e(periodLabel((int) $invoice['period_month'], (int) $invoice['period_year'])) ?></b>.</div>
        </div>
        <div class="col-md-5 text-md-end">
          <div class="label">Status Pembayaran</div>
          <div class="fs-4 fw-bold text-success">LUNAS</div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
