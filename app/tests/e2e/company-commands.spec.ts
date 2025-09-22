import { test, expect } from '@playwright/test';

test.describe('Company Commands', () => {
  test.beforeEach(async ({ page }) => {
    // Load authenticated state
    await page.context().addInitScript(() => {
      window.localStorage.setItem('auth-token', 'your-auth-token');
    });
    
    await page.goto('/dashboard');
  });

  test('should create company via command', async ({ page }) => {
    // Open command palette (assuming there's a shortcut or button)
    await page.press('Body', 'Control+K'); // Common shortcut for command palette
    
    // Wait for command palette to appear
    await page.waitForSelector('.command-palette');
    
    // Type company creation command
    await page.fill('.command-input', 'create company');
    await page.press('.command-input', 'Enter');
    
    // Fill company details in command form
    const companyName = `Command Test Company ${Date.now()}`;
    await page.fill('[data-command="company-name"]', companyName);
    await page.fill('[data-command="company-currency"]', 'CAD');
    
    // Execute command
    await page.click('[data-command="execute"]');
    
    // Verify success
    await expect(page.locator('.command-success')).toBeVisible();
    await expect(page.locator(`text=${companyName}`)).toBeVisible();
  });

  test('should list companies via command', async ({ page }) => {
    // Open command palette
    await page.press('Body', 'Control+K');
    
    // Type list command
    await page.fill('.command-input', 'list companies');
    await page.press('.command-input', 'Enter');
    
    // Verify company list appears
    await expect(page.locator('.company-list')).toBeVisible();
    await expect(page.locator('.company-item')).toHaveCount({ min: 1 });
  });

  test('should switch company via command', async ({ page }) => {
    // Open command palette
    await page.press('Body', 'Control+K');
    
    // Type switch command
    await page.fill('.command-input', 'switch company');
    await page.press('.command-input', 'Enter');
    
    // Select company from list
    const companyOption = page.locator('.company-option').first();
    const companyName = await companyOption.textContent();
    
    await companyOption.click();
    
    // Verify company switch
    await expect(page.locator('.current-company')).toHaveText(companyName);
  });
});