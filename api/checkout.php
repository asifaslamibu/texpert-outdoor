<?php
// POST /api/checkout.php — creates an order from the cart. Prices and stock come from the database.
// Returns {ok, order_no, token} for pay-later orders, or {ok, redirect} to Stripe Checkout for card payments.

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/stripe.php';

$in = json_input();
throttle('checkout', 10, 600);

// ----- Validate customer details -----
$pickup = ($in['delivery'] ?? '') === 'pickup';
$payment = ($in['payment'] ?? 'later') === 'card' ? 'card' : 'later';
$f = [
    'email' => strtolower(str_field($in, 'email', 190)),
    'phone' => str_field($in, 'phone', 40),
    'first_name' => str_field($in, 'first', 80),
    'last_name' => str_field($in, 'last', 80),
    'address' => $pickup ? '' : str_field($in, 'address'),
    'city' => $pickup ? '' : str_field($in, 'city', 100),
    'state' => $pickup ? '' : str_field($in, 'state', 60),
    'zip' => $pickup ? '' : str_field($in, 'zip', 20),
    'notes' => str_field($in, 'notes', 2000),
];
if (!valid_email($f['email'])) json_error('Please enter a valid email address.');
if (strlen(preg_replace('/\D/', '', $f['phone'])) < 7) json_error('Please enter a valid phone number.');
if ($f['first_name'] === '' || $f['last_name'] === '') json_error('Please enter your first and last name.');
if (!$pickup && ($f['address'] === '' || $f['city'] === '' || $f['state'] === '' || $f['zip'] === '')) {
    json_error('Please complete your shipping address.');
}
if ($payment === 'card' && !stripe_enabled()) json_error('Card payments are not available right now. Choose "Pay Later".');

// ----- Cart: merge duplicate lines, cap quantities -----
$cart = [];
foreach ((array)($in['items'] ?? []) as $line) {
    $id = (int)($line['id'] ?? 0);
    $qty = (int)($line['qty'] ?? 0);
    if ($id > 0 && $qty > 0) $cart[$id] = min(99, ($cart[$id] ?? 0) + $qty);
}
if (!$cart) json_error('Your cart is empty.');
if (count($cart) > 50) json_error('Too many different items in one order.');

$customer = current_customer();

db()->beginTransaction();
try {
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $rows = query("SELECT id, name, price, stock FROM products WHERE is_active = 1 AND id IN ($placeholders) FOR UPDATE", array_keys($cart))->fetchAll();
    $byId = array_column($rows, null, 'id');

    $items = [];
    $subtotal = 0.0;
    foreach ($cart as $id => $qty) {
        $p = $byId[$id] ?? null;
        if (!$p) throw new DomainException('One of the items in your cart is no longer available. Please review your cart.');
        if ((int)$p['stock'] < $qty) {
            throw new DomainException((int)$p['stock'] > 0
                ? "Only {$p['stock']} left of \"{$p['name']}\". Please lower the quantity."
                : "\"{$p['name']}\" is out of stock. Please remove it from your cart.");
        }
        $items[] = ['product_id' => $id, 'name' => $p['name'], 'price' => (float)$p['price'], 'qty' => $qty];
        $subtotal += (float)$p['price'] * $qty;
    }
    $subtotal = round($subtotal, 2);
    $shipping = shipping_for($subtotal, $pickup);
    $total = round($subtotal + $shipping, 2);

    do {
        $orderNo = 'TXO-' . random_int(100000, 999999);
    } while (query('SELECT 1 FROM orders WHERE order_no = ?', [$orderNo])->fetchColumn());
    $token = bin2hex(random_bytes(16));

    query('INSERT INTO orders (order_no, token, customer_id, first_name, last_name, email, phone, delivery, address, city, state, zip, notes,
                               subtotal, shipping, total, payment_method, payment_status)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$orderNo, $token, $customer['id'] ?? null, $f['first_name'], $f['last_name'], $f['email'], $f['phone'], $pickup ? 'pickup' : 'ship',
         $f['address'], $f['city'], $f['state'], $f['zip'], $f['notes'] ?: null, $subtotal, $shipping, $total,
         $payment, $payment === 'card' ? 'pending' : 'unpaid']);
    $orderId = (int)db()->lastInsertId();

    foreach ($items as $item) {
        query('INSERT INTO order_items (order_id, product_id, name, price, qty) VALUES (?, ?, ?, ?, ?)',
            [$orderId, $item['product_id'], $item['name'], $item['price'], $item['qty']]);
        query('UPDATE products SET stock = stock - ? WHERE id = ?', [$item['qty'], $item['product_id']]);
    }

    // Save the address on the customer's account for next time
    if ($customer && !$pickup) {
        query('UPDATE customers SET address = ?, city = ?, state = ?, zip = ?, phone = IF(phone = "", ?, phone) WHERE id = ?',
            [$f['address'], $f['city'], $f['state'], $f['zip'], $f['phone'], $customer['id']]);
    }

    $order = ['order_no' => $orderNo, 'token' => $token, 'email' => $f['email'], 'shipping' => $shipping];
    $redirect = null;
    if ($payment === 'card') {
        $session = stripe_checkout_session($order, $items);
        query('UPDATE orders SET stripe_session_id = ? WHERE id = ?', [$session['id'], $orderId]);
        $redirect = $session['url'];
    }
    db()->commit();
} catch (DomainException $ex) {
    db()->rollBack();
    json_error($ex->getMessage(), 409);
} catch (Throwable $ex) {
    db()->rollBack();
    throw $ex;
}

// ----- Notifications -----
$lines = implode("\n", array_map(fn($i) => "{$i['qty']} x {$i['name']} @ " . money($i['price']), $items));
$summary = "Order $orderNo\n\n$lines\n\nSubtotal: " . money($subtotal) . "\nShipping: " . ($shipping ? money($shipping) : 'Free')
    . "\nTotal: " . money($total) . "\n\n" . ($pickup ? 'Store pickup' : "Ship to: {$f['address']}, {$f['city']}, {$f['state']} {$f['zip']}");
notify((string)config('notify_email', ''), "New order $orderNo (" . money($total) . ')',
    "$summary\n\nCustomer: {$f['first_name']} {$f['last_name']}, {$f['email']}, {$f['phone']}\nPayment: $payment\n\n"
    . site_url() . "/admin/order.php?id=$orderId");
if ($payment === 'later') {
    notify($f['email'], "We received your order $orderNo",
        "Hi {$f['first_name']},\n\nThanks for your order! Our team will call you at {$f['phone']} to confirm stock and payment.\n\n$summary\n\nTexpert Outdoors\n+1-254-358-0017");
}

json_out(['ok' => true, 'order_no' => $orderNo, 'token' => $token, 'redirect' => $redirect]);
