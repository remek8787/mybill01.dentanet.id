<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    initializeDatabase($pdo);

    return $pdo;
}

function initializeDatabase(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ("admin", "staff")),
            full_name TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS packages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            speed TEXT,
            price INTEGER NOT NULL DEFAULT 0,
            description TEXT,
            api_plan_id TEXT,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_no TEXT,
            full_name TEXT NOT NULL,
            address TEXT,
            phone TEXT,
            area TEXT,
            package_id INTEGER,
            api_customer_id TEXT,
            router_name TEXT,
            status TEXT NOT NULL DEFAULT "active" CHECK(status IN ("active", "suspended", "inactive")),
            due_day INTEGER NOT NULL DEFAULT 10,
            join_date TEXT,
            notes TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(package_id) REFERENCES packages(id) ON DELETE SET NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS invoices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            package_id INTEGER,
            invoice_no TEXT NOT NULL UNIQUE,
            period_month INTEGER NOT NULL,
            period_year INTEGER NOT NULL,
            due_date TEXT,
            amount INTEGER NOT NULL DEFAULT 0,
            discount_amount INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT "unpaid" CHECK(status IN ("unpaid", "paid")),
            paid_at TEXT,
            payment_method TEXT,
            payment_note TEXT,
            customer_name_snapshot TEXT,
            package_name_snapshot TEXT,
            created_by INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(customer_id, period_month, period_year),
            FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY(package_id) REFERENCES packages(id) ON DELETE SET NULL,
            FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
        )'
    );

    seedDefaults($pdo);
}

function seedDefaults(PDO $pdo): void
{
    $insertUser = $pdo->prepare('INSERT OR IGNORE INTO users(username, password_hash, role, full_name)
        VALUES(:username, :password_hash, :role, :full_name)');

    $insertUser->execute([
        ':username' => 'admin',
        ':password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
        ':role' => 'admin',
        ':full_name' => 'Administrator',
    ]);

    $insertSetting = $pdo->prepare('INSERT OR IGNORE INTO settings(key, value) VALUES(:key, :value)');
    $insertSetting->execute([':key' => 'company_name', ':value' => APP_NAME]);
    $insertSetting->execute([':key' => 'company_address', ':value' => 'Isi alamat kantor / basecamp RT/RW Net']);
    $insertSetting->execute([':key' => 'company_phone', ':value' => '0812xxxxxxxx']);
    $insertSetting->execute([':key' => 'company_note', ':value' => 'Billing RT/RW Net siap konek API dan bisa diubah dari menu pengaturan.']);
    $insertSetting->execute([':key' => 'api_provider', ':value' => 'Custom API']);
    $insertSetting->execute([':key' => 'api_base_url', ':value' => '']);
    $insertSetting->execute([':key' => 'api_token', ':value' => '']);
    $insertSetting->execute([':key' => 'api_username', ':value' => '']);
    $insertSetting->execute([':key' => 'api_password', ':value' => '']);
    $insertSetting->execute([':key' => 'api_secret', ':value' => '']);
    $insertSetting->execute([':key' => 'api_notes', ':value' => 'Simpan endpoint, token, atau catatan integrasi API di sini.']);

    $pkgCount = (int) $pdo->query('SELECT COUNT(*) FROM packages')->fetchColumn();
    if ($pkgCount === 0) {
        $pdo->exec("INSERT INTO packages(name, speed, price, description, is_active) VALUES
            ('Paket Basic', '10 Mbps', 100000, 'Contoh paket awal untuk pelanggan RT/RW Net.', 1),
            ('Paket Pro', '20 Mbps', 150000, 'Paket lebih cepat untuk pelanggan aktif.', 1)");
    }
}
