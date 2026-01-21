const { chromium } = require('playwright');

const BASE_URL = 'http://localhost:8000';
const LOGIN_EMAIL = 'admin@haasib.com';
const LOGIN_PASSWORD = 'password';
const COMPANY_SLUG = '019b735a-c83c-709a-9194-905845772573';

// Error tracking
const errors = [];

function logError(step, error, url) {
  const errorEntry = {
    step,
    message: error.message,
    url,
    timestamp: new Date().toISOString()
  };
  errors.push(errorEntry);
  console.log(`\n❌ ERROR at step: ${step}`);
  console.log(`   Message: ${error.message}`);
  console.log(`   URL: ${url}\n`);
}

function logSuccess(step, details) {
  console.log(`\n✅ ${step}`);
  if (details) console.log(`   ${details}`);
}

(async () => {
  const browser = await chromium.launch({
    headless: false,
    slowMo: 1000
  });

  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 }
  });

  const page = await context.newPage();

  try {
    // ===== STEP 1: Login =====
    logSuccess('STEP 1: Navigating to login page');
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');

    const loginButton = page.getByRole('link', { name: 'Log in' });
    if (await loginButton.isVisible()) {
      await loginButton.click();
      await page.waitForLoadState('networkidle');
    }

    logSuccess('STEP 2: Filling login form');
    await page.fill('input[name="email"], input[type="email"]', LOGIN_EMAIL);
    await page.fill('input[name="password"], input[type="password"]', LOGIN_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    logSuccess('STEP 3: Login successful', `Current URL: ${page.url()}`);

    // ===== STEP 4: Select Company =====
    logSuccess('STEP 4: Selecting company');
    const currentUrl = page.url();
    if (currentUrl.includes('/companies')) {
      const companyLink = page.locator(`a[href*="${COMPANY_SLUG}"]`).first();
      await companyLink.click();
      await page.waitForLoadState('networkidle');
    }

    await page.waitForURL(`**/dashboard`, { timeout: 10000 });
    logSuccess('STEP 5: Company selected, on dashboard', `URL: ${page.url()}`);

    // ===== STEP 6: Navigate to Bills =====
    logSuccess('STEP 6: Navigating to Bills page');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/bills/create`);
    await page.waitForLoadState('networkidle', { timeout: 15000 });
    console.log(`   Page title: ${await page.title()}`);
    console.log(`   Current URL: ${page.url()}`);

    // Wait a bit to see the page
    await page.waitForTimeout(3000);

    // ===== STEP 7: Try to create a bill =====
    logSuccess('STEP 7: Attempting to create bill');

    // Take screenshot before attempting
    await page.screenshot({ path: 'tests-e2e/screenshots/step7-bill-page.png' });
    console.log('   📸 Screenshot saved: tests-e2e/screenshots/step7-bill-page.png');

    // Try to find vendor dropdown
    try {
      const vendorSelect = page.locator('select[name*="vendor"], select[id*="vendor"]').first();
      if (await vendorSelect.isVisible({ timeout: 5000 })) {
        logSuccess('Vendor dropdown found');

        // Try to select Parco LTD
        await vendorSelect.selectOption({ label: 'Parco LTD' });
        logSuccess('Vendor selected: Parco LTD');
        await page.waitForTimeout(1000);
      } else {
        logError('STEP 7', new Error('Vendor dropdown not found'), page.url());
      }
    } catch (error) {
      logError('STEP 7', error, page.url());
    }

    // Continue with next steps...
    await page.waitForTimeout(5000);

  } catch (error) {
    logError('GENERAL', error, page.url());
  }

  // Save error report
  if (errors.length > 0) {
    const fs = require('fs');
    fs.writeFileSync('tests-e2e/error-report.json', JSON.stringify(errors, null, 2));
    console.log('\n📄 Error report saved: tests-e2e/error-report.json');
    console.log(`\n📊 Total errors encountered: ${errors.length}\n`);
  }

  console.log('\n⏸️  Browser will stay open for 30 seconds for you to inspect...');
  console.log('🔍 Check the screenshots in tests-e2e/screenshots/\n');

  await page.waitForTimeout(30000);

  await browser.close();
})();
