<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'staff']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'test_connection') {
            $info = mikrotikTestConnection();
            flash('success', 'Koneksi MikroTik berhasil ke router ' . ($info['identity'] ?? 'RouterOS') . '.');
        }

        if ($action === 'refresh_cache') {
            $snapshot = mikrotikFetchSnapshot(true, 0);
            flash('success', 'Cache MikroTik berhasil diperbarui. Profile: ' . count($snapshot['profiles'] ?? []) . ', secret: ' . count($snapshot['secrets'] ?? []) . '.');
        }

        if ($action === 'sync_customers') {
            $result = syncCustomersFromMikrotik($pdo);
            flash('success', 'Sinkron selesai. Customer diperbarui: ' . $result['updated'] . ', isolir: ' . $result['isolated'] . ', tidak ketemu: ' . $result['not_found'] . '.');
        }

        if ($action === 'enable_secret') {
            $secretId = trim((string) ($_POST['secret_id'] ?? ''));
            if ($secretId !== '') {
                $kicked = mikrotikEnableSecretAndKick($secretId);
                flash('success', 'Secret berhasil di-enable. Active session yang dikick: ' . $kicked . '.');
            }
        }

        if ($action === 'disable_secret') {
            $secretId = trim((string) ($_POST['secret_id'] ?? ''));
            if ($secretId !== '') {
                $kicked = mikrotikDisableSecretAndKick($secretId);
                flash('success', 'Secret berhasil di-disable / isolir. Active session yang dikick: ' . $kicked . '.');
            }
        }

        if ($action === 'change_profile') {
            $secretId = trim((string) ($_POST['secret_id'] ?? ''));
            $profileName = trim((string) ($_POST['profile_name'] ?? ''));
            if ($secretId !== '' && $profileName !== '') {
                $kicked = mikrotikSetSecretProfileAndKick($secretId, $profileName);
                flash('success', 'Profile secret berhasil dipindah ke ' . $profileName . '. Active session yang dikick: ' . $kicked . '.');
            }
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    header('Location: mikrotik.php');
    exit;
}

$mikrotikInfo = null;
$mikrotikProfiles = [];
$mikrotikSecrets = [];
$mikrotikError = '';
$disabledCount = 0;
$cacheMeta = null;

if (mikrotikIsConfigured()) {
    try {
        $snapshot = mikrotikReadCachedSnapshot(3600);
        if (is_array($snapshot)) {
            $mikrotikInfo = $snapshot['info'] ?? null;
            $mikrotikProfiles = $snapshot['profiles'] ?? [];
            $mikrotikSecrets = $snapshot['secrets'] ?? [];
            $disabledCount = (int) ($snapshot['disabled_count'] ?? 0);
            $cacheMeta = $snapshot;
        }
    } catch (Throwable $e) {
        $mikrotikError = $e->getMessage();
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="bg-white rounded-xl shadow p-4 mb-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h2 class="font-semibold mb-1">Kontrol MikroTik API</h2>
      <div class="small text-secondary">Untuk baca PPP secret, ganti profile, dan enable/disable secret langsung dari web billing.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <form method="post" class="d-inline">
        <input type="hidden" name="action" value="test_connection">
        <button class="btn btn-outline-primary">Tes Koneksi</button>
      </form>
      <form method="post" class="d-inline">
        <input type="hidden" name="action" value="refresh_cache">
        <button class="btn btn-outline-secondary">Refresh Cache</button>
      </form>
      <form method="post" class="d-inline">
        <input type="hidden" name="action" value="sync_customers">
        <button class="btn btn-primary">Sinkron Pelanggan</button>
      </form>
    </div>
  </div>

  <?php if (!mikrotikIsConfigured()): ?>
    <div class="rounded-4 border border-amber-200 bg-amber-50 p-3 text-amber-800 small">
      Konfigurasi MikroTik belum lengkap. Isi dulu host, port API, username, dan password di menu <a href="settings.php">Pengaturan</a>.
    </div>
  <?php elseif ($mikrotikError !== ''): ?>
    <div class="rounded-4 border border-red-200 bg-red-50 p-3 text-red-800 small">
      Gagal menghubungi MikroTik: <?= e($mikrotikError) ?>
    </div>
  <?php elseif (!$cacheMeta): ?>
    <div class="rounded-4 border border-sky-200 bg-sky-50 p-3 text-sky-800 small">
      Belum ada cache MikroTik. Tekan <b>Refresh Cache</b> untuk ambil data secret dan profile satu kali, lalu halaman lain akan memakai cache itu supaya lebih ringan.
    </div>
  <?php else: ?>
    <div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4">
      <div class="stat-card p-4">
        <p class="text-sm text-slate-500">Router</p>
        <p class="text-xl font-bold"><?= e((string) ($mikrotikInfo['identity'] ?? '-')) ?></p>
      </div>
      <div class="stat-card p-4">
        <p class="text-sm text-slate-500">Version</p>
        <p class="text-xl font-bold text-sky-600"><?= e((string) ($mikrotikInfo['version'] ?? '-')) ?></p>
      </div>
      <div class="stat-card p-4">
        <p class="text-sm text-slate-500">PPP Secret</p>
        <p class="text-xl font-bold"><?= count($mikrotikSecrets) ?></p>
      </div>
      <div class="stat-card p-4">
        <p class="text-sm text-slate-500">Secret Disabled</p>
        <p class="text-xl font-bold text-red-600"><?= $disabledCount ?></p>
      </div>
    </div>
    <div class="small text-secondary mt-3">Cache terakhir: <?= e((string) ($cacheMeta['fetched_at'] ?? '-')) ?>. Halaman ini sekarang tidak auto-nembak router setiap dibuka, supaya lebih ringan.</div>
  <?php endif; ?>
</section>

<section class="bg-white rounded-xl shadow p-4 mb-4">
  <h3 class="font-semibold mb-3">Tutorial Singkat Admin</h3>
  <div class="grid md:grid-cols-2 gap-4 small text-secondary">
    <div class="rounded-4 border p-3 bg-light">
      <div class="fw-semibold text-dark mb-2">Cara pakai aman</div>
      <ol class="mb-0 ps-3">
        <li>Isi koneksi MikroTik di Pengaturan.</li>
        <li>Tes koneksi dari halaman ini.</li>
        <li>Sinkron pelanggan supaya status secret masuk ke app.</li>
        <li>Jika pelanggan menunggak, disable secret untuk isolir.</li>
        <li>Jika sudah bayar, enable lagi atau pindah profile sesuai paket baru.</li>
      </ol>
    </div>
    <div class="rounded-4 border p-3 bg-light">
      <div class="fw-semibold text-dark mb-2">Catatan</div>
      <ul class="mb-0 ps-3">
        <li>Status isolir di app mengikuti status <b>disabled</b> pada PPP secret.</li>
        <li>Port API bisa diubah dari web, jadi tidak terkunci ke 8728.</li>
        <li>Langkah ini masih fondasi awal, nanti bisa kita improve ke auto isolir by invoice.</li>
      </ul>
    </div>
  </div>
</section>

<?php if (mikrotikIsConfigured() && $mikrotikError === '' && $cacheMeta): ?>
  <section class="bg-white rounded-xl shadow p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h3 class="font-semibold mb-0">Daftar PPP Secret</h3>
      <div class="small text-secondary">Kelola enable / disable dan profile secret langsung dari sini.</div>
    </div>
    <div class="overflow-auto table-wrap">
      <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
        <thead>
          <tr class="text-left border-b">
            <th class="py-2 pr-3">Username</th>
            <th class="py-2 pr-3">Profile</th>
            <th class="py-2 pr-3">Service</th>
            <th class="py-2 pr-3">Status</th>
            <th class="py-2 pr-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mikrotikSecrets as $secret): ?>
            <?php $isDisabled = ((string) ($secret['disabled'] ?? 'false')) === 'true'; ?>
            <tr class="border-b align-top">
              <td class="py-2 pr-3">
                <div class="font-semibold"><?= e((string) ($secret['name'] ?? '-')) ?></div>
                <div class="text-xs text-slate-500">ID: <?= e((string) ($secret['id'] ?? '-')) ?></div>
              </td>
              <td class="py-2 pr-3">
                <div><?= e((string) ($secret['profile'] ?? '-')) ?></div>
                <div class="text-xs text-slate-500">Caller ID: <?= e((string) ($secret['caller-id'] ?? '-')) ?></div>
              </td>
              <td class="py-2 pr-3">
                <div><?= e((string) ($secret['service'] ?? '-')) ?></div>
                <div class="text-xs text-slate-500">Remote: <?= e((string) ($secret['remote-address'] ?? '-')) ?></div>
              </td>
              <td class="py-2 pr-3"><span class="badge <?= $isDisabled ? 'text-bg-danger' : 'text-bg-success' ?>"><?= $isDisabled ? 'Disabled / Isolir' : 'Aktif' ?></span></td>
              <td class="py-2 pr-3">
                <?php if ($isDisabled): ?>
                  <form method="post" class="inline-block mb-2">
                    <input type="hidden" name="action" value="enable_secret">
                    <input type="hidden" name="secret_id" value="<?= e((string) ($secret['id'] ?? '')) ?>">
                    <button class="px-2 py-1 rounded bg-emerald-100 text-emerald-800">Enable</button>
                  </form>
                <?php else: ?>
                  <form method="post" class="inline-block mb-2" onsubmit="return confirm('Disable secret ini untuk isolir pelanggan?')">
                    <input type="hidden" name="action" value="disable_secret">
                    <input type="hidden" name="secret_id" value="<?= e((string) ($secret['id'] ?? '')) ?>">
                    <button class="px-2 py-1 rounded bg-red-100 text-red-700">Disable</button>
                  </form>
                <?php endif; ?>
                <form method="post" class="mt-1">
                  <input type="hidden" name="action" value="change_profile">
                  <input type="hidden" name="secret_id" value="<?= e((string) ($secret['id'] ?? '')) ?>">
                  <div class="d-flex gap-2">
                    <select name="profile_name" class="border rounded px-2 py-1">
                      <option value="">Pilih profile</option>
                      <?php foreach ($mikrotikProfiles as $profile): ?>
                        <?php $profileName = (string) ($profile['name'] ?? ''); ?>
                        <option value="<?= e($profileName) ?>" <?= ((string) ($secret['profile'] ?? '') === $profileName) ? 'selected' : '' ?>><?= e($profileName) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="px-2 py-1 rounded bg-sky-100 text-sky-800">Pindah</button>
                  </div>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$mikrotikSecrets): ?>
            <tr><td colspan="5" class="py-4 text-slate-500">Belum ada PPP secret yang terbaca dari MikroTik.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
