// Checkers (games/checkers.html, js/games/checkers.js on js/shared/board-game.js).
// playwright.config.js asks for less motion: the other player's move shows at once.
// Traceability: FR-CHECKERS-09, FR-GAMES-02, US-35.
const { test, expect } = require('@playwright/test');

const SHOTS = '../../test-results/checkers';
const sq = (page, r, c) => page.locator(`#board .ksq[data-r="${r}"][data-c="${c}"]`);

test('TC-E2E-184: a move against the computer: pick a piece, then a dot; the computer answers; nothing is stored', async ({ page, context }, info) => {
  await page.goto('/games/checkers.html');
  await page.click('#go-computer');
  await page.check('input[name=color][value=black]');
  await page.check('input[name=difficulty][value=easy]');
  await page.click('#start');
  await expect(page.locator('#turn')).toHaveText('Your turn (Black)');      // English checkers: black moves first
  await expect(page.locator('#variant-tag')).toHaveText('🇬🇧 English checkers');
  await expect(sq(page, 0, 1)).toHaveAttribute('aria-label', 'b8, your piece');
  // Black sits at the bottom: the board is turned round for the black player
  const top = await sq(page, 7, 0).boundingBox(), bottom = await sq(page, 0, 1).boundingBox();
  expect(top.y).toBeLessThan(bottom.y);

  await sq(page, 2, 1).click();
  await expect(page.locator('#board .ksq.sel')).toHaveCount(1);
  await expect(page.locator('#board .ksq.to')).toHaveCount(2);
  await sq(page, 4, 3).click();                                              // not a dot: refused at once
  await expect(page.locator('#error')).toContainText('cannot go there');
  await sq(page, 3, 2).click();
  await expect(page.locator('#move-count')).toHaveText('2');
  await expect(page.locator('#turn')).toContainText('Your turn (Black)');
  await expect(page.locator('#movelist button.mv').first()).toHaveText('b6–c5');
  await page.screenshot({ path: `${SHOTS}-move-${info.project.name}.png`, fullPage: true });
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
  expect(await context.cookies()).toEqual([]);
});

test('TC-E2E-185: a friend\'s jump comes in by itself, taking is compulsory and the takers are marked; flying kings say so', async ({ page, request }) => {
  const api = (action, body, token) => request.post(`/checkers.php?action=${action}`, { data: body, headers: token ? { 'X-Game-Token': token } : {} }).then(r => r.json());
  const a = await api('new', { mode: 'friend', color: 'white', variant: 'international', name: 'Ann' });
  const b = await api('join', { game: a.game, invite: a.invite, name: 'Ben' });
  await page.goto(`/games/checkers.html#g=${a.game}&p=${a.token}`);
  await expect(page.locator('#variant-tag')).toHaveText('👑 Flying kings');
  await expect(page.locator('#turn')).toHaveText('Your turn (White)');      // flying kings: white moves first
  await sq(page, 5, 2).click();
  await sq(page, 4, 3).click();
  await expect(page.locator('#move-count')).toHaveText('1');
  await api('move', { game: a.game, path: [[2, 5], [3, 4]] }, b.token);    // Ben offers a piece: Ann must take it
  await expect(page.locator('#move-count')).toHaveText('2', { timeout: 8000 });
  await expect(page.locator('#turn')).toContainText('You must take.');
  await expect(page.locator('#board .ksq.src')).toHaveCount(1);
  await sq(page, 5, 0).click();                                             // a piece that cannot take
  await expect(page.locator('#error')).toContainText('You must take');
  await sq(page, 4, 3).click();
  await sq(page, 2, 5).click();
  await expect(page.locator('#move-count')).toHaveText('3');
  await expect(page.locator('#count-black')).toHaveText('11');
  await expect(page.locator('#movelist button.mv').last()).toHaveText('d4×f6');
});
