const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 1000 });
  const page = await browser.newPage();

  try {
    console.log('🔄 Testing company switch flow...\n');

    // Login
    await page.goto('http://localhost:8000');
    await page.getByRole('link', { name: 'Log in' }).click();
    await page.fill('input[type="email"]', 'admin@haasib.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    console.log('Step 1: ✅ Logged in');

    // Go to companies
    await page.goto('http://localhost:8000/companies');
    await page.waitForLoadState('networkidle');

    console.log('Step 2: ✅ On companies page');

    // Click the Switch button
    console.log('\nStep 3: Clicking Switch button...');

    const switchButton = page.locator('button:has-text("Switch")');
    await switchButton.click();

    // Wait for navigation
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    console.log(`   After clicking Switch:`);
    console.log(`   URL: ${page.url()}`);
    console.log(`   Title: ${await page.title()}`);

    // Save session state AFTER switch
    console.log('\nStep 4: Saving session state...');
    await page.context().storageState({ path: '/home/banna/projects/Haasib/tests-e2e/auth-state-switched.json' });
    console.log('   ✅ Session saved');

    // Now try to access bills
    console.log('\nStep 5: Testing access to bills...');

    const response = await page.goto('http://localhost:8000/naveed-filling-station/bills/create', {
      waitUntil: 'networkidle'
    });

    console.log(`   Status: ${response.status()}`);
    console.log(`   Title: ${await page.title()}`);
    console.log(`   URL: ${page.url()}`);

    if (response.status() === 200 && !page.url().includes('login')) {
      console.log('\n   🎉 SUCCESS! Bills page is accessible!');

      // Check for form elements
      const vendorSelect = page.locator('select[name*="vendor"], select[id*="vendor"]');
      const vendorCount = await vendorSelect.count();
      console.log(`   Vendor dropdowns: ${vendorCount}`);

      await page.screenshot({ path: '/home/banna/projects/Haasib/tests-e2e/screenshots/bills-success-after-switch.png', fullPage: true });
    } else {
      console.log('\n   ❌ Still blocked or redirected');
      await page.screenshot({ path: '/home/banna/projects/Haasib/tests-e2e/screenshots/bills-failed-after-switch.png', fullPage: true });
    }

    console.log('\n⏸️  Waiting 20 seconds...');
    await page.waitForTimeout(20000);

  } catch (error) {
    console.error('\n❌ Error:', error.message);
    await page.screenshot({ path: '/home/banna/projects/Haasib/tests-e2e/screenshots/error.png' });
  } finally {
    await browser.close();
  }
})();
