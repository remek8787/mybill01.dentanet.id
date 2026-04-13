<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'staff']);

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Invoice tidak ditemukan.');
    header('Location: bills.php');
    exit;
}

$stmt = $pdo->prepare('SELECT i.*, c.full_name AS customer_name, c.address, c.phone, c.area, c.customer_no,
        c.api_customer_id, c.router_name, c.service_username, c.mikrotik_profile_name,
        p.name AS package_name, p.speed
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN packages p ON p.id = i.package_id
    WHERE i.id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flash('error', 'Invoice tidak ditemukan.');
    header('Location: bills.php');
    exit;
}

$invoiceNo = ensureInvoiceNumber($pdo, (int) $invoice['id']);
$barcode = barcodeSvg($invoiceNo, 78);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoice <?= e($invoiceNo) ?></title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; margin: 0; background: #f3f4f6; color: #111827; padding: 14px; }
    .toolbar { max-width: 980px; margin: 0 auto 12px; display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
    .btn { display: inline-block; text-decoration: none; border: 0; border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
    .btn-dark { background: #0f172a; color: #fff; }
    .btn-light { background: #e2e8f0; color: #0f172a; }
    .invoice { max-width: 980px; margin: 0 auto; background: #fff; border: 1px solid #111827; padding: 28px; }
    .head { display: flex; justify-content: space-between; gap: 20px; border-bottom: 1px dashed #64748b; padding-bottom: 14px; margin-bottom: 16px; }
    .title { font-size: 28px; font-weight: 800; text-transform: uppercase; }
    .sub { color: #64748b; font-size: 13px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 18px; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 8px 0; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
    td:first-child, th:first-child { color: #64748b; width: 180px; }
    .text-right { text-align: right; }
    .total { font-size: 18px; font-weight: 800; }
    .note { margin-top: 20px; font-size: 12px; color: #475569; }
    .barcode-wrap { margin: 18px 0 10px; border: 1px dashed #cbd5e1; padding: 12px; text-align: center; }
    .barcode-wrap svg { max-width: 100%; height: auto; }
    .meta-chip { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #e2e8f0; font-size: 12px; font-weight: 700; margin-bottom: 8px; }
    @media print {
      body { background: #fff; padding: 0; }
      .no-print { display: none !important; }
      .invoice { border: 0; margin: 0; max-width: none; }
    }
  </style>
</head>
<body>
  <div class="toolbar no-print">
    <a href="bills.php" class="btn btn-light">← Kembali</a>
    <button type="button" class="btn btn-dark" onclick="window.print()">Print / PDF</button>
  </div>
  <div class="invoice">
    <div class="head">
      <div>
        <div class="title">Invoice Billing</div>
        <div class="sub"><?= e(companyName()) ?></div>
        <div class="sub"><?= e(appSettingText('company_address', '-')) ?></div>
        <div class="sub">Telp: <?= e(appSettingText('company_phone', '-')) ?></div>
      </div>
      <div class="text-right">
        <div class="meta-chip"><?= ($invoice['status'] ?? '') === 'paid' ? 'LUNAS' : 'BELUM LUNAS' ?></div>
        <div><b><?= e($invoiceNo) ?></b></div>
        <div class="sub">Periode: <?= e(periodLabel((int) $invoice['period_month'], (int) $invoice['period_year'])) ?></div>
        <div class="sub">Jatuh tempo: <?= e(formatDateId((string) ($invoice['due_date'] ?? ''), '-')) ?></div>
      </div>
    </div>

    <div class="grid">
      <table>
        <tr><td>Pelanggan</td><td><?= e((string) ($invoice['customer_name'] ?? '-')) ?></td></tr>
        <tr><td>No Pelanggan</td><td><?= e((string) ($invoice['customer_no'] ?: '-')) ?></td></tr>
        <tr><td>Area</td><td><?= e((string) ($invoice['area'] ?: '-')) ?></td></tr>
        <tr><td>Alamat</td><td><?= e((string) ($invoice['address'] ?: '-')) ?></td></tr>
        <tr><td>No HP</td><td><?= e((string) ($invoice['phone'] ?: '-')) ?></td></tr>
      </table>
      <table>
        <tr><td>Paket</td><td><?= e(packageLabel($invoice)) ?></td></tr>
        <tr><td>ID Layanan</td><td><?= e((string) ($invoice['service_username'] ?: '-')) ?></td></tr>
        <tr><td>Catatan Layanan</td><td><?= e((string) ($invoice['mikrotik_profile_name'] ?: '-')) ?></td></tr>
        <tr><td>Lokasi / Router</td><td><?= e((string) ($invoice['router_name'] ?: '-')) ?></td></tr>
        <tr><td>Referensi</td><td><?= e((string) ($invoice['api_customer_id'] ?: '-')) ?></td></tr>
      </table>
    </div>

    <div class="barcode-wrap">
      <?= $barcode ?>
      <div class="sub">Barcode nomor invoice untuk referensi cepat admin / penagihan lapangan.</div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Uraian</th>
          <th class="text-right">Nominal</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Tagihan paket internet <?= e(packageLabel($invoice)) ?> periode <?= e(periodLabel((int) $invoice['period_month'], (int) $invoice['period_year'])) ?></td>
          <td class="text-right"><?= e(rupiah((int) $invoice['amount'])) ?></td>
        </tr>
        <tr>
          <td>Diskon</td>
          <td class="text-right">- <?= e(rupiah(normalizeCurrencyInput($invoice['discount_amount'] ?? 0))) ?></td>
        </tr>
        <tr>
          <td class="total">Total Tagihan</td>
          <td class="text-right total"><?= e(rupiah(invoiceNetAmount($invoice))) ?></td>
        </tr>
      </tbody>
    </table>

    <div class="note">
      <?= e(appSettingText('company_note', 'Terima kasih telah menggunakan layanan kami.')) ?><br>
      Tanggal cetak: <?= e(date('d-m-Y H:i')) ?>
    </div>
  </div>
</body>
</html>
