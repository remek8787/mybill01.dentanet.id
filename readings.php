<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'staff']);

$pdo = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_billing') {
        $periodMonth = max(1, min(12, (int) ($_POST['period_month'] ?? date('n'))));
        $periodYear = max(2024, (int) ($_POST['period_year'] ?? date('Y')));
        $dueDate = normalizeDateInput($_POST['due_date'] ?? '') ?: date('Y-m-d');
        $customerId = (int) ($_POST['customer_id'] ?? 0);

        $sql = 'SELECT c.*, p.name AS package_name, p.speed, p.price
            FROM customers c
            LEFT JOIN packages p ON p.id = c.package_id
            WHERE c.status = "active"';
        $params = [];
        if ($customerId > 0) {
            $sql .= ' AND c.id = :customer_id';
            $params[':customer_id'] = $customerId;
        }
        $sql .= ' ORDER BY c.full_name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $targets = $stmt->fetchAll();

        if (!$targets) {
            flash('error', 'Tidak ada pelanggan aktif yang bisa dibuatkan billing.');
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

$packages = $pdo->query('SELECT * FROM packages WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
$customers = $pdo->query('SELECT c.id, c.full_name, c.area, p.name AS package_name, p.speed, p.price
    FROM customers c
    LEFT JOIN packages p ON p.id = c.package_id
    WHERE c.status = "active"
    ORDER BY c.full_name ASC')->fetchAll();
$latest = $pdo->query('SELECT i.*, c.full_name AS customer_name, c.area, p.name AS package_name, p.speed
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN packages p ON p.id = i.package_id
    ORDER BY i.id DESC LIMIT 20')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="grid lg:grid-cols-3 gap-4">
  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3">Generate Billing Bulanan</h2>
    <form method="post" class="space-y-3">
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
      <div>
        <label class="text-sm">Jatuh Tempo</label>
        <input type="date" name="due_date" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(date('Y-m-d')) ?>">
      </div>
      <div>
        <label class="text-sm">Pelanggan Tertentu (opsional)</label>
        <select name="customer_id" id="billing_customer_id" class="mt-1 w-full border rounded px-3 py-2">
          <option value="0">Semua pelanggan aktif</option>
          <?php foreach ($customers as $customer): ?>
            <option value="<?= (int) $customer['id'] ?>"><?= e((string) $customer['full_name'] . ' • ' . (string) ($customer['area'] ?: '-') . ' • ' . packageLabel($customer)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="bg-slate-900 text-white rounded px-4 py-2">Generate Billing</button>
    </form>
  </section>

  <section class="bg-white rounded-xl shadow p-4 lg:col-span-2">
    <h2 class="font-semibold mb-3">Invoice Terbaru Hasil Generate</h2>
    <div class="overflow-auto table-wrap">
      <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
        <thead>
          <tr class="text-left border-b">
            <th class="py-2 pr-3">Invoice</th>
            <th class="py-2 pr-3">Customer</th>
            <th class="py-2 pr-3">Paket</th>
            <th class="py-2 pr-3">Periode</th>
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
              <td class="py-2 pr-3"><?= e(rupiah(invoiceNetAmount($row))) ?></td>
              <td class="py-2 pr-3"><span class="badge <?= ($row['status'] ?? '') === 'paid' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= ($row['status'] ?? '') === 'paid' ? 'Lunas' : 'Belum Lunas' ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$latest): ?>
            <tr><td colspan="6" class="py-4 text-slate-500">Belum ada invoice yang di-generate.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script>
(() => {
  const select = document.getElementById('billing_customer_id');
  if (select && window.TomSelect) {
    new TomSelect(select, {
      create: false,
      maxItems: 1,
      searchField: ['text'],
      placeholder: 'Ketik nama pelanggan / area / paket...'
    });
  }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
