<?php
// pages/techniciens.php — Gestion de l'équipe technique (Ajout & Suppression)
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

$form_error   = '';
$form_success = '';

// ── Traitement des formulaires POST ───────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Ajouter un technicien
    if ($action === 'add_technician') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '') ?: null;
        $specialty  = trim($_POST['specialty'] ?? '');
        $team_id    = !empty($_POST['team_id']) ? (int)$_POST['team_id'] : null;

        if (!$first_name || !$last_name) {
            $form_error = 'Le prénom et le nom du technicien sont requis.';
        } else {
            try {
                // Trouver l'ID du rôle technicien
                $role_id = (int)$db->query("SELECT id FROM roles WHERE code = 'technician'")->fetchColumn() ?: 2;

                $stmt = $db->prepare("
                    INSERT INTO users (role_id, team_id, first_name, last_name, email, specialty, active)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$role_id, $team_id, $first_name, $last_name, $email, $specialty]);
                $form_success = "Le technicien « {$first_name} {$last_name} » a été ajouté avec succès.";
            } catch (PDOException $e) {
                $form_error = str_contains($e->getMessage(), 'Duplicate')
                    ? 'Un utilisateur avec cette adresse email existe déjà.'
                    : 'Erreur SQL : ' . $e->getMessage();
            }
        }
    }

    // 2. Supprimer un technicien
    if ($action === 'delete_technician') {
        $tech_id = (int)($_POST['technician_id'] ?? 0);
        if ($tech_id > 0) {
            try {
                // Supprimer les évaluations et liaisons d'interventions associées
                $db->prepare("DELETE FROM technician_evaluations WHERE technician_id = ?")->execute([$tech_id]);
                $db->prepare("DELETE FROM work_order_technicians WHERE technician_id = ?")->execute([$tech_id]);
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$tech_id]);
                $form_success = "Le technicien a été retiré de l'équipe.";
            } catch (Exception $e) {
                $form_error = "Impossible de supprimer ce technicien : " . $e->getMessage();
            }
        }
    }
}

// ── Liste des équipes disponibles ────────────────────────
$teams = [];
try {
    $teams = $db->query("SELECT * FROM teams ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}

// ── Liste des techniciens ─────────────────────────────────
$technicians = [];
try {
    $technicians = $db->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.specialty,
               t.name AS team,
               COUNT(wt.work_order_id) AS interventions,
               ROUND(AVG(ev.rating), 1) AS rating
        FROM users u
        JOIN roles r ON r.id = u.role_id
        LEFT JOIN teams t ON t.id = u.team_id
        LEFT JOIN work_order_technicians wt ON wt.technician_id = u.id
        LEFT JOIN technician_evaluations ev ON ev.technician_id = u.id
        WHERE r.code = 'technician' AND u.active = 1
        GROUP BY u.id
        ORDER BY u.last_name ASC, u.first_name ASC
    ")->fetchAll();
} catch (Exception $e) {}

function ratingStars(?float $r): string {
    if (!$r) return '<span style="color:var(--text-muted)">☆☆☆☆☆ <small style="color:var(--text-muted)">—</small></span>';
    $full  = (int)round($r);
    $empty = max(0, 5 - $full);
    return '<span class="stars">'
         . str_repeat('★', $full) . str_repeat('☆', $empty)
         . '<small>' . number_format($r, 1) . '/5</small></span>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Barre d'outils ────────────────────────────────────── -->
<div class="toolbar">
  <div style="color:var(--text-secondary);font-size:13.5px;font-weight:500">
    <strong style="color:var(--text-primary)"><?= count($technicians) ?></strong> technicien(s) enregistré(s)
  </div>

  <button class="btn btn-primary" onclick="openTechModal()">
    <span>➕</span> Nouveau technicien
  </button>
</div>

<!-- ── Messages d'alerte ─────────────────────────────────── -->
<?php if ($form_success): ?>
  <div class="alert-success">✅ <?= htmlspecialchars($form_success) ?></div>
<?php endif; ?>
<?php if ($form_error): ?>
  <div class="error">⚠ <?= htmlspecialchars($form_error) ?></div>
<?php endif; ?>

<!-- ── Tableau de l'équipe ───────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <h2>👷 Équipe technique &amp; Intervenants</h2>
    <span class="count-badge"><?= count($technicians) ?> actif(s)</span>
  </div>

  <?php if (!empty($technicians)): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Technicien</th>
          <th>Spécialité</th>
          <th>Équipe</th>
          <th style="text-align:center">Interventions</th>
          <th>Évaluation moyenne</th>
          <th style="width:50px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($technicians as $t): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:12px">
              <div class="tech-avatar">
                <?= mb_strtoupper(mb_substr($t['first_name'],0,1) . mb_substr($t['last_name'],0,1)) ?>
              </div>
              <div>
                <strong style="color:var(--text-primary);display:block">
                  <?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?>
                </strong>
                <small style="color:var(--text-muted)"><?= htmlspecialchars($t['email'] ?? 'Non renseigné') ?></small>
              </div>
            </div>
          </td>

          <td style="color:var(--text-secondary);font-weight:500">
            <?= htmlspecialchars($t['specialty'] ?? 'Maintenance générale') ?>
          </td>

          <td style="color:var(--text-secondary)">
            <span class="dept-badge">
              👥 <?= htmlspecialchars($t['team'] ?? 'Équipe standard') ?>
            </span>
          </td>

          <td style="text-align:center">
            <span class="badge neutral" style="font-weight:700"><?= (int)$t['interventions'] ?></span>
          </td>

          <td><?= ratingStars($t['rating'] ? (float)$t['rating'] : null) ?></td>

          <!-- Suppression du technicien -->
          <td style="text-align:right">
            <form method="post" style="display:inline" onsubmit="return confirm('Confirmez-vous la suppression du technicien <?= htmlspecialchars(addslashes($t['first_name'] . ' ' . $t['last_name'])) ?> ?');">
              <input type="hidden" name="action" value="delete_technician">
              <input type="hidden" name="technician_id" value="<?= $t['id'] ?>">
              <button type="submit" class="btn-delete-row" title="Supprimer ce technicien">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="empty">👷 Aucun technicien enregistré.</p>
  <?php endif; ?>
</div>

<!-- ── Modal : Ajouter un Technicien ─────────────────────── -->
<div id="techModal" class="modal hidden">
  <form method="post" class="modal-card">
    <input type="hidden" name="action" value="add_technician">
    <button type="button" class="modal-close" onclick="closeTechModal()" aria-label="Fermer">×</button>

    <h2>👷 Ajouter un nouveau technicien</h2>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:18px">
      Renseignez les compétences et l'équipe d'affectation de l'intervenant.
    </p>

    <div class="form-grid">
      <label>
        Prénom
        <input name="first_name" placeholder="Ex: Lucas" required>
      </label>
      <label>
        Nom
        <input name="last_name" placeholder="Ex: Mercier" required>
      </label>

      <label>
        Spécialité technique
        <input name="specialty" placeholder="Ex: Électrotechnique & Automates" required>
      </label>

      <label>
        Équipe de rattachement
        <select name="team_id">
          <option value="">Sélectionnez une équipe</option>
          <?php foreach ($teams as $tm): ?>
            <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label style="grid-column: span 2;">
        Adresse email professionnelle
        <input name="email" type="email" placeholder="Ex: l.mercier@gestimaint.local">
      </label>
    </div>

    <div class="form-actions">
      <button type="button" class="btn btn-secondary" onclick="closeTechModal()">Annuler</button>
      <button type="submit" class="btn btn-primary">💾 Ajouter le technicien</button>
    </div>
  </form>
</div>

<script>
  function openTechModal()  { document.getElementById('techModal').classList.remove('hidden'); }
  function closeTechModal() { document.getElementById('techModal').classList.add('hidden'); }

  window.addEventListener('click', (e) => {
    const modal = document.getElementById('techModal');
    if (e.target === modal) closeTechModal();
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
