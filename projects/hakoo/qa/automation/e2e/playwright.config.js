// End-to-end tests (system level, black-box) for hakoo.eu.
// BASE_URL is http://web inside Docker; set it to http://localhost:8080 to run from the host.
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',
  timeout: 30_000,
  outputDir: '../../test-results/e2e-artifacts',   // traces/screenshots (not committed)
  retries: process.env.CI ? 1 : 0,
  reporter: [
    ['list'],
    ['junit', { outputFile: '../../test-results/e2e-junit.xml' }],
    ['html', { outputFolder: '../../test-results/e2e-report', open: 'never' }],
  ],
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8080',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    // Less motion: moves are then not animated unless a test picks a move speed (FR-GAMES-11),
    // so the other tests see each position at once. tests/othello-flip.spec.js tests the animation.
    contextOptions: { reducedMotion: 'reduce' },
  },
  // NFR-COMP-01: the site must work in Chromium and Firefox, desktop and mobile.
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox',  use: { ...devices['Desktop Firefox'] } },
    { name: 'mobile',   use: { ...devices['Pixel 7'] } },
  ],
});
