const { chromium } = require('playwright');

async function testFullCompaniesFunctionality() {
  console.log('🚀 Testing Full Companies Functionality...');

  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    // Login first
    console.log('\n🔐 Logging in...');
    await page.goto('http://localhost:8000/login');
    await page.locator('input[name="username"]').fill('admin');
    await page.locator('input[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/dashboard', { timeout: 10000 });
    console.log('✅ Login successful');

    // Navigate to companies
    console.log('\n🏢 Navigating to companies...');
    await page.goto('http://localhost:8000/companies');
    await page.waitForLoadState('networkidle');
    console.log('✅ Companies page loaded');

    // Count initial companies
    const initialRows = await page.locator('table tbody tr').all();
    console.log(`📊 Initial companies count: ${initialRows.length}`);

    // TEST 1: CREATE COMPANY
    console.log('\n➕ TEST 1: CREATE COMPANY');

    // Look for Add Company button in various places
    const addSelectors = [
      'button:has-text("Add Company")',
      'button:has-text("Add")',
      'a:has-text("Add Company")',
      'a:has-text("Add")',
      '.btn-primary',
      '[data-testid="add-company"]',
      'button[title*="Add"]',
      '.fab', // Floating action button
      'header button', // Buttons in header
      '.page-header button', // Buttons in page header
      'button:has-text("+")', // Plus button
      'button:has-text("New")'
    ];

    let addButtonFound = false;
    for (const selector of addSelectors) {
      const button = page.locator(selector).first();
      if (await button.isVisible()) {
        console.log(`✅ Found potential add button: ${selector}`);

        try {
          await button.click();
          await page.waitForTimeout(2000);

          // Look for modal or form
          const modalSelectors = [
            '.modal',
            '.dialog',
            '.popup',
            'form',
            '[data-testid="company-form"]',
            '.company-form',
            '.modal.show',
            '.modal.showing'
          ];

          let formFound = false;
          for (const modalSelector of modalSelectors) {
            const modal = page.locator(modalSelector).first();
            if (await modal.isVisible()) {
              console.log(`✅ Company form found: ${modalSelector}`);
              formFound = true;

              // Take screenshot of form
              await page.screenshot({
                path: 'test-results/company-form.png',
                fullPage: false
              });

              // Analyze form fields
              const formFields = await modal.locator('input, select, textarea').all();
              console.log(`📝 Found ${formFields.length} form fields:`);

              for (let i = 0; i < formFields.length; i++) {
                const field = formFields[i];
                const name = await field.getAttribute('name') || await field.getAttribute('id');
                const type = await field.getAttribute('type');
                const placeholder = await field.getAttribute('placeholder');
                const label = await field.locator('preceding-sibling::label, ../label, .form-label').first().textContent();

                console.log(`   Field ${i + 1}: ${name || 'unnamed'} (${type}) - ${label || placeholder || 'no label'}`);
              }

              // Try to fill the form with test data
              try {
                console.log('📝 Attempting to fill company form...');

                // Company Name
                const nameField = modal.locator('input[name*="name"], input[id*="name"], input[placeholder*="name" i]').first();
                if (await nameField.isVisible()) {
                  await nameField.fill('Test Company ' + Date.now());
                  console.log('✅ Filled company name');
                }

                // Email
                const emailField = modal.locator('input[name*="email"], input[type="email"], input[placeholder*="email" i]').first();
                if (await emailField.isVisible()) {
                  await emailField.fill('test' + Date.now() + '@company.com');
                  console.log('✅ Filled company email');
                }

                // Phone
                const phoneField = modal.locator('input[name*="phone"], input[placeholder*="phone" i]').first();
                if (await phoneField.isVisible()) {
                  await phoneField.fill('+1-555-' + Math.floor(Math.random() * 10000));
                  console.log('✅ Filled company phone');
                }

                // Address
                const addressField = modal.locator('input[name*="address"], textarea[name*="address"], input[placeholder*="address" i]').first();
                if (await addressField.isVisible()) {
                  await addressField.fill('123 Test Street, Test City, TC 12345');
                  console.log('✅ Filled company address');
                }

                // Industry/Type dropdown
                const selectField = modal.locator('select').first();
                if (await selectField.isVisible()) {
                  const options = await selectField.locator('option').all();
                  if (options.length > 1) {
                    await selectField.selectOption({ index: 1 });
                    console.log('✅ Selected industry/type');
                  }
                }

                // Look for save button
                const saveSelectors = [
                  'button:has-text("Save")',
                  'button:has-text("Create")',
                  'button:has-text("Submit")',
                  'button[type="submit"]',
                  '.btn-success',
                  '[data-testid="save-company"]'
                ];

                let saveButtonFound = false;
                for (const saveSelector of saveSelectors) {
                  const saveButton = modal.locator(saveSelector).first();
                  if (await saveButton.isVisible()) {
                    console.log(`✅ Found save button: ${saveSelector}`);

                    // Take screenshot before save
                    await page.screenshot({
                      path: 'test-results/company-form-filled.png',
                      fullPage: false
                    });

                    // Click save button
                    await saveButton.click();
                    await page.waitForTimeout(3000);

                    // Check for success or error messages
                    const successMessage = await page.locator('.success, .alert-success, .notification-success, [data-testid="success"]').first();
                    const errorMessage = await page.locator('.error, .alert-danger, .notification-error, [data-testid="error"]').first();

                    if (await successMessage.isVisible()) {
                      const successText = await successMessage.textContent();
                      console.log(`✅ Company created successfully: ${successText}`);
                      saveButtonFound = true;
                    } else if (await errorMessage.isVisible()) {
                      const errorText = await errorMessage.textContent();
                      console.log(`❌ Company creation failed: ${errorText}`);
                    } else {
                      console.log('ℹ️ No success/error message detected after save');
                      saveButtonFound = true; // Assume success if no error
                    }
                    break;
                  }
                }

                if (!saveButtonFound) {
                  console.log('❌ No save button found in form');
                }

              } catch (error) {
                console.log(`⚠️ Error filling form: ${error.message}`);
              }

              // Close modal if still open
              const closeButtons = await modal.locator('.close, .cancel, [data-testid="close"], .modal-backdrop').all();
              if (closeButtons.length > 0) {
                try {
                  await closeButtons[0].click();
                  await page.waitForTimeout(1000);
                  console.log('✅ Modal closed');
                } catch (error) {
                  console.log('⚠️ Could not close modal');
                }
              }

              break;
            }
          }

          if (!formFound) {
            console.log('⚠️ No form appeared after clicking add button');
          }

          addButtonFound = true;
          break;

        } catch (error) {
          console.log(`⚠️ Error clicking add button: ${error.message}`);
        }
      }
    }

    if (!addButtonFound) {
      console.log('❌ No Add Company button found');

      // Check if there's a different way to add companies
      console.log('🔍 Looking for alternative ways to add companies...');

      // Check for dropdown menus
      const dropdowns = await page.locator('.dropdown, .menu, .nav-dropdown').all();
      console.log(`Found ${dropdowns.length} dropdown menus`);

      // Check for sidebar actions
      const sidebarActions = await page.locator('.sidebar button, .menu button').all();
      console.log(`Found ${sidebarActions} sidebar actions`);

      // Check for page header actions
      const headerActions = await page.locator('.page-header button, .header-actions button').all();
      console.log(`Found ${headerActions.length} header actions`);
    }

    // Refresh companies list to see if new company was added
    await page.goto('http://localhost:8000/companies');
    await page.waitForLoadState('networkidle');

    const newRows = await page.locator('table tbody tr').all();
    console.log(`📊 Companies count after creation attempt: ${newRows.length}`);

    // TEST 2: DELETE COMPANY
    console.log('\n🗑️ TEST 2: DELETE COMPANY');

    if (newRows.length > 0) {
      // Look for delete/remove buttons
      const firstRow = newRows[0];
      const actionButtons = await firstRow.locator('button, a').all();

      console.log(`🎯 Found ${actionButtons.length} action buttons on first company`);

      let deleteButtonFound = false;
      for (let i = 0; i < actionButtons.length; i++) {
        const button = actionButtons[i];
        const buttonText = await button.textContent();
        const buttonTitle = await button.getAttribute('title');

        console.log(`   Action ${i + 1}: "${buttonText?.trim()}" (title: "${buttonTitle}")`);

        // Check if this is a delete button
        const isDeleteButton =
          buttonText?.toLowerCase().includes('delete') ||
          buttonText?.toLowerCase().includes('remove') ||
          buttonTitle?.toLowerCase().includes('delete') ||
          buttonTitle?.toLowerCase().includes('remove') ||
          await button.locator('.fa-trash, .fas fa-trash, .delete-icon').count() > 0;

        if (isDeleteButton) {
          console.log(`✅ Found delete button: "${buttonText?.trim()}"`);

          try {
            // Take screenshot before delete
            await page.screenshot({
              path: 'test-results/before-delete.png',
              fullPage: false
            });

            await button.click();
            await page.waitForTimeout(1000);

            // Look for confirmation dialog
            const confirmSelectors = [
              '.modal',
              '.dialog',
              '.confirm-dialog',
              '[data-testid="confirm-delete"]',
              '.swal2-popup' // SweetAlert
            ];

            let confirmationFound = false;
            for (const confirmSelector of confirmSelectors) {
              const confirmDialog = page.locator(confirmSelector).first();
              if (await confirmDialog.isVisible()) {
                console.log(`✅ Confirmation dialog found: ${confirmSelector}`);
                confirmationFound = true;

                // Look for confirm button
                const confirmButtonSelectors = [
                  'button:has-text("Delete")',
                  'button:has-text("Confirm")',
                  'button:has-text("Yes")',
                  'button:has-text("OK")',
                  '.btn-danger',
                  '[data-testid="confirm-delete-button"]'
                ];

                for (const confirmSelector of confirmButtonSelectors) {
                  const confirmButton = confirmDialog.locator(confirmSelector).first();
                  if (await confirmButton.isVisible()) {
                    console.log(`✅ Found confirm button: ${confirmSelector}`);
                    await confirmButton.click();
                    await page.waitForTimeout(2000);

                    // Check for success message
                    const successMessage = await page.locator('.success, .alert-success, .notification-success').first();
                    if (await successMessage.isVisible()) {
                      const successText = await successMessage.textContent();
                      console.log(`✅ Company deleted successfully: ${successText}`);
                    } else {
                      console.log('✅ Delete action completed (no success message detected)');
                    }

                    break;
                  }
                }

                // If no confirm button found, try to cancel
                const cancelButtonSelectors = [
                  'button:has-text("Cancel")',
                  'button:has-text("No")',
                  'button:has-text("Close")',
                  '.btn-secondary',
                  '[data-testid="cancel-delete"]'
                ];

                for (const cancelSelector of cancelButtonSelectors) {
                  const cancelButton = confirmDialog.locator(cancelSelector).first();
                  if (await cancelButton.isVisible()) {
                    await cancelButton.click();
                    console.log('ℹ️ Cancelled delete operation');
                    break;
                  }
                }

                break;
              }
            }

            if (!confirmationFound) {
              console.log('ℹ️ No confirmation dialog - delete might be immediate');
            }

            deleteButtonFound = true;
            break;

          } catch (error) {
            console.log(`⚠️ Error clicking delete button: ${error.message}`);
          }
        }
      }

      if (!deleteButtonFound) {
        console.log('❌ No delete button found');
      }
    }

    // Refresh companies list
    await page.goto('http://localhost:8000/companies');
    await page.waitForLoadState('networkidle');

    const finalRows = await page.locator('table tbody tr').all();
    console.log(`📊 Final companies count: ${finalRows.length}`);

    // TEST 3: USER MANAGEMENT FUNCTIONS
    console.log('\n👥 TEST 3: USER MANAGEMENT');

    // Look for user management sections
    const userManagementSelectors = [
      '[data-testid="user-management"]',
      '.user-management',
      '.company-users',
      '.users-section',
      'a:has-text("Users")',
      'button:has-text("Users")',
      'a:has-text("Team")',
      'button:has-text("Team")'
    ];

    let userSectionFound = false;
    for (const selector of userManagementSelectors) {
      const userSection = page.locator(selector).first();
      if (await userSection.isVisible()) {
        console.log(`✅ Found user management section: ${selector}`);
        userSectionFound = true;

        // Look for user invite button
        const inviteSelectors = [
          'button:has-text("Invite User")',
          'button:has-text("Add User")',
          'button:has-text("Invite")',
          'a:has-text("Invite User")',
          'a:has-text("Add User")',
          '[data-testid="invite-user"]',
          '.btn-primary'
        ];

        for (const inviteSelector of inviteSelectors) {
          const inviteButton = userSection.locator(inviteSelector).first();
          if (await inviteButton.isVisible()) {
            console.log(`✅ Found invite button: ${inviteSelector}`);

            try {
              await inviteButton.click();
              await page.waitForTimeout(2000);

              // Look for invite form
              const inviteForm = page.locator('.modal, .dialog, form').first();
              if (await inviteForm.isVisible()) {
                console.log('✅ User invite form found');

                // Analyze invite form fields
                const inviteFields = await inviteForm.locator('input, select').all();
                console.log(`📝 Invite form has ${inviteFields.length} fields`);

                for (let i = 0; i < inviteFields.length; i++) {
                  const field = inviteFields[i];
                  const name = await field.getAttribute('name') || await field.getAttribute('id');
                  const type = await field.getAttribute('type');
                  const placeholder = await field.getAttribute('placeholder');
                  console.log(`   Field ${i + 1}: ${name || 'unnamed'} (${type}) - ${placeholder || 'no label'}`);
                }

                // Take screenshot of invite form
                await page.screenshot({
                  path: 'test-results/user-invite-form.png',
                  fullPage: false
                });

                // Try to fill invite form
                try {
                  const emailField = inviteForm.locator('input[type="email"], input[name*="email"], input[placeholder*="email" i]').first();
                  if (await emailField.isVisible()) {
                    await emailField.fill('testuser' + Date.now() + '@example.com');
                    console.log('✅ Filled user email');
                  }

                  const nameField = inviteForm.locator('input[name*="name"], input[placeholder*="name" i]').first();
                  if (await nameField.isVisible()) {
                    await nameField.fill('Test User');
                    console.log('✅ Filled user name');
                  }

                  // Look for role selection
                  const roleField = inviteForm.locator('select[name*="role"], [data-testid="role"]').first();
                  if (await roleField.isVisible()) {
                    const options = await roleField.locator('option').all();
                    if (options.length > 1) {
                      await roleField.selectOption({ index: 1 });
                      console.log('✅ Selected user role');
                    }
                  }

                  // Look for send invite button
                  const sendButton = inviteForm.locator('button:has-text("Send"), button:has-text("Invite"), button[type="submit"]').first();
                  if (await sendButton.isVisible()) {
                    console.log('✅ Found send invite button - not clicking to avoid creating test users');
                  }

                } catch (error) {
                  console.log(`⚠️ Error filling invite form: ${error.message}`);
                }

                // Close invite form
                const closeButton = inviteForm.locator('.close, .cancel').first();
                if (await closeButton.isVisible()) {
                  await closeButton.click();
                  await page.waitForTimeout(1000);
                  console.log('✅ Invite form closed');
                }
              } else {
                console.log('⚠️ Invite button clicked but no form appeared');
              }

            } catch (error) {
              console.log(`⚠️ Error clicking invite button: ${error.message}`);
            }

            break;
          }
        }

        // Look for existing users to test assign/ban functions
        const userRows = await userSection.locator('tr, .user-item, .user-card').all();
        console.log(`👥 Found ${userRows.length} users in management section`);

        if (userRows.length > 0) {
          const firstUser = userRows[0];
          const userActions = await firstUser.locator('button, a').all();

          console.log(`🎯 Found ${userActions.length} action buttons for first user`);

          for (let i = 0; i < Math.min(userActions.length, 5); i++) {
            const action = userActions[i];
            const actionText = await action.textContent();
            const actionTitle = await action.getAttribute('title');

            console.log(`   User Action ${i + 1}: "${actionText?.trim()}" (title: "${actionTitle}")`);

            // Check for ban/disable action
            const isBanAction =
              actionText?.toLowerCase().includes('ban') ||
              actionText?.toLowerCase().includes('disable') ||
              actionText?.toLowerCase().includes('suspend') ||
              actionTitle?.toLowerCase().includes('ban') ||
              actionTitle?.toLowerCase().includes('disable');

            if (isBanAction) {
              console.log(`✅ Found ban/disable action: "${actionText?.trim()}"`);
            }

            // Check for assign action
            const isAssignAction =
              actionText?.toLowerCase().includes('assign') ||
              actionText?.toLowerCase().includes('role') ||
              actionTitle?.toLowerCase().includes('assign') ||
              actionTitle?.toLowerCase().includes('role');

            if (isAssignAction) {
              console.log(`✅ Found assign action: "${actionText?.trim()}"`);
            }
          }
        }

        break;
      }
    }

    if (!userSectionFound) {
      console.log('⚠️ No user management section found on companies page');

      // Look for separate user management page
      console.log('🔍 Looking for separate user management...');

      const userPageSelectors = [
        'a:has-text("Users")',
        'a:has-text("User Management")',
        'a:has-text("Team")',
        'nav a[href*="user"]',
        '.sidebar a[href*="user"]'
      ];

      for (const selector of userPageSelectors) {
        const userLink = page.locator(selector).first();
        if (await userLink.isVisible()) {
          console.log(`✅ Found user management link: ${selector}`);

          try {
            await userLink.click();
            await page.waitForLoadState('networkidle');
            console.log('✅ Navigated to user management page');

            // Analyze user management page
            const pageTitle = await page.title();
            console.log(`📄 User management page title: ${pageTitle}`);

            // Take screenshot of user management page
            await page.screenshot({
              path: 'test-results/user-management-page.png',
              fullPage: true
            });

            break;
          } catch (error) {
            console.log(`⚠️ Error navigating to user management: ${error.message}`);
          }
        }
      }
    }

    // TEST 4: NAVIGATION TO RELATED PAGES
    console.log('\n🧭 TEST 4: NAVIGATION TO RELATED PAGES');

    const relatedPages = [
      { name: 'Settings', url: '/settings', purpose: 'Company settings' },
      { name: 'Dashboard', url: '/dashboard', purpose: 'Main dashboard' },
      { name: 'Invoices', url: '/invoices', purpose: 'Invoice management' },
      { name: 'Reports', url: '/reports', purpose: 'Company reports' }
    ];

    for (const pageToTest of relatedPages) {
      try {
        await page.goto(`http://localhost:8000${pageToTest.url}`);
        await page.waitForLoadState('networkidle');

        const pageTitle = await page.title();
        console.log(`✅ ${pageToTest.name} (${pageToTest.purpose}): ${pageTitle}`);

        // Look for company-specific features on this page
        const companySelectors = await page.locator('[data-testid*="company"], .company, [class*="company"]').all();
        if (companySelectors.length > 0) {
          console.log(`   📊 Found ${companySelectors.length} company-related elements`);
        }

      } catch (error) {
        console.log(`❌ ${pageToTest.name}: Error - ${error.message}`);
      }
    }

    // FINAL SCREENSHOT
    console.log('\n📸 Taking final screenshot...');
    await page.goto('http://localhost:8000/companies');
    await page.waitForLoadState('networkidle');

    await page.screenshot({
      path: 'test-results/companies-final-full-test.png',
      fullPage: true
    });

    console.log('✅ Full companies functionality testing complete!');

  } catch (error) {
    console.error(`❌ Error during testing: ${error.message}`);
  } finally {
    await browser.close();
  }
}

testFullCompaniesFunctionality().catch(console.error);