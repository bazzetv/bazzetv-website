<?php
if (php_sapi_name() !== 'cli') {
  http_response_code(403);
  exit('Forbidden');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

const CHANNEL_ID = 'UCQZa27_VXLUowoMsYo6Y0Dw';

function curl_json(string $url, array $headers = [], ?array $postFields = null): array {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  if ($postFields !== null) {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
  }
  $response = curl_exec($ch);
  if ($response === false) {
    fwrite(STDERR, 'cURL error: ' . curl_error($ch) . "\n");
    exit(1);
  }
  return json_decode($response, true) ?? [];
}

// ── OAuth access token ───────────────────────────────────────────────────
$token = curl_json('https://oauth2.googleapis.com/token', [], [
  'client_id' => GOOGLE_CLIENT_ID,
  'client_secret' => GOOGLE_CLIENT_SECRET,
  'refresh_token' => GOOGLE_REFRESH_TOKEN,
  'grant_type' => 'refresh_token',
]);
$accessToken = $token['access_token'] ?? null;
if (!$accessToken) {
  fwrite(STDERR, "Failed to get access token\n");
  exit(1);
}
$authHeaders = ['Authorization: Bearer ' . $accessToken, 'Referer: https://bazzetv.fr'];

// ── channel stats (public, cumulative totals) ────────────────────────────
$chan = curl_json(
  'https://www.googleapis.com/youtube/v3/channels?part=statistics&id=' . CHANNEL_ID . '&key=' . YOUTUBE_API_KEY,
  ['Referer: https://bazzetv.fr']
);
$chanStats = $chan['items'][0]['statistics'] ?? null;
if (!$chanStats) {
  fwrite(STDERR, "Failed to fetch channel stats\n");
  exit(1);
}

$subscribers = (int)$chanStats['subscriberCount'];
$viewsTotal  = (int)$chanStats['viewCount'];
$videoCount  = (int)$chanStats['videoCount'];

// Always snapshot yesterday's date — YouTube Analytics data has a 24-48h processing lag.
$statDate = date('Y-m-d', strtotime('-1 day'));

// ── watch time for that day (requires OAuth, Analytics API) ─────────────
$analytics = curl_json(
  'https://youtubeanalytics.googleapis.com/v2/reports?ids=channel%3D%3D' . CHANNEL_ID .
  "&startDate={$statDate}&endDate={$statDate}&metrics=estimatedMinutesWatched",
  $authHeaders
);
$watchMinutes = isset($analytics['rows'][0][0]) ? (int)$analytics['rows'][0][0] : null;

// ── delta vs the previous stored snapshot ────────────────────────────────
$prevStmt = db()->prepare('SELECT subscribers, views_total FROM stats_daily WHERE stat_date < ? ORDER BY stat_date DESC LIMIT 1');
$prevStmt->execute([$statDate]);
$prev = $prevStmt->fetch();

$subscribersDelta = $prev ? $subscribers - (int)$prev['subscribers'] : null;
$viewsDelta = $prev ? $viewsTotal - (int)$prev['views_total'] : null;

$stmt = db()->prepare('
  INSERT INTO stats_daily (stat_date, subscribers, subscribers_delta, views_total, views_delta, video_count, watch_minutes)
  VALUES (?, ?, ?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    subscribers = VALUES(subscribers),
    subscribers_delta = VALUES(subscribers_delta),
    views_total = VALUES(views_total),
    views_delta = VALUES(views_delta),
    video_count = VALUES(video_count),
    watch_minutes = VALUES(watch_minutes)
');
$stmt->execute([$statDate, $subscribers, $subscribersDelta, $viewsTotal, $viewsDelta, $videoCount, $watchMinutes]);

echo "✓ {$statDate}: {$subscribers} subs ({$subscribersDelta}), {$viewsTotal} views ({$viewsDelta}), {$videoCount} videos, {$watchMinutes} min watched\n";
