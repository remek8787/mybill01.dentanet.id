<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin']);

$pdo = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_company') {
        try {
            updateSetting('company_name', trim((string) ($_POST['company_name'] ?? APP_NAME)));
            updateSetting('billing_tagline', trim((string) ($_POST['billing_tagline'] ?? billingTagline())));
            updateSetting('company_address', trim((string) ($_POST['company_address'] ?? '')));
            updateSetting('company_phone', trim((string) ($_POST['company_phone'] ?? '')));
            updateSetting('company_note', trim((string) ($_POST['company_note'] ?? '')));

            if (isset($_POST['remove_company_logo'])) {
                updateSetting('company_logo', '');
            }

            if (isset($_FILES['company_logo'])) {
                $logoPath = saveBrandLogoUpload($_FILES['company_logo']);
                if ($logoPath !== null) {
                    updateSetting('company_logo', $logoPath);
                }
            }

            flash('success', 'Profil usaha berhasil diperbarui.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        header('Location: settings.php');
        exit;
    }

    if ($action === 'update_api') {
        updateSetting('api_provider', trim((string) ($_POST['api_provider'] ?? 'MikroTik RouterOS / Custom API')));
        updateSetting('api_base_url', trim((string) ($_POST['api_base_url'] ?? '')));
        updateSetting('api_token', trim((string) ($_POST['api_token'] ?? '')));
        updateSetting('api_username', trim((string) ($_POST['api_username'] ?? '')));
        updateSetting('api_password', trim((string) ($_POST['api_password'] ?? '')));
        updateSetting('api_secret', trim((string) ($_POST['api_secret'] ?? '')));
        updateSetting('api_notes', trim((string) ($_POST['api_notes'] ?? '')));
        updateSetting('mikrotik_host', trim((string) ($_POST['mikrotik_host'] ?? '')));
        updateSetting('mikrotik_port', (string) max(1, (int) ($_POST['mikrotik_port'] ?? 8728)));
        updateSetting('mikrotik_use_ssl', isset($_POST['mikrotik_use_ssl']) ? '1' : '0');
        updateSetting('mikrotik_timeout', (string) max(3, (int) ($_POST['mikrotik_timeout'] ?? 10)));
        updateSetting('mikrotik_username', trim((string) ($_POST['mikrotik_username'] ?? '')));
        updateSetting('mikrotik_password', trim((string) ($_POST['mikrotik_password'] ?? '')));
        updateSetting('mikrotik_router_name', trim((string) ($_POST['mikrotik_router_name'] ?? '')));
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
    <form method="post" enctype="multipart/form-data" class="space-y-3">
      <input type="hidden" name="action" value="update_company">
      <div>
        <label class="text-sm">Nama Billing / Aplikasi</label>
        <input name="company_name" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(appSettingText('company_name', APP_NAME)) ?>">
      </div>
      <div>
        <label class="text-sm">Tagline / Subjudul</label>
        <input name="billing_tagline" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(billingTagline()) ?>" placeholder="contoh: Billing pelanggan internet yang rapi dan modern">
      </div>
      <div class="brand-logo-settings-card">
        <div class="brand-logo-settings-preview">
          <img src="<?= e(brandingLogoPath()) ?>" alt="<?= e(companyName()) ?>">
        </div>
        <div class="brand-logo-settings-form">
          <label class="text-sm">Logo Billing</label>
          <input type="file" name="company_logo" accept=".png,.jpg,.jpeg,.webp,.gif,.svg,image/*" class="mt-1 w-full border rounded px-3 py-2">
          <div class="text-xs text-slate-500 mt-1">Bisa upload logo baru. Format: PNG, JPG, WEBP, GIF, atau SVG. Maksimal 2 MB.</div>
          <?php if (trim(appSettingText('company_logo')) !== ''): ?>
            <label class="inline-flex items-center gap-2 text-sm mt-2"><input type="checkbox" name="remove_company_logo"> Hapus logo custom dan kembali ke logo default</label>
          <?php endif; ?>
        </div>
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
    <h2 class="font-semibold mb-3">Catatan Sistem</h2>
    <div class="rounded-4 border border-sky-200 bg-sky-50 p-3 small text-sky-800">Fitur API disembunyikan sementara supaya aplikasi tetap ringan. Nanti bisa diaktifkan lagi saat alur paling efisiennya sudah diputuskan.</div>

    <hr class="my-4">

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
  <h2 class="font-semibold mb-3">Pengaturan Tambahan</h2>
  <div class="rounded-4 border border-slate-200 bg-slate-50 p-3 small text-slate-700">Bagian API disembunyikan sementara. Saat dibutuhkan lagi, pengaturan koneksi bisa dimunculkan kembali tanpa mengganggu data billing yang sudah ada.</div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
