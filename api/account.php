<?php
// Customer accounts.
// GET  /api/account.php                 -> {ok, customer|null}
// GET  /api/account.php?action=orders   -> {ok, orders}
// POST /api/account.php {action: register|login|logout|update|password, ...}

require_once __DIR__ . '/../includes/api.php';

function customer_out(?array $c): ?array
{
    if (!$c) return null;
    return [
        'first' => $c['first_name'], 'last' => $c['last_name'], 'email' => $c['email'], 'phone' => $c['phone'],
        'address' => $c['address'], 'city' => $c['city'], 'state' => $c['state'], 'zip' => $c['zip'],
        'since' => substr((string)$c['created_at'], 0, 10),
    ];
}

function require_customer(): array
{
    $c = current_customer();
    if (!$c) json_error('Please sign in.', 401);
    return $c;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    if (($_GET['action'] ?? '') === 'orders') {
        $c = require_customer();
        $orders = query('SELECT id, order_no, created_at, total, status, payment_status, delivery FROM orders
                         WHERE customer_id = ? OR (customer_id IS NULL AND email = ?) ORDER BY id DESC LIMIT 50', [$c['id'], $c['email']])->fetchAll();
        $out = [];
        foreach ($orders as $o) {
            $items = query('SELECT name, qty FROM order_items WHERE order_id = ?', [$o['id']])->fetchAll();
            $out[] = [
                'order_no' => $o['order_no'], 'date' => substr($o['created_at'], 0, 10), 'total' => (float)$o['total'],
                'status' => ORDER_STATUSES[$o['status']] ?? $o['status'], 'payment' => $o['payment_status'], 'delivery' => $o['delivery'],
                'items' => array_map(fn($i) => ['name' => $i['name'], 'qty' => (int)$i['qty']], $items),
            ];
        }
        json_out(['ok' => true, 'orders' => $out]);
    }
    json_out(['ok' => true, 'customer' => customer_out(current_customer())]);
}

$in = json_input();
start_session();

switch ($in['action'] ?? '') {
    case 'register':
        throttle('register', 5, 3600);
        $first = str_field($in, 'first', 80);
        $last = str_field($in, 'last', 80);
        $email = strtolower(str_field($in, 'email', 190));
        $pass = (string)($in['password'] ?? '');
        if ($first === '') json_error('Please enter your first name.');
        if (!valid_email($email)) json_error('Please enter a valid email address.');
        if (strlen($pass) < 8) json_error('Password must be at least 8 characters.');
        if (query('SELECT 1 FROM customers WHERE email = ?', [$email])->fetchColumn()) {
            json_error('An account with this email already exists. Please sign in.', 409);
        }
        query('INSERT INTO customers (first_name, last_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)',
            [$first, $last, $email, str_field($in, 'phone', 40), password_hash($pass, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int)db()->lastInsertId();
        json_out(['ok' => true, 'customer' => customer_out(current_customer())]);

    case 'login':
        throttle('login', 8, 900);
        $email = strtolower(str_field($in, 'email', 190));
        $row = query('SELECT id, password_hash FROM customers WHERE email = ?', [$email])->fetch();
        if (!$row || !password_verify((string)($in['password'] ?? ''), $row['password_hash'])) {
            json_error('Email or password is incorrect.', 401);
        }
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int)$row['id'];
        json_out(['ok' => true, 'customer' => customer_out(current_customer())]);

    case 'logout':
        unset($_SESSION['customer_id']);
        session_regenerate_id(true);
        json_out(['ok' => true]);

    case 'update':
        $c = require_customer();
        $first = str_field($in, 'first', 80);
        if ($first === '') json_error('Please enter your first name.');
        query('UPDATE customers SET first_name = ?, last_name = ?, phone = ?, address = ?, city = ?, state = ?, zip = ? WHERE id = ?',
            [$first, str_field($in, 'last', 80), str_field($in, 'phone', 40), str_field($in, 'address'), str_field($in, 'city', 100),
             str_field($in, 'state', 60), str_field($in, 'zip', 20), $c['id']]);
        json_out(['ok' => true, 'customer' => customer_out(current_customer())]);

    case 'password':
        $c = require_customer();
        throttle('password', 5, 900);
        $hash = query('SELECT password_hash FROM customers WHERE id = ?', [$c['id']])->fetchColumn();
        if (!password_verify((string)($in['current'] ?? ''), $hash)) json_error('Current password is incorrect.', 401);
        if (strlen((string)($in['new'] ?? '')) < 8) json_error('New password must be at least 8 characters.');
        query('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash((string)$in['new'], PASSWORD_DEFAULT), $c['id']]);
        json_out(['ok' => true]);
}
json_error('Unknown action.');
