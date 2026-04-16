<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth();

$phases = [
    [
        'title' => 'Phase 1 · Pondasi Billing Modern',
        'icon' => 'fa-layer-group',
        'tone' => 'blue',
        'items' => [
            'Rapikan dashboard, sidebar, dan alur kerja harian admin billing.',
            'Pastikan pelanggan, paket, wilayah, dan invoice nyaman dipakai di desktop maupun HP.',
            'Lengkapi identitas pelanggan: ID pelanggan, lokasi, no WA, router, username layanan.',
            'Buat sistem penomoran dan filter data yang lebih gampang dioperasikan admin lapangan.',
        ],
    ],
    [
        'title' => 'Phase 2 · Billing Engine RT/RW Net',
        'icon' => 'fa-file-invoice-dollar',
        'tone' => 'emerald',
        'items' => [
            'Generate tagihan bulanan dengan due date, periode, dan status bayar yang jelas.',
            'Tambahkan prorate untuk pelanggan baru di tengah bulan.',
            'Siapkan reminder penagihan H-3, H-1, dan lewat jatuh tempo.',
            'Buat status invoice lebih operasional: draft, unpaid, partial, paid, cancelled.',
        ],
    ],
    [
        'title' => 'Phase 3 · Integrasi ISP & Otomasi',
        'icon' => 'fa-tower-broadcast',
        'tone' => 'violet',
        'items' => [
            'Integrasi MikroTik: sync secret/profile, enable-disable pelanggan, cek status isolir.',
            'Siapkan fondasi auto-isolir dan auto-open setelah pembayaran tervalidasi.',
            'Tambahkan template WA untuk invoice, reminder, bukti bayar, dan notifikasi isolir.',
            'Siapkan connector opsional ke payment gateway bila nanti dibutuhkan.',
        ],
    ],
    [
        'title' => 'Phase 4 · Operasional & Skalabilitas',
        'icon' => 'fa-chart-line',
        'tone' => 'amber',
        'items' => [
            'Dashboard KPI: collection rate, ARPU, total active, pelanggan suspend, cashflow.',
            'Mapping area/ODP/router untuk mempermudah manajemen coverage.',
            'Client portal sederhana untuk cek tagihan, histori, dan tombol lapor gangguan.',
            'Siapkan fitur inventory perangkat serta catatan tiket/kunjungan teknisi.',
        ],
    ],
];

$featureGroups = [
    [
        'title' => 'Fitur prioritas yang diadopsi',
        'items' => [
            'Dashboard KPI sederhana tapi kuat, bukan dashboard ramai tanpa fokus.',
            'Invoice + piutang + reminder sebagai jantung aplikasi.',
            'Data pelanggan yang langsung nyambung ke operasional lapangan.',
            'Integrasi MikroTik dan WA sebagai akselerator, bukan beban UI.',
        ],
    ],
    [
        'title' => 'Fitur lanjutan yang layak masuk antrian',
        'items' => [
            'Payment gateway / mutasi bank / QRIS.',
            'Ticketing gangguan dan assignment teknisi.',
            'Inventory ONT, router, kabel, dan perangkat lapangan.',
            'Peta pelanggan, ODP/ODC, dan dashboard area coverage.',
        ],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<section class="billing-command-hero mb-4">
  <div>
    <div class="billing-command-hero__kicker"><i class="fa-solid fa-sitemap me-2"></i>Blueprint Billing ISP</div>
    <h1 class="billing-command-hero__title">Roadmap MyBill RT/RW Net</h1>
    <p class="billing-command-hero__text">Ini blueprint kerja yang saya pakai untuk menaikkan aplikasi jadi lebih simple, modern, dan relevan buat operasional ISP/RT-RW Net. Polanya terinspirasi dari produk besar, tapi tetap saya rapikan supaya lebih ringan dipakai harian.</p>
    <div class="billing-command-hero__pills">
      <span class="billing-kpi-pill"><i class="fa-solid fa-bolt me-1"></i>Simple & modern</span>
      <span class="billing-kpi-pill"><i class="fa-solid fa-wifi me-1"></i>ISP ready</span>
      <span class="billing-kpi-pill"><i class="fa-solid fa-robot me-1"></i>Automation ready</span>
    </div>
  </div>
  <div class="billing-command-hero__actions">
    <a href="dashboard.php" class="btn btn-light isp-action-btn"><i class="fa-solid fa-gauge-high me-2"></i>Kembali ke Dashboard</a>
    <a href="customers.php" class="btn btn-light isp-action-btn"><i class="fa-solid fa-users me-2"></i>Lihat Pelanggan</a>
    <a href="settings.php" class="btn btn-light isp-action-btn"><i class="fa-solid fa-sliders me-2"></i>Siapkan Branding</a>
  </div>
</section>

<section class="grid lg:grid-cols-2 gap-4 mb-4">
  <?php foreach ($phases as $phase): ?>
    <article class="bg-white rounded-xl shadow p-4 roadmap-card roadmap-card--<?= e($phase['tone']) ?>">
      <div class="roadmap-card__head">
        <div class="roadmap-card__icon"><i class="fa-solid <?= e($phase['icon']) ?>"></i></div>
        <div>
          <div class="section-kicker">Execution Track</div>
          <h2 class="font-semibold mb-0"><?= e($phase['title']) ?></h2>
        </div>
      </div>
      <ul class="roadmap-list mb-0 mt-3">
        <?php foreach ($phase['items'] as $item): ?>
          <li><?= e($item) ?></li>
        <?php endforeach; ?>
      </ul>
    </article>
  <?php endforeach; ?>
</section>

<section class="grid lg:grid-cols-[1.1fr_0.9fr] gap-4 mb-4">
  <div class="bg-white rounded-xl shadow p-4">
    <div class="section-kicker">Direction</div>
    <h2 class="font-semibold mb-3">Prinsip produk yang dipakai</h2>
    <div class="quick-module-grid quick-module-grid--stacked">
      <div class="quick-module-card quick-module-card--blue">
        <div class="quick-module-card__icon"><i class="fa-solid fa-minimize"></i></div>
        <div class="quick-module-card__title">Jangan terlalu ramai</div>
        <div class="quick-module-card__desc">Yang sering dipakai admin harus muncul duluan: pelanggan, invoice, piutang, generate billing, dan monitoring status layanan.</div>
      </div>
      <div class="quick-module-card quick-module-card--emerald">
        <div class="quick-module-card__icon"><i class="fa-solid fa-gears"></i></div>
        <div class="quick-module-card__title">Otomasi harus terasa</div>
        <div class="quick-module-card__desc">Integrasi MikroTik, reminder WA, dan status isolir bukan sekadar pajangan, tapi harus mengurangi kerja manual admin.</div>
      </div>
      <div class="quick-module-card quick-module-card--amber">
        <div class="quick-module-card__icon"><i class="fa-solid fa-mobile-screen-button"></i></div>
        <div class="quick-module-card__title">Nyaman dari HP</div>
        <div class="quick-module-card__desc">Admin lapangan dan owner harus bisa cek kondisi billing cepat dari mobile tanpa UI berantakan.</div>
      </div>
    </div>
  </div>

  <div class="grid gap-4">
    <?php foreach ($featureGroups as $group): ?>
      <div class="isp-panel-card isp-panel-card--light">
        <div class="isp-panel-card__title"><?= e($group['title']) ?></div>
        <ul class="isp-panel-list mb-0">
          <?php foreach ($group['items'] as $item): ?>
            <li><?= e($item) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="bg-white rounded-xl shadow p-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <div class="section-kicker">Next Recommended Move</div>
      <h2 class="font-semibold mb-0">Urutan gas yang paling masuk akal</h2>
    </div>
    <span class="badge rounded-pill text-bg-primary">Versi awal blueprint aktif</span>
  </div>
  <ol class="roadmap-sequence mb-0">
    <li>Rapikan pengalaman admin di dashboard, data pelanggan, invoice, dan filter.</li>
    <li>Matangkan engine billing dan reminder tagihan supaya cashflow lebih kebantu.</li>
    <li>Aktifkan integrasi MikroTik bertahap: monitor → sinkron → kontrol.</li>
    <li>Baru masuk ke WA automation, portal pelanggan, inventory, dan ticketing.</li>
  </ol>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
