<?php
require_once __DIR__ . '/_init.php';
require_admin();

if (is_post()) {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'toggle') {
        query('UPDATE products SET is_active = 1 - is_active WHERE id = ?', [$id]);
        flash('Product visibility updated.');
    }
    if (($_POST['action'] ?? '') === 'delete') {
        $img = (string)query('SELECT image FROM products WHERE id = ?', [$id])->fetchColumn();
        query('DELETE FROM products WHERE id = ?', [$id]);
        if (str_starts_with($img, 'uploads/products/')) @unlink(APP_ROOT . '/' . $img);
        flash('Product deleted.');
    }
    redirect('products.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'cat', 'stock', 'page']))));
}

$q = trim($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);
$stock = $_GET['stock'] ?? '';
$where = ['1 = 1'];
$params = [];
if ($q !== '') { $where[] = '(p.name LIKE ? OR p.brand LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
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
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or brand">
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
  <a href="product-edit.php" class="btn"><i class="fa-solid fa-plus"></i> Add Product</a>
</div>

<section class="card flush">
  <p class="card-meta"><?= $total ?> product<?= $total === 1 ? '' : 's' ?></p>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th></th><th>Product</th><th>Category</th><th class="num">Price</th><th class="num">Stock</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
      <tr>
        <td><span class="thumb"><?php if ($p['image']): ?><img src="<?= e(img_url($p['image'])) ?>" alt="" loading="lazy"><?php endif; ?></span></td>
        <td><a href="product-edit.php?id=<?= $p['id'] ?>"><strong><?= e($p['name']) ?></strong></a><br><small class="muted"><?= e($p['brand']) ?> · #<?= $p['id'] ?></small></td>
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
    <?php if (!$rows): ?><tr><td colspan="7" class="muted center">No products found.</td></tr><?php endif; ?>
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
<?php admin_footer();
