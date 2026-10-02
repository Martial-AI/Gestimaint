<?php
require_once __DIR__ . '/../includes/db.php';
mb_internal_encoding('UTF-8');

$db = getDB();
$flash = '';

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_technician') {
        $name  = trim($_POST['name'] ?? '');
        $spec  = trim($_POST['specialite'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name) {
            $db->prepare("INSERT INTO users (name, specialite, phone, email, role) VALUES (?,?,?,?,'technicien')")
               ->execute([$name, $spec, $phone, $email]);
            $flash = "success|Technicien « {$name} » ajoute.";
        }
    }
    if ($action === 'delete_technician') {
        $id = (int)($_POST['tech_id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM users WHERE id=? AND role='technicien'")->execute([$id]);
            $flash = "success|Technicien supprime.";
        }
    }
}

// Liste techniciens avec compteur OT
$technicians = $db->query("
    SELECT u.id, u.name, u.specialite, u.phone, u.email, u.created_at,
           COUNT(wt.work_order_id) AS wo_count
    FROM users u
    LEFT JOIN work_order_technicians wt ON wt.technician_id = u.id
    WHERE u.role = 'technicien'
    GROUP BY u.id
    ORDER BY u.name
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): [$type, $msg] = explode('|', $flash, 2); ?>
<div class="flash <?= $type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card-header" style="margin-bottom:20px">
  <div>
    <h2 style="font-size:17px;font-weight:700;color:var(--text-primary)">Equipe technique</h2>
    <p style="font-size:13px;color:var(--text-muted);margin-top:2px">
      <?= count($technicians) ?> technicien(s) enregistre(s)
    </p>
  </div>
  <button class="btn btn-primary" onclick="openTechModal()">Ajouter un technicien</button>
</div>

<!-- Tableau -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nom et prenom</th>
          <th>Specialite</th>
          <th>Telephone</th>
          <th>Email</th>
          <th style="text-align:center">Interventions</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($technicians)): foreach ($technicians as $t): ?>
        <tr>
          <td>
            <strong style="color:var(--text-primary)"><?= htmlspecialchars($t['name']) ?></strong>
          </td>
          <td style="color:var(--text-secondary)"><?= htmlspecialchars($t['specialite'] ?? '—') ?></td>
          <td style="color:var(--text-secondary);font-size:13px"><?= htmlspecialchars($t['phone'] ?? '—') ?></td>
          <td style="font-size:13px">
            <?php if ($t['email']): ?>
            <a href="mailto:<?= htmlspecialchars($t['email']) ?>" style="color:var(--green-600)">
              <?= htmlspecialchars($t['email']) ?>
            </a>
            <?php else: echo '—'; endif; ?>
          </td>
          <td style="text-align:center">
            <span class="badge <?= (int)$t['wo_count'] > 0 ? 'green' : 'gray' ?>">
              <?= (int)$t['wo_count'] ?>
            </span>
          </td>
          <td>
            <form method="post" onsubmit="return confirm('Supprimer ce technicien ?')">
              <input type="hidden" name="action" value="delete_technician">
              <input type="hidden" name="tech_id" value="<?= $t['id'] ?>">
              <button type="submit" class="btn-delete-row">Supprimer</button>
            </form>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6" class="empty">Aucun technicien enregistre.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Ajouter technicien -->
<div id="techModal" class="modal hidden">
  <form method="post" class="modal-card">
    <input type="hidden" name="action" value="add_technician">
    <button type="button" class="modal-close" onclick="closeTechModal()">&#x2715;</button>
    <h2>Ajouter un technicien</h2>

    <div class="form-grid">
      <label style="grid-column:span 2">
        Nom complet <span style="color:var(--red-500)">*</span>
        <input name="name" placeholder="Ex: Ahmed Benali" required>
      </label>
      <label>
        Specialite
        <input name="specialite" placeholder="Ex: Electricite, Mecanique...">
      </label>
      <label>
        Telephone
        <input name="phone" placeholder="Ex: 06 12 34 56 78">
      </label>
      <label style="grid-column:span 2">
        Email
        <input name="email" type="email" placeholder="Ex: ahmed.benali@usine.dz">
      </label>
    </div>

    <div class="form-actions">
      <button type="button" class="btn btn-secondary" onclick="closeTechModal()">Annuler</button>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
</div>

<script>
  function openTechModal()  { document.getElementById('techModal').classList.remove('hidden'); }
  function closeTechModal() { document.getElementById('techModal').classList.add('hidden'); }
  window.addEventListener('click', e => {
    const m = document.getElementById('techModal');
    if (e.target === m) m.classList.add('hidden');
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>