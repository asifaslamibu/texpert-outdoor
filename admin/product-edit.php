<?php
require_once __DIR__ . '/_init.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$product = $id ? query('SELECT * FROM products WHERE id = ?', [$id])->fetch() : null;
if ($id && !$product) { flash('Product not found.', 'err'); redirect('products.php'); }

$defaults = ['name' => '', 'brand' => '', 'subcategory_id' => 0, 'description' => '', 'price' => '', 'old_price' => '', 'stock' => 10,
             'rating' => 0, 'reviews' => 0, 'image' => '', 'is_new' => 0, 'is_active' => 1];
$data = $product ?: $defaults;
$errors = [];

// Saves an uploaded image into uploads/products and returns its site-relative path.
function save_upload(array $file, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Image upload failed. Try a smaller file.'; return null; }
    if ($file['size'] > 5 * 1024 * 1024) { $errors[] = 'Image must be 5 MB or smaller.'; return null; }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($types[$mime]) || !getimagesize($file['tmp_name'])) { $errors[] = 'Image must be a JPG, PNG or WebP file.'; return null; }
    $dir = APP_ROOT . '/uploads/products';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(8)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) { $errors[] = 'Could not save the image.'; return null; }
    return "uploads/products/$name";
}

if (is_post()) {
    check_csrf();
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'brand' => trim($_POST['brand'] ?? ''),
        'subcategory_id' => (int)($_POST['subcategory_id'] ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'price' => trim($_POST['price'] ?? ''),
        'old_price' => trim($_POST['old_price'] ?? ''),
        'stock' => (int)($_POST['stock'] ?? 0),
        'rating' => min(5, max(0, round((float)($_POST['rating'] ?? 0), 1))),
        'reviews' => max(0, (int)($_POST['reviews'] ?? 0)),
        'image' => $product['image'] ?? '',
        'is_new' => isset($_POST['is_new']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    if ($data['name'] === '') $errors[] = 'Name is required.';
    if (!query('SELECT 1 FROM subcategories WHERE id = ?', [$data['subcategory_id']])->fetchColumn()) $errors[] = 'Choose a category.';
    if (!is_numeric($data['price']) || (float)$data['price'] <= 0) $errors[] = 'Price must be a number above 0.';
    if ($data['old_price'] !== '' && (!is_numeric($data['old_price']) || (float)$data['old_price'] <= (float)$data['price'])) {
        $errors[] = 'Compare-at (old) price must be higher than the price, or left empty.';
    }
    if ($data['stock'] < 0) $errors[] = 'Stock cannot be negative.';

    $uploaded = save_upload($_FILES['image'] ?? [], $errors);
    if (!$errors) {
        $oldImage = $data['image'];
        if ($uploaded) $data['image'] = $uploaded;
        if (isset($_POST['remove_image']) && !$uploaded) $data['image'] = '';
        $values = [$data['subcategory_id'], $data['name'], slugify($data['name']), $data['brand'], $data['description'] ?: null,
                   (float)$data['price'], $data['old_price'] !== '' ? (float)$data['old_price'] : null, $data['stock'], $data['rating'],
                   $data['reviews'], $data['image'], $data['is_new'], $data['is_active']];
        if ($product) {
            query('UPDATE products SET subcategory_id = ?, name = ?, slug = ?, brand = ?, description = ?, price = ?, old_price = ?, stock = ?,
                   rating = ?, reviews = ?, image = ?, is_new = ?, is_active = ? WHERE id = ?', [...$values, $id]);
        } else {
            query('INSERT INTO products (subcategory_id, name, slug, brand, description, price, old_price, stock, rating, reviews, image, is_new, is_active)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $values);
            $id = (int)db()->lastInsertId();
        }
        // Remove a replaced upload (never the original stock images)
        if ($oldImage !== $data['image'] && str_starts_with($oldImage, 'uploads/products/')) @unlink(APP_ROOT . '/' . $oldImage);
        flash($product ? 'Product saved.' : 'Product added.');
        redirect('product-edit.php?id=' . $id);
    } elseif ($uploaded) {
        @unlink(APP_ROOT . '/' . $uploaded);
    }
}

$groups = query('SELECT s.id, s.name, c.name AS cat FROM subcategories s JOIN categories c ON c.id = s.category_id ORDER BY c.sort_order, c.id, s.sort_order, s.id')->fetchAll();

admin_header($product ? 'Edit Product' : 'Add Product', 'products');
?>
<p class="back"><a href="products.php"><i class="fa-solid fa-arrow-left"></i> All products</a>
  <?php if ($product): ?> · <a href="../product.html?id=<?= $id ?>" target="_blank">View in store <i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?></p>
<?php if ($errors): ?><div class="alert alert-err"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="grid-form">
  <?= csrf_field() ?>
  <section class="card stack">
    <label>Product Name<input name="name" required value="<?= e($data['name']) ?>"></label>
    <div class="row-2">
      <label>Brand<input name="brand" value="<?= e($data['brand']) ?>"></label>
      <label>Category
        <select name="subcategory_id" required>
          <option value="">Choose…</option>
          <?php $last = null; foreach ($groups as $g): ?>
            <?php if ($g['cat'] !== $last): ?><?= $last !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($g['cat']) ?>"><?php $last = $g['cat']; endif; ?>
            <option value="<?= $g['id'] ?>" <?= (int)$data['subcategory_id'] === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
          <?php endforeach; ?></optgroup>
        </select>
      </label>
    </div>
    <label>Description <small>(shown on the product page; leave empty for an automatic one)</small>
      <textarea name="description" rows="7"><?= e($data['description']) ?></textarea></label>
    <div class="row-3">
      <label>Price ($)<input name="price" type="number" step="0.01" min="0" required value="<?= e($data['price']) ?>"></label>
      <label>Compare-at Price ($) <small>(for sales)</small><input name="old_price" type="number" step="0.01" min="0" value="<?= e($data['old_price']) ?>"></label>
      <label>Stock<input name="stock" type="number" min="0" required value="<?= e($data['stock']) ?>"></label>
    </div>
    <div class="row-2">
      <label>Rating (0–5)<input name="rating" type="number" step="0.1" min="0" max="5" value="<?= e($data['rating']) ?>"></label>
      <label>Review Count<input name="reviews" type="number" min="0" value="<?= e($data['reviews']) ?>"></label>
    </div>
  </section>

  <aside class="stack">
    <section class="card stack">
      <h2>Image</h2>
      <div class="img-preview"><?php if ($data['image']): ?><img src="<?= e(img_url($data['image'])) ?>" alt=""><?php else: ?><span class="muted">No image</span><?php endif; ?></div>
      <label>Upload new image <small>(JPG, PNG or WebP, max 5 MB)</small><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
      <?php if ($data['image']): ?><label class="check"><input type="checkbox" name="remove_image"> Remove current image</label><?php endif; ?>
    </section>
    <section class="card stack">
      <h2>Visibility</h2>
      <label class="check"><input type="checkbox" name="is_active" <?= $data['is_active'] ? 'checked' : '' ?>> Visible in store</label>
      <label class="check"><input type="checkbox" name="is_new" <?= $data['is_new'] ? 'checked' : '' ?>> Mark as "New"</label>
    </section>
    <button class="btn btn-block"><?= $product ? 'Save Changes' : 'Add Product' ?></button>
  </aside>
</form>
<?php admin_footer();
