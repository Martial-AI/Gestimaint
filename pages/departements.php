<?php
require_once __DIR__ . '/../includes/db.php';
mb_internal_encoding('UTF-8');

$db = getDB();
$flash = '';

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_department') {
        $name = trim($_POST['department_name'] ?? '');
        $desc = trim($_POST['department_desc'] ?? '');
        if ($name !== '') {
            $db->prepare("INSERT INTO departments (name, description) VALUES (?, ?)")
               ->execute([$name, $desc]);
            $flash = "success|Le departement « {$name} » a ete cree.";
        }
    }

    if ($action === 'delete_department') {
        $id = (int)($_POST['dept_id'] ?? 0);
        if ($id > 0) {
            // Desaffecter les equipements
            $db->prepare("UPDATE equipment SET department_id = NULL WHERE department_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
            $flash = "success|Departement supprime. Les equipements ont ete desaffectes.";
        }
    }
}

// Liste departements avec compteur
$departments = $db->query("
    SELECT d.id, d.name, d.description,
           COUNT(e.id) AS equipment_count
    FROM departments d
    LEFT JOIN equipment e ON e.department_id = d.id AND e.active = 1
    GROUP BY d.id
    ORDER BY d.name
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): [$type, $msg] = explode('|', $flash, 2); ?>
<div class="flash <?= $type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Header avec bouton ajouter -->
<div class="card-header" style="margin-bottom:20px">
  <div>
    <h2 style="font-size:17px;font-weight:700;color:var(--text-primary)">Gestion des Departements</h2>
    <p style="font-size:13px;color:var(--text-muted);margin-top:2px">Organisez vos equipements par zones ou unites operationnelles</p>
  </div>
  <button class="btn btn-primary" onclick="openDeptModal()">Nouveau departement</button>
</div>

<!-- Grille des departements -->
<?php if (!empty($departments)): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px">
  <?php foreach ($departments as $d): ?>
  <div class="card" style="padding:20px;display:flex;flex-direction:column;gap:12px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
      <div>
        <div style="font-size:15px;font-weight:700;color:var(--text-primary)"><?= htmlspecialchars($d['name']) ?></div>
        <?php if ($d['description']): ?>
        <div style="font-size:12.5px;color:var(--text-muted);margin-top:3px"><?= htmlspecialchars($d['description']) ?></div>
        <?php endif; ?>
      </div>
      <span class="count-badge"><?= (int)$d['equipment_count'] ?> equip.</span>
    </div>

    <div style="display:flex;align-items:center;gap:8px;border-top:1px solid var(--border-light);padding-top:12px">
      <a href="equipements.php?dept=<?= $d['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1;text-align:center">
        Voir les equipements
      </a>
      <?php if ((int)$d['equipment_count'] === 0): ?>
      <form method="post" onsubmit="return confirm('Supprimer ce departement ?')">
        <input type="hidden" name="action" value="delete_department">
        <input type="hidden" name="dept_id" value="<?= $d['id'] ?>">
        <button type="submit" class="btn-delete-row">Supprimer</button>
      </form>
      <?php else: ?>
      <span style="font-size:12px;color:var(--text-muted);font-style:italic">Non supprimable</span>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card"><p class="empty">Aucun departement cree. Commencez par en ajouter un.</p></div>
<?php endif; ?>

<!-- Modal Nouveau departement -->
<div id="deptModal" class="modal hidden">
  <form method="post" class="modal-card">
    <input type="hidden" name="action" value="add_department">
    <button type="button" class="modal-close" onclick="closeDeptModal()" aria-label="Fermer">&#x2715;</button>
    <h2>Creer un nouveau departement</h2>

    <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:22px">
      <label style="display:flex;flex-direction:column;gap:6px;font-size:12.5px;font-weight:600;color:var(--text-secondary)">
        Nom du departement <span style="color:var(--red-500)">*</span>
        <input name="department_name" placeholder="Ex: Production, Atelier mecanique..." required>
      </label>
      <label style="display:flex;flex-direction:column;gap:6px;font-size:12.5px;font-weight:600;color:var(--text-secondary)">
        Description / Localisation
        <input name="department_desc" placeholder="Ex: Batiment C, secteur finitions">
      </label>
    </div>

    <div class="form-actions">
      <button type="button" class="btn btn-secondary" onclick="closeDeptModal()">Annuler</button>
      <button type="submit" class="btn btn-primary">Creer le departement</button>
    </div>
  </form>
</div>

<script>
  function openDeptModal()  { document.getElementById('deptModal').classList.remove('hidden'); }
  function closeDeptModal() { document.getElementById('deptModal').classList.add('hidden'); }
  window.addEventListener('click', e => {
    const m = document.getElementById('deptModal');
    if (e.target === m) m.classList.add('hidden');
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>