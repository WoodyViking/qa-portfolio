// The murder mystery "The Last Toast" in real browsers: Bob hosts and plays, Erik plays on a phone, a TV shows the
// plan in Swedish. The culprit and the order of the clues are drawn at random, so the tests let whoever has the turn act.
// Traceability: US-17, K-55, K-57..K-62, K-64..K-66, K-69, K-28, K-29, NFR-02, AK-44, AK-46, AK-47..AK-51, AK-54, AK-57.
const { test, expect } = require('@playwright/test');
const { USERS, device } = require('./helpers');

const TV = { width: 1920, height: 1080 }, PHONE = { width: 390, height: 844 };

/** Bob starts "The Last Toast" with the board in Swedish and lets a TV and Erik in. */
async function table(browser, baseURL) {
  const host = await device(browser, baseURL, USERS.bob);
  await host.page.goto('/mysteries/');
  await host.page.locator('select[name=category]').selectOption('mystery:last-toast');
  await host.page.getByLabel('Language on the board').selectOption('sv');
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
  await expect(tv.page.locator('.my-cover h2')).toHaveText('Den sista skålen', { timeout: 10_000 });
  return { host: host.page, tv: tv.page, erik: erik.page, close: () => Promise.all([host.ctx.close(), tv.ctx.close(), erik.ctx.close()]) };
}

/** The phone whose player has the turn: [page, the other page]. */
async function whoseTurn(g) {
  for (let i = 0; i < 20; i++) {
    for (const [p, o] of [[g.host, g.erik], [g.erik, g.host]]) {
      if (await p.locator('.my-head', { hasText: 'Your turn' }).count()) return [p, o];
    }
    await g.host.waitForTimeout(500);
  }
  throw new Error('nobody has the turn');
}

test.describe('Murder mystery', () => {
  test.describe.configure({ timeout: 60_000 });                  // three devices at once: slow browsers need more than 30 s
  /** TC-E2E-011 (K-55, AK-50): a mystery offers only its own lengths */
  test('the lengths of a mystery', async ({ browser, baseURL }) => {
    const host = await device(browser, baseURL, USERS.bob);
    await host.page.goto('/mysteries/');
    const len = host.page.locator('select[name=lengthMin]');
    await host.page.locator('select[name=category]').selectOption('mystery:last-toast');
    await expect(len.locator('option:not([disabled])')).toHaveText(['60 min', '90 min']);
    await host.page.locator('select[name=category]').selectOption('ludo');
    await expect(len.locator('option:not([disabled])')).toHaveCount(6);
    await host.ctx.close();
  });

  /** TC-E2E-012 (K-57..K-59, K-62, K-64, AK-44, AK-46, NFR-02): the case file, a search, a clue shown on the TV, the notebook */
  test('search, show and take notes', async ({ browser, baseURL }) => {
    const g = await table(browser, baseURL);
    await g.host.getByRole('button', { name: 'Start the game' }).click();
    await expect(g.tv.locator('.my-cover .intro')).toContainText('Nyårsafton 1929', { timeout: 10_000 });   // the board in Swedish (K-29)
    await expect(g.erik.locator('.my-file h2')).toHaveText('The Last Toast', { timeout: 10_000 });          // Erik's phone in English
    await expect(g.erik.locator('.my-file .clue')).toHaveCount(5);                                          // the five statements (K-57)

    const [me, other] = await whoseTurn(g);
    await me.getByRole('button', { name: /^Search/ }).click();
    await expect(other.locator('.my-head')).toContainText('Waiting for');
    await me.locator('[data-place="tower"]').click();
    const clue = me.locator('.my-tab .clue').first();
    await expect(clue).toBeVisible({ timeout: 5_000 });                                                      // AK-44: in "My clues"
    await expect(clue).toContainText('Found in: Tower room');
    await expect(g.tv.locator('.my-plan')).toBeVisible({ timeout: 5_000 });                                 // NFR-02: within 5 s
    await expect(g.tv.locator('.my-log li').first()).toContainText('letade i Tornrummet');
    await expect(g.tv.locator('.my-plan .last .pl-n')).toHaveText('7');
    await expect(async () => {                                   // a poll may redraw the tabs under the click: click again
      await other.getByRole('button', { name: /^My clues/ }).click();
      await expect(other.locator('[data-tab="clues"]')).toHaveAttribute('aria-pressed', 'true', { timeout: 1_000 });
    }).toPass({ timeout: 10_000 });
    await expect(other.locator('.my-tab')).toContainText('You have not found any clues yet.');              // K-13

    const title = await clue.locator('h3').textContent();
    await clue.getByRole('button', { name: 'Show to all' }).click();                                        // AK-46
    await expect(me.locator('.my-tab .clue').first()).toContainText('Shown to all', { timeout: 5_000 });
    await expect(g.tv.locator('.my-side .clue')).toBeVisible({ timeout: 5_000 });
    await expect(g.tv.locator('.my-side .clue h3')).not.toHaveText('');
    await other.getByRole('button', { name: /^Case file/ }).click();
    await expect(other.locator('.my-file .clue h3', { hasText: title.trim() })).toBeVisible({ timeout: 5_000 });

    await g.erik.getByRole('button', { name: /^Notes/ }).click();                                           // K-62
    await g.erik.locator('[data-note="oskar"]').click();
    await expect(g.erik.locator('[data-note="oskar"]')).toHaveAttribute('aria-pressed', 'true', { timeout: 5_000 });
    await g.erik.getByRole('combobox', { name: 'Language' }).selectOption('sv');                            // K-28: the clue follows the language
    await g.erik.getByRole('button', { name: /^Fallakten/ }).click();
    await expect(g.erik.locator('.my-file h2')).toHaveText('Den sista skålen', { timeout: 5_000 });
    await g.close();
  });

  /** TC-E2E-013 (K-60, K-61, AK-48, AK-49): wrong accusations, then the solution on every screen */
  test('accuse wrongly, then the solution', async ({ browser, baseURL }) => {
    const g = await table(browser, baseURL);
    await g.host.getByRole('button', { name: 'Start the game' }).click();
    await expect(g.tv.locator('.my-cover .intro')).toBeVisible({ timeout: 10_000 });
    g.host.on('dialog', d => d.accept());
    g.erik.on('dialog', d => d.accept());
    const accuse = async (p, who, what) => {
      await p.getByRole('button', { name: /^Accuse/ }).first().click();
      await p.locator('.my-pick label', { hasText: who }).click();
      await p.locator('.my-pick label', { hasText: what }).click();
      await p.locator('form.my-accuse button.go').click();
    };
    let [me, other] = await whoseTurn(g);
    await accuse(me, 'Oskar Falk', 'Iron poker');
    const result = me.locator('.my-tab p.big');
    await expect(result).toContainText('You accused Oskar Falk, Iron poker', { timeout: 5_000 });
    const right = (await result.textContent()).includes('you were right');
    if (!right) {
      await expect(g.tv.locator('.my-players .out')).toHaveText('anklagade fel', { timeout: 5_000 });     // AK-48: never what
      await expect(g.tv.locator('.my-side')).not.toContainText('Oskar');
      await expect(other.locator('.my-head')).toContainText('Your turn', { timeout: 5_000 });
      await accuse(other, 'Agnes Lind', 'Bronze falcon');
    }
    for (const p of [g.tv, g.host, g.erik]) {                                                                // K-61 (AK-47 or AK-49)
      await expect(p.locator('.my-solution .sol-text p').first()).not.toBeEmpty({ timeout: 10_000 });
    }
    await expect(g.tv.locator('.state')).toHaveText('Spelet är slut');
    await expect(g.tv.locator('.my-solution h3')).toHaveText('Efteråt');
    await g.close();
  });

  /** TC-E2E-014 (K-61): the host ends the game and every screen shows the solution */
  test('the host ends the game', async ({ browser, baseURL }) => {
    const g = await table(browser, baseURL);
    await g.host.getByRole('button', { name: 'Start the game' }).click();
    await expect(g.erik.locator('.my-tabs')).toBeVisible({ timeout: 10_000 });
    await expect(g.erik.getByRole('button', { name: 'End and show the solution' })).toHaveCount(0);
    g.host.once('dialog', d => d.accept());
    await g.host.getByRole('button', { name: 'End and show the solution' }).click();
    for (const p of [g.tv, g.erik]) await expect(p.locator('.my-solution')).toBeVisible({ timeout: 10_000 });
    await expect(g.erik.locator('.my-solution .my-head')).toHaveText('The game is over. This is what happened:');
    await g.close();
  });

  /** TC-E2E-015 (K-65, AK-51): the host picks the rules in the lobby; Erik's phone and the TV show them, only the host can change them */
  test('the host picks the rules in the lobby', async ({ browser, baseURL }) => {
    const g = await table(browser, baseURL);
    await expect(g.host.locator('[data-rule="play"][data-value="against"]')).toHaveAttribute('aria-pressed', 'true');
    await expect(g.erik.locator('.my-lobby-rules')).toContainText('You play against each other', { timeout: 10_000 });
    await g.host.locator('[data-rule="play"][data-value="together"]').click();
    await expect(g.host.locator('[data-rule="ask"][data-value="yes"]')).toBeDisabled();                   // no questions in a team
    await g.host.locator('[data-rule="wrong"][data-value="penalty"]').click();
    await expect(g.erik.locator('.my-lobby-rules')).toContainText('You play together', { timeout: 10_000 });
    await expect(g.erik.locator('.my-lobby-rules')).toContainText('the next 2 turns are lost');
    await expect(g.erik.locator('[data-rule]')).toHaveCount(0);
    await expect(g.tv.locator('.my-cover .my-rules')).toContainText('Ni spelar tillsammans', { timeout: 10_000 });
    await expect(g.tv.locator('.my-cover .my-rules')).toContainText('går de 2 nästa turerna förlorade');
    await g.close();
  });

  /** TC-E2E-016 (K-66, K-20, AK-57): together, what is found is on the TV at once and the team votes on the accusation */
  test('play together and vote', async ({ browser, baseURL }) => {
    const g = await table(browser, baseURL);
    await g.host.locator('[data-rule="play"][data-value="together"]').click();
    await expect(g.tv.locator('.my-cover .my-rules')).toContainText('Ni spelar tillsammans', { timeout: 10_000 });
    await g.host.getByRole('button', { name: 'Start the game' }).click();
    await expect(g.erik.locator('.my-tabs')).toBeVisible({ timeout: 10_000 });
    let [me, other] = await whoseTurn(g);
    await me.getByRole('button', { name: /^Search/ }).click();
    await me.locator('[data-place="tower"]').click();
    await expect(g.tv.locator('.my-side .clue')).toBeVisible({ timeout: 5_000 });                         // shown to all at once
    await expect(me.locator('.my-tab .clue').first()).toContainText('Shown to all');
    [me, other] = [other, me];
    await expect(me.locator('.my-head')).toContainText('Your turn', { timeout: 5_000 });
    me.on('dialog', d => d.accept());
    await me.getByRole('button', { name: /^Accuse/ }).click();
    await me.locator('.my-pick label', { hasText: 'Agnes Lind' }).click();
    await me.locator('.my-pick label', { hasText: 'Bronze falcon' }).click();
    await me.locator('form.my-accuse button.go').click();
    await expect(g.tv.locator('.my-pending')).toContainText('Agnes Lind', { timeout: 5_000 });
    await expect(other.locator('.my-prompt')).toContainText('Agnes Lind, Bronze falcon', { timeout: 5_000 });
    await other.locator('.my-prompt').getByRole('button', { name: 'Yes, accuse' }).click();
    for (const p of [g.tv, g.host, g.erik]) await expect(p.locator('.my-solution')).toBeVisible({ timeout: 10_000 });
    await expect(g.erik.locator('.my-solution .my-head')).toHaveText(/You solved the case together!|Your accusation was wrong/);
    await g.close();
  });

  /** TC-E2E-017 (K-69, K-13, AK-54): a player asks about a place and the answer reaches only their phone */
  test('ask a player', async ({ browser, baseURL }) => {
    const g = await table(browser, baseURL);
    await g.host.getByRole('button', { name: 'Start the game' }).click();
    await expect(g.erik.locator('.my-tabs')).toBeVisible({ timeout: 10_000 });
    let [me, other] = await whoseTurn(g);
    await me.getByRole('button', { name: /^Search/ }).click();
    await me.locator('[data-place="library"]').click();
    const found = await me.locator('.my-tab .clue h3').first().textContent({ timeout: 5_000 });
    await expect(other.locator('.my-head')).toContainText('Your turn', { timeout: 5_000 });
    await other.getByRole('button', { name: /^Search/ }).click();
    const form = other.locator('form.my-ask');
    await form.locator('select[name=place]').selectOption('library');
    await form.getByRole('button', { name: 'Ask' }).click();
    await expect(other.locator('.my-head')).toContainText('Waiting for the answer', { timeout: 5_000 });
    await expect(g.tv.locator('.my-pending')).toContainText('om Biblioteket', { timeout: 5_000 });
    const prompt = me.locator('.my-prompt');
    await expect(prompt).toContainText('asks you about Library', { timeout: 5_000 });
    await prompt.getByRole('button', { name: /^Show to/ }).click();
    await other.getByRole('button', { name: /^My clues/ }).click();
    await expect(other.locator('.my-tab .clue', { hasText: found.trim() })).toContainText('Shown only to you by', { timeout: 5_000 });
    await expect(g.tv.locator('.my-side .clue')).toHaveCount(0);                                          // not on the TV
    await expect(me.locator('.my-head')).toContainText('Your turn', { timeout: 5_000 });                  // the answer ends the turn
    await g.close();
  });
});
