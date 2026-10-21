// Shared helpers for the E2E tests.
const crypto = require('crypto');

// Must match <SESSION_SECRET> in docker/web/config.test.php (test value only).
const <SESSION_SECRET> = process.env.<SESSION_SECRET> || '<TEST_SECRET_VALUE>';

const USERS = {
  alice: '100000000000000001',   // admin, WoT 500000011 linked
  bob:   '100000000000000002',   // WoT 500000012 linked
  carol: '100000000000000003',   // logged in once, nothing linked
};

/** Log the browser context in as a test user by setting a signed hakoo_auth cookie. */
async function loginAs(context, baseURL, discordId, name = 'Tester') {
  const b = Buffer.from(JSON.stringify({ id: discordId, name, avatar: '', ts: Math.floor(Date.now() / 1000) }))
    .toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  const sig = crypto.createHmac('sha256', <SESSION_SECRET>).update(b).digest('hex');
  await context.addCookies([{ name: 'hakoo_auth', value: `${b}.${sig}`, url: baseURL }]);
}

/** A Discord id no other test uses, so tests that change data stay independent (DEF-012). */
function freshUserId() {
  return String(200000000000000000n + BigInt(Date.now()) * 1000n + BigInt(Math.floor(Math.random() * 1000)));
}

/**
 * On a phone the HAKOO Games menu bar is folded into ☰ (the page: links, ⚙, turning the board) and
 * 👤▾ (the language and the login) (js/shared/game-nav.js, FR-GAMES-18). Opens the one that holds
 * what the test wants; on a wider screen everything is in the bar and nothing happens.
 */
async function openNav(page, which = 'page') {
  const btn = page.locator(which === 'corner' ? 'nav .nav-more' : 'nav .nav-burger');
  if (await btn.count() && await btn.isVisible() && await btn.getAttribute('aria-expanded') !== 'true') await btn.click();
}
/** Closes a menu opened by openNav (so it does not lie over the board). */
async function closeNav(page) {
  if (await page.locator('nav [aria-expanded="true"]').count()) await page.keyboard.press('Escape');
}
/** The buttons that turn and tilt the board: under it, or in ☰ on a phone (opened). */
async function viewBar(page) {
  if (await page.locator('nav .nav-burger').isVisible()) { await openNav(page); return page.locator('nav .nav-view'); }
  return page.locator('.view-bar');
}

module.exports = { USERS, loginAs, freshUserId, openNav, closeNav, viewBar };
