<?php
require_once __DIR__ . '/_init.php';
require_admin();

if (is_post()) {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['new', 'read', 'archived'], true)) query('UPDATE messages SET status = ? WHERE id = ?', [$action, $id]);
    if ($action === 'delete') query('DELETE FROM messages WHERE id = ?', [$id]);
    flash($action === 'delete' ? 'Message deleted.' : 'Message updated.');
    redirect($action === 'delete' || $action === 'archived' ? 'messages.php' : 'messages.php?id=' . $id);
}

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $m = query('SELECT * FROM messages WHERE id = ?', [$id])->fetch();
    if (!$m) { flash('Message not found.', 'err'); redirect('messages.php'); }
    if ($m['status'] === 'new') { query("UPDATE messages SET status = 'read' WHERE id = ?", [$id]); $m['status'] = 'read'; }

    admin_header('Contact Query', 'messages');
    ?>
    <p class="back"><a href="messages.php"><i class="fa-solid fa-arrow-left"></i> All queries</a></p>
    <section class="card stack message">
      <div class="card-head"><h2><?= e($m['topic'] ?: 'Message') ?></h2><?= badge($m['status']) ?></div>
      <p><strong><?= e($m['name']) ?></strong> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?= $m['phone'] ? ' · <a href="tel:' . e($m['phone']) . '">' . e($m['phone']) . '</a>' : '' ?><br>
        <span class="muted small"><?= e(date('l, M j, Y \a\t g:ia', strtotime($m['created_at']))) ?></span></p>
      <div class="message-body"><?= nl2br(e($m['message'])) ?></div>
      <div class="filters-row">
        <a class="btn" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . ($m['topic'] ?: 'Your message')) ?>"><i class="fa-solid fa-reply"></i> Reply by Email</a>
        <?php foreach (['new' => 'Mark Unread', 'archived' => 'Archive', 'delete' => 'Delete'] as $act => $label): ?>
          <form method="post" <?= $act === 'delete' ? 'onsubmit="return confirm(\'Delete this message?\')"' : '' ?>>
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="<?= $act ?>">
            <button class="btn btn-light<?= $act === 'delete' ? ' danger' : '' ?>"><?= $label ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
    admin_footer();
    exit;
}

$filter = $_GET['status'] ?? 'inbox';
$where = $filter === 'archived' ? "status = 'archived'" : "status <> 'archived'";
$rows = query("SELECT * FROM messages WHERE $where ORDER BY id DESC LIMIT 300")->fetchAll();

admin_header('Contact Queries', 'messages');
?>
<nav class="tabs">
  <a href="messages.php" class="<?= $filter !== 'archived' ? 'on' : '' ?>">Inbox</a>
  <a href="?status=archived" class="<?= $filter === 'archived' ? 'on' : '' ?>">Archived</a>
</nav>
<section class="card flush">
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>From</th><th>Topic</th><th>Message</th><th>Received</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $m): ?>
      <tr class="clickable<?= $m['status'] === 'new' ? ' unread' : '' ?>" onclick="location='messages.php?id=<?= $m['id'] ?>'">
        <td><a href="messages.php?id=<?= $m['id'] ?>"><strong><?= e($m['name']) ?></strong></a><br><small class="muted"><?= e($m['email']) ?></small></td>
        <td><?= e($m['topic']) ?></td>
        <td class="clip"><?= e(mb_strimwidth($m['message'], 0, 90, '…')) ?></td>
        <td><?= e(date('M j, g:ia', strtotime($m['created_at']))) ?></td>
        <td><?= $m['status'] === 'new' ? badge('new') : '' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="muted center">No messages here.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
<?php admin_footer();
