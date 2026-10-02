<?php
// pages/equipements.php — Gestion du parc d'équipements & départements
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

$form_error   = '';
$form_success = '';

// ── Traitement POST : Actions diverses ────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Ajouter un équipement
    if ($action === 'add_equipment') {
        $sn      = trim($_POST['serial_number'] ?? '');
        $model   = trim($_POST['model'] ?? '');
        $desig   = trim($_POST['designation'] ?? '');
        $cat     = trim($_POST['category'] ?? '');
        $dept_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $mfr     = trim($_POST['manufacturer'] ?? '') ?: null;
        $stat    = $_POST['status'] ?? 'en_service';
        $reason  = ($stat === 'en_panne') ? trim($_POST['failure_reason'] ?? 'Panne déclarée') : null;
        $next    = !empty($_POST['next_maintenance_at']) ? $_POST['next_maintenance_at'] : null;

        if (!$sn || !$model || !$desig || !$cat) {
            $form_error = 'Numéro de série, modèle, désignation et catégorie sont requis.';
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO equipment (serial_number, model, designation, category, department_id, manufacturer, status, failure_reason, next_maintenance_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$sn, $model, $desig, $cat, $dept_id, $mfr, $stat, $reason, $next]);
                $new_id = $db->lastInsertId();

                // Si créé directement en panne, créer l'OT correspondant
                if ($stat === 'en_panne') {
                    $ref = 'OT-' . date('Y') . '-' . rand(100, 999);
                    $db->prepare("
                        INSERT INTO work_orders (reference, equipment_id, kind, priority, status, description, failure_reason)
                        VALUES (?, ?, 'corrective', 'urgente', 'ouverte', ?, ?)
                    ")->execute([$ref, $new_id, "Déclaration de panne initiale : {$reason}", $reason]);
                }

                $form_success = "Équipement « {$desig} » ajouté avec succès.";
            } catch (PDOException $e) {
                $form_error = str_contains($e->getMessage(), 'Duplicate')
                    ? 'Ce numéro de série existe déjà dans la base.'
                    : 'Erreur SQL : ' . $e->getMessage();
            }
        }
    }

    // 2. Supprimer un équipement
    if ($action === 'delete_equipment') {
        $id = (int)($_POST['equipment_id'] ?? 0);
        if ($id > 0) {
            try {
                $db->prepare("DELETE FROM equipment WHERE id = ?")->execute([$id]);
                $form_success = "L'équipement a été supprimé du parc.";
            } catch (Exception $e) {
                $form_error = "Impossible de supprimer cet équipement : " . $e->getMessage();
            }
        }
    }

    // 3. Changement d'état rapide (en_panne, en_maintenance, repare / en_service)
    if ($action === 'change_status') {
        $id         = (int)($_POST['equipment_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        $reason     = trim($_POST['failure_reason'] ?? '');

        if ($id > 0 && in_array($new_status, ['en_service', 'en_panne', 'en_maintenance'])) {
            try {
                if ($new_status === 'en_panne') {
                    $failure_label = $reason ?: 'Défaut mécanique/électrique non spécifié';
                    $db->prepare("UPDATE equipment SET status = 'en_panne', failure_reason = ? WHERE id = ?")
                       ->execute([$failure_label, $id]);

                    // Créer automatiquement un Ordre de Travail correctif
                    $ref = 'OT-' . date('Y') . '-' . rand(100, 999);
                    $db->prepare("
                        INSERT INTO work_orders (reference, equipment_id, kind, priority, status, description, failure_reason)
                        VALUES (?, ?, 'corrective', 'urgente', 'ouverte', ?, ?)
                    ")->execute([$ref, $id, "Panne signalée : {$failure_label}", $failure_label]);

                    // Notification
                    $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type)
                        VALUES (1, 'Équipement tombé en panne', ?, 'alert')
                    ")->execute(["L'équipement #{$id} a été déclaré en panne : {$failure_label}."]);

                    $form_success = "Équipement passé en état « En panne ». Ordre de travail d'urgence généré.";
                } elseif ($new_status === 'en_maintenance') {
                    $db->prepare("UPDATE equipment SET status = 'en_maintenance' WHERE id = ?")->execute([$id]);
                    $form_success = "Équipement passé « En maintenance ». Intervention en cours.";
                } elseif ($new_status === 'en_service') {
                    // Réparé / En service
                    $db->prepare("UPDATE equipment SET status = 'en_service', failure_reason = NULL WHERE id = ?")->execute([$id]);
                    // Clôturer les OT en cours sur cet équipement
                    $db->prepare("UPDATE work_orders SET status = 'terminee' WHERE equipment_id = ? AND status IN ('ouverte', 'en_cours')")
                       ->execute([$id]);
                    $form_success = "Équipement réparé avec succès ! Remis en service opérationnel.";
                }
            } catch (Exception $e) {
                $form_error = "Erreur lors du changement d'état : " . $e->getMessage();
            }
        }
    }

    // 4. Ajouter un département
    if ($action === 'add_department') {
        $d_name = trim($_POST['department_name'] ?? '');
        $d_desc = trim($_POST['department_desc'] ?? '');
        if ($d_name) {
            try {
                $db->prepare("INSERT INTO departments (name, description) VALUES (?, ?)")->execute([$d_name, $d_desc]);
                $form_success = "Département « {$d_name} » créé avec succès.";
            } catch (PDOException $e) {
                $form_error = str_contains($e->getMessage(), 'Duplicate')
                    ? 'Ce département existe déjà.'
                    : 'Erreur : ' . $e->getMessage();
            }
        }
    }
}

// ── Liste des départements ────────────────────────────────
$departments = [];
try {
    $departments = $db->query("
        SELECT d.*, COUNT(e.id) AS equipment_count
        FROM departments d
        LEFT JOIN equipment e ON e.department_id = d.id AND e.active = 1
        GROUP BY d.id
        ORDER BY d.name ASC
    ")->fetchAll();
} catch (Exception $e) {}

// ── Filtres & Recherche ───────────────────────────────────
$q             = trim($_GET['q'] ?? '');
$status        = $_GET['status'] ?? '';
$department_id = !empty($_GET['dept']) ? (int)$_GET['dept'] : 0;
$like          = "%{$q}%";

$sql = "SELECT e.*, d.name AS dept_name, l.site, l.zone
        FROM equipment e
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN locations l ON l.id = e.location_id
        WHERE e.active = 1
          AND (e.serial_number LIKE ? OR e.model LIKE ? OR e.designation LIKE ? OR e.category LIKE ? OR IFNULL(d.name,'') LIKE ?)";
$params = [$like, $like, $like, $like, $like];

if ($status !== '') {
    $sql .= " AND e.status = ?";
    $params[] = $status;
}

if ($department_id > 0) {
    $sql .= " AND e.department_id = ?";
    $params[] = $department_id;
}

$sql .= " ORDER BY e.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Total général pour le filtre "Tous"
$totalEquipmentCount = (int)$db->query("SELECT COUNT(*) FROM equipment WHERE active = 1")->fetchColumn();

// ── Helpers ──────────────────────────────────────────────
function statusBadge(string $s, ?string $reason = null): string {
    $lbl = str_replace('_', ' ', $s);
    if ($s === 'en_service')     $lbl = 'En service';
    if ($s === 'en_panne')       $lbl = 'En panne';
    if ($s === 'en_maintenance') $lbl = 'En maintenance';

    $out = '<span class="badge status-' . htmlspecialchars($s) . '">' . htmlspecialchars($lbl) . '</span>';
    if ($s === 'en_panne' && $reason) {
        $out .= '<div style="font-size:11px;color:var(--red-500);font-weight:600;margin-top:4px">⚠ ' . htmlspecialchars($reason) . '</div>';
    }
    return $out;
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

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Navigation par Départements (Pills) ────────────────── -->
<div class="dept-nav">
  <a href="<?= ($base_url ?: '') ?>/pages/equipements.php?<?= http_build_query(array_merge($_GET, ['dept' => 0])) ?>"
     class="dept-pill <?= $department_id === 0 ? 'active' : '' ?>">
    🏢 Tous les départements (<?= $totalEquipmentCount ?>)
  </a>

  <?php foreach ($departments as $d): ?>
    <a href="<?= ($base_url ?: '') ?>/pages/equipements.php?<?= http_build_query(array_merge($_GET, ['dept' => $d['id']])) ?>"
       class="dept-pill <?= $department_id === (int)$d['id'] ? 'active' : '' ?>">
      <?= htmlspecialchars($d['name']) ?> (<?= $d['equipment_count'] ?>)
    </a>
  <?php endforeach; ?>

  <button class="dept-pill" style="border-style:dashed;color:var(--green-600);background:var(--green-50)" onclick="openDeptModal()">
    + Nouveau département
  </button>
</div>

<!-- ── Barre d'outils & filtres ──────────────────────────── -->
<div class="toolbar">
  <form method="get" class="filters" id="filterForm">
    <?php if ($department_id > 0): ?>
      <input type="hidden" name="dept" value="<?= $department_id ?>">
    <?php endif; ?>

    <input id="search" name="q"
           placeholder="🔍  Rechercher équipement, série, modèle, atelier…"
           value="<?= htmlspecialchars($q) ?>"
           style="width: 320px;">

    <select name="status" id="statusFilter" onchange="document.getElementById('filterForm').submit()">
      <option value="">Tous les statuts</option>
      <option value="en_service" <?= $status === 'en_service' ? 'selected' : '' ?>>En service (Opérationnel)</option>
      <option value="en_panne" <?= $status === 'en_panne' ? 'selected' : '' ?>>En panne (Alerte)</option>
      <option value="en_maintenance" <?= $status === 'en_maintenance' ? 'selected' : '' ?>>En maintenance</option>
      <option value="reforme" <?= $status === 'reforme' ? 'selected' : '' ?>>Réformé</option>
    </select>
  </form>

  <div style="display:flex;gap:10px">
    <button class="btn btn-primary" onclick="openEquipModal()">
      <span>➕</span> Nouvel équipement
    </button>
  </div>
</div>

<!-- ── Messages de retour ────────────────────────────────── -->
<?php if ($form_success): ?>
  <div class="alert-success">✅ <?= htmlspecialchars($form_success) ?></div>
<?php endif; ?>
<?php if ($form_error): ?>
  <div class="error">⚠ <?= htmlspecialchars($form_error) ?></div>
<?php endif; ?>

<!-- ── Carte Tableau ─────────────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <h2>
      ⚙️ Parc d'équipements
      <?php if ($department_id > 0):
        $currentDeptName = '';
        foreach ($departments as $d) if ((int)$d['id'] === $department_id) $currentDeptName = $d['name'];
      ?>
        <span style="font-weight:400;color:var(--text-muted);font-size:14px">· Rayon : <?= htmlspecialchars($currentDeptName) ?></span>
      <?php endif; ?>
    </h2>
    <span class="count-badge"><?= count($data) ?> équipement(s)</span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Équipement &amp; Modèle</th>
          <th>Département</th>
          <th>Catégorie</th>
          <th>Prochaine maintenance</th>
          <th>État actuel</th>
          <th style="text-align:center">Changer d'état (Suivi)</th>
          <th style="width:50px"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($data)): foreach ($data as $e):
          $st = $e['status'];
        ?>
        <tr>
          <td>
            <strong style="color:var(--text-primary)"><?= htmlspecialchars($e['designation']) ?></strong><br>
            <code style="font-size:11px"><?= htmlspecialchars($e['serial_number']) ?></code>
            <small style="color:var(--text-muted);margin-left:6px">
              <?= htmlspecialchars(trim(($e['manufacturer'] ?? '') . ' ' . $e['model'])) ?>
            </small>
          </td>

          <td>
            <span class="dept-badge">
              🏷️ <?= htmlspecialchars($e['dept_name'] ?? 'Non affecté') ?>
            </span>
          </td>

          <td style="color:var(--text-secondary)"><?= htmlspecialchars($e['category']) ?></td>

          <td><?= fmtDate($e['next_maintenance_at']) ?></td>

          <td><?= statusBadge($e['status'], $e['failure_reason']) ?></td>

          <!-- Actions de suivi / cycle de vie de l'équipement -->
          <td style="text-align:center">
            <div class="action-buttons" style="justify-content:center">
              <?php if ($st !== 'en_panne'): ?>
                <button type="button" class="btn-action-status btn-to-broken"
                        onclick="promptBreakdown(<?= $e['id'] ?>, '<?= htmlspecialchars(addslashes($e['designation'])) ?>')"
                        title="Signaler cet équipement comme tombé en panne">
                  🚨 En panne
                </button>
              <?php endif; ?>

              <?php if ($st === 'en_panne' || $st === 'en_service'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="change_status">
                  <input type="hidden" name="equipment_id" value="<?= $e['id'] ?>">
                  <input type="hidden" name="new_status" value="en_maintenance">
                  <button type="submit" class="btn-action-status btn-to-maintenance"
                          title="Lancer l'intervention de maintenance">
                    🔧 En maintenance
                  </button>
                </form>
              <?php endif; ?>

              <?php if ($st === 'en_panne' || $st === 'en_maintenance'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Confirmer la remise en service de cet équipement réparé ?');">
                  <input type="hidden" name="action" value="change_status">
                  <input type="hidden" name="equipment_id" value="<?= $e['id'] ?>">
                  <input type="hidden" name="new_status" value="en_service">
                  <button type="submit" class="btn-action-status btn-to-repaired"
                          title="Valider la réparation et remettre en service">
                    ✅ Réparé
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </td>

          <!-- Bouton suppression d'équipement -->
          <td style="text-align:right">
            <form method="post" style="display:inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet équipement ?');">
              <input type="hidden" name="action" value="delete_equipment">
              <input type="hidden" name="equipment_id" value="<?= $e['id'] ?>">
              <button type="submit" class="btn-delete-row" title="Supprimer cet équipement">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="7" class="empty">
            🔍 Aucun équipement trouvé<?= $q ? " pour le terme « " . htmlspecialchars($q) . " »" : '' ?>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Modal 1 : Ajouter un équipement ───────────────────── -->
<div id="equipModal" class="modal hidden">
  <form method="post" class="modal-card">
    <input type="hidden" name="action" value="add_equipment">
    <button type="button" class="modal-close" onclick="closeEquipModal()" aria-label="Fermer">×</button>
    <h2>➕ Ajouter un équipement au département</h2>

    <div class="form-grid">
      <label>
        Numéro de série
        <input name="serial_number" placeholder="Ex: EQ-2026-010" required>
      </label>
      <label>
        Modèle technique
        <input name="model" placeholder="Ex: CX-5000" required>
      </label>

      <label style="grid-column: span 2;">
        Désignation complète
        <input name="designation" placeholder="Ex: Compresseur d'air à vis haute pression" required>
      </label>

      <label>
        Département / Rayon
        <select name="department_id" required>
          <option value="">Sélectionnez un département</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $department_id === (int)$d['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($d['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Catégorie d'équipement
        <input name="category" placeholder="Ex: Compresseurs, Usinage, Robotique..." required>
      </label>

      <label>
        Fabricant / Marque
        <input name="manufacturer" placeholder="Ex: Siemens, Atlas Copco...">
      </label>

      <label>
        Statut initial
        <select name="status" id="newEquipStatus" onchange="toggleReasonField()">
          <option value="en_service">En service (Opérationnel)</option>
          <option value="en_panne">En panne (Déclarer panne)</option>
          <option value="en_maintenance">En maintenance</option>
          <option value="reforme">Réformé</option>
        </select>
      </label>

      <label id="reasonField" style="display:none; grid-column: span 2;">
        Motif de panne initial
        <input name="failure_reason" placeholder="Ex: Surchauffe moteur, fuite huile...">
      </label>

      <label style="grid-column: span 2;">
        Date de prochaine maintenance préventive
        <input name="next_maintenance_at" type="date">
      </label>
    </div>

    <div class="form-actions">
      <button type="button" class="btn btn-secondary" onclick="closeEquipModal()">Annuler</button>
      <button type="submit" class="btn btn-primary">💾 Enregistrer l'équipement</button>
    </div>
  </form>
</div>

<!-- ── Modal 2 : Déclarer une panne (avec Motif) ─────────── -->
<div id="breakdownModal" class="modal hidden">
  <form method="post" class="modal-card">
    <input type="hidden" name="action" value="change_status">
    <input type="hidden" name="new_status" value="en_panne">
    <input type="hidden" name="equipment_id" id="breakdown_equip_id">
    <button type="button" class="modal-close" onclick="closeBreakdownModal()" aria-label="Fermer">×</button>

    <h2 style="color:var(--red-500)">🚨 Déclarer une panne</h2>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:18px">
      Équipement concerné : <strong id="breakdown_equip_name"></strong>
    </p>

    <div style="margin-bottom:20px">
      <label style="display:block;font-size:12.5px;font-weight:600;margin-bottom:8px">
        Motif ou cause constatée de la panne :
      </label>
      <select name="failure_reason" style="width:100%;margin-bottom:12px;" id="breakdown_preset" onchange="checkCustomReason(this)">
        <option value="Surchauffe moteur / broche">Surchauffe moteur / broche</option>
        <option value="Court-circuit électrique">Court-circuit électrique</option>
        <option value="Usure mécanique des pièces">Usure mécanique des pièces</option>
        <option value="Fuite hydraulique / pneumatique">Fuite hydraulique / pneumatique</option>
        <option value="Défaut capteur / automate">Défaut capteur / automate</option>
        <option value="Bourrage matière / blocage">Bourrage matière / blocage</option>
        <option value="Autre motif">Autre motif (personnalisé)...</option>
      </select>

      <input type="text" id="breakdown_custom" name="failure_reason_custom"
             placeholder="Précisez le motif de la panne..."
             style="display:none;width:100%">
    </div>

    <div class="form-actions">
      <button type="button" class="btn btn-secondary" onclick="closeBreakdownModal()">Annuler</button>
      <button type="submit" class="btn btn-danger">🚨 Valider et déclarer la panne</button>
    </div>
  </form>
</div>

<!-- ── Modal 3 : Ajouter un Département ──────────────────── -->
<div id="deptModal" class="modal hidden">
  <form method="post" class="modal-card">
    <input type="hidden" name="action" value="add_department">
    <button type="button" class="modal-close" onclick="closeDeptModal()" aria-label="Fermer">×</button>

    <h2>🏢 Créer un nouveau département</h2>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:18px">
      Permet de regrouper et filtrer les équipements par zone de production ou unité opérationnelle.
    </p>

    <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:22px">
      <label style="display:flex;flex-direction:column;gap:6px;font-size:12.5px;font-weight:600;color:var(--text-secondary)">
        Nom du département
        <input name="department_name" placeholder="Ex: Peinture & Traitement thermique" required>
      </label>
      <label style="display:flex;flex-direction:column;gap:6px;font-size:12.5px;font-weight:600;color:var(--text-secondary)">
        Description / Localisation
        <input name="department_desc" placeholder="Ex: Bâtiment C, secteur finitions">
      </label>
    </div>

    <div class="form-actions">
      <button type="button" class="btn btn-secondary" onclick="closeDeptModal()">Annuler</button>
      <button type="submit" class="btn btn-primary">🏢 Créer le département</button>
    </div>
  </form>
</div>

<script>
  function openEquipModal()  { document.getElementById('equipModal').classList.remove('hidden'); }
  function closeEquipModal() { document.getElementById('equipModal').classList.add('hidden'); }

  function openDeptModal()   { document.getElementById('deptModal').classList.remove('hidden'); }
  function closeDeptModal()  { document.getElementById('deptModal').classList.add('hidden'); }

  function promptBreakdown(id, name) {
    document.getElementById('breakdown_equip_id').value = id;
    document.getElementById('breakdown_equip_name').textContent = name;
    document.getElementById('breakdownModal').classList.remove('hidden');
  }
  function closeBreakdownModal() {
    document.getElementById('breakdownModal').classList.add('hidden');
  }

  function checkCustomReason(sel) {
    const custom = document.getElementById('breakdown_custom');
    if (sel.value === 'Autre motif') {
      custom.style.display = 'block';
      custom.required = true;
    } else {
      custom.style.display = 'none';
      custom.required = false;
    }
  }

  function toggleReasonField() {
    const st = document.getElementById('newEquipStatus').value;
    document.getElementById('reasonField').style.display = (st === 'en_panne') ? 'flex' : 'none';
  }

  // Auto-submit recherche avec délai
  let _timer;
  const searchInput = document.getElementById('search');
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(_timer);
      _timer = setTimeout(() => document.getElementById('filterForm').submit(), 350);
    });
  }

  // Fermer les modales au clic en dehors
  window.addEventListener('click', (e) => {
    ['equipModal', 'deptModal', 'breakdownModal'].forEach(id => {
      const el = document.getElementById(id);
      if (e.target === el) el.classList.add('hidden');
    });
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
