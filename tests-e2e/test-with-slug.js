const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 500 });
  const page = await browser.newPage();

  try {
    // Login
    console.log('🔐 Logging in...');
    await page.goto('http://localhost:8000');
    await page.getByRole('link', { name: 'Log in' }).click();
    await page.fill('input[type="email"]', 'admin@haasib.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    console.log('   ✅ Logged in');

    // Go to companies and switch
    console.log('\n🏢 Going to companies page...');
    await page.goto('http://localhost:8000/companies');
    await page.waitForLoadState('networkidle');

    // Look for any clickable element with "Switch" or company name
    console.log('\n🔄 Looking for company switch/link...');

    // Try clicking on the company card/name directly
    const companyCard = page.locator('text=Naveed Filling Station').or(
      page.locator('[href*="naveed-filling-station"]')
    );

    const count = await companyCard.count();
    console.log(`   Found ${count} company references`);

    if (count > 0) {
      await companyCard.first().click();
      await page.waitForLoadState('networkidle');
      console.log(`   ✅ Clicked company. URL: ${page.url()}`);
    }

    // Save session state
    console.log('\n💾 Saving session state...');
    await page.context().storageState({ path: '/home/banna/projects/Haasib/tests-e2e/auth-state.json' });
    console.log('   ✅ Session saved to auth-state.json');

    // Now test with SLUG instead of UUID
    console.log('\n💰 Testing with company slug...');
    const slug = 'naveed-filling-station';

    const response = await page.goto(`http://localhost:8000/${slug}/bills/create`, { waitUntil: 'networkidle' });

    console.log(`   Status: ${response.status()}`);
    console.log(`   Title: ${await page.title()}`);
    console.log(`   URL: ${page.url()}`);

    if (response.status() === 200) {
      console.log('\n   ✅✅✅ SUCCESS! Bills page works with slug!');
      await page.screenshot({ path: '/home/banna/projects/Haasib/tests-e2e/screenshots/bills-success-with-slug.png', fullPage: true });

      // Check for vendor dropdown
      const vendorSelect = page.locator('select');
      const vendorCount = await vendorSelect.count();
      console.log(`   Found ${vendorCount} select elements`);

      if (vendorCount > 0) {
        console.log('   ✅ Form elements found! Ready to test bill creation.');
      }
    } else {
      console.log(`\n   ❌ Failed with status ${response.status()}`);
    }

    console.log('\n⏸️  Keeping browser open for 20 seconds...');
    await page.waitForTimeout(20000);

  } catch (error) {
    console.error('\n❌ Error:', error.message);
    await page.screenshot({ path: '/home/banna/projects/Haasib/tests-e2e/screenshots/error.png' });
  } finally {
    await browser.close();
  }
})();
