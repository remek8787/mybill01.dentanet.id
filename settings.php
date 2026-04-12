<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin']);

$pdo = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_company') {
        updateSetting('company_name', trim((string) ($_POST['company_name'] ?? APP_NAME)));
        updateSetting('company_address', trim((string) ($_POST['company_address'] ?? '')));
        updateSetting('company_phone', trim((string) ($_POST['company_phone'] ?? '')));
        updateSetting('company_note', trim((string) ($_POST['company_note'] ?? '')));
        flash('success', 'Profil usaha berhasil diperbarui.');
        header('Location: settings.php');
        exit;
    }

    if ($action === 'update_api') {
        updateSetting('api_provider', trim((string) ($_POST['api_provider'] ?? 'Custom API')));
        updateSetting('api_base_url', trim((string) ($_POST['api_base_url'] ?? '')));
        updateSetting('api_token', trim((string) ($_POST['api_token'] ?? '')));
        updateSetting('api_username', trim((string) ($_POST['api_username'] ?? '')));
        updateSetting('api_password', trim((string) ($_POST['api_password'] ?? '')));
        updateSetting('api_secret', trim((string) ($_POST['api_secret'] ?? '')));
        updateSetting('api_notes', trim((string) ($_POST['api_notes'] ?? '')));
        flash('success', 'Konfigurasi API berhasil diperbarui.');
        header('Location: settings.php');
        exit;
    }

    if ($action === 'change_password') {
        $oldPassword = (string) ($_POST['old_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int) $user['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($oldPassword, (string) $row['password_hash'])) {
            flash('error', 'Password lama salah.');
            header('Location: settings.php');
            exit;
        }
        if (strlen($newPassword) < 6) {
            flash('error', 'Password baru minimal 6 karakter.');
            header('Location: settings.php');
            exit;
        }
        if ($newPassword !== $confirmPassword) {
            flash('error', 'Konfirmasi password tidak sama.');
            header('Location: settings.php');
            exit;
        }

        $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id')->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':id' => (int) $user['id'],
        ]);
        flash('success', 'Password admin berhasil diubah.');
        header('Location: settings.php');
        exit;
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="grid md:grid-cols-2 gap-4">
  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3">Profil Usaha / Billing</h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="update_company">
      <div>
        <label class="text-sm">Nama Aplikasi / Usaha</label>
        <input name="company_name" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(appSettingText('company_name', APP_NAME)) ?>">
      </div>
      <div>
        <label class="text-sm">Alamat</label>
        <textarea name="company_address" rows="3" class="mt-1 w-full border rounded px-3 py-2"><?= e(appSettingText('company_address')) ?></textarea>
      </div>
      <div>
        <label class="text-sm">No Kontak</label>
        <input name="company_phone" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(appSettingText('company_phone')) ?>">
      </div>
      <div>
        <label class="text-sm">Catatan Invoice</label>
        <textarea name="company_note" rows="3" class="mt-1 w-full border rounded px-3 py-2"><?= e(appSettingText('company_note')) ?></textarea>
      </div>
      <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan Profil</button>
    </form>
  </section>

  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3">Ganti Password Admin</h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="change_password">
      <div><label class="text-sm">Password Lama</label><input name="old_password" type="password" required class="mt-1 w-full border rounded px-3 py-2"></div>
      <div><label class="text-sm">Password Baru</label><input name="new_password" type="password" required class="mt-1 w-full border rounded px-3 py-2"></div>
      <div><label class="text-sm">Konfirmasi Password Baru</label><input name="confirm_password" type="password" required class="mt-1 w-full border rounded px-3 py-2"></div>
      <button class="bg-emerald-700 text-white rounded px-4 py-2">Ubah Password</button>
    </form>
  </section>
</div>

<section class="bg-white rounded-xl shadow p-4 mt-4">
  <h2 class="font-semibold mb-3">Konfigurasi API</h2>
  <form method="post" class="space-y-3">
    <input type="hidden" name="action" value="update_api">
    <div class="grid md:grid-cols-2 gap-3">
      <div><label class="text-sm">Provider API</label><input name="api_provider" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(appSettingText('api_provider', 'Custom API')) ?>"></div>
      <div><label class="text-sm">Base URL API</label><input name="api_base_url" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(appSettingText('api_base_url')) ?>" placeholder="https://api.example.com"></div>
      <div><label class="text-sm">API Token</label><input name="api_token" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(appSettingText('api_token')) ?>"></div>
      <div><label class="text-sm">API Username</label><input name="api_username" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(appSettingText('api_username')) ?>"></div>
      <div><label class="text-sm">API Password</label><input name="api_password" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(appSettingText('api_password')) ?>"></div>
      <div><label class="text-sm">API Secret / Key Tambahan</label><input name="api_secret" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(appSettingText('api_secret')) ?>"></div>
    </div>
    <div>
      <label class="text-sm">Catatan Integrasi API</label>
      <textarea name="api_notes" rows="4" class="mt-1 w-full border rounded px-3 py-2"><?= e(appSettingText('api_notes')) ?></textarea>
    </div>
    <div class="text-xs text-slate-500">Semua field ini bisa diubah tanpa phpMyAdmin. Nanti kalau Tuan Besar punya format endpoint final, tinggal kita sambungkan logic-nya ke sini.</div>
    <button class="bg-sky-700 text-white rounded px-4 py-2">Simpan Setting API</button>
  </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
