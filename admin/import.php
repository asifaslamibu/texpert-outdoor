<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../includes/importer.php';
require_admin();

$result = null;
$error = '';
if (is_post()) {
    check_csrf();
    $file = $_FILES['csv'] ?? [];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Choose a CSV file to import.';
    } elseif (!preg_match('/\.csv$/i', (string)$file['name'])) {
        $error = 'The file must be a .csv file. In Excel use File → Save As → CSV UTF-8.';
    } else {
        $result = import_products_csv($file['tmp_name'], [
            'download_images' => isset($_POST['download_images']),
            'image_base' => trim($_POST['image_base'] ?? ''),
        ]);
    }
}

admin_header('Import Products', 'products');
?>
<p class="back"><a href="products.php"><i class="fa-solid fa-arrow-left"></i> All products</a></p>
<?php if ($error): ?><p class="alert alert-err"><?= e($error) ?></p><?php endif; ?>

<?php if ($result): ?>
<section class="card stack">
  <h2>Import finished</h2>
  <div class="stats">
    <div class="stat"><strong><?= $result['added'] ?></strong><span>Added</span></div>
    <div class="stat"><strong><?= $result['updated'] ?></strong><span>Updated</span></div>
    <div class="stat"><strong><?= $result['duplicates'] + $result['skipped'] ?></strong><span>Skipped</span></div>
    <div class="stat"><strong><?= $result['images_downloaded'] ?></strong><span>Images downloaded</span></div>
  </div>
  <p class="muted">
    <?= $result['duplicates'] ?> duplicate row(s) in the file were ignored.
    <?php if ($result['categories'] || $result['sections']): ?><?= $result['categories'] ?> new categor<?= $result['categories'] === 1 ? 'y' : 'ies' ?> and <?= $result['sections'] ?> new section(s) were created.<?php endif; ?>
    <?php if ($result['images_missing']): ?><?= $result['images_missing'] ?> product image(s) could not be downloaded; those products show the category icon for now. Import the same file again to retry.<?php endif; ?>
  </p>
  <?php if ($result['errors']): ?><div class="alert alert-err"><?= implode('<br>', array_map('e', $result['errors'])) ?></div><?php endif; ?>
  <p><a href="products.php" class="btn">View Products</a></p>
</section>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card stack">
  <?= csrf_field() ?>
  <h2>Upload a CSV file</h2>
  <p class="muted">Save your Excel sheet as <strong>CSV UTF-8</strong>. Required columns: <strong>Product Title</strong>, <strong>Price</strong> and <strong>Category</strong>.
    Optional: SKU, Brand, Compare At, In Stock, Stock Qty, Published, Image, Short Description, URL.
    Categories that do not exist yet are created automatically. Products already in the store with the same title and image are updated (price, stock, category) instead of added twice.</p>
  <label>CSV file<input type="file" name="csv" accept=".csv,text/csv" required></label>
  <label class="check"><input type="checkbox" name="download_images" checked> Copy product images to this website</label>
  <label>Image website <small>(only needed when the Image column has paths like /uploads/x.jpg and there is no URL column)</small>
    <input name="image_base" placeholder="https://texpertdeals.us"></label>
  <button class="btn" onclick="this.textContent = 'Importing… this can take a few minutes'"><i class="fa-solid fa-file-import"></i> Import Products</button>
</form>
<?php admin_footer();
