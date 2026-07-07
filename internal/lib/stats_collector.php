<?php
const YT_CHANNEL_ID = 'UCQZa27_VXLUowoMsYo6Y0Dw';

function yt_curl_json(string $url, array $headers = [], ?array $postFields = null): array {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  if ($postFields !== null) {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
  }
  $response = curl_exec($ch);
  if ($response === false) {
    throw new RuntimeException('cURL error: ' . curl_error($ch));
  }
  return json_decode($response, true) ?? [];
}

// Fetches current YouTube stats, snapshots yesterday's date (Analytics API has
// a 24-48h processing lag), and upserts into stats_daily. Returns the saved row.
function collect_youtube_stats(): array {
  $token = yt_curl_json('https://oauth2.googleapis.com/token', [], [
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'refresh_token' => GOOGLE_REFRESH_TOKEN,
    'grant_type' => 'refresh_token',
  ]);
  $accessToken = $token['access_token'] ?? null;
  if (!$accessToken) {
    throw new RuntimeException('Failed to get Google access token');
  }
  $authHeaders = ['Authorization: Bearer ' . $accessToken, 'Referer: https://bazzetv.fr'];

  $chan = yt_curl_json(
    'https://www.googleapis.com/youtube/v3/channels?part=statistics&id=' . YT_CHANNEL_ID . '&key=' . YOUTUBE_API_KEY,
    ['Referer: https://bazzetv.fr']
  );
  $chanStats = $chan['items'][0]['statistics'] ?? null;
  if (!$chanStats) {
    throw new RuntimeException('Failed to fetch channel stats');
  }

  $subscribers = (int)$chanStats['subscriberCount'];
  $viewsTotal  = (int)$chanStats['viewCount'];
  $videoCount  = (int)$chanStats['videoCount'];

  $statDate = date('Y-m-d', strtotime('-1 day'));

  $analytics = yt_curl_json(
    'https://youtubeanalytics.googleapis.com/v2/reports?ids=channel%3D%3D' . YT_CHANNEL_ID .
    "&startDate={$statDate}&endDate={$statDate}&metrics=estimatedMinutesWatched",
    $authHeaders
  );
  $watchMinutes = isset($analytics['rows'][0][0]) ? (int)$analytics['rows'][0][0] : null;
  $watchMinutesError = null;
  if ($watchMinutes === null) {
    $watchMinutesError = $analytics['error']['message'] ?? ('Réponse Analytics inattendue: ' . json_encode($analytics));
  }

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

  return [
    'stat_date' => $statDate,
    'subscribers' => $subscribers,
    'subscribers_delta' => $subscribersDelta,
    'views_total' => $viewsTotal,
    'views_delta' => $viewsDelta,
    'video_count' => $videoCount,
    'watch_minutes' => $watchMinutes,
    'watch_minutes_error' => $watchMinutesError,
  ];
}
