<?php
require_once __DIR__ . '/_init.php';
require_admin();

$stats = [
    ['Orders Today', (int)query('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()')->fetchColumn(), 'fa-receipt', 'orders.php'],
    ['New Orders', (int)query("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn(), 'fa-bell', 'orders.php?status=new'],
    ['Revenue (30 days, paid)', money(query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'paid' AND created_at >= NOW() - INTERVAL 30 DAY")->fetchColumn()), 'fa-dollar-sign', 'orders.php?payment=paid'],
    ['New Contact Queries', (int)query("SELECT COUNT(*) FROM messages WHERE status = 'new'")->fetchColumn(), 'fa-envelope', 'messages.php'],
    ['Active Products', (int)query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(), 'fa-box', 'products.php'],
    ['Customers', (int)query('SELECT COUNT(*) FROM customers')->fetchColumn(), 'fa-users', 'customers.php'],
];
$recent = query('SELECT id, order_no, first_name, last_name, total, status, payment_status, created_at FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
$lowStock = query('SELECT id, name, stock FROM products WHERE is_active = 1 AND stock <= 3 ORDER BY stock, name LIMIT 8')->fetchAll();

admin_header('Dashboard', 'index');
?>
<div class="stats">
  <?php foreach ($stats as [$label, $value, $icon, $link]): ?>
    <a href="<?= $link ?>" class="stat"><i class="fa-solid <?= $icon ?>"></i><strong><?= e($value) ?></strong><span><?= e($label) ?></span></a>
  <?php endforeach; ?>
</div>

<div class="grid-2">
  <section class="card">
    <div class="card-head"><h2>Recent Orders</h2><a href="orders.php">View all</a></div>
    <?php if (!$recent): ?><p class="muted">No orders yet.</p><?php else: ?>
    <table class="table">
      <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Payment</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): ?>
        <tr onclick="location='order.php?id=<?= $o['id'] ?>'" class="clickable">
          <td><a href="order.php?id=<?= $o['id'] ?>"><?= e($o['order_no']) ?></a><br><small class="muted"><?= e(date('M j, g:ia', strtotime($o['created_at']))) ?></small></td>
          <td><?= e($o['first_name'] . ' ' . $o['last_name']) ?></td>
          <td><?= money($o['total']) ?></td>
          <td><?= badge($o['status'], ORDER_STATUSES[$o['status']]) ?></td>
          <td><?= badge($o['payment_status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h2>Low Stock</h2><a href="products.php?stock=low">View all</a></div>
    <?php if (!$lowStock): ?><p class="muted">All products have more than 3 in stock.</p><?php else: ?>
    <table class="table">
      <tbody>
      <?php foreach ($lowStock as $p): ?>
        <tr><td><a href="product-edit.php?id=<?= $p['id'] ?>"><?= e($p['name']) ?></a></td><td class="num"><?= $p['stock'] > 0 ? $p['stock'] . ' left' : badge('out', 'Out of stock') ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</div>
<?php admin_footer();
