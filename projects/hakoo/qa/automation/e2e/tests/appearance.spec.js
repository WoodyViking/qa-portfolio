// The site's appearance (js/shared/appearance.js, the 🎨 item in the language menu's ⚙, profile → Appearance,
// appearance.php). Nothing is stored until Save; a member's look follows them to another browser.
// Traceability: FR-SITE-01..07, US-31, US-32.
const { test, expect } = require('@playwright/test');
const { loginAs, freshUserId, openNav } = require('./helpers');

const SHOTS = '../../test-results/appearance';
const bodyBg = page => page.evaluate(() => getComputedStyle(document.body).backgroundColor);
const stored = page => page.evaluate(() => localStorage.getItem('hakoo_look'));

/** Open the language menu, then ⚙, then 🎨 Appearance. */
async function openLook(page) {
  await openNav(page, 'corner');                                  // HAKOO Games on a phone: the language is in 👤▾
  await page.locator('.lang-current').first().click();
  await page.locator('.lang-gear').first().click();
  await page.locator('.lang-look').first().click();
  await expect(page.locator('#hl-dialog')).toBeVisible();
}
const dlg = page => page.locator('#hl-dialog');

test('TC-E2E-168: a theme shows at once but is stored only on Save; then every page has it, and Reset brings the default back', async ({ page }) => {
  await page.goto('/community.html');
  const dark = await bodyBg(page);
  await openLook(page);
  await dlg(page).locator('label.hl-chip', { hasText: 'Light' }).click();
  await expect.poll(() => bodyBg(page)).toBe('rgb(246, 248, 250)');            // shown at once
  expect(await stored(page)).toBeNull();                                         // not stored yet
  await dlg(page).locator('.hl-close').click();                                  // closed without Save: back
  await expect.poll(() => bodyBg(page)).toBe(dark);

  await openLook(page);
  await dlg(page).locator('label.hl-chip', { hasText: 'Light' }).click();
  await dlg(page).locator('label.hl-chip', { hasText: 'Dots' }).click();
  await dlg(page).locator('.hl-save').click();
  await expect(dlg(page).locator('.hl-msg')).toHaveText('Saved in this browser ✓');
  expect(JSON.parse(await stored(page))).toEqual({ theme: 'light', bg: null, gradient: null, text: null, accent: null, box: null,
    pattern: 'dots', effect: 'none' });
  for (const p of ['/profile.html', '/hakoo-games.html', '/games/chess.html', '/privacy.html', '/roster.html']) {
    await page.goto(p);
    expect(await bodyBg(page), p).toBe('rgb(246, 248, 250)');
    expect(await page.evaluate(() => getComputedStyle(document.body).backgroundImage), p).toContain('radial-gradient');   // the dots
  }
  await page.goto('/community.html');
  await openLook(page);
  await dlg(page).locator('.hl-reset').click();
  await dlg(page).locator('.hl-save').click();
  await expect.poll(() => bodyBg(page)).toBe(dark);
  expect(await stored(page)).toBeNull();                                         // the default needs no storage
});

test('TC-E2E-169: own colours: text that is hard to read gives a warning (it shows, Save still works); readable colours save at once', async ({ page }) => {
  await page.goto('/hakoo-games.html');
  const dark = await bodyBg(page);
  await openLook(page);
  const textInput = dlg(page).locator('input[type=color][data-k="text"]');
  await textInput.fill('#22272e');                                               // dark grey on the dark background
  await expect(dlg(page).locator('.hl-msg')).toContainText('hard to read');
  await expect(dlg(page).locator('.hl-msg')).toContainText('you will be asked to confirm');
  await expect(dlg(page).locator('.hl-save')).toBeEnabled();
  expect(await page.evaluate(() => getComputedStyle(document.body).color)).toBe('rgb(34, 39, 46)');   // shown as it is
  await textInput.fill('#ffe9a8');
  await dlg(page).locator('label.hl-chip', { hasText: 'One colour' }).click();
  await dlg(page).locator('input[type=color][data-k="bg"]').fill('#10233a');
  await expect(dlg(page).locator('.hl-msg')).not.toHaveClass(/bad/);
  await dlg(page).locator('.hl-save').click();
  expect(await bodyBg(page)).toBe('rgb(16, 35, 58)');
  expect(await page.evaluate(() => getComputedStyle(document.body).color)).toBe('rgb(255, 233, 168)');
  expect(JSON.parse(await stored(page))).toMatchObject({ bg: '#10233a', text: '#ffe9a8' });
  expect(dark).not.toBe(await bodyBg(page));
});

test('TC-E2E-170: a member\'s look saved on the profile follows them to another browser; Reset there removes it', async ({ browser, baseURL }, info) => {
  const id = freshUserId();
  const a = await browser.newContext();
  await loginAs(a, baseURL, id, 'Painter');
  const pa = await a.newPage();
  await pa.goto('/profile.html#appearance');
  const ctl = pa.locator('#look-controls');
  await ctl.locator('label.hl-chip', { hasText: 'HAKOO' }).click();
  await ctl.locator('label.hl-chip', { hasText: 'Grid' }).click();
  await ctl.locator('.hl-save').click();
  await expect(ctl.locator('.hl-msg')).toHaveText('Saved ✓ (in this browser and on your account)');
  if (info.project.name === 'chromium') await pa.screenshot({ path: `${SHOTS}-profile-hakoo.png` });

  const b = await browser.newContext();                                          // another device: nothing stored yet
  await loginAs(b, baseURL, id, 'Painter');
  const pb = await b.newPage();
  await pb.goto('/roster.html');
  await expect.poll(() => bodyBg(pb)).toBe('rgb(40, 0, 78)');
  expect(JSON.parse(await stored(pb))).toMatchObject({ theme: 'hakoo', pattern: 'grid' });

  await pb.goto('/profile.html#appearance');
  await pb.locator('#look-controls .hl-reset').click();
  await pb.locator('#look-controls .hl-save').click();
  await expect(pb.locator('#look-controls .hl-msg')).toHaveText('Saved ✓ (in this browser and on your account)');
  expect(await stored(pb)).toBeNull();
  const j = await pb.evaluate(async () => (await fetch('appearance.php?action=me')).json());
  expect(j.look).toBeNull();
  await a.close();
  await b.close();
});

test('TC-E2E-171: a first visit stores nothing and looks as before (no inline colours)', async ({ page, context }) => {
  await page.goto('/index.html?choose=1');
  expect(await page.evaluate(() => [localStorage.length, document.documentElement.getAttribute('style'), document.documentElement.dataset.theme || null]))
    .toEqual([0, null, null]);
  expect(await context.cookies()).toEqual([]);
});

test('TC-E2E-173: Close and Escape go back to the saved look at once, without waiting for the dialog\'s close event (DEF-014)', async ({ page }) => {
  await page.goto('/roster.html');
  const dark = await bodyBg(page);
  for (const how of ['button', 'escape']) {
    await openLook(page);
    await dlg(page).locator('label.hl-chip', { hasText: 'Light' }).click();
    await expect.poll(() => bodyBg(page)).toBe('rgb(246, 248, 250)');
    // The colours are read in the same task as the click or the key: an event that comes later cannot help
    const after = await page.evaluate(h => {
      const d = document.getElementById('hl-dialog');
      if (h === 'button') d.querySelector('.hl-close').click();
      else d.dispatchEvent(new Event('cancel', { cancelable: true }));
      return getComputedStyle(document.body).backgroundColor;
    }, how);
    expect(after, how).toBe(dark);
    await page.evaluate(() => { const d = document.getElementById('hl-dialog'); if (d.open) d.close(); });
  }
  expect(await stored(page)).toBeNull();
});

test('TC-E2E-174: a background of several colours: a straight line or a circle, each colour where it should be on the way; text must be readable on every colour', async ({ page }, info) => {
  await page.goto('/community.html');
  const bannerBefore = await page.locator('.hero').evaluate(el => getComputedStyle(el).backgroundImage);
  expect(bannerBefore).toContain('rgb(89, 0, 176)');                              // the HAKOO banner as it was
  await openLook(page);
  await dlg(page).locator('label.hl-chip', { hasText: 'Several colours' }).click();
  await expect(dlg(page).locator('.hl-stops [data-stop]')).toHaveCount(2);
  await dlg(page).locator('[data-stop-c="0"]').fill('#0b2a4a');
  await dlg(page).locator('[data-stop-c="1"]').fill('#3a0a5e');
  await dlg(page).locator('.hl-add').click();                                     // a third colour, then a fourth: no more
  await dlg(page).locator('.hl-add').click();
  await expect(dlg(page).locator('.hl-stops [data-stop]')).toHaveCount(4);
  await expect(dlg(page).locator('.hl-add')).toBeHidden();
  await dlg(page).locator('[data-stop-x="3"]').click();
  await dlg(page).locator('[data-stop-c="2"]').fill('#14532d');
  await dlg(page).locator('[data-stop-p="1"]').fill('40');
  await dlg(page).locator('#' + await dlg(page).locator('.hl-angle input').getAttribute('id')).fill('90');
  await expect(dlg(page).locator('.hl-msg')).not.toHaveClass(/bad/);
  const img = await page.evaluate(() => getComputedStyle(document.body).backgroundImage);
  expect(img).toContain('linear-gradient(90deg');
  expect(img).toContain('rgb(11, 42, 74) 0%');
  expect(img).toContain('rgb(58, 10, 94) 40%');
  // A light colour on the way makes the light text unreadable there: a warning, and it shows
  await dlg(page).locator('[data-stop-c="2"]').fill('#f5f5dc');
  await expect(dlg(page).locator('.hl-msg')).toContainText('on one of the background colours');
  expect(await page.evaluate(() => getComputedStyle(document.body).backgroundImage)).toContain('rgb(245, 245, 220)');
  await dlg(page).locator('[data-stop-c="2"]').fill('#14532d');
  // A circle from the top
  await dlg(page).locator('label.hl-chip', { hasText: 'Circle' }).click();
  await dlg(page).locator('.hl-at select').selectOption('top');
  expect(await page.evaluate(() => getComputedStyle(document.body).backgroundImage)).toMatch(/radial-gradient\(circle at (center top|50% 0%)/);   // Chromium / Firefox
  if (info.project.name === 'chromium') await dlg(page).screenshot({ path: `${SHOTS}-gradient-dialog.png` });
  await dlg(page).locator('.hl-save').click();
  expect(JSON.parse(await stored(page)).gradient).toEqual({ type: 'radial', at: 'top',
    stops: [{ c: '#0b2a4a', p: 0 }, { c: '#3a0a5e', p: 40 }, { c: '#14532d', p: 100 }] });
  await dlg(page).locator('.hl-close').click();
  // The banner follows the appearance
  expect(await page.locator('.hero').evaluate(el => getComputedStyle(el).backgroundImage)).toContain('radial-gradient');
  if (info.project.name === 'chromium') await page.screenshot({ path: `${SHOTS}-gradient-community.png` });
});

test('TC-E2E-175: the HAKOO theme has the banner\'s purple and yellow: a purple gradient behind the page and yellow headings', async ({ page }, info) => {
  await page.goto('/community.html');
  await openLook(page);
  await dlg(page).locator('label.hl-chip', { hasText: 'HAKOO' }).click();
  await dlg(page).locator('.hl-save').click();
  await dlg(page).locator('.hl-close').click();
  expect(await bodyBg(page)).toBe('rgb(40, 0, 78)');
  expect(await page.evaluate(() => getComputedStyle(document.body).backgroundImage)).toContain('rgb(89, 0, 176)');
  expect(await page.locator('.hero h1').evaluate(el => getComputedStyle(el).color)).toBe('rgb(255, 223, 86)');
  expect(await page.locator('.hero').evaluate(el => getComputedStyle(el).backgroundImage)).toContain('rgb(255, 223, 86)');   // the banner as before
  if (info.project.name === 'chromium') {
    await page.screenshot({ path: `${SHOTS}-hakoo-theme-community.png` });
    await page.goto('/games/othello.html');
    await page.screenshot({ path: `${SHOTS}-hakoo-theme-othello.png` });
  }
});

test('TC-E2E-176: the boxes are made from the background, not from the text colour, and can have a colour of their own', async ({ page }) => {
  await page.goto('/community.html');
  const card = () => page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--card').trim());
  await openLook(page);
  await dlg(page).locator('label.hl-chip', { hasText: 'One colour' }).click();
  await dlg(page).locator('input[type=color][data-k="bg"]').fill('#10233a');
  const auto = await card();
  await dlg(page).locator('input[type=color][data-k="text"]').fill('#00e000');      // green text: the boxes stay as they were
  expect(await card()).toBe(auto);
  const boxes = dlg(page).locator('input[type=color][data-k="box"]');
  await boxes.fill('#3a0a5e');
  expect(await card()).toBe('#3a0a5e');
  await boxes.fill('#f0f0f0');                                                      // green text on light boxes: a warning
  await expect(dlg(page).locator('.hl-msg')).toContainText('on the boxes');
  await boxes.fill('#3a0a5e');
  await dlg(page).locator('.hl-save').click();
  expect(JSON.parse(await stored(page))).toMatchObject({ bg: '#10233a', text: '#00e000', box: '#3a0a5e' });
  await dlg(page).locator('[data-clear="box"]').click();                            // Automatic: back to the background's
  expect(await card()).toBe(auto);
});

test('TC-E2E-177: a moving background is drawn behind the page, never takes a click, and stops when it is turned off', async ({ page }, info) => {
  await page.goto('/community.html');
  await openLook(page);
  for (const fx of ['Stars', 'Network', 'Fireflies']) {
    await dlg(page).locator('label.hl-chip', { hasText: fx }).click();
    const c = page.locator('canvas.hl-fx');
    await expect(c).toBeVisible();
    expect(await c.evaluate(el => [getComputedStyle(el).position, getComputedStyle(el).pointerEvents, getComputedStyle(el).zIndex]))
      .toEqual(['fixed', 'none', '-1']);
  }
  await dlg(page).locator('.hl-save').click();
  expect(JSON.parse(await stored(page)).effect).toBe('fireflies');
  await dlg(page).locator('.hl-close').click();
  await page.goto('/roster.html');                                                  // it follows to the other pages
  await expect(page.locator('canvas.hl-fx')).toBeVisible();
  await page.mouse.move(300, 300);
  if (info.project.name === 'chromium') await page.screenshot({ path: `${SHOTS}-fireflies-roster.png` });
  await openLook(page);
  await dlg(page).locator('label.hl-chip', { hasText: 'None' }).last().click();
  await expect(page.locator('canvas.hl-fx')).toBeHidden();
});

test('TC-E2E-178: with "reduce motion" the moving background never moves by itself, but does move with the mouse', async ({ page }, info) => {
  test.skip(info.project.name === 'mobile', 'no mouse');
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('/index.html?choose=1');
  await page.evaluate(() => localStorage.setItem('hakoo_look', JSON.stringify({ theme: 'dark', effect: 'net' })));
  await page.goto('/roster.html');
  const pixels = () => page.locator('canvas.hl-fx').evaluate(c => c.toDataURL());
  await expect(page.locator('canvas.hl-fx')).toBeVisible();
  await page.waitForTimeout(1000);                                                  // the glass boxes are found and drawn once
  const a = await pixels();
  await page.waitForTimeout(600);
  expect(await pixels()).toBe(a);                                                   // still by itself
  await page.mouse.move(200, 200);
  await page.mouse.move(600, 400, { steps: 15 });
  await page.waitForTimeout(100);
  expect(await pixels()).not.toBe(a);                                               // the mouse moves it
  await page.waitForTimeout(1600);
  const b = await pixels();
  await page.waitForTimeout(500);
  expect(await pixels()).toBe(b);                                                   // and it settles again
});

test('TC-E2E-179: with a moving background the boxes and bars are glass that shows it larger; dialogs stay solid; off, all is solid again', async ({ page }) => {
  await page.goto('/community.html');
  await openLook(page);
  await dlg(page).locator('label.hl-chip', { hasText: 'Stars' }).click();
  await page.waitForTimeout(800);
  const glass = () => page.evaluate(() => [...document.querySelectorAll('[data-hl-lens]')].map(el => getComputedStyle(el).backgroundColor));
  const g = await glass();
  expect(g.length).toBeGreaterThan(3);                                              // nav, buttons, This week ...
  for (const c of g) expect(c).toMatch(/^rgba\(.*0\.78\)$/);
  expect(await page.locator('nav').getAttribute('data-hl-lens')).toBe('bg2');       // the top bar follows the boxes
  expect(await page.locator('#hl-dialog').getAttribute('data-hl-lens')).toBeNull(); // the dialog stays solid
  await dlg(page).locator('label.hl-chip', { hasText: 'None' }).last().click();
  expect(await glass()).toEqual([]);
});

test('TC-E2E-180: saving colours that are hard to read asks in high contrast whether to keep them; "Keep" saves them', async ({ page }, info) => {
  await page.goto('/roster.html');
  await openLook(page);
  await dlg(page).locator('input[type=color][data-k="text"]').fill('#22272e');
  await dlg(page).locator('.hl-save').click();
  const box = page.locator('dialog.hl-confirm');
  await expect(box).toBeVisible();
  await expect(box).toContainText('Keep these colours?');
  await expect(box).toContainText('hard to read');
  // The question is always black, white and yellow, whatever the page looks like
  expect(await box.evaluate(el => [getComputedStyle(el).backgroundColor, getComputedStyle(el).color])).toEqual(['rgb(0, 0, 0)', 'rgb(255, 255, 255)']);
  await expect(box.locator('.hl-back')).toBeFocused();                            // Enter by mistake goes back
  if (info.project.name === 'chromium') await page.screenshot({ path: `${SHOTS}-confirm.png` });
  await box.locator('.hl-keep').click();
  await expect(box).toHaveCount(0);
  await expect(dlg(page).locator('.hl-msg')).toHaveText('Saved in this browser ✓ You chose to keep colours that are hard to read.');
  expect(JSON.parse(await stored(page))).toMatchObject({ text: '#22272e' });
});

test('TC-E2E-181: no answer within 5 seconds, or "Go back", returns to the saved colours and suggests readable ones like those picked', async ({ page }) => {
  await page.goto('/roster.html');
  const before = await page.evaluate(() => getComputedStyle(document.body).color);
  await openLook(page);
  await dlg(page).locator('input[type=color][data-k="text"]').fill('#22272e');
  await dlg(page).locator('.hl-save').click();
  const box = page.locator('dialog.hl-confirm');
  await expect(box).toContainText('Going back in 5 s');
  await expect(box).toHaveCount(0, { timeout: 8000 });                             // the time ran out
  expect(await page.evaluate(() => getComputedStyle(document.body).color)).toBe(before);
  expect(await stored(page)).toBeNull();
  await expect(dlg(page).locator('.hl-msg')).toContainText('A readable version of what you picked');
  await dlg(page).locator('.hl-use').click();                                       // the suggestion: readable, so Save keeps it at once
  await expect(dlg(page).locator('.hl-msg')).not.toHaveClass(/bad/);
  const text = await dlg(page).locator('input[type=color][data-k="text"]').inputValue();
  expect(text).not.toBe('#22272e');
  expect(await page.evaluate(t => HakooLook.problem({ ...HakooLook.saved(), text: t }), text)).toBe('');
  await dlg(page).locator('.hl-save').click();
  await expect(page.locator('dialog.hl-confirm')).toHaveCount(0);
  expect(JSON.parse(await stored(page))).toMatchObject({ text });
  // "Go back" works the same way as the time running out
  await dlg(page).locator('input[type=color][data-k="text"]').fill('#22272e');
  await dlg(page).locator('.hl-save').click();
  await page.locator('dialog.hl-confirm .hl-back').click();
  expect(JSON.parse(await stored(page))).toMatchObject({ text });
});

// Screenshots of a few pages in each theme, for a look by eye (chromium only)
test('TC-E2E-172: the themes on a few pages (screenshots for review)', async ({ page }, info) => {
  test.skip(info.project.name !== 'chromium', 'screenshots once');
  for (const theme of ['light', 'contrast', 'hakoo']) {
    await page.goto('/index.html?choose=1');
    await page.evaluate(t => localStorage.setItem('hakoo_look', JSON.stringify({ theme: t, pattern: t === 'light' ? 'none' : 'dots' })), theme);
    for (const p of ['community.html', 'hakoo-games.html', 'games/othello.html', 'roster.html', 'privacy.html', 'index.html?choose=1']) {
      await page.goto('/' + p);
      await page.waitForTimeout(300);
      await page.screenshot({ path: `${SHOTS}-${theme}-${p.replace(/[/?=.]/g, '_')}.png` });
    }
  }
});
