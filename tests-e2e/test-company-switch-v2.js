const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 500 });
  const page = await browser.newPage();

  try {
    // Step 1: Login
    console.log('🔐 Step 1: Logging in...');
    await page.goto('http://localhost:8000');
    await page.getByRole('link', { name: 'Log in' }).click();
    await page.waitForLoadState('networkidle');

    await page.fill('input[type="email"]', 'admin@haasib.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    console.log(`   ✅ Logged in. URL: ${page.url()}`);

    // Step 2: Go to companies page
    console.log('\n🏢 Step 2: Going to companies page...');
    await page.goto('http://localhost:8000/companies');
    await page.waitForLoadState('networkidle');

    console.log(`   ✅ On companies page`);
    await page.screenshot({ path: '/home/banna/projects/Haasib/tests-e2e/screenshots/companies-page.png', fullPage: true });

    // Step 3: Find the switch button/form for the company
    console.log('\n🔄 Step 3: Looking for company switch form...');

    // Look for form with action containing "switch"
    const switchForm = page.locator('form[action*="switch"]').first();

    const formCount = await switchForm.count();
    console.log(`   Found ${formCount} switch forms`);

    if (formCount > 0) {
      // Submit the form
      await switchForm.evaluate(form => form.submit());
      await page.waitForLoadState('networkidle');

      console.log(`   ✅ Submitted switch form`);
      console.log(`   New URL: ${page.url()}`);

      await page.screenshot({ path: '/home/banna/projects/Haasib/tests-e2e/screenshots/after-switch.png', fullPage: true });
    } else {
      // Try clicking a button with "Switch" text
      console.log('   No form found, trying button click...');

      // Look for button with exact text "Switch"
      const switchButton = page.locator('button:has-text("Switch")').or(
        page.locator('form').filter({ hasText: 'Switch' }).locator('button')
      );

      const buttonCount = await switchButton.count();
      console.log(`   Found ${buttonCount} Switch buttons`);

      if (buttonCount > 0) {
        await switchButton.first().click();
        await page.waitForLoadState('networkidle');
        console.log(`   ✅ Clicked Switch button`);
        console.log(`   New URL: ${page.url()}`);
      }
    }

    // Step 4: Check session/cookies to see if company is set
    console.log('\n🍪 Step 4: Checking session...');

    const cookies = await page.context().cookies();
    console.log(`   Cookies: ${cookies.length}`);

    // Check localStorage
    const localStorage = await page.evaluate(() => {
      return {
        ...Object.assign({}, window.localStorage),
        activeCompanyId: localStorage.getItem('active_company_id'),
      };
    });
    console.log(`   Active Company ID from localStorage: ${localStorage.activeCompanyId}`);

    // Step 5: Now try to access bills
    console.log('\n💰 Step 5: Trying to access bills after switch...');
    const companyId = '019b735a-c83c-709a-9194-905845772573';

    const response = await page.goto(`http://localhost:8000/${companyId}/bills/create`, { waitUntil: 'networkidle' });

    console.log(`   Status: ${response.status()}`);
    console.log(`   Title: ${await page.title()}`);

    if (response.status() === 200) {
      console.log('   ✅ SUCCESS! Bills page is now accessible!');
      await page.screenshot({ path: '/home/banna/projects/Haasib/tests-e2e/screenshots/bills-success.png', fullPage: true });
    } else {
      console.log('   ❌ Still 404. Switch may not have worked properly.');
    }

    console.log('\n⏸️  Browser open for 15 seconds...');
    await page.waitForTimeout(15000);

  } catch (error) {
    console.error('\n❌ Error:', error.message);
    await page.screenshot({ path: '/home/banna/projects/Haasib/tests-e2e/screenshots/error.png' });
  } finally {
    await browser.close();
  }
})();
