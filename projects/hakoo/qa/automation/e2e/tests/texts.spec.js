// The translation page (texts.html), the language menu and the footer link.
// Traceability: US-11, FR-TEXTS-01..10, FR-I18N-06..09, REG-GDPR-25.
// The database is shared by all tests and browsers. Each browser project changes its own text,
// and members who change data are made with freshUserId(); Alice (admin) is the only shared user.
const { test, expect } = require('@playwright/test');
const { USERS, loginAs, freshUserId } = require('./helpers');

// One text per browser project, all without a Danish and Dutch translation in the seed.
const KEYS = { chromium: 'community.b_wf', firefox: 'community.b_wf_b', mobile: 'community.b_wf_tr' };
const keyFor = testInfo => KEYS[testInfo.project.name] || KEYS.chromium;
const cell = (page, key, lang) => page.locator(`td[data-key="${key}"][data-lang="${lang}"]`);

/** Call texts-admin.php as Alice (admin) without a browser page. */
async function asAdmin(browser, baseURL, action, body) {
  const ctx = await browser.newContext({ baseURL });
  await loginAs(ctx, baseURL, USERS.alice, 'Alice');
  const res = await ctx.request.post(`/texts-admin.php?action=${action}`, { data: body });
  const json = await res.json();
  await ctx.close();
  return json;
}

test.beforeEach(async ({ page, context }) => {
  await context.clearCookies();
  await page.goto('/index.html?choose=1');
  await page.evaluate(() => localStorage.clear());
});

test('TC-E2E-138: logged out, the translation page shows a login card and no texts', async ({ page }) => {
  const res = page.waitForResponse(r => r.url().includes('texts-admin.php?action=state'));
  await page.goto('/texts.html');
  expect((await res).status()).toBe(401);
  await expect(page.locator('#login-card')).toBeVisible();
  await expect(page.locator('#login-card a.btn')).toHaveAttribute('href', /discord-auth\.php\?action=start&return=texts\.html$/);
  await expect(page.locator('#app')).toBeHidden();
  await expect(page.locator('#tbody tr')).toHaveCount(0);
});

test('TC-E2E-139: a member without the role reads the texts, with the flags on top, but cannot change them', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, freshUserId(), 'Reader');
  await page.setViewportSize({ width: 1300, height: 900 });
  await page.goto('/texts.html');
  await expect(page.locator('#thead th img')).toHaveCount(5);
  expect(await page.locator('#thead th img').evaluateAll(els => els.map(e => e.getAttribute('src'))))
    .toEqual(['gb', 'se', 'dk', 'nl', 'de'].map(f => `iconer/flags/${f}.png`));
  await expect(cell(page, 'roster.h_me', 'sv')).toHaveText('✏ Dina in-game-namn');
  await expect(page.locator('#role-card')).toContainText('Ask for the language role');
  await expect(page.locator('td.can')).toHaveCount(0);
  await cell(page, 'roster.h_me', 'sv').click();
  await expect(page.locator('td textarea')).toHaveCount(0);
  await expect(page.locator('#roles-card, #pending-card')).toHaveCount(2);
  await expect(page.locator('#roles-card')).toBeHidden();
  await expect(page.locator('#pending-card')).toBeHidden();
});

test('TC-E2E-140: the filters narrow the table', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, freshUserId(), 'Reader');
  await page.goto('/texts.html');
  await page.selectOption('#f-page', 'roster');
  await expect(page.locator('tr.page-row')).toHaveText(['roster']);
  await page.fill('#f-search', 'in-game-namn');
  await expect(cell(page, 'roster.h_me', 'en')).toBeVisible();
  await expect(cell(page, 'roster.save', 'en')).toHaveCount(0);
  await page.fill('#f-search', 'no such text anywhere');
  await expect(page.locator('#tbody')).toContainText('No texts match.');
});

test('TC-E2E-141: the admin changes a text and the site shows it', async ({ page, context, baseURL, browser }, testInfo) => {
  const key = keyFor(testInfo);
  const text = `Admin ${testInfo.project.name} ${Date.now()}`;
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.setViewportSize({ width: 1300, height: 900 });
  await page.goto('/texts.html');
  try {
    await expect(page.locator('#role-card')).toContainText('You are the admin');
    await cell(page, key, 'da').click();
    await page.locator('td textarea').fill(text);
    await page.locator('td.editing [data-act=commit]').click();
    await expect(cell(page, key, 'da').locator('.tx')).toHaveText(text);
    const site = await (await page.request.get('/texts.php?lang=da&page=community')).json();
    expect(site.texts[key.split('.')[1]]).toBe(text);

    // English cannot be emptied.
    await cell(page, key, 'en').click();
    await page.locator('td textarea').fill('');
    await page.locator('td.editing [data-act=commit]').click();
    await expect(page.locator('td.editing .err')).toHaveText('The English text cannot be empty.');
  } finally {
    await asAdmin(browser, baseURL, 'save', { key, lang: 'da', text: '' });
  }
});

test('TC-E2E-142: a member gets the language role, suggests a translation and the admin approves it', async ({ page, context, baseURL, browser }, testInfo) => {
  const key = keyFor(testInfo);
  const id = freshUserId();
  const name = `Translator ${testInfo.project.name}`;
  const text = `Voorstel ${testInfo.project.name} ${Date.now()}`;
  await loginAs(context, baseURL, id, name);
  await page.setViewportSize({ width: 1300, height: 900 });
  await page.goto('/texts.html');
  try {
    // Ask for the role; until the admin answers nothing can be changed.
    await page.click('[data-act=role_request]');
    await expect(page.locator('#role-card')).toContainText('You have asked for the language role');
    await expect(page.locator('td.can')).toHaveCount(0);

    // The admin sees the request and gives the role.
    const adminCtx = await browser.newContext({ baseURL, viewport: { width: 1300, height: 900 } });
    await loginAs(adminCtx, baseURL, USERS.alice, 'Alice');
    const admin = await adminCtx.newPage();
    await admin.goto('/texts.html');
    await admin.locator(`#roles-card [data-role="${id}"] [data-grant="1"]`).click();
    await expect(admin.locator(`#roles-card [data-role="${id}"] .tag`)).toHaveText('has the role');

    // With the role: English stays locked, a translation can be suggested.
    await page.reload();
    await expect(page.locator('#role-card')).toContainText('You have the language role');
    await expect(cell(page, key, 'en')).not.toHaveClass(/can/);
    await cell(page, key, 'nl').click();
    await page.locator('td textarea').fill(text);
    await page.locator('td.editing [data-act=commit]').click();
    await expect(cell(page, key, 'nl').locator('.sug .st')).toHaveText(text);
    await expect(cell(page, key, 'nl').locator('.tx')).not.toHaveText(text);   // not on the site yet

    // The admin approves it; now it is the text.
    await admin.reload();
    const waiting = admin.locator('#pending-card .row', { hasText: text });
    await expect(waiting).toContainText(`Suggestion by ${name}`);
    await waiting.locator('[data-approve="1"]').click();
    await expect(cell(admin, key, 'nl').locator('.tx')).toHaveText(text);
    await page.reload();
    await expect(cell(page, key, 'nl').locator('.tx')).toHaveText(text);
    await expect(cell(page, key, 'nl').locator('.sug')).toHaveCount(0);
    await adminCtx.close();
  } finally {
    await asAdmin(browser, baseURL, 'save', { key, lang: 'nl', text: '' });
    await asAdmin(browser, baseURL, 'role_set', { discord_id: id, grant: false });
  }
});

test('TC-E2E-143: the language menu shows only the chosen flag and stores nothing until a language is picked', async ({ page }) => {
  await page.goto('/privacy.html');
  const menu = page.locator('.lang-row.lang-menu');
  await expect(menu.locator('.lang-current img')).toHaveAttribute('src', 'iconer/flags/gb.png');
  await expect(menu.locator('.lang-flag:visible')).toHaveCount(0);

  await menu.locator('.lang-current').click();
  await expect(menu.locator('.lang-flag:visible')).toHaveCount(5);
  expect(await page.evaluate(() => Object.keys(localStorage))).toEqual([]);      // opening stores nothing (FR-I18N-06)

  await menu.locator('.lang-flag[data-lang=sv]').click();
  await expect(menu.locator('.lang-flag:visible')).toHaveCount(0);
  await expect(menu.locator('.lang-current img')).toHaveAttribute('src', 'iconer/flags/se.png');
  expect(await page.evaluate(() => localStorage.getItem('wot_lang'))).toBe('sv');

  await menu.locator('.lang-current').click();
  await page.keyboard.press('Escape');
  await expect(menu.locator('.lang-flag:visible')).toHaveCount(0);
});

for (const path of ['/community.html', '/games/warframe.html', '/wot-stats.html', '/texts.html']) {
  test(`TC-E2E-144: ${path} loads its flags from hakoo.eu, not from a flag CDN`, async ({ page, baseURL }) => {
    const requests = [];
    page.on('request', r => requests.push(r.url()));
    await page.goto(path);
    await page.locator('.lang-current').click();           // flags in the closed menu may load lazily
    await expect(page.locator('.lang-flag:visible')).toHaveCount(5);
    await page.waitForLoadState('networkidle');
    expect(requests.filter(u => u.includes('flagcdn'))).toEqual([]);
    // Every flag image comes from this site and really loaded (the browser may serve it from its
    // cache, so the requests alone do not show it).
    const flags = await page.locator('.lang-flag img').evaluateAll(els => els.map(e => ({ src: e.currentSrc, w: e.naturalWidth })));
    expect(flags.length).toBe(5);
    for (const f of flags) {
      expect(f.src.startsWith(new URL(baseURL).origin + '/') && f.src.includes('/iconer/flags/')).toBe(true);
      expect(f.w).toBeGreaterThan(0);
    }
  });
}

test('TC-E2E-145: the footer links to the translation page only when logged in', async ({ page, context, baseURL }) => {
  await page.goto('/hakoo-games.html');
  await page.waitForLoadState('networkidle');
  await expect(page.locator('footer a.texts-link')).toHaveCount(0);

  await loginAs(context, baseURL, freshUserId(), 'Member');
  for (const path of ['/hakoo-games.html', '/games/chess.html']) {
    await page.goto(path);
    const link = page.locator('footer a.texts-link');
    await expect(link).toHaveText('🌐 Translations');
    expect(new URL(await link.evaluate(a => a.href)).pathname).toBe('/texts.html');
  }
});

test('TC-E2E-146: the gear in the language menu opens the settings; a text size is saved only when picked', async ({ page }) => {
  await page.goto('/privacy.html');
  const menu = page.locator('.lang-row.lang-menu');
  await menu.locator('.lang-current').click();
  await expect(menu.locator('.lang-settings')).toBeHidden();
  await menu.locator('.lang-gear').click();
  await expect(menu.locator('.lang-size')).toContainText('Text size');
  await expect(menu.locator('.lang-ts button.on')).toHaveAttribute('data-t', 'normal');
  await expect(menu.locator('.lang-texts')).toBeHidden();                       // logged out: no link to the translations
  expect(await page.evaluate(() => Object.keys(localStorage))).toEqual([]);      // looking stores nothing

  await menu.locator('.lang-ts button[data-t=xl]').click();
  await expect(page.locator('html')).toHaveAttribute('data-textsize', 'xl');
  await expect(menu.locator('.lang-ts button.on')).toHaveAttribute('data-t', 'xl');
  expect(await page.evaluate(() => Number(getComputedStyle(document.body).zoom))).toBeCloseTo(1.22, 2);   // Firefox rounds to 1.21875
  expect(await page.evaluate(() => localStorage.getItem('wf_textsize'))).toBe('xl');

  // The size follows the visitor to the next page, and can be set back.
  await page.goto('/terms.html');
  await expect(page.locator('html')).toHaveAttribute('data-textsize', 'xl');
  await page.locator('.lang-current').click();
  await page.locator('.lang-gear').click();
  await page.locator('.lang-ts button[data-t=normal]').click();
  expect(await page.evaluate(() => getComputedStyle(document.body).zoom)).toBe('1');
});

test('TC-E2E-147: logged in, the settings in the language menu link to the translation page', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, freshUserId(), 'Member');
  for (const path of ['/community.html', '/games/warframe.html']) {
    await page.goto(path);
    await page.locator('.lang-current').click();
    await page.locator('.lang-gear').click();
    const link = page.locator('.lang-pop a.lang-texts');
    await expect(link).toBeVisible();
    await expect(link).toContainText('Translations');
    expect(new URL(await link.evaluate(a => a.href)).pathname).toBe('/texts.html');
  }
});

test('TC-E2E-148: the Warframe Builder keeps its own text sizes, set from the language menu', async ({ page }) => {
  await page.goto('/games/warframe-builder.html');
  await expect(page.locator('nav .ts-ctl')).toHaveCount(0);                     // moved into the menu
  await page.locator('.lang-current').click();
  await page.locator('.lang-gear').click();
  await page.locator('.lang-ts button[data-t=large]').click();
  await expect(page.locator('html')).toHaveAttribute('data-textsize', 'large');
  await expect(page.locator('.ts-ctl button.on').first()).toHaveAttribute('data-t', 'large');   // the builder's own control agrees
  expect(await page.evaluate(() => getComputedStyle(document.body).zoom)).toBe('1');           // its own rules, not the page zoom
});
