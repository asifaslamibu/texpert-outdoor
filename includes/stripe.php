<?php
// Minimal Stripe client (Checkout Sessions + webhook signatures) using cURL, no SDK needed.

require_once __DIR__ . '/bootstrap.php';

function stripe_request(string $method, string $path, array $params = []): array
{
    $ch = curl_init('https://api.stripe.com/v1/' . ltrim($path, '/') . ($method === 'GET' && $params ? '?' . http_build_query($params) : ''));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => config('stripe.secret_key') . ':',
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($method !== 'GET') curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) throw new RuntimeException('Stripe connection failed: ' . $err);
    $data = json_decode($body, true) ?: [];
    if ($status >= 400) throw new RuntimeException('Stripe error: ' . ($data['error']['message'] ?? "HTTP $status"));
    return $data;
}

// Creates a hosted Checkout page for an order. $items: [['name' => ..., 'price' => float, 'qty' => int], ...]
function stripe_checkout_session(array $order, array $items): array
{
    $currency = config('stripe.currency', 'usd');
    $lines = [];
    foreach ($items as $item) {
        $lines[] = [
            'quantity' => $item['qty'],
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => (int)round($item['price'] * 100),
                'product_data' => ['name' => mb_substr($item['name'], 0, 250)],
            ],
        ];
    }
    if ($order['shipping'] > 0) {
        $lines[] = [
            'quantity' => 1,
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => (int)round($order['shipping'] * 100),
                'product_data' => ['name' => 'Shipping'],
            ],
        ];
    }

    $base = site_url();
    $query = 'order=' . urlencode($order['order_no']) . '&token=' . $order['token'];
    return stripe_request('POST', 'checkout/sessions', [
        'mode' => 'payment',
        'line_items' => $lines,
        'customer_email' => $order['email'],
        'client_reference_id' => $order['order_no'],
        'metadata' => ['order_no' => $order['order_no']],
        'success_url' => "$base/order-success.html?$query&session_id={CHECKOUT_SESSION_ID}",
        'cancel_url' => "$base/order-success.html?$query&cancelled=1",
    ]);
}

// Verifies the Stripe-Signature header (v1 scheme, 5 minute tolerance).
function stripe_verify_signature(string $payload, string $header, string $secret): bool
{
    $parts = [];
    foreach (explode(',', $header) as $piece) {
        [$k, $v] = array_pad(explode('=', trim($piece), 2), 2, '');
        $parts[$k][] = $v;
    }
    $timestamp = (int)($parts['t'][0] ?? 0);
    if (!$timestamp || abs(time() - $timestamp) > 300) return false;
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($parts['v1'] ?? [] as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}

// Marks an order paid once Stripe confirms the session.
function mark_order_paid(string $sessionId): ?array
{
    $order = query('SELECT * FROM orders WHERE stripe_session_id = ?', [$sessionId])->fetch();
    if (!$order) return null;
    if ($order['payment_status'] !== 'paid') {
        query("UPDATE orders SET payment_status = 'paid', status = IF(status = 'new', 'confirmed', status) WHERE id = ?", [$order['id']]);
        notify((string)config('notify_email', ''), "Payment received: {$order['order_no']}",
            "Order {$order['order_no']} was paid by card (" . money($order['total']) . ").\n" . site_url() . "/admin/order.php?id={$order['id']}");
    }
    return query('SELECT * FROM orders WHERE id = ?', [$order['id']])->fetch();
}
