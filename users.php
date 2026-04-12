<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin']);

$pdo = db();
$current = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_user') {
        $id = (int) ($_POST['id'] ?? 0);
        $username = trim((string) ($_POST['username'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? 'staff'));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $fullName === '' || !in_array($role, ['admin', 'staff'], true)) {
            flash('error', 'Data user tidak valid.');
            header('Location: users.php');
            exit;
        }

        if ($id > 0) {
            $existingStmt = $pdo->prepare('SELECT is_hidden, is_superuser FROM users WHERE id = :id LIMIT 1');
            $existingStmt->execute([':id' => $id]);
            $existing = $existingStmt->fetch() ?: null;
            if ($existing && (((int) ($existing['is_hidden'] ?? 0) === 1) || ((int) ($existing['is_superuser'] ?? 0) === 1))) {
                flash('error', 'User sistem tidak bisa diedit dari panel ini.');
                header('Location: users.php');
                exit;
            }

            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET username = :username, full_name = :full_name, role = :role, password_hash = :password_hash WHERE id = :id');
                $stmt->execute([
                    ':username' => $username,
                    ':full_name' => $fullName,
                    ':role' => $role,
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ':id' => $id,
                ]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username = :username, full_name = :full_name, role = :role WHERE id = :id');
                $stmt->execute([
                    ':username' => $username,
                    ':full_name' => $fullName,
                    ':role' => $role,
                    ':id' => $id,
                ]);
            }
            flash('success', 'User berhasil diperbarui.');
        } else {
            if ($password === '') {
                flash('error', 'Password wajib untuk user baru.');
                header('Location: users.php');
                exit;
            }
            $stmt = $pdo->prepare('INSERT INTO users(username, password_hash, role, full_name) VALUES(:username, :password_hash, :role, :full_name)');
            $stmt->execute([
                ':username' => $username,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => $role,
                ':full_name' => $fullName,
            ]);
            flash('success', 'User baru berhasil ditambahkan.');
        }

        header('Location: users.php');
        exit;
    }

    if ($action === 'delete_user') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0 && $id !== (int) $current['id']) {
            $checkStmt = $pdo->prepare('SELECT is_hidden, is_superuser FROM users WHERE id = :id LIMIT 1');
            $checkStmt->execute([':id' => $id]);
            $target = $checkStmt->fetch() ?: null;
            if ($target && (((int) ($target['is_hidden'] ?? 0) === 1) || ((int) ($target['is_superuser'] ?? 0) === 1))) {
                flash('error', 'User sistem tidak bisa dihapus.');
            } else {
                $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);
                flash('success', 'User dihapus.');
            }
        }
        header('Location: users.php');
        exit;
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$editUser = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id AND is_hidden = 0 LIMIT 1');
    $stmt->execute([':id' => $editId]);
    $editUser = $stmt->fetch() ?: null;
}

$users = $pdo->query('SELECT * FROM users WHERE is_hidden = 0 ORDER BY id ASC')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="grid lg:grid-cols-3 gap-4">
  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3"><?= $editUser ? 'Edit User' : 'Tambah User' ?></h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="save_user">
      <input type="hidden" name="id" value="<?= (int) ($editUser['id'] ?? 0) ?>">
      <div><label class="text-sm">Nama Lengkap</label><input name="full_name" required class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editUser['full_name'] ?? '')) ?>"></div>
      <div><label class="text-sm">Username</label><input name="username" required class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string) ($editUser['username'] ?? '')) ?>"></div>
      <div><label class="text-sm">Role</label><select name="role" class="mt-1 w-full border rounded px-3 py-2"><option value="admin" <?= (($editUser['role'] ?? '') === 'admin') ? 'selected' : '' ?>>admin</option><option value="staff" <?= (($editUser['role'] ?? '') === 'staff') ? 'selected' : '' ?>>staff</option></select></div>
      <div class="text-xs text-slate-500">Akun sistem dan superuser tersembunyi tidak akan tampil di daftar ini.</div>
      <div><label class="text-sm">Password <?= $editUser ? '(kosongkan jika tidak diubah)' : '' ?></label><input name="password" type="password" class="mt-1 w-full border rounded px-3 py-2"></div>
      <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan User</button>
    </form>
  </section>
  <section class="bg-white rounded-xl shadow p-4 lg:col-span-2">
    <h2 class="font-semibold mb-3">Daftar User</h2>
    <div class="overflow-auto">
      <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
        <thead><tr class="text-left border-b"><th class="py-2 pr-3">ID</th><th class="py-2 pr-3">Nama</th><th class="py-2 pr-3">Username</th><th class="py-2 pr-3">Role</th><th class="py-2 pr-3">Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <tr class="border-b">
              <td class="py-2 pr-3"><?= (int) $user['id'] ?></td>
              <td class="py-2 pr-3"><?= e((string) $user['full_name']) ?></td>
              <td class="py-2 pr-3"><?= e((string) $user['username']) ?></td>
              <td class="py-2 pr-3"><?= e(displayRoleLabel($user)) ?></td>
              <td class="py-2 pr-3">
                <a class="px-2 py-1 rounded bg-slate-200" href="users.php?edit=<?= (int) $user['id'] ?>">Edit</a>
                <?php if ((int) $user['id'] !== (int) $current['id']): ?>
                  <form method="post" class="inline" onsubmit="return confirm('Hapus user ini?')"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><button class="px-2 py-1 rounded bg-red-100 text-red-700">Hapus</button></form>
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
