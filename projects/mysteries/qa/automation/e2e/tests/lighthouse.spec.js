// The escape room "The Lighthouse in the Storm" in real browsers: the puzzles on the phones (US-24, US-25). Bob hosts and
// plays, Erik plays on a phone, a TV shows the room. Everything is drawn at random, so the tests solve the puzzles the way
// a team does: they read their own phones (sliding tiles, the valve chart, the ring lines, the signal book) and talk.
// Traceability: US-24, US-25, K-109..K-120, NFR-01, NFR-02, AK-86, AK-87, AK-89, AK-91, AK-93.
const { test, expect } = require('@playwright/test');
const { USERS, device } = require('./helpers');

const TV = { width: 1920, height: 1080 }, PHONE = { width: 390, height: 844 };

/** Bob starts the lighthouse (60 min) and lets a TV and Erik in. */
async function table(browser, baseURL, { len = '60' } = {}) {
  const host = await device(browser, baseURL, USERS.bob);
  await host.page.goto('/mysteries/');
  await host.page.locator('select[name=category]').selectOption('escape:lighthouse');
  await host.page.locator('select[name=lengthMin]').selectOption(len);
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
  for (const name of ['TV', 'Erik']) await host.page.locator('ul.waiting li', { hasText: name }).getByRole('button', { name: 'Let in' }).click();
  await expect(tv.page.locator('.my-cover h2')).toHaveText('The Lighthouse in the Storm', { timeout: 10_000 });
  for (const p of [host.page, erik.page]) p.on('dialog', d => d.accept());
  await host.page.getByRole('button', { name: 'Start the game' }).click();
  return { code, host: host.page, tv: tv.page, erik: erik.page, close: () => Promise.all([host.ctx.close(), tv.ctx.close(), erik.ctx.close()]) };
}

/** With SHOTS=1: a picture of a lock on a screen, for the design review (not committed). */
async function shot(page, name, lock) {
  if (!process.env.SHOTS) return;
  const el = lock ? panel(page, lock) : page.locator('main');
  await expect(async () => el.screenshot({ path: `../../test-results/shots/${name}.png`, timeout: 2_000 })).toPass({ timeout: 10_000 });
}
const panel = (page, lock) => page.locator(`section.esc-lock[data-lock="${lock}"]`);
async function until(click, check, timeout = 15_000) {
  await expect(async () => { await click(); await check(); }).toPass({ timeout });
}
async function tab(page, name) {
  const b = page.locator(`[data-tab="${name}"]`);
  if (await b.getAttribute('aria-pressed') === 'true') return;
  await until(() => b.click(), () => expect(b).toHaveAttribute('aria-pressed', 'true', { timeout: 1_000 }));
}
async function thirdHint(page, lock) {
  await tab(page, 'room');
  const box = panel(page, lock), hints = box.locator('.esc-hints li');
  for (let n = await hints.count(); n < 3; n = await hints.count()) {
    await until(async () => { if (await hints.count() === n) await box.getByRole('button', { name: `Hint ${n + 1}` }).click({ timeout: 2_000 }); },
      async () => { if (await box.count()) await expect(hints).toHaveCount(n + 1, { timeout: 2_000 }); });
    if (!await box.count()) return '';                            // the third hint laid the puzzle right (K-115)
  }
  return (await hints.nth(2).innerText()).replace(/^\s*3\.\s*/, '').trim();
}
/** The texts of a lock's fragments on a phone. */
async function frags(page, lock) {
  await tab(page, 'frags');
  const box = page.locator('section.esc-frag', { hasText: lock });
  const out = (await box.count()) ? await box.locator('.paper').allInnerTexts() : [];
  await tab(page, 'room');
  return out;
}

/** Pump the lamp: the two phones in turn, ten times each (K-85). The last pump is Erik's: he gets the key ring. */
async function lightTheLamp(g) {
  for (let i = 0; i < 20; i++) {
    const p = i % 2 ? g.erik : g.host;
    await panel(p, 'lamp').locator('[data-press]').click();
    if (i < 19) await expect(panel(p, 'lamp').locator('p.small.muted')).toContainText(new RegExp(`\\b${i + 1}\\D+20\\b`), { timeout: 5_000 });
  }
  await expect(g.tv.locator('.esc-room')).toBeVisible({ timeout: 10_000 });
}

/** The shortest way to lay a sliding puzzle: the tiles to tap, one by one (breadth first; 3×3 has 181 440 positions). */
function slideWay(start, n) {
  const goal = Array.from({ length: n * n }, (_, i) => (i + 1) % (n * n)).join(',');
  const from = new Map([[start.join(','), null]]);
  let wave = [start];
  while (wave.length) {
    const next = [];
    for (const t of wave) {
      const key = t.join(',');
      if (key === goal) {
        const way = [];
        for (let k = key; from.get(k); k = from.get(k)[0]) way.unshift(from.get(k)[1]);
        return way;
      }
      const g = t.indexOf(0), x = g % n, y = Math.floor(g / n);
      for (const [dx, dy] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
        if (x + dx < 0 || x + dx >= n || y + dy < 0 || y + dy >= n) continue;
        const i = (y + dy) * n + x + dx, u = t.slice();
        [u[g], u[i]] = [u[i], 0];
        const k = u.join(',');
        if (!from.has(k)) { from.set(k, [key, i]); next.push(u); }
      }
    }
    wave = next;
  }
  return null;
}
/** Lay every sliding puzzle on one phone with the fingers (K-111): read the tiles, tap the way. */
async function slideAll(page) {
  const box = panel(page, 'bricks');
  await expect(box.locator('[data-slide], .esc-slid').first()).toBeVisible({ timeout: 10_000 });
  for (let grid = box.locator('[data-slide]').first(); await grid.count(); grid = box.locator('[data-slide]').first()) {
    const id = await grid.getAttribute('data-slide'), cells = grid.locator(':scope > *');
    const n = Math.round(Math.sqrt(await cells.count())), start = [];
    for (let i = 0; i < n * n; i++) {
      const label = await cells.nth(i).getAttribute('aria-label');
      start.push(label ? +label.match(/\d+/)[0] : 0);
    }
    for (const i of slideWay(start, n)) await page.locator(`[data-slide="${id}"] [data-tile="${i}"]`).click();
    await expect(page.locator(`[data-slide="${id}"]`)).toHaveCount(0, { timeout: 10_000 });
  }
}

/** Turn the dial lock's wheels to a code and try it. */
async function dial(page, lock, code) {
  for (const [i, d] of [...code].entries()) {
    for (let k = 0; k < +d; k++) await panel(page, lock).locator(`[data-dial="${i}"][data-step="1"]`).click();
  }
  await expect(panel(page, lock).locator('.esc-dials b')).toHaveText([...code]);
  await panel(page, lock).getByRole('button', { name: 'Try the code' }).click();
}

/** The valves: the chart from both phones' rows, the fewest wheels that turn the panel into it (K-111). */
async function valves(g) {
  const target = [];
  for (const p of [g.host, g.erik]) {
    await tab(p, 'frags');
    const rows = p.locator('section.esc-frag', { hasText: 'The valves' }).locator('.paper');
    for (let r = 0; r < await rows.count(); r++) {
      const row = +(await rows.nth(r).innerText()).match(/Row (\d+)/)[1] - 1;
      target[row] = await rows.nth(r).locator('.valve').evaluateAll(v => v.map(x => x.classList.contains('open')));
    }
    await tab(p, 'room');
  }
  const want = target.flat(), cols = target[0].length, cells = want.length;
  const on = await panel(g.host, 'valves').locator('button.valve').evaluateAll(v => v.map(x => x.classList.contains('open')));
  let best = null;
  for (let set = 0; set < 1 << cells; set++) {
    const s = on.slice();
    for (let c = 0; c < cells; c++) if (set & (1 << c)) {
      for (const d of [0, -cols, cols, -1, 1]) {
        const k = c + d;
        if (k < 0 || k >= cells || ((d === -1 || d === 1) && Math.floor(k / cols) !== Math.floor(c / cols))) continue;
        s[k] = !s[k];
      }
    }
    if (s.every((x, i) => x === want[i]) && (!best || popcount(set) < popcount(best))) best = set;
  }
  for (let c = 0; c < cells; c++) if (best & (1 << c)) {
    const before = await panel(g.host, 'valves').locator('button.valve').evaluateAll(v => v.map(x => x.className).join());
    await panel(g.host, 'valves').locator(`[data-flip="${c}"]`).click();
    await expect(async () => {                                    // the server's panel is back before the next wheel
      if (!await panel(g.host, 'valves').count()) return;
      expect(await panel(g.host, 'valves').locator('button.valve').evaluateAll(v => v.map(x => x.className).join())).not.toBe(before);
    }).toPass({ timeout: 5_000 });
  }
}
const popcount = x => x.toString(2).replace(/0/g, '').length;

/** The lens rings: the lines from the light list, and each owner turns until the mark faces the sea. */
async function rings(g) {
  const want = {};
  for (const p of [g.host, g.erik]) for (const t of await frags(p, 'The lens rings')) {
    const m = t.match(/Ring (\d+): (.+) towards the sea/);
    want[+m[1] - 1] = m[2].trim();
  }
  for (let round = 0; round < 3 && await panel(g.host, 'rings').count(); round++) {   // a second look if a turn was lost
    for (const [ring, mark] of Object.entries(want)) {
      for (const p of [g.host, g.erik]) {
        const right = panel(p, 'rings').locator(`[data-spin="${ring}"][data-dir="1"]`);
        if (!await right.count()) continue;                        // not this one's ring (K-113)
        const up = panel(p, 'rings').locator('.esc-ringlist li').nth(+ring).locator('.ring-strip .up');
        const label = () => up.getAttribute('aria-label', { timeout: 1_000 }).catch(() => null);
        for (let k = 0; k < 12; k++) {
          const now = await label();
          if (now === null || now === mark) break;                 // facing the sea, or the lock is open
          await right.click({ timeout: 2_000 }).catch(() => {});
          await expect(async () => expect(await label()).not.toBe(now)).toPass({ timeout: 5_000 }).catch(() => {});
        }
      }
    }
  }
}

/** Tap a signal with the two buttons (K-110) and send it. */
async function tapSignal(page, beats) {
  for (const b of beats) await panel(page, 'signal').getByRole('button', { name: b === '•' ? '• Short' : '▬ Long' }).click();
  await expect(panel(page, 'signal')).toContainText(`Your answer: ${beats}`);
  await panel(page, 'signal').getByRole('button', { name: 'Flash the answer' }).click();
}

test.describe('The lighthouse', () => {
  test.describe.configure({ timeout: 240_000 });                 // three devices, and a whole room of puzzles

  /** TC-E2E-027 (K-119, K-77): the lighthouse offers 30 and 60 minutes; 90 comes later */
  test('the lengths of the lighthouse', async ({ browser, baseURL }) => {
    const host = await device(browser, baseURL, USERS.bob);
    await host.page.goto('/mysteries/');
    await host.page.locator('select[name=category]').selectOption('escape:lighthouse');
    await expect(host.page.locator('select[name=lengthMin] option:not([disabled])')).toHaveText(['30 min', '60 min']);
    await host.ctx.close();
  });

  /** TC-E2E-028 (AK-93, AK-86, AK-87, AK-89, AK-91, K-110, K-116, K-117, NFR-02): the whole 60-minute room, puzzles by hand */
  test('play the whole lighthouse', async ({ browser, baseURL }) => {
    const g = await table(browser, baseURL);
    await expect(g.tv.locator('.my-head')).toContainText('Pump the lamp on your phones!', { timeout: 10_000 });   // the room's own words
    await lightTheLamp(g);
    await expect(g.tv.locator('.esc-fire .fire-t')).toContainText('of oil left in the lamp');

    // the sea mark puzzles: each phone lays its own; the TV says who is done, never the digit (K-116, AK-86)
    await expect(g.tv.locator('.esc-puzzles li')).toHaveCount(4, { timeout: 10_000 });
    await shot(g.erik, 'phone-bricks', 'bricks');
    await shot(g.tv, 'tv-bricks');
    await slideAll(g.host);
    await expect(g.tv.locator('.esc-puzzles li.done')).toHaveCount(2, { timeout: 5_000 });
    await expect(g.tv.locator('.my-log')).toContainText('Bob solved the sliding puzzle');
    await slideAll(g.erik);
    for (const p of [g.host, g.erik]) expect((await frags(p, 'The sea mark puzzles')).filter(t => /On the back of .+: \d/.test(t))).toHaveLength(2);
    await expect(g.tv.locator('body')).not.toContainText('On the back of');

    // the tower door: the dial lock, turned by whoever has the key ring
    await expect(panel(g.erik, 'door')).toBeVisible({ timeout: 10_000 });
    const code = (await thirdHint(g.host, 'door')).match(/\d{4}/)[0];
    await expect(panel(g.erik, 'door').locator('.esc-needs')).toContainText('✓');
    await shot(g.erik, 'phone-door', 'door');
    await dial(g.erik, 'door', code);
    await expect(g.tv.locator('.esc-room figcaption')).toHaveText('The engine room', { timeout: 10_000 });

    // the gas pipe: an own square turns on every screen (AK-87, NFR-02); the third hint lays the rest
    await expect(panel(g.erik, 'valves')).toBeVisible({ timeout: 10_000 });
    await shot(g.erik, 'phone-gas', 'gas');
    await shot(g.erik, 'phone-valves', 'valves');
    await shot(g.tv, 'tv-engine');
    const mine = panel(g.host, 'gas').locator('button.cell:not([disabled])').first();
    const cell = +await mine.getAttribute('data-cell');
    const tvCell = g.tv.locator('.esc-pipes span.cell').nth(cell).locator('path.pipe');
    const before = await tvCell.getAttribute('d');
    await mine.click();
    await expect(tvCell).not.toHaveAttribute('d', before, { timeout: 5_000 });
    await expect(panel(g.host, 'gas').locator('button.cell[disabled]').first()).toBeDisabled();   // the others' squares
    await thirdHint(g.host, 'gas');
    await expect(panel(g.host, 'gas')).toHaveCount(0, { timeout: 10_000 });

    // the valves by the chart, the counterweight by the third hint
    await valves(g);
    await expect(panel(g.host, 'valves')).toHaveCount(0, { timeout: 10_000 });
    const things = (await thirdHint(g.host, 'hatch')).replace(/^.*?: /, '').replace(/\.$/, '').split(', ');
    for (const t of things) await panel(g.host, 'hatch').getByLabel(t, { exact: false }).check();
    await panel(g.host, 'hatch').getByRole('button', { name: 'Weigh' }).click();
    await expect(g.tv.locator('.esc-room figcaption')).toHaveText('The lantern', { timeout: 10_000 });

    // the lens rings: the line for a ring is with the other player (AK-88), so they talk
    await expect(panel(g.erik, 'rings')).toBeVisible({ timeout: 10_000 });
    await shot(g.erik, 'phone-rings', 'rings');
    await shot(g.tv, 'tv-lantern');
    await rings(g);
    await expect(panel(g.host, 'rings')).toHaveCount(0, { timeout: 10_000 });

    // the signal: a wrong answer costs nothing (AK-89), the right one ends the game
    await expect(panel(g.erik, 'signal')).toBeVisible({ timeout: 10_000 });
    await shot(g.erik, 'phone-signal', 'signal');
    await shot(g.tv, 'tv-signal');
    const [pic, lamp] = [await g.tv.locator('.esc-ship.pin .bg').boundingBox(), await g.tv.locator('.esc-ship.pin .esc-blink').boundingBox()];
    expect(Math.abs((lamp.x + lamp.width / 2 - pic.x) / pic.width - .699), 'K-117: the lamp sits on the ship\'s mast').toBeLessThan(.01);
    expect(Math.abs((lamp.y + lamp.height / 2 - pic.y) / pic.height - .33)).toBeLessThan(.01);
    const ship = (await panel(g.erik, 'signal').locator('.esc-ship .beats').innerText()).replace(/^.*: /, '').trim();
    const book = [...await frags(g.host, 'The signal'), ...await frags(g.erik, 'The signal')]
      .map(t => t.match(/flashes (\S+), answer (\S+)\./)).filter(Boolean);
    const answer = book.find(m => m[1] === ship)[2], wrong = book.find(m => m[1] !== ship)[2];
    await tapSignal(g.erik, wrong);
    await expect(g.erik.locator('#msg, .my-notes')).toContainText('Not that one', { timeout: 5_000 });
    await expect(g.tv.locator('.my-side')).toContainText('Wrong answers: 0');
    await g.erik.waitForTimeout(3_000);
    await tapSignal(g.erik, answer);
    for (const p of [g.tv, g.host, g.erik]) await expect(p.locator('.esc-end')).toContainText('The lighthouse is lit!', { timeout: 10_000 });
    await expect(g.tv.locator('.esc-end')).toContainText('Linnea had forty passengers');
    await expect(g.tv.locator('.esc-end')).toContainText('Hints: 9');
    await shot(g.tv, 'tv-end');
    await g.close();
  });
});
