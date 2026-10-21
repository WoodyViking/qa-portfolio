// Runborg between two players (games/runborg.html, ../runborg.php, Runborg::FRIEND): the host picks "A friend",
// sends the link; the friend opens it and the saga starts for both; a card thrown away on one side shows on the
// other side's page. The Motion switch is off, so every step shows at once.
// Traceability: FR-RUNBORG-10..12, FR-RUNBORG-14.
const { test, expect } = require('@playwright/test');

test('TC-E2E-215: two players: an invite link, the same start, turns that show on the other page, giving up, and the end in a box over the field', async ({ browser }, info) => {
  test.setTimeout(60_000);
  const host = await (await browser.newContext()).newPage();
  await host.goto('/games/runborg.html');
  await host.uncheck('#rb-anim');
  await expect(host.locator('#rb-hand .rbc')).toHaveCount(6);
  await host.locator('#rb-foe').selectOption('friend');
  await expect(host.locator('#rb-invite')).toBeVisible();
  await expect(host.locator('#rb-say')).toContainText('Send the link below to a friend');
  await expect(host.locator('#rb-goal')).toContainText('You both start the same');
  const link = await host.locator('#rb-invite-link').inputValue();
  expect(link).toMatch(/runborg\.html#join=[0-9a-f]{16}\.[0-9a-f]{32}$/);

  const guest = await (await browser.newContext()).newPage();
  await guest.goto(link.replace(/^https?:\/\/[^/]+/, ''));
  await guest.uncheck('#rb-anim');
  await expect(guest).toHaveURL(/#g=[0-9a-f]{16}&p=/);
  await expect(guest.locator('#rb-them-name')).toContainText('Player 1');
  await expect(host.locator('#rb-say')).toContainText('took the seat', { timeout: 10_000 });   // the host's page sees it
  await expect(host.locator('#rb-invite')).toBeHidden();

  // Whoever's turn it is throws a card away; it shows on the other page, and then it is theirs.
  const [now, next] = (await host.locator('#rb-say').textContent()).includes('Your turn') ? [host, guest] : [guest, host];
  await expect(next.locator('#rb-say')).toContainText("turn: waiting for their card");
  await now.locator('#rb-hand .rbc').first().click({ button: 'right' });
  await expect(now.locator('#rb-say')).toContainText('waiting for their card');
  await expect(next.locator('#rb-say')).toHaveText('Your turn.', { timeout: 10_000 });
  await expect(next.locator('#rb-last small')).toContainText('threw away');
  await next.screenshot({ path: `../../test-results/runborg-friend-${info.project.name}.png`, fullPage: true });

  next.on('dialog', d => d.accept());
  await next.click('#rb-resign');
  await expect(next.locator('#rb-say')).toContainText('you gave up');
  await expect(now.locator('#rb-say')).toContainText('gave up', { timeout: 10_000 });
  await expect(now.locator('#rb-say')).toContainText('You win');
  // The end in a box over the field on both pages: the loser's says Defeat, the winner's Victory
  await expect(next.locator('#rb-over')).toBeVisible();
  await expect(next.locator('#rb-over-title')).toHaveText('Defeat');
  await expect(now.locator('#rb-over-title')).toHaveText('Victory!');
  await expect(now.locator('#rb-over-text')).toContainText('gave up');
  await now.screenshot({ path: `../../test-results/runborg-over-${info.project.name}.png` });
  await now.click('#rb-over-close');                                                // a look at the table as it was left
  await expect(now.locator('#rb-over')).toBeHidden();
  await next.click('#rb-over-new');                                                 // or a new game straight away
  await expect(next.locator('#rb-over')).toBeHidden();
});
