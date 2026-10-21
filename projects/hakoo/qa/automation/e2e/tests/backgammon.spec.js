// Backgammon (games/backgammon.html, js/games/backgammon.js on js/shared/board-game.js, the dice from js/shared/dice.js).
// playwright.config.js asks for less motion: the dice land and the checkers move at once.
// Traceability: FR-BACKGAMMON-09, FR-GAMES-13, US-33.
const { test, expect } = require('@playwright/test');
const { openNav } = require('./helpers');

const SHOTS = '../../test-results/backgammon';

async function start(page) {
  await page.goto('/games/backgammon.html');
  await page.click('#go-computer');
  await page.check('input[name=color][value=white]');
  await page.check('input[name=difficulty][value=easy]');
  await page.click('#start');
  await expect(page.locator('#turn')).toContainText(/Your turn|You start/);     // the opening roll may be mine
}

/** Roll with the button (⚙ → A button). */
async function rollWithButton(page) {
  await openNav(page);                                            // on a phone ⚙ is in ☰
  await page.click('#settings-open');
  await page.check('input[name=set-roll][value=button]');
  await expect(page.locator('#dice-msg')).toHaveText('Log in to keep it for next time.');
  await page.click('#settings-close');
}

/** Play a whole turn: glowing checker, then a green target (or the checker twice: the higher die), until Done. */
async function playTurn(page) {
  for (let i = 0; i < 6 && await page.locator('#done').isDisabled(); i++) {
    await page.locator('#board .src').first().click();
    const to = page.locator('#board .to');
    if (await to.count()) await to.first().click();
    else await page.locator('#board .sel').click();
  }
  await expect(page.locator('#done')).toBeEnabled();
  await page.click('#done');
}

test('TC-E2E-182: a turn against the computer: roll, move checker by checker, Undo, Done; the computer answers; nothing is stored', async ({ page, context }, info) => {
  await start(page);
  await rollWithButton(page);
  const before = +(await page.locator('#move-count').textContent());
  if (await page.locator('#roll').isVisible()) {
    await page.click('#roll');
    await expect(page.locator('#turn')).toContainText('Your turn: play');
  }
  await expect(page.locator('#bg-left span')).not.toHaveCount(0);
  // One step, then Undo puts it back.
  await page.locator('#board .src').first().click();
  await expect(page.locator('#board .sel')).toHaveCount(1);
  await page.locator('#board .to').first().click();
  await expect(page.locator('#bg-left s')).toHaveCount(1);
  await page.click('#undo');
  await expect(page.locator('#bg-left s')).toHaveCount(0);
  await playTurn(page);
  // My turn and the computer's answer are in the move list, and it is my turn again.
  await expect(page.locator('#move-count')).toHaveText(String(before + 2), { timeout: 8000 });
  await expect(page.locator('#turn')).toHaveText('Your turn: roll the dice.');
  await expect(page.locator('#bg-last')).toContainText('Computer (Easy)');
  await expect(page.locator('#movelist button.mv').last()).toContainText(':');
  await page.screenshot({ path: `${SHOTS}-turn-${info.project.name}.png`, fullPage: true });
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  expect(await context.cookies()).toEqual([]);
});

test('TC-E2E-183: the board and the dice table fit the screen (also on a phone)', async ({ page }) => {
  await start(page);
  const m = await page.evaluate(() => ({
    scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    board: document.getElementById('board').getBoundingClientRect(),
    tray: document.getElementById('tray').getBoundingClientRect().height,
  }));
  expect(m.scroll).toBeLessThanOrEqual(0);
  expect(m.board.width).toBeGreaterThan(300);
  expect(m.board.height / m.board.width).toBeGreaterThan(0.7);
  expect(m.tray).toBeGreaterThan(90);
});

test.describe('with motion (the other turn is shown)', () => {
  test.use({ contextOptions: { reducedMotion: 'no-preference' } });

  test('TC-E2E-197: the dice table shows only on my turn: hidden while the computer rolls and moves; on a phone the cup rolls with a finger', async ({ page }, info) => {
    test.setTimeout(60_000);
    await page.goto('/games/backgammon.html');
    await page.click('#go-computer');
    await page.check('input[name=color][value=white]');
    await page.check('input[name=difficulty][value=easy]');
    await page.click('#start');
    const phone = info.project.name === 'mobile';
    const cdp = phone ? await page.context().newCDPSession(page) : null;
    for (let turn = 0; turn < 2; turn++) {
      // My turn (the computer's is shown first): the table is there and works.
      await expect(page.locator('#tray')).toBeVisible({ timeout: 20_000 });
      if (await page.locator('#turn').textContent().then(t => t.includes('roll the dice'))) {   // (not after an opening roll I won)
        await expect(page.locator('#tray')).not.toHaveClass(/locked/);
        if (phone) {                                                  // hold the cup with a finger, shake, let go
          await page.locator('#tray').scrollIntoViewIfNeeded();
          const c = await page.locator('.dice-cup').boundingBox();
          const cx = c.x + c.width / 2, cy = c.y + c.height / 2;
          await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: cx, y: cy }] });
          for (let i = 0; i < 10; i++) await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: cx - 40 + (i % 2) * 30, y: cy - i * 4 }] });
          await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        } else {                                                      // the same with the mouse
          const c = await page.locator('.dice-cup').boundingBox();
          await page.mouse.move(c.x + c.width / 2, c.y + c.height / 2);
          await page.mouse.down();
          for (let i = 0; i < 6; i++) await page.mouse.move(c.x - 20 + (i % 2) * 30, c.y - i * 5);
          await page.mouse.up();
        }
        await expect(page.locator('#turn')).not.toContainText('roll the dice', { timeout: 10_000 });
      }
      if (await page.locator('#done').isVisible()) {                 // (a roll with no move passes at once)
        await expect(page.locator('#tray')).toBeVisible();            // my dice lie on my table while I move
        for (let i = 0; i < 6 && await page.locator('#done').isDisabled(); i++) {
          await page.locator('#board .src').first().click();
          await expect(page.locator('#board .sel')).toHaveCount(1);
          const to = page.locator('#board .to');
          if (await to.count()) await to.first().click(); else await page.locator('#board .sel').click();
          await expect(page.locator('#board .sel')).toHaveCount(0);
        }
        await page.click('#done');
      }
      // The computer's turn: its dice are with it, the turn line says so.
      await expect(page.locator('#turn')).toContainText('is playing', { timeout: 10_000 });
      await expect(page.locator('#tray')).toBeHidden();
      await expect(page.locator('#bg-hint')).toContainText('Computer (Easy) rolled');
      await expect(page.locator('#bg-left span')).toHaveCount(2);     // its dice, big beside the board
      await expect(page.locator('#p-black')).toHaveClass(/active/);   // its box is lit, not mine
      await expect(page.locator('#p-white')).not.toHaveClass(/active/);
    }
    await page.screenshot({ path: `${SHOTS}-table-${info.project.name}.png`, fullPage: true });
  });
});
