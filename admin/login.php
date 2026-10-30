<?php
require_once __DIR__ . '/_init.php';

if (current_admin()) redirect('index.php');

$error = '';
if (is_post()) {
    check_csrf();
    // Simple brute-force brake: 5 failures locks this session for 15 minutes
    $fails = array_filter($_SESSION['admin_fails'] ?? [], fn($t) => $t > time() - 900);
    if (count($fails) >= 5) {
        $error = 'Too many failed attempts. Please wait 15 minutes.';
    } else {
        $admin = query('SELECT id, password_hash FROM admins WHERE email = ?', [trim($_POST['email'] ?? '')])->fetch();
        if ($admin && password_verify((string)($_POST['password'] ?? ''), $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            unset($_SESSION['admin_fails']);
            redirect('index.php');
        }
        $fails[] = time();
        $_SESSION['admin_fails'] = $fails;
        $error = 'Email or password is incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Sign In | Texpert Admin</title>
  <link rel="icon" type="image/png" href="../assets/images/favicon-01.png">
  <link rel="stylesheet" href="admin.css?v=2">
</head>
<body class="auth-page">
  <main class="auth-card">
    <img src="../assets/images/logo.png" alt="Texpert Outdoors" class="auth-logo">
    <h1>Admin Sign In</h1>
    <?php if ($error): ?><p class="alert alert-err"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <label>Email<input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>"></label>
      <label>Password<input type="password" name="password" required></label>
      <button class="btn btn-block">Sign In</button>
    </form>
  </main>
</body>
</html>
