const { chromium } = require('playwright');

async function exploreAccessiblePages() {
  console.log('🔍 Exploring Accessible Pages...');

  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    // Login
    console.log('\n🔐 Logging in...');
    await page.goto('http://localhost:8000/login');
    await page.locator('input[name="username"]').fill('admin');
    await page.locator('input[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/dashboard', { timeout: 10000 });
    console.log('✅ Login successful');

    // Explore /companies/create (200 OK)
    console.log('\n🏝️ EXPLORING: /companies/create');
    await page.goto('http://localhost:8000/companies/create');
    await page.waitForLoadState('networkidle');

    const createPageTitle = await page.title();
    console.log(`📄 Companies create page title: ${createPageTitle}`);

    await page.screenshot({
      path: 'test-results/companies-create-page.png',
      fullPage: true
    });

    // Look for company creation form
    const createForm = page.locator('form').first();
    if (await createForm.isVisible()) {
      console.log('✅ Company creation form found on /companies/create');

      const formFields = await createForm.locator('input, select, textarea').all();
      console.log(`📝 Found ${formFields.length} form fields:`);

      for (let i = 0; i < formFields.length; i++) {
        const field = formFields[i];
        const name = await field.getAttribute('name') || await field.getAttribute('id');
        const type = await field.getAttribute('type');
        const placeholder = await field.getAttribute('placeholder');
        const label = await field.locator('preceding-sibling::label, ../label, .form-label').first().textContent();

        console.log(`   Field ${i + 1}: ${name || 'unnamed'} (${type}) - ${label || placeholder || 'no label'}`);

        // Fill test data
        try {
          if (name?.includes('name') || placeholder?.toLowerCase().includes('name')) {
            await field.fill('Test Company ' + Date.now());
            console.log('      ✅ Filled company name');
          } else if (name?.includes('email') || type === 'email') {
            await field.fill('test' + Date.now() + '@company.com');
            console.log('      ✅ Filled company email');
          } else if (name?.includes('phone') || placeholder?.toLowerCase().includes('phone')) {
            await field.fill('+1-555-' + Math.floor(Math.random() * 10000));
            console.log('      ✅ Filled company phone');
          } else if (name?.includes('address') || placeholder?.toLowerCase().includes('address')) {
            await field.fill('123 Test Street, Test City');
            console.log('      ✅ Filled company address');
          }
        } catch (error) {
          console.log(`      ⚠️ Could not fill field: ${error.message}`);
        }
      }

      // Look for save/submit button
      const saveButton = createForm.locator('button:has-text("Save"), button:has-text("Create"), button[type="submit"]').first();
      if (await saveButton.isVisible()) {
        console.log('✅ Found save button on creation form');

        // Take screenshot of filled form
        await page.screenshot({
          path: 'test-results/companies-create-form-filled.png',
          fullPage: false
        });

        // Try to save (but be careful about creating test data)
        console.log('🧪 Ready to create company - clicking save button...');

        try {
          await saveButton.click();
          await page.waitForTimeout(3000);

          const currentUrl = page.url();
          console.log(`📍 After save, current URL: ${currentUrl}`);

          // Look for success or error messages
          const successMessage = await page.locator('.success, .alert-success, .notification-success').first();
          const errorMessage = await page.locator('.error, .alert-danger, .notification-error').first();

          if (await successMessage.isVisible()) {
            const successText = await successMessage.textContent();
            console.log(`✅ Company created successfully: ${successText}`);
          } else if (await errorMessage.isVisible()) {
            const errorText = await errorMessage.textContent();
            console.log(`❌ Company creation failed: ${errorText}`);
          } else {
            console.log('ℹ️ No clear success/error message detected');
          }

          // Take screenshot after save attempt
          await page.screenshot({
            path: 'test-results/companies-create-after-save.png',
            fullPage: true
          });

        } catch (error) {
          console.log(`⚠️ Error during save: ${error.message}`);
        }
      } else {
        console.log('❌ No save button found on creation form');
      }
    } else {
      console.log('❌ No form found on /companies/create');
    }

    // Explore /admin/users (200 OK)
    console.log('\n👥 EXPLORING: /admin/users');
    await page.goto('http://localhost:8000/admin/users');
    await page.waitForLoadState('networkidle');

    const adminUsersTitle = await page.title();
    console.log(`📄 Admin users page title: ${adminUsersTitle}`);

    await page.screenshot({
      path: 'test-results/admin-users-page.png',
      fullPage: true
    });

    // Look for user management functionality
    console.log('\n🔍 Analyzing admin user management...');

    // User list/table
    const userTable = page.locator('table').first();
    if (await userTable.isVisible()) {
      console.log('✅ User table found in admin section');

      const userRows = await userTable.locator('tbody tr').all();
      console.log(`👥 Found ${userRows.length} users in admin section`);

      if (userRows.length > 0) {
        // Analyze first user
        const firstUser = userRows[0];
        const userCells = await firstUser.locator('td').all();
        console.log(`📊 First admin user has ${userCells.length} cells`);

        for (let i = 0; i < userCells.length; i++) {
          const cellText = await userCells[i].textContent();
          console.log(`   Cell ${i + 1}: ${cellText?.trim()}`);
        }

        // Look for user actions
        const userActions = await firstUser.locator('button, a').all();
        console.log(`🎯 Found ${userActions.length} user actions`);

        for (let i = 0; i < userActions.length; i++) {
          const action = userActions[i];
          const actionText = await action.textContent();
          const actionTitle = await action.getAttribute('title');

          console.log(`   Action ${i + 1}: "${actionText?.trim()}" (title: "${actionTitle}")`);

          // Test specific actions
          if (actionText?.toLowerCase().includes('assign') || actionTitle?.toLowerCase().includes('assign')) {
            console.log('     👤 USER ASSIGN ACTION FOUND');
            await testAdminUserAssign(page, action);
          }

          if (actionText?.toLowerCase().includes('ban') || actionText?.toLowerCase().includes('disable') || actionText?.toLowerCase().includes('suspend')) {
            console.log('     🚫 USER BAN/DISABLE ACTION FOUND');
            await testAdminUserBan(page, action);
          }

          if (actionText?.toLowerCase().includes('invite') || actionText?.toLowerCase().includes('add')) {
            console.log('     ➕ USER INVITE/ADD ACTION FOUND');
            await testAdminUserInvite(page, action);
          }
        }
      }
    }

    // Look for invite user functionality in admin section
    console.log('\n➕ Testing admin user invite...');
    await testAdminUserInviteFlow(page);

    // Check if we can access company delete functionality
    console.log('\n🗑️ TESTING COMPANY DELETE ACCESS');

    // Go back to companies
    await page.goto('http://localhost:8000/companies');
    await page.waitForLoadState('networkidle');

    // Look for delete functionality more carefully
    const companyRows = await page.locator('table tbody tr').all();
    if (companyRows.length > 0) {
      const firstCompany = companyRows[0];

      // Look for dropdown menus
      const dropdownButtons = await firstCompany.locator('button[class*="dropdown"], button[class*="menu"], .dropdown-toggle').all();
      console.log(`📋 Found ${dropdownButtons.length} dropdown buttons`);

      for (let i = 0; i < dropdownButtons.length; i++) {
        const dropdown = dropdownButtons[i];
        try {
          await dropdown.click();
          await page.waitForTimeout(1000);

          // Look for dropdown menu
          const dropdownMenu = page.locator('.dropdown-menu, .menu, [role="menu"]').first();
          if (await dropdownMenu.isVisible()) {
            console.log('✅ Dropdown menu opened');

            const menuItems = await dropdownMenu.locator('a, li, button').all();
            console.log(`📋 Found ${menuItems.length} menu items in dropdown`);

            for (let j = 0; j < menuItems.length; j++) {
              const item = menuItems[j];
              const itemText = await item.textContent();
              console.log(`   Item ${j + 1}: "${itemText?.trim()}"`);

              if (itemText?.toLowerCase().includes('delete') || itemText?.toLowerCase().includes('remove')) {
                console.log('   🗑️ DELETE ACTION FOUND IN DROPDOWN!');
              }

              if (itemText?.toLowerCase().includes('edit') || itemText?.toLowerCase().includes('modify')) {
                console.log('   ✏️ EDIT ACTION FOUND IN DROPDOWN!');
              }
            }

            // Click outside to close
            await page.locator('body').click();
            await page.waitForTimeout(500);
          }

        } catch (error) {
          console.log(`⚠️ Error with dropdown ${i + 1}: ${error.message}`);
        }
      }

      // Try right-click context menu
      try {
        await firstCompany.click({ button: 'right' });
        await page.waitForTimeout(1000);

        const contextMenu = page.locator('.context-menu, [role="menu"]').first();
        if (await contextMenu.isVisible()) {
          console.log('✅ Context menu found');

          const contextItems = await contextMenu.locator('li, a, button').all();
          console.log(`📋 Found ${contextItems.length} context menu items`);

          for (let j = 0; j < contextItems.length; j++) {
            const item = contextItems[j];
            const itemText = await item.textContent();
            console.log(`   Context Item ${j + 1}: "${itemText?.trim()}"`);
          }

          // Click outside to close
          await page.locator('body').click();
        } else {
          console.log('⚠️ No context menu appeared on right-click');
        }
      } catch (error) {
        console.log(`⚠️ Error testing context menu: ${error.message}`);
      }
    }

    console.log('\n✅ Exploration complete!');

  } catch (error) {
    console.error(`❌ Error during exploration: ${error.message}`);
  } finally {
    await browser.close();
  }
}

async function testAdminUserAssign(page, actionButton) {
  console.log('   🧪 Testing admin user assign...');
  try {
    await actionButton.click();
    await page.waitForTimeout(2000);

    const assignModal = page.locator('.modal, .dialog').first();
    if (await assignModal.isVisible()) {
      console.log('   ✅ Admin assign modal opened');

      const roleControls = await assignModal.locator('select, input[type="radio"], [role="radiogroup"]').all();
      console.log(`   📝 Found ${roleControls.length} role controls`);

      // Look for company assignment options
      const companySelectors = await assignModal.locator('select[name*="company"], [data-testid*="company"]').all();
      if (companySelectors.length > 0) {
        console.log('   🏢 Company assignment options found');
      }

      // Close modal
      const closeButton = assignModal.locator('.close, .cancel').first();
      if (await closeButton.isVisible()) {
        await closeButton.click();
        await page.waitForTimeout(1000);
      }
    }
  } catch (error) {
    console.log(`   ⚠️ Error testing admin assign: ${error.message}`);
  }
}

async function testAdminUserBan(page, actionButton) {
  console.log('   🧪 Testing admin user ban/disable...');
  try {
    // Take screenshot before action
    await page.screenshot({
      path: 'test-results/before-admin-user-action.png',
      fullPage: false
    });

    console.log(`   ⚠️ Ban/disable action detected: "${await actionButton.textContent()}" - not clicking to avoid affecting test user`);

  } catch (error) {
    console.log(`   ⚠️ Error testing admin ban: ${error.message}`);
  }
}

async function testAdminUserInvite(page, actionButton) {
  console.log('   🧪 Testing admin user invite...');
  try {
    await actionButton.click();
    await page.waitForTimeout(2000);

    const inviteForm = page.locator('.modal, .dialog, form').first();
    if (await inviteForm.isVisible()) {
      console.log('   ✅ Admin invite form opened');

      const formFields = await inviteForm.locator('input, select').all();
      console.log(`   📝 Admin invite form has ${formFields.length} fields`);

      // Close form
      const closeButton = inviteForm.locator('.close, .cancel').first();
      if (await closeButton.isVisible()) {
        await closeButton.click();
        await page.waitForTimeout(1000);
      }
    }
  } catch (error) {
    console.log(`   ⚠️ Error testing admin invite: ${error.message}`);
  }
}

async function testAdminUserInviteFlow(page) {
  console.log('➕ Testing admin user invite flow...');

  const inviteSelectors = [
    'button:has-text("Invite User")',
    'button:has-text("Add User")',
    'a:has-text("Invite")',
    '.btn-primary:has-text("Invite")',
    '[data-testid="admin-invite-user"]'
  ];

  for (const selector of inviteSelectors) {
    const button = page.locator(selector).first();
    if (await button.isVisible()) {
      console.log(`✅ Found admin invite button: ${selector}`);

      try {
        await button.click();
        await page.waitForTimeout(2000);

        const inviteForm = page.locator('.modal, .dialog, form').first();
        if (await inviteForm.isVisible()) {
          console.log('✅ Admin invite form opened');

          const formFields = await inviteForm.locator('input, select, textarea').all();
          console.log(`📝 Admin invite form has ${formFields.length} fields`);

          // Try to find email field
          const emailField = inviteForm.locator('input[type="email"], input[name*="email"]').first();
          if (await emailField.isVisible()) {
            console.log('✅ Email field found in admin invite form');
          }

          // Look for role assignment
          const roleField = inviteForm.locator('select[name*="role"], [data-testid*="role"]').first();
          if (await roleField.isVisible()) {
            const options = await roleField.locator('option').all();
            console.log(`✅ Role field found with ${options.length} options`);
          }

          // Look for company assignment
          const companyField = inviteForm.locator('select[name*="company"], [data-testid*="company"]').first();
          if (await companyField.isVisible()) {
            const options = await companyField.locator('option').all();
            console.log(`✅ Company assignment field found with ${options.length} options`);
          }

          // Take screenshot
          await page.screenshot({
            path: 'test-results/admin-invite-form.png',
            fullPage: false
          });

          // Close form
          const closeButton = inviteForm.locator('.close, .cancel').first();
          if (await closeButton.isVisible()) {
            await closeButton.click();
            await page.waitForTimeout(1000);
          }
        }

        break;
      } catch (error) {
        console.log(`⚠️ Error with admin invite button: ${error.message}`);
      }
    }
  }
}

exploreAccessiblePages().catch(console.error);