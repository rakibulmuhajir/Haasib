const { chromium } = require('playwright');

async function testLoginAndCompanies() {
  console.log('🚀 Testing login and companies directly...');

  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    // Step 1: Go to login page
    console.log('📝 Navigating to login page...');
    await page.goto('http://localhost:8000/login');
    await page.waitForLoadState('networkidle');

    // Step 2: Find and fill the login form
    console.log('🔍 Looking for login form...');

    // Try different possible selectors for username field
    const usernameSelectors = [
      'input[name="username"]',
      'input[name="email"]',
      'input[type="email"]',
      'input[type="text"]',
      'input[id*="email"]',
      'input[id*="username"]',
      'input[placeholder*="email" i]',
      'input[placeholder*="username" i]'
    ];

    let usernameField = null;
    for (const selector of usernameSelectors) {
      const element = page.locator(selector).first();
      if (await element.isVisible()) {
        usernameField = element;
        console.log(`✅ Found username field: ${selector}`);
        break;
      }
    }

    if (!usernameField) {
      console.log('❌ Username field not found, checking page content...');
      const pageContent = await page.content();
      console.log('Page has', pageContent.length, 'characters');

      // Look for any input fields
      const allInputs = await page.locator('input').all();
      console.log(`Found ${allInputs.length} input fields`);

      for (let i = 0; i < allInputs.length; i++) {
        const input = allInputs[i];
        const type = await input.getAttribute('type');
        const name = await input.getAttribute('name');
        const placeholder = await input.getAttribute('placeholder');
        console.log(`  Input ${i}: type=${type}, name=${name}, placeholder=${placeholder}`);
      }
    }

    // Try password field
    const passwordSelectors = [
      'input[name="password"]',
      'input[type="password"]',
      'input[id*="password"]',
      'input[placeholder*="password" i]'
    ];

    let passwordField = null;
    for (const selector of passwordSelectors) {
      const element = page.locator(selector).first();
      if (await element.isVisible()) {
        passwordField = element;
        console.log(`✅ Found password field: ${selector}`);
        break;
      }
    }

    if (usernameField && passwordField) {
      console.log('✅ Both login fields found, filling credentials...');

      await usernameField.fill('admin');
      await passwordField.fill('password');

      // Look for submit button
      const submitSelectors = [
        'button[type="submit"]',
        'input[type="submit"]',
        'button:has-text("Login")',
        'button:has-text("Sign in")',
        'button:has-text("Log in")',
        '.btn-primary'
      ];

      let submitButton = null;
      for (const selector of submitSelectors) {
        const element = page.locator(selector).first();
        if (await element.isVisible()) {
          submitButton = element;
          console.log(`✅ Found submit button: ${selector}`);
          break;
        }
      }

      if (submitButton) {
        console.log('🚀 Submitting login form...');
        await submitButton.click();

        // Wait for navigation
        console.log('⏳ Waiting for login to complete...');
        try {
          await page.waitForURL('**/dashboard', { timeout: 10000 });
          console.log('✅ Login successful! Redirected to dashboard');

          // Now test companies page
          console.log('🏢 Testing companies page...');
          await page.goto('http://localhost:8000/companies');
          await page.waitForLoadState('networkidle');

          const currentUrl = page.url();
          console.log(`📍 Current URL: ${currentUrl}`);

          // Check if we can access companies
          if (currentUrl.includes('/companies')) {
            console.log('✅ Companies page accessible!');

            // Look for company-related content
            const pageContent = await page.content();

            // Check for specific company elements
            const companySelectors = [
              'h1:has-text("Companies")',
              'h2:has-text("Companies")',
              '.companies',
              'table',
              '.btn-primary'
            ];

            for (const selector of companySelectors) {
              const element = page.locator(selector).first();
              if (await element.isVisible()) {
                const text = await element.textContent();
                console.log(`✅ Found company element: ${selector} - ${text?.substring(0, 50)}...`);
              }
            }

            // Take screenshot
            await page.screenshot({
              path: 'test-results/companies-success.png',
              fullPage: true
            });
            console.log('📸 Screenshot saved to test-results/companies-success.png');

          } else {
            console.log('❌ Still redirected away from companies page');
          }

        } catch (error) {
          console.log('⚠️ Login redirect timeout, checking current URL...');
          const currentUrl = page.url();
          console.log(`Current URL: ${currentUrl}`);

          if (currentUrl.includes('/login')) {
            console.log('❌ Still on login page - login failed');
          } else {
            console.log('✅ Logged in but not redirected to dashboard');
          }
        }
      } else {
        console.log('❌ Submit button not found');
      }
    } else {
      console.log('❌ Login form fields not found properly');
      console.log(`Username field found: ${usernameField ? 'Yes' : 'No'}`);
      console.log(`Password field found: ${passwordField ? 'Yes' : 'No'}`);
    }

  } catch (error) {
    console.error(`❌ Error: ${error.message}`);
  } finally {
    await browser.close();
    console.log('🏁 Test complete!');
  }
}

testLoginAndCompanies().catch(console.error);