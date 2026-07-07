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

// Fetches current YouTube stats (live totals + month-to-date views/subscribers
// gained/watch-time from the Analytics API) and upserts into stats_daily.
// stat_date is always "yesterday" since Analytics data has a processing lag.
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

  // Month-to-date range instead of a single day: a single day's watch-time
  // is unreliable (YouTube's processing lag can exceed 24h and return no
  // rows at all), but a wider range almost always has usable data.
  $monthStart = date('Y-m-01');
  if ($monthStart > $statDate) {
    $monthStart = $statDate; // 1st of the month: avoid an inverted range
  }

  $analytics = yt_curl_json(
    'https://youtubeanalytics.googleapis.com/v2/reports?ids=channel%3D%3D' . YT_CHANNEL_ID .
    "&startDate={$monthStart}&endDate={$statDate}&metrics=views,subscribersGained,estimatedMinutesWatched",
    $authHeaders
  );
  $row = $analytics['rows'][0] ?? null;
  // views_delta / subscribers_delta hold month-to-date totals (not a day-over-day
  // delta, despite the column name — repurposed to avoid a schema migration).
  $viewsMonth = $row[0] ?? null;
  $subscribersGainedMonth = $row[1] ?? null;
  $watchMinutes = $row[2] ?? null;
  $analyticsError = null;
  if ($row === null) {
    $analyticsError = $analytics['error']['message'] ?? ('Réponse Analytics inattendue: ' . json_encode($analytics));
  }

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
  $stmt->execute([$statDate, $subscribers, $subscribersGainedMonth, $viewsTotal, $viewsMonth, $videoCount, $watchMinutes]);

  return [
    'stat_date' => $statDate,
    'subscribers' => $subscribers,
    'subscribers_delta' => $subscribersGainedMonth,
    'views_total' => $viewsTotal,
    'views_delta' => $viewsMonth,
    'video_count' => $videoCount,
    'watch_minutes' => $watchMinutes,
    'watch_minutes_error' => $analyticsError,
  ];
}
