// The admin page (admin.html, js/admin.js, admin.php): Alice is the owner (DISCORD_ADMIN_IDS in the
// test config). She makes Bob an admin by picking his name; Bob then sees the statistics and blocks
// and unblocks a loot code; Carol, a member, sees nothing. The Discord API is the stub.
// Traceability: FR-ADMIN-01..05, FR-ADMIN-07, FR-ADMIN-08, FR-ADMIN-09.
const { test, expect } = require('@playwright/test');
const { loginAs, freshUserId } = require('./helpers');

const ALICE = '100000000000000001', BOB = '100000000000000002', CAROL = '100000000000000003';
const MEMBERS = { members: [
  { user: { id: ALICE, username: 'alice', global_name: 'Alice' }, nick: null },
  { user: { id: BOB, username: 'bob', global_name: 'Bob' }, nick: null },
  { user: { id: CAROL, username: 'carol', global_name: 'Carol' }, nick: 'Carol' },
  { user: { id: '100000000000000777', username: 'hakoobot', bot: true }, nick: null },
] };
const SHOTS = '../../test-results/admin';

test('TC-E2E-206: the owner gives a member several roles and a name of their own, from the menu; an admin gives every role but Admin; a member with a badge gets no page', async ({ browser, baseURL }, info) => {
  // Fresh members, so the browsers running at the same time do not change each other's roles (DEF-012).
  const fresh = () => { let id; do id = freshUserId(); while (/(404|777)$/.test(id)); return id; };   // the stub's "not a member" and "bot"
  const helper = fresh(), badge = fresh();
  const name = id => `Member ${id.slice(-4)}`;                                 // what the Discord stub calls them
  const members = { members: [...MEMBERS.members,
    { user: { id: helper, username: 'helper', global_name: 'Helper' }, nick: null },
    { user: { id: badge, username: 'badge', global_name: 'Badge' }, nick: null }] };
  const a = await browser.newContext();
  await loginAs(a, baseURL, ALICE, 'Alice');
  const pa = await a.newPage();
  await pa.route('**/discord_presence.json', r => r.fulfill({ json: members }));
  await pa.goto('/admin.html');
  await expect(pa.locator('#who')).toContainText('You are the owner');
  await expect(pa.locator('#sec-stats')).toBeVisible();                        // the first section; one at a time
  await expect(pa.locator('#sec-members')).toBeHidden();
  await pa.locator('.am-item[data-sec=members]').click();
  await expect(pa.locator('#sec-members')).toBeVisible();
  await expect(pa.locator('#sec-stats')).toBeHidden();
  await expect(pa.locator('.am-item[data-sec=members]')).toHaveAttribute('aria-current', 'page');
  expect(await pa.locator('#members option').evaluateAll(o => o.map(x => x.value))).toEqual(['Alice', 'Badge', 'Bob', 'Carol', 'Helper']);   // no bots

  // Admin, with a name of my own.
  await pa.locator('#member').fill('Helper');
  await pa.locator('#role').selectOption('admin');
  await pa.locator('#given-name').fill('Helpy');
  await pa.locator('#role-form button[type=submit]').click();
  await expect(pa.locator('#role-msg')).toHaveText('Added ✓');
  const helperRow = pa.locator(`#people tr[data-id="${helper}"]`);
  await expect(helperRow.locator('.known')).toHaveText('Helpy');
  await expect(helperRow.locator('.disc')).toContainText('Discord: Helper');  // the name on the server today (the stub said another)
  await expect(helperRow.locator('.chip.admin')).toHaveAttribute('aria-pressed', 'true');
  // A second role on the same member: Tester also gives the Discord role (the stub has it).
  await helperRow.locator('.chip.tester').click();
  await expect(pa.locator('#role-msg')).toContainText('the role Hakoo testers on Discord ✓');
  await expect(helperRow.locator('.chip.tester')).toHaveAttribute('aria-pressed', 'true');
  await expect(helperRow.locator('.chip.admin')).toHaveAttribute('aria-pressed', 'true');
  // The name changed with ✏️.
  await helperRow.locator('[data-name]').click();
  await helperRow.locator('.name-edit input').fill('Helpy the Great');
  await helperRow.locator('.name-edit button:not([data-cancel])').click();
  await expect(helperRow.locator('.known')).toHaveText('Helpy the Great');

  await pa.locator('#member').fill('Badge');
  await pa.locator('#role').selectOption('og');
  await pa.locator('#role-form button[type=submit]').click();
  await expect(pa.locator('#role-msg')).toHaveText('Added ✓');
  await expect(pa.locator(`#people tr[data-id="${badge}"] .chip.og`)).toHaveAttribute('aria-pressed', 'true');
  await pa.screenshot({ path: `${SHOTS}-owner-${info.project.name}.png`, fullPage: true });

  const b = await browser.newContext();
  await loginAs(b, baseURL, helper, 'Helper');
  const pb = await b.newPage();
  await pb.goto('/profile.html');
  await expect(pb.locator('#pmenu a[href="admin.html"]')).toHaveCount(1);       // the way in, for admins
  await pb.goto('/admin.html#members');
  await expect(pb.locator('#who')).toContainText('You are an admin');
  await expect(pb.locator('#role-form')).toBeVisible();                         // an admin gives roles too ...
  await expect(pb.locator('#role option[value="admin"]')).toHaveJSProperty('disabled', true);   // ... but not Admin
  await expect(pb.locator(`#people tr[data-id="${helper}"] .known`)).toHaveText('Helpy the Great');
  await expect(pb.locator('#people [data-name]')).toHaveCount(0);
  await expect(pb.locator('#people .chip.admin:enabled')).toHaveCount(0);
  await expect(pb.locator(`#people tr[data-id="${helper}"] .chip.tester`)).toBeEnabled();

  const c = await browser.newContext();
  await loginAs(c, baseURL, badge, 'Badge');
  const pc = await c.newPage();
  await pc.goto('/admin.html');
  await expect(pc.locator('#login-text')).toHaveText('This page is for the HAKOO admins.');
  await expect(pc.locator('#app')).toBeHidden();
  await expect(pc.locator('#amenu')).toBeHidden();
  await pc.goto('/profile.html');
  await expect(pc.locator('#pmenu a.pm-item').first()).toBeVisible();
  await expect(pc.locator('#pmenu a[href="admin.html"]')).toHaveCount(0);       // a badge is not the admin role

  // The owner switches Admin off: the helper is out again (and still a Tester).
  await helperRow.locator('.chip.admin').click();
  await expect(helperRow.locator('.chip.admin')).toHaveAttribute('aria-pressed', 'false');
  await expect(helperRow.locator('.chip.tester')).toHaveAttribute('aria-pressed', 'true');
  await pb.reload();
  await expect(pb.locator('#login-text')).toHaveText('This page is for the HAKOO admins.');
  for (const ctx of [a, b, c]) await ctx.close();
});

test('TC-E2E-207: the page shows who plays (numbers only) and a reported loot code can be blocked and unblocked; it fits a phone', async ({ page, context, baseURL }, info) => {
  // A game started, a code reported by Carol.
  await page.request.post('/gomoku.php?action=new', { data: { mode: 'computer', color: 'black', difficulty: 'easy' } });
  const code = `E2E${info.project.name.toUpperCase().slice(0, 3)}${Date.now() % 100000}`;
  const carol = await page.context().browser().newContext();
  await loginAs(carol, baseURL, CAROL, 'Carol');
  await carol.request.post(`${baseURL}/codes.php?action=report`, { data: { game: 'wot', code } });
  await carol.close();

  await loginAs(context, baseURL, ALICE, 'Alice');
  await page.goto('/admin.html');
  await expect(page.locator('#tiles .tile')).toHaveCount(4);
  await expect(page.locator('#logins .bar')).toHaveCount(30);
  await expect(page.locator('#games tbody tr', { hasText: 'Gomoku' })).toHaveCount(1);
  await page.locator('.am-item[data-sec=loot]').click();
  await expect(page.locator('#sec-loot')).toBeVisible();
  const row = page.locator('#loot tr', { hasText: code });
  await expect(row).toContainText('1 / 5');
  await row.locator('[data-block]').click();
  await expect(page.locator('#loot tr', { hasText: code }).locator('[data-unblock]')).toHaveCount(1);
  await expect(page.locator('#loot tr', { hasText: code })).toContainText('an admin');
  await page.screenshot({ path: `${SHOTS}-page-${info.project.name}.png`, fullPage: true });
  await page.locator('#loot tr', { hasText: code }).locator('[data-unblock]').click();
  await expect(page.locator('#loot tr', { hasText: code })).toHaveCount(0);       // unblocked, and its reports went with it
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});
