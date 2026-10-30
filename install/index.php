<?php
// One-time installer: creates the tables, imports the starter catalog and the first admin.
// Open /install/?token=YOUR_INSTALL_TOKEN (the token is set in includes/config.php).

require_once __DIR__ . '/../includes/bootstrap.php';

$token = (string)config('install_token', '');
if ($token === '' || str_starts_with($token, 'change-me') || !hash_equals($token, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit('Installer locked. Set install_token in includes/config.php and open /install/?token=...');
}

$messages = [];
$error = '';

try {
    // 1. Tables
    foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__ . '/schema.sql')))) as $sql) {
        if (!str_starts_with($sql, '--') || str_contains($sql, 'CREATE')) db()->exec($sql);
    }
    $messages[] = 'Database tables are ready.';

    // 2. Starter catalog (only into an empty database)
    if ((int)query('SELECT COUNT(*) FROM categories')->fetchColumn() === 0) {
        $seed = json_decode(file_get_contents(__DIR__ . '/seed.json'), true);
        db()->beginTransaction();
        $subIds = [];
        foreach ($seed['categories'] as $cat) {
            query('INSERT INTO categories (name, short_name, slug, icon, sort_order) VALUES (?, ?, ?, ?, ?)',
                [$cat['name'], $cat['short'], $cat['slug'], $cat['icon'], $cat['sort']]);
            $catId = (int)db()->lastInsertId();
            foreach ($cat['subs'] as $sub) {
                query('INSERT INTO subcategories (category_id, name, slug, sort_order) VALUES (?, ?, ?, ?)',
                    [$catId, $sub['name'], $sub['slug'], $sub['sort']]);
                $subIds[$cat['slug'] . '/' . $sub['slug']] = (int)db()->lastInsertId();
            }
        }
        foreach ($seed['products'] as $p) {
            query('INSERT INTO products (id, subcategory_id, name, slug, brand, price, old_price, rating, reviews, image, is_new)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$p['id'], $subIds[$p['category'] . '/' . $p['sub']], $p['name'], $p['slug'], $p['brand'], $p['price'],
                 $p['old_price'] ?: null, $p['rating'], $p['reviews'], $p['image'], $p['is_new']]);
        }
        db()->commit();
        $messages[] = sprintf('Imported %d categories and %d products.', count($seed['categories']), count($seed['products']));
    } else {
        $messages[] = 'Catalog already exists, nothing imported.';
    }

    // 3. First admin
    $hasAdmin = (int)query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    if (!$hasAdmin && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = (string)($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 10) {
            $error = 'Enter a name, a valid email and a password of at least 10 characters.';
        } else {
            query('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $hasAdmin = true;
            $messages[] = 'Admin account created.';
        }
    }
} catch (Throwable $ex) {
    if (db()->inTransaction()) db()->rollBack();
    $error = 'Install failed: ' . $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>Install | Texpert Outdoors</title>
  <link rel="stylesheet" href="../admin/admin.css">
</head>
<body class="auth-page">
  <main class="auth-card">
    <img src="../assets/images/logo.png" alt="Texpert Outdoors" class="auth-logo">
    <h1>Store Setup</h1>
    <?php foreach ($messages as $m): ?><p class="alert alert-ok"><?= e($m) ?></p><?php endforeach; ?>
    <?php if ($error): ?><p class="alert alert-err"><?= e($error) ?></p><?php endif; ?>

    <?php if (!empty($hasAdmin)): ?>
      <p>Setup is complete. For safety, delete the <code>install</code> folder from the server or change <code>install_token</code>.</p>
      <a href="../admin/" class="btn btn-block">Go to Admin Panel</a>
    <?php else: ?>
      <p>Create the first admin account.</p>
      <form method="post" class="stack">
        <label>Name<input name="name" required value="<?= e($_POST['name'] ?? '') ?>"></label>
        <label>Email<input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
        <label>Password <small>(10+ characters)</small><input type="password" name="password" minlength="10" required></label>
        <button class="btn btn-block">Create Admin</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
