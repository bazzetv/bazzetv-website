import { readFileSync, writeFileSync } from 'fs';

const API_KEY        = process.env.YOUTUBE_API_KEY;
const CLIENT_ID      = process.env.GOOGLE_CLIENT_ID;
const CLIENT_SECRET  = process.env.GOOGLE_CLIENT_SECRET;
const REFRESH_TOKEN  = process.env.GOOGLE_REFRESH_TOKEN;

if (!API_KEY || !CLIENT_ID || !CLIENT_SECRET || !REFRESH_TOKEN) {
  console.error('Missing env vars: YOUTUBE_API_KEY, GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REFRESH_TOKEN');
  process.exit(1);
}

const VIDEO_IDS  = ['pRt6h1xFA9U', 'y5u2yPym1CI', 'UOiAT0sCeEM', '5h_v3HbX7k4'];
const CHANNEL_ID = 'UCQZa27_VXLUowoMsYo6Y0Dw';
const HEADERS    = { Referer: 'https://bazzetv.fr' };

// ── helpers ────────────────────────────────────────────────────────────────
function fmt(n) {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0','') + 'M';
  if (n >= 1_000)     return (n / 1_000).toFixed(1).replace('.0','') + 'K';
  return String(n);
}
function fmtDur(iso) {
  const m = iso.match(/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/);
  const h = +( m[1]||0), min = +(m[2]||0), s = +(m[3]||0);
  return h ? `${h}:${String(min).padStart(2,'0')}:${String(s).padStart(2,'0')}`
           : `${min}:${String(s).padStart(2,'0')}`;
}
function timeAgo(d) {
  const diff = Math.floor((Date.now() - new Date(d)) / 1000);
  if (diff < 86400)        return `${Math.floor(diff/3600)}h ago`;
  if (diff < 7*86400)      return `${Math.floor(diff/86400)} days ago`;
  if (diff < 30*86400)     return `${Math.floor(diff/604800)} weeks ago`;
  if (diff < 365*86400)    return `${Math.floor(diff/2592000)} months ago`;
  return `${Math.floor(diff/31536000)} years ago`;
}
function pct(n) { return Math.round(n * 10) / 10 + '%'; }

function inject(html, attr, value) {
  // Replace text content after data-stat="attr">
  html = html.replace(
    new RegExp(`(data-stat="${attr}">)[^<]*`), `$1${value}`
  );
  // Replace bar width (include closing quote)
  html = html.replace(
    new RegExp(`(data-stat-bar="${attr}" style="width:)[^"]*"`), `$1${value}"`
  );
  return html;
}

// ── get OAuth access token ─────────────────────────────────────────────────
const tokenRes = await fetch('https://oauth2.googleapis.com/token', {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({
    client_id: CLIENT_ID, client_secret: CLIENT_SECRET,
    refresh_token: REFRESH_TOKEN, grant_type: 'refresh_token',
  }),
});
const { access_token } = await tokenRes.json();
if (!access_token) { console.error('Failed to get access token'); process.exit(1); }
const authHeaders = { ...HEADERS, Authorization: `Bearer ${access_token}` };

// ── date range: last 28 days ───────────────────────────────────────────────
const end   = new Date(); end.setDate(end.getDate() - 1);
const start = new Date(end); start.setDate(start.getDate() - 27);
const fmt8  = d => d.toISOString().split('T')[0];
const [startDate, endDate] = [fmt8(start), fmt8(end)];

// ── fetch channel stats (public API) ──────────────────────────────────────
const chanRes  = await fetch(`https://www.googleapis.com/youtube/v3/channels?part=statistics&id=${CHANNEL_ID}&key=${API_KEY}`, { headers: HEADERS });
const chanData = await chanRes.json();
const chanStats = chanData.items?.[0]?.statistics;

// ── fetch video stats (public API) ────────────────────────────────────────
const vidRes  = await fetch(`https://www.googleapis.com/youtube/v3/videos?part=snippet,statistics,contentDetails&id=${VIDEO_IDS.join(',')}&key=${API_KEY}`, { headers: HEADERS });
const vidData = await vidRes.json();

// ── fetch 28-day analytics ────────────────────────────────────────────────
const analyticsBase = `https://youtubeanalytics.googleapis.com/v2/reports?ids=channel%3D%3D${CHANNEL_ID}&startDate=${startDate}&endDate=${endDate}`;

const [mainRes, demoRes, geoRes] = await Promise.all([
  fetch(`${analyticsBase}&metrics=views,estimatedMinutesWatched,averageViewDuration,averageViewPercentage`, { headers: authHeaders }),
  fetch(`${analyticsBase}&metrics=viewerPercentage&dimensions=ageGroup,gender`, { headers: authHeaders }),
  fetch(`${analyticsBase}&metrics=views&dimensions=country&sort=-views&maxResults=5`, { headers: authHeaders }),
]);

const [mainData, demoData, geoData] = await Promise.all([mainRes.json(), demoRes.json(), geoRes.json()]);

// ── parse analytics ───────────────────────────────────────────────────────
const row = mainData.rows?.[0] || [];
const [views28, watchMins, avgDurSec, avgPct] = row;

// demographics
const ageMap = {}, genderMap = { male: 0, female: 0 };
for (const [ageGroup, gender, viewerPct] of (demoData.rows || [])) {
  const key = ageGroup.replace('age','').replace('-','_').toLowerCase();
  ageMap[key] = (ageMap[key] || 0) + viewerPct;
  if (gender === 'male')   genderMap.male   += viewerPct;
  if (gender === 'female') genderMap.female += viewerPct;
}

// geo — map country codes to flags
const flags = { FR:'🇫🇷', MA:'🇲🇦', CA:'🇨🇦', BE:'🇧🇪', DZ:'🇩🇿', US:'🇺🇸', GB:'🇬🇧', DE:'🇩🇪', TN:'🇹🇳', SN:'🇸🇳' };
const countryNames = { FR:'France', MA:'Morocco', CA:'Canada', BE:'Belgium', DZ:'Algeria', US:'United States', GB:'United Kingdom', DE:'Germany', TN:'Tunisia', SN:'Senegal' };
const totalGeoViews = (geoData.rows || []).reduce((s, [,v]) => s + v, 0);

// ── build HTML ────────────────────────────────────────────────────────────
let html = readFileSync('index.html', 'utf8');

// channel
if (chanStats) {
  html = inject(html, 'subscribers', fmt(+chanStats.subscriberCount));
  html = inject(html, 'videoCount',  chanStats.videoCount);
  console.log(`✓ Channel: ${fmt(+chanStats.subscriberCount)} subs, ${chanStats.videoCount} videos`);
}

// 28-day stats
if (row.length) {
  const watchHours = Math.round(watchMins / 60 / 100) / 10;
  const m = Math.floor(avgDurSec / 60), s = Math.floor(avgDurSec % 60);
  html = inject(html, 'views28',        fmt(views28));
  html = inject(html, 'watchTime',      watchHours + 'K');
  html = inject(html, 'avgDuration',    `${m}:${String(s).padStart(2,'0')}`);
  html = inject(html, 'avgPercent',     Math.round(avgPct * 10) / 10 + '%');
  console.log(`✓ Analytics: ${fmt(views28)} views, ${watchHours}K watch hours, avg ${m}:${String(s).padStart(2,'0')}`);
}

// gender
const totalGender = genderMap.male + genderMap.female;
if (totalGender > 0) {
  const malePct   = Math.round(genderMap.male   / totalGender * 1000) / 10;
  const femalePct = Math.round(genderMap.female / totalGender * 1000) / 10;
  html = inject(html, 'malePct',   malePct + '%');
  html = inject(html, 'femalePct', femalePct + '%');
  console.log(`✓ Gender: ${malePct}% male, ${femalePct}% female`);
}

// age
const ageKeys = { age18_24: '18_24', age25_34: '25_34', age35_44: '35_44', age45_54: '45_54' };
for (const [statKey, mapKey] of Object.entries(ageKeys)) {
  const v = ageMap[mapKey];
  if (v !== undefined) {
    const rounded = Math.round(v * 10) / 10 + '%';
    html = inject(html, statKey, rounded);
  }
}
console.log(`✓ Age demographics injected`);

// geo — inject percentages via data-stat
if (geoData.rows?.length) {
  const geoStatKeys = ['geo_FR', 'geo_MA', 'geo_CA', 'geo_BE', 'geo_DZ'];
  const topRows = geoData.rows.slice(0, 5);
  topRows.forEach(([code, v], i) => {
    const p = Math.round(v / totalGeoViews * 1000) / 10 + '%';
    html = inject(html, geoStatKeys[i], p);
  });
  console.log(`✓ Geo: ${topRows.map(([c])=>c).join(', ')}`);
}

// videos
for (const item of (vidData.items || [])) {
  const id      = item.id;
  const views   = fmt(+item.statistics.viewCount);
  const dur     = fmtDur(item.contentDetails.duration);
  const date    = timeAgo(item.snippet.publishedAt);
  const title   = item.snippet.title;
  const isHot   = +item.statistics.viewCount >= 50_000;
  const badge   = isHot
    ? `<span class="yt-views-badge hot">🔥 ${views} views</span>`
    : `<span class="yt-views-badge">▶ ${views} views</span>`;

  html = html.replace(new RegExp(`(data-video-id="${id}"[\\s\\S]*?)<span class="yt-views-badge[^"]*">[^<]*<\\/span>`), `$1${badge}`);
  html = html.replace(new RegExp(`(data-video-id="${id}"[\\s\\S]*?)<span class="yt-duration">[^<]*<\\/span>`), `$1<span class="yt-duration">${dur}</span>`);
  html = html.replace(new RegExp(`(data-video-id="${id}"[\\s\\S]*?)<div class="yt-title">[^<]*<\\/div>`), `$1<div class="yt-title">${title}</div>`);
  html = html.replace(new RegExp(`(data-video-id="${id}"[\\s\\S]*?<div class="yt-meta"><span>bazze tv<\\/span><span>)[^<]*(<\\/span>)`), `$1${date}$2`);
  console.log(`✓ ${id}: ${views} views — "${title.slice(0,50)}"`);
}

writeFileSync('index.html', html);
console.log('\n✅ index.html fully updated.');
