import { test as base, Page, BrowserContext } from '@playwright/test';

// Define test fixtures
export interface TestFixtures {
  authenticatedPage: Page;
}

// Extend base test with custom fixtures
export const test = base.extend<TestFixtures>({
  authenticatedPage: async ({ page, context }, use) => {
    // Login before using the page
    await performLogin(page);
    await use(page);
  },
});

// Helper function for login
export async function performLogin(page: Page) {
  await page.goto('/login');
  await page.waitForLoadState('networkidle');

  // Fill login form
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');

  // Wait a moment to see if login succeeds
  await page.waitForTimeout(3000);

  // Check if we're still on login page (login failed)
  const currentUrl = page.url();
  if (currentUrl.includes('/login')) {
    console.log('Login failed, attempting to register testowner user...');

    // Go to registration page and create the user
    await page.goto('/register');
    await page.waitForLoadState('networkidle');

    const timestamp = Date.now();
    await page.fill('input[name="name"]', 'Test Owner');
    await page.fill('input[name="username"]', 'testowner');
    await page.fill('input[name="email"]', `testowner${timestamp}@example.com`);
    await page.fill('input[name="password"]', 'password');
    await page.fill('input[name="password_confirmation"]', 'password');
    await page.fill('input[name="company_name"]', `TestOwner Company ${timestamp}`);
    await page.fill('input[name="company_email"]', `info@testowner${timestamp}.com`);
    await page.fill('input[name="company_phone"]', '+1 (555) 987-6543');
    await page.fill('input[name="company_website"]', `https://testowner${timestamp}.com`);

    await page.click('button:has-text("Create Account")');
    await page.waitForTimeout(3000);

    // Try to login again after registration
    await page.goto('/login');
    await page.waitForLoadState('networkidle');
    await page.fill('input[name="username"]', 'testowner');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(3000);
  }

  // Wait for successful login - could redirect to dashboard, companies, or other authenticated page
  try {
    await page.waitForFunction(() => {
      const url = window.location.href;
      return !url.includes('/login') && !url.includes('/register');
    }, { timeout: 10000 });
    await page.waitForLoadState('networkidle');
  } catch (error) {
    console.log('Login/registration failed, taking screenshot for debugging...');
    await page.screenshot({
      path: `test-results/login-failed-${Date.now()}.png`,
      fullPage: true
    });
    throw error;
  }
}

// Export common test utilities
export const expect = base.expect;

// Helper functions
export async function navigateToModule(page: Page, module: string) {
  const modulePaths: { [key: string]: string } = {
    'companies': '/companies',
    'customers': '/customers',
    'invoices': '/invoices',
    'payments': '/payments',
    'reports': '/reports',
    'settings': '/settings',
    'ledger': '/ledger',
    'journal': '/journal',
    'dashboard': '/dashboard'
  };

  const path = modulePaths[module.toLowerCase()];
  if (!path) {
    throw new Error(`Unknown module: ${module}`);
  }

  console.log(`Navigating directly to ${path}...`);
  await page.goto(path);
  await page.waitForLoadState('networkidle');
}

export async function clickButtonWithText(page: Page, text: string) {
  const selectors = [
    `button:has-text("${text}")`,
    `button[aria-label*="${text}"]`,
    `button.p-button:has-text("${text}")`,
    `a:has-text("${text}")`,
    `[data-testid*="${text.toLowerCase()}"]`,
    `.btn:has-text("${text}")`,
    `[label*="${text}"]`
  ];

  for (const selector of selectors) {
    try {
      const button = page.locator(selector).first();
      if (await button.isVisible({ timeout: 3000 })) {
        await button.click();
        console.log(`✅ Clicked button: ${text}`);
        return true;
      }
    } catch (error) {
      // Continue trying other selectors
    }
  }

  throw new Error(`Button with text "${text}" not found`);
}

export async function fillForm(page: Page, data: { [key: string]: string }) {
  for (const [fieldName, value] of Object.entries(data)) {
    const selectors = [
      `input[name="${fieldName}"]`,
      `input[id*="${fieldName}"]`,
      `textarea[name="${fieldName}"]`,
      `select[name="${fieldName}"]`,
      `[data-testid="${fieldName}"]`
    ];

    let fieldFound = false;
    for (const selector of selectors) {
      const field = page.locator(selector).first();
      if (await field.isVisible()) {
        await field.fill(value);
        fieldFound = true;
        break;
      }
    }

    if (!fieldFound) {
      console.warn(`Field "${fieldName}" not found in form`);
    }
  }
}

export async function waitForSuccessMessage(page: Page) {
  const successSelectors = [
    '.p-toast-message-success',
    '.p-toast-message',
    '[data-pc-name="toast"]',
    '.success',
    '.alert-success',
    '.notification-success',
    '[data-testid="success-message"]',
    '.toast-success'
  ];

  for (const selector of successSelectors) {
    try {
      await page.waitForSelector(selector, { timeout: 5000 });
      console.log(`✅ Found success message: ${selector}`);
      return true;
    } catch (error) {
      // Continue trying other selectors
    }
  }

  return false;
}

export async function takeScreenshot(page: Page, name: string) {
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  await page.screenshot({
    path: `test-results/screenshots/${name}-${timestamp}.png`,
    fullPage: true
  });
}

export function generateTestData() {
  const timestamp = Date.now();
  const random = Math.random().toString(36).substring(7);

  return {
    company: {
      name: `Test Company ${timestamp}`,
      email: `company-${random}@example.com`,
      phone: `+1-555-${timestamp.toString().slice(-4)}`,
      website: `https://testcompany${random}.com`,
      industry: 'technology',
      base_currency: 'USD'
    },
    customer: {
      name: `Test Customer ${timestamp}`,
      email: `customer-${random}@example.com`,
      phone: `+1-555-${timestamp.toString().slice(-4)}`,
      currency: 'USD',
      credit_limit: '10000.00'
    },
    invoice: {
      invoice_number: `INV-${timestamp}`,
      subtotal: '1000.00',
      tax_amount: '80.00',
      total_amount: '1080.00'
    },
    user: {
      name: `Test User ${timestamp}`,
      email: `user-${random}@example.com`,
      username: `user${random}`,
      password: 'Password123!'
    }
  };
}

// Common test data
export const TEST_CREDENTIALS = {
  username: 'admin',
  password: 'password'
};

export default test;