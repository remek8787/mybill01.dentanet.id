<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (login($username, $password)) {
        flash('success', 'Login berhasil.');
        header('Location: dashboard.php');
        exit;
    }

    flash('error', 'Username atau password salah.');
    header('Location: index.php');
    exit;
}

$flash = getFlash();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">
  <title>Login - <?= e(companyName()) ?></title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="icon" href="assets/app-icon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="assets/app-icon-192.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body data-theme="light" class="login-screen">
  <div class="login-shell">
    <section class="login-showcase">
      <div class="login-showcase-badge">ISP Billing Dashboard</div>
      <div class="login-showcase-logo-wrap">
        <img src="<?= e(brandingLogoPath()) ?>" alt="<?= e(companyName()) ?>" class="login-brand-logo">
      </div>
      <h1 class="login-showcase-title"><?= e(companyName()) ?></h1>
      <p class="login-showcase-text"><?= e(billingTagline()) ?></p>
      <div class="login-showcase-points">
        <div class="login-showcase-point"><i class="bi bi-hdd-network"></i><span>Konek MikroTik, secret, dan profile dari dashboard</span></div>
        <div class="login-showcase-point"><i class="bi bi-receipt-cutoff"></i><span>Invoice rapi, barcode, dan monitoring tagihan</span></div>
        <div class="login-showcase-point"><i class="bi bi-brush"></i><span>Nama billing dan logo bisa dicustom dari panel admin</span></div>
      </div>
    </section>

    <section class="login-panel">
      <div class="login-panel-head">
        <div>
          <div class="login-panel-kicker">Admin Login</div>
          <h2 class="login-panel-title">Masuk ke dashboard</h2>
          <p class="login-panel-text">Gunakan akun admin atau staff untuk mengelola pelanggan, tagihan, dan integrasi MikroTik.</p>
        </div>
      </div>

      <?php if ($flash): ?>
        <div class="mb-4 px-4 py-3 rounded text-sm <?= $flash['type'] === 'error' ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' ?>">
          <?= e((string) $flash['message']) ?>
        </div>
      <?php endif; ?>

      <form method="post" class="space-y-3">
        <div>
          <label class="text-sm font-medium">Username</label>
          <input name="username" required class="mt-1 w-full border rounded px-3 py-2" placeholder="contoh: admin">
        </div>
        <div>
          <label class="text-sm font-medium">Password</label>
          <input name="password" type="password" required class="mt-1 w-full border rounded px-3 py-2" placeholder="••••••••">
        </div>
        <button class="w-full btn btn-primary login-submit-btn">Masuk ke Dashboard</button>
      </form>

      <div class="login-default-note mt-4">
        Login awal: <b>admin / admin123</b>
      </div>
    </section>
  </div>
  <script src="assets/app.js"></script>
</body>
</html>
