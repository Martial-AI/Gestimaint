<?php
// index.php — Tableau de bord principal GESTIMAINT (Seul fichier PHP à la racine)
require_once __DIR__ . '/includes/db.php';
$db = getDB();

// ── Requêtes dashboard ────────────────────────────────────
$equipment = [
    'total'       => 0,
    'operational' => 0,
    'broken'      => 0,
    'maintenance' => 0,
];

try {
    $res = $db->query("
        SELECT
            COUNT(*)                        AS total,
            SUM(status = 'en_service')      AS operational,
            SUM(status = 'en_panne')        AS broken,
            SUM(status = 'en_maintenance')  AS maintenance
        FROM equipment WHERE active = 1
    ")->fetch();
    if ($res) $equipment = $res;
} catch (Exception $e) {}

$overdue = 0;
try {
    $overdue = (int) $db->query(
        "SELECT COUNT(*) FROM equipment WHERE active=1 AND next_maintenance_at < CURDATE()"
    )->fetchColumn();
} catch (Exception $e) {}

$upcoming = [];
try {
    $upcoming = $db->query("
        SELECT e.serial_number, e.designation, e.status, e.failure_reason, e.next_maintenance_at,
               d.name AS dept_name, l.site
        FROM equipment e
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN locations l ON l.id = e.location_id
        WHERE e.active = 1
        ORDER BY e.next_maintenance_at ASC
        LIMIT 6
    ")->fetchAll();
} catch (Exception $e) {}

$work_orders = [];
try {
    $work_orders = $db->query("
        SELECT w.reference, w.kind, w.priority, w.status, w.failure_reason, w.planned_at,
               e.designation, d.name AS dept_name
        FROM work_orders w
        JOIN equipment e ON e.id = w.equipment_id
        LEFT JOIN departments d ON d.id = e.department_id
        ORDER BY w.created_at DESC
        LIMIT 6
    ")->fetchAll();
} catch (Exception $e) {}

// Stats préventif vs correctif pour le widget
$statsWO = ['preventive' => 0, 'corrective' => 0, 'total' => 0];
try {
    $resWO = $db->query("
        SELECT
            COUNT(*) AS total,
            SUM(kind = 'preventive') AS preventive,
            SUM(kind = 'corrective') AS corrective
        FROM work_orders
    ")->fetch();
    if ($resWO) $statsWO = $resWO;
} catch (Exception $e) {}

$totalWO = max(1, (int)$statsWO['total']);
$prevPct = round(((int)$statsWO['preventive'] / $totalWO) * 100);

$byCategory = [];
try {
    $byCategory = $db->query("
        SELECT category AS label, COUNT(*) AS value
        FROM equipment WHERE active = 1
        GROUP BY category
        ORDER BY value DESC
    ")->fetchAll();
} catch (Exception $e) {}

$maxCat = max(array_column($byCategory, 'value') ?: [1]);

// ── Helpers d'affichage ───────────────────────────────────
function statusBadge(string $s, ?string $reason = null): string {
    $lbl = str_replace('_', ' ', $s);
    if ($s === 'en_service')     $lbl = 'En service';
    if ($s === 'en_panne')       $lbl = 'En panne';
    if ($s === 'en_maintenance') $lbl = 'En maintenance';

    $out = '<span class="badge status-' . htmlspecialchars($s) . '">' . htmlspecialchars($lbl) . '</span>';
    if ($s === 'en_panne' && $reason) {
        $out .= '<div style="font-size:11px;color:var(--red-500);font-weight:600;margin-top:2px">⚠ ' . htmlspecialchars($reason) . '</div>';
    }
    return $out;
}

function priorityBadge(string $p): string {
    return '<span class="badge priority-' . htmlspecialchars($p) . '">'
         . htmlspecialchars(ucfirst($p)) . '</span>';
}

function fmtDate(?string $d): string {
    if (!$d) return '<span style="color:var(--text-muted)">—</span>';
    try {
        $dt = new DateTime($d);
        $overdue = $dt < new DateTime(date('Y-m-d'));
        $style = $overdue ? 'color:var(--red-500);font-weight:700' : 'color:var(--text-primary)';

        if (class_exists('IntlDateFormatter')) {
            $fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
            $formatted = $fmt->format($dt);
        } else {
            $months = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
            $m = (int)$dt->format('n');
            $formatted = $dt->format('j') . ' ' . ($months[$m] ?? '') . ' ' . $dt->format('Y');
        }
        return "<span style=\"{$style}\">{$formatted}</span>";
    } catch (Exception $e) {
        return htmlspecialchars((string)$d);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── KPIs du tableau de bord ────────────────────────────── -->
<div class="kpis">
  <div class="card kpi neutral">
    <span class="kpi-icon">🏭</span>
    <small>Parc d'équipements</small>
    <strong><?= (int)$equipment['total'] ?></strong>
  </div>

  <div class="card kpi good">
    <span class="kpi-icon">✅</span>
    <small>En service normal</small>
    <strong><?= (int)($equipment['operational'] ?? 0) ?></strong>
  </div>

  <!-- Alerte rouge clair rare : machines en panne -->
  <div class="card kpi <?= ($equipment['broken'] ?? 0) > 0 ? 'danger' : 'neutral' ?>">
    <span class="kpi-icon">⛔</span>
    <small>En panne critique</small>
    <strong><?= (int)($equipment['broken'] ?? 0) ?></strong>
  </div>

  <!-- Alerte rouge clair rare : retards maintenance -->
  <div class="card kpi <?= $overdue > 0 ? 'danger' : 'good' ?>">
    <span class="kpi-icon">⏰</span>
    <small>Échéances dépassées</small>
    <strong><?= $overdue ?></strong>
  </div>
</div>

<!-- ── Bannière Raccourci Statistiques ────────────────────── -->
<div class="card" style="margin-bottom:24px;background:linear-gradient(135deg, #ffffff 0%, var(--green-50) 100%);border-color:var(--green-200)">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
    <div style="display:flex;align-items:center;gap:14px">
      <div style="font-size:28px">📈</div>
      <div>
        <strong style="font-size:15px;color:var(--text-primary)">Ratio Maintenance Préventive : <?= $prevPct ?>%</strong>
        <p style="font-size:12.5px;color:var(--text-secondary);margin-top:2px">
          <?= (int)$statsWO['preventive'] ?> interventions préventives contre <?= (int)$statsWO['corrective'] ?> pannes curatives.
        </p>
      </div>
    </div>
    <a href="<?= ($base_url ?: '') ?>/pages/statistiques.php" class="btn btn-primary" style="font-size:12.5px;padding:7px 15px">
      Consulter les statistiques &amp; motifs de pannes →
    </a>
  </div>
</div>

<!-- ── Grille 2 colonnes ─────────────────────────────────── -->
<div class="grid-2">

  <!-- Prochaines maintenances -->
  <div class="card">
    <div class="card-header">
      <h2>📅 Prochaines échéances de maintenance</h2>
      <a href="<?= ($base_url ?: '') ?>/pages/equipements.php" style="font-size:12.5px;color:var(--green-600);font-weight:600;display:inline-flex;align-items:center;gap:4px">
        Gérer le parc &amp; départements →
      </a>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Équipement</th>
            <th>Département</th>
            <th>Échéance</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($upcoming)): foreach ($upcoming as $e): ?>
          <tr>
            <td>
              <strong style="color:var(--text-primary)"><?= htmlspecialchars($e['designation']) ?></strong><br>
              <code style="font-size:11px"><?= htmlspecialchars($e['serial_number']) ?></code>
            </td>
            <td>
              <span class="dept-badge"><?= htmlspecialchars($e['dept_name'] ?? 'Atelier central') ?></span>
            </td>
            <td><?= fmtDate($e['next_maintenance_at']) ?></td>
            <td><?= statusBadge($e['status'], $e['failure_reason']) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="4" class="empty">Aucune maintenance planifiée pour le moment</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Répartition par catégorie -->
  <div class="card">
    <div class="card-header">
      <h2>📊 Répartition par catégorie</h2>
    </div>
    <div class="bars">
      <?php foreach ($byCategory as $cat): ?>
      <div>
        <div class="bar-label">
          <span><?= htmlspecialchars($cat['label']) ?></span>
          <b><?= $cat['value'] ?> équipement<?= $cat['value'] > 1 ? 's' : '' ?></b>
        </div>
        <div class="bar-track">
          <div class="bar-fill" style="width:<?= round($cat['value'] / $maxCat * 100) ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($byCategory)): ?>
        <p style="color:var(--text-muted);font-size:13px;text-align:center;padding:24px 0">Aucune catégorie répertoriée</p>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /.grid-2 -->

<!-- ── Ordres de travail récents ─────────────────────────── -->
<div class="card section-gap">
  <div class="card-header">
    <h2>🔧 Ordres de travail &amp; interventions récentes</h2>
    <a href="<?= ($base_url ?: '') ?>/pages/maintenance.php" style="font-size:12.5px;color:var(--green-600);font-weight:600;display:inline-flex;align-items:center;gap:4px">
      Gestion des OT →
    </a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Référence</th>
          <th>Équipement &amp; Département</th>
          <th>Type d'OT</th>
          <th>Motif panne / détail</th>
          <th>Priorité</th>
          <th>Statut</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($work_orders)): foreach ($work_orders as $w):
          $isCorr = ($w['kind'] === 'corrective');
        ?>
        <tr>
          <td><code><?= htmlspecialchars($w['reference']) ?></code></td>
          <td>
            <strong style="color:var(--text-primary)"><?= htmlspecialchars($w['designation']) ?></strong><br>
            <span class="dept-badge" style="font-size:10.5px;margin-top:2px"><?= htmlspecialchars($w['dept_name'] ?? 'Général') ?></span>
          </td>
          <td>
            <span class="badge <?= $isCorr ? 'red' : 'green' ?>" style="font-size:11px">
              <?= $isCorr ? '🚨 Correctif' : '🛡️ Préventif' ?>
            </span>
          </td>
          <td style="color:var(--text-secondary);font-weight:500">
            <?= htmlspecialchars($w['failure_reason'] ?? 'Entretien courant') ?>
          </td>
          <td><?= priorityBadge($w['priority']) ?></td>
          <td><?= statusBadge($w['status']) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6" class="empty">Aucun ordre de travail récent</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
