<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth();

$pdo = db();
$user = currentUser();
$currentMonth = (int) date('n');
$currentYear = (int) date('Y');

$customerCount = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$packageCount = (int) $pdo->query('SELECT COUNT(*) FROM packages WHERE is_active = 1')->fetchColumn();
$invoiceCount = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$unpaidCount = (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'unpaid'")->fetchColumn();
$unpaidTotal = (int) $pdo->query("SELECT COALESCE(SUM(amount - discount_amount),0) FROM invoices WHERE status = 'unpaid'")->fetchColumn();
$paidTotal = (int) $pdo->query("SELECT COALESCE(SUM(amount - discount_amount),0) FROM invoices WHERE status = 'paid'")->fetchColumn();

$stmtUnpaidCustomerCount = $pdo->prepare("SELECT COUNT(DISTINCT customer_id) FROM invoices WHERE status = 'unpaid' AND period_month = :month AND period_year = :year");
$stmtUnpaidCustomerCount->execute([':month' => $currentMonth, ':year' => $currentYear]);
$unpaidCustomerCount = (int) $stmtUnpaidCustomerCount->fetchColumn();

$isolatedCount = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE isolated = 1 OR status = 'suspended'")->fetchColumn();
$mikrotikReady = mikrotikIsConfigured();
$mikrotikSummary = null;
$mikrotikError = '';

if ($mikrotikReady) {
    try {
        $mikrotikSummary = mikrotikTestConnection();
    } catch (Throwable $e) {
        $mikrotikError = $e->getMessage();
    }
}

$recentInvoices = $pdo->query('SELECT i.*, c.full_name AS customer_name, c.area, c.service_username, c.mikrotik_profile_name,
        p.name AS package_name, p.speed
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
        <img src="<?= e(brandingLogoPath()) ?>" alt="Logo <?= e(companyName()) ?>" class="dashboard-hero-logo dashboard-hero-logo--mini">
      </div>
      <div>
        <div class="dashboard-hero-kicker">Dashboard Billing</div>
        <h2 class="dashboard-hero-title dashboard-hero-title--compact"><?= e(companyName()) ?></h2>
        <div class="small text-secondary"><?= e(billingTagline()) ?></div>
      </div>
    </div>
    <div class="dashboard-hero-actions dashboard-hero-actions--compact">
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tutorialModal">
        <i class="bi bi-journal-text me-2"></i>Tutorial
      </button>
      <a href="mikrotik.php" class="btn btn-outline-primary">
        <i class="bi bi-hdd-network me-2"></i>MikroTik API
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
        <div class="dashboard-section-kicker">Versi Fondasi</div>
        <h3 class="dashboard-section-title dashboard-section-title--compact">Yang sudah siap dipakai sekarang</h3>
      </div>
      <span class="dashboard-update-badge">V1+</span>
    </div>
    <ul class="dashboard-bullet-list">
      <li>Data pelanggan RT/RW Net lengkap dengan username PPPoE / secret MikroTik</li>
      <li>Kontrol koneksi MikroTik API, port custom, enable/disable secret, pindah profile</li>
      <li>Generate invoice bulanan, filter pelanggan belum bayar per bulan, dan hitung isolir</li>
      <li>Cetak invoice dengan barcode nomor invoice</li>
      <li>Panel tutorial supaya admin tidak bingung saat onboarding</li>
    </ul>
  </div>

  <div class="bg-white rounded-xl shadow p-4 dashboard-update-card dashboard-update-card--compact">
    <div class="dashboard-section-head dashboard-section-head--compact">
      <div>
        <div class="dashboard-section-kicker">Alur Cepat</div>
        <h3 class="dashboard-section-title dashboard-section-title--compact">Pakai harian admin</h3>
      </div>
    </div>
    <ol class="dashboard-inline-steps">
      <li><b>Pengaturan</b> → isi host, port API, username, password MikroTik</li>
      <li><b>MikroTik API</b> → tes koneksi dan sinkron data secret / profile</li>
      <li><b>Pelanggan</b> → pilih secret PPPoE, profile, paket, dan identitas pelanggan</li>
      <li><b>Generate Billing</b> → buat invoice bulan berjalan</li>
      <li><b>Invoice</b> → filter yang belum bayar, print invoice, lalu isolir bila perlu</li>
    </ol>
  </div>
</section>

<div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4 mb-4">
  <div class="stat-card p-4">
    <p class="text-sm text-slate-500">Total Pelanggan</p>
    <p class="text-3xl font-bold"><?= $customerCount ?></p>
  </div>
  <div class="stat-card p-4">
    <p class="text-sm text-slate-500">Pelanggan Belum Bayar <?= e(monthName($currentMonth)) ?></p>
    <p class="text-3xl font-bold text-amber-600"><?= $unpaidCustomerCount ?></p>
  </div>
  <div class="stat-card p-4">
    <p class="text-sm text-slate-500">Pelanggan Diisolir</p>
    <p class="text-3xl font-bold text-red-600"><?= $isolatedCount ?></p>
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
  <div class="stat-card p-4">
    <p class="text-sm text-slate-500">MikroTik API</p>
    <?php if (!$mikrotikReady): ?>
      <p class="text-sm text-amber-700 fw-semibold">Belum dikonfigurasi</p>
      <div class="text-xs text-slate-500 mt-1">Isi host, port, username, dan password di Pengaturan.</div>
    <?php elseif ($mikrotikError !== ''): ?>
      <p class="text-sm text-red-700 fw-semibold">Koneksi gagal</p>
      <div class="text-xs text-slate-500 mt-1"><?= e($mikrotikError) ?></div>
    <?php else: ?>
      <p class="text-lg font-bold text-emerald-600"><?= e((string) ($mikrotikSummary['identity'] ?? 'Router OK')) ?></p>
      <div class="text-xs text-slate-500 mt-1">Version <?= e((string) ($mikrotikSummary['version'] ?? '-')) ?> • <?= e((string) ($mikrotikSummary['uptime'] ?? '-')) ?></div>
    <?php endif; ?>
  </div>
</div>

<div class="bg-white rounded-xl shadow p-4 mb-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="text-lg font-semibold mb-0">Ringkasan Sistem</h2>
    <div class="small text-secondary">Invoice total: <?= $invoiceCount ?> • Paket aktif: <?= $packageCount ?></div>
  </div>
  <div class="grid md:grid-cols-2 gap-4">
    <div class="rounded-4 border p-3 bg-light">
      <div class="fw-semibold mb-2">Fokus bulan ini</div>
      <ul class="mb-0 small text-secondary ps-3">
        <li>Follow up pelanggan yang belum bayar untuk periode <?= e(periodLabel($currentMonth, $currentYear)) ?>.</li>
        <li>Gunakan menu MikroTik API untuk isolir / buka isolir dari secret PPPoE.</li>
        <li>Cetak invoice barcode dari menu Invoice saat penagihan lapangan.</li>
      </ul>
    </div>
    <div class="rounded-4 border p-3 bg-light">
      <div class="fw-semibold mb-2">Catatan implementasi awal</div>
      <ul class="mb-0 small text-secondary ps-3">
        <li>Sinkron status isolir memakai status disabled pada PPP secret MikroTik.</li>
        <li>Profile pelanggan bisa diisi manual atau mengikuti hasil sinkron dari router.</li>
        <li>Struktur ini sudah siap untuk kita improve ke auto isolir dan reminder WA berikutnya.</li>
      </ul>
    </div>
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
          <th class="py-2 pr-3">Paket / PPPoE</th>
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
              <div class="text-xs text-slate-500">PPPoE: <?= e((string) ($invoice['service_username'] ?: '-')) ?> • Profile: <?= e((string) ($invoice['mikrotik_profile_name'] ?: '-')) ?></div>
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
          <tr><td colspan="6" class="py-4 text-slate-500">Belum ada invoice. Mulai dari pengaturan MikroTik, paket, pelanggan, lalu generate billing.</td></tr>
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
          <div class="small text-secondary">Versi mudah untuk admin billing RT/RW Net + MikroTik</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3">
        <div class="rounded-4 border border-primary-subtle bg-primary-subtle bg-opacity-50 p-3 mb-4">
          <div class="fw-semibold mb-2">Urutan paling aman</div>
          <div class="small text-secondary">Isi <b>Pengaturan MikroTik</b> dulu, lanjut <b>Tes Koneksi</b>, setelah itu tambah <b>Paket</b> dan <b>Pelanggan</b>, lalu <b>Generate Billing</b> dan proses penagihan dari menu <b>Invoice</b>.</div>
        </div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">1</div><div><div class="tutorial-step-title">Isi koneksi MikroTik</div><div class="tutorial-step-text">Masuk ke <b>Pengaturan</b>, isi host router, port API (default 8728), timeout, username, password, dan SSL bila dipakai.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">2</div><div><div class="tutorial-step-title">Tes koneksi dan sinkron secret</div><div class="tutorial-step-text">Buka menu <b>MikroTik API</b>, cek identitas router, lalu jalankan sinkron supaya status secret dan profile masuk ke app.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">3</div><div><div class="tutorial-step-title">Buat paket internet</div><div class="tutorial-step-text">Masuk ke menu <b>Paket</b>, isi nama paket, speed, harga, dan mapping profile MikroTik bila profile router sudah baku.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">4</div><div><div class="tutorial-step-title">Tambah pelanggan</div><div class="tutorial-step-text">Isi nama, alamat, no HP, wilayah, paket, lalu pilih username PPPoE / secret dari MikroTik dan profile yang dipakai pelanggan.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">5</div><div><div class="tutorial-step-title">Generate billing bulanan</div><div class="tutorial-step-text">Pilih bulan, tahun, dan due date. Sistem membuat invoice untuk pelanggan aktif yang belum punya invoice di periode tersebut.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">6</div><div><div class="tutorial-step-title">Tagih, print, dan isolir bila perlu</div><div class="tutorial-step-text">Filter invoice belum lunas per bulan, cetak invoice barcode, lalu jika perlu buka menu MikroTik API untuk disable / enable secret pelanggan.</div></div></div>
        <div class="rounded-4 border p-3 mt-4 bg-light">
          <div class="fw-semibold mb-2">Checklist harian</div>
          <ul class="mb-0 small text-secondary ps-3">
            <li>Cek pelanggan belum bayar bulan berjalan.</li>
            <li>Cek jumlah pelanggan yang sedang diisolir.</li>
            <li>Sinkron ulang data MikroTik bila ada perubahan profile atau secret baru.</li>
            <li>Print invoice atau kwitansi saat ada pembayaran.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
