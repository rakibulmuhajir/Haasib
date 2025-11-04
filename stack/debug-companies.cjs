const { chromium } = require('playwright');

async function debugCompanies() {
  console.log('🚀 Starting companies debugging with Playwright...');

  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();

  // Listen for console errors
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      console.log(`❌ Console Error: ${msg.text()}`);
    }
  });

  // Listen for JavaScript errors
  page.on('pageerror', (error) => {
    console.log(`❌ JavaScript Error: ${error.message}`);
  });

  try {
    // Step 1: Test login
    console.log('\n📝 Step 1: Testing login...');
    await page.goto('http://localhost:8000/login');
    await page.waitForLoadState('networkidle');

    // Check if login form exists
    const emailField = await page.locator('input[name="email"]').first();
    const passwordField = await page.locator('input[name="password"]').first();

    if (await emailField.isVisible() && await passwordField.isVisible()) {
      console.log('✅ Login form found');

      // Try to login
      await emailField.fill('admin@example.com');
      await passwordField.fill('password');

      const submitButton = await page.locator('button[type="submit"]').first();
      if (await submitButton.isVisible()) {
        await submitButton.click();
        console.log('✅ Login form submitted');

        // Wait for navigation
        try {
          await page.waitForURL('**/dashboard', { timeout: 10000 });
          console.log('✅ Login successful - redirected to dashboard');
        } catch (error) {
          console.log('⚠️ Login redirect failed - checking current page...');
          const currentUrl = page.url();
          console.log(`   Current URL: ${currentUrl}`);
        }
      } else {
        console.log('❌ Submit button not found');
      }
    } else {
      console.log('❌ Login form not found');
    }

    // Step 2: Test companies page
    console.log('\n🏢 Step 2: Testing companies page...');
    await page.goto('http://localhost:8000/companies', { waitUntil: 'networkidle' });

    const currentUrl = page.url();
    console.log(`   Current URL: ${currentUrl}`);

    // Check page title
    const pageTitle = await page.title();
    console.log(`   Page title: ${pageTitle}`);

    // Look for companies content
    const contentSelectors = [
      'h1', 'h2', '.page-title', '[data-testid="page-title"]',
      'table', '.companies-list', '[data-testid="companies-list"]',
      '.empty-state', '.no-data', '[data-testid="empty-state"]'
    ];

    let foundContent = false;
    for (const selector of contentSelectors) {
      const element = page.locator(selector).first();
      if (await element.isVisible()) {
        const text = await element.textContent();
        console.log(`✅ Found content with selector ${selector}: ${text?.substring(0, 100)}...`);
        foundContent = true;
        break;
      }
    }

    if (!foundContent) {
      console.log('⚠️ No obvious content found - checking page source...');
      const bodyText = await page.locator('body').textContent();
      if (bodyText && bodyText.length > 100) {
        console.log(`✅ Page has content: ${bodyText.substring(0, 200)}...`);
      } else {
        console.log('❌ Page appears to be empty or not loaded properly');
      }
    }

    // Step 3: Test navigation
    console.log('\n🧭 Step 3: Testing navigation...');
    const navSelectors = [
      'nav', '.navigation', '.sidebar', '.menu',
      'a[href="/companies"]', 'a:has-text("Companies")'
    ];

    for (const selector of navSelectors) {
      const element = page.locator(selector).first();
      if (await element.isVisible()) {
        console.log(`✅ Navigation element found: ${selector}`);
        break;
      }
    }

    // Step 4: Take screenshot for manual inspection
    console.log('\n📸 Step 4: Taking screenshot...');
    await page.screenshot({
      path: 'test-results/companies-debug-screenshot.png',
      fullPage: true
    });
    console.log('✅ Screenshot saved to test-results/companies-debug-screenshot.png');

    // Step 5: Test some common company-related URLs
    console.log('\n🔍 Step 5: Testing related URLs...');
    const testUrls = [
      '/customers',
      '/dashboard',
      '/settings'
    ];

    for (const testUrl of testUrls) {
      try {
        const response = await page.goto(`http://localhost:8000${testUrl}`, { waitUntil: 'networkidle' });
        console.log(`✅ ${testUrl}: ${response?.status()} - ${page.url()}`);
      } catch (error) {
        console.log(`❌ ${testUrl}: Error - ${error.message}`);
      }
    }

  } catch (error) {
    console.error(`❌ Error during debugging: ${error.message}`);
  } finally {
    await browser.close();
    console.log('\n🏁 Debugging complete!');
  }
}

debugCompanies().catch(console.error);