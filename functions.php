<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

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
        'is_superuser' => (int) ($user['is_superuser'] ?? 0),
        'is_hidden' => (int) ($user['is_hidden'] ?? 0),
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

    if (($roles !== []) && !isSuperUser() && !in_array((string) currentUser()['role'], $roles, true)) {
        flash('error', 'Akses ditolak untuk role ini.');
        header('Location: dashboard.php');
        exit;
    }
}

function isSuperUser(): bool
{
    return (int) (currentUser()['is_superuser'] ?? 0) === 1;
}

function displayRoleLabel(?array $user = null): string
{
    $user = $user ?? currentUser();
    if (!$user) {
        return '-';
    }
    if ((int) ($user['is_superuser'] ?? 0) === 1) {
        return 'Superuser';
    }
    return ucfirst((string) ($user['role'] ?? 'user'));
}

function displayUsernameLabel(?array $user = null): string
{
    $user = $user ?? currentUser();
    if (!$user) {
        return '-';
    }
    if ((int) ($user['is_hidden'] ?? 0) === 1) {
        return 'Akun sistem tersembunyi';
    }
    return (string) ($user['username'] ?? '-');
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

function appSettingBool(string $key, bool $fallback = false): bool
{
    return in_array(strtolower(appSettingText($key, $fallback ? '1' : '0')), ['1', 'true', 'yes', 'on'], true);
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

function serviceTypeLabel(string $type): string
{
    if ($type === 'hotspot') {
        return 'Hotspot';
    }
    if ($type === 'static') {
        return 'Static IP';
    }
    if ($type === 'other') {
        return 'Lainnya';
    }

    return 'PPPoE';
}

function mikrotikSyncLabel(?string $status): string
{
    $status = trim((string) $status);
    if ($status === 'disabled') {
        return 'Secret disabled / isolir';
    }
    if ($status === 'enabled') {
        return 'Secret aktif';
    }
    if ($status === 'not_found') {
        return 'Tidak ditemukan di MikroTik';
    }

    return 'Belum sinkron';
}

function customerIsolationBadgeClass(array $customer): string
{
    return customerIsIsolated($customer) ? 'text-bg-danger' : 'text-bg-success';
}

function customerIsIsolated(array $customer): bool
{
    return (int) ($customer['isolated'] ?? 0) === 1 || (string) ($customer['status'] ?? '') === 'suspended';
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

function billingTagline(): string
{
    return appSettingText('billing_tagline', 'Billing internet yang rapi, cepat, dan siap konek ke MikroTik.');
}

function brandingLogoPath(): string
{
    $logo = trim(appSettingText('company_logo'));
    return $logo !== '' ? $logo : 'assets/app-logo.svg';
}

function ensureDirectory(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function saveBrandLogoUpload(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload logo gagal. Coba ulangi dengan file lain.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('File logo tidak valid.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        throw new RuntimeException('Logo maksimal 2 MB.');
    }

    $mime = '';
    $info = @getimagesize($tmp);
    if ($info && !empty($info['mime'])) {
        $mime = (string) $info['mime'];
    }
    if ($mime === '' && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, $tmp);
            finfo_close($finfo);
        }
    }
    if ($mime === '') {
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension === 'svg') {
            $mime = 'image/svg+xml';
        }
    }

    $extMap = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
        'text/plain' => 'svg',
    ];
    if (!isset($extMap[$mime])) {
        throw new RuntimeException('Format logo belum didukung. Gunakan PNG, JPG, WEBP, GIF, atau SVG.');
    }

    $dir = __DIR__ . '/uploads/branding';
    ensureDirectory($dir);
    $filename = 'logo-' . date('YmdHis') . '.' . $extMap[$mime];
    $target = $dir . '/' . $filename;

    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Gagal menyimpan logo ke server.');
    }

    return 'uploads/branding/' . $filename;
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

function code39Normalized(string $text): string
{
    $text = strtoupper(trim($text));
    if ($text === '') {
        $text = 'EMPTY';
    }

    $allowed = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%';
    $clean = '';
    foreach (str_split($text) as $char) {
        $clean .= str_contains($allowed, $char) ? $char : '-';
    }

    return '*' . $clean . '*';
}

function code39Patterns(): array
{
    return [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn', '4' => 'nnnwwnnnw',
        '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw', '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw', 'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn',
        'F' => 'nnwnwwnnn', 'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww', 'O' => 'wnnnwnnwn',
        'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw', 'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn',
        'Z' => 'nwwnwnnnn', '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn', '*' => 'nwnnwnwnn',
    ];
}

function barcodeSvg(string $text, int $barHeight = 72): string
{
    $patterns = code39Patterns();
    $encoded = code39Normalized($text);
    $narrow = 2;
    $wide = 5;
    $gap = 2;
    $x = 12;
    $svg = [];

    foreach (str_split($encoded) as $char) {
        $pattern = $patterns[$char] ?? $patterns['-'];
        for ($i = 0; $i < strlen($pattern); $i++) {
            $width = $pattern[$i] === 'w' ? $wide : $narrow;
            $isBar = $i % 2 === 0;
            if ($isBar) {
                $svg[] = '<rect x="' . $x . '" y="8" width="' . $width . '" height="' . $barHeight . '" fill="#111827" />';
            }
            $x += $width;
        }
        $x += $gap;
    }

    $width = $x + 12;
    $label = e($text);

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . ($barHeight + 38) . '" role="img" aria-label="Barcode ' . $label . '">' . implode('', $svg) . '<text x="50%" y="' . ($barHeight + 28) . '" text-anchor="middle" font-size="14" font-family="Arial, Helvetica, sans-serif" fill="#111827">' . $label . '</text></svg>';
}

final class RouterOsApiClient
{
    private $socket = null;
    private string $host;
    private int $port;
    private bool $useSsl;
    private int $timeout;

    public function __construct(string $host, int $port = 8728, bool $useSsl = false, int $timeout = 10)
    {
        $this->host = $host;
        $this->port = $port;
        $this->useSsl = $useSsl;
        $this->timeout = max(3, $timeout);
    }

    public function connect(string $username, string $password): void
    {
        $transport = $this->useSsl ? 'ssl://' : '';
        $address = $transport . $this->host . ':' . $this->port;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($address, $errno, $errstr, $this->timeout);
        if (!$socket) {
            throw new RuntimeException('Gagal konek ke MikroTik: ' . ($errstr !== '' ? $errstr : 'socket error'));
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;

        $response = $this->talk([
            '/login',
            '=name=' . $username,
            '=password=' . $password,
        ]);

        if ($response['done']) {
            return;
        }

        $challenge = $response['doneAttributes']['ret'] ?? $response['trap'][0]['ret'] ?? null;
        if ($challenge) {
            $hash = md5(chr(0) . $password . hex2bin((string) $challenge));
            $legacy = $this->talk([
                '/login',
                '=name=' . $username,
                '=response=00' . $hash,
            ]);
            if ($legacy['done']) {
                return;
            }
            $message = $legacy['trap'][0]['message'] ?? 'Login legacy gagal';
            throw new RuntimeException((string) $message);
        }

        $message = $response['trap'][0]['message'] ?? 'Login MikroTik gagal';
        throw new RuntimeException((string) $message);
    }

    public function disconnect(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function command(string $command, array $attributes = [], array $proplist = []): array
    {
        $sentence = [$command];
        foreach ($attributes as $key => $value) {
            $sentence[] = '=' . $key . '=' . $value;
        }
        if ($proplist !== []) {
            $sentence[] = '=.proplist=' . implode(',', $proplist);
        }

        $response = $this->talk($sentence);
        if ($response['trap'] !== []) {
            $message = $response['trap'][0]['message'] ?? 'MikroTik API error';
            throw new RuntimeException((string) $message);
        }

        return $response['re'];
    }

    private function talk(array $sentence): array
    {
        foreach ($sentence as $word) {
            $this->writeWord((string) $word);
        }
        $this->writeWord('');

        $replies = [];
        $traps = [];
        $doneAttributes = [];
        $done = false;

        while (true) {
            $reply = $this->readSentence();
            if ($reply === []) {
                break;
            }

            $type = array_shift($reply);
            $attributes = $this->normalizeReplyAttributes($reply);

            if ($type === '!re') {
                $replies[] = $attributes;
                continue;
            }

            if ($type === '!trap') {
                $traps[] = $attributes;
                continue;
            }

            if ($type === '!done') {
                $done = true;
                $doneAttributes = $attributes;
                break;
            }
        }

        return [
            're' => $replies,
            'trap' => $traps,
            'done' => $done,
            'doneAttributes' => $doneAttributes,
        ];
    }

    private function normalizeReplyAttributes(array $reply): array
    {
        $data = [];
        foreach ($reply as $word) {
            if (str_starts_with($word, '=')) {
                $parts = explode('=', substr($word, 1), 2);
                $data[$parts[0]] = $parts[1] ?? '';
                continue;
            }
            if (str_starts_with($word, '.')) {
                $parts = explode('=', $word, 2);
                $data[ltrim($parts[0], '.')] = $parts[1] ?? '';
            }
        }

        return $data;
    }

    private function writeWord(string $word): void
    {
        $this->writeLength(strlen($word));
        if ($word !== '') {
            fwrite($this->socket, $word);
        }
    }

    private function writeLength(int $length): void
    {
        if ($length < 0x80) {
            fwrite($this->socket, chr($length));
            return;
        }
        if ($length < 0x4000) {
            $length |= 0x8000;
            fwrite($this->socket, chr(($length >> 8) & 0xFF) . chr($length & 0xFF));
            return;
        }
        if ($length < 0x200000) {
            $length |= 0xC00000;
            fwrite($this->socket, chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF));
            return;
        }
        if ($length < 0x10000000) {
            $length |= 0xE0000000;
            fwrite($this->socket, chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF));
            return;
        }

        fwrite($this->socket, chr(0xF0) . pack('N', $length));
    }

    private function readSentence(): array
    {
        $sentence = [];
        while (true) {
            $word = $this->readWord();
            if ($word === '') {
                return $sentence;
            }
            $sentence[] = $word;
        }
    }

    private function readWord(): string
    {
        $length = $this->readLength();
        if ($length === 0) {
            return '';
        }

        $word = '';
        while (strlen($word) < $length) {
            $chunk = fread($this->socket, $length - strlen($word));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);
                if (($meta['timed_out'] ?? false) === true) {
                    throw new RuntimeException('Timeout saat membaca respon MikroTik.');
                }
                break;
            }
            $word .= $chunk;
        }

        return $word;
    }

    private function readLength(): int
    {
        $first = ord(fread($this->socket, 1));
        if (($first & 0x80) === 0x00) {
            return $first;
        }
        if (($first & 0xC0) === 0x80) {
            $second = ord(fread($this->socket, 1));
            return (($first & ~0xC0) << 8) + $second;
        }
        if (($first & 0xE0) === 0xC0) {
            $second = ord(fread($this->socket, 1));
            $third = ord(fread($this->socket, 1));
            return (($first & ~0xE0) << 16) + ($second << 8) + $third;
        }
        if (($first & 0xF0) === 0xE0) {
            $second = ord(fread($this->socket, 1));
            $third = ord(fread($this->socket, 1));
            $fourth = ord(fread($this->socket, 1));
            return (($first & ~0xF0) << 24) + ($second << 16) + ($third << 8) + $fourth;
        }
        if (($first & 0xF8) === 0xF0) {
            $data = fread($this->socket, 4);
            $parts = unpack('N', $data);
            return (int) ($parts[1] ?? 0);
        }

        return 0;
    }
}

function mikrotikConfig(): array
{
    return [
        'host' => trim(appSettingText('mikrotik_host')),
        'port' => max(1, appSettingInt('mikrotik_port', 8728)),
        'use_ssl' => appSettingBool('mikrotik_use_ssl', false),
        'timeout' => max(3, appSettingInt('mikrotik_timeout', 10)),
        'username' => trim(appSettingText('mikrotik_username')),
        'password' => appSettingText('mikrotik_password'),
        'router_name' => trim(appSettingText('mikrotik_router_name')),
    ];
}

function mikrotikIsConfigured(): bool
{
    $config = mikrotikConfig();
    return $config['host'] !== '' && $config['username'] !== '' && $config['password'] !== '';
}

function mikrotikClient(): RouterOsApiClient
{
    $config = mikrotikConfig();
    if ($config['host'] === '' || $config['username'] === '' || $config['password'] === '') {
        throw new RuntimeException('Konfigurasi MikroTik belum lengkap. Isi host, username, password, dan port di Pengaturan.');
    }

    $client = new RouterOsApiClient(
        $config['host'],
        (int) $config['port'],
        (bool) $config['use_ssl'],
        (int) $config['timeout'],
    );
    $client->connect((string) $config['username'], (string) $config['password']);

    return $client;
}

function mikrotikTestConnection(): array
{
    $client = mikrotikClient();
    $identity = $client->command('/system/identity/print', [], ['name']);
    $resource = $client->command('/system/resource/print', [], ['version', 'board-name', 'uptime']);
    $client->disconnect();

    return [
        'identity' => $identity[0]['name'] ?? (mikrotikConfig()['router_name'] ?: 'MikroTik'),
        'version' => $resource[0]['version'] ?? '-',
        'board_name' => $resource[0]['board-name'] ?? '-',
        'uptime' => $resource[0]['uptime'] ?? '-',
    ];
}

function mikrotikFetchPppProfiles(): array
{
    $client = mikrotikClient();
    $profiles = $client->command('/ppp/profile/print', [], ['.id', 'name', 'rate-limit', 'only-one', 'local-address', 'remote-address']);
    $client->disconnect();

    usort($profiles, static fn(array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
    return $profiles;
}

function mikrotikFetchPppSecrets(): array
{
    $client = mikrotikClient();
    $secrets = $client->command('/ppp/secret/print', [], ['.id', 'name', 'profile', 'service', 'disabled', 'comment', 'caller-id', 'last-logged-out', 'last-logged-in', 'remote-address']);
    $client->disconnect();

    usort($secrets, static fn(array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
    return $secrets;
}

function mikrotikEnableSecret(string $secretId): void
{
    $client = mikrotikClient();
    $client->command('/ppp/secret/enable', ['.id' => $secretId]);
    $client->disconnect();
}

function mikrotikDisableSecret(string $secretId): void
{
    $client = mikrotikClient();
    $client->command('/ppp/secret/disable', ['.id' => $secretId]);
    $client->disconnect();
}

function mikrotikSetSecretProfile(string $secretId, string $profileName): void
{
    $client = mikrotikClient();
    $client->command('/ppp/secret/set', ['.id' => $secretId, 'profile' => $profileName]);
    $client->disconnect();
}

function syncCustomersFromMikrotik(PDO $pdo): array
{
    $secrets = mikrotikFetchPppSecrets();
    $byId = [];
    $byName = [];
    foreach ($secrets as $secret) {
        if (!empty($secret['id'])) {
            $byId[(string) $secret['id']] = $secret;
        }
        if (!empty($secret['name'])) {
            $byName[strtolower((string) $secret['name'])] = $secret;
        }
    }

    $customers = $pdo->query('SELECT id, service_username, mikrotik_secret_id FROM customers')->fetchAll();
    $updated = 0;
    $isolated = 0;
    $notFound = 0;

    $stmt = $pdo->prepare('UPDATE customers
        SET service_username = :service_username,
            mikrotik_secret_id = :mikrotik_secret_id,
            mikrotik_profile_name = :mikrotik_profile_name,
            mikrotik_last_status = :mikrotik_last_status,
            isolated = :isolated,
            last_synced_at = :last_synced_at
        WHERE id = :id');

    foreach ($customers as $customer) {
        $secret = null;
        $secretId = trim((string) ($customer['mikrotik_secret_id'] ?? ''));
        $username = strtolower(trim((string) ($customer['service_username'] ?? '')));

        if ($secretId !== '' && isset($byId[$secretId])) {
            $secret = $byId[$secretId];
        } elseif ($username !== '' && isset($byName[$username])) {
            $secret = $byName[$username];
        }

        if ($secret === null) {
            $stmt->execute([
                ':service_username' => trim((string) ($customer['service_username'] ?? '')),
                ':mikrotik_secret_id' => trim((string) ($customer['mikrotik_secret_id'] ?? '')),
                ':mikrotik_profile_name' => '',
                ':mikrotik_last_status' => 'not_found',
                ':isolated' => 0,
                ':last_synced_at' => date('Y-m-d H:i:s'),
                ':id' => (int) $customer['id'],
            ]);
            $updated++;
            $notFound++;
            continue;
        }

        $isDisabled = in_array(strtolower((string) ($secret['disabled'] ?? 'false')), ['true', 'yes'], true);
        $stmt->execute([
            ':service_username' => (string) ($secret['name'] ?? ''),
            ':mikrotik_secret_id' => (string) ($secret['id'] ?? ''),
            ':mikrotik_profile_name' => (string) ($secret['profile'] ?? ''),
            ':mikrotik_last_status' => $isDisabled ? 'disabled' : 'enabled',
            ':isolated' => $isDisabled ? 1 : 0,
            ':last_synced_at' => date('Y-m-d H:i:s'),
            ':id' => (int) $customer['id'],
        ]);
        $updated++;
        if ($isDisabled) {
            $isolated++;
        }
    }

    return [
        'updated' => $updated,
        'isolated' => $isolated,
        'not_found' => $notFound,
        'secret_count' => count($secrets),
    ];
}
