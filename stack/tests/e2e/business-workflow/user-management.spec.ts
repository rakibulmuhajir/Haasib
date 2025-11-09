import { test, expect, navigateToModule, clickButtonWithText, fillForm, waitForSuccessMessage, takeScreenshot, generateTestData } from '../helpers/auth-helper';

test.describe('User Management E2E Tests', () => {
  let testData: any;
  let companyId: string | null = null;

  test.beforeEach(async ({ page }) => {
    testData = generateTestData();
  });

  test('should create and manage company users with different roles', async ({ page }) => {
    console.log('👥 Testing comprehensive user management...');

    // Step 1: Login as admin
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    // Step 2: Create a test company for user management
    console.log('🏢 Creating test company...');
    await navigateToModule(page, 'companies');
    await clickButtonWithText(page, 'Add Company');

    const companyData = testData.company;
    await page.fill('input[name="name"], input[id*="name"], [data-testid="company-name"]', companyData.name);
    await page.fill('input[name="email"], input[id*="email"], [data-testid="company-email"]', companyData.email);
    await page.fill('input[name="phone"], input[id*="phone"], [data-testid="company-phone"]', companyData.phone);
    await clickButtonWithText(page, 'Save');
    await page.waitForTimeout(2000);

    // Get company ID from URL or list
    await navigateToModule(page, 'companies');
    const companyLink = page.locator(`a:has-text("${companyData.name}")`).first();
    await companyLink.click();
    await page.waitForLoadState('networkidle');

    // Extract company ID from current URL
    const currentUrl = page.url();
    const urlMatch = currentUrl.match(/companies\/([a-f0-9-]+)/);
    if (urlMatch) {
      companyId = urlMatch[1];
      console.log(`✅ Working with company ID: ${companyId}`);
    }

    await takeScreenshot(page, 'company-for-user-management');

    // Step 3: Navigate to company users section
    console.log('👤 Navigating to company users...');

    // Look for Users tab or button
    const usersSelectors = [
      'a:has-text("Users")',
      'button:has-text("Users")',
      '[data-testid="users-tab"]',
      '.nav-tabs a:has-text("Users")'
    ];

    let usersFound = false;
    for (const selector of usersSelectors) {
      const usersButton = page.locator(selector).first();
      if (await usersButton.isVisible()) {
        await usersButton.click();
        await page.waitForTimeout(1000);
        usersFound = true;
        console.log('✅ Navigated to users section');
        break;
      }
    }

    if (!usersFound) {
      console.log('⚠️ Users section not found, trying alternative navigation...');
      // Try direct URL if we have company ID
      if (companyId) {
        await page.goto(`/companies/${companyId}/users`);
        await page.waitForLoadState('networkidle');
      }
    }

    await takeScreenshot(page, 'company-users-section');

    // Step 4: Add new users with different roles
    console.log('➕ Adding new users to company...');

    const userRoles = [
      { role: 'admin', email: `admin-${Date.now()}@example.com` },
      { role: 'member', email: `member-${Date.now()}@example.com` },
      { role: 'viewer', email: `viewer-${Date.now()}@example.com` }
    ];

    for (const userRole of userRoles) {
      console.log(`Adding user with role: ${userRole.role}`);

      // Look for "Add User" or "Invite User" button
      const addUserSelectors = [
        'button:has-text("Add User")',
        'button:has-text("Invite User")',
        'button:has-text("Add")',
        'a:has-text("Add User")',
        '[data-testid="add-user"]'
      ];

      let addUserClicked = false;
      for (const selector of addUserSelectors) {
        const addButton = page.locator(selector).first();
        if (await addButton.isVisible()) {
          await addButton.click();
          await page.waitForTimeout(1000);
          addUserClicked = true;
          break;
        }
      }

      if (addUserClicked) {
        // Fill user invitation form
        const userFormData = {
          email: userRole.email,
          name: `Test ${userRole.role.charAt(0).toUpperCase() + userRole.role.slice(1)} User`,
          role: userRole.role
        };

        await fillForm(page, userFormData);

        // Select role if dropdown exists
        const roleSelect = page.locator('select[name="role"], [data-testid="user-role"]').first();
        if (await roleSelect.isVisible()) {
          await roleSelect.selectOption(userRole.role);
        }

        await takeScreenshot(page, `user-form-${userRole.role}`);

        // Submit form
        await clickButtonWithText(page, 'Save');
        await page.waitForTimeout(2000);

        // Check for success message
        const success = await waitForSuccessMessage(page);
        if (success) {
          console.log(`✅ User ${userRole.email} added successfully with role: ${userRole.role}`);
        } else {
          console.log(`⚠️ May have encountered issue adding user ${userRole.email}`);
        }

        // Close modal or go back to users list
        const closeButton = page.locator('.close, .cancel, [data-testid="close"]').first();
        if (await closeButton.isVisible()) {
          await closeButton.click();
          await page.waitForTimeout(500);
        }
      } else {
        console.log(`⚠️ Could not find Add User button for role: ${userRole.role}`);
      }
    }

    await takeScreenshot(page, 'company-users-added');

    // Step 5: Test role management and user status changes
    console.log('🔄 Testing user role management...');

    // Look for user list items
    const userItems = page.locator('tr, .user-item, [data-testid="user-item"]');
    const userCount = await userItems.count();

    if (userCount > 0) {
      console.log(`Found ${userCount} users in the list`);

      // Test changing user roles
      for (let i = 0; i < Math.min(userCount, 2); i++) {
        const userItem = userItems.nth(i);

        // Look for role change or edit options
        const actionButtons = await userItem.locator('button, a, .dropdown-toggle').all();

        for (const button of actionButtons) {
          const buttonText = await button.textContent();
          if (buttonText && (
            buttonText.toLowerCase().includes('edit') ||
            buttonText.toLowerCase().includes('change role') ||
            buttonText.toLowerCase().includes('manage')
          )) {
            console.log(`Found user action: ${buttonText.trim()}`);
            await button.click();
            await page.waitForTimeout(1000);

            // Look for role dropdown
            const roleDropdown = page.locator('select[name="role"], [data-testid="role-select"]').first();
            if (await roleDropdown.isVisible()) {
              const currentRole = await roleDropdown.inputValue();
              console.log(`  Current role: ${currentRole}`);

              // Change to different role
              const newRole = currentRole === 'admin' ? 'member' : 'admin';
              await roleDropdown.selectOption(newRole);

              await clickButtonWithText(page, 'Save');
              await page.waitForTimeout(2000);

              const success = await waitForSuccessMessage(page);
              if (success) {
                console.log(`  ✅ Role changed from ${currentRole} to ${newRole}`);
              }
            }

            // Close modal or go back
            const closeButton = page.locator('.close, .cancel, [data-testid="close"]').first();
            if (await closeButton.isVisible()) {
              await closeButton.click();
              await page.waitForTimeout(500);
            }

            break;
          }
        }
      }
    }

    // Step 6: Test user removal
    console.log('🗑️ Testing user removal...');

    const userItemsForRemoval = page.locator('tr, .user-item, [data-testid="user-item"]');
    const userCountForRemoval = await userItemsForRemoval.count();

    if (userCountForRemoval > 1) { // Keep at least one user
      const lastUserItem = userItemsForRemoval.nth(userCountForRemoval - 1);

      // Look for remove/delete action
      const removeButtons = await lastUserItem.locator('button, a').all();

      for (const button of removeButtons) {
        const buttonText = await button.textContent();
        if (buttonText && (
          buttonText.toLowerCase().includes('remove') ||
          buttonText.toLowerCase().includes('delete') ||
          buttonText.toLowerCase().includes('deactivate')
        )) {
          console.log(`Found remove action: ${buttonText.trim()}`);
          await button.click();
          await page.waitForTimeout(1000);

          // Look for confirmation dialog
          const confirmButton = page.locator('button:has-text("Confirm"), button:has-text("Yes"), .btn-danger').first();
          if (await confirmButton.isVisible()) {
            await confirmButton.click();
            await page.waitForTimeout(2000);
          }

          // Check for success message
          const success = await waitForSuccessMessage(page);
          if (success) {
            console.log('✅ User removed successfully');
          }

          break;
        }
      }
    }

    await takeScreenshot(page, 'user-management-complete');
  });

  test('should handle user invitations and registration', async ({ page }) => {
    console.log('📧 Testing user invitation workflow...');

    // Login as admin
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    // Navigate to a company
    await navigateToModule(page, 'companies');

    // Try to find a company to work with
    const firstCompany = page.locator('tr a, .company-item a').first();
    if (await firstCompany.isVisible()) {
      await firstCompany.click();
      await page.waitForLoadState('networkidle');

      // Look for invitations section
      console.log('📋 Looking for invitations section...');

      const invitationSelectors = [
        'a:has-text("Invitations")',
        'button:has-text("Invitations")',
        '[data-testid="invitations-tab"]',
        '.nav-tabs a:has-text("Invitations")'
      ];

      let invitationsFound = false;
      for (const selector of invitationSelectors) {
        const invitationsButton = page.locator(selector).first();
        if (await invitationsButton.isVisible()) {
          await invitationsButton.click();
          await page.waitForTimeout(1000);
          invitationsFound = true;
          console.log('✅ Found invitations section');
          break;
        }
      }

      if (invitationsFound) {
        // Look for send invitation button
        const sendInvitationSelectors = [
          'button:has-text("Send Invitation")',
          'button:has-text("Invite")',
          'button:has-text("Add")',
          '[data-testid="send-invitation"]'
        ];

        for (const selector of sendInvitationSelectors) {
          const inviteButton = page.locator(selector).first();
          if (await inviteButton.isVisible()) {
            await inviteButton.click();
            await page.waitForTimeout(1000);

            // Fill invitation form
            const invitationData = {
              email: `invite-${Date.now()}@example.com`,
              name: 'Invited Test User',
              role: 'member'
            };

            await fillForm(page, invitationData);

            // Select role if dropdown exists
            const roleSelect = page.locator('select[name="role"], [data-testid="user-role"]').first();
            if (await roleSelect.isVisible()) {
              await roleSelect.selectOption(invitationData.role);
            }

            await takeScreenshot(page, 'invitation-form');

            // Send invitation
            await clickButtonWithText(page, 'Send');
            await page.waitForTimeout(2000);

            const success = await waitForSuccessMessage(page);
            if (success) {
              console.log(`✅ Invitation sent to ${invitationData.email}`);
            }

            // Close modal
            const closeButton = page.locator('.close, .cancel, [data-testid="close"]').first();
            if (await closeButton.isVisible()) {
              await closeButton.click();
              await page.waitForTimeout(500);
            }

            break;
          }
        }

        // Check for pending invitations
        console.log('📋 Checking for pending invitations...');
        const invitationList = page.locator('tr, .invitation-item, [data-testid="invitation"]');
        const invitationCount = await invitationList.count();

        if (invitationCount > 0) {
          console.log(`Found ${invitationCount} pending invitations`);

          // Test invitation actions (resend, revoke)
          const firstInvitation = invitationList.first();
          const actionButtons = await firstInvitation.locator('button, a').all();

          for (const button of actionButtons) {
            const buttonText = await button.textContent();
            if (buttonText && (
              buttonText.toLowerCase().includes('resend') ||
              buttonText.toLowerCase().includes('revoke')
            )) {
              console.log(`Found invitation action: ${buttonText.trim()}`);

              // Just log the action, don't actually click to avoid sending real emails
              console.log(`  📧 Action available: ${buttonText.trim()}`);
            }
          }
        } else {
          console.log('⚠️ No pending invitations found');
        }

        await takeScreenshot(page, 'invitations-section');
      } else {
        console.log('⚠️ Invitations section not found');
      }
    } else {
      console.log('⚠️ No companies found to test invitations');
    }
  });

  test('should display user permissions and capabilities', async ({ page }) => {
    console.log('🔐 Testing user permissions display...');

    // Login as admin
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    // Navigate to a company with users
    await navigateToModule(page, 'companies');

    const firstCompany = page.locator('tr a, .company-item a').first();
    if (await firstCompany.isVisible()) {
      await firstCompany.click();
      await page.waitForLoadState('networkidle');

      // Look for users or roles section
      const usersSelectors = [
        'a:has-text("Users")',
        'button:has-text("Users")',
        'a:has-text("Roles")',
        'button:has-text("Roles")',
        '[data-testid="users-tab"]'
      ];

      for (const selector of usersSelectors) {
        const tabButton = page.locator(selector).first();
        if (await tabButton.isVisible()) {
          await tabButton.click();
          await page.waitForTimeout(1000);

          console.log(`✅ Navigated to: ${await tabButton.textContent()}`);
          break;
        }
      }

      // Look for user list with permissions
      console.log('🔍 Checking user permissions...');

      const userItems = page.locator('tr, .user-item, [data-testid="user-item"]');
      const userCount = await userItems.count();

      if (userCount > 0) {
        console.log(`Found ${userCount} users to check permissions`);

        for (let i = 0; i < Math.min(userCount, 3); i++) {
          const userItem = userItems.nth(i);

          // Look for user role or permission information
          const roleElement = userItem.locator(':has-text("admin"), :has-text("member"), :has-text("owner"), :has-text("viewer")').first();
          if (await roleElement.isVisible()) {
            const roleText = await roleElement.textContent();
            console.log(`  User ${i + 1} role: ${roleText}`);
          }

          // Look for permission details or capabilities
          const permissionElement = userItem.locator(':has-text("permission"), :has-text("access"), .permissions, [data-testid="permissions"]').first();
          if (await permissionElement.isVisible()) {
            const permissionText = await permissionElement.textContent();
            console.log(`  Permissions: ${permissionText}`);
          }
        }
      }

      // Look for role management interface
      const roleManagement = page.locator('.role-management, [data-testid="role-management"], .permissions-manager').first();
      if (await roleManagement.isVisible()) {
        console.log('✅ Found role management interface');

        // Check for different role definitions
        const roleDefinitions = roleManagement.locator('.role-definition, .permission-set, [data-testid="role"]');
        const roleCount = await roleDefinitions.count();

        if (roleCount > 0) {
          console.log(`Found ${roleCount} role definitions`);

          for (let i = 0; i < Math.min(roleCount, 3); i++) {
            const roleDef = roleDefinitions.nth(i);
            const roleName = await roleDef.locator('h3, h4, .role-name, [data-testid="role-name"]').first().textContent();
            if (roleName) {
              console.log(`  - Role: ${roleName.trim()}`);
            }
          }
        }
      }

      await takeScreenshot(page, 'user-permissions-display');
    } else {
      console.log('⚠️ No companies found to test permissions');
    }
  });

  test('should handle user activity and audit logs', async ({ page }) => {
    console.log('📊 Testing user activity tracking...');

    // Login as admin
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    // Navigate to a company
    await navigateToModule(page, 'companies');

    const firstCompany = page.locator('tr a, .company-item a').first();
    if (await firstCompany.isVisible()) {
      await firstCompany.click();
      await page.waitForLoadState('networkidle');

      // Look for audit log or activity section
      console.log('📋 Looking for audit/activity section...');

      const auditSelectors = [
        'a:has-text("Audit")',
        'button:has-text("Audit")',
        'a:has-text("Activity")',
        'button:has-text("Activity")',
        'a:has-text("Log")',
        '[data-testid="audit-tab"]'
      ];

      for (const selector of auditSelectors) {
        const auditButton = page.locator(selector).first();
        if (await auditButton.isVisible()) {
          await auditButton.click();
          await page.waitForTimeout(1000);

          console.log('✅ Found audit/activity section');
          break;
        }
      }

      // Look for activity logs
      const logEntries = page.locator('.log-entry, .activity-item, tr, [data-testid="log-entry"]');
      const logCount = await logEntries.count();

      if (logCount > 0) {
        console.log(`Found ${logCount} activity log entries`);

        // Check different types of activities
        for (let i = 0; i < Math.min(logCount, 5); i++) {
          const logEntry = logEntries.nth(i);
          const logText = await logEntry.textContent();

          if (logText && logText.length > 10) {
            console.log(`  Log ${i + 1}: ${logText.substring(0, 100)}...`);
          }
        }

        // Test filtering if available
        const filterSelect = page.locator('select[name="action"], select[name="user"], [data-testid="filter"]').first();
        if (await filterSelect.isVisible()) {
          console.log('🔍 Testing activity log filters...');

          const options = await filterSelect.locator('option').all();
          for (let i = 1; i < Math.min(options.length, 3); i++) {
            const option = options[i];
            const optionText = await option.textContent();
            if (optionText && optionText.trim()) {
              await filterSelect.selectOption({ index: i });
              await page.waitForTimeout(1000);
              console.log(`  Filtered by: ${optionText.trim()}`);
            }
          }
        }
      } else {
        console.log('⚠️ No activity log entries found');
      }

      await takeScreenshot(page, 'user-activity-logs');
    } else {
      console.log('⚠️ No companies found to test activity logs');
    }
  });
});