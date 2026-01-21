import { test, expect } from '@playwright/test';
import fs from 'fs';

const BASE_URL = 'http://localhost:8000';
const LOGIN_EMAIL = 'admin@haasib.com';
const LOGIN_PASSWORD = 'password';
const AUTH_STATE_PATH = 'tests-e2e/auth-state.json';

const restoreStorageState = async (page, state) => {
  if (state?.cookies?.length) {
    await page.context().addCookies(state.cookies);
  }

  if (state?.origins?.length) {
    for (const origin of state.origins) {
      await page.goto(origin.origin, { waitUntil: 'domcontentloaded' });
      await page.evaluate((entries) => {
        for (const entry of entries || []) {
          localStorage.setItem(entry.name, entry.value);
        }
      }, origin.localStorage || []);
    }
  }
};

const login = async (page) => {
  await page.goto(`${BASE_URL}/login`);
  await page.waitForSelector('input[name="email"], input[type="email"]', { timeout: 10000 });
  await page.fill('input[name="email"], input[type="email"]', LOGIN_EMAIL);
  await page.fill('input[name="password"], input[type="password"]', LOGIN_PASSWORD);

  const rememberMe = page.getByLabel('Remember me');
  if (await rememberMe.isVisible().catch(() => false)) {
    const checked = await rememberMe.isChecked().catch(() => false);
    if (!checked) {
      await rememberMe.check();
    }
  }

  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');

  if (page.url().includes('/login')) {
    const errorMessage = await page
      .locator('[role="alert"], .text-destructive, .text-red-600')
      .first()
      .textContent()
      .catch(() => '');
    throw new Error(`Login failed${errorMessage ? `: ${errorMessage.trim()}` : ''}`);
  }
};

const selectOption = async (page, combo, optionLabel) => {
  const matcher = optionLabel instanceof RegExp ? optionLabel : new RegExp(optionLabel, 'i');
  await combo.click();
  await page.getByRole('option', { name: matcher }).click();
};

test.describe('Minimal Setup Flow', () => {
  test.setTimeout(120000);

  test.beforeEach(async ({ page }) => {
    if (fs.existsSync(AUTH_STATE_PATH)) {
      const state = JSON.parse(fs.readFileSync(AUTH_STATE_PATH, 'utf8'));
      await restoreStorageState(page, state);
    }

    await page.goto(`${BASE_URL}/dashboard`);
    await page.waitForLoadState('networkidle');

    if (page.url().includes('/login')) {
      await login(page);
      await page.context().storageState({ path: AUTH_STATE_PATH });
    }
  });

  test('Create company with minimal setup and create first invoice', async ({ page }) => {
    const timestamp = Date.now();
    const companyName = `Test Station ${timestamp}`;

    await page.goto(`${BASE_URL}/companies/create`);
    await page.waitForLoadState('networkidle');

    await page.getByLabel('Company Name').fill(companyName);

    await selectOption(
      page,
      page.getByRole('combobox', { name: /Industry/i }),
      /Fuel Station|Retail|Wholesale/i
    );

    await selectOption(
      page,
      page.getByRole('combobox', { name: /Country/i }),
      /Pakistan/i
    );

    await page.getByRole('button', { name: /Create Company/i }).click();
    await page.waitForLoadState('networkidle');

    const companySlug = new URL(page.url()).pathname.split('/').filter(Boolean).pop();
    expect(companySlug).toBeTruthy();

    await expect(page.getByText(companyName)).toBeVisible();

    await page.goto(`${BASE_URL}/${companySlug}/accounting/default-accounts`);
    await page.waitForLoadState('networkidle');
    const firstCombobox = page.getByRole('combobox').first();
    await firstCombobox.click();
    await expect(page.getByRole('option').first()).toBeVisible();
    await page.keyboard.press('Escape');

    const customerName = `Customer ${timestamp}`;
    await page.goto(`${BASE_URL}/${companySlug}/invoices/create`);
    await page.waitForLoadState('networkidle');

    await page.getByRole('combobox', { name: /customer/i }).click();
    await page.locator('[data-entity-search-input]').fill(customerName);
    await page.getByRole('button', { name: /\+ New Customer/i }).click();

    const quickAddDialog = page.getByRole('dialog', { name: /Quick Add Customer/i });
    await expect(quickAddDialog).toBeVisible();
    const nameInput = quickAddDialog.getByLabel('Name');
    await nameInput.fill(customerName);
    await quickAddDialog.getByRole('button', { name: /Create & Select/i }).click();

    await expect(page.getByRole('combobox', { name: /customer/i })).toContainText(customerName);

    await page.getByPlaceholder('Item description').fill('Initial service');
    const numberInputs = page.locator('input[type="number"]');
    await numberInputs.nth(1).fill('1');
    await numberInputs.nth(2).fill('500');

    await page.getByRole('button', { name: /Create Invoice/i }).click();
    await page.waitForURL(new RegExp(`${BASE_URL}/[^/]+/invoices/`));
    await expect(page.getByText('Invoice')).toBeVisible();
  });
});
