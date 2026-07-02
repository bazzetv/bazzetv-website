import { createServer } from 'http';
import { exec } from 'child_process';

const CLIENT_ID = process.env.GOOGLE_CLIENT_ID;
const CLIENT_SECRET = process.env.GOOGLE_CLIENT_SECRET;

if (!CLIENT_ID || !CLIENT_SECRET) {
  console.error('Usage: GOOGLE_CLIENT_ID=xxx GOOGLE_CLIENT_SECRET=yyy node scripts/get-refresh-token.mjs');
  process.exit(1);
}

const REDIRECT_URI = 'http://localhost:8080';
const SCOPES = [
  'https://www.googleapis.com/auth/yt-analytics.readonly',
  'https://www.googleapis.com/auth/youtube.readonly',
].join(' ');

const authUrl = `https://accounts.google.com/o/oauth2/v2/auth?` +
  `client_id=${CLIENT_ID}&` +
  `redirect_uri=${encodeURIComponent(REDIRECT_URI)}&` +
  `response_type=code&` +
  `scope=${encodeURIComponent(SCOPES)}&` +
  `access_type=offline&` +
  `prompt=consent`;

console.log('\n🔗 Opening browser for Google OAuth...\n');
exec(`open "${authUrl}"`);

const server = createServer(async (req, res) => {
  const url = new URL(req.url, 'http://localhost:8080');
  const code = url.searchParams.get('code');

  if (!code) {
    res.end('No code received.');
    return;
  }

  res.end('<h2>✅ Auth successful! Check your terminal for the refresh token.</h2>');

  const tokenRes = await fetch('https://oauth2.googleapis.com/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      code,
      client_id: CLIENT_ID,
      client_secret: CLIENT_SECRET,
      redirect_uri: REDIRECT_URI,
      grant_type: 'authorization_code',
    }),
  });

  const tokens = await tokenRes.json();

  if (tokens.refresh_token) {
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('✅ REFRESH TOKEN (ajoute-le dans GitHub secrets):');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log(tokens.refresh_token);
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
    console.log('Secret name: GOOGLE_REFRESH_TOKEN');
  } else {
    console.error('❌ Error:', JSON.stringify(tokens));
  }

  server.close();
});

server.listen(8080, () => {
  console.log('Waiting for Google redirect on http://localhost:8080...');
});
