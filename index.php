<?php
require_once __DIR__ . '/includes/db.php';
mb_internal_encoding('UTF-8');

$db = getDB();

// Stats equipements
$equipment = $db->query("
    SELECT COUNT(*) AS total,
           SUM(status='en_service') AS operational,
           SUM(status='en_panne') AS broken,
           SUM(status='en_maintenance') AS in_maintenance
    FROM equipment WHERE active=1
")->fetch(PDO::FETCH_ASSOC) ?: [];

$overdue = (int)$db->query(
    "SELECT COUNT(*) FROM equipment WHERE active=1 AND next_maintenance_at < CURDATE()"
)->fetchColumn();

// Stats OT
$statsWO = $db->query("
    SELECT COUNT(*) AS total,
           SUM(type='preventive') AS preventive,
           SUM(type='corrective') AS corrective
    FROM work_orders
")->fetch(PDO::FETCH_ASSOC) ?: [];

$totalWO = max(1, (int)($statsWO['total'] ?? 0));
$prevPct = $totalWO > 0 ? round((int)($statsWO['preventive'] ?? 0) / $totalWO * 100) : 0;

// Prochaines maintenances (30 jours)
$upcoming = $db->query("
    SELECT e.designation, e.serial_number, e.status, e.next_maintenance_at, e.failure_reason,
           d.name AS dept_name
    FROM equipment e
    LEFT JOIN departments d ON d.id = e.department_id
    WHERE e.active = 1 AND e.next_maintenance_at IS NOT NULL
    ORDER BY e.next_maintenance_at ASC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Repartition par categorie
$byCategory = $db->query("
    SELECT COALESCE(NULLIF(category,''), 'Non categorise') AS label, COUNT(*) AS value
    FROM equipment WHERE active=1
    GROUP BY label ORDER BY value DESC LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);
$maxCat = max(1, ...array_column($byCategory ?: [['value'=>1]], 'value'));

// OT recents
$work_orders = $db->query("
    SELECT w.id, w.reference, w.type, w.status, w.priority,
           w.failure_reason, w.created_at,
           e.designation, e.serial_number,
           d.name AS dept_name
    FROM work_orders w
    LEFT JOIN equipment e ON e.id = w.equipment_id
    LEFT JOIN departments d ON d.id = e.department_id
    ORDER BY w.created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

function statusBadge(string $s): string {
    $map = ['en_service'=>['En service','green'],'en_panne'=>['En panne','red'],'en_maintenance'=>['Maintenance','yellow'],'reforme'=>['Reforme','gray']];
    [$lbl,$cls] = $map[$s] ?? [ucfirst($s),'gray'];
    return '<span class="badge '.$cls.'">'.$lbl.'</span>';
}
function fmtDate(?string $d): string {
    if (!$d) return '<span style="color:var(--text-muted)">—</span>';
    $dt  = new DateTime($d);
    $now = new DateTime(date('Y-m-d'));
    $style = $dt < $now ? 'color:var(--red-500);font-weight:700' : '';
    return '<span'.($style?" style=\"$style\"":'').'>'.date('d/m/Y', strtotime($d)).'</span>';
}
function priorityBadge(string $p): string {
    $cls = match($p) { 'urgente','critique' => 'red', 'haute' => 'yellow', default => 'gray' };
    return '<span class="badge '.$cls.'">'.htmlspecialchars(ucfirst($p)).'</span>';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- KPIs -->
<div class="kpis">
  <div class="card kpi neutral">
    <small>Parc equipements</small>
    <strong><?= (int)$equipment['total'] ?></strong>
  </div>
  <div class="card kpi good">
    <small>En service</small>
    <strong><?= (int)($equipment['operational'] ?? 0) ?></strong>
  </div>
  <div class="card kpi <?= ($equipment['broken'] ?? 0) > 0 ? 'danger' : 'neutral' ?>">
    <small>En panne</small>
    <strong><?= (int)($equipment['broken'] ?? 0) ?></strong>
  </div>
  <div class="card kpi <?= $overdue > 0 ? 'danger' : 'good' ?>">
    <small>Echeances depassees</small>
    <strong><?= $overdue ?></strong>
  </div>
</div>

<!-- Banniere statistiques -->
<div class="card" style="margin-bottom:22px;background:linear-gradient(135deg,#ffffff 0%,var(--green-50) 100%);border-color:var(--green-200)">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
    <div>
      <strong style="font-size:15px;color:var(--text-primary)">
        Ratio Preventif : <?= $prevPct ?>%
      </strong>
      <p style="font-size:12.5px;color:var(--text-secondary);margin-top:3px">
        <?= (int)($statsWO['preventive'] ?? 0) ?> interventions preventives
        contre <?= (int)($statsWO['corrective'] ?? 0) ?> pannes curatives.
      </p>
    </div>
    <a href="<?= ($base_url ?: '') ?>/pages/statistiques.php" class="btn btn-primary btn-sm">
      Voir les statistiques
    </a>
  </div>
</div>

<!-- Grille 2 cols -->
<div class="grid-2">

  <!-- Prochaines maintenances -->
  <div class="card">
    <div class="card-header">
      <h2>Prochaines echeances</h2>
      <a href="<?= ($base_url ?: '') ?>/pages/equipements.php" style="font-size:12.5px;color:var(--green-600);font-weight:600">
        Gerer le parc
      </a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Equipement</th><th>Departement</th><th>Echeance</th><th>Statut</th></tr>
        </thead>
        <tbody>
          <?php if (!empty($upcoming)): foreach ($upcoming as $e): ?>
          <tr>
            <td>
              <strong style="color:var(--text-primary)"><?= htmlspecialchars($e['designation']) ?></strong><br>
              <code style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($e['serial_number']) ?></code>
            </td>
            <td><span class="dept-badge"><?= htmlspecialchars($e['dept_name'] ?? 'General') ?></span></td>
            <td><?= fmtDate($e['next_maintenance_at']) ?></td>
            <td><?= statusBadge($e['status']) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="4" class="empty">Aucune maintenance planifiee</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Repartition par categorie -->
  <div class="card">
    <div class="card-header">
      <h2>Repartition par categorie</h2>
    </div>
    <?php if (!empty($byCategory)): ?>
    <div class="chart-bars">
      <?php foreach ($byCategory as $cat): ?>
      <div class="chart-bar-row">
        <div class="chart-bar-label">
          <span class="chart-bar-name"><?= htmlspecialchars($cat['label']) ?></span>
          <span class="chart-bar-val"><?= $cat['value'] ?></span>
        </div>
        <div class="chart-bar-track">
          <div class="chart-bar-fill green" style="width:<?= round($cat['value']/$maxCat*100) ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="empty">Aucune categorie repertoriee</p>
    <?php endif; ?>
  </div>

</div>

<!-- OT recents -->
<div class="card section-gap">
  <div class="card-header">
    <h2>Derniers ordres de travail</h2>
    <a href="<?= ($base_url ?: '') ?>/pages/maintenance.php" style="font-size:12.5px;color:var(--green-600);font-weight:600">
      Voir tout
    </a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Reference</th>
          <th>Equipement et Departement</th>
          <th>Type</th>
          <th>Motif</th>
          <th>Priorite</th>
          <th>Statut</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($work_orders)): foreach ($work_orders as $w):
          $isCorr = ($w['type'] === 'corrective');
        ?>
        <tr>
          <td><code style="font-size:12px"><?= htmlspecialchars($w['reference'] ?? 'OT-'.$w['id']) ?></code></td>
          <td>
            <strong style="color:var(--text-primary)"><?= htmlspecialchars($w['designation'] ?? '—') ?></strong><br>
            <span class="dept-badge" style="font-size:10.5px;margin-top:2px"><?= htmlspecialchars($w['dept_name'] ?? 'General') ?></span>
          </td>
          <td>
            <span class="badge <?= $isCorr ? 'red' : 'green' ?>" style="font-size:11px">
              <?= $isCorr ? 'Correctif' : 'Preventif' ?>
            </span>
          </td>
          <td style="color:var(--text-secondary);font-size:13px"><?= htmlspecialchars($w['failure_reason'] ?? 'Entretien courant') ?></td>
          <td><?= priorityBadge($w['priority'] ?? 'normale') ?></td>
          <td>
            <?php
            $stLabel = match($w['status']) { 'terminee'=>'Terminee','en_cours'=>'En cours','ouverte'=>'Ouverte', default=>$w['status'] };
            $stClass = match($w['status']) { 'terminee'=>'green','en_cours'=>'yellow', default=>'gray' };
            ?>
            <span class="badge <?= $stClass ?>"><?= $stLabel ?></span>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6" class="empty">Aucun ordre de travail recent</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>