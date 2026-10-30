<?php
require_once __DIR__ . '/_init.php';
require_admin();

$status = $_GET['status'] ?? '';
$payment = $_GET['payment'] ?? '';
$q = trim($_GET['q'] ?? '');
$where = ['1 = 1'];
$params = [];
if (isset(ORDER_STATUSES[$status])) { $where[] = 'status = ?'; $params[] = $status; }
if (in_array($payment, ['unpaid', 'pending', 'paid', 'refunded', 'failed'], true)) { $where[] = 'payment_status = ?'; $params[] = $payment; }
if ($q !== '') {
    $where[] = '(order_no LIKE ? OR email LIKE ? OR CONCAT(first_name, " ", last_name) LIKE ? OR phone LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
$whereSql = implode(' AND ', $where);

$perPage = 30;
$total = (int)query("SELECT COUNT(*) FROM orders WHERE $whereSql", $params)->fetchColumn();
$page = max(1, min((int)($_GET['page'] ?? 1), (int)ceil(max($total, 1) / $perPage)));
$rows = query("SELECT o.*, (SELECT SUM(qty) FROM order_items i WHERE i.order_id = o.id) AS units FROM orders o
               WHERE $whereSql ORDER BY id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params)->fetchAll();
$counts = query('SELECT status, COUNT(*) n FROM orders GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);

admin_header('Orders', 'orders');
?>
<nav class="tabs">
  <a href="orders.php" class="<?= $status === '' ? 'on' : '' ?>">All <em><?= array_sum($counts) ?></em></a>
  <?php foreach (ORDER_STATUSES as $key => $label): ?>
    <a href="?status=<?= $key ?>" class="<?= $status === $key ? 'on' : '' ?>"><?= e($label) ?> <em><?= (int)($counts[$key] ?? 0) ?></em></a>
  <?php endforeach; ?>
</nav>

<div class="toolbar">
  <form class="filters-row" method="get">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Order no, name, email or phone">
    <select name="payment" onchange="this.form.submit()">
      <option value="">Any payment</option>
      <?php foreach (['unpaid', 'pending', 'paid', 'refunded', 'failed'] as $p): ?><option <?= $payment === $p ? 'selected' : '' ?>><?= $p ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-light">Search</button>
  </form>
</div>

<section class="card flush">
  <p class="card-meta"><?= $total ?> order<?= $total === 1 ? '' : 's' ?></p>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Delivery</th><th class="num">Items</th><th class="num">Total</th><th>Payment</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $o): ?>
      <tr class="clickable" onclick="location='order.php?id=<?= $o['id'] ?>'">
        <td><a href="order.php?id=<?= $o['id'] ?>"><strong><?= e($o['order_no']) ?></strong></a></td>
        <td><?= e(date('M j, Y g:ia', strtotime($o['created_at']))) ?></td>
        <td><?= e($o['first_name'] . ' ' . $o['last_name']) ?><br><small class="muted"><?= e($o['email']) ?></small></td>
        <td><?= $o['delivery'] === 'pickup' ? 'Pickup' : 'Ship' ?></td>
        <td class="num"><?= (int)$o['units'] ?></td>
        <td class="num"><?= money($o['total']) ?></td>
        <td><?= badge($o['payment_status']) ?><br><small class="muted"><?= $o['payment_method'] === 'card' ? 'Card' : 'Pay later' ?></small></td>
        <td><?= badge($o['status'], ORDER_STATUSES[$o['status']]) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="muted center">No orders found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <?php if ($total > $perPage): ?>
  <nav class="pager">
    <?php for ($i = 1; $i <= ceil($total / $perPage); $i++): ?>
      <a href="?<?= e(http_build_query(['status' => $status ?: null, 'payment' => $payment ?: null, 'q' => $q ?: null, 'page' => $i])) ?>" class="<?= $i === $page ? 'on' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
</section>
<?php admin_footer();
