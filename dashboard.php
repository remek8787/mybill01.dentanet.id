<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth();

$pdo = db();
$currentMonth = (int) date('n');
$currentYear = (int) date('Y');
$monthLabel = periodLabel($currentMonth, $currentYear);
$today = date('Y-m-d');

$customerCount = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$activeCustomerCount = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn();
$customerWithIdCount = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE TRIM(COALESCE(customer_no, '')) <> ''")->fetchColumn();
$packageCount = (int) $pdo->query('SELECT COUNT(*) FROM packages WHERE is_active = 1')->fetchColumn();
$invoiceCount = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$unpaidCount = (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'unpaid'")->fetchColumn();
$unpaidTotal = (int) $pdo->query("SELECT COALESCE(SUM(amount - discount_amount),0) FROM invoices WHERE status = 'unpaid'")->fetchColumn();
$paidTotal = (int) $pdo->query("SELECT COALESCE(SUM(amount - discount_amount),0) FROM invoices WHERE status = 'paid'")->fetchColumn();
$isolatedCount = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'suspended' OR isolated = 1")->fetchColumn();

$stmtIncomeToday = $pdo->prepare("SELECT COALESCE(SUM(amount - discount_amount),0) FROM invoices WHERE status = 'paid' AND date(paid_at) = :today");
$stmtIncomeToday->execute([':today' => $today]);
$incomeToday = (int) $stmtIncomeToday->fetchColumn();

$stmtPaidMonth = $pdo->prepare("SELECT COUNT(DISTINCT customer_id) FROM invoices WHERE status = 'paid' AND period_month = :month AND period_year = :year");
$stmtPaidMonth->execute([':month' => $currentMonth, ':year' => $currentYear]);
$paidCustomerCount = (int) $stmtPaidMonth->fetchColumn();

$stmtUnpaidMonth = $pdo->prepare("SELECT COUNT(DISTINCT customer_id) FROM invoices WHERE status = 'unpaid' AND period_month = :month AND period_year = :year");
$stmtUnpaidMonth->execute([':month' => $currentMonth, ':year' => $currentYear]);
$unpaidCustomerCount = (int) $stmtUnpaidMonth->fetchColumn();

$stmtMonthlyInvoiceCount = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE period_month = :month AND period_year = :year");
$stmtMonthlyInvoiceCount->execute([':month' => $currentMonth, ':year' => $currentYear]);
$monthlyInvoiceCount = (int) $stmtMonthlyInvoiceCount->fetchColumn();

$stmtPaidMonthRevenue = $pdo->prepare("SELECT COALESCE(SUM(amount - discount_amount),0) FROM invoices WHERE status = 'paid' AND period_month = :month AND period_year = :year");
$stmtPaidMonthRevenue->execute([':month' => $currentMonth, ':year' => $currentYear]);
$paidMonthRevenue = (int) $stmtPaidMonthRevenue->fetchColumn();

$collectionBase = max(1, $paidCustomerCount + $unpaidCustomerCount);
$collectionRate = (int) round(($paidCustomerCount / $collectionBase) * 100);
$avgRevenuePerPaidCustomer = $paidCustomerCount > 0 ? (int) floor($paidMonthRevenue / $paidCustomerCount) : 0;

$recentInvoices = $pdo->query('SELECT i.*, c.full_name AS customer_name, c.customer_no, c.area, c.service_username, p.name AS package_name, p.speed
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN packages p ON p.id = i.package_id
    ORDER BY i.id DESC LIMIT 10')->fetchAll();

$areaStats = $pdo->query("SELECT COALESCE(NULLIF(TRIM(area), ''), 'Tanpa Area') AS label, COUNT(*) AS total
    FROM customers
    GROUP BY COALESCE(NULLIF(TRIM(area), ''), 'Tanpa Area')
    ORDER BY total DESC, label ASC
    LIMIT 5")->fetchAll();

$routerStats = $pdo->query("SELECT COALESCE(NULLIF(TRIM(router_name), ''), 'Belum diisi') AS label, COUNT(*) AS total
    FROM customers
    GROUP BY COALESCE(NULLIF(TRIM(router_name), ''), 'Belum diisi')
    ORDER BY total DESC, label ASC
    LIMIT 5")->fetchAll();

$upcomingDueInvoices = $pdo->query('SELECT i.*, c.full_name AS customer_name, c.customer_no, c.phone, c.area, p.name AS package_name, p.speed
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN packages p ON p.id = i.package_id
    WHERE i.status = "unpaid" AND COALESCE(i.due_date, "") <> ""
    ORDER BY date(i.due_date) ASC, i.id ASC
    LIMIT 6')->fetchAll();

$moduleCards = [
    [
        'href' => 'customers.php',
        'icon' => 'fa-users-viewfinder',
        'title' => 'Data Pelanggan',
        'desc' => 'Kelola identitas pelanggan, paket, area, dan router dari satu pintu.',
        'tone' => 'blue',
    ],
    [
        'href' => 'readings.php',
        'icon' => 'fa-file-circle-plus',
        'title' => 'Generate Billing',
        'desc' => 'Buat invoice bulanan tanpa ribet, fokus ke siklus penagihan inti.',
        'tone' => 'emerald',
    ],
    [
        'href' => 'bills.php?status=unpaid',
        'icon' => 'fa-file-invoice-dollar',
        'title' => 'Follow Up Piutang',
        'desc' => 'Masuk cepat ke invoice belum lunas dan proses pembayaran.',
        'tone' => 'amber',
    ],
    [
        'href' => 'income_report.php?range=month',
        'icon' => 'fa-chart-line',
        'title' => 'Income Summary',
        'desc' => 'Pantau pemasukan bulan berjalan dengan tampilan yang lebih ringkas.',
        'tone' => 'violet',
    ],
    [
        'href' => 'roadmap.php',
        'icon' => 'fa-sitemap',
        'title' => 'Blueprint ISP',
        'desc' => 'Lihat peta jalan pengembangan billing RT/RW Net yang sedang dieksekusi.',
        'tone' => 'blue',
    ],
];

require __DIR__ . '/includes/header.php';
?>

<section class="billing-command-hero mb-4">
  <div>
    <div class="billing-command-hero__kicker"><i class="fa-solid fa-satellite-dish me-2"></i>Control Center Billing</div>
    <h1 class="billing-command-hero__title"><?= e(companyName()) ?></h1>
    <p class="billing-command-hero__text">Saya adaptasi gaya panel e-billing besar ke versi yang lebih orisinal, fokus, dan ringan. Intinya tetap kuat: pelanggan, billing, invoice, area, dan monitoring harian.</p>
    <div class="billing-command-hero__pills">
      <span class="billing-kpi-pill"><i class="fa-solid fa-users me-1"></i><?= $customerCount ?> pelanggan</span>
      <span class="billing-kpi-pill"><i class="fa-solid fa-id-card me-1"></i><?= $customerWithIdCount ?> sudah punya ID</span>
      <span class="billing-kpi-pill"><i class="fa-solid fa-receipt me-1"></i><?= $monthlyInvoiceCount ?> invoice periode ini</span>
    </div>
  </div>
  <div class="billing-command-hero__actions">
    <a href="customers.php" class="btn btn-light isp-action-btn"><i class="fa-solid fa-user-plus me-2"></i>Kelola Pelanggan</a>
    <a href="bills.php?status=unpaid" class="btn btn-light isp-action-btn"><i class="fa-solid fa-bell-concierge me-2"></i>Ke Piutang</a>
    <a href="readings.php" class="btn btn-light isp-action-btn"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Generate Billing</a>
  </div>
</section>

<section class="billing-kpi-strip mb-4">
  <div class="billing-kpi-strip__item">
    <div class="billing-kpi-strip__label">Collection rate</div>
    <div class="billing-kpi-strip__value"><?= $collectionRate ?>%</div>
    <div class="billing-kpi-strip__note">Lunas vs belum lunas periode <?= e($monthLabel) ?></div>
  </div>
  <div class="billing-kpi-strip__item">
    <div class="billing-kpi-strip__label">ARPU tertagih</div>
    <div class="billing-kpi-strip__value"><?= e(rupiah($avgRevenuePerPaidCustomer)) ?></div>
    <div class="billing-kpi-strip__note">Rata-rata pemasukan per pelanggan lunas</div>
  </div>
  <div class="billing-kpi-strip__item">
    <div class="billing-kpi-strip__label">Pelanggan aktif</div>
    <div class="billing-kpi-strip__value"><?= $activeCustomerCount ?></div>
    <div class="billing-kpi-strip__note">Siap digenerate billing</div>
  </div>
  <div class="billing-kpi-strip__item">
    <div class="billing-kpi-strip__label">Suspended / isolir</div>
    <div class="billing-kpi-strip__value"><?= $isolatedCount ?></div>
    <div class="billing-kpi-strip__note">Perlu atensi operasional</div>
  </div>
</section>

<section class="isp-tile-grid mb-4">
  <a class="isp-stat-tile isp-stat-tile--green" href="customers.php">
    <div class="isp-stat-tile__value"><?= $customerCount ?></div>
    <div class="isp-stat-tile__label">Data Pelanggan</div>
    <div class="isp-stat-tile__sub">Seluruh pelanggan tersimpan</div>
    <div class="isp-stat-tile__icon"><i class="fa-solid fa-users"></i></div>
    <div class="isp-stat-tile__footer">Selengkapnya <i class="fa-solid fa-circle-arrow-right"></i></div>
  </a>

  <a class="isp-stat-tile isp-stat-tile--blue" href="customers_paid.php?month=<?= $currentMonth ?>&year=<?= $currentYear ?>">
    <div class="isp-stat-tile__value"><?= $paidCustomerCount ?></div>
    <div class="isp-stat-tile__label">Pelanggan Lunas</div>
    <div class="isp-stat-tile__sub">Periode <?= e($monthLabel) ?></div>
    <div class="isp-stat-tile__icon"><i class="fa-solid fa-user-check"></i></div>
    <div class="isp-stat-tile__footer">Selengkapnya <i class="fa-solid fa-circle-arrow-right"></i></div>
  </a>

  <a class="isp-stat-tile isp-stat-tile--red" href="customers_unpaid.php?month=<?= $currentMonth ?>&year=<?= $currentYear ?>">
    <div class="isp-stat-tile__value"><?= $unpaidCustomerCount ?></div>
    <div class="isp-stat-tile__label">Belum Lunas</div>
    <div class="isp-stat-tile__sub">Periode <?= e($monthLabel) ?></div>
    <div class="isp-stat-tile__icon"><i class="fa-solid fa-user-clock"></i></div>
    <div class="isp-stat-tile__footer">Prioritas tagih <i class="fa-solid fa-circle-arrow-right"></i></div>
  </a>

  <a class="isp-stat-tile isp-stat-tile--emerald-deep" href="income_report.php?range=today">
    <div class="isp-stat-tile__value"><?= e(rupiah($incomeToday)) ?></div>
    <div class="isp-stat-tile__label">Pemasukan Hari Ini</div>
    <div class="isp-stat-tile__sub">Tanggal <?= e(formatDateId($today, '-')) ?></div>
    <div class="isp-stat-tile__icon"><i class="fa-solid fa-sack-dollar"></i></div>
    <div class="isp-stat-tile__footer">Transaksi harian <i class="fa-solid fa-circle-arrow-right"></i></div>
  </a>

  <a class="isp-stat-tile isp-stat-tile--cyan" href="income_report.php?range=month">
    <div class="isp-stat-tile__value"><?= e(rupiah($paidMonthRevenue)) ?></div>
    <div class="isp-stat-tile__label">Pemasukan Bulan Ini</div>
    <div class="isp-stat-tile__sub"><?= e($monthLabel) ?></div>
    <div class="isp-stat-tile__icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
    <div class="isp-stat-tile__footer">Selengkapnya <i class="fa-solid fa-circle-arrow-right"></i></div>
  </a>

  <a class="isp-stat-tile isp-stat-tile--green-dark" href="packages.php">
    <div class="isp-stat-tile__value"><?= $packageCount ?></div>
    <div class="isp-stat-tile__label">Paket Aktif</div>
    <div class="isp-stat-tile__sub">Paket internet siap jual</div>
    <div class="isp-stat-tile__icon"><i class="fa-solid fa-box-open"></i></div>
    <div class="isp-stat-tile__footer">Lihat paket <i class="fa-solid fa-circle-arrow-right"></i></div>
  </a>

  <a class="isp-stat-tile isp-stat-tile--yellow" href="bills.php?status=unpaid">
    <div class="isp-stat-tile__value"><?= $unpaidCount ?></div>
    <div class="isp-stat-tile__label">Invoice Open</div>
    <div class="isp-stat-tile__sub">Belum lunas semua periode</div>
    <div class="isp-stat-tile__icon"><i class="fa-regular fa-folder-open"></i></div>
    <div class="isp-stat-tile__footer">Prioritas follow up <i class="fa-solid fa-circle-arrow-right"></i></div>
  </a>

  <div class="isp-stat-tile isp-stat-tile--pink">
    <div class="isp-stat-tile__value"><?= e(rupiah($unpaidTotal)) ?></div>
    <div class="isp-stat-tile__label">Total Piutang</div>
    <div class="isp-stat-tile__sub">Saldo invoice belum lunas</div>
    <div class="isp-stat-tile__icon"><i class="fa-solid fa-wallet"></i></div>
    <div class="isp-stat-tile__footer">Jaga arus kas <i class="fa-solid fa-circle-arrow-right"></i></div>
  </div>
</section>

<section class="grid xl:grid-cols-[1.05fr_0.95fr] gap-4 mb-4">
  <div class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--table">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <div class="section-kicker">Modul Utama</div>
        <h2 class="font-semibold mb-0">Adaptasi panel operasional</h2>
      </div>
      <div class="small text-secondary">Original arrangement, bukan copy mentah</div>
    </div>
    <div class="quick-module-grid">
      <?php foreach ($moduleCards as $card): ?>
        <a href="<?= e($card['href']) ?>" class="quick-module-card quick-module-card--<?= e($card['tone']) ?>">
          <div class="quick-module-card__icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></div>
          <div class="quick-module-card__title"><?= e($card['title']) ?></div>
          <div class="quick-module-card__desc"><?= e($card['desc']) ?></div>
          <div class="quick-module-card__cta">Buka modul <i class="fa-solid fa-arrow-right ms-1"></i></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="grid gap-4">
    <div class="isp-summary-tile isp-summary-tile--teal">
      <div class="isp-summary-tile__title">Ringkasan Pemasukan</div>
      <div class="isp-summary-tile__big"><?= e(rupiah($paidMonthRevenue)) ?></div>
      <div class="isp-summary-tile__line">Pemasukan bulan ini | <?= e($monthLabel) ?></div>
      <div class="isp-summary-tile__mini mt-3"><?= e(rupiah($paidTotal)) ?></div>
      <div class="isp-summary-tile__line">Akumulasi invoice lunas semua periode</div>
      <div class="isp-summary-tile__mini mt-4"><?= e(rupiah($unpaidTotal)) ?></div>
      <div class="isp-summary-tile__line">Sisa piutang yang masih dikejar</div>
    </div>

    <div class="isp-panel-card isp-panel-card--light">
      <div class="isp-panel-card__title">Checklist admin harian</div>
      <ul class="isp-panel-list mb-0">
        <li>Cek invoice mendekati jatuh tempo.</li>
        <li>Pastikan data pelanggan baru sudah punya paket, area, dan ID pelanggan.</li>
        <li>Follow up pelanggan unpaid lalu lanjut generate billing bila periode baru dimulai.</li>
        <li>Review blueprint ISP bila mau lanjut tahap otomasi atau pengembangan modul baru.</li>
        <li>Follow up pelanggan belum lunas bulan berjalan.</li>
        <li>Pastikan pelanggan baru langsung punya ID pelanggan.</li>
      </ul>
    </div>
  </div>
</section>

<section class="grid xl:grid-cols-[1.05fr_0.95fr] gap-4 mb-4">
  <div class="grid gap-4">
    <section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--table">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <div class="section-kicker">Monitoring</div>
          <h2 class="font-semibold mb-0">Sebaran area dan router</h2>
        </div>
      </div>
      <div class="grid md:grid-cols-2 gap-3">
        <div class="monitor-list-card">
          <div class="monitor-list-card__title"><i class="fa-solid fa-map-location-dot me-2"></i>Area terbanyak</div>
          <ul class="monitor-list mb-0">
            <?php foreach ($areaStats as $row): ?>
              <li>
                <span><?= e((string) $row['label']) ?></span>
                <strong><?= (int) $row['total'] ?></strong>
              </li>
            <?php endforeach; ?>
            <?php if (!$areaStats): ?>
              <li><span>Belum ada data area</span><strong>0</strong></li>
            <?php endif; ?>
          </ul>
        </div>
        <div class="monitor-list-card">
          <div class="monitor-list-card__title"><i class="fa-solid fa-router me-2"></i>Router / POP terbanyak</div>
          <ul class="monitor-list mb-0">
            <?php foreach ($routerStats as $row): ?>
              <li>
                <span><?= e((string) $row['label']) ?></span>
                <strong><?= (int) $row['total'] ?></strong>
              </li>
            <?php endforeach; ?>
            <?php if (!$routerStats): ?>
              <li><span>Belum ada router</span><strong>0</strong></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </section>

    <section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--table">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <div class="section-kicker">Focus board</div>
          <h2 class="font-semibold mb-0">Invoice jatuh tempo terdekat</h2>
        </div>
        <a href="bills.php?status=unpaid" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Buka invoice</a>
      </div>
      <div class="monitor-deadline-list">
        <?php foreach ($upcomingDueInvoices as $invoice): ?>
          <?php
            $dueDate = (string) ($invoice['due_date'] ?? '');
            $dayDiff = null;
            if ($dueDate !== '') {
                $dayDiff = (int) floor((strtotime($dueDate . ' 00:00:00') - strtotime($today . ' 00:00:00')) / 86400);
            }
            $dueLabel = 'Belum ada jatuh tempo';
            if ($dayDiff !== null) {
                if ($dayDiff < 0) {
                    $dueLabel = 'Terlambat ' . abs($dayDiff) . ' hari';
                } elseif ($dayDiff === 0) {
                    $dueLabel = 'Jatuh tempo hari ini';
                } else {
                    $dueLabel = $dayDiff . ' hari lagi';
                }
            }
          ?>
          <div class="monitor-deadline-item">
            <div>
              <div class="fw-semibold"><?= e((string) ($invoice['customer_name'] ?? '-')) ?></div>
              <div class="text-xs text-slate-500">ID <?= e((string) (($invoice['customer_no'] ?? '') ?: '-')) ?> • <?= e(packageLabel($invoice)) ?></div>
              <div class="text-xs text-slate-500">Area <?= e((string) (($invoice['area'] ?? '') ?: '-')) ?> • HP <?= e((string) (($invoice['phone'] ?? '') ?: '-')) ?></div>
            </div>
            <div class="text-end">
              <div class="fw-semibold"><?= e(rupiah(invoiceNetAmount($invoice))) ?></div>
              <div class="text-xs <?= $dayDiff !== null && $dayDiff < 0 ? 'text-danger' : 'text-slate-500' ?>"><?= e($dueLabel) ?></div>
              <div class="text-xs text-slate-500"><?= e(formatDateId($dueDate, '-')) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$upcomingDueInvoices): ?>
          <div class="py-3 text-slate-500">Belum ada invoice jatuh tempo yang perlu dipantau.</div>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--table">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <div class="section-kicker">Invoice terbaru</div>
        <h2 class="font-semibold mb-0">Aktivitas billing terkini</h2>
      </div>
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
              <td class="py-2 pr-3">
                <div class="font-semibold"><?= e((string) ($invoice['customer_name'] ?? '-')) ?></div>
                <div class="text-xs text-slate-500">ID Pelanggan: <?= e((string) (($invoice['customer_no'] ?? '') ?: '-')) ?></div>
              </td>
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
        <div class="tutorial-step-card"><div class="tutorial-step-number">2</div><div><div class="tutorial-step-title">Tambah pelanggan</div><div class="tutorial-step-text">Isi nama, alamat, no HP, area, paket, due day, dan catatan layanan pelanggan. Biarkan ID kosong kalau mau auto-generate format DSA.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">3</div><div><div class="tutorial-step-title">Generate billing bulanan</div><div class="tutorial-step-text">Pilih bulan, tahun, dan due date. Sistem akan membuat invoice untuk pelanggan aktif.</div></div></div>
        <div class="tutorial-step-card"><div class="tutorial-step-number">4</div><div><div class="tutorial-step-title">Proses pembayaran</div><div class="tutorial-step-text">Tandai invoice lunas dari menu <b>Invoice</b>, isi metode bayar, dan cetak invoice atau kwitansi.</div></div></div>
        <div class="rounded-4 border p-3 mt-4 bg-light">
          <div class="fw-semibold mb-2">Checklist harian</div>
          <ul class="mb-0 small text-secondary ps-3">
            <li>Cek pelanggan belum lunas.</li>
            <li>Follow up piutang bulan berjalan.</li>
            <li>Pastikan pelanggan baru langsung punya ID pelanggan.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
