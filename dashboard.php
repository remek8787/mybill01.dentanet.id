<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth();

$pdo = db();
$currentMonth = (int) date('n');
$currentYear = (int) date('Y');
$monthLabel = periodLabel($currentMonth, $currentYear);

$customerCount = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$packageCount = (int) $pdo->query('SELECT COUNT(*) FROM packages WHERE is_active = 1')->fetchColumn();
$invoiceCount = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$unpaidCount = (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'unpaid'")->fetchColumn();
$unpaidTotal = (int) $pdo->query("SELECT COALESCE(SUM(amount - discount_amount),0) FROM invoices WHERE status = 'unpaid'")->fetchColumn();
$paidTotal = (int) $pdo->query("SELECT COALESCE(SUM(amount - discount_amount),0) FROM invoices WHERE status = 'paid'")->fetchColumn();
$isolatedCount = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'suspended'")->fetchColumn();

$stmtPaidMonth = $pdo->prepare("SELECT COUNT(DISTINCT customer_id) FROM invoices WHERE status = 'paid' AND period_month = :month AND period_year = :year");
$stmtPaidMonth->execute([':month' => $currentMonth, ':year' => $currentYear]);
$paidCustomerCount = (int) $stmtPaidMonth->fetchColumn();

$stmtUnpaidMonth = $pdo->prepare("SELECT COUNT(DISTINCT customer_id) FROM invoices WHERE status = 'unpaid' AND period_month = :month AND period_year = :year");
$stmtUnpaidMonth->execute([':month' => $currentMonth, ':year' => $currentYear]);
$unpaidCustomerCount = (int) $stmtUnpaidMonth->fetchColumn();

$stmtPaidMonthRevenue = $pdo->prepare("SELECT COALESCE(SUM(amount - discount_amount),0) FROM invoices WHERE status = 'paid' AND period_month = :month AND period_year = :year");
$stmtPaidMonthRevenue->execute([':month' => $currentMonth, ':year' => $currentYear]);
$paidMonthRevenue = (int) $stmtPaidMonthRevenue->fetchColumn();

$recentInvoices = $pdo->query('SELECT i.*, c.full_name AS customer_name, c.area, c.service_username, p.name AS package_name, p.speed
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN packages p ON p.id = i.package_id
    ORDER BY i.id DESC LIMIT 10')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="isp-dashboard-head mb-4">
  <div class="isp-dashboard-head__left">
    <div class="isp-dashboard-head__badge"><i class="fa-solid fa-compass-drafting me-2"></i>Dashboard Billing</div>
    <h2 class="isp-dashboard-head__title"><?= e(companyName()) ?></h2>
    <p class="isp-dashboard-head__text">Tema diarahkan ke panel billing ISP dengan nuansa batik gelap, tile warna-warni, dan fokus ke data yang cepat kebaca.</p>
  </div>
  <div class="isp-dashboard-head__right">
    <a href="customers.php" class="btn btn-light isp-action-btn"><i class="fa-solid fa-users me-2"></i>Pelanggan</a>
    <a href="readings.php" class="btn btn-light isp-action-btn"><i class="fa-solid fa-file-circle-plus me-2"></i>Generate Billing</a>
    <button type="button" class="btn btn-light isp-action-btn" data-bs-toggle="modal" data-bs-target="#tutorialModal"><i class="fa-solid fa-book-open me-2"></i>Tutorial</button>
  </div>
</section>

<section class="isp-tile-grid mb-4">
  <a class="isp-stat-tile isp-stat-tile--green" href="customers.php">
    <div class="isp-stat-tile__value"><?= $customerCount ?></div>
    <div class="isp-stat-tile__label">Data Pelanggan</div>
    <div class="isp-stat-tile__sub">Pelanggan aktif + tersimpan</div>
    <div class="isp-stat-tile__icon"><i class="fa-solid fa-users"></i></div>
    <div class="isp-stat-tile__footer">Selengkapnya <i class="fa-solid fa-circle-arrow-right"></i></div>
  </a>

  <a class="isp-stat-tile isp-stat-tile--blue" href="bills.php?status=paid&month=<?= $currentMonth ?>&year=<?= $currentYear ?>">
    <div class="isp-stat-tile__value"><?= $paidCustomerCount ?> | <?= e(rupiah($paidMonthRevenue)) ?></div>
    <div class="isp-stat-tile__label">Pelanggan Sudah Lunas</div>
    <div class="isp-stat-tile__sub">Periode <?= e($monthLabel) ?></div>
    <div class="isp-stat-tile__icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
    <div class="isp-stat-tile__footer">Selengkapnya <i class="fa-solid fa-circle-arrow-right"></i></div>
  </a>

  <a class="isp-stat-tile isp-stat-tile--red" href="bills.php?status=unpaid&month=<?= $currentMonth ?>&year=<?= $currentYear ?>">
    <div class="isp-stat-tile__value"><?= $unpaidCustomerCount ?> | <?= e(rupiah($unpaidTotal)) ?></div>
    <div class="isp-stat-tile__label">Pelanggan Belum Lunas</div>
    <div class="isp-stat-tile__sub">Periode <?= e($monthLabel) ?></div>
    <div class="isp-stat-tile__icon"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="isp-stat-tile__footer">Selengkapnya <i class="fa-solid fa-circle-arrow-right"></i></div>
  </a>

  <a class="isp-stat-tile isp-stat-tile--green-dark" href="packages.php">
    <div class="isp-stat-tile__value"><?= $packageCount ?></div>
    <div class="isp-stat-tile__label">Paket Aktif</div>
    <div class="isp-stat-tile__sub">Paket internet siap jual</div>
    <div class="isp-stat-tile__icon"><i class="fa-solid fa-box-open"></i></div>
    <div class="isp-stat-tile__footer">Selengkapnya <i class="fa-solid fa-circle-arrow-right"></i></div>
  </a>

  <div class="isp-stat-tile isp-stat-tile--cyan">
    <div class="isp-stat-tile__value"><?= $invoiceCount ?></div>
    <div class="isp-stat-tile__label">Total Invoice</div>
    <div class="isp-stat-tile__sub">Semua periode tersimpan</div>
    <div class="isp-stat-tile__icon"><i class="fa-regular fa-file-lines"></i></div>
    <div class="isp-stat-tile__footer">Billing tersusun rapi <i class="fa-solid fa-check"></i></div>
  </div>

  <div class="isp-stat-tile isp-stat-tile--yellow">
    <div class="isp-stat-tile__value"><?= $unpaidCount ?></div>
    <div class="isp-stat-tile__label">Invoice Open</div>
    <div class="isp-stat-tile__sub">Belum lunas semua periode</div>
    <div class="isp-stat-tile__icon"><i class="fa-regular fa-folder-open"></i></div>
    <div class="isp-stat-tile__footer">Prioritas follow up <i class="fa-solid fa-circle-arrow-right"></i></div>
  </div>

  <div class="isp-stat-tile isp-stat-tile--navy">
    <div class="isp-stat-tile__value"><?= $paidCustomerCount ?></div>
    <div class="isp-stat-tile__label">Status Langganan On</div>
    <div class="isp-stat-tile__sub">Sudah lunas bulan ini</div>
    <div class="isp-stat-tile__icon"><i class="fa-solid fa-satellite-dish"></i></div>
    <div class="isp-stat-tile__footer"><?= e($monthLabel) ?> <i class="fa-solid fa-circle-arrow-right"></i></div>
  </div>

  <div class="isp-stat-tile isp-stat-tile--pink">
    <div class="isp-stat-tile__value"><?= $isolatedCount ?></div>
    <div class="isp-stat-tile__label">Status Suspend</div>
    <div class="isp-stat-tile__sub">Pelanggan suspend di app</div>
    <div class="isp-stat-tile__icon"><i class="fa-regular fa-eye-slash"></i></div>
    <div class="isp-stat-tile__footer">Monitoring status <i class="fa-solid fa-circle-arrow-right"></i></div>
  </div>
</section>

<section class="grid xl:grid-cols-[1.1fr_0.9fr] gap-4 mb-4">
  <div class="isp-summary-tile isp-summary-tile--teal">
    <div class="isp-summary-tile__title">Ringkasan Pemasukan</div>
    <div class="isp-summary-tile__big"><?= e(rupiah($paidMonthRevenue)) ?></div>
    <div class="isp-summary-tile__line">Pemasukan bulan ini | Periode <?= e($monthLabel) ?></div>
    <div class="isp-summary-tile__mini mt-3"><?= e(rupiah($paidTotal)) ?></div>
    <div class="isp-summary-tile__line">Total pemasukan lunas semua periode</div>
    <div class="isp-summary-tile__mini mt-4"><?= e(rupiah($unpaidTotal)) ?></div>
    <div class="isp-summary-tile__line">Balance piutang saat ini</div>
  </div>

  <div class="grid gap-4">
    <div class="isp-panel-card isp-panel-card--dark">
      <div class="isp-panel-card__title">Arah aplikasi saat ini</div>
      <ul class="isp-panel-list mb-0">
        <li>Fokus ke billing inti, pelanggan, paket, invoice, dan pembayaran.</li>
        <li>UI diarahkan ke gaya panel ISP, lebih tegas dan mudah dipantau.</li>
        <li>Sentuhan batik dipakai sebagai motif halus, bukan dekor berlebihan.</li>
      </ul>
    </div>

    <div class="isp-panel-card isp-panel-card--light">
      <div class="isp-panel-card__title">Checklist admin</div>
      <ul class="isp-panel-list mb-0">
        <li>Cek pelanggan belum lunas bulan berjalan.</li>
        <li>Generate billing di awal bulan.</li>
        <li>Print invoice atau kwitansi saat ada pembayaran.</li>
      </ul>
    </div>
  </div>
</section>

<section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--table">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="font-semibold mb-0">Invoice Terbaru</h2>
    <a href="bills.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-table-list me-1"></i>Lihat Semua</a>
  </div>
  <div class="overflow-auto table-wrap">
    <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Periode</th>
          <th class="py-2 pr-3">Customer</th>
          <th class="py-2 pr-3">Paket / Layanan</th>
          <th class="py-2 pr-3">Area</th>
          <th class="py-2 pr-3">Nominal</th>
          <th class="py-2 pr-3">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentInvoices as $invoice): ?>
          <tr class="border-b align-top">
            <td class="py-2 pr-3"><?= e(periodLabel((int) $invoice['period_month'], (int) $invoice['period_year'])) ?></td>
            <td class="py-2 pr-3"><?= e((string) ($invoice['customer_name'] ?? '-')) ?></td>
            <td class="py-2 pr-3">
              <div><?= e(packageLabel($invoice)) ?></div>
              <div class="text-xs text-slate-500">Layanan: <?= e((string) ($invoice['service_username'] ?: '-')) ?></div>
            </td>
            <td class="py-2 pr-3"><?= e((string) ($invoice['area'] ?? '-')) ?></td>
            <td class="py-2 pr-3"><?= e(rupiah(invoiceNetAmount($invoice))) ?></td>
            <td class="py-2 pr-3">
              <span class="badge <?= ($invoice['status'] ?? '') === 'paid' ? 'text-bg-success' : 'text-bg-warning' ?>">
                <?= ($invoice['status'] ?? '') === 'paid' ? 'Lunas' : 'Belum Lunas' ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recentInvoices): ?>
          <tr><td colspan="6" class="py-4 text-slate-500">Belum ada invoice. Mulai dari paket, pelanggan, lalu generate billing.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="modal fade" id="tutorialModal" tabindex="-1" aria-labelledby="tutorialModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header">
        <div>
          <h5 class="modal-title fw-bold" id="tutorialModalLabel">Tutorial Penggunaan <?= e(companyName()) ?></h5>
          <div class="small text-secondary">Versi ringkas untuk admin billing</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3">
        <div class="rounded-4 border border-primary-subtle bg-primary-subtle bg-opacity-50 p-3 mb-4">
          <div class="fw-semibold mb-2">Urutan paling aman</div>
          <div class="small text-secondary">Buat <b>Paket</b> dulu, lalu tambah <b>Pelanggan</b>, setelah itu buka <b>Generate Billing</b>, dan proses pembayaran dari menu <b>Invoice</b>.</div>
        </div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">1</div><div><div class="tutorial-step-title">Siapkan paket internet</div><div class="tutorial-step-text">Masuk ke menu <b>Paket</b> dan isi nama paket, speed, harga, serta kode internal jika diperlukan.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">2</div><div><div class="tutorial-step-title">Tambah pelanggan</div><div class="tutorial-step-text">Isi nama, alamat, no HP, area, paket, due day, dan catatan layanan pelanggan.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">3</div><div><div class="tutorial-step-title">Generate billing bulanan</div><div class="tutorial-step-text">Pilih bulan, tahun, dan due date. Sistem akan membuat invoice untuk pelanggan aktif.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">4</div><div><div class="tutorial-step-title">Proses pembayaran</div><div class="tutorial-step-text">Tandai invoice lunas dari menu <b>Invoice</b>, isi metode bayar, dan cetak invoice atau kwitansi.</div></div></div>
        <div class="rounded-4 border p-3 mt-4 bg-light">
          <div class="fw-semibold mb-2">Checklist harian</div>
          <ul class="mb-0 small text-secondary ps-3">
            <li>Cek pelanggan belum lunas.</li>
            <li>Follow up piutang bulan berjalan.</li>
            <li>Print invoice saat penagihan lapangan.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
