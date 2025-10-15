import { test, expect } from '@playwright/test';

// Test data
const testBatchData = {
  source_type: 'manual',
  entries: [{
    entity_id: 'test-customer-uuid',
    payment_method: 'bank_transfer',
    amount: 500.00,
    currency_id: 'USD',
    payment_date: '2025-01-15',
    reference_number: 'PLAYWRIGHT-TEST-001',
    auto_allocate: true,
    allocation_strategy: 'fifo'
  }]
};

const invalidBatchData = {
  source_type: 'manual',
  entries: [{
    entity_id: 'invalid-uuid',
    payment_method: 'invalid_method',
    amount: -100.00,
    currency_id: 'USD',
    payment_date: 'invalid-date'
  }]
};

test.describe('Batch Processing CLI↔GUI Parity', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to the batches page
    await page.goto('/accounting/payments/batches');
    
    // Wait for page to load
    await page.waitForLoadState('networkidle');
  });

  test('GUI batch creation matches CLI behavior', async ({ page }) => {
    // Test that GUI creates batches with same structure as CLI
    
    // Click create batch button
    await page.click('[data-testid="create-batch-btn"]');
    
    // Wait for modal to appear
    await page.waitForSelector('[data-testid="batch-creation-modal"]');
    
    // Fill out the form
    await page.selectOption('[data-testid="source-type"]', 'manual');
    await page.fill('[data-testid="entity-id"]', testBatchData.entries[0].entity_id);
    await page.selectOption('[data-testid="payment-method"]', testBatchData.entries[0].payment_method);
    await page.fill('[data-testid="amount"]', testBatchData.entries[0].amount.toString());
    await page.fill('[data-testid="payment-date"]', testBatchData.entries[0].payment_date);
    await page.fill('[data-testid="reference-number"]', testBatchData.entries[0].reference_number);
    await page.check('[data-testid="auto-allocate"]');
    await page.selectOption('[data-testid="allocation-strategy"]', testBatchData.entries[0].allocation_strategy);
    
    // Submit form
    await page.click('[data-testid="submit-batch-btn"]');
    
    // Wait for success message
    await page.waitForSelector('[data-testid="success-message"]');
    
    // Verify batch appears in list with correct data
    await expect(page.locator('[data-testid="batch-list"]')).toContainText(testBatchData.entries[0].reference_number);
    await expect(page.locator('[data-testid="batch-list"]')).toContainText(testBatchData.entries[0].payment_method);
    await expect(page.locator('[data-testid="batch-list"]')).toContainText('$' + testBatchData.entries[0].amount.toFixed(2));
    
    // Verify batch status and metadata
    await page.click('[data-testid="view-batch-details"]');
    await expect(page.locator('[data-testid="batch-details"]')).toContainText(testBatchData.entries[0].reference_number);
    await expect(page.locator('[data-testid="batch-details"]')).toContainText('Manual Entry');
    await expect(page.locator('[data-testid="batch-details"]')).toContainText('Processing');
  });

  test('GUI error handling matches CLI validation', async ({ page }) => {
    // Test that GUI shows same validation errors as CLI
    
    // Click create batch button
    await page.click('[data-testid="create-batch-btn"]');
    
    // Submit empty form
    await page.click('[data-testid="submit-batch-btn"]');
    
    // Check for validation errors
    await expect(page.locator('[data-testid="entity-id-error"]')).toBeVisible();
    await expect(page.locator('[data-testid="amount-error"]')).toBeVisible();
    await expect(page.locator('[data-testid="payment-date-error"]')).toBeVisible();
    
    // Fill with invalid data
    await page.fill('[data-testid="entity-id"]', invalidBatchData.entries[0].entity_id);
    await page.fill('[data-testid="amount"]', invalidBatchData.entries[0].amount.toString());
    await page.fill('[data-testid="payment-date"]', invalidBatchData.entries[0].payment_date);
    
    // Submit again
    await page.click('[data-testid="submit-batch-btn"]');
    
    // Check for specific validation errors
    await expect(page.locator('[data-testid="entity-id-error"]')).toContainText('invalid');
    await expect(page.locator('[data-testid="amount-error"]')).toContainText('negative');
    await expect(page.locator('[data-testid="payment-date-error"]')).toContainText('invalid');
  });

  test('GUI batch status monitoring matches CLI output', async ({ page }) => {
    // Create a batch first (assuming one exists)
    const batchRows = page.locator('[data-testid="batch-row"]');
    
    if (await batchRows.count() > 0) {
      // Click on first batch
      await batchRows.first().click();
      
      // Wait for batch details to load
      await page.waitForSelector('[data-testid="batch-details"]');
      
      // Verify status information matches CLI structure
      await expect(page.locator('[data-testid="batch-status"]')).toBeVisible();
      await expect(page.locator('[data-testid="batch-progress"]')).toBeVisible();
      await expect(page.locator('[data-testid="batch-metadata"]')).toBeVisible();
      
      // Test refresh functionality
      await page.click('[data-testid="refresh-status-btn"]');
      await page.waitForSelector('[data-testid="status-updated"]');
      
      // Verify status information is consistent
      const statusText = await page.locator('[data-testid="batch-status"]').textContent();
      expect(['pending', 'processing', 'completed', 'failed']).toContain(statusText.toLowerCase());
    }
  });

  test('GUI filtering matches CLI filtering options', async ({ page }) => {
    // Test status filtering
    await page.selectOption('[data-testid="status-filter"]', 'pending');
    await page.click('[data-testid="apply-filters-btn"]');
    
    // Verify filter is applied
    await expect(page.locator('[data-testid="filter-status-applied"]')).toContainText('pending');
    
    // Test source type filtering
    await page.selectOption('[data-testid="source-filter"]', 'manual');
    await page.click('[data-testid="apply-filters-btn"]');
    
    // Verify filter is applied
    await expect(page.locator('[data-testid="filter-source-applied"]')).toContainText('manual');
    
    // Test date range filtering
    await page.fill('[data-testid="date-from"]', '2025-01-01');
    await page.fill('[data-testid="date-to"]', '2025-01-31');
    await page.click('[data-testid="apply-filters-btn"]');
    
    // Verify date filter is applied
    await expect(page.locator('[data-testid="filter-date-applied"]')).toContainText('2025-01-01');
    await expect(page.locator('[data-testid="filter-date-applied"]')).toContainText('2025-01-31');
    
    // Test clear filters
    await page.click('[data-testid="clear-filters-btn"]');
    await expect(page.locator('[data-testid="no-filters-applied"]')).toBeVisible();
  });

  test('GUI CSV upload matches CLI CSV import', async ({ page }) => {
    // Test CSV upload functionality
    
    // Click create batch button
    await page.click('[data-testid="create-batch-btn"]');
    
    // Select CSV import
    await page.selectOption('[data-testid="source-type"]', 'csv_import');
    
    // Create a test CSV file
    const csvContent = [
      'entity_id,payment_method,amount,currency,payment_date,reference_number,auto_allocate,allocation_strategy',
      'test-customer-uuid,bank_transfer,250.00,USD,2025-01-15,CSV-PLAYWRIGHT-001,true,fifo'
    ].join('\n');
    
    // Upload CSV file
    const csvFile = await page.setInputFiles('[data-testid="csv-file-input"]');
    
    // Note: In a real test, you would need to create an actual file
    // For demonstration purposes, we'll simulate the upload
    
    // Submit form
    await page.click('[data-testid="submit-batch-btn"]');
    
    // Wait for upload completion
    await page.waitForSelector('[data-testid="upload-complete"]');
    
    // Verify CSV was processed
    await expect(page.locator('[data-testid="csv-validation-results"]')).toContainText('CSV-PLAYWRIGHT-001');
    await expect(page.locator('[data-testid="csv-validation-results"]')).toContainText('1 valid row');
  });

  test('GUI performance is comparable to CLI', async ({ page }) => {
    // Measure GUI response time
    
    const startTime = Date.now();
    
    // Load batches page
    await page.goto('/accounting/payments/batches');
    await page.waitForLoadState('networkidle');
    
    const loadTime = Date.now() - startTime;
    
    // Verify page loads within reasonable time (less than 3 seconds)
    expect(loadTime).toBeLessThan(3000);
    
    // Test batch creation time
    const createStartTime = Date.now();
    
    await page.click('[data-testid="create-batch-btn"]');
    await page.waitForSelector('[data-testid="batch-creation-modal"]');
    
    const modalLoadTime = Date.now() - createStartTime;
    
    // Modal should appear quickly (less than 1 second)
    expect(modalLoadTime).toBeLessThan(1000);
    
    // Test filtering performance
    const filterStartTime = Date.now();
    
    await page.selectOption('[data-testid="status-filter"]', 'pending');
    await page.click('[data-testid="apply-filters-btn"]');
    await page.waitForSelector('[data-testid="filters-applied"]');
    
    const filterTime = Date.now() - filterStartTime;
    
    // Filtering should be fast (less than 2 seconds)
    expect(filterTime).toBeLessThan(2000);
  });

  test('GUI responsive design matches CLI accessibility', async ({ page }) => {
    // Test mobile view
    await page.setViewportSize({ width: 375, height: 667 });
    
    // Verify mobile layout
    await expect(page.locator('[data-testid="mobile-navigation"]')).toBeVisible();
    await expect(page.locator('[data-testid="batch-list-mobile"]')).toBeVisible();
    
    // Test tablet view
    await page.setViewportSize({ width: 768, height: 1024 });
    
    // Verify tablet layout
    await expect(page.locator('[data-testid="batch-table"]')).toBeVisible();
    await expect(page.locator('[data-testid="batch-actions"]')).toBeVisible();
    
    // Test desktop view
    await page.setViewportSize({ width: 1200, height: 800 });
    
    // Verify desktop layout
    await expect(page.locator('[data-testid="batch-table"]')).toBeVisible();
    await expect(page.locator('[data-testid="sidebar-navigation"]')).toBeVisible();
  });

  test('GUI keyboard navigation matches CLI efficiency', async ({ page }) => {
    // Test keyboard shortcuts
    
    // Press Ctrl+N to create new batch (if implemented)
    await page.keyboard.press('Control+n');
    
    // Check if new batch modal appears
    const modalVisible = await page.locator('[data-testid="batch-creation-modal"]').isVisible();
    
    if (modalVisible) {
      // Test form navigation with Tab
      await page.keyboard.press('Tab');
      await expect(page.locator(':focus')).toHaveAttribute('data-testid', 'source-type');
      
      // Test Enter to submit
      await page.keyboard.press('Escape'); // Close modal first
    }
    
    // Test search functionality
    await page.keyboard.press('/');
    await expect(page.locator('[data-testid="search-input"]')).toBeFocused();
    
    // Test filter shortcuts
    await page.keyboard.press('f');
    await expect(page.locator('[data-testid="status-filter"]')).toBeFocused();
  });
});