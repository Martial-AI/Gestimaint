<?php
// includes/header.php — En-tete partage et barre laterale de navigation
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

$current_page = basename($_SERVER['PHP_SELF'], '.php');

$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if (basename($script_dir) === 'pages' || basename($script_dir) === 'includes') {
    $base_url = dirname($script_dir);
} else {
    $base_url = $script_dir;
}
$base_url = rtrim($base_url, '/\\');

$nav_items = [
  'index'         => ['label' => 'Tableau de bord',        'href' => ($base_url ?: '') . '/index.php'],
  'equipements'   => ['label' => 'Equipements et Rayons',  'href' => ($base_url ?: '') . '/pages/equipements.php'],
  'departements'  => ['label' => 'Departements',           'href' => ($base_url ?: '') . '/pages/departements.php'],
  'maintenance'   => ['label' => 'Maintenance et OT',      'href' => ($base_url ?: '') . '/pages/maintenance.php'],
  'statistiques'  => ['label' => 'Statistiques et Pannes', 'href' => ($base_url ?: '') . '/pages/statistiques.php'],
  'techniciens'   => ['label' => 'Techniciens',            'href' => ($base_url ?: '') . '/pages/techniciens.php'],
  'notifications' => ['label' => 'Notifications',          'href' => ($base_url ?: '') . '/pages/notifications.php'],
];

$page_label = $nav_items[$current_page]['label'] ?? 'GESTIMAINT';

$overdue_count = 0;
try {
  $overdue_count = (int) getDB()->query(
    "SELECT COUNT(*) FROM equipment WHERE active=1 AND next_maintenance_at < CURDATE()"
  )->fetchColumn();
} catch (Exception $e) {}

$unread_count = 0;
try {
  $unread_count = (int) getDB()->query(
    "SELECT COUNT(*) FROM notifications WHERE read_at IS NULL"
  )->fetchColumn();
} catch (Exception $e) {}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>GESTIMAINT &mdash; <?= htmlspecialchars($page_label) ?></title>
  <meta name="description" content="GESTIMAINT — Application GMAO de gestion de maintenance industrielle">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base_url ?>/assets/styles.css">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app-shell">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
        </svg>
      </div>
      <div class="logo-details">
        <span class="logo-text">GESTIMAINT</span>
        <span class="logo-tag">GMAO Industrielle</span>
      </div>
      <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Fermer le menu">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <nav class="sidebar-nav">
      <span class="nav-section-title">Menu principal</span>
      <?php foreach ($nav_items as $key => $item):
        $is_active = ($current_page === $key);
      ?>
        <a href="<?= $item['href'] ?>"
           class="nav-item<?= $is_active ? ' active' : '' ?>"
           <?= $is_active ? 'aria-current="page"' : '' ?>>
          <span class="nav-text"><?= $item['label'] ?></span>
          <?php if ($key === 'notifications' && $unread_count > 0): ?>
            <span class="nav-badge"><?= $unread_count ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
      <div class="system-status">
        <span class="status-dot"></span>
        <span>Systeme operationnel</span>
      </div>
      <div class="foot-version">GESTIMAINT v2.0</div>
    </div>
  </aside>

  <div class="main-wrap">

    <header class="topbar">
      <button class="menu-btn" id="menuBtn" aria-label="Ouvrir le menu">
        <span></span><span></span><span></span>
      </button>

      <div class="topbar-title">
        <h1><?= htmlspecialchars($page_label) ?></h1>
        <p id="today-date"></p>
      </div>

      <div class="topbar-right">
        <?php if ($overdue_count > 0): ?>
          <a href="<?= ($base_url ?: '') ?>/pages/equipements.php" class="alert-pill-red">
            <span class="alert-dot"></span>
            <span><?= $overdue_count ?> maintenance<?= $overdue_count > 1 ? 's' : '' ?> en retard</span>
          </a>
        <?php else: ?>
          <div class="alert-pill-green">
            <span class="success-dot"></span>
            <span>Maintenance a jour</span>
          </div>
        <?php endif; ?>
      </div>
    </header>

    <main class="page-content">

<script>
  try {
    document.getElementById('today-date').textContent =
      new Intl.DateTimeFormat('fr-FR', { dateStyle: 'full' }).format(new Date());
  } catch(e) {}

  const menuBtn  = document.getElementById('menuBtn');
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const closeBtn = document.getElementById('sidebarCloseBtn');

  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  if (menuBtn)  menuBtn.addEventListener('click', openSidebar);
  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
  if (overlay)  overlay.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
</script>