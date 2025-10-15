import { test, expect } from '@playwright/test';
import { User } from '../../app/models/User';
import { Company } from '../../app/models/Company';
import { Customer } from '../../app/models/Customer';

test.describe('Customer Contacts Management', () => {
  let user: any;
  let company: any;
  let customer: any;

  test.beforeEach(async ({ page }) => {
    // Setup test data - in a real implementation this would use factories
    // For now, we'll assume test user/company setup exists
    
    // Navigate to customer detail page
    await page.goto('/accounting/customers');
    await page.waitForLoadState('networkidle');
  });

  test('can navigate to customer detail and see contacts tab', async ({ page }) => {
    // Look for a test customer or create one
    await page.waitForSelector('[data-testid="customer-list"]', { timeout: 10000 });
    
    // Try to find a customer to click on
    const customerRow = page.locator('[data-testid="customer-row"]').first();
    if (await customerRow.isVisible()) {
      await customerRow.click();
      await page.waitForLoadState('networkidle');
      
      // Should see tabs including Contacts
      await expect(page.locator('text=Contacts')).toBeVisible();
      await expect(page.locator('text=Overview')).toBeVisible();
    } else {
      // If no customers exist, we should at least see the empty state
      await expect(page.locator('text=No customers found')).toBeVisible();
    }
  });

  test('can click contacts tab and see add contact button', async ({ page }) => {
    // Navigate to a customer detail page first
    await page.goto('/accounting/customers');
    await page.waitForLoadState('networkidle');
    
    // Find and click on a customer
    const customerRow = page.locator('[data-testid="customer-row"]').first();
    if (await customerRow.isVisible()) {
      await customerRow.click();
      await page.waitForLoadState('networkidle');
      
      // Click on Contacts tab
      await page.locator('text=Contacts').click();
      await page.waitForTimeout(500);
      
      // Should see Add Contact button (this will fail since UI doesn't exist)
      await expect(page.locator('text=Add Contact')).toBeVisible();
      await expect(page.locator('text=Primary Contact')).toBeVisible();
    }
  });

  test('can open add contact dialog', async ({ page }) => {
    await page.goto('/accounting/customers');
    await page.waitForLoadState('networkidle');
    
    const customerRow = page.locator('[data-testid="customer-row"]').first();
    if (await customerRow.isVisible()) {
      await customerRow.click();
      await page.waitForLoadState('networkidle');
      
      await page.locator('text=Contacts').click();
      await page.waitForTimeout(500);
      
      await page.locator('text=Add Contact').click();
      await page.waitForTimeout(500);
      
      // Should see contact form dialog
      await expect(page.locator('text=Add New Contact')).toBeVisible();
      await expect(page.locator('label:has-text("First Name")')).toBeVisible();
      await expect(page.locator('label:has-text("Last Name")')).toBeVisible();
      await expect(page.locator('label:has-text("Email")')).toBeVisible();
      await expect(page.locator('label:has-text("Phone")')).toBeVisible();
      await expect(page.locator('label:has-text("Role")')).toBeVisible();
      await expect(page.locator('label:has-text("Primary Contact")')).toBeVisible();
    }
  });

  test('can fill contact form and submit', async ({ page }) => {
    await page.goto('/accounting/customers');
    await page.waitForLoadState('networkidle');
    
    const customerRow = page.locator('[data-testid="customer-row"]').first();
    if (await customerRow.isVisible()) {
      await customerRow.click();
      await page.waitForLoadState('networkidle');
      
      await page.locator('text=Contacts').click();
      await page.waitForTimeout(500);
      
      await page.locator('text=Add Contact').click();
      await page.waitForTimeout(500);
      
      // Fill in contact form
      await page.fill('input[name="first_name"]', 'John');
      await page.fill('input[name="last_name"]', 'Doe');
      await page.fill('input[name="email"]', 'john.doe@example.com');
      await page.fill('input[name="phone"]', '+1234567890');
      await page.selectOption('select[name="role"]', 'billing');
      await page.check('input[name="is_primary"]');
      
      await page.locator('button:has-text("Save Contact")').click();
      await page.waitForTimeout(1000);
      
      // Should see success message or new contact in list
      // This will fail since backend doesn't exist
      await expect(page.locator('text=Contact added successfully')).toBeVisible();
      await expect(page.locator('text=John Doe')).toBeVisible();
      await expect(page.locator('text=john.doe@example.com')).toBeVisible();
    }
  });

  test('validates contact form fields', async ({ page }) => {
    await page.goto('/accounting/customers');
    await page.waitForLoadState('networkidle');
    
    const customerRow = page.locator('[data-testid="customer-row"]').first();
    if (await customerRow.isVisible()) {
      await customerRow.click();
      await page.waitForLoadState('networkidle');
      
      await page.locator('text=Contacts').click();
      await page.waitForTimeout(500);
      
      await page.locator('text=Add Contact').click();
      await page.waitForTimeout(500);
      
      // Try to submit empty form
      await page.locator('button:has-text("Save Contact")').click();
      await page.waitForTimeout(500);
      
      // Should see validation errors
      await expect(page.locator('text=First name is required')).toBeVisible();
      await expect(page.locator('text=Last name is required')).toBeVisible();
      await expect(page.locator('text=Email is required')).toBeVisible();
    }
  });

  test('can edit existing contact', async ({ page }) => {
    await page.goto('/accounting/customers');
    await page.waitForLoadState('networkidle');
    
    const customerRow = page.locator('[data-testid="customer-row"]').first();
    if (await customerRow.isVisible()) {
      await customerRow.click();
      await page.waitForLoadState('networkidle');
      
      await page.locator('text=Contacts').click();
      await page.waitForTimeout(500);
      
      // Look for edit button on existing contact (this will fail)
      await expect(page.locator('button:has-text("Edit Contact")')).toBeVisible();
      await page.locator('button:has-text("Edit Contact")').first().click();
      await page.waitForTimeout(500);
      
      // Should see edit form with pre-filled data
      await expect(page.locator('text=Edit Contact')).toBeVisible();
      await expect(page.locator('label:has-text("First Name")')).toBeVisible();
      
      await page.fill('input[name="first_name"]', 'Updated Name');
      await page.locator('button:has-text("Update Contact")').click();
      await page.waitForTimeout(1000);
      
      // Should see updated contact
      await expect(page.locator('text=Contact updated successfully')).toBeVisible();
      await expect(page.locator('text=Updated Name')).toBeVisible();
    }
  });

  test('can delete contact with confirmation', async ({ page }) => {
    await page.goto('/accounting/customers');
    await page.waitForLoadState('networkidle');
    
    const customerRow = page.locator('[data-testid="customer-row"]').first();
    if (await customerRow.isVisible()) {
      await customerRow.click();
      await page.waitForLoadState('networkidle');
      
      await page.locator('text=Contacts').click();
      await page.waitForTimeout(500);
      
      // Look for delete button
      await expect(page.locator('button:has-text("Delete Contact")')).toBeVisible();
      await page.locator('button:has-text("Delete Contact")').first().click();
      await page.waitForTimeout(500);
      
      // Should see confirmation dialog
      await expect(page.locator('text=Delete Contact?')).toBeVisible();
      await expect(page.locator('text=Are you sure you want to delete this contact?')).toBeVisible();
      await page.locator('button:has-text("Confirm Delete")').click();
      await page.waitForTimeout(1000);
      
      // Should see success message and contact removed
      await expect(page.locator('text=Contact deleted successfully')).toBeVisible();
    }
  });

  test('enforces rbac permissions for contact management', async ({ page }) => {
    // This test would need to be run with a user without contact management permissions
    // For now, we'll test the UI should not show certain elements
    
    await page.goto('/accounting/customers');
    await page.waitForLoadState('networkidle');
    
    const customerRow = page.locator('[data-testid="customer-row"]').first();
    if (await customerRow.isVisible()) {
      await customerRow.click();
      await page.waitForLoadState('networkidle');
      
      await page.locator('text=Contacts').click();
      await page.waitForTimeout(500);
      
      // Should not see Add Contact button (or it should be disabled)
      const addContactBtn = page.locator('button:has-text("Add Contact")');
      if (await addContactBtn.isVisible()) {
        await expect(addContactBtn).toBeDisabled();
      } else {
        await expect(addContactBtn).not.toBeVisible();
      }
    }
  });

  test('handles keyboard navigation accessibility', async ({ page }) => {
    await page.goto('/accounting/customers');
    await page.waitForLoadState('networkidle');
    
    const customerRow = page.locator('[data-testid="customer-row"]').first();
    if (await customerRow.isVisible()) {
      await customerRow.click();
      await page.waitForLoadState('networkidle');
      
      await page.locator('text=Contacts').click();
      await page.waitForTimeout(500);
      
      // Test tab navigation
      await page.keyboard.press('Tab');
      await page.keyboard.press('Tab');
      await page.keyboard.press('Enter'); // Try to activate Add Contact button
      
      // Test escape key to close dialog
      await page.waitForTimeout(500);
      await page.keyboard.press('Escape'); // Should close any open dialog
    }
  });

  test('displays contact loading states', async ({ page }) => {
    await page.goto('/accounting/customers');
    await page.waitForLoadState('networkidle');
    
    const customerRow = page.locator('[data-testid="customer-row"]').first();
    if (await customerRow.isVisible()) {
      await customerRow.click();
      await page.waitForLoadState('networkidle');
      
      await page.locator('text=Contacts').click();
      
      // Should show loading state while fetching contacts
      await expect(page.locator('text=Loading contacts...')).toBeVisible();
    }
  });

  test('handles contact management errors gracefully', async ({ page }) => {
    await page.goto('/accounting/customers');
    await page.waitForLoadState('networkidle');
    
    const customerRow = page.locator('[data-testid="customer-row"]').first();
    if (await customerRow.isVisible()) {
      await customerRow.click();
      await page.waitForLoadState('networkidle');
      
      await page.locator('text=Contacts').click();
      await page.waitForTimeout(500);
      
      await page.locator('text=Add Contact').click();
      await page.waitForTimeout(500);
      
      // Fill form with data that might cause an error
      await page.fill('input[name="first_name"]', 'Jane');
      await page.fill('input[name="last_name"]', 'Doe');
      await page.fill('input[name="email"]', 'invalid-email'); // Invalid email
      await page.locator('button:has-text("Save Contact")').click();
      await page.waitForTimeout(1000);
      
      // Should show error message
      await expect(page.locator('text=Please enter a valid email address')).toBeVisible();
    }
  });

  test('shows responsive design on mobile', async ({ page }) => {
    // Test mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/accounting/customers');
    await page.waitForLoadState('networkidle');
    
    const customerRow = page.locator('[data-testid="customer-row"]').first();
    if (await customerRow.isVisible()) {
      await customerRow.click();
      await page.waitForLoadState('networkidle');
      
      // On mobile, contacts might be in a different layout
      await expect(page.locator('text=Contacts')).toBeVisible();
      await page.locator('text=Contacts').click();
      await page.waitForTimeout(500);
      
      // Should still be usable on mobile
      await expect(page.locator('[data-testid="contacts-list"]')).toBeVisible();
    }
  });
});