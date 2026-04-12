<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'staff']);

$pdo = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_package') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $speed = trim((string) ($_POST['speed'] ?? ''));
        $price = normalizeCurrencyInput($_POST['price'] ?? 0);
        $description = trim((string) ($_POST['description'] ?? ''));
        $apiPlanId = trim((string) ($_POST['api_plan_id'] ?? ''));
        $mikrotikProfileName = trim((string) ($_POST['mikrotik_profile_name'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $price <= 0) {
            flash('error', 'Nama paket dan harga wajib diisi.');
            header('Location: packages.php');
            exit;
        }

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE packages SET name = :name, speed = :speed, price = :price,
                description = :description, api_plan_id = :api_plan_id, mikrotik_profile_name = :mikrotik_profile_name,
                is_active = :is_active WHERE id = :id');
            $stmt->execute([
                ':name' => $name,
                ':speed' => $speed,
                ':price' => $price,
                ':description' => $description,
                ':api_plan_id' => $apiPlanId,
                ':mikrotik_profile_name' => $mikrotikProfileName,
                ':is_active' => $isActive,
                ':id' => $id,
            ]);
            flash('success', 'Paket berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO packages(name, speed, price, description, api_plan_id, mikrotik_profile_name, is_active)
                VALUES(:name, :speed, :price, :description, :api_plan_id, :mikrotik_profile_name, :is_active)');
            $stmt->execute([
                ':name' => $name,
                ':speed' => $speed,
                ':price' => $price,
                ':description' => $description,
                ':api_plan_id' => $apiPlanId,
                ':mikrotik_profile_name' => $mikrotikProfileName,
                ':is_active' => $isActive,
            ]);
            flash('success', 'Paket berhasil ditambahkan.');
        }

        header('Location: packages.php');
        exit;
    }

    if ($action === 'delete_package' && ($user['role'] ?? '') === 'admin') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM packages WHERE id = :id')->execute([':id' => $id]);
            flash('success', 'Paket dihapus.');
        }
        header('Location: packages.php');
        exit;
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$editPackage = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM packages WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editId]);
    $editPackage = $stmt->fetch() ?: null;
}

$packages = $pdo->query('SELECT * FROM packages ORDER BY is_active DESC, id DESC')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="page-ornament page-ornament--violet mb-4">
  <div class="page-ornament-kicker"><i class="fa-solid fa-box-open me-2"></i>Master Paket</div>
  <h1 class="page-ornament-title">Kelola Paket Internet</h1>
  <p class="page-ornament-text">Atur paket, harga, speed, dan mapping profile MikroTik dengan layout yang lebih premium dan berkarakter.</p>
</section>

<div class="grid lg:grid-cols-3 gap-4">
  <section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--form">
    <h2 class="font-semibold mb-3"><?= $editPackage ? 'Edit Paket' : 'Tambah Paket Internet' ?></h2>
    <form method="post" class="space-y-3 luxe-form">
      <input type="hidden" name="action" value="save_package">
      <input type="hidden" name="id" value="<?= (int) ($editPackage['id'] ?? 0) ?>">
      <div>
        <label class="text-sm">Nama Paket</label>
        <input name="name" required class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editPackage['name'] ?? '')) ?>">
      </div>
      <div>
        <label class="text-sm">Speed</label>
        <input name="speed" class="mt-1 w-full border rounded px-3 py-2" placeholder="contoh: 20 Mbps" value="<?= e((string) ($editPackage['speed'] ?? '')) ?>">
      </div>
      <div>
        <label class="text-sm">Harga per Bulan</label>
        <input name="price" type="number" min="0" required class="mt-1 w-full border rounded px-3 py-2" value="<?= (int) ($editPackage['price'] ?? 0) ?>">
      </div>
      <div>
        <label class="text-sm">Mapping Profile MikroTik</label>
        <input name="mikrotik_profile_name" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editPackage['mikrotik_profile_name'] ?? '')) ?>" placeholder="contoh: pppoe-10mb">
        <div class="text-xs text-slate-500 mt-1">Opsional, dipakai sebagai profile default saat pilih paket pelanggan.</div>
      </div>
      <div>
        <label class="text-sm">API Plan ID</label>
        <input name="api_plan_id" class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editPackage['api_plan_id'] ?? '')) ?>" placeholder="opsional untuk mapping ke API lain">
      </div>
      <div>
        <label class="text-sm">Deskripsi</label>
        <textarea name="description" rows="3" class="mt-1 w-full border rounded px-3 py-2"><?= e((string) ($editPackage['description'] ?? '')) ?></textarea>
      </div>
      <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" <?= ((int) ($editPackage['is_active'] ?? 1) === 1) ? 'checked' : '' ?>> Paket aktif</label>
      <div class="flex gap-2 flex-wrap">
        <button class="btn btn-primary px-4 py-2"><i class="fa-solid fa-floppy-disk me-1"></i>Simpan Paket</button>
        <?php if ($editPackage): ?>
          <a href="packages.php" class="btn btn-outline-secondary px-4 py-2"><i class="fa-solid fa-xmark me-1"></i>Batal</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="bg-white rounded-xl shadow p-4 lg:col-span-2 luxe-card luxe-card--table">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h2 class="font-semibold mb-0">Daftar Paket</h2>
      <div class="small text-secondary">Tambahkan mapping profile agar admin lebih cepat saat input pelanggan.</div>
    </div>
    <div class="overflow-auto">
      <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
        <thead>
          <tr class="text-left border-b">
            <th class="py-2 pr-3">Nama</th>
            <th class="py-2 pr-3">Speed</th>
            <th class="py-2 pr-3">Harga</th>
            <th class="py-2 pr-3">Profile MikroTik</th>
            <th class="py-2 pr-3">API ID</th>
            <th class="py-2 pr-3">Status</th>
            <th class="py-2 pr-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($packages as $package): ?>
            <tr class="border-b align-top">
              <td class="py-2 pr-3">
                <div class="font-semibold"><?= e((string) $package['name']) ?></div>
                <div class="text-xs text-slate-500"><?= e((string) ($package['description'] ?? '')) ?></div>
              </td>
              <td class="py-2 pr-3"><?= e((string) ($package['speed'] ?? '-')) ?></td>
              <td class="py-2 pr-3"><?= e(rupiah((int) $package['price'])) ?></td>
              <td class="py-2 pr-3"><?= e((string) ($package['mikrotik_profile_name'] ?? '-')) ?></td>
              <td class="py-2 pr-3"><?= e((string) ($package['api_plan_id'] ?? '-')) ?></td>
              <td class="py-2 pr-3"><span class="badge <?= ((int) $package['is_active'] === 1) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= ((int) $package['is_active'] === 1) ? 'Aktif' : 'Nonaktif' ?></span></td>
              <td class="py-2 pr-3">
                <a class="btn btn-sm btn-outline-secondary" href="packages.php?edit=<?= (int) $package['id'] ?>"><i class="fa-solid fa-pen-to-square me-1"></i>Edit</a>
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                  <form method="post" class="inline" onsubmit="return confirm('Hapus paket ini?')">
                    <input type="hidden" name="action" value="delete_package">
                    <input type="hidden" name="id" value="<?= (int) $package['id'] ?>">
                    <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can me-1"></i>Hapus</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
