<?php
// pages/maintenance.php — Gestion des ordres de travail (OT) et maintenance
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

$form_success = '';
$form_error   = '';

// ── Action POST : Clôturer un OT ──────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'complete_wo') {
    $wo_id = (int)($_POST['work_order_id'] ?? 0);
    if ($wo_id > 0) {
        try {
            $db->prepare("UPDATE work_orders SET status = 'terminee', completed_at = NOW() WHERE id = ?")->execute([$wo_id]);
            $form_success = "L'ordre de travail a été validé et clôturé.";
        } catch (Exception $e) {
            $form_error = "Erreur : " . $e->getMessage();
        }
    }
}

// ── Filtres ───────────────────────────────────────────────
$kind_filter = $_GET['kind'] ?? '';

$sql = "
    SELECT w.*,
           e.serial_number, e.designation, d.name AS dept_name,
           GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') AS technicians
    FROM work_orders w
    JOIN equipment e ON e.id = w.equipment_id
    LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN work_order_technicians wt ON wt.work_order_id = w.id
    LEFT JOIN users u ON u.id = wt.technician_id
";

$params = [];
if ($kind_filter === 'preventive' || $kind_filter === 'corrective') {
    $sql .= " WHERE w.kind = ?";
    $params[] = $kind_filter;
}

$sql .= " GROUP BY w.id ORDER BY w.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$work_orders = $stmt->fetchAll();

function statusBadge(string $s): string {
    $lbl = str_replace('_', ' ', $s);
    if ($s === 'terminee') $lbl = 'Terminé';
    if ($s === 'en_cours') $lbl = 'En cours';
    if ($s === 'ouverte')  $lbl = 'Ouvert';
    return '<span class="badge status-' . htmlspecialchars($s) . '">' . htmlspecialchars($lbl) . '</span>';
}

function priorityBadge(string $p): string {
    return '<span class="badge priority-' . htmlspecialchars($p) . '">'
         . htmlspecialchars(ucfirst($p)) . '</span>';
}

function fmtDate(?string $d): string {
    if (!$d) return '<span style="color:var(--text-muted)">—</span>';
    try {
        $dt = new DateTime($d);
        if (class_exists('IntlDateFormatter')) {
            return (new IntlDateFormatter('fr_FR', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE))->format($dt);
        }
        $months = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $m = (int)$dt->format('n');
        return $dt->format('j') . ' ' . ($months[$m] ?? '') . ' ' . $dt->format('Y');
    } catch (Exception $e) {
        return htmlspecialchars((string)$d);
    }
}

// Statistiques rapides
$stats = [
    'total'      => 0,
    'preventive' => 0,
    'corrective' => 0,
    'en_cours'   => 0,
    'terminee'   => 0,
    'urgente'    => 0,
];

try {
    $res = $db->query("
        SELECT
            COUNT(*)                                  AS total,
            SUM(kind='preventive')                    AS preventive,
            SUM(kind='corrective')                    AS corrective,
            SUM(status='en_cours')                    AS en_cours,
            SUM(status='terminee')                    AS terminee,
            SUM(priority IN ('urgente', 'critique')) AS urgente
        FROM work_orders
    ")->fetch();
    if ($res) $stats = $res;
} catch (Exception $e) {}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Mini KPIs ordres de travail ───────────────────────── -->
<div class="kpis">
  <div class="card kpi neutral">
    <span class="kpi-icon">📋</span>
    <small>Total Ordres de Travail</small>
    <strong><?= (int)$stats['total'] ?></strong>
  </div>

  <div class="card kpi good">
    <span class="kpi-icon">🛡️</span>
    <small>Préventif planifié</small>
    <strong><?= (int)($stats['preventive'] ?? 0) ?></strong>
  </div>

  <!-- Alerte rouge rare : correctifs / pannes -->
  <div class="card kpi <?= ($stats['corrective'] ?? 0) > 0 ? 'danger' : 'neutral' ?>">
    <span class="kpi-icon">🚨</span>
    <small>Correctif / Pannes</small>
    <strong><?= (int)($stats['corrective'] ?? 0) ?></strong>
  </div>

  <div class="card kpi good">
    <span class="kpi-icon">✅</span>
    <small>Interventions clôturées</small>
    <strong><?= (int)($stats['terminee'] ?? 0) ?></strong>
  </div>
</div>

<!-- ── Navigation par type (Pills) ───────────────────────── -->
<div class="dept-nav">
  <a href="<?= ($base_url ?: '') ?>/pages/maintenance.php"
     class="dept-pill <?= empty($kind_filter) ? 'active' : '' ?>">
    📋 Tous les ordres de travail (<?= (int)$stats['total'] ?>)
  </a>
  <a href="<?= ($base_url ?: '') ?>/pages/maintenance.php?kind=preventive"
     class="dept-pill <?= $kind_filter === 'preventive' ? 'active' : '' ?>">
    🛡️ Maintenances Préventives (<?= (int)$stats['preventive'] ?>)
  </a>
  <a href="<?= ($base_url ?: '') ?>/pages/maintenance.php?kind=corrective"
     class="dept-pill <?= $kind_filter === 'corrective' ? 'active' : '' ?>">
    🚨 Pannes &amp; Correctifs (<?= (int)$stats['corrective'] ?>)
  </a>
</div>

<?php if ($form_success): ?>
  <div class="alert-success">✅ <?= htmlspecialchars($form_success) ?></div>
<?php endif; ?>
<?php if ($form_error): ?>
  <div class="error">⚠ <?= htmlspecialchars($form_error) ?></div>
<?php endif; ?>

<!-- ── Tableau des interventions ─────────────────────────── -->
<div class="card">
  <div class="card-header">
    <h2>🔧 Ordres de travail &amp; Interventions techniques</h2>
    <span class="count-badge"><?= count($work_orders) ?> intervention(s)</span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Référence &amp; Type</th>
          <th>Équipement cible</th>
          <th>Département</th>
          <th>Motif de la panne / Motif intervention</th>
          <th>Priorité</th>
          <th>Techniciens affectés</th>
          <th>Statut</th>
          <th style="width:60px"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($work_orders)): foreach ($work_orders as $w):
          $isCorr = ($w['kind'] === 'corrective');
        ?>
        <tr>
          <td>
            <code><?= htmlspecialchars($w['reference']) ?></code><br>
            <span class="badge <?= $isCorr ? 'red' : 'green' ?>" style="font-size:11px;margin-top:3px">
              <?= $isCorr ? '🚨 Correctif' : '🛡️ Préventif' ?>
            </span>
          </td>

          <td>
            <strong style="color:var(--text-primary)"><?= htmlspecialchars($w['designation']) ?></strong><br>
            <code style="font-size:11px"><?= htmlspecialchars($w['serial_number']) ?></code>
          </td>

          <td>
            <span class="dept-badge"><?= htmlspecialchars($w['dept_name'] ?? 'Général') ?></span>
          </td>

          <td>
            <?php if (!empty($w['failure_reason'])): ?>
              <span style="font-weight:600;color:<?= $isCorr ? 'var(--red-600)' : 'var(--green-700)' ?>">
                <?= htmlspecialchars($w['failure_reason']) ?>
              </span>
            <?php else: ?>
              <span style="color:var(--text-muted);font-style:italic">Non précisé</span>
            <?php endif; ?>
          </td>

          <td><?= priorityBadge($w['priority']) ?></td>

          <td>
            <?php if (!empty($w['technicians'])): ?>
              <span style="color:var(--text-primary);font-weight:500">👷 <?= htmlspecialchars($w['technicians']) ?></span>
            <?php else: ?>
              <span style="color:var(--text-muted);font-style:italic">Non assigné</span>
            <?php endif; ?>
          </td>

          <td><?= statusBadge($w['status']) ?></td>

          <td>
            <?php if ($w['status'] !== 'terminee'): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Clôturer cette intervention de maintenance ?');">
                <input type="hidden" name="action" value="complete_wo">
                <input type="hidden" name="work_order_id" value="<?= $w['id'] ?>">
                <button type="submit" class="btn-action-status btn-to-repaired" title="Marquer comme terminée">
                  ✓ Clôturer
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" class="empty">📋 Aucun ordre de travail dans cette catégorie.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
