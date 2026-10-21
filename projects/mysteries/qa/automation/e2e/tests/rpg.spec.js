// The role-playing game "The Stolen Hammer" in real browsers: Bob hosts and plays two heroes, Erik plays two on a phone,
// a TV shows the scene and the computer is the game master. The rolls and the enemies are random, so the whole
// adventure is played by a small bot that always takes the first thing a hero may do (attack first in a fight).
// Traceability: US-20, US-21, US-22, US-23, K-90, K-93, K-96..K-100, K-108, K-71, NFR-01, AK-71, AK-82, AK-83.
const { test, expect } = require('@playwright/test');
const { USERS, device } = require('./helpers');

const TV = { width: 1920, height: 1080 }, PHONE = { width: 390, height: 844 };

/** Bob starts the adventure (60 min) with the board in a language and lets a TV and Erik in. */
async function table(browser, baseURL, { lang = 'en', erikLang = '' } = {}) {
  const host = await device(browser, baseURL, USERS.bob);
  await host.page.goto('/mysteries/');
  await host.page.locator('select[name=category]').selectOption('rpg:stolen-hammer');
  await host.page.locator('select[name=lengthMin]').selectOption('60');
  await host.page.getByLabel('Language on the board').selectOption(lang);
  await host.page.getByRole('button', { name: 'Start a new game' }).click();
  await expect(host.page).toHaveURL(/play\.html\?code=[A-Z2-9]{8}$/, { timeout: 15_000 });
  const code = new URL(host.page.url()).searchParams.get('code');

  const tv = await device(browser, baseURL, null, TV);
  await tv.page.goto(`/mysteries/board.html?code=${code}`);
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
  for (const p of [host.page, erik.page]) await forgetSaved(p);
  return { code, host: host.page, tv: tv.page, erik: erik.page, close: () => Promise.all([host.ctx.close(), tv.ctx.close(), erik.ctx.close()]) };
}

/**
 * Forget heroes saved on the account by earlier runs (at most 12 may be kept, K-92). Another browser project may be
 * using the same accounts at the same time, and a hero in its party cannot be forgotten, so a few may stay.
 */
async function forgetSaved(page) {
  await expect(page.locator('.rpg-new')).toBeVisible({ timeout: 10_000 });
  const saved = page.locator('[data-forget]');
  await expect(async () => {
    if (await saved.count() > 4) await saved.first().click({ timeout: 1_000 });
    expect(await saved.count()).toBeLessThanOrEqual(4);
  }).toPass({ timeout: 30_000 });
}

/** Make a hero in the lobby: a class, a folk (its +1 on the first free attribute if it may choose), a name. */
async function hero(page, cls, folk, name) {
  await page.locator(`input[name=cls][value=${cls}]`).check();
  await page.locator(`input[name=folk][value=${folk}]`).check();
  const attr = page.locator('select[name=attr]');
  if (await attr.count()) await attr.selectOption({ index: 1 });
  await page.locator('.rpg-new input[name=name]').fill(name);
  await page.locator('[data-make]').click();
  await expect(page.locator('.rpg-phone .rpg-heroes').first().locator('li', { hasText: name })).toHaveCount(1, { timeout: 5_000 });
}

/** Bob: a farmer and a viking; Erik: a völva and a skald. */
async function party(g) {
  await hero(g.host, 'farmer', 'dwarf', 'Ragnar');
  await hero(g.erik, 'volva', 'lightelf', 'Groa');
  await hero(g.host, 'viking', 'norse', 'Sigrid');
  await hero(g.erik, 'skald', 'giant', 'Brage');
}

/**
 * One move of a phone, in the page: a host's tie first, then for each of the player's heroes the first thing it may do.
 * A target is picked before another tab is opened (opening a tab forgets the aim). What was clicked, or ''.
 */
const bot = page => page.evaluate(() => {
  const free = s => [...document.querySelectorAll(s)].filter(b => !b.disabled && b.offsetParent !== null);
  const click = s => { const b = free(s)[0]; if (!b) return ''; b.click(); return s; };
  const first = click('[data-target]') || click('[data-decide]');
  if (first) return first;
  const tabs = document.querySelectorAll('.my-tabs [data-tab]');
  for (let k = 0; k < tabs.length - 1; k++) {
    const t = document.querySelector(`.my-tabs [data-tab="${k}"]`);
    if (t && t.getAttribute('aria-pressed') !== 'true') t.click();
    const voted = document.querySelector('[data-vote][aria-pressed="true"]');
    for (const s of ['[data-react="1"]', '[data-target]', '[data-do="attack"]', '[data-do="help"]', '[data-do]', '[data-roll]',
                     voted ? '' : '[data-vote]', '[data-answer]', '[data-riddle]', '[data-excuse]', '[data-spot]']) {
      if (s && click(s)) return `${s} ${k}`;
    }
  }
  return '';
});

test.describe('Role-playing game', () => {
  test.describe.configure({ timeout: 120_000 });                 // three devices at once

  /** TC-E2E-024 (K-96, K-90, K-93, AK-71): the adventure's lengths; four heroes of different classes in the lobby, then the first scene */
  test('the lobby and the start', async ({ browser, baseURL }) => {
    const g = await table(browser, baseURL);
    await expect(g.tv.locator('.my-cover h2')).toHaveText('The Stolen Hammer');
    await hero(g.host, 'farmer', 'dwarf', 'Ragnar');
    await hero(g.erik, 'farmer', 'dwarf', 'Torgrim');
    await hero(g.erik, 'volva', 'lightelf', 'Groa');
    await expect(g.erik.locator('.rpg-new')).toHaveCount(0);                         // two heroes: no third (AK-71)
    await expect(g.erik.locator('.rpg-phone')).toContainText('You have two heroes');
    await hero(g.host, 'viking', 'norse', 'Sigrid');
    await expect(g.tv.locator('.rpg-problems')).toContainText('Farmer', { timeout: 5_000 });   // two farmers among the four
    await g.erik.locator('.rpg-heroes li', { hasText: 'Torgrim' }).locator('[data-drop]').click();
    await hero(g.erik, 'skald', 'giant', 'Brage');
    await expect(g.tv.locator('.my-cover')).toContainText('The party is ready', { timeout: 5_000 });
    await expect(g.tv.locator('.my-cover .rpg-heroes li')).toHaveCount(4);
    await g.host.getByRole('button', { name: 'Start the game' }).click();
    await expect(g.tv.locator('.esc-room figcaption')).toHaveText('Thunder Bay', { timeout: 10_000 });
    await expect(g.tv.locator('.rpg-prompt')).toHaveText('Over the mountains or through the forest?');
    await expect(g.tv.locator('.my-side .rpg-heroes li')).toHaveCount(4);
    await expect(g.erik.locator('.my-tabs [data-tab]')).toHaveText([/Groa/, /Brage/, 'The story']);
    await expect(g.erik.locator('.rpg-sheet')).toContainText('Your task');               // their own task, only on their phone
    await expect(g.tv.locator('.rpg-sheet')).toHaveCount(0);
    await g.close();
  });

  /** TC-E2E-025 (AK-83, NFR-01, K-97, K-98, K-100, K-92): the whole 60-minute adventure with the computer as game master, to the end */
  test('play the whole adventure', async ({ browser, baseURL }) => {
    test.setTimeout(20 * 60_000);
    const g = await table(browser, baseURL);
    await party(g);
    await g.host.getByRole('button', { name: 'Start the game' }).click();
    await expect(g.tv.locator('.esc-room figcaption')).toHaveText('Thunder Bay', { timeout: 10_000 });
    const scenes = new Set();
    const end = g.tv.locator('.rpg-stars');
    for (let idle = 0; !await end.count(); ) {
      scenes.add(await g.tv.locator('.esc-room figcaption').innerText().catch(() => ''));
      const moved = (await bot(g.host).catch(() => '')) || (await bot(g.erik).catch(() => ''));
      idle = moved ? 0 : idle + 1;
      expect(idle, 'nobody could do anything for a minute').toBeLessThan(150);
      await g.host.waitForTimeout(moved ? 250 : 400);
    }
    expect([...scenes]).toEqual(expect.arrayContaining(['Thunder Bay', 'Thrym\'s Cellar']));
    for (const p of [g.tv, g.host, g.erik]) {
      await expect(p.locator('.rpg-gifts li')).toHaveCount(4, { timeout: 10_000 });
      await expect(p.locator('.rpg-stars li.got').first()).toBeVisible();
    }
    await expect(g.tv.locator('.my-cover')).toContainText('now level 2');
    await g.close();
  });

  /** TC-E2E-026 (K-108, K-71, AK-82): the TV in the host's language or the address's, a phone in its player's */
  test('languages of the adventure', async ({ browser, baseURL }) => {
    const g = await table(browser, baseURL, { lang: 'sv', erikLang: 'da' });
    await expect(g.tv.locator('.my-cover h2')).toHaveText('Den stulna hammaren');
    await expect(g.erik.locator('.my-cover h2')).toHaveText('Den stjålne hammer', { timeout: 10_000 });
    await party(g);
    await g.host.getByRole('button', { name: 'Start the game' }).click();
    await expect(g.tv.locator('.esc-room figcaption')).toHaveText('Åskvik', { timeout: 10_000 });
    await expect(g.erik.locator('.my-head').first()).toHaveText('Tordenvig');
    const nl = await device(browser, baseURL, null, TV);
    await nl.page.goto(`/mysteries/board.html?code=${g.code}&lang=NL`);
    await nl.page.getByLabel('Naam van dit scherm').fill('TV2');
    await nl.page.getByRole('button', { name: 'Toon het speelbord hier' }).click();
    await g.host.locator('ul.waiting li', { hasText: 'TV2' }).getByRole('button', { name: 'Let in' }).click();
    await expect(nl.page.locator('.esc-room figcaption')).toHaveText('Donderbaai', { timeout: 10_000 });
    await expect(nl.page.locator('.rpg-prompt')).toHaveText('Over de bergen of door het bos?');
    await nl.ctx.close();
    await g.close();
  });
});
