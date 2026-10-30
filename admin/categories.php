<?php
require_once __DIR__ . '/_init.php';
require_admin();

if (is_post()) {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    try {
        switch ($action) {
            case 'add_cat':
                if ($name === '') throw new DomainException('Enter a category name.');
                $short = trim($_POST['short'] ?? '') ?: $name;
                $icon = preg_replace('/[^a-z0-9-]/', '', $_POST['icon'] ?? '') ?: 'fa-tag';
                $next = (int)query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories')->fetchColumn();
                query('INSERT INTO categories (name, short_name, slug, icon, sort_order) VALUES (?, ?, ?, ?, ?)',
                    [$name, $short, slugify($name), $icon, $next]);
                flash("Category \"$name\" added.");
                break;
            case 'edit_cat':
                if ($name === '') throw new DomainException('Enter a category name.');
                $icon = preg_replace('/[^a-z0-9-]/', '', $_POST['icon'] ?? '') ?: 'fa-tag';
                query('UPDATE categories SET name = ?, short_name = ?, slug = ?, icon = ?, sort_order = ? WHERE id = ?',
                    [$name, trim($_POST['short'] ?? '') ?: $name, slugify($name), $icon, (int)($_POST['sort'] ?? 0), (int)$_POST['id']]);
                flash('Category saved.');
                break;
            case 'delete_cat':
                $count = (int)query('SELECT COUNT(*) FROM products p JOIN subcategories s ON s.id = p.subcategory_id WHERE s.category_id = ?', [(int)$_POST['id']])->fetchColumn();
                if ($count) throw new DomainException("This category still has $count product(s). Move or delete them first.");
                query('DELETE FROM categories WHERE id = ?', [(int)$_POST['id']]);
                flash('Category deleted.');
                break;
            case 'add_sub':
                if ($name === '') throw new DomainException('Enter a subcategory name.');
                $catId = (int)$_POST['category_id'];
                $next = (int)query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM subcategories WHERE category_id = ?', [$catId])->fetchColumn();
                query('INSERT INTO subcategories (category_id, name, slug, sort_order) VALUES (?, ?, ?, ?)', [$catId, $name, slugify($name), $next]);
                flash("Subcategory \"$name\" added.");
                break;
            case 'edit_sub':
                if ($name === '') throw new DomainException('Enter a subcategory name.');
                query('UPDATE subcategories SET name = ?, slug = ? WHERE id = ?', [$name, slugify($name), (int)$_POST['id']]);
                flash('Subcategory renamed.');
                break;
            case 'delete_sub':
                $count = (int)query('SELECT COUNT(*) FROM products WHERE subcategory_id = ?', [(int)$_POST['id']])->fetchColumn();
                if ($count) throw new DomainException("This subcategory still has $count product(s). Move or delete them first.");
                query('DELETE FROM subcategories WHERE id = ?', [(int)$_POST['id']]);
                flash('Subcategory deleted.');
                break;
        }
    } catch (DomainException $ex) {
        flash($ex->getMessage(), 'err');
    } catch (PDOException $ex) {
        flash($ex->getCode() === '23000' ? 'That name is already used.' : 'Could not save: ' . $ex->getMessage(), 'err');
    }
    redirect('categories.php');
}

$cats = query('SELECT * FROM categories ORDER BY sort_order, id')->fetchAll();
$subs = query('SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.subcategory_id = s.id) AS products FROM subcategories s ORDER BY sort_order, id')->fetchAll();
$subsByCat = [];
foreach ($subs as $s) $subsByCat[$s['category_id']][] = $s;

admin_header('Categories', 'categories');
?>
<section class="card">
  <h2>Add Category</h2>
  <form method="post" class="filters-row">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_cat">
    <input name="name" placeholder="Category name" required>
    <input name="short" placeholder="Short menu name (optional)">
    <input name="icon" placeholder="Icon, e.g. fa-fish">
    <button class="btn">Add</button>
  </form>
  <p class="muted small">Icons use Font Awesome names (<a href="https://fontawesome.com/search?o=r&m=free&s=solid" target="_blank" rel="noopener">browse icons</a>). Categories without products are hidden from the store automatically.</p>
</section>

<?php foreach ($cats as $c): $list = $subsByCat[$c['id']] ?? []; $total = array_sum(array_column($list, 'products')); ?>
<section class="card cat-card">
  <form method="post" class="filters-row cat-edit">
    <?= csrf_field() ?><input type="hidden" name="action" value="edit_cat"><input type="hidden" name="id" value="<?= $c['id'] ?>">
    <i class="fa-solid <?= e($c['icon']) ?> cat-icon"></i>
    <input name="name" value="<?= e($c['name']) ?>" required aria-label="Name">
    <input name="short" value="<?= e($c['short_name']) ?>" aria-label="Short name" title="Short menu name">
    <input name="icon" value="<?= e($c['icon']) ?>" aria-label="Icon" class="narrow">
    <input name="sort" type="number" value="<?= (int)$c['sort_order'] ?>" aria-label="Order" title="Menu order" class="tiny">
    <button class="btn btn-light">Save</button>
    <span class="muted"><?= $total ?> products</span>
  </form>
  <form method="post" onsubmit="return confirm('Delete this category and its empty subcategories?')" class="cat-del">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete_cat"><input type="hidden" name="id" value="<?= $c['id'] ?>">
    <button class="icon-btn danger" title="Delete category"><i class="fa-solid fa-trash"></i></button>
  </form>

  <ul class="sub-list">
    <?php foreach ($list as $s): ?>
      <li>
        <form method="post" class="filters-row">
          <?= csrf_field() ?><input type="hidden" name="action" value="edit_sub"><input type="hidden" name="id" value="<?= $s['id'] ?>">
          <input name="name" value="<?= e($s['name']) ?>" required aria-label="Subcategory name">
          <button class="btn btn-light btn-sm">Rename</button>
          <a class="muted" href="products.php?cat=<?= $c['id'] ?>"><?= (int)$s['products'] ?> products</a>
        </form>
        <form method="post" onsubmit="return confirm('Delete this subcategory?')">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete_sub"><input type="hidden" name="id" value="<?= $s['id'] ?>">
          <button class="icon-btn danger" title="Delete subcategory"><i class="fa-solid fa-xmark"></i></button>
        </form>
      </li>
    <?php endforeach; ?>
    <li>
      <form method="post" class="filters-row">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_sub"><input type="hidden" name="category_id" value="<?= $c['id'] ?>">
        <input name="name" placeholder="New subcategory" required>
        <button class="btn btn-light btn-sm"><i class="fa-solid fa-plus"></i> Add</button>
      </form>
    </li>
  </ul>
</section>
<?php endforeach; ?>
<?php admin_footer();
