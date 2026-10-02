<?php
// pages/notifications.php — Centre de notifications et alertes
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

// Détection de base_url pour la redirection
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$base_url = rtrim(dirname($script_dir), '/\\');

// Marquer toutes les notifications comme lues
if (isset($_GET['mark_read'])) {
    try {
        $db->exec("UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL");
    } catch (Exception $e) {}
    header('Location: ' . ($base_url ?: '') . '/pages/notifications.php');
    exit;
}

$notifications = [];
try {
    $notifications = $db->query("
        SELECT n.*, u.first_name, u.last_name
        FROM notifications n
        JOIN users u ON u.id = n.user_id
        ORDER BY n.trigger_at DESC
    ")->fetchAll();
} catch (Exception $e) {}

$unread = array_filter($notifications, fn($n) => $n['read_at'] === null);

function fmtDatetime(?string $d): string {
    if (!$d) return '—';
    try {
        $dt = new DateTime($d);
        if (class_exists('IntlDateFormatter')) {
            $fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);
            return $fmt->format($dt);
        }
        $months = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $m = (int)$dt->format('n');
        return $dt->format('j') . ' ' . ($months[$m] ?? '') . ' ' . $dt->format('Y') . ' à ' . $dt->format('H:i');
    } catch (Exception $e) {
        return htmlspecialchars((string)$d);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Barre d'outils ────────────────────────────────────── -->
<div class="toolbar">
  <div style="color:var(--text-secondary);font-size:13.5px;font-weight:500">
    <strong style="color:var(--text-primary)"><?= count($unread) ?></strong> non lue(s) · <?= count($notifications) ?> notification(s) au total
  </div>

  <?php if (count($unread) > 0): ?>
  <a href="<?= ($base_url ?: '') ?>/pages/notifications.php?mark_read=1" class="btn btn-secondary">
    ✓ Tout marquer comme lu
  </a>
  <?php endif; ?>
</div>

<!-- ── Carte Tableau des notifications ───────────────────── -->
<div class="card">
  <div class="card-header">
    <h2>🔔 Flux des notifications &amp; Alertes système</h2>
    <?php if (count($unread) > 0): ?>
      <span class="badge red-pulse"><?= count($unread) ?> non lue(s)</span>
    <?php else: ?>
      <span class="badge green">Toutes lues ✓</span>
    <?php endif; ?>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date &amp; Heure</th>
          <th>Destinataire</th>
          <th>Type</th>
          <th>Titre &amp; Détails</th>
          <th>Statut</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($notifications)): foreach ($notifications as $n):
          $is_unread = ($n['read_at'] === null);
          $is_alert  = ($n['type'] === 'alert');
        ?>
        <tr class="<?= $is_unread ? 'unread' : '' ?>">
          <td style="white-space:nowrap;color:var(--text-muted);font-size:12.5px">
            <?= fmtDatetime($n['trigger_at']) ?>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="tech-avatar" style="width:30px;height:30px;font-size:11px">
                <?= mb_strtoupper(mb_substr($n['first_name'],0,1) . mb_substr($n['last_name'],0,1)) ?>
              </div>
              <span style="font-weight:500;color:var(--text-primary)"><?= htmlspecialchars($n['first_name'] . ' ' . $n['last_name']) ?></span>
            </div>
          </td>
          <td>
            <?php if ($is_alert): ?>
              <span class="badge red">⚠ Alerte</span>
            <?php elseif ($n['type'] === 'warning'): ?>
              <span class="badge red" style="background:var(--red-50);color:var(--red-500);border-color:var(--red-200)">Avertissement</span>
            <?php else: ?>
              <span class="badge green">Information</span>
            <?php endif; ?>
          </td>
          <td>
            <strong style="color:var(--text-primary)"><?= htmlspecialchars($n['title']) ?></strong><br>
            <small style="color:var(--text-secondary);line-height:1.4;display:inline-block;margin-top:2px">
              <?= htmlspecialchars($n['message']) ?>
            </small>
          </td>
          <td>
            <?php if ($is_unread): ?>
              <span class="badge red-pulse">● Non lue</span>
            <?php else: ?>
              <span class="badge green">✓ Lue</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" class="empty">🔔 Aucune notification enregistrée</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
