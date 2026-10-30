<?php
// Shared setup for every PHP entry point: config, database, session and small helpers.

declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Site is not configured yet: copy includes/config.sample.php to includes/config.php.');
}
$CONFIG = require $configFile;

function config(string $key, $default = null)
{
    global $CONFIG;
    $value = $CONFIG;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', config('db.host'), config('db.name'));
        $pdo = new PDO($dsn, config('db.user'), config('db.pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('txo_session');
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function slugify(string $text): string
{
    $text = strtolower(str_replace('&', 'and', $text));
    return trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
}

function money($amount): string
{
    return '$' . number_format((float)$amount, 2);
}

// Base URL of the site (folder that contains index.html), e.g. https://www.texpertoutdoors.com
function site_url(): string
{
    $configured = rtrim((string)config('site_url', ''), '/');
    if ($configured !== '') return $configured;
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = preg_replace('#/(api|admin|install)$#', '', $dir);
    return ($https ? 'https' : 'http') . '://' . $host . rtrim($dir, '/');
}

function shipping_for(float $subtotal, bool $pickup): float
{
    if ($pickup || $subtotal >= (float)config('shipping.free_over', 75)) return 0.0;
    return (float)config('shipping.flat_rate', 9.95);
}

function stripe_enabled(): bool
{
    return str_starts_with((string)config('stripe.secret_key', ''), 'sk_');
}

// Best-effort notification email; failures never block the request.
function notify(string $to, string $subject, string $body): void
{
    if ($to === '') return;
    $from = (string)config('from_email', '');
    $headers = "Content-Type: text/plain; charset=UTF-8\r\n" . ($from ? "From: Texpert Outdoors <$from>\r\n" : '');
    @mail($to, $subject, $body, $headers);
}

const ORDER_STATUSES = [
    'new' => 'New',
    'confirmed' => 'Confirmed',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'ready_for_pickup' => 'Ready for Pickup',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
