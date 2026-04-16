<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'staff']);

function nextCustomerNo(PDO $pdo): string
{
    $numbers = $pdo->query("SELECT customer_no FROM customers WHERE customer_no LIKE 'DSA%'")->fetchAll(PDO::FETCH_COLUMN);
    $max = 0;
    foreach ($numbers as $number) {
        $value = strtoupper(trim((string) $number));
        if (preg_match('/^DSA(\d{4,})$/', $value, $matches)) {
            $max = max($max, (int) $matches[1]);
        }
    }

    do {
        $max++;
        $candidate = 'DSA' . str_pad((string) $max, 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE customer_no = :customer_no');
        $stmt->execute([':customer_no' => $candidate]);
        $exists = (int) $stmt->fetchColumn() > 0;
    } while ($exists);

    return $candidate;
}

$pdo = db();
$user = currentUser();
$nextSuggestedCustomerNo = nextCustomerNo($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_customer') {
        $id = (int) ($_POST['id'] ?? 0);
        $customerNo = strtoupper(trim((string) ($_POST['customer_no'] ?? '')));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $area = trim((string) ($_POST['area'] ?? ''));
        $packageId = (int) ($_POST['package_id'] ?? 0);
        $apiCustomerId = trim((string) ($_POST['api_customer_id'] ?? ''));
        $routerName = trim((string) ($_POST['router_name'] ?? ''));
        $serviceType = trim((string) ($_POST['service_type'] ?? 'pppoe'));
        $serviceUsername = trim((string) ($_POST['service_username'] ?? ''));
        $mikrotikSecretId = trim((string) ($_POST['mikrotik_secret_id'] ?? ''));
        $mikrotikProfileName = trim((string) ($_POST['mikrotik_profile_name'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'active'));
        $dueDay = max(1, min(28, (int) ($_POST['due_day'] ?? 10)));
        $joinDate = normalizeDateInput($_POST['join_date'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($fullName === '') {
            flash('error', 'Nama pelanggan wajib diisi.');
            header('Location: customers.php');
            exit;
        }

        if ($customerNo === '') {
            $customerNo = nextCustomerNo($pdo);
        }

        if (!in_array($status, ['active', 'suspended', 'inactive'], true)) {
            $status = 'active';
        }

        if (!in_array($serviceType, ['pppoe', 'hotspot', 'static', 'other'], true)) {
            $serviceType = 'pppoe';
        }

        if ($serviceUsername === '') {
            $serviceUsername = $customerNo;
        }

        if ($mikrotikProfileName === '' && $packageId > 0) {
            $packageStmt = $pdo->prepare('SELECT mikrotik_profile_name FROM packages WHERE id = :id LIMIT 1');
            $packageStmt->execute([':id' => $packageId]);
            $mikrotikProfileName = trim((string) ($packageStmt->fetchColumn() ?: ''));
        }

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE customers SET customer_no = :customer_no, full_name = :full_name, address = :address,
                phone = :phone, area = :area, package_id = :package_id, api_customer_id = :api_customer_id,
                router_name = :router_name, service_type = :service_type, service_username = :service_username,
                mikrotik_secret_id = :mikrotik_secret_id, mikrotik_profile_name = :mikrotik_profile_name,
                status = :status, due_day = :due_day, join_date = :join_date, notes = :notes WHERE id = :id');
            $stmt->execute([
                ':customer_no' => $customerNo,
                ':full_name' => $fullName,
                ':address' => $address,
                ':phone' => $phone,
                ':area' => $area,
                ':package_id' => $packageId > 0 ? $packageId : null,
                ':api_customer_id' => $apiCustomerId,
                ':router_name' => $routerName,
                ':service_type' => $serviceType,
                ':service_username' => $serviceUsername,
                ':mikrotik_secret_id' => $mikrotikSecretId,
                ':mikrotik_profile_name' => $mikrotikProfileName,
                ':status' => $status,
                ':due_day' => $dueDay,
                ':join_date' => $joinDate,
                ':notes' => $notes,
                ':id' => $id,
            ]);
            flash('success', 'Pelanggan berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO customers(customer_no, full_name, address, phone, area, package_id,
                api_customer_id, router_name, service_type, service_username, mikrotik_secret_id, mikrotik_profile_name,
                status, due_day, join_date, notes)
                VALUES(:customer_no, :full_name, :address, :phone, :area, :package_id,
                :api_customer_id, :router_name, :service_type, :service_username, :mikrotik_secret_id, :mikrotik_profile_name,
                :status, :due_day, :join_date, :notes)');
            $stmt->execute([
                ':customer_no' => $customerNo,
                ':full_name' => $fullName,
                ':address' => $address,
                ':phone' => $phone,
                ':area' => $area,
                ':package_id' => $packageId > 0 ? $packageId : null,
                ':api_customer_id' => $apiCustomerId,
                ':router_name' => $routerName,
                ':service_type' => $serviceType,
                ':service_username' => $serviceUsername,
                ':mikrotik_secret_id' => $mikrotikSecretId,
                ':mikrotik_profile_name' => $mikrotikProfileName,
                ':status' => $status,
                ':due_day' => $dueDay,
                ':join_date' => $joinDate,
                ':notes' => $notes,
            ]);
            flash('success', 'Pelanggan berhasil ditambahkan.');
        }

        if ($area !== '') {
            $pdo->prepare('INSERT OR IGNORE INTO areas(name, description, is_active) VALUES(:name, :description, 1)')->execute([
                ':name' => $area,
                ':description' => 'Wilayah pelanggan ' . $area,
            ]);
        }

        header('Location: customers.php');
        exit;
    }

    if ($action === 'delete_customer' && ($user['role'] ?? '') === 'admin') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM customers WHERE id = :id')->execute([':id' => $id]);
            flash('success', 'Pelanggan dihapus.');
        }
        header('Location: customers.php');
        exit;
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$editCustomer = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editId]);
    $editCustomer = $stmt->fetch() ?: null;
}

$areaFilter = trim((string) ($_GET['area_filter'] ?? ''));
$statusFilter = trim((string) ($_GET['status_filter'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

$packages = $pdo->query('SELECT * FROM packages WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
$areas = $pdo->query('SELECT * FROM areas WHERE is_active = 1 ORDER BY name ASC')->fetchAll();

$sqlCustomers = 'SELECT c.*, p.name AS package_name, p.speed, p.mikrotik_profile_name AS package_mikrotik_profile,
        (SELECT i.status FROM invoices i WHERE i.customer_id = c.id ORDER BY i.period_year DESC, i.period_month DESC, i.id DESC LIMIT 1) AS latest_invoice_status,
        (SELECT i.period_month FROM invoices i WHERE i.customer_id = c.id ORDER BY i.period_year DESC, i.period_month DESC, i.id DESC LIMIT 1) AS latest_invoice_month,
        (SELECT i.period_year FROM invoices i WHERE i.customer_id = c.id ORDER BY i.period_year DESC, i.period_month DESC, i.id DESC LIMIT 1) AS latest_invoice_year,
        (SELECT i.paid_at FROM invoices i WHERE i.customer_id = c.id AND i.status = "paid" ORDER BY i.paid_at DESC, i.id DESC LIMIT 1) AS latest_paid_at,
        (SELECT COUNT(*) FROM invoices i WHERE i.customer_id = c.id AND i.status = "unpaid") AS unpaid_invoice_count
    FROM customers c
    LEFT JOIN packages p ON p.id = c.package_id';
$paramsCustomers = [];
$where = [];

if ($areaFilter !== '') {
    $where[] = 'c.area = :area';
    $paramsCustomers[':area'] = $areaFilter;
}

if (in_array($statusFilter, ['active', 'suspended', 'inactive'], true)) {
    $where[] = 'c.status = :status';
    $paramsCustomers[':status'] = $statusFilter;
}

if ($search !== '') {
    $where[] = '(c.full_name LIKE :q OR c.customer_no LIKE :q OR c.phone LIKE :q OR c.area LIKE :q OR c.address LIKE :q OR c.router_name LIKE :q OR c.service_username LIKE :q)';
    $paramsCustomers[':q'] = '%' . $search . '%';
}

if ($where) {
    $sqlCustomers .= ' WHERE ' . implode(' AND ', $where);
}

$sqlCustomers .= ' ORDER BY c.id DESC';
$stmtCustomers = $pdo->prepare($sqlCustomers);
$stmtCustomers->execute($paramsCustomers);
$customers = $stmtCustomers->fetchAll();

$activeCustomers = 0;
$suspendedCustomers = 0;
$inactiveCustomers = 0;
$withIdCustomers = 0;
$uniqueAreas = [];
$uniqueRouters = [];
$totalUnpaidInvoices = 0;
foreach ($customers as $customerRow) {
    $statusValue = (string) ($customerRow['status'] ?? 'active');
    if ($statusValue === 'suspended') {
        $suspendedCustomers++;
    } elseif ($statusValue === 'inactive') {
        $inactiveCustomers++;
    } else {
        $activeCustomers++;
    }

    if (trim((string) ($customerRow['customer_no'] ?? '')) !== '') {
        $withIdCustomers++;
    }

    $areaName = trim((string) ($customerRow['area'] ?? ''));
    if ($areaName !== '') {
        $uniqueAreas[$areaName] = true;
    }

    $routerName = trim((string) ($customerRow['router_name'] ?? ''));
    if ($routerName !== '') {
        $uniqueRouters[$routerName] = true;
    }

    $totalUnpaidInvoices += (int) ($customerRow['unpaid_invoice_count'] ?? 0);
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-ornament page-ornament--gold mb-4">
  <div class="page-ornament-kicker"><i class="fa-solid fa-users-viewfinder me-2"></i>Manajemen Pelanggan</div>
  <h1 class="page-ornament-title">Pelanggan RT/RW Net</h1>
  <p class="page-ornament-text">Saya adaptasi pola referensi e-billing ke versi yang lebih rapi untuk app kita, fokus di ID pelanggan, layanan aktif, status billing, area, dan router tanpa menu yang gemuk.</p>
</section>

<section class="billing-kpi-strip mb-4">
  <div class="billing-kpi-strip__item">
    <div class="billing-kpi-strip__label">ID pelanggan berikutnya</div>
    <div class="billing-kpi-strip__value"><?= e($nextSuggestedCustomerNo) ?></div>
    <div class="billing-kpi-strip__note">Format default DSA + 4 digit</div>
  </div>
  <div class="billing-kpi-strip__item">
    <div class="billing-kpi-strip__label">Sudah punya ID</div>
    <div class="billing-kpi-strip__value"><?= $withIdCustomers ?></div>
    <div class="billing-kpi-strip__note">Dari <?= count($customers) ?> pelanggan di filter sekarang</div>
  </div>
  <div class="billing-kpi-strip__item">
    <div class="billing-kpi-strip__label">Area aktif</div>
    <div class="billing-kpi-strip__value"><?= count($uniqueAreas) ?></div>
    <div class="billing-kpi-strip__note">Wilayah yang terpakai di daftar ini</div>
  </div>
  <div class="billing-kpi-strip__item">
    <div class="billing-kpi-strip__label">Router / POP</div>
    <div class="billing-kpi-strip__value"><?= count($uniqueRouters) ?></div>
    <div class="billing-kpi-strip__note">Node layanan yang sudah diisi</div>
  </div>
</section>

<section class="isp-mini-grid mb-4">
  <div class="isp-mini-card isp-mini-card--emerald">
    <div class="isp-mini-card__label">Total Pelanggan</div>
    <div class="isp-mini-card__value"><?= count($customers) ?></div>
    <div class="isp-mini-card__note">Sesuai filter aktif</div>
  </div>
  <div class="isp-mini-card isp-mini-card--blue">
    <div class="isp-mini-card__label">Pelanggan Aktif</div>
    <div class="isp-mini-card__value"><?= $activeCustomers ?></div>
    <div class="isp-mini-card__note">Siap dibuatkan billing</div>
  </div>
  <div class="isp-mini-card isp-mini-card--amber">
    <div class="isp-mini-card__label">Suspend</div>
    <div class="isp-mini-card__value"><?= $suspendedCustomers ?></div>
    <div class="isp-mini-card__note">Perlu follow up</div>
  </div>
  <div class="isp-mini-card isp-mini-card--slate">
    <div class="isp-mini-card__label">Inactive</div>
    <div class="isp-mini-card__value"><?= $inactiveCustomers ?></div>
    <div class="isp-mini-card__note">Tidak ditagihkan dulu</div>
  </div>
  <div class="isp-mini-card isp-mini-card--red">
    <div class="isp-mini-card__label">Invoice Unpaid</div>
    <div class="isp-mini-card__value"><?= $totalUnpaidInvoices ?></div>
    <div class="isp-mini-card__note">Akumulasi semua pelanggan di list</div>
  </div>
</section>

<section class="grid lg:grid-cols-[1.08fr_0.92fr] gap-4 mb-4">
  <div class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--table">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <div class="section-kicker">Aksi Cepat</div>
        <h2 class="font-semibold mb-0">Workflow admin pelanggan</h2>
      </div>
      <span class="badge rounded-pill text-bg-primary">Phase 1 Active</span>
    </div>
    <div class="quick-module-grid">
      <a href="#form-pelanggan" class="quick-module-card quick-module-card--blue">
        <div class="quick-module-card__icon"><i class="fa-solid fa-user-plus"></i></div>
        <div class="quick-module-card__title">Tambah pelanggan</div>
        <div class="quick-module-card__desc">Masukkan data pelanggan baru dengan ID, area, paket, dan username layanan.</div>
        <div class="quick-module-card__cta">Isi form <i class="fa-solid fa-arrow-right ms-1"></i></div>
      </a>
      <a href="bills.php?status=unpaid" class="quick-module-card quick-module-card--amber">
        <div class="quick-module-card__icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        <div class="quick-module-card__title">Ke daftar piutang</div>
        <div class="quick-module-card__desc">Langsung cek pelanggan yang punya invoice unpaid dan butuh follow up.</div>
        <div class="quick-module-card__cta">Buka invoice <i class="fa-solid fa-arrow-right ms-1"></i></div>
      </a>
      <a href="readings.php" class="quick-module-card quick-module-card--emerald">
        <div class="quick-module-card__icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div class="quick-module-card__title">Generate billing</div>
        <div class="quick-module-card__desc">Lanjutkan ke pembuatan invoice periode baru setelah data pelanggan rapi.</div>
        <div class="quick-module-card__cta">Generate sekarang <i class="fa-solid fa-arrow-right ms-1"></i></div>
      </a>
      <a href="roadmap.php" class="quick-module-card quick-module-card--violet">
        <div class="quick-module-card__icon"><i class="fa-solid fa-sitemap"></i></div>
        <div class="quick-module-card__title">Lihat blueprint ISP</div>
        <div class="quick-module-card__desc">Pantau roadmap pengembangan supaya arah fitur tetap konsisten.</div>
        <div class="quick-module-card__cta">Buka blueprint <i class="fa-solid fa-arrow-right ms-1"></i></div>
      </a>
    </div>
  </div>

  <div class="isp-panel-card isp-panel-card--light">
    <div class="isp-panel-card__title">Checklist data pelanggan yang sehat</div>
    <ul class="isp-panel-list mb-0">
      <li>Setiap pelanggan sebaiknya punya <b>ID pelanggan</b>, area, paket, dan due day.</li>
      <li>Kalau layanan PPPoE dipakai, isi <b>username layanan</b> konsisten dengan ID pelanggan.</li>
      <li>Untuk operasional lapangan, nama <b>router / POP</b> sebaiknya tidak dibiarkan kosong.</li>
      <li>Begitu data dasar rapi, baru lanjut generate billing dan follow up invoice.</li>
    </ul>
  </div>
</section>

<div class="grid xl:grid-cols-[0.98fr_1.02fr] gap-4">
  <section id="form-pelanggan" class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--form">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
      <h2 class="font-semibold mb-0"><?= $editCustomer ? 'Edit Pelanggan' : 'Tambah Pelanggan RT/RW Net' ?></h2>
    </div>

    <div class="billing-note-card mb-3">
      <div class="billing-note-card__title"><i class="fa-solid fa-lightbulb me-2"></i>Pola data yang saya pakai</div>
      <ul class="billing-note-card__list mb-0">
        <li>ID pelanggan default auto format <b>DSA0001</b>, <b>DSA0002</b>, dan seterusnya.</li>
        <li>Kalau username layanan kosong, sistem akan samakan dulu ke <b>ID pelanggan</b>.</li>
        <li>Tujuannya biar data pelanggan, invoice, dan pencarian lapangan lebih konsisten.</li>
      </ul>
    </div>

    <form method="post" class="space-y-3 luxe-form">
      <input type="hidden" name="action" value="save_customer">
      <input type="hidden" name="id" value="<?= (int) ($editCustomer['id'] ?? 0) ?>">

      <div>
        <div class="d-flex justify-content-between align-items-center gap-2">
          <label class="text-sm mb-0">ID Pelanggan</label>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="fillSuggestedId" data-suggested-id="<?= e($nextSuggestedCustomerNo) ?>"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Pakai ID berikutnya</button>
        </div>
        <input name="customer_no" id="customer_no" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['customer_no'] ?? '')) ?>" placeholder="kosongkan untuk auto DSAxxxx">
        <div class="form-hint-text">ID ini akan ditonjolkan di invoice, daftar pelanggan, dan pencarian cepat.</div>
      </div>

      <div>
        <label class="text-sm">Nama Lengkap</label>
        <input name="full_name" required class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['full_name'] ?? '')) ?>">
      </div>

      <div class="grid md:grid-cols-2 gap-3">
        <div>
          <label class="text-sm">No HP</label>
          <input name="phone" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['phone'] ?? '')) ?>">
        </div>
        <div>
          <label class="text-sm">Area / Wilayah</label>
          <input name="area" list="area_options" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['area'] ?? '')) ?>" placeholder="contoh: RW 05 / Cluster A">
          <datalist id="area_options">
            <?php foreach ($areas as $areaRow): ?>
              <option value="<?= e((string) $areaRow['name']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>
      </div>

      <div>
        <label class="text-sm">Alamat</label>
        <textarea name="address" rows="2" class="mt-1 w-full border rounded px-3 py-2"><?= e((string) ($editCustomer['address'] ?? '')) ?></textarea>
      </div>

      <div>
        <label class="text-sm">Paket Internet</label>
        <select name="package_id" id="package_id" class="mt-1 w-full border rounded px-3 py-2">
          <option value="0">Pilih paket</option>
          <?php foreach ($packages as $package): ?>
            <option value="<?= (int) $package['id'] ?>" data-mikrotik-profile="<?= e((string) ($package['mikrotik_profile_name'] ?? '')) ?>" <?= ((int) ($editCustomer['package_id'] ?? 0) === (int) $package['id']) ? 'selected' : '' ?>><?= e(packageLabel($package)) ?> - <?= e(rupiah((int) $package['price'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="grid md:grid-cols-2 gap-3">
        <div>
          <label class="text-sm">Jenis Layanan</label>
          <select name="service_type" class="mt-1 w-full border rounded px-3 py-2">
            <?php foreach (['pppoe' => 'PPPoE', 'hotspot' => 'Hotspot', 'static' => 'Static IP', 'other' => 'Lainnya'] as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= (($editCustomer['service_type'] ?? 'pppoe') === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="text-sm">Router / OLT / POP</label>
          <input name="router_name" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['router_name'] ?? '')) ?>" placeholder="opsional nama router / site">
        </div>
      </div>

      <div class="grid md:grid-cols-2 gap-3">
        <div>
          <div class="d-flex justify-content-between align-items-center gap-2">
            <label class="text-sm mb-0">Username / ID Layanan</label>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="copyIdToService"><i class="fa-solid fa-arrows-to-dot me-1"></i>Samakan dengan ID</button>
          </div>
          <input name="service_username" id="service_username" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['service_username'] ?? '')) ?>" placeholder="default mengikuti ID pelanggan kalau kosong">
        </div>
        <div>
          <label class="text-sm">Catatan Layanan / Profil</label>
          <input name="mikrotik_profile_name" id="mikrotik_profile_name" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['mikrotik_profile_name'] ?? '')) ?>" placeholder="opsional, misal paket rumahan / catatan teknis">
        </div>
      </div>

      <div class="grid md:grid-cols-3 gap-3">
        <div>
          <label class="text-sm">ID Referensi Pelanggan</label>
          <input name="api_customer_id" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['api_customer_id'] ?? '')) ?>" placeholder="opsional untuk catatan internal">
        </div>
        <div>
          <label class="text-sm">Status Pelanggan</label>
          <select name="status" class="mt-1 w-full border rounded px-3 py-2">
            <option value="active" <?= (($editCustomer['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
            <option value="suspended" <?= (($editCustomer['status'] ?? '') === 'suspended') ? 'selected' : '' ?>>Suspended</option>
            <option value="inactive" <?= (($editCustomer['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
        <div>
          <label class="text-sm">Due Day</label>
          <input type="number" min="1" max="28" name="due_day" class="mt-1 w-full border rounded px-3 py-2" value="<?= (int) ($editCustomer['due_day'] ?? 10) ?>">
        </div>
      </div>

      <div>
        <label class="text-sm">Tanggal Join</label>
        <input type="date" name="join_date" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(dateInputValue((string) ($editCustomer['join_date'] ?? ''))) ?>">
      </div>

      <div>
        <label class="text-sm">Catatan</label>
        <textarea name="notes" rows="2" class="mt-1 w-full border rounded px-3 py-2"><?= e((string) ($editCustomer['notes'] ?? '')) ?></textarea>
      </div>

      <div class="flex gap-2 flex-wrap">
        <button class="btn btn-primary px-4 py-2"><i class="fa-solid fa-floppy-disk me-1"></i>Simpan Pelanggan</button>
        <?php if ($editCustomer): ?>
          <a href="customers.php" class="btn btn-outline-secondary px-4 py-2"><i class="fa-solid fa-xmark me-1"></i>Batal</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--table">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <h2 class="font-semibold mb-0">Daftar Pelanggan</h2>
        <div class="small text-secondary">Versi list lebih informatif, mendekati pola e-billing besar tapi tetap fokus ke kebutuhan billing kita.</div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="bills.php?status=unpaid" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-file-invoice-dollar me-1"></i>Lihat Piutang</a>
        <a href="roadmap.php" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-sitemap me-1"></i>Blueprint</a>
      </div>
    </div>

    <form class="grid md:grid-cols-4 gap-2 text-sm mb-4">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama, ID, HP, area, router, username" class="border rounded px-3 py-2">
      <select name="area_filter" class="border rounded px-3 py-2">
        <option value="">Semua wilayah</option>
        <?php foreach ($areas as $areaRow): ?>
          <option value="<?= e((string) $areaRow['name']) ?>" <?= $areaFilter === (string) $areaRow['name'] ? 'selected' : '' ?>><?= e((string) $areaRow['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status_filter" class="border rounded px-3 py-2">
        <option value="">Semua status</option>
        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
      <div class="d-flex gap-2">
        <button class="btn btn-primary w-full"><i class="fa-solid fa-filter me-1"></i>Filter</button>
        <a href="customers.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
      </div>
    </form>

    <div class="overflow-auto table-wrap">
      <table class="min-w-full text-sm js-data-table table-soft table-card-mode" data-page-size="10">
        <thead>
          <tr class="text-left border-b">
            <th class="py-2 pr-3">Identitas</th>
            <th class="py-2 pr-3">Paket & Area</th>
            <th class="py-2 pr-3">Layanan Teknis</th>
            <th class="py-2 pr-3">Status Billing</th>
            <th class="py-2 pr-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $customer): ?>
            <?php
              $latestPeriod = '-';
              if (!empty($customer['latest_invoice_month']) && !empty($customer['latest_invoice_year'])) {
                  $latestPeriod = periodLabel((int) $customer['latest_invoice_month'], (int) $customer['latest_invoice_year']);
              }
              $latestInvoiceStatus = (string) ($customer['latest_invoice_status'] ?? '');
              $unpaidInvoiceCount = (int) ($customer['unpaid_invoice_count'] ?? 0);
            ?>
            <tr class="border-b align-top">
              <td class="py-2 pr-3">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                  <div class="font-semibold"><?= e((string) $customer['full_name']) ?></div>
                  <span class="id-pill">ID <?= e((string) (($customer['customer_no'] ?: '-') ?: '-')) ?></span>
                </div>
                <div class="text-xs text-slate-500">HP: <?= e((string) ($customer['phone'] ?: '-')) ?></div>
                <div class="text-xs text-slate-500">Join: <?= e(formatDateId((string) ($customer['join_date'] ?? ''), '-')) ?></div>
                <div class="text-xs text-slate-500"><?= e((string) ($customer['address'] ?: '-')) ?></div>
              </td>
              <td class="py-2 pr-3">
                <div><?= e(packageLabel($customer)) ?></div>
                <div class="text-xs text-slate-500">Area: <?= e((string) ($customer['area'] ?: '-')) ?></div>
                <div class="text-xs text-slate-500">Status layanan: <?= e(serviceTypeLabel((string) ($customer['service_type'] ?? 'pppoe'))) ?></div>
                <div class="text-xs text-slate-500">Due day: <?= (int) ($customer['due_day'] ?? 10) ?></div>
              </td>
              <td class="py-2 pr-3">
                <div>ID/User: <?= e((string) ($customer['service_username'] ?: '-')) ?></div>
                <div class="text-xs text-slate-500">Router / POP: <?= e((string) ($customer['router_name'] ?: '-')) ?></div>
                <div class="text-xs text-slate-500">Profil: <?= e((string) (($customer['mikrotik_profile_name'] ?: $customer['package_mikrotik_profile']) ?: '-')) ?></div>
                <div class="text-xs text-slate-500">Ref: <?= e((string) ($customer['api_customer_id'] ?: '-')) ?></div>
              </td>
              <td class="py-2 pr-3">
                <div class="mb-1"><span class="badge <?= ($customer['status'] ?? '') === 'active' ? 'text-bg-success' : (($customer['status'] ?? '') === 'suspended' ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= e(customerStatusLabel((string) $customer['status'])) ?></span></div>
                <div class="mb-1"><span class="badge <?= customerIsolationBadgeClass($customer) ?>"><?= customerIsIsolated($customer) ? 'Sedang diisolir' : 'Tidak diisolir' ?></span></div>
                <div class="text-xs text-slate-500">Invoice terakhir: <?= e($latestPeriod) ?></div>
                <div class="text-xs text-slate-500">Status invoice: <?= e($latestInvoiceStatus === 'paid' ? 'Lunas' : ($latestInvoiceStatus === 'unpaid' ? 'Belum Lunas' : '-')) ?></div>
                <div class="text-xs text-slate-500">Unpaid aktif: <?= $unpaidInvoiceCount ?></div>
                <div class="text-xs text-slate-500">Bayar terakhir: <?= e(formatDateTimeId((string) ($customer['latest_paid_at'] ?? ''), '-')) ?></div>
              </td>
              <td class="py-2 pr-3">
                <div class="d-flex flex-wrap gap-2">
                  <a class="btn btn-sm btn-outline-secondary" href="customers.php?edit=<?= (int) $customer['id'] ?>"><i class="fa-solid fa-pen-to-square me-1"></i>Edit</a>
                  <a class="btn btn-sm btn-outline-primary" href="bills.php?q=<?= urlencode((string) (($customer['customer_no'] ?: $customer['service_username'] ?: $customer['full_name']) ?? '')) ?>"><i class="fa-solid fa-file-invoice me-1"></i>Invoice</a>
                </div>
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                  <form method="post" class="mt-2 inline" onsubmit="return confirm('Hapus pelanggan ini?')">
                    <input type="hidden" name="action" value="delete_customer">
                    <input type="hidden" name="id" value="<?= (int) $customer['id'] ?>">
                    <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can me-1"></i>Hapus</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$customers): ?>
            <tr><td colspan="5" class="py-4 text-slate-500">Belum ada pelanggan yang cocok dengan filter. Tambah paket dulu lalu masukkan pelanggan dan ID layanannya.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script>
(() => {
  const packageSelect = document.getElementById('package_id');
  const customerNoInput = document.getElementById('customer_no');
  const usernameInput = document.getElementById('service_username');
  const profileInput = document.getElementById('mikrotik_profile_name');
  const fillSuggestedIdBtn = document.getElementById('fillSuggestedId');
  const copyIdBtn = document.getElementById('copyIdToService');

  const applyPackage = () => {
    if (!packageSelect || !profileInput) return;
    const option = packageSelect.options[packageSelect.selectedIndex];
    if (!option) return;
    const profile = option.dataset.mikrotikProfile || '';
    if (profile && profileInput.value.trim() === '') {
      profileInput.value = profile;
    }
  };

  const fillSuggestedId = () => {
    if (!fillSuggestedIdBtn || !customerNoInput) return;
    if (customerNoInput.value.trim() !== '') return;
    customerNoInput.value = fillSuggestedIdBtn.dataset.suggestedId || '';
  };

  const copyIdToService = () => {
    if (!customerNoInput || !usernameInput) return;
    if (customerNoInput.value.trim() === '') return;
    usernameInput.value = customerNoInput.value.trim();
  };

  if (packageSelect && window.TomSelect) {
    new TomSelect(packageSelect, {
      create: false,
      maxItems: 1,
      searchField: ['text'],
      placeholder: 'Ketik nama paket...'
    });
  }

  packageSelect?.addEventListener('change', applyPackage);
  fillSuggestedIdBtn?.addEventListener('click', fillSuggestedId);
  copyIdBtn?.addEventListener('click', copyIdToService);
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
