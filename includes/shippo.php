<?php
// Minimal Shippo client using cURL (no SDK): live carrier rates at checkout and label purchase in the admin.
// Without an API key in config.php the store keeps charging its flat shipping rate.

// Ship-from address; override any field with 'shippo' => ['from' => [...]] in config.php
const SHIPPO_FROM = [
    'name' => 'Texpert Outdoors', 'company' => 'Texpert Outdoors', 'street1' => '2990 Congressman Ln', 'city' => 'Dallas',
    'state' => 'TX', 'zip' => '75220', 'country' => 'US', 'phone' => '+1-254-358-0017', 'email' => 'sales@texpertoutdoors.com',
];

function shippo_request(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init('https://api.goshippo.com/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Authorization: ShippoToken ' . config('shippo.api_key'), 'Content-Type: application/json'],
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('Shippo connection failed: ' . $err);
    $data = json_decode($raw, true) ?: [];
    if ($status >= 400) throw new RuntimeException('Shippo error: ' . ($data['detail'] ?? substr((string)$raw, 0, 300)));
    return $data;
}

function shippo_from(): array
{
    return array_merge(SHIPPO_FROM, (array)config('shippo.from', []));
}

// $to: first_name, last_name, address, city, state, zip, phone, email (same keys as the orders table)
function shippo_address_to(array $to): array
{
    return [
        'name' => trim(($to['first_name'] ?? '') . ' ' . ($to['last_name'] ?? '')) ?: 'Customer',
        'street1' => $to['address'], 'city' => $to['city'], 'state' => $to['state'], 'zip' => $to['zip'], 'country' => 'US',
        'phone' => $to['phone'] ?? '', 'email' => $to['email'] ?? '',
    ];
}

// One box for the whole order: total weight, sized to the largest item with items stacked by height.
// $items: [['weight' => lb or null, 'dimensions' => 'LxWxH' inches, 'qty' => n]]
function shippo_parcel(array $items): array
{
    $weight = $length = $width = $height = 0.0;
    foreach ($items as $i) {
        $qty = max(1, (int)$i['qty']);
        $weight += ((float)($i['weight'] ?? 0) ?: 1.0) * $qty;
        $d = array_map('floatval', preg_split('/\s*[x*]\s*/i', trim((string)($i['dimensions'] ?? ''))));
        if (count($d) === 3 && min($d) > 0) {
            rsort($d);
            $length = max($length, $d[0]);
            $width = max($width, $d[1]);
            $height += $d[2] * $qty;
        }
    }
    if (!$length) [$length, $width, $height] = [12, 9, 4];
    return [
        'length' => (string)round(max($length, 1), 1), 'width' => (string)round(max($width, 1), 1),
        'height' => (string)round(min(max($height, 1), 36), 1), 'distance_unit' => 'in',
        'weight' => (string)round(max($weight, 0.1), 2), 'mass_unit' => 'lb',
    ];
}

function shippo_shipment(array $to, array $items): array
{
    return shippo_request('POST', 'shipments/', [
        'address_from' => shippo_from(),
        'address_to' => shippo_address_to($to),
        'parcels' => [shippo_parcel($items)],
        'async' => false,
    ]);
}

// A shipment's US-dollar rates, cheapest first. Orders over the free-shipping amount get the cheapest one free.
function shippo_rates(array $shipment, float $subtotal): array
{
    $rates = [];
    foreach ($shipment['rates'] ?? [] as $r) {
        if (($r['currency'] ?? '') !== 'USD' || !isset($r['amount'])) continue;
        $rates[] = [
            'id' => $r['object_id'],
            'carrier' => $r['provider'] ?? '',
            'service' => $r['servicelevel']['name'] ?? '',
            'token' => $r['servicelevel']['token'] ?? '',
            'days' => $r['estimated_days'] ?? null,
            'cost' => (float)$r['amount'],
        ];
    }
    usort($rates, fn($a, $b) => $a['cost'] <=> $b['cost']);
    $rates = array_slice($rates, 0, 6);
    $free = $subtotal >= (float)config('shipping.free_over', 75);
    foreach ($rates as $i => &$r) {
        $r['price'] = $free && $i === 0 ? 0.0 : round($r['cost'], 2);
        $r['label'] = trim("{$r['carrier']} {$r['service']}");
    }
    return $rates;
}

// Product weights and sizes for a list of [product_id => qty]
function shippo_items(array $qtyById): array
{
    if (!$qtyById) return [];
    $in = implode(',', array_fill(0, count($qtyById), '?'));
    $rows = query("SELECT id, weight, dimensions FROM products WHERE id IN ($in)", array_keys($qtyById))->fetchAll();
    return array_map(fn($p) => ['weight' => $p['weight'], 'dimensions' => $p['dimensions'], 'qty' => $qtyById[$p['id']]], $rows);
}

// Buys a label for an order: re-quotes the parcel and buys the service the customer chose (or the cheapest).
// Returns ['tracking_number', 'tracking_url', 'label_url', 'carrier', 'service', 'cost'].
function shippo_buy_label(array $order): array
{
    $qty = [];
    foreach (query('SELECT product_id, qty FROM order_items WHERE order_id = ? AND product_id IS NOT NULL', [$order['id']])->fetchAll() as $i) {
        $qty[$i['product_id']] = ($qty[$i['product_id']] ?? 0) + (int)$i['qty'];
    }
    $shipment = shippo_shipment($order, shippo_items($qty));
    $rates = shippo_rates($shipment, 0);
    if (!$rates) {
        $why = implode(' ', array_map(fn($m) => $m['text'] ?? '', $shipment['messages'] ?? []));
        throw new RuntimeException('No shipping rates for this address. ' . $why);
    }
    $chosen = $rates[0];
    foreach ($rates as $r) {
        if ($r['label'] === $order['shipping_service']) { $chosen = $r; break; }
    }
    $tx = shippo_request('POST', 'transactions/', ['rate' => $chosen['id'], 'label_file_type' => 'PDF_4x6', 'async' => false]);
    if (($tx['status'] ?? '') !== 'SUCCESS') {
        $why = implode(' ', array_map(fn($m) => $m['text'] ?? '', $tx['messages'] ?? []));
        throw new RuntimeException('Label purchase failed. ' . ($why ?: 'Please try again.'));
    }
    return [
        'tracking_number' => (string)($tx['tracking_number'] ?? ''),
        'tracking_url' => (string)($tx['tracking_url_provider'] ?? ''),
        'label_url' => (string)($tx['label_url'] ?? ''),
        'carrier' => $chosen['carrier'],
        'service' => $chosen['label'],
        'cost' => $chosen['cost'],
    ];
}
