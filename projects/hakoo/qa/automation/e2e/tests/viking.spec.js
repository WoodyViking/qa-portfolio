// The member's viking (profile → Your viking, js/shared/viking-avatar.js, avatar.php): the editor, the locked items,
// the login corner and the Runborg table. Fixture: Bob has a saved viking with an axe and shows it in the corner.
// Traceability: FR-AVATAR-02, FR-AVATAR-04, FR-AVATAR-05, FR-AVATAR-12, FR-AVATAR-13.
const { test, expect } = require('@playwright/test');
const { loginAs, freshUserId } = require('./helpers');

test('TC-E2E-216: a new member makes a viking: the free choices change the picture at once, the locked ones say how to win them; saved, it takes the corner', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, freshUserId(), 'Sigrid');
  await page.goto('/profile.html#viking');
  const v = page.locator('#viking');
  await expect(v.locator('#vk-preview svg.viking-av')).toBeVisible();
  await expect(v.locator('#vk-msg')).toContainText('Not saved yet');
  const before = await v.locator('#vk-preview').innerHTML();
  await v.locator('input[name=vk-body][value=woman]').check();
  await v.locator('input[name=vk-hair][value=red]').check();
  await v.locator('#vk-head').selectOption('plain');
  expect(await v.locator('#vk-preview').innerHTML()).not.toBe(before);           // the picture follows the choices
  await expect(v.locator('#vk-head option[value=crown]')).toHaveJSProperty('disabled', true);   // locked
  await expect(v.locator('.vk-locked')).toContainText('Crown: Beat the King in Runborg');
  await expect(v.locator('.vk-locked')).toContainText('Raven: 100 puzzles of the day in a row');
  await v.locator('#vk-corner').check();
  await v.locator('#vk-save').click();
  await expect(v.locator('#vk-msg')).toHaveText('Saved ✓');
  await page.screenshot({ path: `../../test-results/viking-editor-${info.project.name}.png`, fullPage: true });
  await page.goto('/hakoo-games.html');                                          // any page with the login corner
  await expect(page.locator('#auth-area img.auth-av')).toHaveAttribute('src', /^data:image\/svg\+xml/);
});

test('TC-E2E-217: Bob\'s viking with his axe: in his login corner and at the Runborg table; another member\'s corner keeps the Discord picture', async ({ page, context, baseURL }, info) => {
  test.skip(info.project.name !== 'chromium', 'one browser: Bob is shared by the browsers running at the same time');
  await loginAs(context, baseURL, '100000000000000002', 'Bob');
  await page.goto('/games/runborg.html');
  await expect(page.locator('#rb-me-name svg.viking-av')).toBeVisible();
  await page.goto('/hakoo-games.html');
  await expect(page.locator('#auth-area img.auth-av')).toHaveAttribute('src', /^data:image\/svg\+xml/);
  const carol = await page.context().browser().newContext();
  await loginAs(carol, baseURL, '100000000000000003', 'Carol');
  const pc = await carol.newPage();
  await pc.goto('/hakoo-games.html');
  await expect(pc.locator('#auth-area .auth-name')).toHaveText('Carol');
  await expect(pc.locator('#auth-area img.auth-av')).not.toHaveAttribute('src', /^data:/);
  await carol.close();
});

test('TC-E2E-225: the rings: choose the ring under the legs, then a colour won, and the legs take it; a new member has no colours yet', async ({ page, context, baseURL, browser }, info) => {
  test.skip(info.project.name !== 'chromium', 'one browser: Bob is shared by the browsers running at the same time');
  await loginAs(context, baseURL, '100000000000000002', 'Bob');                     // Easy in every game: every colour
  await page.goto('/profile.html#viking');
  const v = page.locator('#viking');
  await expect(v.locator('.vk-dab:not(.locked)')).toHaveCount(12);
  await v.locator('.vk-ring[data-slot=legcolor]').click();
  await expect(v.locator('#vk-paint-for')).toHaveText('Now a colour for the legs:');
  await v.locator('.vk-dab[data-colour=gold]').click();
  await expect(v.locator('.vk-ring[data-slot=legcolor]')).toHaveAttribute('aria-pressed', 'true');
  await expect(v.locator('input[name=vk-legcolor]:checked')).toHaveCount(0);
  await v.locator('#vk-save').click();
  await expect(v.locator('#vk-msg')).toHaveText('Saved ✓');
  await page.reload();
  await expect(page.locator('.vk-ring[data-slot=legcolor]')).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('.vk-ring[data-slot=legcolor] span')).toHaveAttribute('style', /background/);
  await page.locator('input[name=vk-legcolor][value=grey]').check({ force: true });  // back to a colour everyone has
  await expect(page.locator('.vk-ring[data-slot=legcolor]')).toHaveAttribute('aria-pressed', 'false');
  await page.locator('#vk-save').click();
  await expect(page.locator('#vk-msg')).toHaveText('Saved ✓');

  const ctx = await browser.newContext();
  await loginAs(ctx, baseURL, freshUserId(), 'Ylva');
  const p2 = await ctx.newPage();
  await p2.goto(`${baseURL}/profile.html#viking`);
  await expect(p2.locator('.vk-dab.locked')).toHaveCount(15);   // 12 for the games' Easy, 3 for Blockdoku's top three
  await expect(p2.locator('#vk-paint-for')).toHaveText('Win a game on Easy to win its colour.');
  await ctx.close();
});

test('TC-E2E-230: your own colour is a square, the colours everyone has are round, and the editor says how to use the squares', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, freshUserId(), 'Astrid');
  await page.goto('/profile.html#viking');
  const v = page.locator('#viking');
  await expect(v.locator('.vk-ring')).toHaveCount(3);                             // the clothes, the legs, the pattern
  const radius = loc => loc.evaluate(el => parseFloat(getComputedStyle(el).borderTopLeftRadius));
  const side = await v.locator('.vk-ring').first().evaluate(el => el.offsetWidth);
  expect(await radius(v.locator('.vk-ring').first())).toBeLessThan(side / 4);    // a square (rounded corners at most)
  expect(await radius(v.locator('.vk-sw').first())).toBeGreaterThanOrEqual(side / 2 - 4);   // a circle
  await expect(v.locator('.vk-own-hint')).toContainText('square');
  await expect(v.locator('.vk-ring').first()).toHaveAttribute('title', /choose this square/);
});

test('TC-E2E-235: the shield box shows while the viking holds the shield; its shape, colours, pattern and rim change the picture and are saved; the locked ones say how to win them', async ({ page, context, baseURL }, info) => {
  test.skip(info.project.name !== 'chromium', 'one browser: Bob is shared by the browsers running at the same time');
  const { USERS } = require('./helpers');
  await loginAs(context, baseURL, USERS.bob, 'Bob');
  await page.goto('/profile.html#viking');
  const v = page.locator('#viking'), box = v.locator('#vk-shieldbox');
  await v.locator('#vk-left').selectOption('none');
  await expect(box).toBeHidden();
  await v.locator('#vk-left').selectOption('shield');
  await expect(box).toBeVisible();
  await expect(box.locator('label', { hasText: 'Kite shield' })).toHaveClass(/locked/);
  await expect(box.locator('label', { hasText: 'Kite shield' })).toHaveAttribute('title', 'Kite shield: Beat the Jarl in Runborg');
  const before = await v.locator('#vk-preview').innerHTML();
  await box.locator('label', { hasText: 'Quarters' }).click();
  await box.locator('input[name="vk-shieldcolor"][value="teal"]').check({ force: true });      // a colour Bob has won
  await box.locator('input[name="vk-shieldpatterncolor"][value="black"]').check({ force: true });
  await box.locator('label', { hasText: 'Iron' }).click();
  expect(await v.locator('#vk-preview').innerHTML()).not.toBe(before);
  await expect(box.locator('label', { hasText: 'Cross' }).locator('svg path[fill="#2a9d8f"]').first()).toBeAttached();   // the small shields take the colour
  await page.screenshot({ path: '../../test-results/viking-shield-box.png', fullPage: true });
  await v.locator('#vk-save').click();
  await expect(v.locator('#vk-msg')).toHaveText('Saved ✓');
  await page.reload();
  await expect(box).toBeVisible();
  for (const [slot, val] of [['shieldpattern', 'quarters'], ['shieldcolor', 'teal'], ['shieldpatterncolor', 'black'], ['shieldrim', 'iron'], ['shield', 'round']]) {
    await expect(box.locator(`input[name="vk-${slot}"][value="${val}"]`)).toBeChecked();
  }
  await v.locator('#vk-left').selectOption('none');                                    // back as it was
  await v.locator('#vk-save').click();
  await expect(v.locator('#vk-msg')).toHaveText('Saved ✓');
});
