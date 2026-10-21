// The players on HAKOO Games (hakoo-games.html, js/hakoo-games.js, members.php ?action=players/card) and the viking
// in 3D (js/shared/viking-3d.js). Fixture: Bob shows his profile and has a viking with an axe; Alice keeps her
// profile hidden; Carol has left the players list.
// The Discord stub (qa/automation/mocks/wg-api.php) gives Bob's name its colour (an Officer: purple).
// Traceability: FR-PLAYERS-01..07, FR-AVATAR-08..10.
const { test, expect } = require('@playwright/test');
const { loginAs, freshUserId } = require('./helpers');

const SHOTS = '../../test-results/players';
/** The viking on the stage: in 3D (a canvas), or the flat portrait where the browser has no WebGL. */
const stageViking = page => page.locator('#pl-stage canvas.viking-3d, #pl-stage svg.viking-av');

test('TC-E2E-218: logged out, the players are a blurred made-up sample with a way to log in; no member\'s data reaches the page', async ({ page }, info) => {
  const answers = [];
  page.on('response', r => { if (r.url().includes('members.php')) answers.push(r.status()); });
  await page.goto('/hakoo-games.html');
  await expect(page.locator('#pl-call')).toBeVisible();
  await expect(page.locator('#pl-call a')).toHaveAttribute('href', /discord-auth\.php\?action=start/);
  await expect(page.locator('#pl-items')).toHaveClass(/blur/);
  await expect(page.locator('#pl-items .pl-item')).toHaveCount(6);
  await expect(page.locator('#pl-stage')).toHaveClass(/blur/);
  const html = await page.content();
  for (const real of ['Bob', 'Alice', 'Carol']) expect(html).not.toContain(real);
  expect(answers).toEqual([401]);                                                  // asked once: "not logged in"
  await page.screenshot({ path: `${SHOTS}-teaser-${info.project.name}.png` });
});

test('TC-E2E-219: a member sees who plays, themselves first; a click shows the viking, the medals, and the profile only when it is shown', async ({ page, context, baseURL }, info) => {
  const me = freshUserId(), name = `Gudrun${me.slice(-4)}`;
  await loginAs(context, baseURL, me, name);
  await page.goto('/hakoo-games.html');
  const list = page.locator('#pl-items');
  await expect(list.locator('.pl-item', { hasText: name })).toHaveAttribute('aria-pressed', 'true');   // her own card first
  await expect(page.locator('#pl-who')).toContainText(name);
  await expect(page.locator('#pl-info')).toContainText('Only you see this');                         // her profile is hidden
  await expect(stageViking(page)).toHaveCount(1);
  await expect(list.locator('.pl-item', { hasText: 'Carol' })).toHaveCount(0);                     // she left the list
  // Herself first (she has not played yet), then the owner, then by rank: free members have no rank by their name
  await expect(list.locator('.pl-group').first()).toHaveText('You — 1');
  await expect(list.locator('.pl-group', { hasText: 'King — 1' })).toHaveCount(1);                  // Alice, the site's owner, is the King
  await expect(list.locator('.pl-group', { hasText: /^Members — \d+$/ })).toHaveCount(1);
  await expect(list.locator('.pl-item', { hasText: 'Bob' }).locator('.pl-tag.rank')).toHaveCount(0);   // nobody is a farmer unless they pick it
  await expect(list.locator('.pl-item', { hasText: 'Alice' }).locator('.pl-tag.owner')).toHaveText('King');
  await expect(list.locator('.pl-item', { hasText: 'Bob' }).locator('.pl-name span[style]')).toHaveCSS('color', 'rgb(155, 89, 182)');
  await expect(list.locator('.pl-item', { hasText: name }).locator('.pl-tag.you')).toHaveText('you');

  await list.locator('.pl-item', { hasText: 'Bob' }).click();
  await expect(page.locator('#pl-who')).toContainText('Bob');
  await expect(page.locator('#pl-medals .vk-medal[title="Sudoku: won on Easy"]')).toHaveCount(1);   // bronze in each puzzle
  await expect(page.locator('#pl-info')).toContainText('Runborg titles');
  await expect(page.locator('#pl-info a', { hasText: 'The whole profile' })).toHaveAttribute('href', /members\.html#m=100000000000000002$/);
  await page.screenshot({ path: `${SHOTS}-bob-${info.project.name}.png` });

  await list.locator('.pl-item', { hasText: 'Alice' }).click();
  await expect(page.locator('#pl-info')).toContainText('keeps their profile to themselves');
  await expect(page.locator('#pl-medals .vk-medal[title="Sudoku: won on Hard"]')).toHaveCount(1);   // the medals show anyway
  await expect(stageViking(page)).toHaveCount(1);                                                // one viewer, changed in place
});

test('TC-E2E-220: the viking in 3D on the profile, with height, build, a dress and bare feet; in the players list once he has played, out of it when he leaves it', async ({ browser, page, context, baseURL }) => {
  const me = freshUserId(), name = `Halvar${me.slice(-4)}`;
  await loginAs(context, baseURL, me, name);
  await page.goto('/profile.html#viking');
  const v = page.locator('#viking');
  await expect(v.locator('#vk-3d canvas.viking-3d, #vk-3d svg.viking-av')).toHaveCount(1);
  await expect(v.locator('#vk-waist option[value=dice]')).toHaveJSProperty('disabled', true);
  await expect(v.locator('.vk-locked')).toContainText('Necklace of Gomoku stones: Win Gomoku on Hard');
  await v.locator('#vk-height').fill('90');
  await v.locator('#vk-build').fill('20');
  await v.locator('#vk-save').click();
  await expect(v.locator('#vk-msg')).toHaveText('Saved ✓');
  await page.reload();
  await expect(page.locator('#vk-height')).toHaveValue('90');
  await expect(page.locator('#vk-build')).toHaveValue('20');
  // A dress, bare feet: the choices everyone has
  await v.locator('input[name=vk-legs][value=dress]').check({ force: true });
  await v.locator('input[name=vk-feet][value=barefoot]').check({ force: true });
  await expect(v.locator('input[name=vk-bg][value=fjord]')).toBeDisabled();         // not won yet
  await v.locator('#vk-save').click();
  await expect(v.locator('#vk-msg')).toHaveText('Saved ✓');

  const other = await browser.newContext();
  await loginAs(other, baseURL, freshUserId(), 'Ingrid');
  const po = await other.newPage();
  await po.goto('/hakoo-games.html');
  await expect(po.locator('#pl-items .pl-item').first()).toBeVisible();
  await expect(po.locator('#pl-items .pl-item', { hasText: name })).toHaveCount(0);  // he has not played yet
  expect((await page.request.post('/runborg.php?action=new', { data: { board: 'fjord', level: 'farmer' } })).status()).toBe(200);
  await po.reload();
  await expect(po.locator('#pl-items .pl-item', { hasText: name })).toHaveCount(1);
  await page.goto('/profile.html#public');
  await expect(page.locator('#pub-players')).toBeChecked();
  await page.uncheck('#pub-players');
  await expect(page.locator('#pub-msg')).toHaveText('Saved ✓');
  await po.reload();
  await expect(po.locator('#pl-items .pl-item').first()).toBeVisible();
  await expect(po.locator('#pl-items .pl-item', { hasText: name })).toHaveCount(0);
  await other.close();
});
