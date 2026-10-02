<?php
require_once __DIR__ . '/../includes/db.php';
mb_internal_encoding('UTF-8');

$db = getDB();
$flash = '';

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_equipment') {
        $sn     = trim($_POST['serial_number'] ?? '');
        $mdl    = trim($_POST['model'] ?? '');
        $desig  = trim($_POST['designation'] ?? '');
        $deptId = (int)($_POST['department_id'] ?? 0);
        $cat    = trim($_POST['category'] ?? '');
        $mfr    = trim($_POST['manufacturer'] ?? '');
        $st     = $_POST['status'] ?? 'en_service';
        $nxt    = !empty($_POST['next_maintenance_at']) ? $_POST['next_maintenance_at'] : null;
        $fr     = trim($_POST['failure_reason'] ?? '');

        if ($sn === '' || $desig === '') {
            $flash = "danger|Veuillez renseigner au minimum le numero de serie et la designation.";
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO equipment (serial_number, model, designation, department_id, category, manufacturer, status, failure_reason, next_maintenance_at, active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $sn,
                    $mdl ?: null,
                    $desig,
                    $deptId > 0 ? $deptId : null,
                    $cat ?: null,
                    $mfr ?: null,
                    $st,
                    ($st === 'en_panne' && $fr !== '') ? $fr : null,
                    $nxt
                ]);
                $eqId = (int)$db->lastInsertId();

                if ($st === 'en_panne') {
                    $ref = 'OT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                    $db->prepare("
                        INSERT INTO work_orders (reference, equipment_id, kind, type, status, priority, description, failure_reason, created_at)
                        VALUES (?, ?, 'corrective', 'corrective', 'ouverte', 'haute', ?, ?, NOW())
                    ")->execute([$ref, $eqId, "Panne declaree a la creation de l'equipement", $fr ?: 'Panne initiale']);
                }

                $flash = "success|Equipement « {$desig} » ajoute avec succes.";
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry') || $e->getCode() == 23000) {
                    $flash = "danger|Le numero de serie « {$sn} » existe deja dans le systeme.";
                } else {
                    $flash = "danger|Erreur lors de l'ajout : " . htmlspecialchars($e->getMessage());
                }
            }
        }
    }

    if ($action === 'change_status') {
        $eqId    = (int)($_POST['equipment_id'] ?? 0);
        $newSt   = $_POST['new_status'] ?? '';
        $fr      = trim($_POST['failure_reason'] ?? '');
        $custom  = trim($_POST['failure_reason_custom'] ?? '');
        $fr = ($fr === 'Autre motif' && $custom !== '') ? $custom : $fr;

        if ($eqId > 0 && $newSt !== '') {
            $db->prepare("UPDATE equipment SET status = ?, failure_reason = ? WHERE id = ?")
               ->execute([$newSt, ($newSt === 'en_panne' ? $fr : null), $eqId]);

            if ($newSt === 'en_panne' && $fr !== '') {
                $ref = 'OT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                $db->prepare("
                    INSERT INTO work_orders (reference, equipment_id, kind, type, status, priority, description, failure_reason, created_at)
                    VALUES (?, ?, 'corrective', 'corrective', 'ouverte', 'haute', 'Panne signalee sur equipement', ?, NOW())
                ")->execute([$ref, $eqId, $fr]);

                // Notification automatique
                $equipName = $db->query("SELECT designation FROM equipment WHERE id = {$eqId}")->fetchColumn() ?: "Equipement #{$eqId}";
                $db->prepare("
                    INSERT INTO notifications (title, message, type, trigger_at, created_at)
                    VALUES (?, ?, 'alert', NOW(), NOW())
                ")->execute(["Alerte panne : {$equipName}", "L'equipement « {$equipName} » a ete declare en panne : {$fr}."]);
            }

            if ($newSt === 'en_service') {
                $db->prepare("
                    UPDATE work_orders SET status = 'terminee', completed_at = NOW()
                    WHERE equipment_id = ? AND status != 'terminee'
                ")->execute([$eqId]);
            }
            $flash = "success|Statut mis a jour avec succes.";
        }
    }

    if ($action === 'delete_equipment') {
        $eqId = (int)($_POST['equipment_id'] ?? 0);
        if ($eqId > 0) {
            $db->prepare("UPDATE equipment SET active = 0 WHERE id = ?")->execute([$eqId]);
            $flash = "success|Equipement supprime avec succes.";
        }
    }
}

// Filtres
$department_id = (int)($_GET['dept'] ?? 0);
$search        = trim($_GET['search'] ?? '');
$statusFilter  = $_GET['status'] ?? '';

// Liste departements
$departments = $db->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Equipements filtres
$where = ['e.active = 1'];
$params = [];
if ($department_id > 0) { $where[] = 'e.department_id = ?'; $params[] = $department_id; }
if ($search !== '')      { $where[] = "(e.designation LIKE ? OR e.serial_number LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($statusFilter !== '') { $where[] = 'e.status = ?'; $params[] = $statusFilter; }

$sql  = "SELECT e.*, d.name AS dept_name FROM equipment e LEFT JOIN departments d ON d.id = e.department_id";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY d.name, e.designation";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$equipements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statuts
$statusLabels = [
  'en_service'    => ['label' => 'En service',    'class' => 'green'],
  'en_panne'      => ['label' => 'En panne',       'class' => 'red'],
  'en_maintenance'=> ['label' => 'En maintenance', 'class' => 'yellow'],
  'reforme'       => ['label' => 'Reforme',        'class' => 'gray'],
];

// Compte par departement
$deptCounts = [];
foreach ($db->query("SELECT department_id, COUNT(*) AS cnt FROM equipment WHERE active=1 GROUP BY department_id")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $deptCounts[(int)$row['department_id']] = (int)$row['cnt'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): [$type, $msg] = explode('|', $flash, 2); ?>
<div class="flash <?= $type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Barre de filtres -->
<form id="filterForm" method="get">
  <div class="toolbar">
    <div class="toolbar-left">
      <div class="search-box">
        <span class="search-icon">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
        </span>
        <input id="search" name="search" type="text" placeholder="Rechercher un equipement..."
               value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="status" class="filter-select" onchange="document.getElementById('filterForm').submit()">
        <option value="">Tous les statuts</option>
        <option value="en_service"     <?= $statusFilter === 'en_service' ? 'selected' : '' ?>>En service</option>
        <option value="en_panne"       <?= $statusFilter === 'en_panne' ? 'selected' : '' ?>>En panne</option>
        <option value="en_maintenance" <?= $statusFilter === 'en_maintenance' ? 'selected' : '' ?>>En maintenance</option>
        <option value="reforme"        <?= $statusFilter === 'reforme' ? 'selected' : '' ?>>Reforme</option>
      </select>
      <?php if ($department_id > 0): ?>
        <input type="hidden" name="dept" value="<?= $department_id ?>">
      <?php endif; ?>
    </div>
    <div class="toolbar-right">
      <button type="button" class="btn btn-primary" onclick="openEquipModal()">Ajouter un equipement</button>
    </div>
  </div>
</form>

<!-- Navigation departements -->
<div class="dept-nav">
  <a href="equipements.php" class="dept-pill <?= $department_id === 0 ? 'active' : '' ?>">
    Tous (<?= array_sum($deptCounts) ?>)
  </a>
  <?php foreach ($departments as $d):
    $cnt = $deptCounts[(int)$d['id']] ?? 0;
  ?>
    <a href="equipements.php?dept=<?= $d['id'] ?>"
       class="dept-pill <?= $department_id === (int)$d['id'] ? 'active' : '' ?>">
      <?= htmlspecialchars($d['name']) ?> (<?= $cnt ?>)
    </a>
  <?php endforeach; ?>
</div>

<!-- Tableau des equipements -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Equipement</th>
          <th>Modele</th>
          <th>Departement</th>
          <th>Categorie</th>
          <th>Statut</th>
          <th>Prochaine maint.</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($equipements)): foreach ($equipements as $eq): ?>
        <tr>
          <td>
            <div style="font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($eq['designation']) ?></div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars($eq['serial_number']) ?></div>
            <?php if ($eq['status'] === 'en_panne' && !empty($eq['failure_reason'])): ?>
              <div style="font-size:11.5px;color:var(--red-600);margin-top:3px;font-style:italic">
                Panne : <?= htmlspecialchars($eq['failure_reason']) ?>
              </div>
            <?php endif; ?>
          </td>
          <td style="color:var(--text-secondary);font-size:13px"><?= htmlspecialchars($eq['model'] ?? '—') ?></td>
          <td>
            <span class="dept-tag"><?= htmlspecialchars($eq['dept_name'] ?? 'Non affecte') ?></span>
          </td>
          <td style="color:var(--text-secondary);font-size:13px"><?= htmlspecialchars($eq['category'] ?? '—') ?></td>
          <td>
            <?php $si = $statusLabels[$eq['status']] ?? ['label' => $eq['status'], 'class' => 'gray']; ?>
            <span class="badge <?= $si['class'] ?>"><?= $si['label'] ?></span>
          </td>
          <td style="font-size:12.5px;color:var(--text-muted)">
            <?= $eq['next_maintenance_at'] ? date('d/m/Y', strtotime($eq['next_maintenance_at'])) : '—' ?>
          </td>
          <td style="text-align:right">
            <div class="action-buttons" style="justify-content:flex-end">
              <?php if ($eq['status'] !== 'en_panne'): ?>
                <button class="btn-action-status btn-to-broken"
                        onclick="promptBreakdown(<?= $eq['id'] ?>, '<?= addslashes($eq['designation']) ?>')">
                  Panne
                </button>
              <?php endif; ?>

              <?php if ($eq['status'] !== 'en_maintenance'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="change_status">
                  <input type="hidden" name="equipment_id" value="<?= $eq['id'] ?>">
                  <input type="hidden" name="new_status" value="en_maintenance">
                  <button type="submit" class="btn-action-status btn-to-maintenance">Maintenance</button>
                </form>
              <?php endif; ?>

              <!-- Icone poubelle discrete a cote de maintenance -->
              <form id="deleteEquipForm_<?= $eq['id'] ?>" method="post" style="display:inline">
                <input type="hidden" name="action" value="delete_equipment">
                <input type="hidden" name="equipment_id" value="<?= $eq['id'] ?>">
                <button type="button"
                        class="btn-trash-icon"
                        title="Supprimer cet equipement"
                        aria-label="Supprimer cet equipement"
                        onclick="confirmDeleteEquip(<?= $eq['id'] ?>, '<?= addslashes($eq['designation']) ?>')">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                  </svg>
                </button>
              </form>

              <?php if ($eq['status'] !== 'en_service'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="change_status">
                  <input type="hidden" name="equipment_id" value="<?= $eq['id'] ?>">
                  <input type="hidden" name="new_status" value="en_service">
                  <button type="submit" class="btn-action-status btn-to-repaired">Reparer</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="7" class="empty">Aucun equipement trouve.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal : Ajouter un equipement -->
<div id="equipModal" class="modal hidden">
  <form method="post" class="modal-card">
    <input type="hidden" name="action" value="add_equipment">
    <button type="button" class="modal-close" onclick="closeEquipModal()" aria-label="Fermer">&#x2715;</button>
    <h2>Ajouter un equipement</h2>

    <div class="form-grid">
      <label>
        Numero de serie <span style="color:var(--red-500)">*</span>
        <input name="serial_number" placeholder="Ex: EQ-2026-010" required>
      </label>
      <label>
        Modele technique
        <input name="model" placeholder="Ex: CX-5000">
      </label>

      <label style="grid-column: span 2;">
        Designation complete <span style="color:var(--red-500)">*</span>
        <input name="designation" placeholder="Ex: Compresseur d'air a vis haute pression" required>
      </label>

      <label>
        Departement
        <select name="department_id">
          <option value="">-- Sans departement --</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $department_id === (int)$d['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($d['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Categorie
        <input name="category" placeholder="Ex: Compresseurs, Usinage...">
      </label>

      <label>
        Fabricant / Marque
        <input name="manufacturer" placeholder="Ex: Siemens, Atlas Copco...">
      </label>

      <label>
        Statut initial
        <select name="status" id="newEquipStatus" onchange="toggleReasonField()">
          <option value="en_service">En service</option>
          <option value="en_panne">En panne</option>
          <option value="en_maintenance">En maintenance</option>
          <option value="reforme">Reforme</option>
        </select>
      </label>

      <label id="reasonField" style="display:none; grid-column: span 2;">
        Motif de panne initial
        <input name="failure_reason" placeholder="Ex: Surchauffe moteur, fuite huile...">
      </label>

      <label style="grid-column: span 2;">
        Date de prochaine maintenance preventive
        <input name="next_maintenance_at" type="date">
      </label>
    </div>

    <div class="form-actions">
      <button type="button" class="btn btn-secondary" onclick="closeEquipModal()">Annuler</button>
      <button type="submit" class="btn btn-primary">Enregistrer l'equipement</button>
    </div>
  </form>
</div>

<!-- Modal : Declarer une panne -->
<div id="breakdownModal" class="modal hidden">
  <form method="post" class="modal-card">
    <input type="hidden" name="action" value="change_status">
    <input type="hidden" name="new_status" value="en_panne">
    <input type="hidden" name="equipment_id" id="breakdown_equip_id">
    <button type="button" class="modal-close" onclick="closeBreakdownModal()" aria-label="Fermer">&#x2715;</button>

    <h2 style="color:var(--red-500)">Declarer une panne</h2>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:18px">
      Equipement concerne : <strong id="breakdown_equip_name"></strong>
    </p>

    <div style="margin-bottom:20px">
      <label style="display:block;font-size:12.5px;font-weight:600;margin-bottom:8px;color:var(--text-secondary)">
        Motif ou cause de la panne :
      </label>
      <select name="failure_reason" class="filter-select" style="width:100%;margin-bottom:10px" onchange="checkCustomReason(this)">
        <option value="Surchauffe moteur">Surchauffe moteur</option>
        <option value="Court-circuit electrique">Court-circuit electrique</option>
        <option value="Fuite hydraulique / pneumatique">Fuite hydraulique / pneumatique</option>
        <option value="Usure mecanique des rouleaux / roulements">Usure mecanique des rouleaux / roulements</option>
        <option value="Defaut capteur / fin de course">Defaut capteur / fin de course</option>
        <option value="Bourrage matiere / blocage">Bourrage matiere / blocage</option>
        <option value="Defaut automate / variateur">Defaut automate / variateur</option>
        <option value="Autre motif">Autre motif (a preciser)</option>
      </select>
      <input type="text" id="breakdown_custom" name="failure_reason_custom"
             placeholder="Precisez la cause de la panne..."
             style="display:none;width:100%">
    </div>

    <div class="form-actions">
      <button type="button" class="btn btn-secondary" onclick="closeBreakdownModal()">Annuler</button>
      <button type="submit" class="btn btn-danger">Valider la panne</button>
    </div>
  </form>
</div>

<script>
  function openEquipModal()  {
    document.getElementById('equipModal').classList.remove('hidden');
  }
  function closeEquipModal() {
    document.getElementById('equipModal').classList.add('hidden');
  }

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
    const field = document.getElementById('reasonField');
    if (field) {
      field.style.display = (st === 'en_panne') ? 'flex' : 'none';
    }
  }

  function confirmDeleteEquip(id, name) {
    showDeleteModal({
      title: 'Supprimer l\'equipement',
      message: `Voulez-vous vraiment supprimer l'equipement « ${name} » ? Il sera archive et retiré de la liste active.`,
      form: document.getElementById('deleteEquipForm_' + id)
    });
  }

  let _timer;
  const searchInput = document.getElementById('search');
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(_timer);
      _timer = setTimeout(() => document.getElementById('filterForm').submit(), 350);
    });
  }

  window.addEventListener('click', e => {
    ['equipModal', 'breakdownModal'].forEach(id => {
      const el = document.getElementById(id);
      if (e.target === el) el.classList.add('hidden');
    });
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>