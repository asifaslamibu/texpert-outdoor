<?php
require_once __DIR__ . '/_init.php';
require_admin();

if (is_post()) {
    check_csrf();
    query('DELETE FROM subscribers WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    flash('Subscriber removed.');
    redirect('subscribers.php');
}

$type = in_array($_GET['type'] ?? '', ['email', 'sms'], true) ? $_GET['type'] : '';
$rows = query('SELECT * FROM subscribers' . ($type ? ' WHERE type = ?' : '') . ' ORDER BY id DESC', $type ? [$type] : [])->fetchAll();

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . ($type ?: 'all') . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['type', 'value', 'signed_up']);
    foreach ($rows as $r) fputcsv($out, [$r['type'], $r['value'], $r['created_at']]);
    exit;
}

admin_header('Subscribers', 'subscribers');
?>
<div class="toolbar">
  <nav class="tabs">
    <a href="subscribers.php" class="<?= $type === '' ? 'on' : '' ?>">All</a>
    <a href="?type=email" class="<?= $type === 'email' ? 'on' : '' ?>">Email</a>
    <a href="?type=sms" class="<?= $type === 'sms' ? 'on' : '' ?>">SMS</a>
  </nav>
  <a href="?<?= e(http_build_query(['type' => $type ?: null, 'export' => 1])) ?>" class="btn btn-light"><i class="fa-solid fa-download"></i> Export CSV</a>
</div>
<section class="card flush">
  <p class="card-meta"><?= count($rows) ?> subscriber<?= count($rows) === 1 ? '' : 's' ?></p>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Type</th><th>Email / Phone</th><th>Signed Up</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= badge($r['type'], strtoupper($r['type'])) ?></td>
        <td><?= e($r['value']) ?></td>
        <td><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
        <td class="actions">
          <form method="post" onsubmit="return confirm('Remove this subscriber?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="icon-btn danger" title="Remove"><i class="fa-solid fa-trash"></i></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="muted center">No subscribers yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
<?php admin_footer();
