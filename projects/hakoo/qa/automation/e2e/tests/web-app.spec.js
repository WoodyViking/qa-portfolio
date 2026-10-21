// hakoo.eu as an app from the home screen (PWA): the manifest, its icons and the tags on the pages.
// Traceability: NFR-PORT-02.
const { test, expect } = require('@playwright/test');

const PAGES = ['/index.html?choose=1', '/hakoo-games.html', '/community.html', '/profile.html', '/games/sudoku.html',
  '/games/blockdoku.html', '/games/chess.html', '/games/sudoku-rules.html', '/games/path-of-berserk.html', '/games/runborg-lab.html'];

/** Width and height of a PNG, from its IHDR header. */
const pngSize = buf => [buf.readUInt32BE(16), buf.readUInt32BE(20)];

test('TC-E2E-253: the manifest makes an app that opens without the browser bars; its icons are there; every page links it', async ({ request }) => {
  const res = await request.get('/manifest.webmanifest');
  expect(res.status()).toBe(200);
  expect(res.headers()['content-type']).toMatch(/json/);
  const m = await res.json();
  expect([m.name, m.display, m.scope, m.start_url]).toEqual(['HAKOO', 'standalone', '/', '/index.html']);
  const want = { '192x192 any': [192, 192], '512x512 any': [512, 512], '512x512 maskable': [512, 512] };
  expect(m.icons.map(i => `${i.sizes} ${i.purpose}`).sort()).toEqual(Object.keys(want).sort());
  for (const icon of m.icons) {
    const r = await request.get(icon.src);
    expect(r.status(), icon.src).toBe(200);
    expect(r.headers()['content-type']).toBe('image/png');
    expect(pngSize(await r.body()), icon.src).toEqual(want[`${icon.sizes} ${icon.purpose}`]);
  }
  const apple = await request.get('/apple-touch-icon.png');
  expect(pngSize(await apple.body())).toEqual([180, 180]);

  // The tags are in each page's own HTML (read before any script runs, as the phone does when adding it)
  for (const path of PAGES) {
    const html = await (await request.get(path)).text();
    const tag = re => (html.match(re) || [])[1];
    const tags = {
      manifest: tag(/<link rel="manifest" href="([^"]+)"/) && new URL(tag(/<link rel="manifest" href="([^"]+)"/), `http://x${path}`).pathname,
      capable: tag(/<meta name="apple-mobile-web-app-capable" content="([^"]+)"/),
      title: tag(/<meta name="apple-mobile-web-app-title" content="([^"]+)"/),
      theme: tag(/<meta name="theme-color" content="([^"]+)"/),
    };
    expect(tags, path).toEqual({ manifest: '/manifest.webmanifest', capable: 'yes', title: 'HAKOO', theme: '#0d1117' });
  }
});
