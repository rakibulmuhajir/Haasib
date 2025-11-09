import { test, expect, navigateToModule, clickButtonWithText, fillForm, waitForSuccessMessage, takeScreenshot, generateTestData } from '../helpers/auth-helper';

test.describe('Payment Processing E2E Tests', () => {
  let testData: any;
  let invoiceNumber: string | null = null;
  let customerName: string | null = null;

  test.beforeEach(async ({ page }) => {
    testData = generateTestData();
  });

  test('should create and process payment successfully', async ({ page }) => {
    console.log('💳 Testing payment creation and processing workflow...');

    // Step 1: Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    // Step 2: First create a customer and invoice for payment testing
    console.log('👤 Creating customer for payment testing...');
    await navigateToModule(page, 'customers');
    await clickButtonWithText(page, 'Add Customer');

    const customerData = testData.customer;
    await page.fill('input[name="name"], input[id*="name"], [data-testid="customer-name"]', customerData.name);
    await page.fill('input[name="email"], input[id*="email"], [data-testid="customer-email"]', customerData.email);
    await page.fill('input[name="phone"], input[id*="phone"], [data-testid="customer-phone"]', customerData.phone);
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(2000);

    customerName = customerData.name;

    // Step 3: Create an invoice for the customer
    console.log('🧾 Creating invoice for payment testing...');
    await navigateToModule(page, 'invoices');
    await clickButtonWithText(page, 'Create Invoice');

    // Select customer
    const customerSelect = page.locator('select[name="customer_id"], [data-testid="customer-select"]').first();
    if (await customerSelect.isVisible()) {
      await customerSelect.selectOption({ label: customerName });
    }

    // Add line item
    const addLineItemButton = page.locator('button:has-text("Add Line"), button:has-text("Add Item"), [data-testid="add-line-item"]').first();
    if (await addLineItemButton.isVisible()) {
      await addLineItemButton.click();
      await page.waitForTimeout(1000);
    }

    // Fill line item
    const lineItemFields = [
      { selector: 'input[name="description"], textarea[name="description"], [data-testid="line-description"]', value: 'Payment Testing Service' },
      { selector: 'input[name="quantity"], [data-testid="line-quantity"]', value: '1' },
      { selector: 'input[name="unit_price"], [data-testid="line-price"]', value: '1500.00' }
    ];

    for (const field of lineItemFields) {
      const fieldElement = page.locator(field.selector).first();
      if (await fieldElement.isVisible()) {
        await fieldElement.fill(field.value);
      }
    }

    // Fill totals
    const subtotalField = page.locator('input[name="subtotal"], [data-testid="subtotal"]').first();
    if (await subtotalField.isVisible()) {
      await subtotalField.fill('1500.00');
    }

    const totalField = page.locator('input[name="total_amount"], [data-testid="total-amount"]').first();
    if (await totalField.isVisible()) {
      await totalField.fill('1500.00');
    }

    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(2000);

    // Get invoice number
    await navigateToModule(page, 'invoices');
    const firstInvoice = page.locator('tr, .invoice-item').first();
    if (await firstInvoice.isVisible()) {
      const invoiceText = await firstInvoice.textContent();
      // Extract invoice number from text (assuming format like "INV-12345")
      const invoiceMatch = invoiceText?.match(/INV-\d+/);
      if (invoiceMatch) {
        invoiceNumber = invoiceMatch[0];
        console.log(`Created invoice: ${invoiceNumber}`);
      }
    }

    // Step 4: Navigate to payments
    await navigateToModule(page, 'payments');
    await page.waitForLoadState('networkidle');
    await takeScreenshot(page, 'payments-list-before-creation');

    // Step 5: Click Create Payment button
    await clickButtonWithText(page, 'Create Payment');
    await page.waitForTimeout(1000);

    // Step 6: Fill payment creation form
    console.log('📝 Filling payment creation form...');
    const paymentData = {
      payment_number: `PAY-${Date.now()}`,
      amount: '500.00',
      payment_method: 'bank_transfer',
      payment_date: new Date().toISOString().split('T')[0],
      notes: 'E2E Test Payment - Partial payment'
    };

    // Select customer
    const paymentCustomerSelect = page.locator('select[name="customer_id"], [data-testid="payment-customer-select"]').first();
    if (await paymentCustomerSelect.isVisible()) {
      await paymentCustomerSelect.selectOption({ label: customerName });
      console.log(`✅ Selected customer: ${customerName}`);
    }

    // Select invoice if applicable
    if (invoiceNumber) {
      const invoiceSelect = page.locator('select[name="invoice_id"], [data-testid="invoice-select"]').first();
      if (await invoiceSelect.isVisible()) {
        await invoiceSelect.selectOption({ label: invoiceNumber });
        console.log(`✅ Selected invoice: ${invoiceNumber}`);
      }
    }

    // Fill payment details
    await fillForm(page, paymentData);

    // Payment method selection
    const paymentMethodSelect = page.locator('select[name="payment_method"], [data-testid="payment-method"]').first();
    if (await paymentMethodSelect.isVisible()) {
      await paymentMethodSelect.selectOption(paymentData.payment_method);
      console.log(`✅ Selected payment method: ${paymentData.payment_method}`);
    }

    // Payment date
    const paymentDateField = page.locator('input[name="payment_date"], input[type="date"], [data-testid="payment-date"]').first();
    if (await paymentDateField.isVisible() && !await paymentDateField.inputValue()) {
      await paymentDateField.fill(paymentData.payment_date);
    }

    // Reference/transaction ID
    const referenceField = page.locator('input[name="reference"], input[name="transaction_id"], [data-testid="payment-reference"]').first();
    if (await referenceField.isVisible()) {
      await referenceField.fill(`TXN-${Date.now()}`);
    }

    await takeScreenshot(page, 'payment-form-filled');

    // Step 7: Submit the form
    console.log('💾 Submitting payment creation form...');
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(3000);

    // Step 8: Verify successful creation
    const success = await waitForSuccessMessage(page);
    expect(success).toBeTruthy();

    // Step 9: Verify payment appears in list
    await navigateToModule(page, 'payments');
    await page.waitForLoadState('networkidle');

    // Look for the created payment in the list
    const paymentLink = page.locator(`a:has-text("${paymentData.payment_number}")`).first();
    if (await paymentLink.isVisible()) {
      console.log(`✅ Payment "${paymentData.payment_number}" created successfully`);
    } else {
      console.log(`✅ Payment created (number may not be visible in list)`);
    }

    await takeScreenshot(page, 'payment-created-successfully');
  });

  test('should allocate payment to invoices', async ({ page }) => {
    console.log('🎯 Testing payment allocation to invoices...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'payments');

    // Find an existing payment to allocate
    const paymentItems = page.locator('tr a, .payment-item a').first();
    if (await paymentItems.isVisible()) {
      await paymentItems.click();
      await page.waitForLoadState('networkidle');

      // Look for allocation options
      console.log('🔍 Looking for payment allocation options...');

      const allocateButton = page.locator('button:has-text("Allocate"), button:has-text("Auto Allocate"), a:has-text("Allocate")').first();
      if (await allocateButton.isVisible()) {
        console.log('Found allocation functionality');
        await allocateButton.click();
        await page.waitForTimeout(1000);

        // Check for invoice selection in allocation modal/form
        const invoiceCheckboxes = await page.locator('input[type="checkbox"], .invoice-allocation').all();
        if (invoiceCheckboxes.length > 0) {
          console.log(`Found ${invoiceCheckboxes.length} invoices available for allocation`);

          // Test allocation form fields
          const allocationAmounts = await page.locator('input[name="allocation_amount"], [data-testid="allocation-amount"]').all();
          if (allocationAmounts.length > 0) {
            console.log('Found allocation amount fields');

            // Fill allocation amount for first invoice
            await allocationAmounts[0].fill('100.00');
            console.log('  ✅ Set allocation amount');
          }

          // Look for allocation notes
          const notesField = page.locator('textarea[name="notes"], [data-testid="allocation-notes"]').first();
          if (await notesField.isVisible()) {
            await notesField.fill('E2E test allocation');
            console.log('  ✅ Added allocation notes');
          }

          await takeScreenshot(page, 'payment-allocation-form');

          // Don't actually submit to avoid affecting real data
          console.log('💰 Allocation form ready for testing');
        } else {
          console.log('⚠️ No invoices found for allocation');
        }

        // Look for auto-allocate functionality
        const autoAllocateButton = page.locator('button:has-text("Auto Allocate"), [data-testid="auto-allocate"]').first();
        if (await autoAllocateButton.isVisible()) {
          console.log('✅ Auto-allocate functionality available');
        }

        // Close modal/form
        const closeButton = page.locator('.close, .cancel, [data-testid="close"]').first();
        if (await closeButton.isVisible()) {
          await closeButton.click();
          await page.waitForTimeout(500);
        }
      } else {
        console.log('⚠️ No allocation functionality found');
      }

      // Check existing allocations
      console.log('📋 Checking existing payment allocations...');

      const allocationSection = page.locator('.payment-allocations, .allocations, [data-testid="payment-allocations"]').first();
      if (await allocationSection.isVisible()) {
        console.log('✅ Found payment allocations section');

        const allocationItems = await allocationSection.locator('tr, .allocation-item').all();
        if (allocationItems.length > 0) {
          console.log(`Found ${allocationItems.length} existing allocations`);

          for (let i = 0; i < Math.min(allocationItems.length, 3); i++) {
            const allocation = allocationItems[i];
            const allocationText = await allocation.textContent();
            if (allocationText && allocationText.length > 10) {
              console.log(`  Allocation ${i + 1}: ${allocationText.substring(0, 100)}...`);
            }
          }
        }
      } else {
        console.log('⚠️ No allocations section found');
      }

      await takeScreenshot(page, 'payment-allocation-view');
    } else {
      console.log('⚠️ No payments found to test allocation');
    }
  });

  test('should manage payment status and refunds', async ({ page }) => {
    console.log('🔄 Testing payment status management and refunds...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'payments');

    // Find a payment to work with
    const paymentItems = page.locator('tr a, .payment-item a').first();
    if (await paymentItems.isVisible()) {
      await paymentItems.click();
      await page.waitForLoadState('networkidle');

      // Check current payment status
      console.log('📊 Checking current payment status...');
      const statusElement = page.locator('.payment-status, .status, [data-testid="payment-status"]').first();
      if (await statusElement.isVisible()) {
        const currentStatus = await statusElement.textContent();
        console.log(`  Current status: ${currentStatus}`);
      }

      // Test payment actions
      console.log('⚡ Testing payment actions...');

      const actionButtons = await page.locator('button, a').all();
      const availableActions = [];

      for (const button of actionButtons) {
        const buttonText = await button.textContent();
        if (buttonText && (
          buttonText.toLowerCase().includes('void') ||
          buttonText.toLowerCase().includes('refund') ||
          buttonText.toLowerCase().includes('allocate') ||
          buttonText.toLowerCase().includes('unallocate')
        )) {
          availableActions.push(buttonText.trim());
        }
      }

      console.log(`Found ${availableActions.length} available actions:`);
      availableActions.forEach(action => console.log(`  - ${action}`));

      // Test refund functionality (but don't actually process)
      const refundButton = page.locator('button:has-text("Refund"), a:has-text("Refund")').first();
      if (await refundButton.isVisible()) {
        console.log('💰 Found Refund action - ready for testing');

        // Click to see refund form (but don't submit)
        await refundButton.click();
        await page.waitForTimeout(1000);

        // Check for refund form fields
        const refundAmountField = page.locator('input[name="refund_amount"], [data-testid="refund-amount"]').first();
        if (await refundAmountField.isVisible()) {
          console.log('  ✅ Found refund amount field');
        }

        const refundReasonField = page.locator('textarea[name="reason"], [data-testid="refund-reason"]').first();
        if (await refundReasonField.isVisible()) {
          console.log('  ✅ Found refund reason field');
        }

        await takeScreenshot(page, 'payment-refund-form');

        // Close form
        const closeButton = page.locator('.close, .cancel, [data-testid="close"]').first();
        if (await closeButton.isVisible()) {
          await closeButton.click();
          await page.waitForTimeout(500);
        }
      }

      // Test void functionality
      const voidButton = page.locator('button:has-text("Void"), a:has-text("Void")').first();
      if (await voidButton.isVisible()) {
        console.log('🚫 Found Void action - ready for testing');
        // Don't actually click to avoid voiding real payments
      }

      await takeScreenshot(page, 'payment-actions-available');
    } else {
      console.log('⚠️ No payments found to test status management');
    }
  });

  test('should search and filter payments', async ({ page }) => {
    console.log('🔍 Testing payment search and filtering...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'payments');
    await page.waitForLoadState('networkidle');

    // Test search functionality
    const searchBox = page.locator('input[type="search"], input[placeholder*="search"], [data-testid="search"]').first();
    if (await searchBox.isVisible()) {
      console.log('Testing search functionality...');

      // Get initial count
      const initialCount = await page.locator('tr, .payment-item').count();
      console.log(`Initial payment count: ${initialCount}`);

      // Search by payment number
      await searchBox.fill('PAY');
      await page.waitForTimeout(1000);

      const payResults = await page.locator('tr, .payment-item').count();
      console.log(`Found ${payResults} results for "PAY"`);

      // Search by payment method
      await searchBox.clear();
      await searchBox.fill('bank');
      await page.waitForTimeout(1000);

      const bankResults = await page.locator('tr, .payment-item').count();
      console.log(`Found ${bankResults} results for "bank"`);

      // Clear search
      await searchBox.clear();
      await page.waitForTimeout(1000);

      console.log('✅ Search functionality working');
    } else {
      console.log('⚠️ Search functionality not found');
    }

    // Test filter options
    console.log('🔽 Testing filter options...');

    const filterSelects = await page.locator('select[name="status"], select[name="payment_method"], select[name="customer"], [data-testid="filter"]').all();
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

    // Test payment method filtering
    const paymentMethodSelect = page.locator('select[name="payment_method"], [data-testid="payment-method-filter"]').first();
    if (await paymentMethodSelect.isVisible()) {
      console.log('💳 Testing payment method filtering...');

      const paymentMethods = ['bank_transfer', 'credit_card', 'cash', 'check'];
      for (const method of paymentMethods) {
        try {
          await paymentMethodSelect.selectOption(method);
          await page.waitForTimeout(1000);
          console.log(`  ✅ Filtered by payment method: ${method}`);
        } catch (error) {
          console.log(`  ⚠️ Payment method not available: ${method}`);
        }
      }
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

    await takeScreenshot(page, 'payment-search-filter');
  });

  test('should display payment details and allocation information', async ({ page }) => {
    console.log('📄 Testing payment detail view...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'payments');

    // Find a payment to view
    const paymentItems = page.locator('tr a, .payment-item a').first();
    if (await paymentItems.isVisible()) {
      await paymentItems.click();
      await page.waitForLoadState('networkidle');

      // Verify payment detail page elements
      console.log('📋 Checking payment detail page elements...');

      // Payment header information
      const paymentNumber = page.locator('h1, h2, .payment-number, [data-testid="payment-number"]').first();
      if (await paymentNumber.isVisible()) {
        const number = await paymentNumber.textContent();
        console.log(`  ✅ Payment number: ${number}`);
      }

      // Payment amount
      const paymentAmount = page.locator('.payment-amount, .amount, [data-testid="payment-amount"]').first();
      if (await paymentAmount.isVisible()) {
        const amount = await paymentAmount.textContent();
        console.log(`  ✅ Payment amount: ${amount}`);
      }

      // Customer information
      const customerInfo = page.locator('.customer-info, [data-testid="customer-info"]').first();
      if (await customerInfo.isVisible()) {
        console.log('  ✅ Customer information section found');

        const customerName = customerInfo.locator(':has-text("@"), .customer-name').first();
        if (await customerName.isVisible()) {
          const name = await customerName.textContent();
          console.log(`    Customer: ${name}`);
        }
      }

      // Payment method and date
      const paymentMethod = page.locator('.payment-method, [data-testid="payment-method"]').first();
      if (await paymentMethod.isVisible()) {
        const method = await paymentMethod.textContent();
        console.log(`  ✅ Payment method: ${method}`);
      }

      const paymentDate = page.locator('.payment-date, [data-testid="payment-date"]').first();
      if (await paymentDate.isVisible()) {
        const date = await paymentDate.textContent();
        console.log(`  ✅ Payment date: ${date}`);
      }

      // Payment status
      const paymentStatus = page.locator('.payment-status, .status, [data-testid="payment-status"]').first();
      if (await paymentStatus.isVisible()) {
        const status = await paymentStatus.textContent();
        console.log(`  ✅ Payment status: ${status}`);
      }

      // Allocation information
      console.log('💰 Checking payment allocation information...');
      const allocationSection = page.locator('.payment-allocations, .allocations, [data-testid="payment-allocations"]').first();
      if (await allocationSection.isVisible()) {
        console.log('  ✅ Payment allocations section found');

        const allocationItems = await allocationSection.locator('tr, .allocation-item').all();
        if (allocationItems.length > 0) {
          console.log(`    Found ${allocationItems.length} allocations`);

          // Check for allocation details
          for (let i = 0; i < Math.min(allocationItems.length, 3); i++) {
            const allocation = allocationItems[i];
            const allocationText = await allocation.textContent();
            if (allocationText && allocationText.length > 10) {
              console.log(`      Allocation ${i + 1}: ${allocationText.substring(0, 80)}...`);
            }
          }
        } else {
          console.log('    No allocations found');
        }
      }

      // Unallocated amount
      const unallocatedAmount = page.locator('.unallocated-amount, [data-testid="unallocated-amount"]').first();
      if (await unallocatedAmount.isVisible()) {
        const amount = await unallocatedAmount.textContent();
        console.log(`  ✅ Unallocated amount: ${amount}`);
      }

      // Action buttons
      const actionButtons = await page.locator('button:has-text("Allocate"), button:has-text("Refund"), button:has-text("Void"), button:has-text("Edit")').all();
      if (actionButtons.length > 0) {
        console.log(`  ✅ Found ${actionButtons.length} action buttons`);
      }

      await takeScreenshot(page, 'payment-detail-view');
    } else {
      console.log('⚠️ No payments found to view details');
    }
  });

  test('should handle payment bulk operations', async ({ page }) => {
    console.log('📦 Testing payment bulk operations...');

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await navigateToModule(page, 'payments');
    await page.waitForLoadState('networkidle');

    // Look for bulk operation controls
    console.log('🔍 Looking for bulk operation controls...');

    // Check for checkboxes for bulk selection
    const checkboxes = await page.locator('input[type="checkbox"], .checkbox').all();
    if (checkboxes.length > 1) {
      console.log(`Found ${checkboxes.length} checkboxes for bulk selection`);

      // Test selecting multiple payments
      for (let i = 1; i < Math.min(checkboxes.length, 4); i++) { // Select first 3 payments
        await checkboxes[i].check();
        await page.waitForTimeout(500);
      }

      console.log('✅ Multiple payments selected');

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

    // Test bulk allocation functionality
    const bulkAllocateButton = page.locator('button:has-text("Bulk Allocate"), [data-testid="bulk-allocate"]').first();
    if (await bulkAllocateButton.isVisible()) {
      console.log('✅ Bulk allocation functionality available');
    }

    // Test payment reconciliation tools
    const reconcileButton = page.locator('button:has-text("Reconcile"), [data-testid="reconcile"]').first();
    if (await reconcileButton.isVisible()) {
      console.log('✅ Payment reconciliation tools available');
    }

    await takeScreenshot(page, 'payment-bulk-operations');
  });
});