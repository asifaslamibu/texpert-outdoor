<?php
// Shared admin setup: authentication, CSRF, flash messages and page layout.

require_once __DIR__ . '/../includes/bootstrap.php';

start_session();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) return null;
    static $admin = false;
    if ($admin === false) $admin = query('SELECT id, name, email FROM admins WHERE id = ?', [$_SESSION['admin_id']])->fetch() ?: null;
    return $admin;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        header('Location: login.php');
        exit;
    }
    return $admin;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

// Call at the top of every POST handler.
function check_csrf(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

function flash(?string $message = null, string $type = 'ok')
{
    if ($message !== null) {
        $_SESSION['flash'] = [$message, $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function badge(string $value, string $label = ''): string
{
    return '<span class="badge badge-' . e($value) . '">' . e($label ?: ucwords(str_replace('_', ' ', $value))) . '</span>';
}

// Product images are stored relative to the site root.
function img_url(string $path): string
{
    return $path === '' ? '' : '../' . ltrim($path, '/');
}

function admin_header(string $title, string $active): void
{
    $admin = current_admin();
    $newMessages = (int)query("SELECT COUNT(*) FROM messages WHERE status = 'new'")->fetchColumn();
    $newOrders = (int)query("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn();
    $nav = [
        'index' => ['Dashboard', 'fa-gauge', 0],
        'orders' => ['Orders', 'fa-receipt', $newOrders],
        'products' => ['Products', 'fa-box', 0],
        'categories' => ['Categories', 'fa-sitemap', 0],
        'customers' => ['Customers', 'fa-users', 0],
        'messages' => ['Contact Queries', 'fa-envelope', $newMessages],
        'subscribers' => ['Subscribers', 'fa-bullhorn', 0],
        'account' => ['Admin Settings', 'fa-gear', 0],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?> | Texpert Admin</title>
  <link rel="icon" type="image/png" href="../assets/images/favicon-01.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="admin.css?v=2">
</head>
<body>
  <aside class="side" id="side">
    <a href="index.php" class="side-logo"><img src="../assets/images/logo-white.png" alt="Texpert Outdoors"></a>
    <nav>
      <?php foreach ($nav as $key => [$label, $icon, $count]): ?>
        <a href="<?= $key ?>.php" class="<?= $key === $active ? 'active' : '' ?>">
          <i class="fa-solid <?= $icon ?>"></i> <?= e($label) ?>
          <?php if ($count): ?><em><?= $count ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="../index.html" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> View Store</a>
      <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="menu-btn" onclick="document.getElementById('side').classList.toggle('open')" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
      <h1><?= e($title) ?></h1>
      <span class="who"><i class="fa-regular fa-circle-user"></i> <?= e($admin['name'] ?? '') ?></span>
    </header>
    <main class="content">
      <?php if ($f = flash()): ?><p class="alert alert-<?= e($f[1]) ?>"><?= e($f[0]) ?></p><?php endif; ?>
<?php
}

function admin_footer(): void
{
    ?>
    </main>
  </div>
</body>
</html>
<?php
}
