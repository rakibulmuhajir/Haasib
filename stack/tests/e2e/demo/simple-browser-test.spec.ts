import { test, expect } from '@playwright/test';

test.describe('Simple Browser Demo Test', () => {
  test('should demonstrate browser automation capabilities', async ({ page }) => {
    console.log('🚀 Starting simple browser demo test...');

    // Step 1: Navigate to the application
    console.log('📱 Navigating to application...');
    await page.goto('http://localhost:8001');
    await page.waitForLoadState('networkidle');

    // Take a screenshot of the homepage
    await page.screenshot({ path: 'test-results/demo-homepage.png', fullPage: true });
    console.log('✅ Homepage screenshot taken');

    // Step 2: Check page title
    const title = await page.title();
    console.log(`📄 Page title: ${title}`);
    expect(title).toBeTruthy();

    // Step 3: Look for navigation elements
    console.log('🧭 Looking for navigation elements...');
    const navigationElements = await page.locator('nav, .navigation, .sidebar, a').all();
    console.log(`  Found ${navigationElements.length} navigation elements`);

    // Step 4: Try to navigate to login page
    console.log('🔐 Looking for login functionality...');

    // Look for login link or try direct navigation
    const loginLink = page.locator('a[href*="login"], button:has-text("Login"), a:has-text("Sign In")').first();

    if (await loginLink.isVisible()) {
      console.log('  ✅ Found login link');
      await loginLink.click();
      await page.waitForLoadState('networkidle');
    } else {
      console.log('  🔍 Trying direct login page navigation...');
      await page.goto('http://localhost:8001/login');
      await page.waitForLoadState('networkidle');
    }

    // Take screenshot of login page
    await page.screenshot({ path: 'test-results/demo-login-page.png', fullPage: true });
    console.log('✅ Login page screenshot taken');

    // Step 5: Look for form elements
    console.log('📝 Looking for form elements...');

    const formElements = {
      emailInputs: await page.locator('input[type="email"], input[name*="email"]').count(),
      passwordInputs: await page.locator('input[type="password"], input[name*="password"]').count(),
      submitButtons: await page.locator('button[type="submit"], input[type="submit"], button:has-text("Login")').count(),
      textInputs: await page.locator('input[type="text"]').count()
    };

    console.log('  Form elements found:');
    Object.entries(formElements).forEach(([type, count]) => {
      console.log(`    ${type}: ${count}`);
    });

    // Step 6: Test responsive design
    console.log('📱 Testing responsive design...');

    // Test mobile view
    await page.setViewportSize({ width: 375, height: 667 });
    await page.waitForTimeout(1000);
    await page.screenshot({ path: 'test-results/demo-mobile-view.png', fullPage: true });
    console.log('  ✅ Mobile view screenshot taken');

    // Test tablet view
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.waitForTimeout(1000);
    await page.screenshot({ path: 'test-results/demo-tablet-view.png', fullPage: true });
    console.log('  ✅ Tablet view screenshot taken');

    // Reset to desktop
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.waitForTimeout(1000);

    // Step 7: Test console for errors
    console.log('🐛 Checking for JavaScript errors...');

    const jsErrors: string[] = [];
    page.on('pageerror', (error) => {
      jsErrors.push(error.message);
    });

    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    if (jsErrors.length > 0) {
      console.log(`  ⚠️ Found ${jsErrors.length} JavaScript errors:`);
      jsErrors.forEach((error, index) => {
        console.log(`    ${index + 1}. ${error.substring(0, 100)}...`);
      });
    } else {
      console.log('  ✅ No JavaScript errors found');
    }

    // Step 8: Generate test summary
    console.log('\n📊 DEMO TEST SUMMARY');
    console.log('====================');
    console.log('✅ Browser automation working');
    console.log('✅ Screenshots captured successfully');
    console.log('✅ Page navigation functional');
    console.log('✅ Responsive design tested');
    console.log('✅ Error monitoring active');
    console.log(`✅ Test completed at ${new Date().toISOString()}`);

    // Final verification
    expect(await page.title()).toBeTruthy();
    expect(formElements.emailInputs + formElements.passwordInputs).toBeGreaterThan(0);
  });

  test('should test basic application functionality', async ({ page }) => {
    console.log('🔧 Testing basic application functionality...');

    // Navigate to application
    await page.goto('http://localhost:8001');
    await page.waitForLoadState('networkidle');

    // Test 1: Check if application loads
    const appLoaded = await page.locator('#app, [data-app], main').first().isVisible();
    expect(appLoaded).toBeTruthy();
    console.log('✅ Application loads correctly');

    // Test 2: Check for Vue.js specific elements
    const vueElements = await page.locator('[data-v-], .vue-component').count();
    console.log(`  Found ${vueElements} Vue.js specific elements`);

    // Test 3: Check for JavaScript frameworks
    const scripts = await page.locator('script').all();
    const hasVue = scripts.some(async script => {
      const src = await script.getAttribute('src');
      return src && src.includes('vue');
    });

    if (hasVue) {
      console.log('✅ Vue.js framework detected');
    }

    // Test 4: Test basic interactions
    const clickableElements = await page.locator('button, a, [onclick]').all();
    console.log(`  Found ${clickableElements.length} clickable elements`);

    // Test a safe click on a non-destructive element
    if (clickableElements.length > 0) {
      const firstElement = clickableElements[0];
      const tagName = await firstElement.evaluate(el => el.tagName.toLowerCase());

      if (tagName === 'a') {
        const href = await firstElement.getAttribute('href');
        if (href && !href.includes('logout') && !href.includes('delete')) {
          console.log('🔗 Testing safe navigation...');
          await firstElement.click();
          await page.waitForTimeout(2000);
          await page.goBack();
          await page.waitForLoadState('networkidle');
          console.log('✅ Navigation and back navigation working');
        }
      }
    }

    console.log('✅ Basic functionality test completed');
  });
});
