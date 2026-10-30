<?php
// Bulk product import from a CSV file (Excel "Save as CSV" works). Used by admin/import.php.
// Rows are matched to existing products by title + image file, so importing the same file again
// refreshes prices and stock instead of creating duplicates.

// Where each store section lives: category => [short name, icon, sections]. Sections that are
// not in the database yet are created under the category listed here.
const IMPORT_TAXONOMY = [
    'Archery' => ['Archery', 'fa-bullseye', ['Bow Sights', 'Arrow Rests', 'Quivers', 'Stabilizers', 'Broadheads', 'Broadhead Blades & Accessories', 'Archery Tools', 'Dampeners & Silencers', 'Arrows', 'Nocks', 'Bow Releases', 'Arrow Vanes', 'Crossbows']],
    'Outdoor Gear' => ['Outdoor Gear', 'fa-person-hiking', ['Bags & Packs', 'Bats & Impact Tools', 'Fishing Accessories', 'Storage Boxes & Outdoor Gear', 'Lighters', 'Hand Warmers', 'Fire Starters', 'Lighter Accessories', 'Candles', 'Bird Feeders & Nectar']],
    'Knives & Multi-Tools' => ['Knives & Tools', 'fa-screwdriver-wrench', ['Folding Knives', 'Fixed Blade Knives', 'Tactical Pens', 'Multi-Tools', 'Swords', 'Axes & Hatchets', 'Knife Accessories']],
    'Hunting Accessories' => ['Hunting', 'fa-binoculars', ['Boot & Gear Care', 'Scent Control & Laundry Care', 'Blinds & Seating', 'Slings', 'Game Calls', 'Trail Cameras']],
    'Optics & Sights' => ['Optics', 'fa-crosshairs', ['Scope Mounts & Accessories', 'Scopes', 'Red Dot Sights', 'Handgun Sights', 'Optic Mounting Plates']],
    'Shooting Accessories' => ['Shooting', 'fa-box-archive', ['Shooting Rests & Bags', 'Chronographs & Wind Meters', 'Bipods & Tripods', 'Targets & Accessories']],
    'Gun Cases & Storage' => ['Gun Cases', 'fa-suitcase', ['Gun Cases', 'Gun Safe Accessories', 'Ammo Cans & Boxes', 'Bow & Arrow Cases']],
    'Airgun Ammunition & Accessories' => ['Airgun Ammo', 'fa-circle-dot', ['Pellets & BBs', 'Airgun Accessories', 'CO2 Cartridges']],
    'Airguns & Air Rifles' => ['Airguns', 'fa-gun', ['Air Rifles & Pistols', 'Airsoft Guns & BBs', 'Slingshots']],
    'Gunsmithing Tools' => ['Gunsmithing', 'fa-toolbox', ['Gunsmithing Screwdrivers', 'Gun Vises', 'Gunsmithing Tools']],
    'Gun Care & Cleaning' => ['Gun Care', 'fa-spray-can', ['Cleaning Kits & Supplies', 'Gun Oils & Solvents']],
    'Reloading Equipment' => ['Reloading', 'fa-gears', ['Reloading Equipment']],
];
const IMPORT_FALLBACK_CATEGORY = 'Outdoor Gear';

// Supplier feeds often write brands in capitals without spaces
const IMPORT_BRAND_NAMES = [
    'COLDSTEEL' => 'Cold Steel', 'NIGHTFISION' => 'Night Fision', 'SIGSAUER' => 'SIG Sauer', 'LUCASOIL' => 'Lucas Oil',
    'G5OUTDOORS' => 'G5 Outdoors', 'FIRSTNATURE' => 'First Nature', 'SLICKTRICK' => 'Slick Trick', 'TACVANES' => 'TAC Vanes',
    'SWHACKER' => 'Swhacker', 'RAVIN' => 'Ravin', 'LIMBSAVER' => 'LimbSaver', 'ATSKO' => 'Atsko', 'BEEMAN' => 'Beeman',
    'ESTWING' => 'Estwing', 'SCOTT' => 'Scott', 'ZINK' => 'Zink', 'REAPR' => 'Reapr', 'GAMEFACE' => 'Gameface',
];

// Accepted spellings of each column, compared lowercase with spaces and symbols removed
const IMPORT_COLUMNS = [
    'name' => ['producttitle', 'title', 'name', 'productname', 'product'],
    'sku' => ['sku', 'itemnumber', 'partnumber'],
    'brand' => ['brand', 'manufacturer', 'vendor'],
    'category' => ['category', 'subcategory', 'section', 'type'],
    'parent' => ['parentcategory', 'department', 'maincategory'],
    'price' => ['price', 'saleprice'],
    'old_price' => ['compareat', 'compareatprice', 'oldprice', 'msrp', 'regularprice'],
    'in_stock' => ['instock', 'available'],
    'stock' => ['stockqty', 'stock', 'qty', 'quantity', 'inventory'],
    'published' => ['published', 'active', 'visible'],
    'image' => ['image', 'imageurl', 'img', 'photo'],
    'description' => ['shortdescription', 'description', 'details'],
    'url' => ['url', 'producturl', 'link'],
];

function import_clean_name(string $name): string
{
    // Drop internal codes such as "*205EagleAndFlag* " at the start of supplier titles
    $name = preg_replace('/^\*[^*]*\*\s*/', '', trim($name));
    return trim(preg_replace('/\s+/', ' ', $name));
}

function import_brand(string $brand): string
{
    $brand = trim($brand);
    return IMPORT_BRAND_NAMES[strtoupper($brand)] ?? $brand;
}

function import_yes(string $value, bool $default = true): bool
{
    $value = strtolower(trim($value));
    if ($value === '') return $default;
    return !in_array($value, ['no', 'n', 'false', '0', 'out', 'out of stock', 'hidden', 'draft'], true);
}

function import_money(string $value): ?float
{
    $value = preg_replace('/[^0-9.]/', '', $value);
    return $value === '' || !is_numeric($value) ? null : round((float)$value, 2);
}

// Finds the section by name, creating it (and its category) when needed. Returns its id.
function import_section(string $name, string $parent, array &$cache, array &$stats): int
{
    $key = strtolower($name);
    if (isset($cache[$key])) return $cache[$key];
    $id = query('SELECT id FROM subcategories WHERE LOWER(name) = ? ORDER BY id LIMIT 1', [$key])->fetchColumn();
    if ($id) return $cache[$key] = (int)$id;

    if ($parent === '') {
        $parent = IMPORT_FALLBACK_CATEGORY;
        foreach (IMPORT_TAXONOMY as $cat => [, , $subs]) {
            if (in_array($key, array_map('strtolower', $subs), true)) { $parent = $cat; break; }
        }
    }
    $catId = query('SELECT id FROM categories WHERE LOWER(name) = ?', [strtolower($parent)])->fetchColumn();
    if (!$catId) {
        [$short, $icon] = IMPORT_TAXONOMY[$parent] ?? [$parent, 'fa-tag'];
        $order = array_search($parent, array_keys(IMPORT_TAXONOMY), true);
        $order = $order === false ? (int)query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories')->fetchColumn() : $order;
        query('INSERT INTO categories (name, short_name, slug, icon, sort_order) VALUES (?, ?, ?, ?, ?)',
              [$parent, mb_substr($short, 0, 60), slugify($parent), $icon, $order]);
        $catId = db()->lastInsertId();
        $stats['categories']++;
    }
    $order = (int)query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM subcategories WHERE category_id = ?', [$catId])->fetchColumn();
    query('INSERT INTO subcategories (category_id, name, slug, sort_order) VALUES (?, ?, ?, ?)', [$catId, $name, slugify($name), $order]);
    $stats['sections']++;
    return $cache[$key] = (int)db()->lastInsertId();
}

// Returns the site-relative image path in uploads/products, downloading the file when it is not
// on the server yet. The path is kept even when the download fails (the store then shows the
// category icon), so a later re-import still matches the product and retries the download.
function import_image(string $src, string $base, array &$stats, float $deadline): string
{
    $src = trim($src);
    if ($src === '') return '';
    $path = parse_url($src, PHP_URL_PATH) ?: '';
    $file = basename($path);
    if (!preg_match('/^[A-Za-z0-9_.-]+\.(jpe?g|png|webp|gif)$/i', $file)) $file = md5($src) . '.jpg';
    $local = 'uploads/products/' . $file;
    if (is_file(APP_ROOT . '/' . $local)) return $local;

    $url = preg_match('#^https?://#i', $src) ? $src : ($base !== '' ? $base . '/' . ltrim($src, '/') : '');
    if ($url === '' || microtime(true) > $deadline) { $stats['images_missing']++; return $local; }

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
                            CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
    $body = curl_exec($ch);
    $ok = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
    curl_close($ch);
    $info = $ok && is_string($body) && strlen($body) < 10 * 1024 * 1024 ? @getimagesizefromstring($body) : false;
    if (!$info || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        $stats['images_missing']++;
        return $local;
    }
    $dir = APP_ROOT . '/uploads/products';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents("$dir/$file", $body);
    $stats['images_downloaded']++;
    return $local;
}

/**
 * Imports products from a CSV file. Options:
 *   download_images  fetch images that are not on the server yet (default true)
 *   image_base       site that relative image paths belong to; read from the URL column when empty
 *   time_budget      seconds to spend downloading images before skipping the rest (default 240)
 */
function import_products_csv(string $csvFile, array $opts = []): array
{
    $stats = ['added' => 0, 'updated' => 0, 'duplicates' => 0, 'skipped' => 0, 'categories' => 0, 'sections' => 0,
              'images_downloaded' => 0, 'images_missing' => 0, 'errors' => []];
    $fh = fopen($csvFile, 'rb');
    if (!$fh) { $stats['errors'][] = 'Could not read the file.'; return $stats; }

    $header = fgetcsv($fh, 0, ',', '"', '');
    if (!$header) { $stats['errors'][] = 'The file is empty.'; return $stats; }
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
    $col = [];
    foreach ($header as $i => $h) {
        $norm = preg_replace('/[^a-z0-9]/', '', strtolower((string)$h));
        foreach (IMPORT_COLUMNS as $field => $names) {
            if (!isset($col[$field]) && in_array($norm, $names, true)) $col[$field] = $i;
        }
    }
    foreach (['name' => 'Product Title', 'price' => 'Price', 'category' => 'Category'] as $field => $label) {
        if (!isset($col[$field])) $stats['errors'][] = "Missing column: $label.";
    }
    if ($stats['errors']) { fclose($fh); return $stats; }

    set_time_limit(0);
    $deadline = microtime(true) + (float)($opts['time_budget'] ?? 240);
    $download = $opts['download_images'] ?? true;
    $base = rtrim(trim((string)($opts['image_base'] ?? '')), '/');
    $sections = [];
    $seen = [];
    $line = 1;

    while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        $line++;
        if ($row === [null] || !array_filter($row, fn($v) => trim((string)$v) !== '')) continue;
        $get = fn(string $field) => isset($col[$field]) ? trim((string)($row[$col[$field]] ?? '')) : '';

        $name = mb_substr(import_clean_name($get('name')), 0, 255);
        $price = import_money($get('price'));
        $section = mb_substr($get('category'), 0, 120);
        if ($name === '' || !$price || $section === '') {
            $stats['skipped']++;
            if (count($stats['errors']) < 15) $stats['errors'][] = "Row $line skipped: needs a title, a price above 0 and a category.";
            continue;
        }
        $imageSrc = $get('image');
        $key = strtolower($name) . '|' . strtolower(basename((string)parse_url($imageSrc, PHP_URL_PATH)));
        if (isset($seen[$key])) { $stats['duplicates']++; continue; }
        $seen[$key] = true;

        if ($base === '' && preg_match('#^(https?)://([^/]+)#i', $get('url'), $m)) $base = 'https://' . $m[2];
        $image = $download ? import_image($imageSrc, $base, $stats, $deadline) : import_image($imageSrc, '', $stats, 0);
        $old = import_money($get('old_price'));
        $stock = import_yes($get('in_stock')) ? max(0, (int)($get('stock') === '' ? 10 : $get('stock'))) : 0;

        $data = [
            'subcategory_id' => import_section($section, $get('parent'), $sections, $stats),
            'sku' => mb_substr($get('sku'), 0, 64),
            'name' => $name,
            'slug' => slugify($name),
            'brand' => mb_substr(import_brand($get('brand')), 0, 120),
            'description' => $get('description') ?: null,
            'price' => $price,
            'old_price' => $old !== null && $old > $price ? $old : null,
            'stock' => $stock,
            'image' => $image,
            'is_active' => import_yes($get('published')) ? 1 : 0,
        ];
        $existing = query('SELECT id FROM products WHERE name = ? AND image = ? ORDER BY id LIMIT 1', [$name, $image])->fetchColumn();
        if ($existing) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
            query("UPDATE products SET $set WHERE id = ?", [...array_values($data), $existing]);
            $stats['updated']++;
        } else {
            $cols = implode(', ', array_keys($data));
            query("INSERT INTO products ($cols, rating, reviews) VALUES (" . rtrim(str_repeat('?, ', count($data)), ', ') . ', 0, 0)', array_values($data));
            $stats['added']++;
        }
    }
    fclose($fh);
    return $stats;
}
