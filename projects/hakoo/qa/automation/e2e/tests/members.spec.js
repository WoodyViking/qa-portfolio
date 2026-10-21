// The members' profiles (members.html, js/members.js, members.php) and the choice on the profile page.
// Fixture: Bob shows his profile and his linked accounts; Alice (owner, admin) and Carol keep theirs hidden.
// Traceability: FR-PROFILE-01..05.
const { test, expect } = require('@playwright/test');
const { loginAs, freshUserId } = require('./helpers');

const ALICE = '100000000000000001', BOB = '100000000000000002';
const SHOTS = '../../test-results/members';

test('TC-E2E-213: logged out, a blurred made-up sample and a way to log in; no member\'s data reaches the page', async ({ page }, info) => {
  const answers = [];
  page.on('response', r => { if (r.url().includes('members.php')) answers.push(r.status()); });
  await page.goto('/members.html');
  await expect(page.locator('#teaser')).toBeVisible();
  await expect(page.locator('.teaser-call a')).toHaveAttribute('href', /discord-auth\.php\?action=start/);
  await expect(page.locator('#teaser-card')).toHaveCSS('filter', /blur/);
  const html = await page.content();
  for (const real of ['Bob', 'Alice', 'TestTanker']) expect(html).not.toContain(real);     // only the sample's made-up data
  expect(answers).toEqual([401]);
  await page.screenshot({ path: `${SHOTS}-teaser-${info.project.name}.png` });
});

test('TC-E2E-214: a member sees the shown profiles and opens one; shows their own from the profile page; an admin also sees the hidden ones', async ({ browser, baseURL }, info) => {
  // A fresh member, so the browsers running at the same time do not change each other's choice.
  const me = freshUserId(), freya = `Freya${me.slice(-4)}`;
  const m = await browser.newContext();
  await loginAs(m, baseURL, me, freya);
  const pm = await m.newPage();
  await pm.goto('/members.html');
  await expect(pm.locator('#people .person', { hasText: 'Bob' })).toHaveCount(1);
  await expect(pm.locator('#people .person', { hasText: 'Alice' })).toHaveCount(0);       // hidden
  await expect(pm.locator('#people .person', { hasText: freya })).toContainText('(you)');
  await pm.locator('#people .person', { hasText: 'Bob' }).click();
  await expect(pm).toHaveURL(new RegExp(`#m=${BOB}$`));
  const p = pm.locator('#profile');
  await expect(p.locator('h2')).toContainText('Bob');                          // (a title may stand before it)
  await expect(p).toContainText('Gomoku');
  await expect(p.locator('.rb td.won')).toHaveCount(3);                                   // Runborg: Farmer, Viking, Shieldmaiden
  await expect(p).toContainText('TestTanker_Bob');                                       // he shows his accounts
  await pm.screenshot({ path: `${SHOTS}-bob-${info.project.name}.png`, fullPage: true });
  await pm.goto(`/members.html#m=${ALICE}`);
  await expect(p).toContainText('has not shown their profile');

  // Her own choice, on the profile page.
  await pm.goto('/profile.html#public');
  await expect(pm.locator('#pub-msg')).toHaveText('');
  await expect(pm.locator('#pub-visible')).not.toBeChecked();
  await expect(pm.locator('#pub-accounts')).toBeDisabled();
  await pm.check('#pub-visible');
  await expect(pm.locator('#pub-msg')).toHaveText('Saved ✓');
  await expect(pm.locator('#pub-accounts')).toBeEnabled();

  const a = await browser.newContext();
  await loginAs(a, baseURL, ALICE, 'Alice');
  const pa = await a.newPage();
  await pa.goto('/members.html');
  await expect(pa.locator('#sub')).toContainText('As an admin you also see the hidden profiles');
  await expect(pa.locator('#people .person', { hasText: 'Carol' }).locator('.tag')).toHaveText('hidden');
  await expect(pa.locator('#people .person', { hasText: freya }).locator('.tag')).toHaveCount(0);   // she shows hers now
  await pa.locator('#people .person', { hasText: 'Carol' }).click();
  await expect(pa.locator('#profile')).toContainText('you see it as an admin');
  await expect(pa.locator('#profile')).toContainText('last login');
  for (const ctx of [m, a]) await ctx.close();
});
