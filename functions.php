<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function sessionLoginUser(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => (string) $user['username'],
        'role' => (string) $user['role'],
        'full_name' => (string) $user['full_name'],
    ];
}

function login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => trim($username)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return false;
    }

    sessionLoginUser($user);
    return true;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function requireAuth(array $roles = []): void
{
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }

    if ($roles !== [] && !in_array((string) currentUser()['role'], $roles, true)) {
        flash('error', 'Akses ditolak untuk role ini.');
        header('Location: dashboard.php');
        exit;
    }
}

function appSettingText(string $key, string $fallback = ''): string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = :key LIMIT 1');
    $stmt->execute([':key' => $key]);
    $value = $stmt->fetchColumn();

    return $value === false ? $fallback : (string) $value;
}

function appSettingInt(string $key, int $fallback = 0): int
{
    return (int) appSettingText($key, (string) $fallback);
}

function updateSetting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings(key, value) VALUES(:key, :value)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([':key' => $key, ':value' => $value]);
}

function rupiah(int $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function monthName(int $month): string
{
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $months[$month] ?? 'Bulan';
}

function periodLabel(int $month, int $year): string
{
    return monthName($month) . ' ' . $year;
}

function normalizeCurrencyInput($value): int
{
    if (is_int($value)) {
        return max(0, $value);
    }

    $raw = trim((string) $value);
    if ($raw === '') {
        return 0;
    }

    $normalized = preg_replace('/[^0-9\-]/', '', $raw) ?? '0';
    return max(0, (int) $normalized);
}

function normalizeDateInput(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d', $timestamp);
}

function paymentDateToDateTime(?string $value, ?string $fallback = null): string
{
    $date = normalizeDateInput($value);
    if ($date !== null) {
        return $date . ' 00:00:00';
    }

    if ($fallback) {
        return $fallback;
    }

    return date('Y-m-d H:i:s');
}

function dateInputValue(?string $value): string
{
    $date = normalizeDateInput($value);
    return $date ?? '';
}

function formatDateId(?string $value, string $fallback = '-'): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d-m-Y', $timestamp);
}

function formatDateTimeId(?string $value, string $fallback = '-'): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d-m-Y H:i', $timestamp);
}

function customerStatusLabel(string $status): string
{
    if ($status === 'suspended') {
        return 'Suspended';
    }
    if ($status === 'inactive') {
        return 'Inactive';
    }
    return 'Active';
}

function invoiceNetAmount(array $invoice): int
{
    return max(0, (int) ($invoice['amount'] ?? 0) - normalizeCurrencyInput($invoice['discount_amount'] ?? 0));
}

function packageLabel(array $row): string
{
    $name = trim((string) ($row['package_name'] ?? $row['name'] ?? '-'));
    $speed = trim((string) ($row['speed'] ?? ''));
    return $speed !== '' ? $name . ' • ' . $speed : $name;
}

function companyName(): string
{
    return appSettingText('company_name', APP_NAME);
}

function invoiceNumberByParts(int $id, int $month, int $year): string
{
    return sprintf('MBL/%04d%02d/%05d', $year, $month, $id);
}

function ensureInvoiceNumber(PDO $pdo, int $invoiceId): string
{
    $stmt = $pdo->prepare('SELECT id, invoice_no, period_month, period_year FROM invoices WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        return '';
    }

    $invoiceNo = trim((string) ($invoice['invoice_no'] ?? ''));
    if ($invoiceNo !== '') {
        return $invoiceNo;
    }

    $invoiceNo = invoiceNumberByParts((int) $invoice['id'], (int) $invoice['period_month'], (int) $invoice['period_year']);
    $pdo->prepare('UPDATE invoices SET invoice_no = :invoice_no WHERE id = :id')->execute([
        ':invoice_no' => $invoiceNo,
        ':id' => $invoiceId,
    ]);

    return $invoiceNo;
}
