const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 1000 });
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

    console.log(`   ✅ Logged in. Current URL: ${page.url()}`);

    // Step 2: Go to companies page
    console.log('\n🏢 Step 2: Navigating to companies page...');
    await page.goto('http://localhost:8000/companies');
    await page.waitForLoadState('networkidle');

    console.log(`   ✅ On companies page: ${page.url()}`);
    await page.screenshot({ path: 'tests-e2e/screenshots/companies-page.png' });
    console.log('   📸 Screenshot saved');

    // Step 3: Find and click "Switch" button on company card
    console.log('\n🔄 Step 3: Looking for Switch button...');
    const switchButton = page.getByRole('button', { name: /switch|enter|select/i })
      .or(page.getByRole('link', { name: /switch|enter|select/i }));

    const switchCount = await switchButton.count();
    console.log(`   Found ${switchCount} switch/select buttons`);

    if (switchCount > 0) {
      // Click the first switch button
      await switchButton.first().click();
      await page.waitForLoadState('networkidle');
      console.log(`   ✅ Clicked switch button. New URL: ${page.url()}`);

      await page.screenshot({ path: 'tests-e2e/screenshots/after-switch.png' });
      console.log('   📸 Screenshot saved');
    } else {
      console.log('   ❌ No switch button found. Let me check the page content...');
      const content = await page.content();
      console.log('   Page contains:', content.substring(0, 500));
    }

    // Step 4: Now try to access bills
    console.log('\n💰 Step 4: Trying to access bills page after switch...');
    const companyId = '019b735a-c83c-709a-9194-905845772573';

    await page.goto(`http://localhost:8000/${companyId}/bills`);
    await page.waitForLoadState('networkidle');

    const response = await page.goto(`http://localhost:8000/${companyId}/bills/create`, { waitUntil: 'networkidle' });

    console.log(`   Status: ${response.status()}`);
    console.log(`   Title: ${await page.title()}`);
    console.log(`   URL: ${page.url()}`);

    if (response.status() === 200) {
      console.log('   ✅ SUCCESS! Bills page is now accessible!');
      await page.screenshot({ path: 'tests-e2e/screenshots/bills-page-success.png' });
      console.log('   📸 Screenshot saved');

      // Check for vendor dropdown
      const vendorSelect = page.locator('select[name*="vendor"], select[id*="vendor"]');
      const vendorCount = await vendorSelect.count();
      console.log(`   Found ${vendorCount} vendor dropdowns`);

      if (vendorCount > 0) {
        console.log('   ✅ Vendor dropdown found! Ready to create bill.');
      }
    } else {
      console.log('   ❌ Still getting error');
    }

    console.log('\n⏸️  Keeping browser open for 10 seconds for inspection...');
    await page.waitForTimeout(10000);

  } catch (error) {
    console.error('\n❌ Error:', error.message);
    await page.screenshot({ path: 'tests-e2e/screenshots/error.png' });
  } finally {
    await browser.close();
  }
})();
