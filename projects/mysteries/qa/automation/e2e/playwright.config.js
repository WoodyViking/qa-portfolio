// End-to-end tests (system level, black-box) for Mysteries.
// BASE_URL is http://web inside Docker; set it to http://localhost:8090 to run from the host.
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
    baseURL: process.env.BASE_URL || 'http://localhost:8090',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  // NFR-01: phone, tablet, computer and TV (a TV browser is a large screen, 1920x1080).
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox',  use: { ...devices['Desktop Firefox'] } },
    { name: 'phone',    use: { ...devices['Pixel 7'] } },
    { name: 'tablet',   use: { ...devices['iPad (gen 7)'], browserName: 'chromium' } },
    { name: 'tv',       use: { ...devices['Desktop Chrome'], viewport: { width: 1920, height: 1080 } } },
  ],
});
