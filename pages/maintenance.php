<?php
require_once __DIR__ . '/../includes/db.php';
mb_internal_encoding('UTF-8');

$db = getDB();
$kind_filter = $_GET['kind'] ?? '';

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'complete_wo') {
        $woId = (int)($_POST['work_order_id'] ?? 0);
        if ($woId > 0) {
            $db->prepare("UPDATE work_orders SET status='terminee', closed_at=NOW() WHERE id=?")->execute([$woId]);
            $db->prepare("UPDATE equipment SET status='en_service' WHERE id=(SELECT equipment_id FROM work_orders WHERE id=?)")->execute([$woId]);
            header('Location: maintenance.php?kind=' . urlencode($kind_filter));
            exit;
        }
    }
}

// Requete OT
$sql = "
    SELECT w.id, w.reference, w.type, w.status, w.priority,
           w.description, w.failure_reason, w.created_at, w.closed_at,
           e.designation, e.serial_number,
           d.name AS dept_name,
           GROUP_CONCAT(u.name SEPARATOR ', ') AS technicians
    FROM work_orders w
    LEFT JOIN equipment e ON e.id = w.equipment_id
    LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN work_order_technicians wt ON wt.work_order_id = w.id
    LEFT JOIN users u ON u.id = wt.technician_id
";
$params = [];
if ($kind_filter === 'preventive' || $kind_filter === 'corrective') {
    $sql .= " WHERE w.type = ?";
    $params[] = $kind_filter;
}
$sql .= " GROUP BY w.id ORDER BY w.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$work_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats = $db->query("
    SELECT COUNT(*) AS total,
           SUM(type='preventive') AS preventive,
           SUM(type='corrective')  AS corrective,
           SUM(status='en_cours')  AS en_cours,
           SUM(status='terminee')  AS terminee,
           SUM(priority IN ('urgente','critique')) AS urgente
    FROM work_orders
")->fetch(PDO::FETCH_ASSOC) ?: [];

function fmtDate(?string $d): string {
    if (!$d) return '<span style="color:var(--text-muted)">—</span>';
    return date('d/m/Y', strtotime($d));
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- KPIs -->
<div class="kpis" style="margin-bottom:22px">
  <div class="card kpi neutral">
    <small>Total OT</small>
    <strong><?= (int)($stats['total'] ?? 0) ?></strong>
  </div>
  <div class="card kpi good">
    <small>Preventif planifie</small>
    <strong><?= (int)($stats['preventive'] ?? 0) ?></strong>
  </div>
  <div class="card kpi <?= ($stats['corrective'] ?? 0) > 0 ? 'danger' : 'neutral' ?>">
    <small>Correctif / Pannes</small>
    <strong><?= (int)($stats['corrective'] ?? 0) ?></strong>
  </div>
  <div class="card kpi good">
    <small>Clotures</small>
    <strong><?= (int)($stats['terminee'] ?? 0) ?></strong>
  </div>
</div>

<!-- Filtre par type -->
<div class="dept-nav">
  <a href="maintenance.php" class="dept-pill <?= empty($kind_filter) ? 'active' : '' ?>">
    Tous (<?= (int)($stats['total'] ?? 0) ?>)
  </a>
  <a href="maintenance.php?kind=preventive" class="dept-pill <?= $kind_filter === 'preventive' ? 'active' : '' ?>">
    Preventif (<?= (int)($stats['preventive'] ?? 0) ?>)
  </a>
  <a href="maintenance.php?kind=corrective" class="dept-pill <?= $kind_filter === 'corrective' ? 'active' : '' ?>">
    Correctif / Pannes (<?= (int)($stats['corrective'] ?? 0) ?>)
  </a>
</div>

<!-- Tableau OT -->
<div class="card">
  <div class="card-header">
    <h2>Ordres de travail et interventions</h2>
    <span class="count-badge"><?= count($work_orders) ?> intervention(s)</span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Reference et Type</th>
          <th>Equipement</th>
          <th>Departement</th>
          <th>Motif / Description</th>
          <th>Priorite</th>
          <th>Techniciens</th>
          <th>Statut</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($work_orders)): foreach ($work_orders as $w):
          $isCorr = ($w['type'] === 'corrective');
        ?>
        <tr>
          <td>
            <code style="font-size:12px"><?= htmlspecialchars($w['reference'] ?? 'OT-' . $w['id']) ?></code><br>
            <span class="badge <?= $isCorr ? 'red' : 'green' ?>" style="font-size:11px;margin-top:3px">
              <?= $isCorr ? 'Correctif' : 'Preventif' ?>
            </span>
          </td>
          <td>
            <strong style="color:var(--text-primary)"><?= htmlspecialchars($w['designation'] ?? '—') ?></strong><br>
            <code style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($w['serial_number'] ?? '') ?></code>
          </td>
          <td><span class="dept-badge"><?= htmlspecialchars($w['dept_name'] ?? 'General') ?></span></td>
          <td style="font-size:13px">
            <?php if (!empty($w['failure_reason'])): ?>
              <span style="font-weight:600;color:<?= $isCorr ? 'var(--red-600)' : 'var(--green-700)' ?>">
                <?= htmlspecialchars($w['failure_reason']) ?>
              </span>
            <?php else: ?>
              <span style="color:var(--text-muted);font-style:italic">Non precise</span>
            <?php endif; ?>
          </td>
          <td>
            <?php
            $pc = $w['priority'] ?? 'normale';
            $pcClass = match($pc) {
              'urgente', 'critique' => 'red',
              'haute' => 'yellow',
              default => 'gray'
            };
            ?>
            <span class="badge <?= $pcClass ?>"><?= htmlspecialchars(ucfirst($pc)) ?></span>
          </td>
          <td style="font-size:13px;color:var(--text-secondary)">
            <?= $w['technicians'] ? htmlspecialchars($w['technicians']) : '<span style="color:var(--text-muted);font-style:italic">Non assigne</span>' ?>
          </td>
          <td>
            <?php
            $st = $w['status'];
            $stClass = match($st) {
              'terminee' => 'green',
              'en_cours' => 'yellow',
              default    => 'gray'
            };
            $stLabel = match($st) {
              'terminee' => 'Terminee',
              'en_cours' => 'En cours',
              'ouverte'  => 'Ouverte',
              default    => $st
            };
            ?>
            <span class="badge <?= $stClass ?>"><?= $stLabel ?></span>
          </td>
          <td>
            <?php if ($w['status'] !== 'terminee'): ?>
            <form method="post" onsubmit="return confirm('Cloturer cette intervention ?')">
              <input type="hidden" name="action" value="complete_wo">
              <input type="hidden" name="work_order_id" value="<?= $w['id'] ?>">
              <button type="submit" class="btn-action-status btn-to-repaired">Cloturer</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" class="empty">Aucun ordre de travail dans cette categorie.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>