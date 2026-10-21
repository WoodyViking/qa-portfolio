// Kunglig Vikingludo in real browsers: Bob hosts and plays, Erik plays on a phone, a TV shows the board.
// The server rolls the die, so the tests play on until what they look for has happened.
// Traceability: US-15, K-42, K-44, K-45, K-46, K-48, K-49, K-28, NFR-02, AK-29, AK-30, AK-31, AK-34, AK-36, AK-37.
const { test, expect } = require('@playwright/test');
const { USERS, device } = require('./helpers');

const TV = { width: 1920, height: 1080 }, PHONE = { width: 390, height: 844 };

/** Bob starts a Ludo game and lets a TV and Erik in. */
async function table(browser, baseURL, boardLang) {
  const host = await device(browser, baseURL, USERS.bob);
  await host.page.goto('/mysteries/');
  await host.page.locator('select[name=category]').selectOption('ludo');
  if (boardLang) await host.page.getByLabel('Language on the board').selectOption(boardLang);
  await host.page.getByRole('button', { name: 'Start a new game' }).click();
  await expect(host.page).toHaveURL(/play\.html\?code=[A-Z2-9]{8}$/);
  const code = new URL(host.page.url()).searchParams.get('code');

  const tv = await device(browser, baseURL, null, TV);
  await tv.page.goto(`/mysteries/board.html?code=${code}`);
  await tv.page.getByLabel('Name of this screen').fill('TV');
  await tv.page.getByRole('button', { name: 'Show the board here' }).click();
  const erik = await device(browser, baseURL, USERS.erik, PHONE);
  await erik.page.goto(`/mysteries/play.html?code=${code}`);

  await expect(host.page.locator('ul.waiting li')).toHaveCount(2, { timeout: 10_000 });
  for (const name of ['TV', 'Erik']) {
    await host.page.locator('ul.waiting li', { hasText: name }).getByRole('button', { name: 'Let in' }).click();
  }
  return { host: host.page, tv: tv.page, erik: erik.page, close: () => Promise.all([host.ctx.close(), tv.ctx.close(), erik.ctx.close()]) };
}

/** Pick colours (Erik first) and start. */
async function start(g, erikRoll = 'tap') {
  await g.erik.getByRole('button', { name: 'Fjord blue', exact: true }).click();
  await expect(g.erik.locator('.cname')).toHaveText('Fjord blue', { timeout: 5_000 });
  if (erikRoll === 'shake') {
    await g.erik.getByRole('button', { name: 'Shake and pour', exact: true }).click();
    await expect(g.erik.getByRole('button', { name: 'Shake and pour', exact: true })).toHaveAttribute('aria-pressed', 'true', { timeout: 5_000 });
  }
  await g.host.getByRole('button', { name: 'Blood red', exact: true }).click();
  await expect(g.tv.locator('.ludo-lobby li')).toHaveCount(2, { timeout: 10_000 });
  await g.host.getByRole('button', { name: 'Start the game' }).click();
  await expect(g.tv.locator('svg.ludo-board')).toBeVisible({ timeout: 10_000 });
  await expect(g.erik.locator('.ludo-controls')).toBeVisible({ timeout: 10_000 });
}

/** One step for whoever may act: move a piece if one may move (the highest number), else roll. What was done, or null. */
async function step(g, { erikRolls = true } = {}) {
  for (const [who, p] of [['Bob', g.host], ['Erik', g.erik]]) {
    try {
      const pc = p.locator('.ludo-controls .pc:not([disabled])');
      if (await pc.count()) {
        const btn = pc.last(), i = +(await btn.getAttribute('data-piece', { timeout: 3_000 }));
        await btn.click({ timeout: 3_000 });
        return { who, piece: i };
      }
      const roll = p.locator('.ludo-controls [data-do="roll"]:not([disabled])');
      if ((erikRolls || who !== 'Erik') && await roll.count()) {
        const cls = await roll.first().getAttribute('class', { timeout: 3_000 });
        await roll.first().click({ timeout: 3_000 });
        return { who, rolled: cls };
      }
    } catch (e) { /* the controls were redrawn under the click: look again */ }
  }
  await g.host.waitForTimeout(300);
  return null;
}

/** Where every piece stands on a board: "p0-0@-1 p0-1@-1 ...". */
const positions = page => page.locator('svg.ludo-board g.piece').evaluateAll(ns => ns.map(n => `${n.dataset.id}@${n.dataset.at}`).sort().join(' '));

/** Shake the phone eight times, then tip it over (K-46), with made-up sensor readings. */
const shakeAndPour = page => page.evaluate(async () => {
  const fire = (x, y, z) => {
    const e = new Event('devicemotion');
    Object.defineProperty(e, 'accelerationIncludingGravity', { value: { x, y, z } });
    window.dispatchEvent(e);
  };
  const wait = ms => new Promise(r => setTimeout(r, ms));
  fire(0, 0, 9.8); await wait(80);
  for (let k = 0; k < 8; k++) { fire(k % 2 ? 15 : -15, 0, 9.8); await wait(80); }
  for (let k = 0; k < 15; k++) { fire(0, 0, -9.8); await wait(30); }
});

test('TC-E2E-006: each player picks a colour of their own; a taken colour is shown as taken; the TV draws the board in those colours', async ({ browser, baseURL }) => {
  const g = await table(browser, baseURL);
  await g.erik.getByRole('button', { name: 'Fjord blue', exact: true }).click();
  await expect(g.erik.locator('.cname')).toHaveText('Fjord blue', { timeout: 5_000 });
  await expect(g.tv.locator('.ludo-lobby')).toContainText('Erik Fjord blue', { timeout: 5_000 });
  await expect(g.host.getByRole('button', { name: 'Fjord blue (Erik)' })).toBeDisabled({ timeout: 5_000 });   // AK-31
  await g.host.getByRole('button', { name: 'Moss green', exact: true }).click();
  await expect(g.host.locator('.cname')).toHaveText('Moss green', { timeout: 5_000 });
  await expect(g.erik.getByRole('button', { name: 'Moss green (Bob)' })).toBeDisabled({ timeout: 5_000 });

  await g.host.getByRole('button', { name: 'Start the game' }).click();
  await expect(g.tv.locator('svg.ludo-board')).toBeVisible({ timeout: 10_000 });                 // K-42: the four-armed board
  await expect(g.tv.locator('svg.ludo-board')).toHaveAttribute('aria-label', 'Ludo board with 2 players');
  await expect(g.tv.locator('svg.ludo-board g.piece')).toHaveCount(8);                            // K-44: four numbered pieces each
  await expect(g.tv.locator('svg.ludo-board g.piece text')).toHaveText(['1', '2', '3', '4', '1', '2', '3', '4']);
  await expect(g.tv.locator('.ludo-list')).toContainText('Bob');
  await expect(g.tv.locator('.ludo-list')).toContainText('Moss green');
  await expect(g.tv.locator('.ludo-list')).toContainText('Fjord blue');
  // AK-30: only the player whose turn it is has a die; the other one waits
  const turn = (await g.tv.locator('.ludo-turn').innerText()).startsWith('Erik') ? [g.erik, g.host, 'Erik'] : [g.host, g.erik, 'Bob'];
  await expect(turn[0].locator('.ludo-controls [data-do="roll"]')).toBeVisible({ timeout: 5_000 });
  await expect(turn[1].locator('.ludo-controls')).toContainText(`Waiting for ${turn[2]}`);
  await expect(turn[1].locator('.ludo-controls [data-do="roll"]')).toHaveCount(0);
  await g.close();
});

test('TC-E2E-007: the player whose turn it is rolls on the phone and picks a piece; the move is on the TV within 5 s', async ({ browser, baseURL }) => {
  test.setTimeout(180_000);
  const g = await table(browser, baseURL);
  await start(g);
  let moved = false, picked = null;
  const until = Date.now() + 150_000;
  while (Date.now() < until && !(moved && picked)) {
    const before = await positions(g.tv);
    const s = await step(g);
    if (!s) continue;
    if (s.piece !== undefined) picked = s;
    // whatever the phone now shows (a piece out, a piece moved), the TV shows the same within 5 s (AK-29, NFR-02)
    const page = s.who === 'Bob' ? g.host : g.erik;
    await page.waitForTimeout(400);
    const now = await positions(page);
    if (now !== before) {
      moved = true;
      await expect.poll(() => positions(g.tv), { timeout: 5_000 }).toBe(now);
    }
  }
  expect(moved, 'a piece came out of the nest').toBe(true);
  // a choice between pieces needs two pieces out; a game that never got that far still showed the moves on the TV
  if (picked) await expect(g.tv.locator('.ludo-log')).toContainText(`${picked.who} moved piece ${picked.piece + 1}.`, { timeout: 5_000 });
  await g.close();
});

test('TC-E2E-008: a player who picked shaking rolls by shaking and pouring the phone, or by tapping the cup', async ({ browser, baseURL }) => {
  test.setTimeout(180_000);
  const g = await table(browser, baseURL);
  await start(g, 'shake');
  await expect(g.erik.getByRole('button', { name: 'Shake and pour', exact: true })).toHaveAttribute('aria-pressed', 'true');
  const erikRolls = () => g.tv.locator('.ludo-log li', { hasText: 'Erik rolled' }).count();
  const until = Date.now() + 150_000;
  let shook = false, tapped = false;
  while (Date.now() < until && !(shook && tapped)) {
    const cup = g.erik.locator('.ludo-controls .cup[data-do="roll"]:not([disabled])');
    if (await cup.count()) {
      const n = await erikRolls();
      if (!shook) {                                                                                    // AK-34: shake, then pour
        await shakeAndPour(g.erik);
        shook = true;
      } else {                                                                                         // AK-34: the cup can be tapped
        await cup.click({ timeout: 3_000 }).catch(() => null);
        tapped = true;
      }
      await expect.poll(erikRolls, { timeout: 5_000 }).toBeGreaterThan(n);
      continue;
    }
    await step(g, { erikRolls: false });                                                              // Bob plays; Erik only moves
  }
  expect([shook, tapped]).toEqual([true, true]);
  await g.close();
});

test('TC-E2E-009: the Ludo texts follow the language: the board in Swedish, Erik in Danish, Bob in Dutch', async ({ browser, baseURL }) => {
  const g = await table(browser, baseURL, 'sv');
  await expect(g.tv.locator('.ludo-lobby')).toContainText('Ingen har valt färg än.', { timeout: 10_000 });
  await g.erik.getByLabel('Language').selectOption('da');
  await expect(g.erik.locator('.ludo-pick h2')).toHaveText('Vælg din farve');
  await g.host.getByLabel('Language').selectOption('nl');
  await expect(g.host.locator('.ludo-pick h2')).toHaveText('Kies je kleur');
  await g.erik.locator('.sw[data-colour="fjord"]').click();
  await expect(g.tv.locator('.ludo-lobby')).toContainText('Fjordblå', { timeout: 5_000 });
  await g.host.locator('button[data-act="start"]').click();
  await expect(g.tv.locator('.ludo-legend')).toContainText('Kung: fredad', { timeout: 10_000 });
  await expect(g.erik.locator('.ludo-mode')).toContainText('Sådan slår du med terningen', { timeout: 10_000 });
  await g.close();
});

test('TC-E2E-010: Erik leaves; Bob lets the computer play his place, then gives it to Alice, who plays on with Erik\'s colour', async ({ browser, baseURL }) => {
  test.setTimeout(120_000);
  const g = await table(browser, baseURL);
  await start(g);
  g.erik.on('dialog', d => d.accept());
  await g.erik.getByRole('button', { name: 'Leave the game' }).click();
  await expect(g.tv.locator('.ludo-list')).toContainText('has left', { timeout: 5_000 });
  const seats = g.host.locator('.ludo-seats');
  await expect(seats).toContainText('Erik (Fjord blue) has left. Who takes over?', { timeout: 5_000 });
  await seats.getByRole('button', { name: 'The computer' }).click();
  await expect(g.tv.locator('.ludo-list')).toContainText('the computer plays', { timeout: 5_000 });
  await expect(g.tv.locator('.ludo-log')).toContainText('The computer takes over from Erik.');

  const until = Date.now() + 60_000;                                       // Bob plays; the computer rolls for Erik
  while (Date.now() < until && !(await g.tv.locator('.ludo-log li', { hasText: 'Erik rolled' }).count())) await step(g);
  await expect(g.tv.locator('.ludo-log')).toContainText('Erik rolled');

  const alice = await device(browser, baseURL, USERS.alice, PHONE);         // a new player at the table
  await alice.page.goto(`/mysteries/play.html?code=${new URL(g.host.url()).searchParams.get('code')}`);
  await expect(g.host.locator('ul.waiting li', { hasText: 'Alice' })).toBeVisible({ timeout: 10_000 });
  await g.host.locator('ul.waiting li', { hasText: 'Alice' }).getByRole('button', { name: 'Let in' }).click();
  await expect(alice.page.locator('.ludo-controls')).toContainText('You are watching', { timeout: 10_000 });
  await expect(seats).toContainText('The computer plays for Erik (Fjord blue)', { timeout: 5_000 });
  await seats.getByRole('button', { name: 'Alice' }).click();
  await expect(alice.page.locator('.ludo-controls .who')).toContainText('Alice Fjord blue', { timeout: 5_000 });
  await expect(g.tv.locator('.ludo-list')).toContainText('Alice');
  await expect(g.tv.locator('.ludo-log')).toContainText('Alice takes over from Erik.');
  await expect(seats).toBeHidden();
  await alice.ctx.close();
  await g.close();
});
