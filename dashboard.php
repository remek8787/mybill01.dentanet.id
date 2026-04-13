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

$isolatedCount = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'suspended'")->fetchColumn();

$recentInvoices = $pdo->query('SELECT i.*, c.full_name AS customer_name, c.area, c.service_username, c.mikrotik_profile_name,
        p.name AS package_name, p.speed
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN packages p ON p.id = i.package_id
    ORDER BY i.id DESC LIMIT 10')->fetchAll();

$reactDashboardProps = [
    'monthLabel' => periodLabel($currentMonth, $currentYear),
    'customerCount' => $customerCount,
    'unpaidCustomerCount' => $unpaidCustomerCount,
    'isolatedCount' => $isolatedCount,
    'unpaidTotal' => rupiah($unpaidTotal),
    'routerStatus' => 'Billing inti aktif',
];

require __DIR__ . '/includes/header.php';
?>

<section class="dashboard-hero-card dashboard-hero-card--colorful mb-4 dashboard-hero-card--compact">
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
      <a href="readings.php" class="btn btn-outline-secondary">
        <i class="bi bi-calendar2-check me-2"></i>Generate Billing
      </a>
    </div>
  </div>
</section>

<section class="mb-4">
  <div id="dashboardReactOrnament" data-props='<?= e(json_encode($reactDashboardProps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'></div>
</section>

<section class="grid lg:grid-cols-2 gap-4 mb-4">
  <div class="bg-white rounded-xl shadow p-4 dashboard-update-card dashboard-update-card--compact dashboard-accent-card dashboard-accent-card--indigo">
    <div class="dashboard-section-head dashboard-section-head--compact">
      <div>
        <div class="dashboard-section-kicker">Versi Fondasi</div>
        <h3 class="dashboard-section-title dashboard-section-title--compact">Yang sudah siap dipakai sekarang</h3>
      </div>
      <span class="dashboard-update-badge">V1+</span>
    </div>
    <ul class="dashboard-bullet-list">
      <li>Data pelanggan RT/RW Net lengkap dan siap dipakai untuk billing harian</li>
      <li>Generate invoice bulanan, pembayaran, cetak invoice, dan monitoring status pelanggan</li>
      <li>Generate invoice bulanan, filter pelanggan belum bayar per bulan, dan hitung isolir</li>
      <li>Cetak invoice dengan barcode nomor invoice</li>
      <li>Panel tutorial supaya admin tidak bingung saat onboarding</li>
    </ul>
  </div>

  <div class="bg-white rounded-xl shadow p-4 dashboard-update-card dashboard-update-card--compact dashboard-accent-card dashboard-accent-card--violet">
    <div class="dashboard-section-head dashboard-section-head--compact">
      <div>
        <div class="dashboard-section-kicker">Alur Cepat</div>
        <h3 class="dashboard-section-title dashboard-section-title--compact">Pakai harian admin</h3>
      </div>
    </div>
    <ol class="dashboard-inline-steps">
      <li><b>Pelanggan</b> → isi identitas pelanggan, layanan, paket, dan area</li>
      <li><b>Paket</b> → siapkan nama paket, speed, dan harga bulanan</li>
      <li><b>Generate Billing</b> → buat invoice bulan berjalan</li>
      <li><b>Invoice</b> → filter yang belum bayar, print invoice, dan tandai lunas</li>
    </ol>
  </div>
</section>

<div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4 mb-4">
  <div class="stat-card stat-card--sky p-4">
    <p class="text-sm text-slate-500">Total Pelanggan</p>
    <p class="text-3xl font-bold"><?= $customerCount ?></p>
  </div>
  <div class="stat-card stat-card--amber p-4">
    <p class="text-sm text-slate-500">Pelanggan Belum Bayar <?= e(monthName($currentMonth)) ?></p>
    <p class="text-3xl font-bold text-amber-600"><?= $unpaidCustomerCount ?></p>
  </div>
  <div class="stat-card stat-card--rose p-4">
    <p class="text-sm text-slate-500">Pelanggan Diisolir</p>
    <p class="text-3xl font-bold text-red-600"><?= $isolatedCount ?></p>
  </div>
  <div class="stat-card stat-card--violet p-4">
    <p class="text-sm text-slate-500">Invoice Belum Lunas</p>
    <p class="text-3xl font-bold text-amber-600"><?= $unpaidCount ?></p>
  </div>
  <div class="stat-card stat-card--mint p-4">
    <p class="text-sm text-slate-500">Piutang Saat Ini</p>
    <p class="text-3xl font-bold"><?= e(rupiah($unpaidTotal)) ?></p>
    <div class="text-xs text-slate-500 mt-1">Sudah dibayar: <?= e(rupiah($paidTotal)) ?></div>
  </div>
  <div class="stat-card stat-card--indigo p-4">
    <p class="text-sm text-slate-500">Mode Aplikasi</p>
    <p class="text-sm text-emerald-700 fw-semibold">Billing inti aktif</p>
    <div class="text-xs text-slate-500 mt-1">Fitur API disembunyikan sementara supaya app lebih ringan dan fokus ke billing.</div>
  </div>
</div>

<div class="bg-white rounded-xl shadow p-4 mb-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="text-lg font-semibold mb-0">Ringkasan Sistem</h2>
    <div class="small text-secondary">Invoice total: <?= $invoiceCount ?> • Paket aktif: <?= $packageCount ?></div>
  </div>
  <div class="grid md:grid-cols-2 gap-4">
    <div class="rounded-4 border p-3 bg-light soft-panel soft-panel--sky">
      <div class="fw-semibold mb-2">Fokus bulan ini</div>
      <ul class="mb-0 small text-secondary ps-3">
        <li>Follow up pelanggan yang belum bayar untuk periode <?= e(periodLabel($currentMonth, $currentYear)) ?>.</li>
        <li>Fokus dulu ke pelanggan aktif, invoice, dan penagihan bulan berjalan.</li>
        <li>Cetak invoice barcode dari menu Invoice saat penagihan lapangan.</li>
      </ul>
    </div>
    <div class="rounded-4 border p-3 bg-light soft-panel soft-panel--violet">
      <div class="fw-semibold mb-2">Catatan implementasi awal</div>
      <ul class="mb-0 small text-secondary ps-3">
        <li>Fitur API sedang disembunyikan sementara untuk menjaga performa app tetap ringan.</li>
        <li>Struktur billing inti tetap aman untuk pelanggan, paket, invoice, dan pembayaran.</li>
        <li>Nanti API bisa dinyalakan lagi setelah alur paling efisiennya diputuskan.</li>
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

<script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script>
(() => {
  const mount = document.getElementById('dashboardReactOrnament');
  if (!mount || !window.React || !window.ReactDOM) {
    return;
  }

  let props = {};
  try {
    props = JSON.parse(mount.dataset.props || '{}');
  } catch (error) {
    props = {};
  }

  const e = React.createElement;

  const StatChip = ({ icon, label, value, ring }) => e(
    'div',
    {
      className: 'rounded-3xl border border-white/50 bg-white/80 backdrop-blur-md px-4 py-3 shadow-lg'
    },
    e('div', { className: 'flex items-center gap-3' },
      e('div', { className: 'h-11 w-11 rounded-2xl flex items-center justify-center text-white shadow-md ' + ring },
        e('i', { className: icon })
      ),
      e('div', null,
        e('div', { className: 'text-xs uppercase tracking-[0.18em] text-slate-500 font-bold' }, label),
        e('div', { className: 'text-lg font-black text-slate-800' }, value)
      )
    )
  );

  const ActionLink = ({ href, icon, title, text, tone }) => e(
    'a',
    {
      href,
      className: 'group rounded-[1.4rem] border border-white/50 bg-white/80 p-4 shadow-lg transition hover:-translate-y-1 hover:shadow-xl backdrop-blur-md ' + tone
    },
    e('div', { className: 'flex items-start justify-between gap-3' },
      e('div', null,
        e('div', { className: 'text-sm font-black text-slate-800' }, title),
        e('div', { className: 'mt-1 text-xs leading-6 text-slate-500' }, text)
      ),
      e('div', { className: 'h-11 w-11 rounded-2xl flex items-center justify-center text-white shadow-md ' + tone.replace('text-slate-800','') },
        e('i', { className: icon })
      )
    )
  );

  const DashboardDecor = () => e(
    'div',
    { className: 'relative overflow-hidden rounded-[2rem] border border-white/50 bg-gradient-to-br from-sky-100 via-fuchsia-50 to-amber-50 p-5 shadow-[0_24px_60px_rgba(15,23,42,0.10)]' },
    e('div', { className: 'absolute -right-12 -top-12 h-36 w-36 rounded-full bg-fuchsia-300/30 blur-3xl' }),
    e('div', { className: 'absolute -left-10 bottom-0 h-32 w-32 rounded-full bg-sky-300/30 blur-3xl' }),
    e('div', { className: 'relative grid gap-4 xl:grid-cols-[1.2fr_0.8fr]' },
      e('div', { className: 'rounded-[1.6rem] border border-white/50 bg-slate-900 px-5 py-5 text-white shadow-xl' },
        e('div', { className: 'inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.2em] text-amber-200' },
          e('i', { className: 'fa-solid fa-wand-magic-sparkles' }),
          ' React + Tailwind Ornament'
        ),
        e('h3', { className: 'mt-3 text-2xl font-black leading-tight' }, 'Dashboard dibuat lebih hidup tanpa build berat'),
        e('p', { className: 'mt-2 max-w-2xl text-sm leading-7 text-slate-300' }, 'Saya pakai React island ringan di atas layout PHP yang sudah ada, jadi tetap aman untuk hosting sekarang tapi terasa lebih modern dan interaktif.'),
        e('div', { className: 'mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4' },
          e(StatChip, { icon: 'fa-solid fa-users', label: 'Pelanggan', value: String(props.customerCount || 0), ring: 'bg-gradient-to-br from-sky-500 to-cyan-400' }),
          e(StatChip, { icon: 'fa-solid fa-file-invoice-dollar', label: 'Belum Bayar', value: String(props.unpaidCustomerCount || 0), ring: 'bg-gradient-to-br from-amber-500 to-orange-400' }),
          e(StatChip, { icon: 'fa-solid fa-user-lock', label: 'Diisolir', value: String(props.isolatedCount || 0), ring: 'bg-gradient-to-br from-rose-500 to-pink-500' }),
          e(StatChip, { icon: 'fa-solid fa-wallet', label: 'Piutang', value: String(props.unpaidTotal || '-'), ring: 'bg-gradient-to-br from-violet-500 to-fuchsia-500' })
        )
      ),
      e('div', { className: 'grid gap-3' },
        e(ActionLink, {
          href: 'customers.php',
          icon: 'fa-solid fa-users-viewfinder',
          title: 'Kelola pelanggan',
          text: 'Tambah, edit, dan cocokan secret PPPoE dengan data billing.',
          tone: 'hover:bg-sky-50'
        }),
        e(ActionLink, {
          href: 'mikrotik.php',
          icon: 'fa-solid fa-network-wired',
          title: 'Pantau router',
          text: 'Status sekarang: ' + (props.routerStatus || '-'),
          tone: 'hover:bg-fuchsia-50'
        }),
        e(ActionLink, {
          href: 'bills.php?status=unpaid',
          icon: 'fa-solid fa-bell-concierge',
          title: 'Tagihan ' + (props.monthLabel || 'bulan ini'),
          text: 'Lanjut cek invoice belum lunas dan follow up penagihan.',
          tone: 'hover:bg-amber-50'
        })
      )
    )
  );

  ReactDOM.createRoot(mount).render(e(DashboardDecor));
})();
</script>

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
