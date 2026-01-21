const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({
    headless: false,
    slowMo: 2000 // Slow down to see what's happening
  });

  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 }
  });

  const page = await context.newPage();

  try {
    console.log('🌐 Starting manual navigation test...\n');

    // Step 1: Login
    console.log('Step 1: Login to application');
    await page.goto('http://localhost:8000');
    await page.waitForLoadState('networkidle');

    await page.getByRole('link', { name: 'Log in' }).click();
    await page.waitForLoadState('networkidle');

    await page.fill('input[type="email"]', 'admin@haasib.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    console.log(`   Current URL: ${page.url()}`);
    console.log(`   Page Title: ${await page.title()}\n`);

    await page.waitForTimeout(3000);

    // Step 2: Go to companies page
    console.log('Step 2: Navigate to Companies page');
    await page.goto('http://localhost:8000/companies');
    await page.waitForLoadState('networkidle');

    console.log(`   Current URL: ${page.url()}`);
    console.log(`   Page Title: ${await page.title()}`);

    // Look for any links or buttons
    const links = await page.locator('a').all();
    console.log(`   Found ${links.length} links on page`);

    // Get all text content to see what's available
    const pageText = await page.evaluate(() => document.body.innerText);
    console.log(`   Page text preview: ${pageText.substring(0, 300)}...\n`);

    await page.screenshot({ path: '/home/banna/projects/Haasib/tests-e2e/screenshots/companies-page-full.png', fullPage: true });
    console.log('   📸 Screenshot saved\n');

    await page.waitForTimeout(5000);

    // Step 3: Try to find and click company card
    console.log('Step 3: Looking for company card or switch button');

    // Try different selectors
    const selectors = [
      'a[href*="naveed"]',
      'a[href*="filling"]',
      'button:has-text("Switch")',
      'button:has-text("Enter")',
      'a:has-text("Switch")',
      'a:has-text("Enter")',
      '[data-testid="company-card"]',
      '.company-card',
    ];

    for (const selector of selectors) {
      const element = page.locator(selector);
      const count = await element.count();
      if (count > 0) {
        console.log(`   ✅ Found ${count} elements with selector: ${selector}`);

        // Get the href if it's a link
        const href = await element.first().getAttribute('href');
        if (href) {
          console.log(`   Href: ${href}`);
        }
      }
    }

    // Step 4: Check if we can access dashboard directly
    console.log('\nStep 4: Try accessing company dashboard directly');
    const slug = 'naveed-filling-station';

    await page.goto(`http://localhost:8000/${slug}/dashboard`);
    await page.waitForLoadState('networkidle');

    console.log(`   Current URL: ${page.url()}`);
    console.log(`   Page Title: ${await page.title()}`);
    console.log(`   Status: ${page.url().includes('login') ? '❌ Redirected to login' : '✅ Access granted'}`);

    await page.screenshot({ path: '/home/banna/projects/Haasib/tests-e2e/screenshots/dashboard-direct.png', fullPage: true });
    console.log('   📸 Screenshot saved\n');

    await page.waitForTimeout(5000);

    // Step 5: Check what navigation items are available
    console.log('Step 5: Checking navigation menu');

    const navItems = await page.locator('nav a, nav button, sidebar a, [role="navigation"] a').all();
    console.log(`   Found ${navItems.length} navigation items`);

    for (let i = 0; i < Math.min(navItems.length, 20); i++) {
      try {
        const text = await navItems[i].innerText();
        const href = await navItems[i].getAttribute('href');
        console.log(`   ${i + 1}. ${text ? text.trim() : '(no text)'} -> ${href || '(no href)'}`);
      } catch (e) {
        // Skip error
      }
    }

    console.log('\n⏸️  Browser will stay open for 30 seconds for manual inspection');
    console.log('   Please navigate manually to see what works\n');

    await page.waitForTimeout(30000);

  } catch (error) {
    console.error('\n❌ Error:', error.message);
    await page.screenshot({ path: '/home/banna/projects/Haasib/tests-e2e/screenshots/error.png' });
  } finally {
    await browser.close();
  }
})();
