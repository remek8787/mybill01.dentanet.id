<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth();

$pdo = db();
$user = currentUser();

$customerCount = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$packageCount = (int) $pdo->query('SELECT COUNT(*) FROM packages WHERE is_active = 1')->fetchColumn();
$invoiceCount = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$unpaidCount = (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'unpaid'")->fetchColumn();
$unpaidTotal = (int) $pdo->query("SELECT COALESCE(SUM(amount - discount_amount),0) FROM invoices WHERE status = 'unpaid'")->fetchColumn();
$paidTotal = (int) $pdo->query("SELECT COALESCE(SUM(amount - discount_amount),0) FROM invoices WHERE status = 'paid'")->fetchColumn();

$recentInvoices = $pdo->query('SELECT i.*, c.full_name AS customer_name, c.area, p.name AS package_name, p.speed
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN packages p ON p.id = i.package_id
    ORDER BY i.id DESC LIMIT 10')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="dashboard-hero-card mb-4 dashboard-hero-card--compact">
  <div class="dashboard-hero-compact-row">
    <div class="dashboard-hero-compact-main">
      <div class="dashboard-hero-logo-inline">
        <img src="assets/app-logo.svg" alt="Logo <?= e(companyName()) ?>" class="dashboard-hero-logo dashboard-hero-logo--mini">
      </div>
      <div>
        <div class="dashboard-hero-kicker">Dashboard Billing</div>
        <h2 class="dashboard-hero-title dashboard-hero-title--compact"><?= e(companyName()) ?></h2>
      </div>
    </div>
    <div class="dashboard-hero-actions dashboard-hero-actions--compact">
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tutorialModal">
        <i class="bi bi-journal-text me-2"></i>Tutorial
      </button>
      <a href="settings.php" class="btn btn-outline-primary">
        <i class="bi bi-link-45deg me-2"></i>Setting API
      </a>
      <a href="readings.php" class="btn btn-outline-secondary">
        <i class="bi bi-calendar2-check me-2"></i>Generate Billing
      </a>
    </div>
  </div>
</section>

<section class="grid lg:grid-cols-2 gap-4 mb-4">
  <div class="bg-white rounded-xl shadow p-4 dashboard-update-card dashboard-update-card--compact">
    <div class="dashboard-section-head dashboard-section-head--compact">
      <div>
        <div class="dashboard-section-kicker">Versi Awal</div>
        <h3 class="dashboard-section-title dashboard-section-title--compact">Yang sudah siap dipakai</h3>
      </div>
      <span class="dashboard-update-badge">V1</span>
    </div>
    <ul class="dashboard-bullet-list">
      <li>Data pelanggan RT/RW Net</li>
      <li>Data paket internet dan harga bulanan</li>
      <li>Generate invoice bulanan tanpa phpMyAdmin</li>
      <li>Pencatatan pembayaran, cetak invoice, dan kwitansi</li>
      <li>Pengaturan API yang bisa diubah kapan saja</li>
    </ul>
  </div>

  <div class="bg-white rounded-xl shadow p-4 dashboard-update-card dashboard-update-card--compact">
    <div class="dashboard-section-head dashboard-section-head--compact">
      <div>
        <div class="dashboard-section-kicker">Alur Cepat</div>
        <h3 class="dashboard-section-title dashboard-section-title--compact">Pakai harian</h3>
      </div>
    </div>
    <ol class="dashboard-inline-steps">
      <li><b>Paket</b> → siapkan paket internet dan harga</li>
      <li><b>Pelanggan</b> → tambah pelanggan, area, API ID, dan due day</li>
      <li><b>Generate Billing</b> → buat invoice bulan berjalan</li>
      <li><b>Invoice</b> → proses bayar, diskon, print invoice / kwitansi</li>
    </ol>
  </div>
</section>

<div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
  <div class="stat-card p-4">
    <p class="text-sm text-slate-500">Total Pelanggan</p>
    <p class="text-3xl font-bold"><?= $customerCount ?></p>
  </div>
  <div class="stat-card p-4">
    <p class="text-sm text-slate-500">Paket Aktif</p>
    <p class="text-3xl font-bold text-sky-600"><?= $packageCount ?></p>
  </div>
  <div class="stat-card p-4">
    <p class="text-sm text-slate-500">Invoice Belum Lunas</p>
    <p class="text-3xl font-bold text-amber-600"><?= $unpaidCount ?></p>
  </div>
  <div class="stat-card p-4">
    <p class="text-sm text-slate-500">Piutang Saat Ini</p>
    <p class="text-3xl font-bold"><?= e(rupiah($unpaidTotal)) ?></p>
    <div class="text-xs text-slate-500 mt-1">Sudah dibayar: <?= e(rupiah($paidTotal)) ?></div>
  </div>
</div>

<div class="bg-white rounded-xl shadow p-4">
  <h2 class="text-lg font-semibold mb-3">Invoice Terbaru</h2>
  <div class="overflow-auto table-wrap">
    <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Periode</th>
          <th class="py-2 pr-3">Customer</th>
          <th class="py-2 pr-3">Paket</th>
          <th class="py-2 pr-3">Area</th>
          <th class="py-2 pr-3">Nominal</th>
          <th class="py-2 pr-3">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentInvoices as $invoice): ?>
          <tr class="border-b">
            <td class="py-2 pr-3"><?= e(periodLabel((int) $invoice['period_month'], (int) $invoice['period_year'])) ?></td>
            <td class="py-2 pr-3"><?= e((string) ($invoice['customer_name'] ?? '-')) ?></td>
            <td class="py-2 pr-3"><?= e(packageLabel($invoice)) ?></td>
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
</div>

<div class="modal fade" id="tutorialModal" tabindex="-1" aria-labelledby="tutorialModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header">
        <div>
          <h5 class="modal-title fw-bold" id="tutorialModalLabel">Tutorial Penggunaan <?= e(companyName()) ?></h5>
          <div class="small text-secondary">Versi mudah untuk admin RT/RW Net</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3">
        <div class="rounded-4 border border-primary-subtle bg-primary-subtle bg-opacity-50 p-3 mb-4">
          <div class="fw-semibold mb-2">Mulai paling gampang</div>
          <div class="small text-secondary">Siapkan <b>Paket</b> dulu, lalu tambah <b>Pelanggan</b>, setelah itu buka <b>Generate Billing</b> untuk membuat invoice bulanan, lalu proses pembayaran dari menu <b>Invoice</b>.</div>
        </div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">1</div><div><div class="tutorial-step-title">Isi paket internet</div><div class="tutorial-step-text">Masuk ke menu <b>Paket</b> dan buat daftar paket, speed, harga, serta kode API jika ada.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">2</div><div><div class="tutorial-step-title">Tambah pelanggan</div><div class="tutorial-step-text">Isi nama, area, paket yang dipakai, customer ID API, router name, due day, dan status pelanggan.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">3</div><div><div class="tutorial-step-title">Setting API bisa diubah</div><div class="tutorial-step-text">Buka menu <b>Pengaturan</b> untuk mengisi base URL, token, username, password, secret, dan catatan integrasi.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">4</div><div><div class="tutorial-step-title">Generate billing bulanan</div><div class="tutorial-step-text">Pilih bulan, tahun, dan due date. Bisa generate untuk semua pelanggan aktif atau satu pelanggan tertentu.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">5</div><div><div class="tutorial-step-title">Catat pembayaran</div><div class="tutorial-step-text">Dari menu <b>Invoice</b>, tandai lunas, isi metode bayar, tanggal bayar, dan diskon jika diperlukan.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">6</div><div><div class="tutorial-step-title">Cetak invoice dan kwitansi</div><div class="tutorial-step-text">Invoice bisa dicetak kapan saja. Kwitansi otomatis tersedia untuk invoice yang sudah lunas.</div></div></div>
        <div class="rounded-4 border p-3 mt-4 bg-light">
          <div class="fw-semibold mb-2">Checklist harian</div>
          <ul class="mb-0 small text-secondary ps-3">
            <li>Cek pelanggan aktif dan paketnya.</li>
            <li>Generate billing awal bulan.</li>
            <li>Filter invoice belum lunas untuk follow up.</li>
            <li>Update setting API jika endpoint provider berubah.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
