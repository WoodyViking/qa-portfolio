// Runborg (games/runborg.html, js/games/runborg.js, ../runborg.php): the game on the server, five opponents,
// a saga per board, and the titles on the profile. playwright.config.js asks for less motion; the Motion
// switch is turned off too, so every step shows at once.
// Fixture: Bob has beaten the Farmer, the Viking and the Shieldmaiden (docker/db/init/90-test-data.sql).
// Traceability: FR-RUNBORG-05..08, FR-GAMES-14.
const { test, expect } = require('@playwright/test');
const { loginAs } = require('./helpers');

const BOB = '100000000000000002';
const SHOTS = '../../test-results/runborg';

test('TC-E2E-211: the old address leads to Runborg; a card played, the opponent answers from the server; another opponent or board is a new saga', async ({ page }, info) => {
  await page.goto('/hakoo-games.html#cards');
  await expect(page.locator('#game-grid a[href="games/runborg.html"]')).toBeVisible();
  await page.goto('/games/runborg-lab.html');                      // an old link from Discord
  await expect(page).toHaveURL(/\/games\/runborg\.html#g=[0-9a-f]{16}&p=/);
  await page.uncheck('#rb-anim');
  await expect(page.locator('#rb-say')).toContainText('against the Farmer');
  await expect(page.locator('#rb-them-name')).toContainText('The Farmer (1 of 5)');
  await expect(page.locator('#rb-hand .rbc')).toHaveCount(6);
  const url = page.url();

  // Throw away the first card (always allowed): the Farmer's turn comes back from the server.
  await page.locator('#rb-hand .rbc').first().click({ button: 'right' });
  await expect(page.locator('#rb-say')).toHaveText(/Your turn\.|wins/);
  await expect(page.locator('#rb-last small')).toContainText(/The Farmer|You/);
  await expect(page.locator('#rb-hand .rbc')).toHaveCount(6);
  await page.reload();                                             // the address keeps the game
  await expect(page).toHaveURL(url);
  await expect(page.locator('#rb-hand .rbc')).toHaveCount(6);

  await page.locator('#rb-foe').selectOption('king');
  await expect(page.locator('#rb-say')).toContainText('against the King');
  await expect(page.locator('#rb-goal')).toContainText('The King has more: warriors +4 · palisade +10 · hall +8');
  await page.locator('#rb-board').selectOption('winter');
  await expect(page.locator('#rb-say')).toContainText('A saga on the Winter board against the King: your hall to 40 and your palisade to 20');
  await expect(page).not.toHaveURL(url);                           // a new game
  await expect(page.locator('#rb-goal')).toContainText('Log in with Discord to keep your wins');
  await page.screenshot({ path: `${SHOTS}-${info.project.name}.png`, fullPage: true });
  expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
});

test('TC-E2E-212: a member chooses a title they have won on the profile; it shows by their name, and the locked ones cannot be chosen', async ({ page, context, baseURL }, info) => {
  test.skip(info.project.name !== 'chromium', 'one browser: the title is Bob\'s, shared by the browsers running at the same time');
  await loginAs(context, baseURL, BOB, 'Bob');
  await page.goto('/profile.html#runborg');
  const titles = page.locator('#runborg .rb-titles');
  await expect(titles.locator('input:enabled')).toHaveCount(4);    // None, Farmer, Viking, Shieldmaiden
  await expect(titles.locator('input[value=jarl]')).toBeDisabled();
  await expect(page.locator('#runborg .rb-wins td.won')).toHaveCount(3);
  await titles.locator('input[value=viking]').check();
  await expect(page.locator('#rb-title-msg')).toHaveText('Saved ✓');
  await page.reload();
  await expect(page.locator('#auth-area .auth-title')).toHaveText('Viking');           // before the name
  await expect(page.locator('#auth-area .auth-name')).toHaveText('Viking Bob');
  await page.goto('/games/runborg.html');
  await expect(page.locator('#rb-me-name')).toHaveText('⚔️ Viking Bob');
  await expect(page.locator('#rb-foe option[value=viking]')).toHaveText('⚔️ Viking ✓');
  await expect(page.locator('#rb-goal')).toContainText('Your wins on Fjord');
  await page.goto('/profile.html#runborg');
  await page.locator('#runborg .rb-titles input[value=""]').check();          // back to none
  await expect(page.locator('#rb-title-msg')).toHaveText('Saved ✓');
});
