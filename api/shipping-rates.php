<?php
// POST /api/shipping-rates.php {address, city, state, zip, first, last, phone, items} — live Shippo rates for checkout.
// Returns {ok, rates: [{id, label, days, price}]}; an empty list means the flat rate applies (Shippo not set up).

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/shippo.php';

$in = json_input();
if (!shippo_enabled()) json_out(['ok' => true, 'rates' => []]);
throttle('shipping-rates', 30, 600);

$to = [
    'first_name' => str_field($in, 'first', 80), 'last_name' => str_field($in, 'last', 80),
    'address' => str_field($in, 'address'), 'city' => str_field($in, 'city', 100),
    'state' => str_field($in, 'state', 60), 'zip' => str_field($in, 'zip', 20), 'phone' => str_field($in, 'phone', 40),
];
if ($to['address'] === '' || $to['city'] === '' || $to['state'] === '' || $to['zip'] === '') json_error('Please complete your shipping address.');

$cart = cart_input($in);
$marks = implode(',', array_fill(0, count($cart), '?'));
$subtotal = 0.0;
foreach (query("SELECT id, price FROM products WHERE is_active = 1 AND id IN ($marks)", array_keys($cart))->fetchAll() as $p) {
    $subtotal += (float)$p['price'] * $cart[$p['id']];
}

try {
    $rates = shippo_rates(shippo_shipment($to, shippo_items($cart)), $subtotal);
} catch (RuntimeException $e) {
    error_log('[texpert shippo] ' . $e->getMessage());
    json_error('We could not get shipping rates right now. Please try again in a moment.', 502);
}
if (!$rates) json_error('We could not find shipping options for this address. Please check the street, city, state and ZIP.');

json_out(['ok' => true, 'rates' => array_map(fn($r) => [
    'id' => $r['id'], 'label' => $r['label'], 'days' => $r['days'], 'price' => $r['price'],
], $rates)]);
