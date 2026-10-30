<?php
// GET /api/order.php?order=TXO-123456&token=...[&session_id=cs_...]
// Returns an order summary for the confirmation page. The secret token from checkout is required.
// With session_id it also asks Stripe whether the card payment went through (in case the webhook is slow).

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/stripe.php';

$order = query('SELECT * FROM orders WHERE order_no = ?', [(string)($_GET['order'] ?? '')])->fetch();
if (!$order || !hash_equals($order['token'], (string)($_GET['token'] ?? ''))) json_error('Order not found.', 404);

$sessionId = (string)($_GET['session_id'] ?? '');
if ($sessionId !== '' && $order['payment_status'] !== 'paid' && stripe_enabled() && hash_equals((string)$order['stripe_session_id'], $sessionId)) {
    try {
        $session = stripe_request('GET', 'checkout/sessions/' . urlencode($sessionId));
        if (($session['payment_status'] ?? '') === 'paid') $order = mark_order_paid($sessionId) ?: $order;
    } catch (Throwable $e) {
        error_log('[texpert order] ' . $e->getMessage());
    }
}

$items = query('SELECT name, price, qty FROM order_items WHERE order_id = ?', [$order['id']])->fetchAll();
json_out(['ok' => true, 'order' => [
    'order_no' => $order['order_no'],
    'first_name' => $order['first_name'],
    'email' => $order['email'],
    'phone' => $order['phone'],
    'delivery' => $order['delivery'],
    'payment_method' => $order['payment_method'],
    'payment_status' => $order['payment_status'],
    'status' => ORDER_STATUSES[$order['status']] ?? $order['status'],
    'subtotal' => (float)$order['subtotal'],
    'shipping' => (float)$order['shipping'],
    'total' => (float)$order['total'],
    'items' => array_map(fn($i) => ['name' => $i['name'], 'price' => (float)$i['price'], 'qty' => (int)$i['qty']], $items),
]]);
