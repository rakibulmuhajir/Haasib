import { test, expect } from '@playwright/test';

test.describe('Company Management', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to the login page
    await page.goto('/login');
    
    // Login as a superadmin user (you may need to create this user first)
    await page.fill('[name="email"]', 'superadmin@example.com');
    await page.fill('[name="password"]', 'password');
    await page.click('[type="submit"]');
    
    // Wait for dashboard to load
    await page.waitForURL('/dashboard');
  });

  test('should create a new company', async ({ page }) => {
    // Navigate to admin companies page
    await page.click('text=Admin');
    await page.click('text=Companies');
    await page.waitForURL('/admin/companies');
    
    // Click on Create Company button
    await page.click('text=Create Company');
    await page.waitForURL('/admin/companies/create');
    
    // Fill company details
    const companyName = `Test Company ${Date.now()}`;
    await page.fill('[name="name"]', companyName);
    await page.selectOption('[name="base_currency"]', 'USD');
    
    // Submit form
    await page.click('[type="submit"]');
    
    // Verify company was created
    await expect(page.locator('text=Company created successfully')).toBeVisible();
    await expect(page.locator(`text=${companyName}`)).toBeVisible();
  });

  test('should activate a company', async ({ page }) => {
    // First create a company or navigate to existing inactive company
    await page.goto('/admin/companies');
    
    // Find an inactive company (this assumes you have a way to identify inactive companies)
    const inactiveCompany = page.locator('.company-item.inactive').first();
    
    if (await inactiveCompany.isVisible()) {
      const companyName = await inactiveCompany.locator('.company-name').textContent();
      
      // Click activate button
      await inactiveCompany.locator('button.activate-button').click();
      
      // Confirm activation if prompted
      const confirmButton = page.locator('button.confirm-activation');
      if (await confirmButton.isVisible()) {
        await confirmButton.click();
      }
      
      // Verify success message
      await expect(page.locator('text=Company activated successfully')).toBeVisible();
      
      // Verify company is now active
      await expect(page.locator(`text=${companyName}`).locator('..')).toHaveClass(/active/);
    }
  });

  test('should deactivate a company', async ({ page }) => {
    // Navigate to companies
    await page.goto('/admin/companies');
    
    // Find an active company
    const activeCompany = page.locator('.company-item.active').first();
    
    if (await activeCompany.isVisible()) {
      const companyName = await activeCompany.locator('.company-name').textContent();
      
      // Click deactivate button
      await activeCompany.locator('button.deactivate-button').click();
      
      // Confirm deactivation if prompted
      const confirmButton = page.locator('button.confirm-deactivation');
      if (await confirmButton.isVisible()) {
        await confirmButton.click();
      }
      
      // Verify success message
      await expect(page.locator('text=Company deactivated successfully')).toBeVisible();
      
      // Verify company is now inactive
      await expect(page.locator(`text=${companyName}`).locator('..')).toHaveClass(/inactive/);
    }
  });

  test('should delete a company', async ({ page }) => {
    // Navigate to companies
    await page.goto('/admin/companies');
    
    // Find a company to delete (preferably a test company)
    const companyToDelete = page.locator('.company-item').first();
    
    if (await companyToDelete.isVisible()) {
      const companyName = await companyToDelete.locator('.company-name').textContent();
      
      // Click delete button
      await companyToDelete.locator('button.delete-button').click();
      
      // Confirm deletion in modal
      await page.click('button.confirm-delete');
      
      // Verify success message
      await expect(page.locator('text=Company deleted successfully')).toBeVisible();
      
      // Verify company is no longer in the list
      await expect(page.locator(`text=${companyName}`)).not.toBeVisible();
    }
  });

  test('should handle company management API endpoints directly', async ({ request }) => {
    // Test company creation via API
    const createResponse = await request.post('/api/companies', {
      data: {
        name: `API Test Company ${Date.now()}`,
        base_currency: 'EUR',
        settings: {
          timezone: 'UTC',
          fiscal_year_start: '01-01'
        }
      }
    });
    
    expect(createResponse.status()).toBe(201);
    const companyData = await createResponse.json();
    expect(companyData.data.name).toContain('API Test Company');
    
    const companyId = companyData.data.id;
    
    // Test company activation
    const activateResponse = await request.patch(`/web/companies/${companyId}/activate`);
    expect(activateResponse.status()).toBe(200);
    
    // Test company deactivation
    const deactivateResponse = await request.patch(`/web/companies/${companyId}/deactivate`);
    expect(deactivateResponse.status()).toBe(200);
    
    // Test company deletion
    const deleteResponse = await request.delete(`/web/companies/${companyId}`);
    expect(deleteResponse.status()).toBe(200);
  });

  test('should validate company creation form', async ({ page }) => {
    await page.goto('/admin/companies/create');
    
    // Try to submit form without required fields
    await page.click('[type="submit"]');
    
    // Check for validation errors
    await expect(page.locator('text=The name field is required')).toBeVisible();
    
    // Fill only name
    await page.fill('[name="name"]', 'Test Company');
    await page.click('[type="submit"]');
    
    // Should succeed now
    await expect(page.locator('text=Company created successfully')).toBeVisible();
  });

  test('should handle concurrent company operations', async ({ page }) => {
    // Create multiple companies rapidly
    const companyPromises = [];
    
    for (let i = 0; i < 3; i++) {
      companyPromises.push((async (index) => {
        await page.goto('/admin/companies/create');
        await page.fill('[name="name"]', `Concurrent Company ${index + 1}`);
        await page.selectOption('[name="base_currency"]', 'GBP');
        await page.click('[type="submit"]');
        await page.waitForURL('/admin/companies');
        return page.locator(`text=Concurrent Company ${index + 1}`).isVisible();
      })(i));
    }
    
    // Wait for all operations to complete
    const results = await Promise.all(companyPromises);
    expect(results.every(result => result === true)).toBeTruthy();
  });
});