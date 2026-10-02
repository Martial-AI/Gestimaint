<?php
// pages/statistiques.php — Analyse & Statistiques Préventif vs Correctif et Motifs de pannes
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

// ── Statistiques globales ─────────────────────────────────
$statsSummary = [
    'total'       => 0,
    'preventive'  => 0,
    'corrective'  => 0,
    'ameliorative'=> 0,
    'terminee'    => 0,
    'en_cours'    => 0,
];

try {
    $res = $db->query("
        SELECT
            COUNT(*)                          AS total,
            SUM(kind = 'preventive')          AS preventive,
            SUM(kind = 'corrective')          AS corrective,
            SUM(kind = 'ameliorative')        AS ameliorative,
            SUM(status = 'terminee')          AS terminee,
            SUM(status IN ('ouverte','en_cours')) AS en_cours
        FROM work_orders
    ")->fetch();
    if ($res) $statsSummary = $res;
} catch (Exception $e) {}

$totalWO    = max(1, (int)$statsSummary['total']);
$prevCount  = (int)($statsSummary['preventive'] ?? 0);
$corrCount  = (int)($statsSummary['corrective'] ?? 0);
$prevPct    = round(($prevCount / $totalWO) * 100);
$corrPct    = 100 - $prevPct;

// ── Motifs de pannes fréquents ────────────────────────────
$failureReasons = [];
try {
    $failureReasons = $db->query("
        SELECT
            COALESCE(NULLIF(TRIM(failure_reason), ''), 'Cause indéterminée') AS reason,
            COUNT(*) AS count
        FROM work_orders
        WHERE kind = 'corrective' OR failure_reason IS NOT NULL
        GROUP BY reason
        ORDER BY count DESC
        LIMIT 8
    ")->fetchAll();
} catch (Exception $e) {}

$totalFailures = array_sum(array_column($failureReasons, 'count')) ?: 1;

// ── Pannes par département ────────────────────────────────
$deptStats = [];
try {
    $deptStats = $db->query("
        SELECT
            COALESCE(d.name, 'Non affecté') AS dept_name,
            COUNT(w.id)                     AS total_interventions,
            SUM(w.kind = 'corrective')      AS corrective_count,
            SUM(w.kind = 'preventive')      AS preventive_count
        FROM work_orders w
        JOIN equipment e ON e.id = w.equipment_id
        LEFT JOIN departments d ON d.id = e.department_id
        GROUP BY d.id
        ORDER BY corrective_count DESC
    ")->fetchAll();
} catch (Exception $e) {}

// ── Équipements les plus sollicités en maintenance ────────
$topEquipmentFailures = [];
try {
    $topEquipmentFailures = $db->query("
        SELECT
            e.serial_number, e.designation, d.name AS dept_name,
            COUNT(w.id) AS total_wo,
            SUM(w.kind = 'corrective') AS failure_count
        FROM work_orders w
        JOIN equipment e ON e.id = w.equipment_id
        LEFT JOIN departments d ON d.id = e.department_id
        GROUP BY e.id
        ORDER BY failure_count DESC, total_wo DESC
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {}

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

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── KPIs Statistiques ─────────────────────────────────── -->
<div class="kpis">
  <div class="card kpi good">
    <span class="kpi-icon">🛡️</span>
    <small>Maintenances Préventives</small>
    <strong><?= $prevCount ?> <span style="font-size:16px;font-weight:600;color:var(--green-600)">(<?= $prevPct ?>%)</span></strong>
  </div>

  <div class="card kpi <?= $corrCount > 0 ? 'danger' : 'neutral' ?>">
    <span class="kpi-icon">🚨</span>
    <small>Maintenances Correctives (Pannes)</small>
    <strong><?= $corrCount ?> <span style="font-size:16px;font-weight:600;color:var(--red-500)">(<?= $corrPct ?>%)</span></strong>
  </div>

  <div class="card kpi neutral">
    <span class="kpi-icon">📊</span>
    <small>Total Interventions GMAO</small>
    <strong><?= (int)$statsSummary['total'] ?></strong>
  </div>

  <div class="card kpi good">
    <span class="kpi-icon">✅</span>
    <small>Interventions Clôturées</small>
    <strong><?= (int)($statsSummary['terminee'] ?? 0) ?></strong>
  </div>
</div>

<!-- ── Boîte de Comparaison Préventif vs Correctif ────────── -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <h2>⚖️ Équilibre Maintenance Préventive vs Corrective</h2>
    <span class="count-badge" style="background:<?= $prevPct >= 60 ? 'var(--green-100)' : 'var(--red-100)' ?>;color:<?= $prevPct >= 60 ? 'var(--green-700)' : 'var(--red-600)' ?>">
      <?= $prevPct >= 60 ? '✓ Ratio préventif sain' : '⚠ Trop de curatif' ?>
    </span>
  </div>

  <p style="font-size:13.5px;color:var(--text-secondary);margin-bottom:14px">
    Une gestion optimale de parc industriel vise un taux de <strong>maintenance préventive supérieur à 70%</strong> afin de minimiser les arrêts imprévus de production.
  </p>

  <!-- Jauge proportionnelle bicolore -->
  <div class="stat-split-bar">
    <div class="stat-split-prev" style="width: <?= $prevPct ?>%">
      <?= $prevPct > 12 ? "Préventif {$prevPct}%" : '' ?>
    </div>
    <div class="stat-split-corr" style="width: <?= $corrPct ?>%">
      <?= $corrPct > 12 ? "Correctif {$corrPct}%" : '' ?>
    </div>
  </div>

  <div class="stat-legend">
    <div class="stat-legend-item">
      <span class="stat-legend-dot" style="background:var(--green-500)"></span>
      <span><strong>Préventif (Planifié) :</strong> <?= $prevCount ?> interventions (<?= $prevPct ?>%)</span>
    </div>
    <div class="stat-legend-item">
      <span class="stat-legend-dot" style="background:var(--red-500)"></span>
      <span><strong>Correctif (Pannes &amp; Urgences) :</strong> <?= $corrCount ?> interventions (<?= $corrPct ?>%)</span>
    </div>
  </div>
</div>

<!-- ── Grille 2 colonnes : Motifs de pannes & Par Département ── -->
<div class="grid-2">

  <!-- Motifs fréquents de pannes -->
  <div class="card">
    <div class="card-header">
      <h2>🔍 Principaux Motifs &amp; Causes de Pannes</h2>
      <span class="count-badge"><?= count($failureReasons) ?> causes</span>
    </div>

    <?php if (!empty($failureReasons)): ?>
      <div style="display:flex;flex-direction:column;gap:14px">
        <?php foreach ($failureReasons as $fr):
          $pct = round(($fr['count'] / $totalFailures) * 100);
        ?>
        <div class="failure-item">
          <div class="failure-info">
            <span class="failure-name">
              <span>⚠️</span>
              <?= htmlspecialchars($fr['reason']) ?>
            </span>
            <span class="failure-count">
              <?= $fr['count'] ?> panne<?= $fr['count'] > 1 ? 's' : '' ?> (<?= $pct ?>%)
            </span>
          </div>
          <div class="failure-bar-track">
            <div class="failure-bar-fill" style="width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="empty">Aucune panne recensée à ce jour.</p>
    <?php endif; ?>
  </div>

  <!-- Pannes par département -->
  <div class="card">
    <div class="card-header">
      <h2>🏢 Répartition par Département</h2>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Département</th>
            <th style="text-align:center">Préventif</th>
            <th style="text-align:center">Correctif</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($deptStats)): foreach ($deptStats as $ds): ?>
          <tr>
            <td style="font-weight:600;color:var(--text-primary)">
              <?= htmlspecialchars($ds['dept_name']) ?>
            </td>
            <td style="text-align:center">
              <span class="badge green"><?= (int)$ds['preventive_count'] ?></span>
            </td>
            <td style="text-align:center">
              <span class="badge red"><?= (int)$ds['corrective_count'] ?></span>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="3" class="empty">Aucune donnée départementale.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /.grid-2 -->

<!-- ── Équipements les plus critiques (Top incidents) ────── -->
<div class="card section-gap">
  <div class="card-header">
    <h2>🎯 Équipements ayant subi le plus d'incidents</h2>
    <span class="count-badge">Priorités d'action</span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Équipement concerné</th>
          <th>Département</th>
          <th style="text-align:center">Total interventions</th>
          <th style="text-align:center">Nombre de pannes</th>
          <th>Niveau d'alerte</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($topEquipmentFailures)): foreach ($topEquipmentFailures as $te):
          $failCount = (int)$te['failure_count'];
        ?>
        <tr>
          <td>
            <strong style="color:var(--text-primary)"><?= htmlspecialchars($te['designation']) ?></strong><br>
            <code><?= htmlspecialchars($te['serial_number']) ?></code>
          </td>
          <td>
            <span class="dept-badge"><?= htmlspecialchars($te['dept_name'] ?? 'Non affecté') ?></span>
          </td>
          <td style="text-align:center;font-weight:600">
            <?= (int)$te['total_wo'] ?>
          </td>
          <td style="text-align:center">
            <span class="badge red" style="font-weight:700"><?= $failCount ?> panne<?= $failCount > 1 ? 's' : '' ?></span>
          </td>
          <td>
            <?php if ($failCount >= 2): ?>
              <span class="badge red-pulse">🚨 Haute criticité</span>
            <?php elseif ($failCount === 1): ?>
              <span class="badge red" style="background:var(--red-50);color:var(--red-500);border-color:var(--red-200)">Sous surveillance</span>
            <?php else: ?>
              <span class="badge green">Fiable</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" class="empty">Aucun équipement critique répertorié.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
