<?php
// Serves the live catalog as a script that sets window.TXO before assets/js/data.js runs.
// If the database is unavailable it outputs nothing, and data.js falls back to its built-in catalog.

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/javascript; charset=utf-8');
// Always revalidate so admin edits show up immediately; unchanged catalogs get a cheap 304.
header('Cache-Control: no-cache');

try {
    $cats = query('SELECT id, name, short_name, slug, icon FROM categories ORDER BY sort_order, id')->fetchAll();
    $subs = query('SELECT id, category_id, name, slug FROM subcategories ORDER BY sort_order, id')->fetchAll();
    $prods = query('SELECT id, subcategory_id, sku, name, brand, description, price, old_price, stock, rating, reviews, image, is_new
                    FROM products WHERE is_active = 1 ORDER BY id')->fetchAll();
} catch (Throwable $e) {
    error_log('[texpert catalog] ' . $e->getMessage());
    echo '/* catalog unavailable, using built-in data */';
    exit;
}

$bySub = [];
foreach ($prods as $p) {
    $bySub[$p['subcategory_id']][] = [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'sku' => $p['sku'],
        'brand' => $p['brand'],
        'description' => (string)$p['description'],
        'price' => (float)$p['price'],
        'oldPrice' => $p['old_price'] !== null ? (float)$p['old_price'] : 0,
        'stock' => (int)$p['stock'],
        'rating' => (float)$p['rating'],
        'reviews' => (int)$p['reviews'],
        'img' => $p['image'],
        'isNew' => (bool)$p['is_new'],
    ];
}
$subsByCat = [];
foreach ($subs as $s) $subsByCat[$s['category_id']][$s['name']] = $bySub[$s['id']] ?? [];

$catalog = array_map(fn($c) => [
    'name' => $c['name'],
    'short' => $c['short_name'],
    'icon' => $c['icon'],
    'subs' => (object)($subsByCat[$c['id']] ?? []),
], $cats);

$payload = [
    'catalog' => $catalog,
    'config' => [
        'api' => true,
        'stripe' => stripe_enabled(),
        'freeOver' => (float)config('shipping.free_over', 75),
        'flatRate' => (float)config('shipping.flat_rate', 9.95),
    ],
];
$js = 'window.TXO = ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';';
$etag = '"' . md5($js) . '"';
header('ETag: ' . $etag);
if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
echo $js;
