<?php
if (php_sapi_name() !== 'cli') {
  http_response_code(403);
  exit('Forbidden');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/stats_collector.php';

try {
  $r = collect_youtube_stats();
  echo "✓ {$r['stat_date']}: {$r['subscribers']} subs ({$r['subscribers_delta']}), {$r['views_total']} views ({$r['views_delta']}), {$r['video_count']} videos, {$r['watch_minutes']} min watched\n";
} catch (Throwable $e) {
  fwrite(STDERR, $e->getMessage() . "\n");
  exit(1);
}
