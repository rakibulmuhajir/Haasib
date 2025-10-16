import { test, expect } from '@playwright/test';
import { loginAs, getTestUser, getTestCompany } from '../support/auth';
import { navigateToJournalEntries } from '../support/navigation';

test.describe('Manual Journal Entry UI Flow', () => {
  let user: any;
  let company: any;

  test.beforeAll(async () => {
    user = await getTestUser();
    company = await getTestCompany();
  });

  test.beforeEach(async ({ page }) => {
    await loginAs(page, user, company);
    await navigateToJournalEntries(page);
  });

  test('can create a balanced manual journal entry', async ({ page }) => {
    // Click Create New Entry button
    await page.click('[data-testid="create-journal-entry-btn"]');
    
    // Should be on create page
    await expect(page).toHaveURL(/.*\/journal-entries\/create/);
    await expect(page.locator('h1')).toContainText('Create Journal Entry');
    
    // Fill in journal entry details
    await page.fill('[data-testid="journal-description"]', 'Test Manual Journal Entry');
    await page.fill('[data-testid="journal-date"]', '2025-01-15');
    await page.selectOption('[data-testid="journal-type"]', 'adjustment');
    
    // Add first line (debit)
    await page.click('[data-testid="add-line-btn"]');
    await page.selectOption('[data-testid="line-0-account"]', '1000'); // Cash account
    await page.selectOption('[data-testid="line-0-debit-credit"]', 'debit');
    await page.fill('[data-testid="line-0-amount"]', '1000.00');
    await page.fill('[data-testid="line-0-description"]', 'Cash debit');
    
    // Add second line (credit)
    await page.click('[data-testid="add-line-btn"]');
    await page.selectOption('[data-testid="line-1-account"]', '4000'); // Revenue account
    await page.selectOption('[data-testid="line-1-debit-credit"]', 'credit');
    await page.fill('[data-testid="line-1-amount"]', '1000.00');
    await page.fill('[data-testid="line-1-description"]', 'Revenue credit');
    
    // Check balance indicator shows balanced
    await expect(page.locator('[data-testid="balance-indicator"]')).toContainText('Balanced');
    await expect(page.locator('[data-testid="total-debits"]')).toContainText('1,000.00');
    await expect(page.locator('[data-testid="total-credits"]')).toContainText('1,000.00');
    
    // Submit form
    await page.click('[data-testid="save-draft-btn"]');
    
    // Should redirect to entry details page
    await expect(page).toHaveURL(/.*\/journal-entries\/[a-f0-9-]{36}$/);
    await expect(page.locator('[data-testid="journal-status"]')).toContainText('Draft');
    await expect(page.locator('[data-testid="journal-description"]')).toContainText('Test Manual Journal Entry');
  });

  test('cannot save unbalanced journal entry', async ({ page }) => {
    // Click Create New Entry button
    await page.click('[data-testid="create-journal-entry-btn"]');
    
    // Fill in journal entry details
    await page.fill('[data-testid="journal-description"]', 'Unbalanced Entry');
    await page.fill('[data-testid="journal-date"]', '2025-01-15');
    await page.selectOption('[data-testid="journal-type"]', 'adjustment');
    
    // Add unbalanced lines
    await page.click('[data-testid="add-line-btn"]');
    await page.selectOption('[data-testid="line-0-account"]', '1000');
    await page.selectOption('[data-testid="line-0-debit-credit"]', 'debit');
    await page.fill('[data-testid="line-0-amount"]', '1000.00');
    
    await page.click('[data-testid="add-line-btn"]');
    await page.selectOption('[data-testid="line-1-account"]', '4000');
    await page.selectOption('[data-testid="line-1-debit-credit"]', 'credit');
    await page.fill('[data-testid="line-1-amount"]', '800.00'); // Unbalanced
    
    // Check balance indicator shows unbalanced
    await expect(page.locator('[data-testid="balance-indicator"]')).toContainText('Unbalanced');
    await expect(page.locator('[data-testid="difference-amount"]')).toContainText('200.00');
    
    // Save button should be disabled
    await expect(page.locator('[data-testid="save-draft-btn"]')).toBeDisabled();
    
    // Error message should be visible
    await expect(page.locator('[data-testid="balance-error"]')).toBeVisible();
    await expect(page.locator('[data-testid="balance-error"]')).toContainText('Journal entry must be balanced');
  });

  test('can submit journal entry for approval', async ({ page }) => {
    // Create a balanced journal entry first
    await createBalancedJournalEntry(page);
    
    // Submit for approval
    await page.click('[data-testid="submit-approval-btn"]');
    
    // Add submission note
    await page.fill('[data-testid="submit-note"]', 'Ready for review');
    await page.click('[data-testid="confirm-submit-btn"]');
    
    // Status should update to pending approval
    await expect(page.locator('[data-testid="journal-status"]')).toContainText('Pending Approval');
    
    // Submit button should be disabled
    await expect(page.locator('[data-testid="submit-approval-btn"]')).toBeDisabled();
    
    // Approval and post buttons should be available
    await expect(page.locator('[data-testid="approve-btn"]')).toBeVisible();
    await expect(page.locator('[data-testid="post-btn"]')).toBeVisible();
  });

  test('can approve and post journal entry', async ({ page }) => {
    // Create and submit journal entry
    await createBalancedJournalEntry(page);
    await page.click('[data-testid="submit-approval-btn"]');
    await page.fill('[data-testid="submit-note"]', 'Ready for review');
    await page.click('[data-testid="confirm-submit-btn"]');
    
    // Approve the entry
    await page.click('[data-testid="approve-btn"]');
    await page.fill('[data-testid="approval-note"]', 'Looks good to me');
    await page.click('[data-testid="confirm-approve-btn"]');
    
    // Status should update to approved
    await expect(page.locator('[data-testid="journal-status"]')).toContainText('Approved');
    
    // Post button should be enabled
    await expect(page.locator('[data-testid="post-btn"]')).toBeEnabled();
    
    // Post the entry
    await page.click('[data-testid="post-btn"]');
    await page.fill('[data-testid="post-note"]', 'Posting to ledger');
    await page.click('[data-testid="confirm-post-btn"]');
    
    // Status should update to posted
    await expect(page.locator('[data-testid="journal-status"]')).toContainText('Posted');
    
    // Posted timestamp should be visible
    await expect(page.locator('[data-testid="posted-at"]')).toBeVisible();
    
    // All action buttons should be disabled for posted entries
    await expect(page.locator('[data-testid="submit-approval-btn"]')).toBeDisabled();
    await expect(page.locator('[data-testid="approve-btn"]')).toBeDisabled();
    await expect(page.locator('[data-testid="post-btn"]')).toBeDisabled();
    
    // Reverse button should be available
    await expect(page.locator('[data-testid="reverse-btn"]')).toBeVisible();
  });

  test('can create reversal for posted journal entry', async ({ page }) => {
    // Create, approve, and post journal entry
    await createAndPostJournalEntry(page);
    
    // Create reversal
    await page.click('[data-testid="reverse-btn"]');
    
    // Should open reversal modal
    await expect(page.locator('[data-testid="reversal-modal"]')).toBeVisible();
    
    // Fill reversal details
    await page.fill('[data-testid="reversal-date"]', '2025-01-20');
    await page.fill('[data-testid="reversal-description"]', 'Reversal of original entry');
    await page.check('[data-testid="auto-post-reversal"]');
    
    // Confirm reversal
    await page.click('[data-testid="confirm-reversal-btn"]');
    
    // Should redirect to reversal entry page
    await expect(page).toHaveURL(/.*\/journal-entries\/[a-f0-9-]{36}$/);
    await expect(page.locator('[data-testid="journal-type"]')).toContainText('Reversal');
    await expect(page.locator('[data-testid="journal-status"]')).toContainText('Posted');
    
    // Check that amounts are inverted
    const lines = page.locator('[data-testid="journal-lines"]');
    await expect(lines).toHaveCount(2);
    
    // First line should be credit (original was debit)
    await expect(page.locator('[data-testid="line-0-debit-credit"]')).toContainText('credit');
    await expect(page.locator('[data-testid="line-0-amount"]')).toContainText('1,000.00');
    
    // Second line should be debit (original was credit)
    await expect(page.locator('[data-testid="line-1-debit-credit"]')).toContainText('debit');
    await expect(page.locator('[data-testid="line-1-amount"]')).toContainText('1,alibaba00.00');
    
    // Original entry should show reference to reversal
    await page.goBack();
    await expect(page.locator('[data-testid="reversal-reference"]')).toBeVisible();
    await page.click('[data-testid="reversal-reference"]');
    
    // Should navigate to reversal entry
    await expect(page.locator('[data-testid="journal-type"]')).toContainText('Reversal');
  });

  test('can filter and search journal entries', async ({ page }) => {
    // Navigate to journal entries list
    await navigateToJournalEntries(page);
    
    // Create multiple entries with different statuses
    await createBalancedJournalEntry(page, 'Draft Entry 1');
    await page.goto(`/companies/${company.id}/journal-entries`);
    
    await createBalancedJournalEntry(page, 'Draft Entry 2');
    await page.goto(`/companies/${company.id}/journal-entries`);
    
    // Test status filter
    await page.selectOption('[data-testid="status-filter"]', 'draft');
    await page.click('[data-testid="apply-filters-btn"]');
    
    // Should show only draft entries
    const draftEntries = page.locator('[data-testid="journal-entry-row"]');
    await expect(draftEntries).toHaveCount(2);
    
    // Test date range filter
    await page.fill('[data-testid="date-from-filter"]', '2025-01-15');
    await page.fill('[data-testid="date-to-filter"]', '2025-01-15');
    await page.click('[data-testid="apply-filters-btn"]');
    
    // Should show entries for specific date
    const dateFilteredEntries = page.locator('[data-testid="journal-entry-row"]');
    await expect(dateFilteredEntries).toHaveCount(2);
    
    // Test search by description
    await page.fill('[data-testid="search-input"]', 'Draft Entry 1');
    await page.click('[data-testid="search-btn"]');
    
    // Should show only matching entry
    const searchResults = page.locator('[data-testid="journal-entry-row"]');
    await expect(searchResults).toHaveCount(1);
    await expect(page.locator('[data-testid="journal-entry-row"] [data-testid="description"]')).toContainText('Draft Entry 1');
    
    // Clear filters
    await page.click('[data-testid="clear-filters-btn"]');
    await expect(page.locator('[data-testid="journal-entry-row"]')).toHaveCountGreaterThan(1);
  });

  test('can view audit trail for journal entry', async ({ page }) => {
    // Create and post journal entry
    await createAndPostJournalEntry(page);
    
    // Navigate to audit trail
    await page.click('[data-testid="audit-trail-tab"]');
    
    // Should show audit events
    await expect(page.locator('[data-testid="audit-events"]')).toBeVisible();
    
    const auditEvents = page.locator('[data-testid="audit-event-row"]');
    await expect(auditEvents).toHaveCount(4); // Created, Submitted, Approved, Posted
    
    // Check specific events
    await expect(page.locator('[data-testid="event-created"]')).toBeVisible();
    await expect(page.locator('[data-testid="event-submitted"]')).toBeVisible();
    await expect(page.locator('[data-testid="event-approved"]')).toBeVisible();
    await expect(page.locator('[data-testid="event-posted"]')).toBeVisible();
    
    // Check event details
    await expect(page.locator('[data-testid="event-created"] [data-testid="actor"]')).toContainText(user.name);
    await expect(page.locator('[data-testid="event-created"] [data-testid="timestamp"]')).toBeVisible();
    
    // Click on an event to see details
    await page.click('[data-testid="event-approved"]');
    await expect(page.locator('[data-testid="event-details-modal"]')).toBeVisible();
    await expect(page.locator('[data-testid="event-details"]')).toContainText('Looks good to me');
    
    // Close modal
    await page.click('[data-testid="close-modal-btn"]');
    await expect(page.locator('[data-testid="event-details-modal"]')).toBeHidden();
  });

  test('validates form inputs correctly', async ({ page }) => {
    // Navigate to create page
    await page.click('[data-testid="create-journal-entry-btn"]');
    
    // Try to save without required fields
    await page.click('[data-testid="save-draft-btn"]');
    
    // Should show validation errors
    await expect(page.locator('[data-testid="description-error"]')).toBeVisible();
    await expect(page.locator('[data-testid="date-error"]')).toBeVisible();
    await expect(page.locator('[data-testid="type-error"]')).toBeVisible();
    await expect(page.locator('[data-testid="lines-error"]')).toBeVisible();
    
    // Fill required fields
    await page.fill('[data-testid="journal-description"]', 'Test Entry');
    await page.fill('[data-testid="journal-date"]', '2025-01-15');
    await page.selectOption('[data-testid="journal-type"]', 'adjustment');
    
    // Add a line
    await page.click('[data-testid="add-line-btn"]');
    await page.selectOption('[data-testid="line-0-account"]', '1000');
    await page.selectOption('[data-testid="line-0-debit-credit"]', 'debit');
    await page.fill('[data-testid="line-0-amount"]', '1000.00');
    
    // Validation errors should be gone
    await expect(page.locator('[data-testid="description-error"]')).toBeHidden();
    await expect(page.locator('[data-testid="date-error"]')).toBeHidden();
    await expect(page.locator('[data-testid="type-error"]')).toBeHidden();
    await expect(page.locator('[data-testid="lines-error"]')).toBeHidden();
    
    // But should still show balance error
    await expect(page.locator('[data-testid="balance-error"]')).toBeVisible();
  });
});

// Helper functions
async function createBalancedJournalEntry(page: any, description = 'Test Journal Entry') {
  await page.click('[data-testid="create-journal-entry-btn"]');
  await page.fill('[data-testid="journal-description"]', description);
  await page.fill('[data-testid="journal-date"]', '2025-01-15');
  await page.selectOption('[data-testid="journal-type"]', 'adjustment');
  
  // Add balanced lines
  await page.click('[data-testid="add-line-btn"]');
  await page.selectOption('[data-testid="line-0-account"]', '1000');
  await page.selectOption('[data-testid="line-0-debit-credit"]', 'debit');
  await page.fill('[data-testid="line-0-amount"]', '1000.00');
  
  await page.click('[data-testid="add-line-btn"]');
  await page.selectOption('[data-testid="line-1-account"]', '4000');
  await page.selectOption('[data-testid="line-1-debit-credit"]', 'credit');
  await page.fill('[data-testid="line-1-amount"]', '1000.00');
  
  await page.click('[data-testid="save-draft-btn"]');
  await page.waitForURL(/.*\/journal-entries\/[a-f0-9-]{36}$/);
}

async function createAndPostJournalEntry(page: any) {
  await createBalancedJournalEntry(page);
  
  // Submit for approval
  await page.click('[data-testid="submit-approval-btn"]');
  await page.fill('[data-testid="submit-note"]', 'Ready for review');
  await page.click('[data-testid="confirm-submit-btn"]');
  
  // Approve
  await page.click('[data-testid="approve-btn"]');
  await page.fill('[data-testid="approval-note"]', 'Approved');
  await page.click('[data-testid="confirm-approve-btn"]');
  
  // Post
  await page.click('[data-testid="post-btn"]');
  await page.fill('[data-testid="post-note"]', 'Posted');
  await page.click('[data-testid="confirm-post-btn"]');
  
  // Wait for posting to complete
  await expect(page.locator('[data-testid="journal-status"]')).toContainText('Posted');
}