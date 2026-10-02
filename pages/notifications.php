<?php
require_once __DIR__ . '/../includes/db.php';
mb_internal_encoding('UTF-8');

$db = getDB();

// Marquer toutes comme lues
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    $db->exec("UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL");
}

// Liste notifications
$notifications = $db->query("
    SELECT id, title, message, type, read_at, created_at
    FROM notifications
    ORDER BY created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$unread = array_filter($notifications, fn($n) => $n['read_at'] === null);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card-header" style="margin-bottom:20px">
  <div>
    <h2 style="font-size:17px;font-weight:700;color:var(--text-primary)">Notifications</h2>
    <p style="font-size:13px;color:var(--text-muted);margin-top:2px">
      <?= count($unread) ?> non lue(s) sur <?= count($notifications) ?> notification(s)
    </p>
  </div>
  <?php if (count($unread) > 0): ?>
  <form method="post">
    <input type="hidden" name="action" value="mark_all_read">
    <button type="submit" class="btn btn-secondary btn-sm">Tout marquer comme lu</button>
  </form>
  <?php endif; ?>
</div>

<?php if (!empty($notifications)): ?>
<div style="display:flex;flex-direction:column;gap:10px">
  <?php foreach ($notifications as $n):
    $isUnread = $n['read_at'] === null;
    $typeClass = match($n['type'] ?? 'info') {
      'warning', 'alerte' => 'yellow',
      'danger', 'error'   => 'red',
      'success'           => 'green',
      default             => 'gray'
    };
  ?>
  <div class="card" style="padding:16px 20px;<?= $isUnread ? 'border-left:3px solid var(--green-400);' : 'opacity:.75' ?>">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
          <?php if ($isUnread): ?>
          <span style="width:8px;height:8px;border-radius:50%;background:var(--green-500);flex-shrink:0"></span>
          <?php endif; ?>
          <strong style="font-size:14px;color:var(--text-primary)"><?= htmlspecialchars($n['title'] ?? 'Notification') ?></strong>
          <span class="badge <?= $typeClass ?>" style="font-size:11px"><?= htmlspecialchars(ucfirst($n['type'] ?? 'info')) ?></span>
        </div>
        <p style="font-size:13px;color:var(--text-secondary);margin:0"><?= htmlspecialchars($n['message'] ?? '') ?></p>
      </div>
      <div style="font-size:11.5px;color:var(--text-muted);white-space:nowrap;text-align:right">
        <?= date('d/m/Y H:i', strtotime($n['created_at'])) ?><br>
        <?= $isUnread ? '<span style="color:var(--green-600);font-weight:600">Non lu</span>' : 'Lu' ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card"><p class="empty">Aucune notification pour le moment.</p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>