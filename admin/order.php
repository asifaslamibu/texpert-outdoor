<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../includes/shippo.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$order = query('SELECT * FROM orders WHERE id = ?', [$id])->fetch();
if (!$order) { flash('Order not found.', 'err'); redirect('orders.php'); }

if (is_post() && ($_POST['action'] ?? '') === 'buy_label') {
    check_csrf();
    if (!shippo_enabled() || $order['delivery'] !== 'ship' || $order['label_url'] !== '' || $order['status'] === 'cancelled') {
        flash('A label cannot be bought for this order.', 'err');
        redirect('order.php?id=' . $id);
    }
    try {
        $label = shippo_buy_label($order);
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'err');
        redirect('order.php?id=' . $id);
    }
    query("UPDATE orders SET status = 'shipped', carrier = ?, tracking_number = ?, tracking_url = ?, label_url = ? WHERE id = ?",
        [$label['carrier'], $label['tracking_number'], $label['tracking_url'], $label['label_url'], $id]);
    if (isset($_POST['notify'])) {
        notify($order['email'], "Order {$order['order_no']} has shipped",
            "Hi {$order['first_name']},\n\nGood news: your order {$order['order_no']} is on its way with {$label['service']}.\n"
            . "Tracking number: {$label['tracking_number']}\n" . ($label['tracking_url'] ? "Track it here: {$label['tracking_url']}\n" : '')
            . "\nThank you,\nTexpert Outdoors\n+1-254-358-0017");
    }
    flash("Label bought: {$label['service']} for " . money($label['cost']) . '. Print it from the Shipping Label box.');
    redirect('order.php?id=' . $id);
}

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
        if ($status === 'shipped' && $order['tracking_url'] !== '') $msg .= "\nTrack it here: " . $order['tracking_url'];
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

    <?php if ($order['delivery'] === 'ship'): ?>
    <section class="card stack">
      <h2>Shipping Label</h2>
      <?php if ($order['shipping_service']): ?><p class="muted small">Customer chose <strong><?= e($order['shipping_service']) ?></strong> (<?= $order['shipping'] > 0 ? money($order['shipping']) : 'free' ?>).</p><?php endif; ?>
      <?php if ($order['label_url']): ?>
        <a href="<?= e($order['label_url']) ?>" target="_blank" rel="noopener" class="btn btn-block"><i class="fa-solid fa-print"></i> Print Label (PDF)</a>
        <p class="small">Tracking: <?= $order['tracking_url'] ? '<a href="' . e($order['tracking_url']) . '" target="_blank" rel="noopener">' . e(trim($order['carrier'] . ' ' . $order['tracking_number'])) . '</a>' : e($order['tracking_number']) ?></p>
      <?php elseif (!shippo_enabled()): ?>
        <p class="muted small">Add a Shippo API key to <code>includes/config.php</code> to buy shipping labels here.</p>
      <?php elseif ($order['status'] !== 'cancelled'): ?>
        <form method="post" class="stack" onsubmit="return window.confirm('Buy a shipping label now? Your Shippo account is charged for it.')">
          <?= csrf_field() ?><input type="hidden" name="action" value="buy_label">
          <label class="check"><input type="checkbox" name="notify" checked> Email the tracking number to the customer</label>
          <button class="btn btn-block"><i class="fa-solid fa-truck-fast"></i> Buy Shipping Label</button>
          <p class="muted small">Uses the product weights and sizes, and marks the order as Shipped.</p>
        </form>
      <?php endif; ?>
    </section>
    <?php endif; ?>

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
