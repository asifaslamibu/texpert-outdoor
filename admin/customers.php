<?php
require_once __DIR__ . '/_init.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $c = query('SELECT * FROM customers WHERE id = ?', [$id])->fetch();
    if (!$c) { flash('Customer not found.', 'err'); redirect('customers.php'); }
    $orders = query('SELECT id, order_no, created_at, total, status, payment_status FROM orders WHERE customer_id = ? OR email = ? ORDER BY id DESC',
        [$id, $c['email']])->fetchAll();

    admin_header($c['first_name'] . ' ' . $c['last_name'], 'customers');
    ?>
    <p class="back"><a href="customers.php"><i class="fa-solid fa-arrow-left"></i> All customers</a></p>
    <div class="grid-form">
      <section class="card">
        <div class="card-head"><h2>Orders</h2><span class="muted"><?= count($orders) ?> total · <?= money(array_sum(array_column($orders, 'total'))) ?></span></div>
        <?php if (!$orders): ?><p class="muted">No orders yet.</p><?php else: ?>
        <table class="table">
          <thead><tr><th>Order</th><th>Date</th><th class="num">Total</th><th>Payment</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
            <tr class="clickable" onclick="location='order.php?id=<?= $o['id'] ?>'">
              <td><a href="order.php?id=<?= $o['id'] ?>"><?= e($o['order_no']) ?></a></td>
              <td><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
              <td class="num"><?= money($o['total']) ?></td>
              <td><?= badge($o['payment_status']) ?></td>
              <td><?= badge($o['status'], ORDER_STATUSES[$o['status']]) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </section>
      <aside class="card stack">
        <h2>Details</h2>
        <p><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><br><?= $c['phone'] ? '<a href="tel:' . e($c['phone']) . '">' . e($c['phone']) . '</a>' : '<span class="muted">No phone</span>' ?></p>
        <h2>Address</h2>
        <p><?= $c['address'] ? e($c['address']) . '<br>' . e("{$c['city']}, {$c['state']} {$c['zip']}") : '<span class="muted">Not saved yet</span>' ?></p>
        <p class="muted small">Customer since <?= e(date('M j, Y', strtotime($c['created_at']))) ?></p>
      </aside>
    </div>
    <?php
    admin_footer();
    exit;
}

$q = trim($_GET['q'] ?? '');
$params = [];
$where = '1 = 1';
if ($q !== '') { $where = '(email LIKE ? OR CONCAT(first_name, " ", last_name) LIKE ? OR phone LIKE ?)'; $params = ["%$q%", "%$q%", "%$q%"]; }
$rows = query("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS orders,
                      (SELECT COALESCE(SUM(total), 0) FROM orders o WHERE o.customer_id = c.id AND o.status <> 'cancelled') AS spent
               FROM customers c WHERE $where ORDER BY c.id DESC LIMIT 200", $params)->fetchAll();

admin_header('Customers', 'customers');
?>
<div class="toolbar">
  <form class="filters-row" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email or phone">
    <button class="btn btn-light">Search</button>
  </form>
</div>
<section class="card flush">
  <p class="card-meta"><?= count($rows) ?> customer<?= count($rows) === 1 ? '' : 's' ?> with accounts</p>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th class="num">Orders</th><th class="num">Spent</th><th>Joined</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): ?>
      <tr class="clickable" onclick="location='customers.php?id=<?= $c['id'] ?>'">
        <td><a href="customers.php?id=<?= $c['id'] ?>"><strong><?= e($c['first_name'] . ' ' . $c['last_name']) ?></strong></a></td>
        <td><?= e($c['email']) ?></td>
        <td><?= e($c['phone']) ?></td>
        <td class="num"><?= (int)$c['orders'] ?></td>
        <td class="num"><?= money($c['spent']) ?></td>
        <td><?= e(date('M j, Y', strtotime($c['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="muted center">No customer accounts yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
<?php admin_footer();
