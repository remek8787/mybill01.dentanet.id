<?php
$flash = getFlash();
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(companyName()) ?></title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="icon" href="assets/app-icon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="assets/app-icon-192.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body data-theme="light">
<div class="app-loader hide" id="appLoader">
  <div class="spinner-border text-primary" role="status"></div>
  <div class="mt-2">Loading dashboard...</div>
</div>

<div class="app-layout" id="appLayout">
  <aside class="app-sidebar" id="appSidebar">
    <div class="brand-sidebar-card mb-4">
      <div class="brand-sidebar-logo-wrap">
        <img src="<?= e(brandingLogoPath()) ?>" alt="<?= e(companyName()) ?>" class="brand-sidebar-logo">
      </div>
      <div>
        <h1 class="app-title"><?= e(companyName()) ?></h1>
        <div class="app-subtitle"><?= e(billingTagline()) ?></div>
      </div>
    </div>

    <?php if ($user): ?>
      <nav class="side-nav">
        <a class="side-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="fa-solid fa-gauge-high me-2"></i>Dashboard</a>
        <a class="side-link <?= $currentPage === 'customers.php' ? 'active' : '' ?>" href="customers.php"><i class="fa-solid fa-users-viewfinder me-2"></i>Pelanggan</a>
        <a class="side-link <?= $currentPage === 'packages.php' ? 'active' : '' ?>" href="packages.php"><i class="fa-solid fa-box-open me-2"></i>Paket</a>
        <a class="side-link <?= $currentPage === 'readings.php' ? 'active' : '' ?>" href="readings.php"><i class="fa-solid fa-file-circle-plus me-2"></i>Generate Billing</a>
        <a class="side-link <?= $currentPage === 'bills.php' ? 'active' : '' ?>" href="bills.php"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Invoice</a>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
          <a class="side-link <?= $currentPage === 'users.php' ? 'active' : '' ?>" href="users.php"><i class="fa-solid fa-user-shield me-2"></i>Users</a>
          <a class="side-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="settings.php"><i class="fa-solid fa-sliders me-2"></i>Pengaturan</a>
        <?php endif; ?>
        <a class="side-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="profile.php"><i class="fa-solid fa-id-badge me-2"></i>Profil</a>
      </nav>
    <?php endif; ?>
  </aside>

  <div class="app-content-wrap">
    <header class="app-topbar">
      <div class="d-flex align-items-center gap-2 topbar-brand-wrap">
        <img src="<?= e(brandingLogoPath()) ?>" alt="<?= e(companyName()) ?>" class="topbar-logo">
        <button class="btn btn-sm btn-outline-secondary" id="sidebarToggle" type="button" title="Hide/Show Sidebar"><i class="fa-solid fa-bars-staggered"></i></button>
        <div>
          <div class="small fw-semibold"><?= e(companyName()) ?></div>
          <div class="topbar-tagline"><?= e(billingTagline()) ?></div>
        </div>
      </div>

      <?php if ($user): ?>
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
          <button id="themeToggle" class="btn btn-sm btn-outline-secondary" type="button"><i class="fa-solid fa-moon me-1"></i>Dark</button>
          <span class="badge text-bg-success"><?= e((string) $user['full_name']) ?> (<?= e(displayRoleLabel($user)) ?>)</span>
          <a class="btn btn-sm btn-outline-primary" href="profile.php"><i class="fa-solid fa-user-pen me-1"></i>Profil</a>
          <a class="btn btn-sm btn-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
        </div>
      <?php endif; ?>
    </header>

    <main class="app-main">
      <?php if ($flash): ?>
        <div class="mb-4 px-4 py-3 rounded text-sm <?= $flash['type'] === 'error' ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' ?>">
          <?= e((string) $flash['message']) ?>
        </div>
      <?php endif; ?>
