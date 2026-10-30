<?php
// Helpers for the JSON endpoints in /api.

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

set_exception_handler(function (Throwable $e) {
    error_log('[texpert api] ' . $e->getMessage());
    json_error('Something went wrong on our side. Please try again or call +1-254-358-0017.', 500);
});

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $status = 400, array $extra = []): void
{
    json_out(['ok' => false, 'error' => $message] + $extra, $status);
}

// Reads a JSON POST body. Requiring the JSON content type also blocks cross-site form posts.
function json_input(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') json_error('Method not allowed.', 405);
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) json_error('Expected JSON.', 415);
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) json_error('Invalid request body.');
    return $data;
}

function str_field(array $data, string $key, int $max = 255): string
{
    $value = trim((string)($data[$key] ?? ''));
    return mb_substr($value, 0, $max);
}

function valid_email(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function current_customer(): ?array
{
    start_session();
    if (empty($_SESSION['customer_id'])) return null;
    $row = query('SELECT id, first_name, last_name, email, phone, address, city, state, zip, created_at FROM customers WHERE id = ?',
        [$_SESSION['customer_id']])->fetch();
    return $row ?: null;
}

// Crude per-session throttle for login / form endpoints.
function throttle(string $key, int $max, int $seconds): void
{
    start_session();
    $now = time();
    $hits = array_filter($_SESSION['throttle'][$key] ?? [], fn($t) => $t > $now - $seconds);
    if (count($hits) >= $max) json_error('Too many attempts. Please wait a few minutes and try again.', 429);
    $hits[] = $now;
    $_SESSION['throttle'][$key] = $hits;
}
