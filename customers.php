<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'staff']);

$pdo = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_customer') {
        $id = (int) ($_POST['id'] ?? 0);
        $customerNo = trim((string) ($_POST['customer_no'] ?? ''));
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

        if (!in_array($status, ['active', 'suspended', 'inactive'], true)) {
            $status = 'active';
        }

        if (!in_array($serviceType, ['pppoe', 'hotspot', 'static', 'other'], true)) {
            $serviceType = 'pppoe';
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

$packages = $pdo->query('SELECT * FROM packages WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
$customers = $pdo->query('SELECT c.*, p.name AS package_name, p.speed, p.mikrotik_profile_name AS package_mikrotik_profile
    FROM customers c
    LEFT JOIN packages p ON p.id = c.package_id
    ORDER BY c.id DESC')->fetchAll();

$mikrotikProfiles = [];
$mikrotikSecrets = [];
$mikrotikError = '';
if (mikrotikIsConfigured()) {
    try {
        $mikrotikProfiles = mikrotikFetchPppProfiles();
        $mikrotikSecrets = mikrotikFetchPppSecrets();
    } catch (Throwable $e) {
        $mikrotikError = $e->getMessage();
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="grid lg:grid-cols-3 gap-4">
  <section class="bg-white rounded-xl shadow p-4">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
      <h2 class="font-semibold mb-0"><?= $editCustomer ? 'Edit Pelanggan' : 'Tambah Pelanggan RT/RW Net' ?></h2>
      <a href="mikrotik.php" class="btn btn-sm btn-outline-primary">Sinkron MikroTik</a>
    </div>
    <?php if (!mikrotikIsConfigured()): ?>
      <div class="rounded-3 border border-amber-200 bg-amber-50 p-3 small text-amber-800 mb-3">
        Setting MikroTik belum diisi. Isi dulu di menu <b>Pengaturan</b> supaya dropdown secret dan profile otomatis muncul.
      </div>
    <?php elseif ($mikrotikError !== ''): ?>
      <div class="rounded-3 border border-red-200 bg-red-50 p-3 small text-red-800 mb-3">
        Gagal ambil data MikroTik: <?= e($mikrotikError) ?>
      </div>
    <?php else: ?>
      <div class="rounded-3 border border-emerald-200 bg-emerald-50 p-3 small text-emerald-800 mb-3">
        Data MikroTik siap dipilih. Secret terbaca: <b><?= count($mikrotikSecrets) ?></b> • Profile terbaca: <b><?= count($mikrotikProfiles) ?></b>
      </div>
    <?php endif; ?>

    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="save_customer">
      <input type="hidden" name="id" value="<?= (int) ($editCustomer['id'] ?? 0) ?>">
      <div>
        <label class="text-sm">No Pelanggan</label>
        <input name="customer_no" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['customer_no'] ?? '')) ?>" placeholder="misal CUST-001">
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
          <input name="area" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['area'] ?? '')) ?>" placeholder="contoh: RW 05 / Cluster A">
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
      <div>
        <label class="text-sm">Username PPPoE / Secret MikroTik</label>
        <select name="mikrotik_secret_id" id="mikrotik_secret_id" class="mt-1 w-full border rounded px-3 py-2">
          <option value="">Pilih dari MikroTik (opsional)</option>
          <?php foreach ($mikrotikSecrets as $secret): ?>
            <?php $selected = ((string) ($editCustomer['mikrotik_secret_id'] ?? '') === (string) ($secret['id'] ?? '')); ?>
            <option value="<?= e((string) ($secret['id'] ?? '')) ?>" data-username="<?= e((string) ($secret['name'] ?? '')) ?>" data-profile="<?= e((string) ($secret['profile'] ?? '')) ?>" <?= $selected ? 'selected' : '' ?>><?= e((string) ($secret['name'] ?? '-')) ?> • <?= e((string) ($secret['profile'] ?? '-')) ?> • <?= e(((string) ($secret['disabled'] ?? 'false')) === 'true' ? 'disabled' : 'active') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid md:grid-cols-2 gap-3">
        <div>
          <label class="text-sm">Username Layanan</label>
          <input name="service_username" id="service_username" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['service_username'] ?? '')) ?>" placeholder="otomatis terisi dari secret, tapi masih bisa diedit">
        </div>
        <div>
          <label class="text-sm">Profile MikroTik</label>
          <input name="mikrotik_profile_name" id="mikrotik_profile_name" list="mikrotik_profile_options" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['mikrotik_profile_name'] ?? '')) ?>" placeholder="bisa pilih dari daftar atau ketik manual">
          <datalist id="mikrotik_profile_options">
            <?php foreach ($mikrotikProfiles as $profile): ?>
              <?php $profileName = (string) ($profile['name'] ?? ''); ?>
              <option value="<?= e($profileName) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>
      </div>
      <div class="grid md:grid-cols-3 gap-3">
        <div>
          <label class="text-sm">API Customer ID</label>
          <input name="api_customer_id" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['api_customer_id'] ?? '')) ?>" placeholder="untuk mapping eksternal">
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
        <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan Pelanggan</button>
        <?php if ($editCustomer): ?>
          <a href="customers.php" class="px-4 py-2 rounded bg-slate-200 text-slate-800">Batal</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="bg-white rounded-xl shadow p-4 lg:col-span-2">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h2 class="font-semibold mb-0">Daftar Pelanggan</h2>
      <div class="small text-secondary">Menampilkan data billing + referensi MikroTik + status isolir.</div>
    </div>
    <div class="overflow-auto table-wrap">
      <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
        <thead>
          <tr class="text-left border-b">
            <th class="py-2 pr-3">Pelanggan</th>
            <th class="py-2 pr-3">Paket / Layanan</th>
            <th class="py-2 pr-3">MikroTik</th>
            <th class="py-2 pr-3">Status</th>
            <th class="py-2 pr-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $customer): ?>
            <tr class="border-b align-top">
              <td class="py-2 pr-3">
                <div class="font-semibold"><?= e((string) $customer['full_name']) ?></div>
                <div class="text-xs text-slate-500">No: <?= e((string) ($customer['customer_no'] ?: '-')) ?> • HP: <?= e((string) ($customer['phone'] ?: '-')) ?></div>
                <div class="text-xs text-slate-500">Area: <?= e((string) ($customer['area'] ?: '-')) ?> • Join: <?= e(formatDateId((string) ($customer['join_date'] ?? ''), '-')) ?></div>
                <div class="text-xs text-slate-500"><?= e((string) ($customer['address'] ?: '-')) ?></div>
              </td>
              <td class="py-2 pr-3">
                <div><?= e(packageLabel($customer)) ?></div>
                <div class="text-xs text-slate-500">Layanan: <?= e(serviceTypeLabel((string) ($customer['service_type'] ?? 'pppoe'))) ?></div>
                <div class="text-xs text-slate-500">Due day: <?= (int) ($customer['due_day'] ?? 10) ?> • API ID: <?= e((string) ($customer['api_customer_id'] ?: '-')) ?></div>
              </td>
              <td class="py-2 pr-3">
                <div>Secret/User: <?= e((string) ($customer['service_username'] ?: '-')) ?></div>
                <div class="text-xs text-slate-500">Profile: <?= e((string) (($customer['mikrotik_profile_name'] ?: $customer['package_mikrotik_profile']) ?: '-')) ?></div>
                <div class="text-xs text-slate-500">Router: <?= e((string) ($customer['router_name'] ?: '-')) ?></div>
                <div class="text-xs text-slate-500">Sync: <?= e(mikrotikSyncLabel((string) ($customer['mikrotik_last_status'] ?? ''))) ?></div>
              </td>
              <td class="py-2 pr-3">
                <div class="mb-1"><span class="badge <?= ($customer['status'] ?? '') === 'active' ? 'text-bg-success' : (($customer['status'] ?? '') === 'suspended' ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= e(customerStatusLabel((string) $customer['status'])) ?></span></div>
                <div><span class="badge <?= customerIsolationBadgeClass($customer) ?>"><?= customerIsIsolated($customer) ? 'Sedang diisolir' : 'Tidak diisolir' ?></span></div>
                <div class="text-xs text-slate-500 mt-1">Last sync: <?= e(formatDateTimeId((string) ($customer['last_synced_at'] ?? ''), '-')) ?></div>
              </td>
              <td class="py-2 pr-3">
                <a class="px-2 py-1 rounded bg-slate-200" href="customers.php?edit=<?= (int) $customer['id'] ?>">Edit</a>
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                  <form method="post" class="inline" onsubmit="return confirm('Hapus pelanggan ini?')">
                    <input type="hidden" name="action" value="delete_customer">
                    <input type="hidden" name="id" value="<?= (int) $customer['id'] ?>">
                    <button class="px-2 py-1 rounded bg-red-100 text-red-700">Hapus</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$customers): ?>
            <tr><td colspan="5" class="py-4 text-slate-500">Belum ada pelanggan. Tambah paket dulu lalu masukkan pelanggan dan secret PPPoE-nya.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script>
(() => {
  const packageSelect = document.getElementById('package_id');
  const secretSelect = document.getElementById('mikrotik_secret_id');
  const usernameInput = document.getElementById('service_username');
  const profileSelect = document.getElementById('mikrotik_profile_name');

  const applySecret = () => {
    if (!secretSelect) return;
    const option = secretSelect.options[secretSelect.selectedIndex];
    if (!option) return;
    const username = option.dataset.username || '';
    const profile = option.dataset.profile || '';
    if (username && usernameInput && usernameInput.value.trim() === '') {
      usernameInput.value = username;
    }
    if (profile && profileSelect && profileSelect.value === '') {
      profileSelect.value = profile;
    }
  };

  const applyPackage = () => {
    if (!packageSelect || !profileSelect) return;
    const option = packageSelect.options[packageSelect.selectedIndex];
    if (!option) return;
    const profile = option.dataset.mikrotikProfile || '';
    if (profile && profileSelect.value === '') {
      profileSelect.value = profile;
    }
  };

  if (secretSelect && window.TomSelect) {
    new TomSelect(secretSelect, {
      create: false,
      maxItems: 1,
      searchField: ['text'],
      placeholder: 'Ketik username secret / profile...'
    });
  }

  if (packageSelect && window.TomSelect) {
    new TomSelect(packageSelect, {
      create: false,
      maxItems: 1,
      searchField: ['text'],
      placeholder: 'Ketik nama paket...'
    });
  }

  packageSelect?.addEventListener('change', applyPackage);
  secretSelect?.addEventListener('change', applySecret);
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
