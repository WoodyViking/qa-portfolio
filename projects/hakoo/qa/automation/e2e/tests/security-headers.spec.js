// Security headers from src/.htaccess (applied by the test web server).
// Traceability: NFR-SEC-05, REG-GDPR-32.
const { test, expect } = require('@playwright/test');

for (const path of ['/index.html', '/community.html', '/hakoo-games.html']) {
  test(`TC-E2E-030: ${path} sends the security headers`, async ({ request }) => {
    const res = await request.get(path);
    expect(res.status()).toBe(200);
    const h = res.headers();
    expect(h['content-security-policy']).toContain("default-src 'self'");
    expect(h['content-security-policy']).toContain("object-src 'none'");
    expect(h['x-content-type-options']).toBe('nosniff');
    expect(h['x-frame-options']).toBe('SAMEORIGIN');
    expect(h['referrer-policy']).toBe('strict-origin-when-cross-origin');
  });
}

test('TC-E2E-031: the users library cannot be opened directly', async ({ request }) => {
  const res = await request.get('/hakoo-users.inc.php');
  expect(res.status()).toBe(404);
});

test('TC-E2E-123: pages, scripts and styles are always checked with the server (no stale page after an upload)', async ({ request }) => {
  for (const path of ['/games/chess.html', '/js/shared/board-game.js', '/css/games/board-game.css', '/profile.html']) {
    const res = await request.get(path);
    expect(res.headers()['cache-control'], path).toBe('no-cache');
    expect(res.headers()['expires'], path).toBeUndefined();
  }
});
