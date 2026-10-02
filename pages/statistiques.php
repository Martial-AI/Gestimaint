<?php
require_once __DIR__ . '/../includes/db.php';
mb_internal_encoding('UTF-8');

$db = getDB();

// Totaux preventif / correctif
$statsSummary = $db->query("
    SELECT
      COUNT(*) AS total,
      SUM(type = 'preventive') AS preventive,
      SUM(type = 'corrective')  AS corrective,
      SUM(status = 'terminee')  AS terminee
    FROM work_orders
")->fetch(PDO::FETCH_ASSOC) ?: [];

$total    = max(1, (int)($statsSummary['total'] ?? 0));
$prevCount= (int)($statsSummary['preventive'] ?? 0);
$corrCount= (int)($statsSummary['corrective'] ?? 0);
$prevPct  = $total > 0 ? round($prevCount / $total * 100) : 0;
$corrPct  = 100 - $prevPct;

// Motifs de pannes
$failureReasons = $db->query("
    SELECT failure_reason AS reason, COUNT(*) AS cnt
    FROM work_orders
    WHERE failure_reason IS NOT NULL AND failure_reason != ''
    GROUP BY failure_reason
    ORDER BY cnt DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);
$totalFailures = max(1, array_sum(array_column($failureReasons, 'cnt')));

// Par departement
$deptStats = $db->query("
    SELECT d.name AS dept_name,
           SUM(wo.type = 'preventive') AS prev_count,
           SUM(wo.type = 'corrective')  AS corr_count,
           COUNT(wo.id) AS total
    FROM departments d
    LEFT JOIN equipment e  ON e.department_id = d.id
    LEFT JOIN work_orders wo ON wo.equipment_id = e.id
    GROUP BY d.id
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Equipements les plus critiques
$topEquipmentFailures = $db->query("
    SELECT e.designation, e.serial_number,
           d.name AS dept_name,
           COUNT(wo.id) AS total_wo,
           SUM(wo.type = 'corrective') AS failure_count
    FROM equipment e
    LEFT JOIN departments d  ON d.id = e.department_id
    LEFT JOIN work_orders wo ON wo.equipment_id = e.id
    WHERE e.active = 1
    GROUP BY e.id
    HAVING total_wo > 0
    ORDER BY failure_count DESC, total_wo DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Evolution mensuelle (12 derniers mois)
$monthly = $db->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS mois,
           SUM(type = 'preventive') AS prev,
           SUM(type = 'corrective')  AS corr
    FROM work_orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY mois
    ORDER BY mois ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Max pour echelle graphique mensuel
$maxMonth = 1;
foreach ($monthly as $m) {
    $maxMonth = max($maxMonth, (int)$m['prev'] + (int)$m['corr']);
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- KPIs -->
<div class="stat-kpi-grid">
  <div class="stat-kpi green">
    <div class="stat-kpi-val"><?= $prevCount ?></div>
    <div class="stat-kpi-label">Preventives</div>
  </div>
  <div class="stat-kpi red">
    <div class="stat-kpi-val"><?= $corrCount ?></div>
    <div class="stat-kpi-label">Correctives</div>
  </div>
  <div class="stat-kpi">
    <div class="stat-kpi-val"><?= (int)($statsSummary['total'] ?? 0) ?></div>
    <div class="stat-kpi-label">Total interventions</div>
  </div>
  <div class="stat-kpi green">
    <div class="stat-kpi-val"><?= (int)($statsSummary['terminee'] ?? 0) ?></div>
    <div class="stat-kpi-label">Cloturees</div>
  </div>
</div>

<!-- Equilibre Preventif vs Correctif -->
<div class="card" style="margin-bottom:22px">
  <div class="card-header">
    <h2>Equilibre Preventif / Correctif</h2>
    <span class="count-badge" style="background:<?= $prevPct >= 60 ? 'var(--green-100)' : 'var(--red-100)' ?>;color:<?= $prevPct >= 60 ? 'var(--green-700)' : 'var(--red-600)' ?>">
      <?= $prevPct >= 60 ? 'Ratio sain' : 'Trop de curatif' ?>
    </span>
  </div>
  <p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px">
    Une gestion optimale vise un taux de <strong>maintenance preventive superieur a 70%</strong> pour minimiser les arrets de production.
  </p>

  <!-- Barre bicolore -->
  <div class="stat-split-bar">
    <div class="stat-split-prev" style="width:<?= $prevPct ?>%">
      <?= $prevPct > 15 ? "Prev. {$prevPct}%" : '' ?>
    </div>
    <div class="stat-split-corr" style="width:<?= $corrPct ?>%">
      <?= $corrPct > 15 ? "Corr. {$corrPct}%" : '' ?>
    </div>
  </div>

  <div class="stat-legend">
    <div class="stat-legend-item">
      <span class="stat-legend-dot" style="background:var(--green-500)"></span>
      <span><strong>Preventif :</strong> <?= $prevCount ?> interventions (<?= $prevPct ?>%)</span>
    </div>
    <div class="stat-legend-item">
      <span class="stat-legend-dot" style="background:var(--red-500)"></span>
      <span><strong>Correctif :</strong> <?= $corrCount ?> interventions (<?= $corrPct ?>%)</span>
    </div>
  </div>
</div>

<!-- Grille 2 cols : Motifs pannes + Departements -->
<div class="grid-2">

  <!-- Motifs de pannes — barres horizontales -->
  <div class="card">
    <div class="card-header">
      <h2>Motifs de pannes les plus frequents</h2>
      <span class="count-badge"><?= count($failureReasons) ?> causes</span>
    </div>

    <?php if (!empty($failureReasons)): ?>
    <div class="chart-bars">
      <?php foreach ($failureReasons as $fr):
        $pct = round(($fr['cnt'] / $totalFailures) * 100);
      ?>
      <div class="chart-bar-row">
        <div class="chart-bar-label">
          <span class="chart-bar-name"><?= htmlspecialchars($fr['reason']) ?></span>
          <span class="chart-bar-val"><?= $fr['cnt'] ?> (<?= $pct ?>%)</span>
        </div>
        <div class="chart-bar-track">
          <div class="chart-bar-fill" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="empty">Aucune panne recensee a ce jour.</p>
    <?php endif; ?>
  </div>

  <!-- Par departement -->
  <div class="card">
    <div class="card-header">
      <h2>Repartition par departement</h2>
    </div>

    <?php if (!empty($deptStats)): ?>
    <div class="chart-bars">
      <?php
      $maxDept = max(1, ...array_column($deptStats, 'total'));
      foreach ($deptStats as $ds):
        $tot  = (int)$ds['total'];
        $pPct = $tot > 0 ? round((int)$ds['prev_count'] / $tot * 100) : 0;
        $cPct = 100 - $pPct;
        $barW = $maxDept > 0 ? round($tot / $maxDept * 100) : 0;
      ?>
      <div class="chart-bar-row">
        <div class="chart-bar-label">
          <span class="chart-bar-name"><?= htmlspecialchars($ds['dept_name']) ?></span>
          <span class="chart-bar-val">
            <span style="color:var(--green-600)"><?= (int)$ds['prev_count'] ?> prev</span>
            &nbsp;/&nbsp;
            <span style="color:var(--red-500)"><?= (int)$ds['corr_count'] ?> corr</span>
          </span>
        </div>
        <div class="chart-bar-track">
          <div style="display:flex;height:100%">
            <div style="width:<?= $pPct ?>%;background:var(--green-400)"></div>
            <div style="width:<?= $cPct ?>%;background:var(--red-400, #f87171)"></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="empty">Aucune donnee departementale.</p>
    <?php endif; ?>
  </div>

</div>

<!-- Evolution mensuelle (barres groupees) -->
<?php if (!empty($monthly)): ?>
<div class="card" style="margin-bottom:22px">
  <div class="card-header">
    <h2>Evolution mensuelle des interventions</h2>
    <span class="count-badge"><?= count($monthly) ?> mois</span>
  </div>

  <div style="overflow-x:auto">
    <div style="display:flex;align-items:flex-end;gap:12px;min-width:500px;height:150px;padding:0 4px">
      <?php foreach ($monthly as $m):
        $tot     = (int)$m['prev'] + (int)$m['corr'];
        $hPrev   = $maxMonth > 0 ? round((int)$m['prev'] / $maxMonth * 130) : 0;
        $hCorr   = $maxMonth > 0 ? round((int)$m['corr'] / $maxMonth * 130) : 0;
        $label   = substr($m['mois'], 5, 2) . '/' . substr($m['mois'], 2, 2);
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;min-width:40px">
        <div style="display:flex;align-items:flex-end;gap:2px">
          <div title="Preventif: <?= $m['prev'] ?>"
               style="width:14px;height:<?= $hPrev ?>px;background:var(--green-400);border-radius:3px 3px 0 0;transition:height .6s ease"></div>
          <div title="Correctif: <?= $m['corr'] ?>"
               style="width:14px;height:<?= $hCorr ?>px;background:var(--red-400,#f87171);border-radius:3px 3px 0 0;transition:height .6s ease"></div>
        </div>
        <span style="font-size:10px;color:var(--text-muted);font-weight:600"><?= $label ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="stat-legend" style="margin-top:12px">
    <div class="stat-legend-item">
      <span class="stat-legend-dot" style="background:var(--green-400)"></span>
      <span>Preventif</span>
    </div>
    <div class="stat-legend-item">
      <span class="stat-legend-dot" style="background:#f87171"></span>
      <span>Correctif</span>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Equipements critiques -->
<div class="card">
  <div class="card-header">
    <h2>Equipements les plus sollicites</h2>
    <span class="count-badge">Priorites d'action</span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Equipement</th>
          <th>Departement</th>
          <th style="text-align:center">Total interventions</th>
          <th style="text-align:center">Pannes</th>
          <th>Criticite</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($topEquipmentFailures)): foreach ($topEquipmentFailures as $te):
          $failCount = (int)$te['failure_count'];
        ?>
        <tr>
          <td>
            <strong style="color:var(--text-primary)"><?= htmlspecialchars($te['designation']) ?></strong><br>
            <code style="font-size:11.5px;color:var(--text-muted)"><?= htmlspecialchars($te['serial_number']) ?></code>
          </td>
          <td><span class="dept-badge"><?= htmlspecialchars($te['dept_name'] ?? 'Non affecte') ?></span></td>
          <td style="text-align:center;font-weight:700"><?= (int)$te['total_wo'] ?></td>
          <td style="text-align:center">
            <span class="badge <?= $failCount > 0 ? 'red' : 'green' ?>"><?= $failCount ?></span>
          </td>
          <td>
            <?php if ($failCount >= 2): ?>
              <span class="badge red-pulse">Haute criticite</span>
            <?php elseif ($failCount === 1): ?>
              <span class="badge yellow">Surveillance</span>
            <?php else: ?>
              <span class="badge green">Fiable</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" class="empty">Aucun equipement critique repertorie.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>