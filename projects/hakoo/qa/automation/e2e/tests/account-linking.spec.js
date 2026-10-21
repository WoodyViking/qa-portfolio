// Pages that need a game account send the visitor to the profile page, which
// helps them link it and sends them back (US-13). Uses the Wargaming stub.
// Test design: STATE TRANSITION (see qa/test-design/state-transition.md § 3).
// Traceability: US-13, FR-LINK-01..07, FR-AUTH-05.
// Tests that change data, or that need a member with NO data, use freshUserId(): the database is
// shared by all tests and browsers, so the shared users (Alice, Bob, Carol) may only be read.
const { test, expect } = require('@playwright/test');
const { USERS, loginAs, freshUserId } = require('./helpers');

test.beforeEach(async ({ page, context }) => {
  await context.clearCookies();
  await page.goto('/index.html?choose=1');
  await page.evaluate(() => localStorage.clear());
});

test('TC-E2E-050: WoT tracker without Discord login goes to the profile, which asks for login', async ({ page }) => {
  await page.goto('/wot-tracker.html');
  await expect(page).toHaveURL(/profile\.html\?need=wot&return=wot-tracker\.html$/);
  const login = page.locator('#profile-login');
  await expect(login).toBeVisible();
  // The Discord login keeps the need/return context so the visitor comes back.
  expect(decodeURIComponent(await login.getAttribute('href')))
    .toContain('return=profile.html?need=wot&return=wot-tracker.html');
});

test('TC-E2E-051: logged in without a WoT link, the profile explains what is missing', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, freshUserId(), 'New member');   // guaranteed to have nothing linked
  await page.goto('/WoWs-tracker.html');
  await expect(page).toHaveURL(/profile\.html\?need=wot&return=WoWs-tracker\.html$/);
  await expect(page.locator('#need-banner')).toContainText('The WoWs Tracker needs your Wargaming account');
  await expect(page.locator('#need-banner a.btn')).toHaveAttribute('href', 'link.php?action=wot_start&return=WoWs-tracker.html');
});

test('TC-E2E-052: linking via Wargaming returns the visitor to the page that needed it', async ({ page, context, baseURL }) => {
  // A brand-new member: this test links an account, so it must not touch the shared test users.
  await loginAs(context, baseURL, freshUserId(), 'New member');
  await page.goto('/profile.html?need=wot&return=WoWs-tracker.html');
  await page.click('#need-banner a.btn');                     // -> link.php -> Wargaming stub -> link.php -> profile -> tracker
  await expect(page).toHaveURL(/WoWs-tracker\.html$/, { timeout: 15_000 });
  const stored = await page.evaluate(() => ({ t: localStorage.getItem('wot_token'), a: localStorage.getItem('wot_account') }));
  expect(stored).toEqual({ t: 'valid-token-500000077', a: '500000077' });
});

test('TC-E2E-053: the Wargaming token never stays in the address bar', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, USERS.carol, 'Carol');
  const handoff = Buffer.from(JSON.stringify({ t: 'valid-token-500000077', a: '500000077', n: 'Mock', e: 1999999999, app: 'test' }))
    .toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  await page.goto(`/profile.html?linked=wot#wg=${handoff}`);
  await expect(page).toHaveURL(/profile\.html\?linked=wot$/);
  expect(await page.evaluate(() => location.hash)).toBe('');
  expect(await page.evaluate(() => localStorage.getItem('wot_token'))).toBe('valid-token-500000077');
});

test('TC-E2E-054: a refused link shows an error and links nothing', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, USERS.carol, 'Carol');
  await page.goto('/profile.html?link_error=wot');
  await expect(page.locator('#need-banner.err')).toContainText('could not confirm');
});

test('TC-E2E-055: linked account but no token in this browser asks to connect Wargaming again', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, USERS.bob, 'Bob');           // Bob has WoT 500000012 linked, no token here
  await page.goto('/wot-tracker.html');
  await expect(page).toHaveURL(/profile\.html\?need=wot&return=wot-tracker\.html$/);
  await expect(page.locator('#need-banner')).toContainText('needs a fresh Wargaming sign-in');
});

test('TC-E2E-056: WoWs tracker opens directly when the account is linked (no token needed)', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, USERS.bob, 'Bob');
  await page.goto('/WoWs-tracker.html');
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/WoWs-tracker\.html$/);
  expect(await page.evaluate(() => localStorage.getItem('wot_account'))).toBe('500000012');
});
