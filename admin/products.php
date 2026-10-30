<?php
require_once __DIR__ . '/_init.php';
require_admin();

// Deletes products and their uploaded images (stock images in assets/ are kept). Past orders keep
// their own copy of each item's name and price, so order history is unaffected.
function delete_products(?array $ids): int
{
    $where = $ids === null ? '' : ' WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    $params = $ids ?? [];
    $images = query("SELECT image FROM products$where", $params)->fetchAll(PDO::FETCH_COLUMN);
    $count = query("DELETE FROM products$where", $params)->rowCount();
    foreach ($images as $img) {
        if (str_starts_with((string)$img, 'uploads/products/')) @unlink(APP_ROOT . '/' . $img);
    }
    return $count;
}

if (is_post()) {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));

    if ($action === 'toggle') {
        query('UPDATE products SET is_active = 1 - is_active WHERE id = ?', [$id]);
        flash('Product visibility updated.');
    }
    if ($action === 'delete') {
        delete_products([$id]);
        flash('Product deleted.');
    }
    if (in_array($action, ['bulk_delete', 'bulk_hide', 'bulk_show'], true)) {
        if (!$ids) {
            flash('Select at least one product first.', 'err');
        } elseif ($action === 'bulk_delete') {
            flash(delete_products($ids) . ' product(s) deleted.');
        } else {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $n = query("UPDATE products SET is_active = ? WHERE id IN ($in)", [$action === 'bulk_show' ? 1 : 0, ...$ids])->rowCount();
            flash("$n product(s) " . ($action === 'bulk_show' ? 'shown in' : 'hidden from') . ' the store.');
        }
    }
    if ($action === 'delete_all') {
        if (trim($_POST['confirm'] ?? '') !== 'DELETE') {
            flash('Nothing was deleted. Type DELETE (in capitals) to confirm.', 'err');
        } else {
            flash(delete_products(null) . ' product(s) deleted. Your categories and past orders were kept.');
        }
    }
    redirect('products.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'cat', 'stock', 'page']))));
}

$q = trim($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);
$stock = $_GET['stock'] ?? '';
$where = ['1 = 1'];
$params = [];
if ($q !== '') { $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.sku = ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = $q; }
if ($cat) { $where[] = 'c.id = ?'; $params[] = $cat; }
if ($stock === 'low') $where[] = 'p.stock <= 3';
if ($stock === 'hidden') $where[] = 'p.is_active = 0';
$whereSql = implode(' AND ', $where);

$perPage = 25;
$total = (int)query("SELECT COUNT(*) FROM products p JOIN subcategories s ON s.id = p.subcategory_id JOIN categories c ON c.id = s.category_id WHERE $whereSql", $params)->fetchColumn();
$page = max(1, min((int)($_GET['page'] ?? 1), (int)ceil(max($total, 1) / $perPage)));
$rows = query("SELECT p.*, s.name AS sub_name, c.name AS cat_name FROM products p
               JOIN subcategories s ON s.id = p.subcategory_id JOIN categories c ON c.id = s.category_id
               WHERE $whereSql ORDER BY p.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params)->fetchAll();
$cats = query('SELECT id, name FROM categories ORDER BY sort_order, id')->fetchAll();

admin_header('Products', 'products');
?>
<div class="toolbar">
  <form class="filters-row" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, brand or SKU">
    <select name="cat" onchange="this.form.submit()">
      <option value="">All categories</option>
      <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="stock" onchange="this.form.submit()">
      <option value="">All products</option>
      <option value="low" <?= $stock === 'low' ? 'selected' : '' ?>>Low stock (3 or less)</option>
      <option value="hidden" <?= $stock === 'hidden' ? 'selected' : '' ?>>Hidden</option>
    </select>
    <button class="btn btn-light">Filter</button>
  </form>
  <span class="filters-row">
    <a href="import.php" class="btn btn-light"><i class="fa-solid fa-file-import"></i> Import CSV</a>
    <a href="product-edit.php" class="btn"><i class="fa-solid fa-plus"></i> Add Product</a>
  </span>
</div>

<section class="card flush">
  <form method="post" id="bulkForm" class="bulk-bar" onsubmit="return confirmBulk(event)">
    <?= csrf_field() ?>
    <span class="muted"><?= $total ?> product<?= $total === 1 ? '' : 's' ?> · <strong id="selCount">0</strong> selected</span>
    <select name="action" aria-label="Bulk action">
      <option value="">Bulk action…</option>
      <option value="bulk_show">Show in store</option>
      <option value="bulk_hide">Hide from store</option>
      <option value="bulk_delete">Delete selected</option>
    </select>
    <button class="btn btn-light btn-sm">Apply</button>
  </form>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th class="sel"><input type="checkbox" id="selAll" aria-label="Select all on this page"></th><th></th><th>Product</th><th>Category</th><th class="num">Price</th><th class="num">Stock</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
      <tr>
        <td class="sel"><input type="checkbox" name="ids[]" value="<?= $p['id'] ?>" form="bulkForm" class="sel-box" aria-label="Select <?= e($p['name']) ?>"></td>
        <td><span class="thumb"><?php if ($p['image']): ?><img src="<?= e(img_url($p['image'])) ?>" alt="" loading="lazy"><?php endif; ?></span></td>
        <td><a href="product-edit.php?id=<?= $p['id'] ?>"><strong><?= e($p['name']) ?></strong></a><br><small class="muted"><?= e($p['brand']) ?> · <?= $p['sku'] !== '' ? 'SKU ' . e($p['sku']) : '#' . $p['id'] ?></small></td>
        <td><?= e($p['cat_name']) ?><br><small class="muted"><?= e($p['sub_name']) ?></small></td>
        <td class="num"><?= money($p['price']) ?><?php if ($p['old_price']): ?><br><small class="muted"><s><?= money($p['old_price']) ?></s></small><?php endif; ?></td>
        <td class="num"><?= $p['stock'] > 0 ? (int)$p['stock'] : badge('out', 'Out') ?></td>
        <td><?= $p['is_active'] ? badge('active', 'Visible') : badge('hidden', 'Hidden') ?><?= $p['is_new'] ? ' ' . badge('new', 'New') : '' ?></td>
        <td class="actions">
          <a href="product-edit.php?id=<?= $p['id'] ?>" class="icon-btn" title="Edit"><i class="fa-solid fa-pen"></i></a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="toggle">
            <button class="icon-btn" title="<?= $p['is_active'] ? 'Hide from store' : 'Show in store' ?>"><i class="fa-solid fa-eye<?= $p['is_active'] ? '-slash' : '' ?>"></i></button></form>
          <form method="post" onsubmit="return confirm('Delete this product permanently?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="delete">
            <button class="icon-btn danger" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="muted center">No products found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <?php if ($total > $perPage): ?>
  <nav class="pager">
    <?php for ($i = 1; $i <= ceil($total / $perPage); $i++): ?>
      <a href="?<?= e(http_build_query(['q' => $q, 'cat' => $cat ?: null, 'stock' => $stock ?: null, 'page' => $i])) ?>" class="<?= $i === $page ? 'on' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
</section>

<?php $allCount = (int)query('SELECT COUNT(*) FROM products')->fetchColumn(); ?>
<?php if ($allCount): ?>
<section class="card danger-zone">
  <h2><i class="fa-solid fa-triangle-exclamation"></i> Delete All Products</h2>
  <p class="muted">Permanently deletes all <strong><?= $allCount ?></strong> products and their uploaded images. Categories, customers and past orders are kept. This cannot be undone.</p>
  <form method="post" class="filters-row" onsubmit="return window.confirm('Delete ALL <?= $allCount ?> products permanently?')">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete_all">
    <input name="confirm" placeholder='Type DELETE to confirm' autocomplete="off" required pattern="DELETE" title="Type DELETE in capitals">
    <button class="btn danger-btn"><i class="fa-solid fa-trash"></i> Delete All Products</button>
  </form>
</section>
<?php endif; ?>

<script>
  const boxes = [...document.querySelectorAll('.sel-box')];
  const selAll = document.getElementById('selAll');
  const updateCount = () => {
    const n = boxes.filter(b => b.checked).length;
    document.getElementById('selCount').textContent = n;
    selAll.checked = n > 0 && n === boxes.length;
    selAll.indeterminate = n > 0 && n < boxes.length;
  };
  selAll.addEventListener('change', () => { boxes.forEach(b => { b.checked = selAll.checked; }); updateCount(); });
  boxes.forEach(b => b.addEventListener('change', updateCount));
  function confirmBulk(e) {
    const action = e.target.elements.namedItem('action').value;
    const n = boxes.filter(b => b.checked).length;
    if (!action) { alert('Choose a bulk action.'); return false; }
    if (!n) { alert('Select at least one product.'); return false; }
    return action !== 'bulk_delete' || confirm(`Delete ${n} selected product(s) permanently?`);
  }
</script>
<?php admin_footer();
