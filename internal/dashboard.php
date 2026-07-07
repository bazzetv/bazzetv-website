<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';

$refresh = isset($_GET['refresh']);
$today = db()->query('SELECT * FROM stats_daily ORDER BY stat_date DESC LIMIT 1')->fetch();
$collectError = null;
$watchMinutesError = null;

if (!$today || $refresh) {
  require_once __DIR__ . '/lib/stats_collector.php';
  try {
    $result = collect_youtube_stats();
    $watchMinutesError = $result['watch_minutes_error'] ?? null;
    $today = db()->query('SELECT * FROM stats_daily ORDER BY stat_date DESC LIMIT 1')->fetch();
  } catch (Throwable $e) {
    $collectError = $e->getMessage();
  }
}

function fmt_delta($n) {
  if ($n === null) return '—';
  $n = (int)$n;
  return ($n > 0 ? '+' : '') . number_format($n, 0, ',', ' ');
}

function delta_class($n) {
  if ($n === null) return '';
  if ($n > 0) return 'up';
  if ($n < 0) return 'down';
  return 'flat';
}

function fmt_hours($minutes) {
  if ($minutes === null) return '—';
  return number_format((float)$minutes / 60, 1, ',', ' ') . ' h';
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
    <div class="page-header">
      <h1>Dashboard</h1>
      <a class="btn secondary" href="dashboard.php?refresh=1">🔄 Rafraîchir</a>
    </div>
    <section class="widget-section">
      <h2>YouTube Analytics</h2>
      <div class="widget-grid">
        <div class="widget">
          <div class="label">Vues (mois en cours)</div>
          <div class="value <?= delta_class($today['views_delta'] ?? null) ?>"><?= fmt_delta($today['views_delta'] ?? null) ?></div>
        </div>
        <div class="widget">
          <div class="label">Nouveaux abonnés (mois en cours)</div>
          <div class="value <?= delta_class($today['subscribers_delta'] ?? null) ?>"><?= fmt_delta($today['subscribers_delta'] ?? null) ?></div>
        </div>
        <div class="widget">
          <div class="label">Abonnés (total)</div>
          <div class="value"><?= fmt_num($today['subscribers'] ?? null) ?></div>
        </div>
        <div class="widget">
          <div class="label">Vues totales</div>
          <div class="value"><?= fmt_num($today['views_total'] ?? null) ?></div>
        </div>
        <div class="widget">
          <div class="label">Vidéos publiées</div>
          <div class="value"><?= fmt_num($today['video_count'] ?? null) ?></div>
        </div>
        <div class="widget">
          <div class="label">Minutes vues (mois en cours)</div>
          <div class="value"><?= fmt_hours($today['watch_minutes'] ?? null) ?></div>
        </div>
      </div>
      <?php if ($today): ?>
        <p class="stale-note">Dernière collecte : <?= htmlspecialchars($today['stat_date']) ?> (les données YouTube Analytics ont 24-48h de latence, ce ne sont pas des chiffres "temps réel").</p>
      <?php elseif (!$collectError): ?>
        <p class="stale-note">Aucune donnée collectée pour l'instant — le script <code>internal/cron/collect-stats.php</code> doit tourner au moins une fois.</p>
      <?php endif; ?>
      <?php if ($collectError): ?>
        <p class="stale-note">⚠️ La collecte a échoué : <?= htmlspecialchars($collectError) ?>. Vérifie les identifiants YouTube/Google dans les secrets.</p>
      <?php endif; ?>
      <?php if ($watchMinutesError): ?>
        <p class="stale-note">⚠️ Minutes vues non récupérées (Analytics API) : <?= htmlspecialchars($watchMinutesError) ?></p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
