const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();

  // Login first
  await page.goto('http://localhost:8000');
  await page.getByRole('link', { name: 'Log in' }).click();
  await page.fill('input[type="email"]', 'admin@haasib.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  // Try different URL formats to see which one works
  const companyId = '019b735a-c83c-709a-9194-905845772573';

  const urls = [
    `http://localhost:8000/${companyId}/bills`,
    `http://localhost:8000/${companyId}/bills/create`,
    `http://localhost:8000/dashboard`,
  ];

  for (const url of urls) {
    console.log(`\n🔍 Testing: ${url}`);
    const response = await page.goto(url, { waitUntil: 'networkidle' });

    console.log(`   Status: ${response.status()}`);
    console.log(`   Title: ${await page.title()}`);
    console.log(`   URL: ${page.url()}`);

    if (response.status() === 404) {
      console.log(`   ❌ 404 Not Found`);
    } else {
      console.log(`   ✅ Works!`);
      await page.screenshot({ path: `tests-e2e/screenshots/test-${url.split('/').pop()}.png` });
    }

    await page.waitForTimeout(2000);
  }

  console.log('\n⏸️  Waiting 10 seconds...');
  await page.waitForTimeout(10000);
  await browser.close();
})();
