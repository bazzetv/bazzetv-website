import { readFileSync, writeFileSync } from 'fs';

const API_KEY = process.env.YOUTUBE_API_KEY;
if (!API_KEY) { console.error('Missing YOUTUBE_API_KEY'); process.exit(1); }

const VIDEO_IDS = ['pRt6h1xFA9U', 'y5u2yPym1CI', 'UOiAT0sCeEM', '5h_v3HbX7k4'];

function formatViews(n) {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0', '') + 'M';
  if (n >= 1_000) return (n / 1_000).toFixed(1).replace('.0', '') + 'K';
  return String(n);
}

function formatDuration(iso) {
  const m = iso.match(/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/);
  const h = parseInt(m[1] || 0), min = parseInt(m[2] || 0), s = parseInt(m[3] || 0);
  if (h > 0) return `${h}:${String(min).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
  return `${min}:${String(s).padStart(2,'0')}`;
}

function timeAgo(dateStr) {
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
  if (diff < 3600) return `${Math.floor(diff/60)} min ago`;
  if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
  if (diff < 7 * 86400) return `${Math.floor(diff/86400)} days ago`;
  if (diff < 30 * 86400) return `${Math.floor(diff/604800)} weeks ago`;
  if (diff < 365 * 86400) return `${Math.floor(diff/2592000)} months ago`;
  return `${Math.floor(diff/31536000)} years ago`;
}

const url = `https://www.googleapis.com/youtube/v3/videos?part=snippet,statistics,contentDetails&id=${VIDEO_IDS.join(',')}&key=${API_KEY}`;
const res = await fetch(url);
const data = await res.json();

if (!data.items) { console.error('API error:', JSON.stringify(data)); process.exit(1); }

let html = readFileSync('index.html', 'utf8');

for (const item of data.items) {
  const id = item.id;
  const views = formatViews(parseInt(item.statistics.viewCount));
  const duration = formatDuration(item.contentDetails.duration);
  const date = timeAgo(item.snippet.publishedAt);
  const title = item.snippet.title;
  const isHot = parseInt(item.statistics.viewCount) >= 50_000;
  const badge = isHot
    ? `<span class="yt-views-badge hot">🔥 ${views} views</span>`
    : `<span class="yt-views-badge">▶ ${views} views</span>`;

  // Replace within each card block using data-video-id as anchor
  const cardRegex = new RegExp(
    `(data-video-id="${id}"[\\s\\S]*?)(yt-views-badge[^<]*(?:hot)?[^>]*>[^<]*<\\/span>)`,
    'g'
  );

  // Replace views badge
  html = html.replace(
    new RegExp(`(data-video-id="${id}"[\\s\\S]*?)<span class="yt-views-badge[^"]*">[^<]*<\\/span>`),
    `$1${badge}`
  );
  // Replace duration
  html = html.replace(
    new RegExp(`(data-video-id="${id}"[\\s\\S]*?)<span class="yt-duration">[^<]*<\\/span>`),
    `$1<span class="yt-duration">${duration}</span>`
  );
  // Replace title
  html = html.replace(
    new RegExp(`(data-video-id="${id}"[\\s\\S]*?)<div class="yt-title">[^<]*<\\/div>`),
    `$1<div class="yt-title">${title}</div>`
  );
  // Replace date in meta (second span)
  html = html.replace(
    new RegExp(`(data-video-id="${id}"[\\s\\S]*?<div class="yt-meta"><span>bazze tv<\\/span><span>)[^<]*(<\\/span>)`),
    `$1${date}$2`
  );

  console.log(`✓ ${id}: ${views} views — "${title.slice(0, 50)}"`);
}

writeFileSync('index.html', html);
console.log('index.html updated.');
