// Privacy by design: pages that promise "nothing stored" must keep that promise.
// Traceability: US-03, FR-GAMES-01..03, REG-EPR-01, REG-GDPR-25.
const { test, expect } = require('@playwright/test');

async function openClean(page, context, path) {
  await context.clearCookies();
  await page.goto('/index.html?choose=1');
  await page.evaluate(() => { localStorage.clear(); sessionStorage.clear(); });
  const requests = [];
  page.on('request', r => requests.push(r.url()));
  await page.goto(path);
  await page.waitForLoadState('networkidle');
  const storage = await page.evaluate(() => ({ local: Object.keys(localStorage), session: Object.keys(sessionStorage) }));
  return { requests, storage, cookies: await context.cookies() };
}

test('TC-E2E-020: HAKOO Games stores nothing and calls no third party', async ({ page, context, baseURL }) => {
  const { requests, storage, cookies } = await openClean(page, context, '/hakoo-games.html');
  expect(storage.local).toEqual([]);
  expect(storage.session).toEqual([]);
  expect(cookies).toEqual([]);
  const origin = new URL(baseURL).origin;
  expect(requests.filter(u => !u.startsWith(origin))).toEqual([]);
});

// TC-E2E-021 ("HAKOO Games runs no JavaScript") was retired on 2026-09-27: the owner decided the
// Games hub gets the normal login corner and badges that show the visitor's real state.
test('TC-E2E-021: logged out, the HAKOO Games badges say anonymous and nothing stored', async ({ page, context }) => {
  await context.clearCookies();
  await page.goto('/hakoo-games.html');
  await expect(page.locator('#b-who')).toHaveText('🕶 Anonymous');
  await expect(page.locator('#b-stored')).toHaveText('🍪 Nothing stored');
  await expect(page.locator('#auth-area a.auth-login')).toHaveAttribute('href', /discord-auth\.php\?action=start&return=hakoo-games\.html$/);
});

test('TC-E2E-022: the start page stores nothing until the visitor opts in', async ({ page, context }) => {
  const { storage, cookies } = await openClean(page, context, '/index.html?choose=1');
  expect(storage.local).toEqual([]);
  expect(cookies).toEqual([]);
});

for (const path of ['/privacy.html', '/terms.html', '/install.html']) {
  test(`TC-E2E-023: ${path} does not write storage on page load (DEF-002 regression)`, async ({ page, context }) => {
    const { storage } = await openClean(page, context, path);
    expect(storage.local).toEqual([]);
  });
}

test('TC-E2E-024: clicking a language flag is what saves the language', async ({ page, context }) => {
  await openClean(page, context, '/privacy.html');
  await page.click('.lang-current');                       // the language menu (js/shared/lang-menu.js)
  expect(await page.evaluate(() => localStorage.getItem('wot_lang'))).toBeNull();
  await page.click('.lang-flag[data-lang=sv]');
  expect(await page.evaluate(() => localStorage.getItem('wot_lang'))).toBe('sv');
});
