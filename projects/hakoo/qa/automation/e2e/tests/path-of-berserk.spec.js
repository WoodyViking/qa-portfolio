// Path of Berserk (games/path-of-berserk.html, js/games/berserk/, berserk.php): the Arcade shelf, the rules of the fight
// run in the page without graphics (engine.js), the controls (keyboard, a game controller, touch), pause, and a run from
// start to end with the server. The score the server counts is tested in BerserkApiTest.
// Traceability: FR-BERSERK-01, -02, -04, -05, -06, -07, -10, -11, -15, FR-GAMES-20.
const { test, expect } = require('@playwright/test');
const { loginAs, freshUserId } = require('./helpers');

const SHOTS = '../../test-results/path-of-berserk';

/** The level and a fresh engine in the page: window.T = {E, L, g(seed)}. */
async function engine(page) {
  await page.goto('/games/path-of-berserk.html');
  await expect.poll(() => page.evaluate(() => !!(window.BerserkEngine && window.PathOfBerserk))).toBe(true);
  await page.evaluate(async () => {
    const L = (await (await fetch('../js/games/berserk/levels.json')).json()).raid;
    window.T = { E: window.BerserkEngine, L, g: seed => window.BerserkEngine.create(L, seed) };
  });
}

test('TC-E2E-245: HAKOO Games has an Arcade shelf with Path of Berserk on it', async ({ page }) => {
  await page.goto('/hakoo-games.html#arcade');
  await expect(page.locator('#gmenu .gm-item[data-cat="arcade"]')).toContainText('Arcade');
  await expect(page.locator('#gmenu .gm-item[data-cat="arcade"] small')).toHaveText('1 game');
  await expect(page.locator('#shelf-title')).toHaveText('🕹️ Arcade');
  const card = page.locator('.game-card[data-key="berserk"]');
  await expect(card).toBeVisible();
  await expect(page.locator('#game-grid .game-card:not([hidden])')).toHaveCount(1);
  await card.click();
  await expect(page).toHaveURL(/games\/path-of-berserk\.html$/);
  await expect(page.locator('#v-home h1')).toContainText('Path of Berserk');
});

test('TC-E2E-246: the fight without graphics: the combo, the shieldman\'s block, Berserk, food, the parts and their waves', async ({ page }, info) => {
  test.skip(info.project.name !== 'chromium', 'the rules are the same in every browser');
  await engine(page);
  const r = await page.evaluate(() => {
    const { E, g: make } = window.T, out = {};
    const quiet = g => { g.state.actors = g.state.actors.filter(a => a.team !== 'foe'); };
    const foe = (g, kind, dx = 0.8) => {                    // a raider right in front of the hero, standing still, facing it
      const h = g.state.hero, f = { id: 900 + g.state.actors.length, kind, team: 'foe', x: h.x + dx, z: h.z, y: 0, vy: 0, vx: 0,
        face: -1, hp: E.KINDS[kind].hp, max: E.KINDS[kind].hp, state: 'recover', t: -1000, cool: 99, charge: 99, side: 1, inside: true };   // held still until hit
      g.state.actors.push(f);
      return f;
    };
    const hit = (g, n = 1) => { for (let k = 0; k < n; k++) { g.step({ attack: true }); for (let i = 0; i < 14; i++) g.step({}); } };
    // The combo: two light hits, the third knocks down
    let g = make(1); g.state.phase = 'test';
    let f = foe(g, 'axeman');
    hit(g, 2);
    out.afterTwo = [f.hp, f.state];
    hit(g, 1);
    out.afterThree = [f.hp, f.state];
    // The shieldman: a blow from the front is blocked; the third of a combo is not; from behind it lands
    g = make(2); g.state.phase = 'test';
    f = foe(g, 'shieldman');
    hit(g, 1);
    out.blocked = [f.hp, g.drain().some(e => e.type === 'block')];
    hit(g, 2);
    out.broken = f.hp;
    g = make(3); g.state.phase = 'test';
    f = foe(g, 'shieldman'); f.face = 1;                      // looking away from the hero
    hit(g, 1);
    out.behind = f.hp;
    // Berserk: the meter fills 8 a hit; full, it doubles the damage and nothing hurts
    g = make(4); g.state.phase = 'test';
    g.state.hero.meter = 99;
    g.step({ berserk: true });
    out.notFull = g.state.hero.berserk;
    g.state.hero.meter = 100;
    g.step({ berserk: true });
    out.on = [g.state.hero.berserk > 5.9, g.state.hero.meter];
    f = foe(g, 'jarl');
    hit(g, 1);
    out.berserkHit = f.max - f.hp;
    f.state = 'windup'; f.t = 10; f.face = -1;
    g.step({});
    out.berserkHurt = g.state.hero.hp;
    // Bread heals 30; at full health it stays on the ground
    g = make(5); g.state.phase = 'test';
    const h = g.state.hero;
    g.state.items.push({ id: 800, kind: 'bread', x: h.x, z: h.z });
    g.step({ mx: 0.3 });
    out.breadFull = g.state.items.length;
    h.hp = 50;
    g.step({ mx: 0.3 });
    out.bread = [h.hp, g.state.items.length];
    // The parts: walking right locks the camera at the beach and sends the first wave; GO when all are down
    g = make(6);
    for (let i = 0; i < 400 && g.state.phase === 'walk'; i++) g.step({ mx: 1 });
    out.locked = [g.state.phase, g.state.camX, g.state.wave];
    for (let i = 0; i < 120; i++) g.step({});
    out.wave1 = g.state.actors.filter(a => a.team === 'foe').length;
    for (let w = 0; w < 2; w++) { quiet(g); g.step({}); for (let i = 0; i < 60; i++) g.step({}); }
    quiet(g); g.step({});
    out.go = [g.state.phase, g.state.go, g.state.part];
    return out;
  });
  expect(r.afterTwo).toEqual([46, 'hurt']);                  // 62 − 8 − 8, still reeling from the blow
  expect(r.afterThree).toEqual([32, 'down']);                // the third: 14, and down
  expect(r.blocked).toEqual([72, true]);
  expect(r.broken).toBe(58);                                 // the second is blocked too, the third (14) lands
  expect(r.behind).toBe(64);
  expect(r.notFull).toBe(0);
  expect(r.on).toEqual([true, 0]);
  expect(r.berserkHit).toBe(16);                             // 8 × 2
  expect(r.berserkHurt).toBe(100);
  expect(r.breadFull).toBe(1);
  expect(r.bread).toEqual([80, 0]);
  expect(r.locked).toEqual(['fight', 9, 0]);
  expect(r.wave1).toBe(3);
  expect(r.go).toEqual(['walk', true, 1]);
});

test('TC-E2E-247: a whole raid played by a simple pilot is won, and the run reports what the level has', async ({ page }, info) => {
  test.skip(info.project.name !== 'chromium', 'the rules are the same in every browser');
  await engine(page);
  const r = await page.evaluate(() => {
    const { E, L, g: make } = window.T;
    const g = make(42), S = g.state;
    for (let n = 0; n < 60 * 400 && S.phase !== 'won' && S.phase !== 'over'; n++) {
      const h = S.hero, foes = S.actors.filter(a => a.team === 'foe' && a.state !== 'dying' && a.state !== 'dead');
      const props = S.actors.filter(a => a.team === 'prop' && a.state !== 'dead' && Math.abs(a.x - h.x) < 4);
      const t = foes.sort((a, b) => Math.abs(a.x - h.x) - Math.abs(b.x - h.x))[0] || props[0];
      const inp = { mx: 0, mz: 0, berserk: h.meter >= 100 };
      if (t) {
        const side = t.x > h.x ? 1 : -1, dx = t.x - side * 0.8 - h.x, dz = t.z - h.z;
        inp.mx = Math.abs(dx) > 0.15 ? Math.sign(dx) : 0; inp.mz = Math.abs(dz) > 0.15 ? Math.sign(dz) : 0;
        const front = S.actors.some(a => a.team !== 'hero' && !['dying', 'dead', 'down'].includes(a.state)
          && (a.x - h.x) * h.face > 0.15 && (a.x - h.x) * h.face < 1.1 && Math.abs(a.z - h.z) < 0.4);
        if (front) { inp.mx = 0; inp.mz = 0; inp.attack = n % 4 === 0; } else if (!inp.mx && !inp.mz && h.face !== side) inp.mx = side * 0.5;
      } else inp.mx = 1;
      g.step(inp);
      g.drain();
    }
    return { phase: S.phase, report: g.report(), totals: E.totals(L), score: g.score() };
  });
  expect(r.phase).toBe('won');
  expect(r.report.kills).toEqual(r.totals.kills);
  expect([r.report.won, r.report.parts, r.report.breaks]).toEqual([true, 4, 6]);
  expect(r.report.health).toBeGreaterThan(0);
  expect(r.score).toBeGreaterThan(6800 + 1000);
});

test('TC-E2E-248: the controls: the keyboard walks and pauses, a game controller hits, and on a touch screen the buttons work', async ({ page }, info) => {
  await page.addInitScript(() => {                           // a pretend game controller, pressed by the test
    window.__pad = { axes: [0, 0, 0, 0], buttons: Array.from({ length: 17 }, () => ({ pressed: false, value: 0 })), index: 0, connected: true, mapping: 'standard', id: 'test pad' };
    navigator.getGamepads = () => [window.__pad];
  });
  await page.goto('/games/path-of-berserk.html');
  await page.locator('#play').click();
  await expect(page.locator('#v-game')).toBeVisible();
  const state = () => page.evaluate(() => { const s = PathOfBerserk.game.state; return { x: s.hero.x, st: s.hero.state, combo: s.hero.combo, t: s.t }; });
  const ok = await page.evaluate(() => !document.getElementById('nogl').hidden);
  if (ok) test.skip(true, 'no WebGL in this browser: the page says so (checked in TC-E2E-249)');
  const x0 = (await state()).x;
  if (info.project.name === 'mobile') {
    // Touch: the stick and the buttons are shown; the hit button starts a blow
    await expect(page.locator('#touch')).toBeVisible();
    await page.locator('.pob-tb.hit').dispatchEvent('pointerdown', { pointerId: 1, pointerType: 'touch' });
    await expect.poll(async () => (await state()).st).toBe('attack');
    await page.locator('.pob-tb.pause').dispatchEvent('pointerdown', { pointerId: 2, pointerType: 'touch' });
    await expect(page.locator('#pause')).toBeVisible();
    await page.screenshot({ path: `${SHOTS}-phone.png` });
    return;
  }
  await page.keyboard.down('ArrowRight');
  await expect.poll(async () => (await state()).x, { timeout: 5000 }).toBeGreaterThan(x0 + 0.5);
  await page.keyboard.up('ArrowRight');
  // The controller's X is a hit
  await page.evaluate(() => { window.__pad.buttons[2].pressed = true; });
  await expect.poll(async () => (await state()).st).toBe('attack');
  await page.evaluate(() => { window.__pad.buttons[2].pressed = false; });
  // P pauses: the game stands still; P again goes on
  await page.keyboard.press('p');
  await expect(page.locator('#pause')).toBeVisible();
  const t1 = (await state()).t;
  await page.waitForTimeout(400);
  expect((await state()).t).toBe(t1);
  if (info.project.name === 'chromium') await page.screenshot({ path: `${SHOTS}-paused.png` });
  await page.keyboard.press('p');
  await expect(page.locator('#pause')).toBeHidden();
  await expect.poll(async () => (await state()).t).toBeGreaterThan(t1);
  // On the start page the arrows and Space are the page's again
  await page.locator('#pause-btn').click();
  await page.locator('#quit').click();
  await expect(page.locator('#v-home')).toBeVisible();
  expect(await page.evaluate(() => PathOfBerserk.input.active)).toBe(false);
});

test('TC-E2E-249: a run from start to end: the server counts it and the result shows', async ({ page }, info) => {
  test.skip(info.project.name === 'firefox', 'one desktop browser and the phone are enough');
  await page.goto('/games/path-of-berserk.html');
  await expect(page.locator('#top li').first()).not.toHaveText('Loading…');
  const finished = page.waitForResponse(r => r.url().includes('berserk.php?action=finish'));
  await page.locator('#play').click();
  await expect(page.locator('#v-game')).toBeVisible();
  if (await page.evaluate(() => !document.getElementById('nogl').hidden)) {
    await expect(page.locator('#nogl')).toContainText('WebGL');
    return;
  }
  await expect(page.locator('.pob-canvas')).toBeVisible();
  await expect(page.locator('#hud-score')).toHaveText('0');
  // Fall at once: the hero's last life goes
  await page.evaluate(() => { const h = PathOfBerserk.game.state.hero; h.lives = 1; h.hp = 0; h.state = 'dying'; h.t = 2; });
  const res = await finished;
  const body = res.request().postDataJSON();
  expect([body.won, body.parts, body.health, body.lives]).toEqual([false, 1, 0, 0]);
  await expect(page.locator('#result')).toBeVisible();
  await expect(page.locator('#result-title')).toHaveText('Fallen in battle');
  await expect(page.locator('#result-lines .total b')).toHaveText('0');
  await page.locator('#result-close').click();
  await expect(page.locator('#v-home')).toBeVisible();
  await page.locator('#tab-year').click();
  await expect(page.locator('#tab-year')).toHaveAttribute('aria-selected', 'true');
});

test('TC-E2E-250: the stage fits the window at every text size and screen width', async ({ page, context, baseURL }) => {
  for (const size of ['normal', 'large', 'xl']) {
    await context.addInitScript(s => { try { if (s !== 'normal') localStorage.setItem('wf_textsize', s); else localStorage.removeItem('wf_textsize'); } catch (e) {} }, size);
    await page.goto('/games/path-of-berserk.html');
    const over = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(over, `text size ${size}: no sideways scrolling`).toBeLessThanOrEqual(1);
    await page.locator('#play').click();
    await expect(page.locator('#v-game')).toBeVisible();
    const box = await page.locator('#stage').boundingBox();
    const vw = page.viewportSize().width;
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(vw + 1);
    expect(Math.abs(box.width / box.height - 16 / 9)).toBeLessThan(0.05);
  }
});

test('TC-E2E-251: the keys can be changed: a key moves from the action that had it, Save keeps them in this browser, Default keys removes them', async ({ page }, info) => {
  test.skip(info.project.name === 'mobile', 'a phone has no keyboard');
  const stored = () => page.evaluate(() => JSON.parse(localStorage.getItem('hakoo_berserk_keys') || 'null'));
  const slot = (a, i) => page.locator(`.pob-slot[data-a="${a}"][data-i="${i}"]`);
  await page.goto('/games/path-of-berserk.html');
  await expect(page.locator('.pob-controls .pob-k[data-acts="attack"]')).toHaveText('J / Z');
  expect(await stored()).toBeNull();                          // nothing kept before a choice
  await page.locator('#keys-open').click();
  await slot('attack', 0).click();
  await expect(slot('attack', 0)).toHaveText('Press a key…');
  await page.keyboard.press('f');
  await expect(slot('attack', 0)).toHaveText('F');
  await slot('jump', 0).click();
  await page.keyboard.press('z');                             // Z was the second key for Hit
  await expect(page.locator('#keys-msg')).toContainText('was taken from');
  await expect(slot('attack', 1)).toHaveText('—');
  await slot('berserk', 1).click();
  await page.keyboard.press('Escape');                        // Esc may be chosen; the dialog stays open
  await expect(page.locator('#keys')).toBeVisible();
  await expect(slot('berserk', 1)).toHaveText('Esc');
  await expect(slot('pause', 1)).toHaveText('—');
  await slot('pause', 1).click();
  await page.keyboard.press('Control');
  await expect(page.locator('#keys-msg')).toHaveText('That key cannot be used.');
  await page.keyboard.press('KeyO');
  // Every action needs a key: empty Hit and try to save
  await slot('attack', 0).click();
  await page.keyboard.press('Backspace');
  await page.locator('#keys-save').click();
  await expect(page.locator('#keys-msg')).toHaveText('Every action needs a key.');
  await slot('attack', 0).click();
  await page.keyboard.press('f');
  expect(await stored()).toBeNull();                          // not before Save
  await page.locator('#keys-save').click();
  await expect(page.locator('#keys')).toBeHidden();
  const k = await stored();
  expect([k.attack, k.jump, k.berserk, k.pause]).toEqual([['KeyF', null], ['KeyZ', 'Space'], ['KeyL', 'Escape'], ['KeyP', 'KeyO']]);
  await expect(page.locator('.pob-controls .pob-k[data-acts="attack"]')).toHaveText('F');

  // In the game F hits and J no longer does; after a reload the keys are still there
  await page.reload();
  await expect(page.locator('.pob-controls .pob-k[data-acts="attack"]')).toHaveText('F');
  await page.locator('#play').click();
  await expect(page.locator('#v-game')).toBeVisible();
  await expect(page.locator('#tip .pob-k[data-acts="attack"]')).toHaveText('F');
  if (await page.evaluate(() => document.getElementById('nogl').hidden)) {
    const st = () => page.evaluate(() => PathOfBerserk.game.state.hero.state);
    await page.keyboard.press('j');
    await page.waitForTimeout(300);
    expect(await st()).not.toBe('attack');
    await page.keyboard.press('f');
    await expect.poll(st).toBe('attack');
    await page.locator('#pause-btn').click();
    await page.locator('#quit').click();
  } else await page.locator('#nogl-back').click();

  // HAKOO Games says what is kept; Default keys removes it
  await page.goto('/hakoo-games.html');
  await expect(page.locator('#b-stored')).toContainText('game keys');
  await page.goto('/games/path-of-berserk.html');
  await page.locator('#keys-open').click();
  await page.locator('#keys-default').click();
  expect(await stored()).toBeNull();
  await expect(page.locator('.pob-controls .pob-k[data-acts="attack"]')).toHaveText('J / Z');
});

test('TC-E2E-252: the keys follow the member: keys kept as a guest move to the account, and another device gets the same keys', async ({ browser, baseURL }, info) => {
  test.skip(info.project.name === 'mobile', 'a phone has no keyboard');
  const id = freshUserId();
  const attack = page => page.locator('.pob-controls .pob-k[data-acts="attack"]');
  const stored = page => page.evaluate(() => localStorage.getItem('hakoo_berserk_keys'));
  async function change(page, act, key) {
    await page.locator('#keys-open').click();
    await page.locator(`.pob-slot[data-a="${act}"][data-i="0"]`).click();
    await page.keyboard.press(key);
    await page.locator('#keys-save').click();
    await expect(page.locator('#keys')).toBeHidden();
  }
  // This computer: as a guest the keys are kept in the browser
  const pc = await browser.newContext();
  const a = await pc.newPage();
  await a.goto('/games/path-of-berserk.html');
  await change(a, 'attack', 'f');
  expect(await stored(a)).not.toBeNull();
  // Logged in: they move to the account and leave the browser
  await loginAs(pc, baseURL, id, 'Keyholder');
  await a.reload();
  await expect(attack(a)).toHaveText('F / Z');
  await expect.poll(() => stored(a)).toBeNull();
  await a.locator('#keys-open').click();
  await expect(a.locator('#keys-where')).toContainText('kept with your account');
  await a.locator('#keys-cancel').click();
  // Another computer: the same member gets the same keys, with nothing in that browser
  const other = await browser.newContext();
  await loginAs(other, baseURL, id, 'Keyholder');
  const b = await other.newPage();
  await b.goto('/games/path-of-berserk.html');
  await expect(attack(b)).toHaveText('F / Z');
  await change(b, 'attack', 'g');
  expect(await stored(b)).toBeNull();
  await a.reload();
  await expect(attack(a)).toHaveText('G / Z');                  // changed there, here too
  // Default keys there: the default keys here as well
  await b.locator('#keys-open').click();
  await b.locator('#keys-default').click();
  await a.reload();
  await expect(attack(a)).toHaveText('J / Z');
  await pc.close();
  await other.close();
});

