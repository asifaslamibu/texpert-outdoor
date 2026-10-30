<?php
// POST /api/track.php {order, email} — order status lookup. Both must match.

require_once __DIR__ . '/../includes/api.php';

$in = json_input();
throttle('track', 10, 600);

$orderNo = strtoupper(str_field($in, 'order', 20));
if ($orderNo !== '' && !str_starts_with($orderNo, 'TXO-')) $orderNo = 'TXO-' . preg_replace('/\D/', '', $orderNo);
$order = query('SELECT * FROM orders WHERE order_no = ? AND email = ?', [$orderNo, strtolower(str_field($in, 'email', 190))])->fetch();
if (!$order) json_error('We could not find an order with that number and email. Please check both and try again.', 404);

$items = query('SELECT name, qty FROM order_items WHERE order_id = ?', [$order['id']])->fetchAll();
json_out(['ok' => true, 'order' => [
    'order_no' => $order['order_no'],
    'date' => substr($order['created_at'], 0, 10),
    'status' => ORDER_STATUSES[$order['status']] ?? $order['status'],
    'status_key' => $order['status'],
    'payment' => $order['payment_status'],
    'delivery' => $order['delivery'],
    'tracking' => $order['tracking_number'],
    'total' => (float)$order['total'],
    'items' => array_map(fn($i) => ['name' => $i['name'], 'qty' => (int)$i['qty']], $items),
]]);
