// The escape room "The Runesmith's Locks" in real browsers: Bob hosts and plays, Erik plays on a phone, a TV shows the
// room. Codes, weights, orders and hiding places are drawn at random, so the tests read the answers from the third hint
// of each lock, the way a team that is stuck would.
// Traceability: US-18, US-19, K-73, K-76..K-87, K-28, K-29, K-71, NFR-01, NFR-02, AK-62, AK-65, AK-66, AK-68, AK-69.
const { test, expect } = require('@playwright/test');
const { USERS, device } = require('./helpers');

const TV = { width: 1920, height: 1080 }, PHONE = { width: 390, height: 844 };

/** Bob starts the escape room (60 min unless given) with the board in a language and lets a TV and Erik in. */
async function table(browser, baseURL, { lang = 'en', len = '60', tvLang = '', erikLang = '' } = {}) {
  const host = await device(browser, baseURL, USERS.bob);
  await host.page.goto('/mysteries/');
  await host.page.locator('select[name=category]').selectOption('escape:runesmith');
  await host.page.locator('select[name=lengthMin]').selectOption(len);
  await host.page.getByLabel('Language on the board').selectOption(lang);
  await host.page.getByRole('button', { name: 'Start a new game' }).click();
  await expect(host.page).toHaveURL(/play\.html\?code=[A-Z2-9]{8}$/);
  const code = new URL(host.page.url()).searchParams.get('code');

  const tv = await device(browser, baseURL, null, TV);
  await tv.page.goto(`/mysteries/board.html?code=${code}${tvLang ? `&lang=${tvLang}` : ''}`);
  await tv.page.getByLabel('Name of this screen').fill('TV');
  await tv.page.getByRole('button', { name: 'Show the board here' }).click();
  const erik = await device(browser, baseURL, USERS.erik, PHONE);
  if (erikLang) await erik.ctx.addInitScript(l => { try { sessionStorage.setItem('mysteries_lang', l); } catch (e) { /* private */ } }, erikLang);
  await erik.page.goto(`/mysteries/play.html?code=${code}`);

  await expect(host.page.locator('ul.waiting li')).toHaveCount(2, { timeout: 10_000 });
  for (const name of ['TV', 'Erik']) {
    await host.page.locator('ul.waiting li', { hasText: name }).getByRole('button', { name: 'Let in' }).click();
  }
  await expect(tv.page.locator('.my-cover h2')).toBeVisible({ timeout: 10_000 });
  for (const p of [host.page, erik.page]) p.on('dialog', d => d.accept());
  return { code, host: host.page, tv: tv.page, erik: erik.page, close: () => Promise.all([host.ctx.close(), tv.ctx.close(), erik.ctx.close()]) };
}

/** A lock's panel on a phone. */
const panel = (page, lock) => page.locator(`section.esc-lock[data-lock="${lock}"]`);

/** Click something that a poll may redraw under the click, until a condition holds. */
async function until(click, check, timeout = 15_000) {
  await expect(async () => { await click(); await check(); }).toPass({ timeout });
}

/** Pump the bellows: the two phones in turn, ten times each (K-85). */
async function lightTheForge(g) {
  for (let i = 0; i < 20; i++) {
    const p = i % 2 ? g.erik : g.host;
    await panel(p, 'bellows').locator('[data-press]').click();
    if (i < 19) await expect(panel(p, 'bellows').locator('p.small.muted')).toContainText(new RegExp(`\\b${i + 1}\\D+20\\b`), { timeout: 5_000 });
  }
  await expect(g.tv.locator('.esc-room')).toBeVisible({ timeout: 10_000 });
}

/** Show a tab of the phone: room, frags, items or story. */
async function tab(page, name) {
  const b = page.locator(`[data-tab="${name}"]`);
  if (await b.getAttribute('aria-pressed') === 'true') return;
  await until(() => b.click(), () => expect(b).toHaveAttribute('aria-pressed', 'true', { timeout: 1_000 }));
}

/** Take the next hints of a lock until the third: the text of the third (K-82). */
async function thirdHint(page, lock) {
  await tab(page, 'room');
  const box = panel(page, lock), hints = box.locator('.esc-hints li');
  for (let n = await hints.count(); n < 3; n = await hints.count()) {
    await until(async () => { if (await hints.count() === n) await box.getByRole('button', { name: `Hint ${n + 1}` }).click({ timeout: 2_000 }); },
      async () => { if (await box.count()) await expect(hints).toHaveCount(n + 1, { timeout: 2_000 }); });
    if (!await box.count()) return '';                            // the last hint opened the lock (the chart)
  }
  return (await hints.nth(2).innerText()).replace(/^\s*3\.\s*/, '').trim();
}
const listOf = (text, lead) => text.slice(text.indexOf(lead) + lead.length).replace(/\.$/, '').split(/, |; /).map(s => s.trim());

/** Open a lock with the answer in its third hint, from one phone. */
const solve = {
  async tools(p) {                                                // search every hiding place, then hang each tool on its hook
    const pairs = listOf(await thirdHint(p, 'tools'), 'Every hiding place: ').map(s => s.split(': '));
    for (const [, spot] of pairs) {
      const b = panel(p, 'tools').getByRole('button', { name: spot, exact: true });
      await until(() => b.click(), () => expect(panel(p, 'tools').getByRole('button', { name: `${spot} ✓` })).toBeVisible({ timeout: 2_000 }));
    }
    const ORDER = ['hammer', 'tongs', 'file', 'chisel', 'punch', 'shears'];   // the hooks, as in escape.json
    for (let left = await panel(p, 'tools').locator('[data-hang]').count(); left; left = await panel(p, 'tools').locator('[data-hang]').count()) {
      const tool = await panel(p, 'tools').locator('[data-hang]').first().getAttribute('data-hang');
      await panel(p, 'tools').locator(`[data-hang="${tool}"][data-hook="${ORDER.indexOf(tool)}"]`).click();
      await expect(panel(p, 'tools').locator(`[data-hang="${tool}"]`)).toHaveCount(0, { timeout: 5_000 });
    }
  },
  async runes(p) {
    const code = (await thirdHint(p, 'runes')).match(/\d{4}/)[0];
    await panel(p, 'runes').getByLabel('The code, 4 digits').fill(code);
    await panel(p, 'runes').getByRole('button', { name: 'Try the code' }).click();
  },
  async scale(p) {
    for (const t of listOf(await thirdHint(p, 'scale'), 'Put these on the scale: ')) {
      await panel(p, 'scale').getByLabel(t, { exact: false }).check();
    }
    await panel(p, 'scale').getByRole('button', { name: 'Weigh' }).click();
  },
  async shields(p) {
    for (const t of listOf(await thirdHint(p, 'shields'), 'The order from the left: ')) {
      await panel(p, 'shields').locator('.esc-choose button', { hasText: new RegExp(`^\\s*${t}\\s*$`, 'i') }).click();
    }
    await panel(p, 'shields').getByRole('button', { name: 'Hang the shields' }).click();
  },
  async chest(p) { await panel(p, 'chest').getByRole('button', { name: /^Open/ }).click(); },
  async chart(p) { await thirdHint(p, 'chart'); },                // the third hint lays the last pieces
  async knots(p) {
    const signs = listOf((await thirdHint(p, 'knots')).replace(/, in that order\.?$/, ''), 'Pull the ropes ');
    for (const s of signs) await panel(p, 'knots').locator('button.float', { hasText: s }).click();
    await panel(p, 'knots').getByRole('button', { name: 'Pull', exact: true }).click();
  },
};

test.describe('Escape room', () => {
  test.describe.configure({ timeout: 120_000 });                 // three devices at once, and a whole room to play

  /** TC-E2E-019 (K-77, K-75): the escape room offers only its own lengths */
  test('the lengths of an escape room', async ({ browser, baseURL }) => {
    const host = await device(browser, baseURL, USERS.bob);
    await host.page.goto('/mysteries/');
    await host.page.locator('select[name=category]').selectOption('escape:runesmith');
    await expect(host.page.locator('select[name=lengthMin] option:not([disabled])')).toHaveText(['30 min', '60 min']);
    await host.ctx.close();
  });

  /** TC-E2E-020 (K-86, K-85, AK-65): the host picks the rules; the bellows lights only when two pump in turn */
  test('the rules in the lobby and the bellows', async ({ browser, baseURL }) => {
    const g = await table(browser, baseURL);
    await expect(g.tv.locator('.my-cover .my-rules')).toContainText('Every lock has three hints');
    await expect(g.erik.locator('[data-rule]')).toHaveCount(0);                       // only the host picks
    await g.host.getByRole('button', { name: 'No hints' }).click();
    await expect(g.tv.locator('.my-cover .my-rules')).toContainText('No hints', { timeout: 5_000 });
    await expect(g.erik.locator('.my-rules')).toContainText('No hints', { timeout: 5_000 });
    await g.host.getByRole('button', { name: 'Three hints per lock' }).click();
    await g.host.getByRole('button', { name: 'Start the game' }).click();

    await expect(g.tv.locator('.esc-dark')).toBeVisible({ timeout: 10_000 });          // dark until the forge is lit
    await expect(g.tv.locator('.cover-text')).toContainText('Autumn, in the year 970');
    const pump = panel(g.host, 'bellows').getByRole('button', { name: 'Pump' });
    for (let i = 0; i < 5; i++) await pump.click();                                    // one player alone: only flicker
    await expect(panel(g.host, 'bellows')).toContainText('1 of 20', { timeout: 5_000 });
    await expect(panel(g.erik, 'bellows')).toContainText('1 of 20', { timeout: 5_000 });
    await expect(g.tv.locator('.esc-fire')).toHaveCount(0);
    await lightTheForge(g);                                                            // the two in turn: it catches
    await expect(g.tv.locator('.esc-fire .fire-t')).toContainText('left before the fire goes out');
    await expect(g.tv.locator('.my-log li').first()).toContainText('lit the forge');
    await expect(panel(g.erik, 'tools')).toBeVisible();
    await expect(panel(g.erik, 'runes')).toBeVisible();
    await g.close();
  });

  /** TC-E2E-021 (K-79, K-81, K-82, K-80, AK-62): fragments on the own phone, a wrong code, hints on every screen, giving an item */
  test('fragments, a wrong code, hints and giving', async ({ browser, baseURL }) => {
    const g = await table(browser, baseURL);
    await g.host.getByRole('button', { name: 'Start the game' }).click();
    await lightTheForge(g);

    await tab(g.erik, 'frags');
    await expect(g.erik.locator('.esc-frag').first()).toBeVisible();
    const erikRunes = await g.erik.locator('.esc-signs b.rune').allInnerTexts();
    expect(erikRunes.length).toBe(8);                                                  // half the rune row each (AK-61)
    await expect(g.erik.locator('.esc-signs b.rune').first()).toHaveCSS('font-family', /Noto Sans Runic/);   // K-76

    await panel(g.host, 'runes').getByLabel('The code, 4 digits').fill('0000');
    await panel(g.host, 'runes').getByRole('button', { name: 'Try the code' }).click();
    const code = (await thirdHint(g.host, 'runes')).match(/\d{4}/)[0];
    if (code === '0000') test.skip(true, 'the drawn code was 0000');
    await expect(g.tv.locator('.my-log')).toContainText('wrong answer', { timeout: 5_000 });      // AK-62: the door held
    await expect(g.tv.locator('.my-side')).toContainText('Wrong answers: 1');
    await expect(g.tv.locator('.esc-lock', { hasText: 'The rune lock' }).locator('.esc-hints li')).toHaveCount(3, { timeout: 5_000 });   // K-82
    await panel(g.host, 'runes').getByLabel('The code, 4 digits').fill(code);
    await panel(g.host, 'runes').getByRole('button', { name: 'Try the code' }).click();
    await expect(g.host.locator('#msg')).toContainText('Wait a few seconds');           // AK-62: one answer per 5 s
    await g.host.waitForTimeout(5_000);
    await panel(g.host, 'runes').getByRole('button', { name: 'Try the code' }).click();
    await expect(g.tv.locator('.my-log li').first()).toContainText('opened: The rune lock', { timeout: 5_000 });   // AK-62, NFR-02
    await expect(g.tv.locator('.esc-room figcaption')).toHaveText('The storeroom');

    await solve.tools(g.erik);                                                         // the iron key goes to Erik
    await tab(g.erik, 'items');
    await expect(g.erik.locator('.esc-items')).toContainText('Iron key');
    await g.erik.locator('form.esc-give').getByRole('button', { name: 'Give' }).click();   // K-80
    await expect(g.tv.locator('.my-log li').first()).toContainText('Erik gave Bob: Iron key', { timeout: 5_000 });
    await expect(g.erik.locator('.esc-items')).toHaveCount(0, { timeout: 5_000 });
    await g.close();
  });

  /** TC-E2E-022 (AK-69, AK-66, K-83, K-80, K-81): the whole 60-minute room with the third hints: out with ★★ on every screen */
  test('play the whole room', async ({ browser, baseURL }) => {
    const g = await table(browser, baseURL);
    await g.host.getByRole('button', { name: 'Start the game' }).click();
    await lightTheForge(g);
    for (const lock of ['tools', 'runes', 'scale', 'shields', 'chest', 'chart', 'knots']) {   // Bob gets every key
      await expect(panel(g.host, lock)).toBeVisible({ timeout: 10_000 });
      await solve[lock](g.host);
      await expect(panel(g.host, lock)).toHaveCount(0, { timeout: 10_000 });
    }
    for (const p of [g.tv, g.host, g.erik]) {
      await expect(p.locator('.esc-end')).toContainText('You are out!', { timeout: 10_000 });
      await expect(p.locator('.esc-end .stars')).toHaveText('★★☆');                    // AK-66: within the time, many hints
    }
    await expect(g.tv.locator('.esc-end')).toContainText('The sword is called Dawn');
    await expect(g.tv.locator('.esc-end')).toContainText('Hints: 18');
    await g.close();
  });

  /** TC-E2E-023 (K-87, K-71, AK-68): the TV in the host's language or the address's, a phone in its player's */
  test('languages of the room', async ({ browser, baseURL }) => {
    const g = await table(browser, baseURL, { lang: 'sv', erikLang: 'da' });
    await expect(g.tv.locator('.my-cover h2')).toHaveText('Runsmedens lås');
    await expect(g.erik.locator('.my-cover h2')).toHaveText('Runesmedens låse', { timeout: 10_000 });
    await g.host.getByRole('button', { name: 'Start the game' }).click();
    await lightTheForge(g);
    await expect(g.tv.locator('.esc-room figcaption')).toHaveText('Smedjan', { timeout: 10_000 });
    await expect(panel(g.erik, 'runes').locator('h3')).toContainText('Runelåsen');
    const nl = await device(browser, baseURL, null, TV);
    await nl.page.goto(`/mysteries/board.html?code=${g.code}&lang=NL`);
    await nl.page.getByLabel('Naam van dit scherm').fill('TV2');
    await nl.page.getByRole('button', { name: 'Toon het speelbord hier' }).click();
    await g.host.locator('ul.waiting li', { hasText: 'TV2' }).getByRole('button', { name: 'Let in' }).click();
    await expect(nl.page.locator('.esc-room figcaption')).toHaveText('De smidse', { timeout: 10_000 });
    await nl.ctx.close();
    await g.close();
  });
});
