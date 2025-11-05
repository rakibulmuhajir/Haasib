const { chromium } = require('playwright');

async function deepCompaniesAnalysis() {
  console.log('🔍 Deep Companies Analysis...');

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

    // ANALYSIS 1: Deep dive into user management
    console.log('\n👥 ANALYSIS 1: USER MANAGEMENT SYSTEM');

    // Navigate to user management
    await page.goto('http://localhost:8000/users');
    await page.waitForLoadState('networkidle');

    const userPageTitle = await page.title();
    console.log(`📄 User management page loaded: ${userPageTitle}`);

    // Take screenshot of user management page
    await page.screenshot({
      path: 'test-results/user-management-full.png',
      fullPage: true
    });

    // Analyze user management structure
    console.log('\n🏗️ Analyzing user management structure...');

    // Look for user list/table
    const userTable = page.locator('table').first();
    if (await userTable.isVisible()) {
      console.log('✅ User table found');

      const userRows = await userTable.locator('tbody tr').all();
      console.log(`👥 Found ${userRows.length} users`);

      if (userRows.length > 0) {
        // Analyze first user in detail
        const firstUser = userRows[0];
        const userCells = await firstUser.locator('td').all();
        console.log(`📊 First user has ${userCells.length} data cells`);

        for (let i = 0; i < userCells.length; i++) {
          const cellText = await userCells[i].textContent();
          console.log(`   Cell ${i + 1}: ${cellText?.trim()}`);
        }

        // Analyze user action buttons
        const userActions = await firstUser.locator('button, a').all();
        console.log(`🎯 Found ${userActions.length} action buttons for first user`);

        for (let i = 0; i < userActions.length; i++) {
          const action = userActions[i];
          const actionText = await action.textContent();
          const actionTitle = await action.getAttribute('title');
          const actionClass = await action.getAttribute('class');

          console.log(`   Action ${i + 1}: "${actionText?.trim()}" (title: "${actionTitle}", class: "${actionClass}")`);

          // Test specific actions
          if (actionText?.toLowerCase().includes('invite') || actionTitle?.toLowerCase().includes('invite')) {
            console.log('     ➕ USER INVITE ACTION FOUND');
            await testUserInvite(page, action);
          }

          if (actionText?.toLowerCase().includes('assign') || actionTitle?.toLowerCase().includes('assign') || actionText?.toLowerCase().includes('role')) {
            console.log('     👤 USER ASSIGN ACTION FOUND');
            await testUserAssign(page, action);
          }

          if (actionText?.toLowerCase().includes('ban') || actionText?.toLowerCase().includes('disable') || actionText?.toLowerCase().includes('suspend')) {
            console.log('     🚫 USER BAN ACTION FOUND');
            await testUserBan(page, action);
          }
        }
      }
    }

    // Look for invite user functionality
    console.log('\n➕ Testing USER INVITE functionality...');
    await testUserInviteFlow(page);

    // ANALYSIS 2: Deep dive into company management
    console.log('\n🏢 ANALYSIS 2: COMPANY MANAGEMENT SYSTEM');

    await page.goto('http://localhost:8000/companies');
    await page.waitForLoadState('networkidle');

    // Analyze the Add Company button more deeply
    console.log('\n🔍 Deep analysis of Add Company functionality...');

    const addButtons = await page.locator('button').all();
    console.log(`Found ${addButtons.length} buttons total on page`);

    for (let i = 0; i < addButtons.length; i++) {
      const button = addButtons[i];
      const buttonText = await button.textContent();
      const buttonClass = await button.getAttribute('class');
      const buttonTitle = await button.getAttribute('title');

      if (buttonText?.includes('Add') || buttonClass?.includes('btn') || buttonTitle?.includes('Add')) {
        console.log(`Button ${i + 1}: "${buttonText?.trim()}" (class: "${buttonClass}", title: "${buttonTitle}")`);

        // Try clicking this button
        try {
          await button.click();
          await page.waitForTimeout(2000);

          // Check if any modal/form appears
          const modals = await page.locator('.modal, .dialog, .popup, [role="dialog"]').all();
          console.log(`   Found ${modals.length} modals after clicking`);

          if (modals.length > 0) {
            const modal = modals[0];
            const modalTitle = await modal.locator('h1, h2, h3, .modal-title').first().textContent();
            console.log(`   ✅ Modal appeared: "${modalTitle?.trim()}"`);

            // Analyze modal content
            const modalInputs = await modal.locator('input, select, textarea').all();
            console.log(`   📝 Modal has ${modalInputs.length} form fields`);

            for (let j = 0; j < modalInputs.length; j++) {
              const input = modalInputs[j];
              const inputName = await input.getAttribute('name');
              const inputType = await input.getAttribute('type');
              const inputPlaceholder = await input.getAttribute('placeholder');
              const inputLabel = await input.locator('preceding-sibling::label, ../label, .form-label').first().textContent();

              console.log(`      Field ${j + 1}: ${inputName || 'unnamed'} (${inputType}) - ${inputLabel || inputPlaceholder || 'no label'}`);
            }

            // Test if it's a company creation form
            const hasNameField = await modal.locator('input[name*="name"], input[placeholder*="name" i]').count() > 0;
            const hasEmailField = await modal.locator('input[name*="email"], input[type="email"]').count() > 0;

            if (hasNameField && hasEmailField) {
              console.log('   ✅ This appears to be a company creation form!');

              // Try to fill it
              await fillAndTestCompanyForm(page, modal);
            }

            // Close modal
            const closeButton = modal.locator('.close, .cancel, button:has-text("Close")').first();
            if (await closeButton.isVisible()) {
              await closeButton.click();
              await page.waitForTimeout(1000);
            }
          }

          // Go back to companies page
          await page.goto('http://localhost:8000/companies');
          await page.waitForLoadState('networkidle');

        } catch (error) {
          console.log(`   ⚠️ Error clicking button: ${error.message}`);
        }
      }
    }

    // ANALYSIS 3: Test company delete functionality
    console.log('\n🗑️ ANALYSIS 3: COMPANY DELETE FUNCTIONALITY');

    const companyRows = await page.locator('table tbody tr').all();
    if (companyRows.length > 0) {
      const firstCompany = companyRows[0];
      const companyActions = await firstCompany.locator('button, a').all();

      console.log(`🎯 Analyzing ${companyActions.length} company actions...`);

      for (let i = 0; i < companyActions.length; i++) {
        const action = companyActions[i];
        const actionText = await action.textContent();
        const actionClass = await action.getAttribute('class');

        console.log(`Action ${i + 1}: "${actionText?.trim()}" (class: "${actionClass}")`);

        // Look for dropdown or menu actions
        if (actionClass?.includes('dropdown') || actionClass?.includes('menu') || actionText?.includes('…') || actionText?.includes('⋮')) {
          console.log('   📋 Found dropdown/menu - clicking to reveal more actions');

          try {
            await action.click();
            await page.waitForTimeout(1000);

            // Look for dropdown menu items
            const dropdownItems = await page.locator('.dropdown-menu, .menu, [role="menu"]').first().locator('li, a, button').all();
            console.log(`   Found ${dropdownItems.length} dropdown items`);

            for (let j = 0; j < dropdownItems.length; j++) {
              const item = dropdownItems[j];
              const itemText = await item.textContent();
              console.log(`      Dropdown item ${j + 1}: "${itemText?.trim()}"`);

              if (itemText?.toLowerCase().includes('delete') || itemText?.toLowerCase().includes('remove')) {
                console.log('      🗑️ DELETE ACTION FOUND IN DROPDOWN!');
              }
            }

            // Click outside to close dropdown
            await page.locator('body').click();
            await page.waitForTimeout(500);

          } catch (error) {
            console.log(`   ⚠️ Error with dropdown: ${error.message}`);
          }
        }
      }
    }

    // ANALYSIS 4: Check permissions and access control
    console.log('\n🔐 ANALYSIS 4: PERMISSIONS AND ACCESS CONTROL');

    // Test different URLs that might require permissions
    const permissionTestUrls = [
      '/companies/create',
      '/companies/new',
      '/admin/companies',
      '/admin/users',
      '/settings/company',
      '/settings/permissions'
    ];

    for (const testUrl of permissionTestUrls) {
      try {
        const response = await page.goto(`http://localhost:8000${testUrl}`);
        const statusCode = response?.status();
        const finalUrl = page.url();

        if (finalUrl.includes('/login')) {
          console.log(`🔒 ${testUrl}: Redirected to login (protected)`);
        } else if (statusCode === 403) {
          console.log(`🚫 ${testUrl}: Access forbidden (403)`);
        } else if (statusCode === 404) {
          console.log(`❌ ${testUrl}: Not found (404)`);
        } else {
          console.log(`✅ ${testUrl}: Accessible (${statusCode})`);
        }
      } catch (error) {
        console.log(`⚠️ ${testUrl}: Error - ${error.message}`);
      }
    }

    console.log('\n✅ Deep analysis complete!');

  } catch (error) {
    console.error(`❌ Error during analysis: ${error.message}`);
  } finally {
    await browser.close();
  }
}

async function testUserInvite(page, actionButton) {
  console.log('   🧪 Testing user invite action...');
  try {
    await actionButton.click();
    await page.waitForTimeout(2000);

    const inviteForm = page.locator('.modal, .dialog, form').first();
    if (await inviteForm.isVisible()) {
      console.log('   ✅ Invite form opened successfully');

      const emailField = inviteForm.locator('input[type="email"], input[name*="email"]').first();
      if (await emailField.isVisible()) {
        await emailField.fill('testuser' + Date.now() + '@example.com');
        console.log('   ✅ Email field filled');
      }

      const sendButton = inviteForm.locator('button:has-text("Send"), button:has-text("Invite")').first();
      if (await sendButton.isVisible()) {
        console.log('   ✅ Send button found - not clicking to avoid creating test user');
      }

      // Close form
      const closeButton = inviteForm.locator('.close, .cancel').first();
      if (await closeButton.isVisible()) {
        await closeButton.click();
        await page.waitForTimeout(1000);
      }
    } else {
      console.log('   ⚠️ No invite form appeared');
    }
  } catch (error) {
    console.log(`   ⚠️ Error testing invite: ${error.message}`);
  }
}

async function testUserAssign(page, actionButton) {
  console.log('   🧪 Testing user assign action...');
  try {
    await actionButton.click();
    await page.waitForTimeout(2000);

    const assignModal = page.locator('.modal, .dialog, [role="dialog"]').first();
    if (await assignModal.isVisible()) {
      console.log('   ✅ Assign modal opened');

      const roleSelectors = await assignModal.locator('select, input[type="radio"], [role="radiogroup"]').all();
      console.log(`   📝 Found ${roleSelectors.length} role/assignment controls`);

      if (roleSelectors.length > 0) {
        console.log('   ✅ Role assignment functionality detected');
      }

      // Close modal
      const closeButton = assignModal.locator('.close, .cancel, button:has-text("Close")').first();
      if (await closeButton.isVisible()) {
        await closeButton.click();
        await page.waitForTimeout(1000);
      }
    } else {
      console.log('   ⚠️ No assign modal appeared');
    }
  } catch (error) {
    console.log(`   ⚠️ Error testing assign: ${error.message}`);
  }
}

async function testUserBan(page, actionButton) {
  console.log('   🧪 Testing user ban action...');
  try {
    // Take screenshot before ban
    await page.screenshot({
      path: 'test-results/before-user-ban.png',
      fullPage: false
    });

    // Check for confirmation dialog first
    const confirmationText = await actionButton.textContent();
    console.log(`   ⚠️ Ban action found: "${confirmationText?.trim()}" - not clicking to avoid banning test user`);

  } catch (error) {
    console.log(`   ⚠️ Error testing ban: ${error.message}`);
  }
}

async function testUserInviteFlow(page) {
  console.log('➕ Testing direct user invite flow...');

  const inviteSelectors = [
    'button:has-text("Invite User")',
    'button:has-text("Add User")',
    'a:has-text("Invite User")',
    '.btn-primary:has-text("Invite")',
    '[data-testid="invite-user-btn"]'
  ];

  for (const selector of inviteSelectors) {
    const button = page.locator(selector).first();
    if (await button.isVisible()) {
      console.log(`✅ Found invite button: ${selector}`);

      try {
        await button.click();
        await page.waitForTimeout(2000);

        const inviteForm = page.locator('.modal, .dialog, form').first();
        if (await inviteForm.isVisible()) {
          console.log('✅ Direct invite form opened');

          const formFields = await inviteForm.locator('input, select, textarea').all();
          console.log(`📝 Invite form has ${formFields.length} fields`);

          // Take screenshot
          await page.screenshot({
            path: 'test-results/direct-invite-form.png',
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
        console.log(`⚠️ Error with invite button: ${error.message}`);
      }
    }
  }
}

async function fillAndTestCompanyForm(page, modal) {
  console.log('   🧪 Testing company form functionality...');

  try {
    // Fill company name
    const nameField = modal.locator('input[name*="name"], input[placeholder*="name" i]').first();
    if (await nameField.isVisible()) {
      await nameField.fill('Test Company ' + Date.now());
      console.log('   ✅ Company name filled');
    }

    // Fill email
    const emailField = modal.locator('input[name*="email"], input[type="email"]').first();
    if (await emailField.isVisible()) {
      await emailField.fill('test' + Date.now() + '@company.com');
      console.log('   ✅ Company email filled');
    }

    // Look for save button
    const saveButton = modal.locator('button:has-text("Save"), button:has-text("Create"), button[type="submit"]').first();
    if (await saveButton.isVisible()) {
      console.log('   ✅ Save button found - not clicking to avoid creating test data');

      // Take screenshot of filled form
      await page.screenshot({
        path: 'test-results/company-form-filled.png',
        fullPage: false
      });
    }

  } catch (error) {
    console.log(`   ⚠️ Error filling company form: ${error.message}`);
  }
}

deepCompaniesAnalysis().catch(console.error);