<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'staff']);

$pdo = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_due_day') {
        $scope = trim((string) ($_POST['scope'] ?? 'all'));
        $dueDay = max(1, min(28, (int) ($_POST['due_day'] ?? 10)));
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $area = trim((string) ($_POST['area'] ?? ''));

        if ($scope === 'customer' && $customerId > 0) {
            $pdo->prepare('UPDATE customers SET due_day = :due_day WHERE id = :id')->execute([
                ':due_day' => $dueDay,
                ':id' => $customerId,
            ]);
            flash('success', 'Jatuh tempo pelanggan berhasil diperbarui.');
        } elseif ($scope === 'area' && $area !== '') {
            $stmt = $pdo->prepare('UPDATE customers SET due_day = :due_day WHERE area = :area');
            $stmt->execute([':due_day' => $dueDay, ':area' => $area]);
            flash('success', 'Jatuh tempo massal untuk wilayah ' . $area . ' berhasil diperbarui.');
        } else {
            $pdo->prepare('UPDATE customers SET due_day = :due_day WHERE status = "active"')->execute([':due_day' => $dueDay]);
            flash('success', 'Jatuh tempo massal pelanggan aktif berhasil diperbarui.');
        }

        header('Location: readings.php');
        exit;
    }

    if ($action === 'generate_billing') {
        $periodMonth = max(1, min(12, (int) ($_POST['period_month'] ?? date('n'))));
        $periodYear = max(2024, (int) ($_POST['period_year'] ?? date('Y')));
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $area = trim((string) ($_POST['area'] ?? ''));
        $dueMode = trim((string) ($_POST['due_mode'] ?? 'customer'));
        $manualDueDate = normalizeDateInput($_POST['due_date'] ?? '');

        $sql = 'SELECT c.*, p.name AS package_name, p.speed, p.price
            FROM customers c
            LEFT JOIN packages p ON p.id = c.package_id
            WHERE c.status = "active"';
        $params = [];
        if ($customerId > 0) {
            $sql .= ' AND c.id = :customer_id';
            $params[':customer_id'] = $customerId;
        }
        if ($area !== '') {
            $sql .= ' AND c.area = :area';
            $params[':area'] = $area;
        }
        $sql .= ' ORDER BY c.full_name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $targets = $stmt->fetchAll();

        if (!$targets) {
            flash('error', 'Tidak ada pelanggan aktif yang cocok untuk dibuatkan billing.');
            header('Location: readings.php');
            exit;
        }

        $insert = $pdo->prepare('INSERT OR IGNORE INTO invoices(
            customer_id, package_id, invoice_no, period_month, period_year, due_date, amount,
            discount_amount, status, customer_name_snapshot, package_name_snapshot, created_by
        ) VALUES(
            :customer_id, :package_id, :invoice_no, :period_month, :period_year, :due_date, :amount,
            0, "unpaid", :customer_name_snapshot, :package_name_snapshot, :created_by
        )');

        $created = 0;
        foreach ($targets as $target) {
            $amount = (int) ($target['price'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $existingStmt = $pdo->prepare('SELECT id FROM invoices WHERE customer_id = :customer_id AND period_month = :period_month AND period_year = :period_year LIMIT 1');
            $existingStmt->execute([
                ':customer_id' => (int) $target['id'],
                ':period_month' => $periodMonth,
                ':period_year' => $periodYear,
            ]);
            if ($existingStmt->fetch()) {
                continue;
            }

            $dueDate = $manualDueDate;
            if ($dueMode !== 'manual' || !$dueDate) {
                $dueDay = max(1, min(28, (int) ($target['due_day'] ?? 10)));
                $dueDate = sprintf('%04d-%02d-%02d', $periodYear, $periodMonth, $dueDay);
            }

            $insert->execute([
                ':customer_id' => (int) $target['id'],
                ':package_id' => !empty($target['package_id']) ? (int) $target['package_id'] : null,
                ':invoice_no' => 'TEMP-' . uniqid('', true),
                ':period_month' => $periodMonth,
                ':period_year' => $periodYear,
                ':due_date' => $dueDate,
                ':amount' => $amount,
                ':customer_name_snapshot' => (string) $target['full_name'],
                ':package_name_snapshot' => (string) ($target['package_name'] ?? '-'),
                ':created_by' => (int) $user['id'],
            ]);

            $invoiceId = (int) $pdo->lastInsertId();
            if ($invoiceId > 0) {
                ensureInvoiceNumber($pdo, $invoiceId);
                $created++;
            }
        }

        flash('success', $created > 0 ? ('Billing berhasil dibuat: ' . $created . ' invoice.') : 'Tidak ada invoice baru, kemungkinan periode tersebut sudah pernah dibuat.');
        header('Location: readings.php');
        exit;
    }
}

$areas = $pdo->query('SELECT * FROM areas WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
$customers = $pdo->query('SELECT c.id, c.full_name, c.area, c.due_day, p.name AS package_name, p.speed, p.price
    FROM customers c
    LEFT JOIN packages p ON p.id = c.package_id
    WHERE c.status = "active"
    ORDER BY c.full_name ASC')->fetchAll();
$latest = $pdo->query('SELECT i.*, c.full_name AS customer_name, c.area, c.due_day, p.name AS package_name, p.speed
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN packages p ON p.id = i.package_id
    ORDER BY i.id DESC LIMIT 20')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="page-ornament page-ornament--violet mb-4">
  <div class="page-ornament-kicker"><i class="fa-solid fa-file-circle-plus me-2"></i>Generate Billing</div>
  <h1 class="page-ornament-title">Atur Jatuh Tempo dan Buat Invoice</h1>
  <p class="page-ornament-text">Bisa atur due day manual atau massal, lalu langsung generate invoice berdasarkan pelanggan tertentu, wilayah, atau semua pelanggan aktif.</p>
</section>

<div class="grid xl:grid-cols-2 gap-4 mb-4">
  <section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--form">
    <h2 class="font-semibold mb-3">Atur Jatuh Tempo Manual / Massal</h2>
    <form method="post" class="space-y-3 luxe-form">
      <input type="hidden" name="action" value="update_due_day">
      <div class="grid md:grid-cols-3 gap-3">
        <div>
          <label class="text-sm">Mode</label>
          <select name="scope" id="due_scope" class="mt-1 w-full border rounded px-3 py-2">
            <option value="all">Massal semua aktif</option>
            <option value="area">Per wilayah</option>
            <option value="customer">Per pelanggan</option>
          </select>
        </div>
        <div>
          <label class="text-sm">Due Day</label>
          <input type="number" name="due_day" min="1" max="28" value="10" class="mt-1 w-full border rounded px-3 py-2">
        </div>
        <div>
          <label class="text-sm">Wilayah</label>
          <select name="area" id="due_area" class="mt-1 w-full border rounded px-3 py-2">
            <option value="">Pilih wilayah</option>
            <?php foreach ($areas as $area): ?>
              <option value="<?= e((string) $area['name']) ?>"><?= e((string) $area['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div>
        <label class="text-sm">Pelanggan</label>
        <select name="customer_id" id="due_customer_id" class="mt-1 w-full border rounded px-3 py-2">
          <option value="0">Pilih pelanggan</option>
          <?php foreach ($customers as $customer): ?>
            <option value="<?= (int) $customer['id'] ?>"><?= e((string) $customer['full_name'] . ' • ' . (string) ($customer['area'] ?: '-') . ' • Due ' . (int) ($customer['due_day'] ?? 10)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary"><i class="fa-solid fa-calendar-check me-1"></i>Simpan Jatuh Tempo</button>
    </form>
  </section>

  <section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--form">
    <h2 class="font-semibold mb-3">Generate Billing Langsung</h2>
    <form method="post" class="space-y-3 luxe-form">
      <input type="hidden" name="action" value="generate_billing">
      <div class="grid md:grid-cols-2 gap-3">
        <div>
          <label class="text-sm">Bulan</label>
          <select name="period_month" class="mt-1 w-full border rounded px-3 py-2">
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?= $m ?>" <?= $m === (int) date('n') ? 'selected' : '' ?>><?= e(monthName($m)) ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="text-sm">Tahun</label>
          <input type="number" name="period_year" class="mt-1 w-full border rounded px-3 py-2" value="<?= (int) date('Y') ?>">
        </div>
      </div>
      <div class="grid md:grid-cols-2 gap-3">
        <div>
          <label class="text-sm">Wilayah (opsional)</label>
          <select name="area" id="billing_area" class="mt-1 w-full border rounded px-3 py-2">
            <option value="">Semua wilayah</option>
            <?php foreach ($areas as $area): ?>
              <option value="<?= e((string) $area['name']) ?>"><?= e((string) $area['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="text-sm">Pelanggan Tertentu (opsional)</label>
          <select name="customer_id" id="billing_customer_id" class="mt-1 w-full border rounded px-3 py-2">
            <option value="0">Semua pelanggan aktif</option>
            <?php foreach ($customers as $customer): ?>
              <option value="<?= (int) $customer['id'] ?>" data-area="<?= e((string) ($customer['area'] ?: '')) ?>"><?= e((string) $customer['full_name'] . ' • ' . (string) ($customer['area'] ?: '-') . ' • ' . packageLabel($customer)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid md:grid-cols-2 gap-3">
        <div>
          <label class="text-sm">Mode Jatuh Tempo</label>
          <select name="due_mode" id="due_mode" class="mt-1 w-full border rounded px-3 py-2">
            <option value="customer">Pakai due day pelanggan</option>
            <option value="manual">Tanggal manual untuk semua</option>
          </select>
        </div>
        <div>
          <label class="text-sm">Tanggal Manual</label>
          <input type="date" name="due_date" id="manual_due_date" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(date('Y-m-d')) ?>">
        </div>
      </div>
      <button class="btn btn-primary"><i class="fa-solid fa-file-invoice me-1"></i>Generate Billing</button>
    </form>
  </section>
</div>

<section class="bg-white rounded-xl shadow p-4 luxe-card luxe-card--table">
  <h2 class="font-semibold mb-3">Invoice Terbaru Hasil Generate</h2>
  <div class="overflow-auto table-wrap">
    <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Invoice</th>
          <th class="py-2 pr-3">Customer</th>
          <th class="py-2 pr-3">Paket</th>
          <th class="py-2 pr-3">Periode</th>
          <th class="py-2 pr-3">Jatuh Tempo</th>
          <th class="py-2 pr-3">Nominal</th>
          <th class="py-2 pr-3">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($latest as $row): ?>
          <tr class="border-b">
            <td class="py-2 pr-3"><?= e((string) ensureInvoiceNumber($pdo, (int) $row['id'])) ?></td>
            <td class="py-2 pr-3">
              <?= e((string) ($row['customer_name'] ?? '-')) ?><br>
              <span class="text-xs text-slate-500"><?= e((string) ($row['area'] ?? '-')) ?></span>
            </td>
            <td class="py-2 pr-3"><?= e(packageLabel($row)) ?></td>
            <td class="py-2 pr-3"><?= e(periodLabel((int) $row['period_month'], (int) $row['period_year'])) ?></td>
            <td class="py-2 pr-3"><?= e(formatDateId((string) ($row['due_date'] ?? ''), '-')) ?></td>
            <td class="py-2 pr-3"><?= e(rupiah(invoiceNetAmount($row))) ?></td>
            <td class="py-2 pr-3"><span class="badge <?= ($row['status'] ?? '') === 'paid' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= ($row['status'] ?? '') === 'paid' ? 'Lunas' : 'Belum Lunas' ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$latest): ?>
          <tr><td colspan="7" class="py-4 text-slate-500">Belum ada invoice yang di-generate.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<script>
(() => {
  const dueScope = document.getElementById('due_scope');
  const dueArea = document.getElementById('due_area');
  const dueCustomer = document.getElementById('due_customer_id');
  const dueMode = document.getElementById('due_mode');
  const manualDueDate = document.getElementById('manual_due_date');

  const toggleDueInputs = () => {
    const scope = dueScope ? dueScope.value : 'all';
    if (dueArea) dueArea.disabled = scope !== 'area';
    if (dueCustomer) dueCustomer.disabled = scope !== 'customer';
  };

  const toggleGenerateDueMode = () => {
    if (manualDueDate) {
      manualDueDate.disabled = !dueMode || dueMode.value !== 'manual';
    }
  };

  if (dueScope) {
    dueScope.addEventListener('change', toggleDueInputs);
    toggleDueInputs();
  }
  if (dueMode) {
    dueMode.addEventListener('change', toggleGenerateDueMode);
    toggleGenerateDueMode();
  }

  ['billing_customer_id', 'due_customer_id', 'billing_area'].forEach((id) => {
    const el = document.getElementById(id);
    if (el && window.TomSelect) {
      new TomSelect(el, {
        create: false,
        maxItems: 1,
        searchField: ['text'],
      });
    }
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
