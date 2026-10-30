<?php
require_once __DIR__ . '/_init.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$order = query('SELECT * FROM orders WHERE id = ?', [$id])->fetch();
if (!$order) { flash('Order not found.', 'err'); redirect('orders.php'); }

if (is_post()) {
    check_csrf();
    $status = $_POST['status'] ?? $order['status'];
    $payment = $_POST['payment_status'] ?? $order['payment_status'];
    if (!isset(ORDER_STATUSES[$status])) $status = $order['status'];
    if (!in_array($payment, ['unpaid', 'pending', 'paid', 'refunded', 'failed'], true)) $payment = $order['payment_status'];

    db()->beginTransaction();
    // Put items back in stock when an order is cancelled, and take them out again if it is reopened
    if ($status !== $order['status'] && ($status === 'cancelled' || $order['status'] === 'cancelled')) {
        $sign = $status === 'cancelled' ? '+' : '-';
        foreach (query('SELECT product_id, qty FROM order_items WHERE order_id = ? AND product_id IS NOT NULL', [$id])->fetchAll() as $item) {
            query("UPDATE products SET stock = GREATEST(0, stock $sign ?) WHERE id = ?", [$item['qty'], $item['product_id']]);
        }
    }
    query('UPDATE orders SET status = ?, payment_status = ?, tracking_number = ?, admin_note = ? WHERE id = ?',
        [$status, $payment, trim($_POST['tracking_number'] ?? ''), trim($_POST['admin_note'] ?? '') ?: null, $id]);
    db()->commit();

    if (isset($_POST['notify']) && $status !== $order['status']) {
        $msg = "Hi {$order['first_name']},\n\nYour order {$order['order_no']} is now: " . ORDER_STATUSES[$status] . '.';
        if ($status === 'shipped' && trim($_POST['tracking_number'] ?? '') !== '') $msg .= "\nTracking number: " . trim($_POST['tracking_number']);
        if ($status === 'ready_for_pickup') $msg .= "\nPick it up at 2990 Congressman Ln, Dallas, TX 75220 (Mon–Fri, 9am–6pm). Please bring a photo ID.";
        notify($order['email'], "Order {$order['order_no']}: " . ORDER_STATUSES[$status], "$msg\n\nThank you,\nTexpert Outdoors\n+1-254-358-0017");
    }
    flash('Order updated.');
    redirect('order.php?id=' . $id);
}

$items = query('SELECT i.*, p.image FROM order_items i LEFT JOIN products p ON p.id = i.product_id WHERE order_id = ?', [$id])->fetchAll();

admin_header('Order ' . $order['order_no'], 'orders');
?>
<p class="back"><a href="orders.php"><i class="fa-solid fa-arrow-left"></i> All orders</a></p>

<div class="grid-form">
  <div class="stack">
    <section class="card">
      <div class="card-head">
        <h2><?= e($order['order_no']) ?></h2>
        <span><?= badge($order['status'], ORDER_STATUSES[$order['status']]) ?> <?= badge($order['payment_status']) ?></span>
      </div>
      <p class="muted">Placed <?= e(date('l, M j, Y \a\t g:ia', strtotime($order['created_at']))) ?> · <?= $order['payment_method'] === 'card' ? 'Card (Stripe)' : 'Pay later' ?></p>
      <table class="table">
        <thead><tr><th></th><th>Item</th><th class="num">Price</th><th class="num">Qty</th><th class="num">Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $i): ?>
          <tr>
            <td><span class="thumb"><?php if ($i['image']): ?><img src="<?= e(img_url($i['image'])) ?>" alt=""><?php endif; ?></span></td>
            <td><?= $i['product_id'] ? '<a href="product-edit.php?id=' . $i['product_id'] . '">' . e($i['name']) . '</a>' : e($i['name']) ?></td>
            <td class="num"><?= money($i['price']) ?></td>
            <td class="num"><?= (int)$i['qty'] ?></td>
            <td class="num"><?= money($i['price'] * $i['qty']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="4" class="num">Subtotal</td><td class="num"><?= money($order['subtotal']) ?></td></tr>
          <tr><td colspan="4" class="num"><?= $order['delivery'] === 'pickup' ? 'Store pickup' : 'Shipping' ?></td><td class="num"><?= $order['shipping'] > 0 ? money($order['shipping']) : 'Free' ?></td></tr>
          <tr class="total"><td colspan="4" class="num">Total</td><td class="num"><?= money($order['total']) ?></td></tr>
        </tfoot>
      </table>
      <?php if ($order['notes']): ?><div class="note"><strong>Customer note:</strong> <?= nl2br(e($order['notes'])) ?></div><?php endif; ?>
    </section>
  </div>

  <aside class="stack">
    <section class="card stack">
      <h2>Customer</h2>
      <p><strong><?= e($order['first_name'] . ' ' . $order['last_name']) ?></strong><?= $order['customer_id'] ? ' <a href="customers.php?id=' . (int)$order['customer_id'] . '" class="small">(account)</a>' : ' <span class="muted small">(guest)</span>' ?><br>
        <a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a><br>
        <a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a></p>
      <h2><?= $order['delivery'] === 'pickup' ? 'Store Pickup' : 'Ship To' ?></h2>
      <p><?= $order['delivery'] === 'pickup' ? '2990 Congressman Ln, Dallas, TX 75220' : e($order['address']) . '<br>' . e("{$order['city']}, {$order['state']} {$order['zip']}") ?></p>
    </section>

    <form method="post" class="card stack">
      <?= csrf_field() ?>
      <h2>Update Order</h2>
      <label>Status
        <select name="status"><?php foreach (ORDER_STATUSES as $k => $l): ?><option value="<?= $k ?>" <?= $order['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      </label>
      <label>Payment
        <select name="payment_status"><?php foreach (['unpaid', 'pending', 'paid', 'refunded', 'failed'] as $p): ?><option <?= $order['payment_status'] === $p ? 'selected' : '' ?>><?= $p ?></option><?php endforeach; ?></select>
      </label>
      <label>Tracking Number<input name="tracking_number" value="<?= e($order['tracking_number']) ?>"></label>
      <label>Internal Note<textarea name="admin_note" rows="3"><?= e($order['admin_note']) ?></textarea></label>
      <label class="check"><input type="checkbox" name="notify" checked> Email the customer when the status changes</label>
      <button class="btn btn-block">Save</button>
      <p class="muted small">Cancelling an order puts its items back in stock.</p>
    </form>
  </aside>
</div>
<?php admin_footer();
