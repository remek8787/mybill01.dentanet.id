<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'staff']);

$pdo = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_area') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            flash('error', 'Nama wilayah wajib diisi.');
            header('Location: areas.php');
            exit;
        }

        if ($id > 0) {
            $pdo->prepare('UPDATE areas SET name = :name, description = :description, is_active = :is_active WHERE id = :id')->execute([
                ':name' => $name,
                ':description' => $description,
                ':is_active' => $isActive,
                ':id' => $id,
            ]);
            flash('success', 'Wilayah berhasil diperbarui.');
        } else {
            $pdo->prepare('INSERT INTO areas(name, description, is_active) VALUES(:name, :description, :is_active)')->execute([
                ':name' => $name,
                ':description' => $description,
                ':is_active' => $isActive,
            ]);
            flash('success', 'Wilayah berhasil ditambahkan.');
        }
        header('Location: areas.php');
        exit;
    }

    if ($action === 'delete_area' && ($user['role'] ?? '') === 'admin') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM areas WHERE id = :id')->execute([':id' => $id]);
            flash('success', 'Wilayah dihapus.');
        }
        header('Location: areas.php');
        exit;
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$editArea = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM areas WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editId]);
    $editArea = $stmt->fetch() ?: null;
}

$areas = $pdo->query('SELECT a.*, (SELECT COUNT(*) FROM customers c WHERE c.area = a.name) AS customer_count FROM areas a ORDER BY a.name ASC')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="page-ornament page-ornament--gold mb-4">
  <div class="page-ornament-kicker"><i class="fa-solid fa-map-location-dot me-2"></i>Master Wilayah</div>
  <h1 class="page-ornament-title">Manajemen Wilayah Pelanggan</h1>
  <p class="page-ornament-text">Bikin master wilayah supaya pelanggan lebih gampang disortir dan dikelompokkan sesuai area masing-masing.</p>
</section>

<section class="isp-mini-grid mb-4">
  <div class="isp-mini-card isp-mini-card--emerald">
    <div class="isp-mini-card__label">Total Wilayah</div>
    <div class="isp-mini-card__value"><?= count($areas) ?></div>
    <div class="isp-mini-card__note">Master area tersimpan</div>
  </div>
</section>

<div class="grid lg:grid-cols-3 gap-4">
  <section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--form">
    <h2 class="font-semibold mb-3"><?= $editArea ? 'Edit Wilayah' : 'Tambah Wilayah' ?></h2>
    <form method="post" class="space-y-3 luxe-form">
      <input type="hidden" name="action" value="save_area">
      <input type="hidden" name="id" value="<?= (int) ($editArea['id'] ?? 0) ?>">
      <div>
        <label class="text-sm">Nama Wilayah</label>
        <input name="name" required class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editArea['name'] ?? '')) ?>" placeholder="contoh: Area Kota / RW 05">
      </div>
      <div>
        <label class="text-sm">Deskripsi</label>
        <textarea name="description" rows="3" class="mt-1 w-full border rounded px-3 py-2"><?= e((string) ($editArea['description'] ?? '')) ?></textarea>
      </div>
      <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" <?= ((int) ($editArea['is_active'] ?? 1) === 1) ? 'checked' : '' ?>> Wilayah aktif</label>
      <div class="flex gap-2 flex-wrap">
        <button class="btn btn-primary px-4 py-2"><i class="fa-solid fa-floppy-disk me-1"></i>Simpan Wilayah</button>
        <?php if ($editArea): ?>
          <a href="areas.php" class="btn btn-outline-secondary px-4 py-2"><i class="fa-solid fa-xmark me-1"></i>Batal</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="bg-white rounded-xl shadow p-4 lg:col-span-2 luxe-card luxe-card--table">
    <h2 class="font-semibold mb-3">Daftar Wilayah</h2>
    <div class="overflow-auto table-wrap">
      <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
        <thead>
          <tr class="text-left border-b">
            <th class="py-2 pr-3">Wilayah</th>
            <th class="py-2 pr-3">Deskripsi</th>
            <th class="py-2 pr-3">Jumlah Pelanggan</th>
            <th class="py-2 pr-3">Status</th>
            <th class="py-2 pr-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($areas as $area): ?>
            <tr class="border-b align-top">
              <td class="py-2 pr-3 font-semibold"><?= e((string) $area['name']) ?></td>
              <td class="py-2 pr-3"><?= e((string) ($area['description'] ?: '-')) ?></td>
              <td class="py-2 pr-3"><?= (int) ($area['customer_count'] ?? 0) ?></td>
              <td class="py-2 pr-3"><span class="badge <?= ((int) ($area['is_active'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= ((int) ($area['is_active'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif' ?></span></td>
              <td class="py-2 pr-3">
                <a class="btn btn-sm btn-outline-secondary" href="areas.php?edit=<?= (int) $area['id'] ?>"><i class="fa-solid fa-pen-to-square me-1"></i>Edit</a>
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                  <form method="post" class="inline" onsubmit="return confirm('Hapus wilayah ini?')">
                    <input type="hidden" name="action" value="delete_area">
                    <input type="hidden" name="id" value="<?= (int) $area['id'] ?>">
                    <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can me-1"></i>Hapus</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$areas): ?>
            <tr><td colspan="5" class="py-4 text-slate-500">Belum ada master wilayah.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
