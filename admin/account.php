<?php
require_once __DIR__ . '/_init.php';
$me = require_admin();

if (is_post()) {
    check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'password') {
        $hash = query('SELECT password_hash FROM admins WHERE id = ?', [$me['id']])->fetchColumn();
        if (!password_verify((string)($_POST['current'] ?? ''), $hash)) flash('Current password is incorrect.', 'err');
        elseif (strlen((string)($_POST['new'] ?? '')) < 10) flash('New password must be at least 10 characters.', 'err');
        else {
            query('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash((string)$_POST['new'], PASSWORD_DEFAULT), $me['id']]);
            flash('Password changed.');
        }
    }
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = (string)($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 10) {
            flash('Enter a name, valid email and a password of at least 10 characters.', 'err');
        } elseif (query('SELECT 1 FROM admins WHERE email = ?', [$email])->fetchColumn()) {
            flash('An admin with this email already exists.', 'err');
        } else {
            query('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            flash("Admin $name added.");
        }
    }
    if ($action === 'remove') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$me['id']) flash('You cannot remove your own account.', 'err');
        else { query('DELETE FROM admins WHERE id = ?', [$id]); flash('Admin removed.'); }
    }
    redirect('account.php');
}

$admins = query('SELECT id, name, email, created_at FROM admins ORDER BY id')->fetchAll();

admin_header('Admin Settings', 'account');
?>
<div class="grid-2">
  <form method="post" class="card stack">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h2>Change Your Password</h2>
    <label>Current Password<input type="password" name="current" required></label>
    <label>New Password <small>(10+ characters)</small><input type="password" name="new" minlength="10" required></label>
    <button class="btn">Change Password</button>
  </form>

  <section class="card stack">
    <h2>Admin Users</h2>
    <table class="table">
      <tbody>
      <?php foreach ($admins as $a): ?>
        <tr>
          <td><strong><?= e($a['name']) ?></strong><?= (int)$a['id'] === (int)$me['id'] ? ' <span class="muted small">(you)</span>' : '' ?><br><small class="muted"><?= e($a['email']) ?></small></td>
          <td class="actions"><?php if ((int)$a['id'] !== (int)$me['id']): ?>
            <form method="post" onsubmit="return confirm('Remove this admin?')"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= $a['id'] ?>">
              <button class="icon-btn danger" title="Remove"><i class="fa-solid fa-trash"></i></button></form>
          <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <h3>Add Admin</h3>
      <div class="row-2">
        <label>Name<input name="name" required></label>
        <label>Email<input type="email" name="email" required></label>
      </div>
      <label>Password <small>(10+ characters)</small><input type="password" name="password" minlength="10" required></label>
      <button class="btn btn-light">Add Admin</button>
    </form>
  </section>
</div>

<section class="card stack">
  <h2>Payments (Stripe)</h2>
  <p><?= stripe_enabled() ? badge('paid', 'Card payments on') : badge('unpaid', 'Card payments off') ?></p>
  <p class="muted small">Stripe keys are set in <code>includes/config.php</code> on the server. Webhook URL for the Stripe dashboard:
    <code><?= e(site_url()) ?>/api/stripe-webhook.php</code> (event: <code>checkout.session.completed</code>).</p>
</section>
<?php admin_footer();
