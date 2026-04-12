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

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE customers SET customer_no = :customer_no, full_name = :full_name, address = :address,
                phone = :phone, area = :area, package_id = :package_id, api_customer_id = :api_customer_id,
                router_name = :router_name, status = :status, due_day = :due_day, join_date = :join_date,
                notes = :notes WHERE id = :id');
            $stmt->execute([
                ':customer_no' => $customerNo,
                ':full_name' => $fullName,
                ':address' => $address,
                ':phone' => $phone,
                ':area' => $area,
                ':package_id' => $packageId > 0 ? $packageId : null,
                ':api_customer_id' => $apiCustomerId,
                ':router_name' => $routerName,
                ':status' => $status,
                ':due_day' => $dueDay,
                ':join_date' => $joinDate,
                ':notes' => $notes,
                ':id' => $id,
            ]);
            flash('success', 'Pelanggan berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO customers(customer_no, full_name, address, phone, area, package_id,
                api_customer_id, router_name, status, due_day, join_date, notes)
                VALUES(:customer_no, :full_name, :address, :phone, :area, :package_id,
                :api_customer_id, :router_name, :status, :due_day, :join_date, :notes)');
            $stmt->execute([
                ':customer_no' => $customerNo,
                ':full_name' => $fullName,
                ':address' => $address,
                ':phone' => $phone,
                ':area' => $area,
                ':package_id' => $packageId > 0 ? $packageId : null,
                ':api_customer_id' => $apiCustomerId,
                ':router_name' => $routerName,
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
$customers = $pdo->query('SELECT c.*, p.name AS package_name, p.speed
    FROM customers c
    LEFT JOIN packages p ON p.id = c.package_id
    ORDER BY c.id DESC')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="grid lg:grid-cols-3 gap-4">
  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3"><?= $editCustomer ? 'Edit Pelanggan' : 'Tambah Pelanggan RT/RW Net' ?></h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="save_customer">
      <input type="hidden" name="id" value="<?= (int) ($editCustomer['id'] ?? 0) ?>">
      <div>
        <label class="text-sm">No Pelanggan</label>
        <input name="customer_no" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['customer_no'] ?? '')) ?>" placeholder="opsional, misal CUST-001">
      </div>
      <div>
        <label class="text-sm">Nama Lengkap</label>
        <input name="full_name" required class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['full_name'] ?? '')) ?>">
      </div>
      <div>
        <label class="text-sm">Area / Wilayah</label>
        <input name="area" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['area'] ?? '')) ?>" placeholder="contoh: RW 05 / Cluster A">
      </div>
      <div>
        <label class="text-sm">Paket Internet</label>
        <select name="package_id" class="mt-1 w-full border rounded px-3 py-2">
          <option value="0">Pilih paket</option>
          <?php foreach ($packages as $package): ?>
            <option value="<?= (int) $package['id'] ?>" <?= ((int) ($editCustomer['package_id'] ?? 0) === (int) $package['id']) ? 'selected' : '' ?>><?= e(packageLabel($package)) ?> - <?= e(rupiah((int) $package['price'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-sm">No HP</label>
        <input name="phone" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['phone'] ?? '')) ?>">
      </div>
      <div>
        <label class="text-sm">Alamat</label>
        <textarea name="address" rows="2" class="mt-1 w-full border rounded px-3 py-2"><?= e((string) ($editCustomer['address'] ?? '')) ?></textarea>
      </div>
      <div class="grid md:grid-cols-2 gap-3">
        <div>
          <label class="text-sm">API Customer ID</label>
          <input name="api_customer_id" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['api_customer_id'] ?? '')) ?>" placeholder="untuk mapping API">
        </div>
        <div>
          <label class="text-sm">Router / PPPoE Name</label>
          <input name="router_name" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editCustomer['router_name'] ?? '')) ?>">
        </div>
      </div>
      <div class="grid md:grid-cols-3 gap-3">
        <div>
          <label class="text-sm">Status</label>
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
        <div>
          <label class="text-sm">Tanggal Join</label>
          <input type="date" name="join_date" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(dateInputValue((string) ($editCustomer['join_date'] ?? ''))) ?>">
        </div>
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
    <h2 class="font-semibold mb-3">Daftar Pelanggan</h2>
    <div class="overflow-auto table-wrap">
      <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
        <thead>
          <tr class="text-left border-b">
            <th class="py-2 pr-3">Pelanggan</th>
            <th class="py-2 pr-3">Paket</th>
            <th class="py-2 pr-3">Area</th>
            <th class="py-2 pr-3">API / Router</th>
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
                <div class="text-xs text-slate-500">Join: <?= e(formatDateId((string) ($customer['join_date'] ?? ''), '-')) ?> • Due day: <?= (int) ($customer['due_day'] ?? 10) ?></div>
              </td>
              <td class="py-2 pr-3">
                <?= e(packageLabel($customer)) ?><br>
                <span class="text-xs text-slate-500"><?= e((string) ($customer['address'] ?: '-')) ?></span>
              </td>
              <td class="py-2 pr-3"><?= e((string) ($customer['area'] ?: '-')) ?></td>
              <td class="py-2 pr-3">
                <div>API ID: <?= e((string) ($customer['api_customer_id'] ?: '-')) ?></div>
                <div class="text-xs text-slate-500">Router: <?= e((string) ($customer['router_name'] ?: '-')) ?></div>
              </td>
              <td class="py-2 pr-3"><span class="badge <?= ($customer['status'] ?? '') === 'active' ? 'text-bg-success' : (($customer['status'] ?? '') === 'suspended' ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= e(customerStatusLabel((string) $customer['status'])) ?></span></td>
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
            <tr><td colspan="6" class="py-4 text-slate-500">Belum ada pelanggan. Tambah paket dulu lalu masukkan pelanggan.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
