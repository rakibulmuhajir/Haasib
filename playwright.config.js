import { defineConfig, devices } from '@playwright/test';

const traceMode = process.env.PW_TRACE || 'retain-on-failure';
const videoMode = process.env.PW_VIDEO || 'retain-on-failure';

export default defineConfig({
  testDir: './tests-e2e',
  fullyParallel: false, // Run tests sequentially for fuel station workflow
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1, // Retry on failure
  workers: 1, // Single worker to avoid conflicts
  reporter: [
    ['html', { outputFolder: 'tests-e2e/report' }],
    ['json', { outputFile: 'tests-e2e/results.json' }],
    ['list']
  ],

  use: {
    baseURL: 'http://localhost:8000',
    trace: traceMode,
    screenshot: 'only-on-failure',
    video: videoMode,
    headless: process.env.PW_HEADLESS === '1' ? true : false, // Default headed; set PW_HEADLESS=1 for CI/sandbox
    chromiumSandbox: false,
    launchOptions: {
      args: ['--disable-setuid-sandbox'],
    },
    actionTimeout: 30000, // 30 seconds
    navigationTimeout: 30000,
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  // Run local dev server before starting tests
  // webServer: {
  //   command: 'php artisan serve --host=127.0.0.1 --port=8000',
  //   url: 'http://localhost:8000',
  //   reuseExistingServer: !process.env.CI,
  //   timeout: 120 * 1000,
  // },
});
