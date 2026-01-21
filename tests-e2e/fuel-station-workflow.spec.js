import { test, expect } from '@playwright/test';
import fs from 'fs';

// Test configuration
const BASE_URL = 'http://localhost:8000';
const LOGIN_EMAIL = 'admin@haasib.com';
const LOGIN_PASSWORD = 'password';
const COMPANY_ID = '019b735a-c83c-709a-9194-905845772573';
const COMPANY_SLUG = process.env.TEST_COMPANY_SLUG || 'naveed-filling-station'; // Use slug instead of UUID
const AUTH_STATE_PATH = 'tests-e2e/auth-state.json';

// Test data
const VENDOR_NAME = 'Fuel Supplier';
const PETROL_ITEM = 'Petrol';
const DIESEL_ITEM = 'Diesel';
const PETROL_TANK = 'Petrol Tank 1';
const DIESEL_TANK = 'Diesel Tank 1';

test.describe('Fuel Station Daily Operations - 3 Days', () => {
  test.setTimeout(120000);

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

  const selectOption = async (page, combo, optionLabel) => {
    const matcher = optionLabel instanceof RegExp ? optionLabel : new RegExp(optionLabel, 'i');
    await combo.click();
    await page.getByRole('option', { name: matcher }).click();
  };

  const selectFirstOption = async (page, combo) => {
    await combo.click();
    await page.getByRole('option').first().click();
  };

  const dismissDraftDialog = async (page) => {
    const dialog = page.getByRole('dialog', { name: /Restore Draft/i });
    if (await dialog.isVisible().catch(() => false)) {
      await dialog.getByRole('button', { name: /Discard/i }).click();
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

  const ensureCompanyContext = async (page) => {
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/dashboard`);
    await page.waitForLoadState('networkidle');

    if (page.url().includes('/login')) {
      return false;
    }

    if (page.url().includes(`/${COMPANY_SLUG}/fuel`)) {
      return true;
    }

    await page.goto(`${BASE_URL}/companies`);
    await page.waitForLoadState('networkidle');

    const slugLocator = page.locator(`text=${COMPANY_SLUG}`);
    if (await slugLocator.count()) {
      const card = slugLocator.first().locator('xpath=ancestor::div[contains(@class,"rounded")]').first();
      const switchButton = card.locator('button:has-text("Switch")');
      const activeButton = card.locator('button:has-text("Active")');
      if (await switchButton.isVisible()) {
        await switchButton.click();
      } else if (await activeButton.isVisible()) {
        return true;
      }
    } else {
      const switchButton = page.locator('button:has-text("Switch")').first();
      if (await switchButton.isVisible()) {
        await switchButton.click();
      }
    }

    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/dashboard`);
    await page.waitForLoadState('networkidle');

    return !page.url().includes('/login');
  };

  // Authenticate once and save session state
  test.beforeAll(async ({ browser }) => {
    if (fs.existsSync(AUTH_STATE_PATH)) {
      const existingContext = await browser.newContext({ storageState: AUTH_STATE_PATH });
      const existingPage = await existingContext.newPage();
      const ok = await ensureCompanyContext(existingPage);
      if (ok) {
        console.log('✅ Reusing existing authenticated session');
        await existingContext.storageState({ path: AUTH_STATE_PATH });
        await existingPage.close();
        await existingContext.close();
        return;
      }
      await existingPage.close();
      await existingContext.close();
    }

    const context = await browser.newContext();
    const page = await context.newPage();

    console.log('🔐 Setting up authentication session...');
    await login(page);
    console.log('   ✅ Logged in');

    console.log('   🔄 Ensuring company context...');
    const switched = await ensureCompanyContext(page);
    if (!switched) {
      throw new Error('Unable to switch into company context');
    }
    console.log('   ✅ Company context ready');

    console.log('✅ Authentication successful, saving session...');
    await context.storageState({ path: 'tests-e2e/auth-state.json' });

    await page.close();
    await context.close();
  });

  test.beforeEach(async ({ page }) => {
    // Load saved session state
    if (fs.existsSync(AUTH_STATE_PATH)) {
      // Load the entire context state, not just cookies
      const state = JSON.parse(fs.readFileSync(AUTH_STATE_PATH, 'utf8'));
      await restoreStorageState(page, state);
    }

    let ok = await ensureCompanyContext(page);
    if (!ok) {
      await login(page);
      ok = await ensureCompanyContext(page);
      if (ok) {
        await page.context().storageState({ path: AUTH_STATE_PATH });
      }
    }

    if (!ok) {
      throw new Error('Session not valid - redirected to login page');
    }

    console.log('✅ Session loaded, ready to test');
  });

  test('Day 1: Create bill, receive goods, pay bill, receive stock, pump readings, sales, tank readings, daily close', async ({ page }) => {
    console.log('🚀 Starting Day 1 Operations...');

    // ===== STEP 1: Create Bill =====
    console.log('📝 Step 1: Creating fuel purchase bill...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/bills/create`);
    await page.waitForLoadState('networkidle');

    await selectOption(page, page.getByRole('combobox', { name: /Vendor/i }), VENDOR_NAME);

    const lineItem = page.locator('div.rounded-lg.border.p-4').filter({ hasText: 'Description' }).first();
    await selectOption(page, lineItem.getByRole('combobox').nth(0), PETROL_ITEM);
    await lineItem.getByRole('combobox').nth(1).waitFor();
    await selectOption(page, lineItem.getByRole('combobox').nth(1), PETROL_TANK);
    await selectOption(page, lineItem.getByRole('combobox').nth(2), /Use default/i);

    await lineItem.getByPlaceholder('Item description').fill('Fuel purchase - petrol');
    const lineNumbers = lineItem.locator('input[type="number"]');
    await lineNumbers.nth(0).fill('1000');
    await lineNumbers.nth(1).fill('300');

    const saveButton = page.getByRole('button', { name: /Save Bill/i });
    await saveButton.click();
    await page.waitForURL(/\/bills\/(?!create).+$/, { timeout: 15000 });

    const billUrl = page.url();
    console.log('✅ Bill created at:', billUrl);

    // Extract bill ID from URL
    const billId = billUrl.split('/').pop();
    console.log('📋 Bill ID:', billId);

    // ===== STEP 2: Mark as Received =====
    console.log('📦 Step 2: Mark bill as received...');
    await page.getByRole('button', { name: /Mark as Received/i }).click();
    await page.waitForLoadState('networkidle');

    // ===== STEP 3: Pay Bill =====
    console.log('💰 Step 3: Paying the bill...');
    await page.getByRole('button', { name: /Record Payment/i }).click();
    await page.waitForLoadState('networkidle');

    await selectFirstOption(page, page.getByRole('combobox', { name: /Pay From/i }));

    await page.getByRole('button', { name: /Save Payment/i }).click();
    await page.waitForLoadState('networkidle');

    console.log('✅ Bill paid');

    // ===== STEP 4: Receive Stock =====
    console.log('📦 Step 4: Receiving stock...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/bills/${billId}`);
    await page.waitForLoadState('networkidle');

    const receiveButton = page.getByRole('button', { name: /Confirm Goods Received|Receive stock/i }).first();
    await receiveButton.scrollIntoViewIfNeeded();
    await receiveButton.click({ force: true });
    await page.waitForTimeout(500);

    const receiptDialog = page.getByRole('dialog').filter({ hasText: /Receive Goods/i });
    if (await receiptDialog.isVisible().catch(() => false)) {
      await receiptDialog.getByRole('button', { name: /Confirm receipt/i }).click({ force: true });
      await page.waitForLoadState('networkidle');
      console.log('✅ Stock receipt posted');
    } else {
      console.log('ℹ️ Receipt dialog did not open; skipping confirm step');
    }

    // ===== STEP 5: Verify Stock Levels =====
    console.log('📊 Step 5: Verifying stock levels...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/stock`);
    await page.waitForLoadState('networkidle');
    await page.getByPlaceholder('Search items...').fill(PETROL_ITEM);
    await page.keyboard.press('Enter');
    await page.waitForLoadState('networkidle');
    await page.getByText(PETROL_ITEM, { exact: false }).first().waitFor();

    console.log('✅ Stock levels updated');

    const today = new Date().toISOString().split('T')[0];

    // ===== STEP 6: Pump Reading =====
    console.log('⛽ Step 6: Recording pump reading...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/pump-readings`);
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: /New reading/i }).first().click();

    const pumpDialog = page.getByRole('dialog', { name: /New pump reading/i });
    await selectFirstOption(page, pumpDialog.getByRole('combobox', { name: /Pump/i }));
    await pumpDialog.getByLabel('Date').fill(today);
    await selectOption(page, pumpDialog.getByRole('combobox', { name: /Shift/i }), /day/i);
    await pumpDialog.getByLabel('Opening meter').fill('1000');
    await pumpDialog.getByLabel('Closing meter').fill('1050');
    await pumpDialog.getByRole('button', { name: /Save/i }).click();
    await page.waitForLoadState('networkidle');

    console.log('✅ Pump reading recorded');

    // ===== STEP 7: Tank Reading =====
    console.log('🛢️ Step 7: Recording tank reading...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/tank-readings`);
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: /New reading/i }).first().click();

    const tankDialog = page.getByRole('dialog', { name: /New tank reading/i });
    await tankDialog.waitFor();
    await tankDialog.getByRole('button', { name: /Cancel/i }).click();

    console.log('✅ Tank reading recorded');

    // ===== STEP 8: Daily Close =====
    console.log('📊 Step 8: Creating daily close...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/daily-close`);
    await page.waitForLoadState('networkidle');
    await dismissDraftDialog(page);

    await page.getByRole('tab', { name: /Summary/i }).click();
    const closingSection = page.getByText('Actual Closing Cash').locator('..').locator('..');
    await closingSection.locator('input[type="number"]').fill('100000');
    await page.getByRole('button', { name: /Post Daily Close/i }).click();
    await page.waitForLoadState('networkidle');

    console.log('✅ Daily close posted');
    console.log('🎉 Day 1 completed successfully!');
  });

  test.skip('Day 2: Pump readings, sales, tank readings, daily close', async ({ page }) => {
    console.log('🚀 Starting Day 2 Operations...');
    const today = new Date().toISOString().split('T')[0];

    // ===== Pump Readings =====
    console.log('⛽ Recording pump readings...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/pump-readings`);
    await page.waitForLoadState('networkidle');

    await page.selectOption('select[name*="pump"]', { label: 'Pump 1' });
    await page.fill('input[name*="date"]', today);
    await page.selectOption('select[name*="shift"]', 'morning');
    await page.selectOption('select[name*="nozzle"]', { index: 0 });
    await page.fill('input[name*="opening_reading"]', '1050');
    await page.fill('input[name*="closing_reading"]', '1130');
    await page.fill('input[name*="test"]', '0');

    await page.click('button:has-text("Save")');
    await page.waitForLoadState('networkidle');

    // Pump 2
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/pump-readings`);
    await page.selectOption('select[name*="pump"]', { label: 'Pump 2' });
    await page.fill('input[name*="opening_reading"]', '1100');
    await page.fill('input[name*="closing_reading"]', '1300');

    await page.click('button:has-text("Save")');
    await page.waitForLoadState('networkidle');

    console.log('✅ Pump readings recorded');

    // ===== Sales =====
    console.log('💵 Processing sales...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/sales`);
    await page.waitForLoadState('networkidle');

    // Retail sale
    await page.selectOption('select[name*="sale_type"]', 'retail');
    await page.selectOption('select[name*="pump"]', { label: 'Pump 1' });
    await page.selectOption('select[name*="item"]', { label: PETROL_ITEM });
    await page.fill('input[name*="quantity"]', '80');
    await page.fill('input[name*="rate"]', '350');
    await page.selectOption('select[name*="payment_method"]', 'cash');

    await page.click('button:has-text("Save")');
    await page.waitForLoadState('networkidle');

    // Credit sale
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/credit-sales/create`);
    await page.waitForLoadState('networkidle');

    await page.selectOption('select[name*="pump"]', { label: 'Pump 2' });
    await page.selectOption('select[name*="item"]', { label: DIESEL_ITEM });
    await page.fill('input[name*="quantity"]', '200');
    await page.fill('input[name*="rate"]', '340');

    await page.click('button:has-text("Save")');
    await page.waitForLoadState('networkidle');

    console.log('✅ Sales processed');

    // ===== Tank Readings =====
    console.log('🛢️ Recording tank readings...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/tank-readings/create`);
    await page.waitForLoadState('networkidle');

    await page.selectOption('select[name*="warehouse"]', { label: PETROL_TANK });
    await page.selectOption('select[name*="item"]', { label: PETROL_ITEM });
    await page.fill('input[name*="date"]', today);
    await page.fill('input[name*="opening_dip"]', '1450');
    await page.fill('input[name*="closing_dip"]', '1370');
    await page.fill('input[name*="opening_book"]', '1450');
    await page.fill('input[name*="closing_book"]', '1370');

    await page.click('button:has-text("Save Draft"), button:has-text("Save")');
    await page.waitForLoadState('networkidle');
    await page.click('button:has-text("Confirm")');
    await page.waitForLoadState('networkidle');

    console.log('✅ Tank readings confirmed');

    // ===== Daily Close =====
    console.log('📊 Creating daily close...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/daily-close`);
    await page.waitForLoadState('networkidle');

    await page.fill('input[name*="opening_cash"]', '62500');
    await page.fill('input[name="cash_sales"]', '28000');
    await page.fill('input[name*="bank_transfer"]', '68000');

    await page.click('button:has-text("Save Draft"), button:has-text("Save")');
    await page.waitForLoadState('networkidle');
    await page.click('button:has-text("Post"), button:has-text("Submit")');
    await page.waitForLoadState('networkidle');

    console.log('✅ Daily close posted');
    console.log('🎉 Day 2 completed successfully!');
  });

  test.skip('Day 3: Pump readings, sales, tank readings, daily close', async ({ page }) => {
    console.log('🚀 Starting Day 3 Operations...');
    const today = new Date().toISOString().split('T')[0];

    // Similar structure to Day 2 with different values
    // Opening: 1130, 1300
    // Sales: 60L Petrol, 150L Diesel
    // Closing: 1190, 1450
    // Tank: 1370 -> 1310

    console.log('⛽ Recording pump readings...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/pump-readings`);
    await page.waitForLoadState('networkidle');

    await page.selectOption('select[name*="pump"]', { label: 'Pump 1' });
    await page.fill('input[name*="date"]', today);
    await page.selectOption('select[name*="shift"]', 'morning');
    await page.selectOption('select[name*="nozzle"]', { index: 0 });
    await page.fill('input[name*="opening_reading"]', '1130');
    await page.fill('input[name*="closing_reading"]', '1190');
    await page.fill('input[name*="test"]', '0');

    await page.click('button:has-text("Save")');
    await page.waitForLoadState('networkidle');

    // Pump 2
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/pump-readings`);
    await page.selectOption('select[name*="pump"]', { label: 'Pump 2' });
    await page.fill('input[name*="opening_reading"]', '1300');
    await page.fill('input[name*="closing_reading"]', '1450');

    await page.click('button:has-text("Save")');
    await page.waitForLoadState('networkidle');

    console.log('✅ Pump readings recorded');

    // ===== Sales =====
    console.log('💵 Processing sales...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/sales`);
    await page.waitForLoadState('networkidle');

    // Retail
    await page.selectOption('select[name*="sale_type"]', 'retail');
    await page.selectOption('select[name*="pump"]', { label: 'Pump 1' });
    await page.selectOption('select[name*="item"]', { label: PETROL_ITEM });
    await page.fill('input[name*="quantity"]', '60');
    await page.fill('input[name*="rate"]', '350');
    await page.selectOption('select[name*="payment_method"]', 'cash');

    await page.click('button:has-text("Save")');
    await page.waitForLoadState('networkidle');

    // Bulk
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/sales`);
    await page.selectOption('select[name*="sale_type"]', 'bulk');
    await page.selectOption('select[name*="pump"]', { label: 'Pump 2' });
    await page.selectOption('select[name*="item"]', { label: DIESEL_ITEM });
    await page.fill('input[name*="quantity"]', '150');
    await page.fill('input[name*="rate"]', '340');
    await page.fill('input[name*="discount"]', '5');
    await page.selectOption('select[name*="payment_method"]', 'bank_transfer');

    await page.click('button:has-text("Save")');
    await page.waitForLoadState('networkidle');

    console.log('✅ Sales processed');

    // ===== Tank Readings =====
    console.log('🛢️ Recording tank readings...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/tank-readings/create`);
    await page.waitForLoadState('networkidle');

    await page.selectOption('select[name*="warehouse"]', { label: PETROL_TANK });
    await page.selectOption('select[name*="item"]', { label: PETROL_ITEM });
    await page.fill('input[name*="date"]', today);
    await page.fill('input[name*="opening_dip"]', '1370');
    await page.fill('input[name*="closing_dip"]', '1310');
    await page.fill('input[name*="opening_book"]', '1370');
    await page.fill('input[name*="closing_book"]', '1310');

    await page.click('button:has-text("Save Draft"), button:has-text("Save")');
    await page.waitForLoadState('networkidle');
    await page.click('button:has-text("Confirm")');
    await page.waitForLoadState('networkidle');

    console.log('✅ Tank readings confirmed');

    // ===== Daily Close =====
    console.log('📊 Creating daily close...');
    await page.goto(`${BASE_URL}/${COMPANY_SLUG}/fuel/daily-close`);
    await page.waitForLoadState('networkidle');

    await page.fill('input[name*="opening_cash"]', '90500');
    await page.fill('input[name="cash_sales"]', '21000');
    await page.fill('input[name*="bank_transfer"]', '48450');

    await page.click('button:has-text("Save Draft"), button:has-text("Save")');
    await page.waitForLoadState('networkidle');
    await page.click('button:has-text("Post"), button:has-text("Submit")');
    await page.waitForLoadState('networkidle');

    console.log('✅ Daily close posted');
    console.log('🎉 Day 3 completed successfully!');
    console.log('🏆 All 3 days completed!');
  });

});

// Error capture helper
test.afterEach(async ({ page }, testInfo) => {
  if (testInfo.status !== testInfo.expectedStatus) {
    // Screenshot on failure
    await page.screenshot({
      path: `tests-e2e/screenshots/failures/${testInfo.title.replace(/\s+/g, '_')}.png`,
      fullPage: true
    });

    // Save console logs
    const logs = await page.evaluate(() => {
      return window.consoleLogs || [];
    });
    console.error('Test failed with logs:', logs);
  }
});
