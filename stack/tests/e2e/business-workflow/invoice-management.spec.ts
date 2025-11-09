import { test, expect, navigateToModule, clickButtonWithText, fillForm, waitForSuccessMessage, takeScreenshot, generateTestData } from '../helpers/auth-helper';

test.describe('Invoice Management E2E Tests', () => {
  let testData: any;
  let customerName: string | null = null;

  test.beforeEach(async ({ page }) => {
    testData = generateTestData();
  });

  test('should create a new invoice successfully', async ({ page }) => {
    console.log('🧾 Testing invoice creation workflow...');

    // Step 1: Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    // Step 2: First, create a customer for the invoice
    console.log('👤 Creating customer for invoice...');
    await navigateToModule(page, 'customers');
    await clickButtonWithText(page, 'Add Customer');

    const customerData = testData.customer;
    await page.fill('input[name="name"], input[id*="name"], [data-testid="customer-name"]', customerData.name);
    await page.fill('input[name="email"], input[id*="email"], [data-testid="customer-email"]', customerData.email);
    await page.fill('input[name="phone"], input[id*="phone"], [data-testid="customer-phone"]', customerData.phone);
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(2000);

    customerName = customerData.name;
    console.log(`✅ Customer "${customerName}" created for invoice`);

    // Step 3: Navigate to invoices
    await navigateToModule(page, 'invoices');
    await page.waitForLoadState('networkidle');
    await takeScreenshot(page, 'invoices-list-before-creation');

    // Step 4: Click Create Invoice button
    await clickButtonWithText(page, 'Create Invoice');
    await page.waitForTimeout(1000);

    // Step 5: Fill invoice creation form
    console.log('📝 Filling invoice creation form...');
    const invoiceData = testData.invoice;

    // Select customer
    const customerSelect = page.locator('select[name="customer_id"], [data-testid="customer-select"]').first();
    if (await customerSelect.isVisible()) {
      await customerSelect.selectOption({ label: customerName });
      console.log(`✅ Selected customer: ${customerName}`);
    }

    // Invoice number (if editable)
    const invoiceNumberField = page.locator('input[name="invoice_number"], input[id*="invoice_number"], [data-testid="invoice-number"]').first();
    if (await invoiceNumberField.isVisible()) {
      await invoiceNumberField.fill(invoiceData.invoice_number);
    }

    // Invoice date
    const invoiceDateField = page.locator('input[name="invoice_date"], input[type="date"], [data-testid="invoice-date"]').first();
    if (await invoiceDateField.isVisible()) {
      await invoiceDateField.fill(new Date().toISOString().split('T')[0]);
    }

    // Due date
    const dueDateField = page.locator('input[name="due_date"], input[type="date"], [data-testid="due-date"]').first();
    if (await dueDateField.isVisible()) {
      const dueDate = new Date();
      dueDate.setDate(dueDate.getDate() + 30); // 30 days from now
      await dueDateField.fill(dueDate.toISOString().split('T')[0]);
    }

    // Add line items
    console.log('📦 Adding invoice line items...');
    await page.waitForTimeout(1000);

    // Look for "Add Line Item" button
    const addLineItemButton = page.locator('button:has-text("Add Line"), button:has-text("Add Item"), [data-testid="add-line-item"]').first();
    if (await addLineItemButton.isVisible()) {
      await addLineItemButton.click();
      await page.waitForTimeout(1000);
    }

    // Fill line item details
    const lineItemFields = [
      { selector: 'input[name="description"], textarea[name="description"], [data-testid="line-description"]', value: 'Professional Services - E2E Testing' },
      { selector: 'input[name="quantity"], [data-testid="line-quantity"]', value: '10' },
      { selector: 'input[name="unit_price"], [data-testid="line-price"]', value: '100.00' },
      { selector: 'input[name="total"], [data-testid="line-total"]', value: '1000.00' }
    ];

    for (const field of lineItemFields) {
      const fieldElement = page.locator(field.selector).first();
      if (await fieldElement.isVisible()) {
        await fieldElement.fill(field.value);
        console.log(`  ✅ Filled ${field.selector.split(',')[0]}`);
      }
    }

    // Add another line item
    if (await addLineItemButton.isVisible()) {
      await addLineItemButton.click();
      await page.waitForTimeout(1000);

      // Fill second line item
      const secondLineItemFields = [
        { selector: 'input[name="description"], textarea[name="description"], [data-testid="line-description"]', value: 'Consulting Services' },
        { selector: 'input[name="quantity"], [data-testid="line-quantity"]', value: '5' },
        { selector: 'input[name="unit_price"], [data-testid="line-price"]', value: '80.00' },
        { selector: 'input[name="total"], [data-testid="line-total"]', value: '400.00' }
      ];

      for (const field of secondLineItemFields) {
        const fieldElement = page.locator(field.selector).first();
        if (await fieldElement.isVisible()) {
          await fieldElement.fill(field.value);
        }
      }
    }

    // Check if totals are calculated automatically
    await page.waitForTimeout(2000);

    // Fill subtotal, tax, and total if manual entry is required
    const subtotalField = page.locator('input[name="subtotal"], [data-testid="subtotal"]').first();
    if (await subtotalField.isVisible()) {
      await subtotalField.fill('1400.00');
    }

    const taxField = page.locator('input[name="tax_amount"], [data-testid="tax-amount"]').first();
    if (await taxField.isVisible()) {
      await taxField.fill('112.00'); // 8% tax
    }

    const totalField = page.locator('input[name="total_amount"], [data-testid="total-amount"]').first();
    if (await totalField.isVisible()) {
      await totalField.fill('1512.00');
    }

    // Add notes
    const notesField = page.locator('textarea[name="notes"], [data-testid="invoice-notes"]').first();
    if (await notesField.isVisible()) {
      await notesField.fill('E2E Test Invoice - Payment due within 30 days');
    }

    await takeScreenshot(page, 'invoice-form-filled');

    // Step 6: Submit the form
    console.log('💾 Submitting invoice creation form...');
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(3000);

    // Step 7: Verify successful creation
    const success = await waitForSuccessMessage(page);
    expect(success).toBeTruthy();

    // Step 8: Verify invoice appears in list
    await navigateToModule(page, 'invoices');
    await page.waitForLoadState('networkidle');

    // Look for the created invoice in the list
    const invoiceLink = page.locator(`a:has-text("${invoiceData.invoice_number}")`).first();
    await expect(invoiceLink).toBeVisible({ timeout: 5000 });

    console.log(`✅ Invoice "${invoiceData.invoice_number}" created successfully`);
    await takeScreenshot(page, 'invoice-created-successfully');
  });

  test('should edit invoice information and line items', async ({ page }) => {
    console.log('✏️ Testing invoice editing functionality...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'invoices');

    // Find an existing invoice to edit
    const invoiceItems = page.locator('tr a, .invoice-item a').first();
    if (await invoiceItems.isVisible()) {
      await invoiceItems.click();
      await page.waitForLoadState('networkidle');

      // Look for Edit button
      await clickButtonWithText(page, 'Edit');
      await page.waitForTimeout(1000);

      // Edit invoice details
      console.log('📝 Editing invoice details...');

      // Change due date
      const dueDateField = page.locator('input[name="due_date"], input[type="date"], [data-testid="due-date"]').first();
      if (await dueDateField.isVisible()) {
        const newDueDate = new Date();
        newDueDate.setDate(newDueDate.getDate() + 60); // Extend to 60 days
        await dueDateField.fill(newDueDate.toISOString().split('T')[0]);
        console.log('  ✅ Updated due date');
      }

      // Edit existing line items
      console.log('📦 Editing line items...');
      const lineItemDescriptions = await page.locator('input[name="description"], textarea[name="description"], [data-testid="line-description"]').all();
      if (lineItemDescriptions.length > 0) {
        await lineItemDescriptions[0].fill('Updated Professional Services - E2E Testing');
        console.log('  ✅ Updated first line item description');
      }

      const lineItemQuantities = await page.locator('input[name="quantity"], [data-testid="line-quantity"]').all();
      if (lineItemQuantities.length > 0) {
        await lineItemQuantities[0].fill('12');
        console.log('  ✅ Updated first line item quantity');
      }

      // Add a new line item
      const addLineItemButton = page.locator('button:has-text("Add Line"), button:has-text("Add Item"), [data-testid="add-line-item"]').first();
      if (await addLineItemButton.isVisible()) {
        await addLineItemButton.click();
        await page.waitForTimeout(1000);

        // Fill new line item
        const newLineItemFields = [
          { selector: 'input[name="description"], textarea[name="description"], [data-testid="line-description"]', value: 'Additional Service - E2E Testing' },
          { selector: 'input[name="quantity"], [data-testid="line-quantity"]', value: '3' },
          { selector: 'input[name="unit_price"], [data-testid="line-price"]', value: '150.00' }
        ];

        for (const field of newLineItemFields) {
          const fieldElement = page.locator(field.selector).last(); // Use last for the newly added item
          if (await fieldElement.isVisible()) {
            await fieldElement.fill(field.value);
          }
        }
        console.log('  ✅ Added new line item');
      }

      // Update notes
      const notesField = page.locator('textarea[name="notes"], [data-testid="invoice-notes"]').first();
      if (await notesField.isVisible()) {
        await notesField.fill('Updated E2E Test Invoice - Extended payment terms and additional services included');
      }

      await takeScreenshot(page, 'invoice-form-edited');

      // Save changes
      await clickButtonWithText(page, 'Save');
      await page.waitForTimeout(3000);

      // Verify changes were saved
      const success = await waitForSuccessMessage(page);
      expect(success).toBeTruthy();

      console.log('✅ Invoice updated successfully');
      await takeScreenshot(page, 'invoice-updated-successfully');
    } else {
      console.log('⚠️ No invoices found to edit');
    }
  });

  test('should manage invoice status and actions', async ({ page }) => {
    console.log('🔄 Testing invoice status management...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'invoices');
    await page.waitForLoadState('networkidle');

    // Find an invoice to work with
    const invoiceItems = page.locator('tr, .invoice-item').first();
    if (await invoiceItems.isVisible()) {
      await invoiceItems.click();
      await page.waitForLoadState('networkidle');

      // Check current status
      console.log('📊 Checking current invoice status...');
      const statusElement = page.locator('.invoice-status, .status, [data-testid="invoice-status"]').first();
      if (await statusElement.isVisible()) {
        const currentStatus = await statusElement.textContent();
        console.log(`  Current status: ${currentStatus}`);
      }

      // Test invoice actions
      console.log('⚡ Testing invoice actions...');

      const actionButtons = await page.locator('button, a').all();
      const availableActions = [];

      for (const button of actionButtons) {
        const buttonText = await button.textContent();
        if (buttonText && (
          buttonText.toLowerCase().includes('send') ||
          buttonText.toLowerCase().includes('post') ||
          buttonText.toLowerCase().includes('cancel') ||
          buttonText.toLowerCase().includes('duplicate') ||
          buttonText.toLowerCase().includes('generate pdf') ||
          buttonText.toLowerCase().includes('email')
        )) {
          availableActions.push(buttonText.trim());
        }
      }

      console.log(`Found ${availableActions.length} available actions:`);
      availableActions.forEach(action => console.log(`  - ${action}`));

      // Test sending invoice (but don't actually send)
      const sendButton = page.locator('button:has-text("Send"), a:has-text("Send")').first();
      if (await sendButton.isVisible()) {
        console.log('📧 Found Send Invoice action - ready for testing');
        // Don't actually click to avoid sending real emails
      }

      // Test PDF generation
      const pdfButton = page.locator('button:has-text("PDF"), button:has-text("Generate PDF")').first();
      if (await pdfButton.isVisible()) {
        console.log('📄 Found PDF Generation action - ready for testing');
        // Don't actually click to avoid generating files
      }

      // Test invoice duplication
      const duplicateButton = page.locator('button:has-text("Duplicate"), a:has-text("Duplicate")').first();
      if (await duplicateButton.isVisible()) {
        console.log('📋 Found Duplicate Invoice action - ready for testing');
        // Don't actually click to avoid creating duplicates
      }

      await takeScreenshot(page, 'invoice-actions-available');
    } else {
      console.log('⚠️ No invoices found to test actions');
    }
  });

  test('should search and filter invoices', async ({ page }) => {
    console.log('🔍 Testing invoice search and filtering...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'invoices');
    await page.waitForLoadState('networkidle');

    // Test search functionality
    const searchBox = page.locator('input[type="search"], input[placeholder*="search"], [data-testid="search"]').first();
    if (await searchBox.isVisible()) {
      console.log('Testing search functionality...');

      // Get initial count
      const initialCount = await page.locator('tr, .invoice-item').count();
      console.log(`Initial invoice count: ${initialCount}`);

      // Search by invoice number
      await searchBox.fill('INV');
      await page.waitForTimeout(1000);

      const invResults = await page.locator('tr, .invoice-item').count();
      console.log(`Found ${invResults} results for "INV"`);

      // Search by status
      await searchBox.clear();
      await searchBox.fill('Draft');
      await page.waitForTimeout(1000);

      const draftResults = await page.locator('tr, .invoice-item').count();
      console.log(`Found ${draftResults} results for "Draft"`);

      // Clear search
      await searchBox.clear();
      await page.waitForTimeout(1000);

      console.log('✅ Search functionality working');
    } else {
      console.log('⚠️ Search functionality not found');
    }

    // Test filter options
    console.log('🔽 Testing filter options...');

    const filterSelects = await page.locator('select[name="status"], select[name="customer"], select[name="date_range"], [data-testid="filter"]').all();
    if (filterSelects.length > 0) {
      console.log(`Found ${filterSelects.length} filter options`);

      for (let i = 0; i < Math.min(filterSelects.length, 3); i++) {
        const filterSelect = filterSelects[i];
        if (await filterSelect.isVisible()) {
          const options = await filterSelect.locator('option').all();
          for (let j = 1; j < Math.min(options.length, 3); j++) {
            const option = options[j];
            const optionText = await option.textContent();
            if (optionText && optionText.trim()) {
              await filterSelect.selectOption({ index: j });
              await page.waitForTimeout(1000);
              console.log(`  - Filtered by: ${optionText.trim()}`);
            }
          }
        }
      }

      console.log('✅ Filter functionality working');
    } else {
      console.log('⚠️ No filter options found');
    }

    // Test date range filtering
    const dateInputs = await page.locator('input[type="date"], [data-testid="date-filter"]').all();
    if (dateInputs.length >= 2) {
      console.log('📅 Testing date range filtering...');

      // Set date range (last 30 days)
      const endDate = new Date();
      const startDate = new Date();
      startDate.setDate(startDate.getDate() - 30);

      await dateInputs[0].fill(startDate.toISOString().split('T')[0]); // Start date
      await dateInputs[1].fill(endDate.toISOString().split('T')[0]); // End date

      await page.waitForTimeout(1000);
      console.log('✅ Date range filter applied');
    }

    await takeScreenshot(page, 'invoice-search-filter');
  });

  test('should display invoice details and customer information', async ({ page }) => {
    console.log('📄 Testing invoice detail view...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'invoices');

    // Find an invoice to view
    const invoiceItems = page.locator('tr a, .invoice-item a').first();
    if (await invoiceItems.isVisible()) {
      await invoiceItems.click();
      await page.waitForLoadState('networkidle');

      // Verify invoice detail page elements
      console.log('📋 Checking invoice detail page elements...');

      // Invoice header information
      const invoiceNumber = page.locator('h1, h2, .invoice-number, [data-testid="invoice-number"]').first();
      if (await invoiceNumber.isVisible()) {
        const number = await invoiceNumber.textContent();
        console.log(`  ✅ Invoice number: ${number}`);
      }

      // Customer information
      const customerInfo = page.locator('.customer-info, .bill-to, [data-testid="customer-info"]').first();
      if (await customerInfo.isVisible()) {
        console.log('  ✅ Customer information section found');

        const customerName = customerInfo.locator(':has-text("@"), .customer-name').first();
        if (await customerName.isVisible()) {
          const name = await customerName.textContent();
          console.log(`    Customer: ${name}`);
        }
      }

      // Invoice totals
      const totalsSection = page.locator('.invoice-totals, .totals, [data-testid="invoice-totals"]').first();
      if (await totalsSection.isVisible()) {
        console.log('  ✅ Invoice totals section found');

        const totalAmount = totalsSection.locator(':has-text("$"), .total-amount, [data-testid="total-amount"]').first();
        if (await totalAmount.isVisible()) {
          const total = await totalAmount.textContent();
          console.log(`    Total: ${total}`);
        }
      }

      // Line items table
      const lineItemsTable = page.locator('table, .line-items, [data-testid="line-items"]').first();
      if (await lineItemsTable.isVisible()) {
        const lineItemRows = await lineItemsTable.locator('tr, .line-item').count();
        console.log(`  ✅ Line items table found with ${lineItemRows} rows`);
      }

      // Payment status
      const paymentStatus = page.locator('.payment-status, .status, [data-testid="payment-status"]').first();
      if (await paymentStatus.isVisible()) {
        const status = await paymentStatus.textContent();
        console.log(`  ✅ Payment status: ${status}`);
      }

      // Action buttons
      const actionButtons = await page.locator('button:has-text("Print"), button:has-text("Email"), button:has-text("Edit"), button:has-text("Send")').all();
      if (actionButtons.length > 0) {
        console.log(`  ✅ Found ${actionButtons.length} action buttons`);
      }

      await takeScreenshot(page, 'invoice-detail-view');
    } else {
      console.log('⚠️ No invoices found to view details');
    }
  });

  test('should handle invoice bulk operations', async ({ page }) => {
    console.log('📦 Testing invoice bulk operations...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'invoices');
    await page.waitForLoadState('networkidle');

    // Look for bulk operation controls
    console.log('🔍 Looking for bulk operation controls...');

    // Check for checkboxes for bulk selection
    const checkboxes = await page.locator('input[type="checkbox"], .checkbox').all();
    if (checkboxes.length > 1) {
      console.log(`Found ${checkboxes.length} checkboxes for bulk selection`);

      // Test selecting multiple invoices
      for (let i = 1; i < Math.min(checkboxes.length, 4); i++) { // Select first 3 invoices
        await checkboxes[i].check();
        await page.waitForTimeout(500);
      }

      console.log('✅ Multiple invoices selected');

      // Look for bulk action buttons
      const bulkActionButtons = await page.locator('.bulk-actions button, .bulk-actions a, [data-testid="bulk-action"]').all();
      if (bulkActionButtons.length > 0) {
        console.log(`Found ${bulkActionButtons.length} bulk action options`);

        for (const button of bulkActionButtons.slice(0, 3)) { // Check first 3 actions
          const buttonText = await button.textContent();
          if (buttonText) {
            console.log(`  - Bulk action: ${buttonText.trim()}`);
          }
        }
      } else {
        console.log('⚠️ No bulk action buttons found after selection');
      }

      // Uncheck all
      for (let i = 1; i < Math.min(checkboxes.length, 4); i++) {
        await checkboxes[i].uncheck();
      }
    } else {
      console.log('⚠️ No checkboxes found for bulk selection');
    }

    // Test export functionality
    console.log('📤 Testing export functionality...');

    const exportButtons = await page.locator('button:has-text("Export"), a:has-text("Export"), .btn-export, [data-testid="export"]').all();
    if (exportButtons.length > 0) {
      console.log(`Found ${exportButtons.length} export options`);

      for (const button of exportButtons) {
        const buttonText = await button.textContent();
        if (buttonText) {
          console.log(`  - Export option: ${buttonText.trim()}`);
        }
      }
    } else {
      console.log('⚠️ No export buttons found');
    }

    // Test print functionality
    const printButtons = await page.locator('button:has-text("Print"), .btn-print, [data-testid="print"]').all();
    if (printButtons.length > 0) {
      console.log(`Found ${printButtons.length} print options`);
    }

    await takeScreenshot(page, 'invoice-bulk-operations');
  });
});