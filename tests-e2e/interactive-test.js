const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({
    headless: false,
    slowMo: 500 // Slow down actions for visibility
  });

  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 }
  });

  const page = await context.newPage();

  console.log('🌐 Opening browser to Haasib application...');
  console.log('📍 URL: http://localhost:8000');

  await page.goto('http://localhost:8000');
  await page.waitForLoadState('networkidle');

  console.log('✅ Browser opened successfully!');
  console.log('🔑 Please login with: admin@haasib.com / password');
  console.log('📋 Navigate through the fuel station workflow manually');
  console.log('⏸️  Press Ctrl+C to close the browser when done');

  // Keep browser open
  await new Promise(() => {});
})();
