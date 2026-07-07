<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';

$today = db()->query('SELECT * FROM stats_daily ORDER BY stat_date DESC LIMIT 1')->fetch();

function delta_html($n) {
  if ($n === null) return '';
  $n = (int)$n;
  if ($n > 0) return '<span class="delta up">+' . $n . ' vs veille</span>';
  if ($n < 0) return '<span class="delta down">' . $n . ' vs veille</span>';
  return '<span class="delta flat">= vs veille</span>';
}

function fmt_num($n) {
  if ($n === null) return '—';
  return number_format((float)$n, 0, ',', ' ');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Dashboard — bazzetv internal</title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <nav>
    <div class="brand">bazzetv internal</div>
    <div class="links">
      <a href="dashboard.php" class="active">Dashboard</a>
      <a href="kanban.php">Kanban</a>
      <a href="logout.php" class="logout">Déconnexion</a>
    </div>
  </nav>
  <main>
    <h1>Dashboard</h1>
    <div class="widget-grid">
      <div class="widget">
        <div class="label">Abonnés</div>
        <div class="value"><?= fmt_num($today['subscribers'] ?? null) ?></div>
        <?= delta_html($today['subscribers_delta'] ?? null) ?>
      </div>
      <div class="widget">
        <div class="label">Vues totales</div>
        <div class="value"><?= fmt_num($today['views_total'] ?? null) ?></div>
        <?= delta_html($today['views_delta'] ?? null) ?>
      </div>
      <div class="widget">
        <div class="label">Vidéos publiées</div>
        <div class="value"><?= fmt_num($today['video_count'] ?? null) ?></div>
      </div>
      <div class="widget">
        <div class="label">Minutes vues (hier)</div>
        <div class="value"><?= fmt_num($today['watch_minutes'] ?? null) ?></div>
      </div>
    </div>
    <?php if ($today): ?>
      <p class="stale-note">Dernière collecte : <?= htmlspecialchars($today['stat_date']) ?> (les données YouTube Analytics ont 24-48h de latence, ce ne sont pas des chiffres "temps réel").</p>
    <?php else: ?>
      <p class="stale-note">Aucune donnée collectée pour l'instant — le script <code>internal/cron/collect-stats.php</code> doit tourner au moins une fois.</p>
    <?php endif; ?>
  </main>
</body>
</html>
