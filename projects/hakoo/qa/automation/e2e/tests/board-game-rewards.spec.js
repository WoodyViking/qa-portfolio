// Chess and Othello rewards, looks and the shared crown (games/chess.html, games/othello.html,
// profile.html, hakoo-games.html, js/shared/crowns.js).
// Fixture: Alice has won on every level of every game (Chess: gold/purple 3D pieces on the HAKOO
// board; Othello: teal/gold 3D discs on the Ocean board; crowns shown).
// Traceability: FR-CHESS-08..10, FR-OTHELLO-07..09, FR-GAMES-06, FR-GAMES-07, US-21, US-22.
const { test, expect } = require('@playwright/test');
const { USERS, loginAs, freshUserId } = require('./helpers');

const SHOTS = '../../test-results/board-game-rewards';

const cssVars = (page, names) => page.evaluate(ns => {
  const s = getComputedStyle(document.documentElement);
  return ns.map(n => s.getPropertyValue(n).trim());
}, names);

test('TC-E2E-094: logged out, Chess and Othello explain the rewards and still work without login', async ({ page }) => {
  for (const game of ['chess', 'othello']) {
    await page.goto(`/games/${game}.html`);
    await expect(page.locator('#rewards')).toContainText('Rewards need a HAKOO login');
    await expect(page.locator(`#rewards a[href*="return=games%2F${game}.html"]`)).toHaveCount(1);
    await expect(page.locator('#auth-area a.auth-login')).toHaveAttribute('href', new RegExp(`return=games%2F${game}\\.html$`));
    await page.click('#go-computer');
    await expect(page.locator('#reward-note')).toContainText('does not count');
  }
});

test('TC-E2E-095: a member who won everything plays Chess and Othello with their own look', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/games/chess.html');
  await expect(page.locator('#rewards li.got')).toHaveCount(3);
  await expect(page.locator('#rewards a[href="../profile.html#chess"]')).toHaveCount(1);
  expect(await cssVars(page, ['--piece-white', '--sq-dark'])).toEqual(['#e2a70f', '#5b2a87']);
  expect(await page.evaluate(() => document.documentElement.classList.contains('p3d'))).toBe(true);
  await page.click('#go-computer');
  await expect(page.locator('#reward-note')).toContainText('counts for rewards');
  await page.check('input[name=color][value=white]');
  await page.click('#start');
  await page.locator('#board .sq[data-r="6"][data-c="4"]').click();
  await page.locator('#board .sq[data-r="4"][data-c="4"]').click();
  await expect(page.locator('#movelist li .mv').nth(1)).not.toBeEmpty();
  await page.screenshot({ path: `${SHOTS}-alice-chess-${info.project.name}.png` });

  await page.goto('/games/othello.html');
  await expect(page.locator('#rewards li.got')).toHaveCount(3);
  expect(await cssVars(page, ['--disc-black', '--ob-bg'])).toEqual(['#0f9a96', '#1f5f8b']);
  expect(await page.evaluate(() => document.documentElement.classList.contains('d3d'))).toBe(true);
  await page.click('#go-computer');
  await page.check('input[name=color][value=black]');
  await page.click('#start');
  await page.locator('#board .osq[data-r="2"][data-c="3"]').click();
  await expect(page.locator('#move-count')).toHaveText('2');
  await page.screenshot({ path: `${SHOTS}-alice-othello-${info.project.name}.png` });
});

test('TC-E2E-096: on the profile, a new member can change the Chess and Othello boards but not the locked rewards', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, freshUserId(), 'Newbie');
  for (const [game, id, colour, d3, board] of [['chess', 'ch', 'pieceWhite', 'pieces3d', 'green'], ['othello', 'ot', 'discBlack', 'discs3d', 'wood']]) {
    await page.goto(`/profile.html#${game}`);                                       // one game's page at a time
    const card = page.locator(`#${game}`);
    await expect(card.locator('.gk-rewards li')).toHaveCount(3);
    await expect(card.locator('.gk-rewards li.got')).toHaveCount(0);
    await expect(card.locator(`input[name=${colour}]`).first()).toBeDisabled();
    await expect(card.locator(`input[name=${d3}]`)).toBeDisabled();
    await card.locator(`input[name=board][value=${board}]`).check({ force: true });
    await card.locator('button[type=submit]').click();
    await expect(card.locator(`#${id}-msg`)).toHaveText('Saved ✓');
  }
  await page.reload();
  await expect(page.locator('#chess input[name=board][value=green]')).toBeChecked();
  await expect(page.locator('#othello input[name=board][value=wood]')).toBeChecked();
  await expect(page.locator('#gomoku input[name=board][value=wood]')).toBeChecked();   // Gomoku untouched
  if (info.project.name === 'chromium') {
    await page.locator('#othello').screenshot({ path: `${SHOTS}-profile-new-othello.png` });
    await page.goto('/profile.html#chess');
    await page.locator('#chess').screenshot({ path: `${SHOTS}-profile-new-chess.png` });
  }
});

test('TC-E2E-097: the profile previews show the unlocked looks of a member who won everything', async ({ page, context, baseURL }, info) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/profile.html#chess');
  await expect(page.locator('#chess .gk-rewards li.got')).toHaveCount(3);
  await expect(page.locator('#chess input[name=pieceWhite][value=gold]')).toBeChecked();
  await expect(page.locator('#ch-preview')).toHaveClass(/p3d/);
  await expect(page.locator('#ch-preview .pc.pw')).toHaveCount(14);
  await expect(page.locator('#othello input[name=discBlack][value=teal]')).toBeChecked();
  await expect(page.locator('#ot-preview')).toHaveClass(/d3d/);
  if (info.project.name === 'chromium') {
    await page.locator('#chess').screenshot({ path: `${SHOTS}-profile-alice-chess.png` });
    await page.goto('/profile.html#othello');
    await page.locator('#othello').screenshot({ path: `${SHOTS}-profile-alice-othello.png` });
  }
});

test('TC-E2E-098: the crown says which games were won on Hard', async ({ page, context, baseURL }) => {
  await loginAs(context, baseURL, USERS.alice, 'Alice');
  await page.goto('/community.html');
  await expect(page.locator('#auth-area .crown-wrap.crowned')).toHaveAttribute('title', "Beat the HAKOO computer on Hard: Gomoku, Chess, Othello, Hnefatafl, Stratego, Backgammon, Checkers, Nine Men's Morris, Nonogram, Sudoku, Owldoku");
  await page.goto('/games/chess.html');
  await expect(page.locator('#auth-area .crown-wrap.crowned img.auth-av')).toHaveCount(1);
});

test("TC-E2E-099: HAKOO Games lists Chess, Othello, Hnefatafl, Stratego, Backgammon, Checkers and Nine Men's Morris next to Gomoku", async ({ page }) => {
  await page.goto('/hakoo-games.html#all');                         // every game; the first shelf shows only the 6 most played
  for (const game of ['gomoku', 'chess', 'othello', 'hnefatafl', 'stratego', 'backgammon', 'checkers', 'morris']) {
    await expect(page.locator(`#game-grid a.game-card[href="games/${game}.html"]`)).toHaveCount(1);
  }
  await page.click('a.game-card[href="games/chess.html"]');
  await expect(page).toHaveTitle(/Chess — HAKOO Games/);
});
