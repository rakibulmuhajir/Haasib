import { test, expect } from '@playwright/test';

test.describe('Frontend Navigation & Functionality Debug', () => {
  let pages: { [key: string]: string } = {};
  let errors: { [key: string]: string[] } = {};

  test.beforeAll(async () => {
    // Define main navigation pages to test
    pages = {
      'Dashboard': '/dashboard',
      'Customers': '/customers',
      'Invoices': '/invoices',
      'Payments': '/payments',
      'Journal Entries': '/journal-entries',
      'Reports': '/reports',
      'Settings': '/settings',
      'Period Close': '/period-close',
      'Bank Reconciliation': '/bank-reconciliation'
    };
  });

  test('Login and test main navigation', async ({ page }) => {
    console.log('🔍 Starting comprehensive frontend debugging...');

    // Step 1: Test Login Page
    console.log('📝 Testing login page...');
    await page.goto('/login');
    await page.waitForLoadState('networkidle');

    // Check for login form elements
    await expect(page.locator('input[name="email"]')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('input[name="password"]')).toBeVisible({ timeout: 5000 });

    // Login with test credentials
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');

    // Wait for login to complete
    await page.waitForURL('**/dashboard', { timeout: 10000 });
    console.log('✅ Login successful');

    // Step 2: Test each main navigation page
    for (const [pageName, url] of Object.entries(pages)) {
      console.log(`🔗 Testing ${pageName} page: ${url}`);

      try {
        await page.goto(url);
        await page.waitForLoadState('networkidle');

        // Check for HTTP errors
        const response = await page.goto(url);
        expect(response?.status()).toBe(200);

        // Check for JavaScript errors
        const jsErrors: string[] = [];
        page.on('pageerror', (error) => {
          jsErrors.push(error.message);
        });

        // Check for console errors
        const consoleErrors: string[] = [];
        page.on('console', (msg) => {
          if (msg.type() === 'error') {
            consoleErrors.push(msg.text());
          }
        });

        // Wait a bit to catch any async errors
        await page.waitForTimeout(2000);

        // Check if page loads without critical errors
        const pageTitle = await page.title();
        console.log(`   📄 Page title: ${pageTitle}`);

        // Check for main content area
        const mainContent = await page.locator('main, .main-content, #app').first();
        await expect(mainContent).toBeVisible({ timeout: 5000 });

        // Look for common error indicators
        const errorSelectors = [
          '.error',
          '.alert-danger',
          '[data-testid="error"]',
          '.exception',
          'pre:has-text("Exception")',
          '.stack-trace'
        ];

        const pageErrors: string[] = [];
        for (const selector of errorSelectors) {
          const errorElements = await page.locator(selector).count();
          if (errorElements > 0) {
            const errorText = await page.locator(selector).first().textContent();
            pageErrors.push(`${selector}: ${errorText}`);
          }
        }

        // Store errors for this page
        const allPageErrors = [...jsErrors, ...consoleErrors, ...pageErrors];
        if (allPageErrors.length > 0) {
          errors[pageName] = allPageErrors;
          console.log(`   ❌ Found ${allPageErrors.length} errors on ${pageName}`);
          allPageErrors.forEach(error => console.log(`      - ${error.substring(0, 100)}...`));
        } else {
          console.log(`   ✅ ${pageName} page loaded successfully`);
        }

        // Take screenshot for visual verification
        await page.screenshot({
          path: `test-results/screenshots/${pageName.toLowerCase().replace(/\s+/g, '-')}.png`,
          fullPage: true
        });

      } catch (error) {
        const errorMsg = `Failed to load ${pageName}: ${error}`;
        console.log(`   ❌ ${errorMsg}`);
        if (!errors[pageName]) errors[pageName] = [];
        errors[pageName].push(errorMsg as string);
      }
    }
  });

  test('Test navigation menu functionality', async ({ page }) => {
    console.log('🧭 Testing navigation menu functionality...');

    // Login first
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    // Look for navigation menu
    const navSelectors = [
      'nav',
      '.navigation',
      '.sidebar',
      '.menu',
      '[data-testid="navigation"]'
    ];

    let navigationFound = false;
    for (const selector of navSelectors) {
      const navElement = await page.locator(selector).first();
      if (await navElement.isVisible()) {
        console.log(`   ✅ Navigation found: ${selector}`);
        navigationFound = true;

        // Test menu items
        const menuItems = await navElement.locator('a, button').all();
        console.log(`   📋 Found ${menuItems.length} menu items`);

        for (let i = 0; i < Math.min(menuItems.length, 10); i++) {
          const item = menuItems[i];
          const text = await item.textContent();
          const href = await item.getAttribute('href');
          console.log(`      - ${text || 'Untitled'} -> ${href || 'no href'}`);
        }
        break;
      }
    }

    if (!navigationFound) {
      console.log('   ❌ No navigation menu found');
      errors['Navigation'] = ['No navigation menu found on the page'];
    }
  });

  test('Test responsive design and mobile view', async ({ page }) => {
    console.log('📱 Testing responsive design...');

    // Login first
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    // Test mobile viewport
    await page.setViewportSize({ width: 375, height: 667 }); // iPhone size
    await page.waitForTimeout(1000);

    // Check if mobile menu appears
    const mobileMenuSelectors = [
      '.mobile-menu-toggle',
      '.hamburger',
      '[data-testid="mobile-menu"]',
      '.menu-toggle'
    ];

    let mobileMenuFound = false;
    for (const selector of mobileMenuSelectors) {
      if (await page.locator(selector).isVisible()) {
        console.log(`   ✅ Mobile menu found: ${selector}`);
        mobileMenuFound = true;

        // Try to click it
        await page.click(selector);
        await page.waitForTimeout(500);

        // Check if menu expands
        const expandedMenu = await page.locator('.mobile-menu, .expanded, .open').first();
        if (await expandedMenu.isVisible()) {
          console.log('   ✅ Mobile menu expands correctly');
        } else {
          console.log('   ⚠️ Mobile menu found but doesn\'t expand');
        }
        break;
      }
    }

    if (!mobileMenuFound) {
      console.log('   ⚠️ No mobile menu found - may use responsive navigation');
    }

    // Test tablet viewport
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.waitForTimeout(1000);
    console.log('   ✅ Tablet viewport tested');

    // Back to desktop
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.waitForTimeout(1000);
    console.log('   ✅ Desktop viewport tested');
  });

  test('Test form interactions and AJAX functionality', async ({ page }) => {
    console.log('📝 Testing form interactions...');

    // Login first
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    // Go to customers page to test forms
    await page.goto('/customers');
    await page.waitForLoadState('networkidle');

    // Look for "Add Customer" or similar button
    const addButtons = [
      'button:has-text("Add")',
      'button:has-text("Create")',
      'button:has-text("New")',
      '.btn-primary',
      '[data-testid="add-customer"]'
    ];

    for (const selector of addButtons) {
      const button = page.locator(selector).first();
      if (await button.isVisible()) {
        console.log(`   🆕 Found add button: ${selector}`);

        try {
          // Click the button to test modal or form
          await button.click();
          await page.waitForTimeout(1000);

          // Look for modal or form
          const modalSelectors = [
            '.modal',
            '.dialog',
            '.popup',
            '[data-testid="modal"]'
          ];

          let formFound = false;
          for (const modalSelector of modalSelectors) {
            if (await page.locator(modalSelector).isVisible()) {
              console.log(`   ✅ Modal/form found: ${modalSelector}`);
              formFound = true;

              // Test form fields
              const inputs = await page.locator('input, select, textarea').all();
              console.log(`   📝 Found ${inputs.length} form fields`);

              // Close modal if possible
              const closeButtons = await page.locator('.close, .cancel, [data-testid="close"]').all();
              if (closeButtons.length > 0) {
                await closeButtons[0].click();
                await page.waitForTimeout(500);
              }
              break;
            }
          }

          if (!formFound) {
            console.log('   ⚠️ Button clicked but no modal/form appeared');
          }

        } catch (error) {
          console.log(`   ❌ Error testing add button: ${error}`);
        }
        break;
      }
    }
  });

  test.afterAll(async () => {
    // Generate comprehensive error report
    console.log('\n📊 FRONTEND DEBUGGING REPORT');
    console.log('================================');

    const totalErrors = Object.values(errors).flat().length;
    const pagesWithErrors = Object.keys(errors).length;

    console.log(`Total pages tested: ${Object.keys(pages).length}`);
    console.log(`Pages with errors: ${pagesWithErrors}`);
    console.log(`Total errors found: ${totalErrors}`);

    if (totalErrors > 0) {
      console.log('\n🚨 ERRORS FOUND:');
      for (const [pageName, pageErrors] of Object.entries(errors)) {
        console.log(`\n❌ ${pageName}:`);
        pageErrors.forEach((error, index) => {
          console.log(`   ${index + 1}. ${error.substring(0, 200)}${error.length > 200 ? '...' : ''}`);
        });
      }
    } else {
      console.log('\n✅ No critical errors found! All pages loaded successfully.');
    }

    console.log('\n📸 Screenshots saved to: test-results/screenshots/');
    console.log('\n🎯 Recommendations:');

    if (pagesWithErrors > 0) {
      console.log('- Fix JavaScript errors found on pages with errors');
      console.log('- Check console logs for detailed error messages');
      console.log('- Review network requests for failed API calls');
    } else {
      console.log('- Frontend is working well - consider adding more specific functionality tests');
    }

    console.log('- Test responsive design on different devices');
    console.log('- Add accessibility testing');
    console.log('- Implement performance testing');
  });
});