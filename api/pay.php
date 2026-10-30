<?php
// POST /api/pay.php {order, token} — starts a new Stripe Checkout for an unpaid order (e.g. after a cancelled payment).

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/stripe.php';

$in = json_input();
throttle('pay', 10, 600);
if (!stripe_enabled()) json_error('Card payments are not available right now. Please call +1-254-358-0017.');

$order = query('SELECT * FROM orders WHERE order_no = ?', [str_field($in, 'order', 20)])->fetch();
if (!$order || !hash_equals($order['token'], (string)($in['token'] ?? ''))) json_error('Order not found.', 404);
if ($order['payment_status'] === 'paid') json_error('This order is already paid.', 409);
if ($order['status'] === 'cancelled') json_error('This order was cancelled. Please place a new order.', 409);

$items = query('SELECT name, price, qty FROM order_items WHERE order_id = ?', [$order['id']])->fetchAll();
$items = array_map(fn($i) => ['name' => $i['name'], 'price' => (float)$i['price'], 'qty' => (int)$i['qty']], $items);
$session = stripe_checkout_session(
    ['order_no' => $order['order_no'], 'token' => $order['token'], 'email' => $order['email'], 'shipping' => (float)$order['shipping']],
    $items
);
query("UPDATE orders SET stripe_session_id = ?, payment_method = 'card', payment_status = 'pending' WHERE id = ?", [$session['id'], $order['id']]);
json_out(['ok' => true, 'redirect' => $session['url']]);
