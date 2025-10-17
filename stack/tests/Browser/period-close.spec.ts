import { test, expect } from '@playwright/test'

test.describe('Period Close Workflow', () => {
  test.beforeEach(async ({ page }) => {
    // Login as controller user with period close permissions
    await page.goto('/login')
    await page.fill('input[name="email"]', 'controller@company.com')
    await page.fill('input[name="password"]', 'password')
    await page.click('button[type="submit"]')
    await page.waitForURL('/dashboard')
    
    // Set company context if needed
    // This might be handled by middleware or a company switcher
  })

  test('can navigate to period close dashboard', async ({ page }) => {
    await page.goto('/ledger/period-close')
    await expect(page.locator('h1')).toContainText('Period Close')
    
    // Should see the period close interface
    await expect(page.locator('[data-testid="period-close-dashboard"]')).toBeVisible()
  })

  test('can start a period close workflow', async ({ page }) => {
    // Navigate to a specific period
    await page.goto('/ledger/period-close/2025-10')
    
    // Click start button
    await page.click('[data-testid="start-close-button"]')
    
    // Should see workflow steps
    await expect(page.locator('[data-testid="workflow-steps"]')).toBeVisible()
    
    // Should see checklist tasks
    await expect(page.locator('[data-testid="checklist-tasks"]')).toBeVisible()
    
    // Status should update to in_review
    await expect(page.locator('[data-testid="period-status"]')).toContainText('in review')
  })

  test('can complete checklist tasks', async ({ page }) => {
    // Start a close first
    await page.goto('/ledger/period-close/2025-10')
    await page.click('[data-testid="start-close-button"]')
    
    // Complete trial balance task
    await page.click('[data-testid="task-tb-validate"] [data-testid="complete-button"]')
    await expect(page.locator('[data-testid="task-tb-validate"] [data-testid="task-status"]')).toContainText('completed')
    
    // Complete subledger tasks
    await page.click('[data-testid="task-subledger-ap"] [data-testid="complete-button"]')
    await page.click('[data-testid="task-subledger-ar"] [data-testid="complete-button"]')
    
    // Complete bank reconciliation
    await page.click('[data-testid="task-bank-reconcile"] [data-testid="complete-button"]')
    
    // Complete management reports
    await page.click('[data-testid="task-management-reports"] [data-testid="complete-button"]')
    
    // All required tasks should be completed
    await expect(page.locator('[data-testid="required-tasks-complete"]')).toBeVisible()
  })

  test('can run period close validations', async ({ page }) => {
    // Start and complete checklist
    await page.goto('/ledger/period-close/2025-10')
    await page.click('[data-testid="start-close-button"]')
    
    // Complete all required tasks first
    const tasks = ['tb-validate', 'subledger-ap', 'subledger-ar', 'bank-reconcile', 'management-reports']
    for (const task of tasks) {
      await page.click(`[data-testid="task-${task}"] [data-testid="complete-button"]`)
    }
    
    // Run validations
    await page.click('[data-testid="run-validations-button"]')
    
    // Should see validation results
    await expect(page.locator('[data-testid="validation-results"]')).toBeVisible()
    
    // Should show trial balance variance
    await expect(page.locator('[data-testid="trial-balance-variance"]')).toBeVisible()
    
    // Should show unposted documents
    await expect(page.locator('[data-testid="unposted-documents"]')).toBeVisible()
  })

  test('can lock period after validations pass', async ({ page }) => {
    // Complete setup and validations
    await page.goto('/ledger/period-close/2025-10')
    await page.click('[data-testid="start-close-button"]')
    
    // Complete all tasks
    const tasks = ['tb-validate', 'subledger-ap', 'subledger-ar', 'bank-reconcile', 'management-reports']
    for (const task of tasks) {
      await page.click(`[data-testid="task-${task}"] [data-testid="complete-button"]`)
    }
    
    // Run validations
    await page.click('[data-testid="run-validations-button"]')
    await page.waitForSelector('[data-testid="validation-results"]')
    
    // Lock period (only enabled if validations pass)
    await expect(page.locator('[data-testid="lock-period-button"]')).toBeEnabled()
    await page.click('[data-testid="lock-period-button"]')
    
    // Fill lock dialog
    await page.fill('[data-testid="lock-summary"]', 'All reconciliations completed, variance is zero')
    await page.click('[data-testid="confirm-lock-button"]')
    
    // Status should update to locked
    await expect(page.locator('[data-testid="period-status"]')).toContainText('locked')
    
    // Checklist should become read-only
    await expect(page.locator('[data-testid="checklist-tasks"]')).toHaveClass(/read-only/)
  })

  test('can complete final close', async ({ page }) => {
    // Go through the full workflow up to lock
    await page.goto('/ledger/period-close/2025-10')
    await page.click('[data-testid="start-close-button"]')
    
    // Complete tasks
    const tasks = ['tb-validate', 'subledger-ap', 'subledger-ar', 'bank-reconcile', 'management-reports']
    for (const task of tasks) {
      await page.click(`[data-testid="task-${task}"] [data-testid="complete-button"]`)
    }
    
    // Run validations and lock
    await page.click('[data-testid="run-validations-button"]')
    await page.waitForSelector('[data-testid="validation-results"]')
    await page.click('[data-testid="lock-period-button"]')
    await page.fill('[data-testid="lock-summary"]', 'Ready to close')
    await page.click('[data-testid="confirm-lock-button"]')
    
    // Complete final close
    await expect(page.locator('[data-testid="complete-close-button"]')).toBeEnabled()
    await page.click('[data-testid="complete-close-button"]')
    
    // Fill close dialog
    await page.fill('[data-testid="closing-summary"]', 'October 2025 period closed successfully')
    await page.click('[data-testid="confirm-close-button"]')
    
    // Status should update to closed
    await expect(page.locator('[data-testid="period-status"]')).toContainText('closed')
    
    // All actions should be disabled
    await expect(page.locator('[data-testid="start-close-button"]')).toBeDisabled()
    await expect(page.locator('[data-testid="lock-period-button"]')).toBeDisabled()
    await expect(page.locator('[data-testid="complete-close-button"]')).toBeDisabled()
    
    // Reopen button should be available for authorized users
    await expect(page.locator('[data-testid="reopen-period-button"]')).toBeVisible()
  })

  test('can reopen a closed period', async ({ page }) => {
    // First complete a full close
    await completeFullCloseWorkflow(page)
    
    // Reopen the period
    await page.click('[data-testid="reopen-period-button"]')
    
    // Fill reopen dialog
    await page.fill('[data-testid="reopen-reason"]', 'Post-audit adjustment required')
    await page.click('[data-testid="confirm-reopen-button"]')
    
    // Status should update to reopened
    await expect(page.locator('[data-testid="period-status"]')).toContainText('reopened')
    
    // Should be able to start close workflow again
    await expect(page.locator('[data-testid="start-close-button"]')).toBeEnabled()
  })

  test('prevents editing closed periods', async ({ page }) => {
    // Complete a full close first
    await completeFullCloseWorkflow(page)
    
    // Try to navigate to journal entry creation for the period
    await page.goto('/ledger/journal-entries/create?period=2025-10')
    
    // Should show error message
    await expect(page.locator('[data-testid="period-closed-error"]')).toBeVisible()
    await expect(page.locator('[data-testid="period-closed-error"]')).toContainText('Period is closed and cannot be modified')
    
    // Form should be disabled
    await expect(page.locator('form[data-testid="journal-entry-form"]')).toBeDisabled()
  })

  test('validates checklist completion', async ({ page }) => {
    // Start close but don't complete all tasks
    await page.goto('/ledger/period-close/2025-10')
    await page.click('[data-testid="start-close-button"]')
    
    // Try to run validations without completing required tasks
    await page.click('[data-testid="run-validations-button"]')
    
    // Should show warning about incomplete tasks
    await expect(page.locator('[data-testid="incomplete-tasks-warning"]')).toBeVisible()
    
    // Lock button should be disabled
    await expect(page.locator('[data-testid="lock-period-button"]')).toBeDisabled()
  })

  test('handles validation failures', async ({ page }) => {
    // Start close and complete tasks
    await page.goto('/ledger/period-close/2025-10')
    await page.click('[data-testid="start-close-button"]')
    
    const tasks = ['tb-validate', 'subledger-ap', 'subledger-ar', 'bank-reconcile', 'management-reports']
    for (const task of tasks) {
      await page.click(`[data-testid="task-${task}"] [data-testid="complete-button"]`)
    }
    
    // Mock validation failure (this would require test data setup)
    await page.click('[data-testid="run-validations-button"]')
    
    // If trial balance is out of balance, should show error
    const varianceElement = page.locator('[data-testid="trial-balance-variance"]')
    if (await varianceElement.isVisible()) {
      const varianceText = await varianceElement.textContent()
      if (varianceText && varianceText !== '$0.00') {
        await expect(page.locator('[data-testid="variance-error"]')).toBeVisible()
        await expect(page.locator('[data-testid="complete-close-button"]')).toBeDisabled()
      }
    }
  })

  test('displays correct workflow progress', async ({ page }) => {
    await page.goto('/ledger/period-close/2025-10')
    
    // Initially should show first step as active
    await expect(page.locator('[data-testid="step-0"]')).toHaveClass(/active/)
    await expect(page.locator('[data-testid="step-1"]')).toHaveClass(/wait/)
    
    // Start close
    await page.click('[data-testid="start-close-button"]')
    await expect(page.locator('[data-testid="step-0"]')).toHaveClass(/completed/)
    await expect(page.locator('[data-testid="step-1"]')).toHaveClass(/active/)
    
    // Complete tasks
    const tasks = ['tb-validate', 'subledger-ap', 'subledger-ar', 'bank-reconcile', 'management-reports']
    for (const task of tasks) {
      await page.click(`[data-testid="task-${task}"] [data-testid="complete-button"]`)
    }
    
    // Run validations
    await page.click('[data-testid="run-validations-button"]')
    await expect(page.locator('[data-testid="step-1"]')).toHaveClass(/completed/)
    await expect(page.locator('[data-testid="step-2"]')).toHaveClass(/active/)
    
    // Lock period
    await page.click('[data-testid="lock-period-button"]')
    await page.fill('[data-testid="lock-summary"]', 'Locking period')
    await page.click('[data-testid="confirm-lock-button"]')
    await expect(page.locator('[data-testid="step-2"]')).toHaveClass(/completed/)
    await expect(page.locator('[data-testid="step-3"]')).toHaveClass(/active/)
  })

  test('supports keyboard navigation', async ({ page }) => {
    await page.goto('/ledger/period-close/2025-10')
    
    // Navigate with keyboard
    await page.keyboard.press('Tab')
    await expect(page.locator(':focus')).toBe(page.locator('[data-testid="start-close-button"]'))
    
    // Start with keyboard
    await page.keyboard.press('Enter')
    await expect(page.locator('[data-testid="workflow-steps"]')).toBeVisible()
    
    // Navigate to tasks
    await page.keyboard.press('Tab')
    await expect(page.locator(':focus')).toBe(page.locator('[data-testid="task-tb-validate"] [data-testid="complete-button"]'))
    
    // Complete task with keyboard
    await page.keyboard.press('Enter')
    await expect(page.locator('[data-testid="task-tb-validate"] [data-testid="task-status"]')).toContainText('completed')
  })

  test('handles network errors gracefully', async ({ page }) => {
    // Mock network failure
    await page.route('/api/v1/ledger/periods/*/close/start', route => route.abort())
    
    await page.goto('/ledger/period-close/2025-10')
    await page.click('[data-testid="start-close-button"]')
    
    // Should show error message
    await expect(page.locator('[data-testid="network-error"]')).toBeVisible()
    await expect(page.locator('[data-testid="network-error"]')).toContainText('Failed to start period close')
    
    // Should be able to retry
    await page.unroute('/api/v1/ledger/periods/*/close/start')
    await page.click('[data-testid="retry-button"]')
    
    // Should work normally now
    await expect(page.locator('[data-testid="workflow-steps"]')).toBeVisible()
  })

  test('can create a new period close template', async ({ page }) => {
    await page.goto('/ledger/period-close')
    
    // Click on templates management
    await page.click('[data-testid="templates-manage-button"]')
    
    // Should see template drawer
    await expect(page.locator('[data-testid="template-drawer"]')).toBeVisible()
    
    // Click create new template
    await page.click('[data-testid="create-template-button"]')
    
    // Fill template details
    await page.fill('[data-testid="template-name"]', 'Monthly Close Template 2025')
    await page.selectOption('[data-testid="template-frequency"]', 'monthly')
    await page.fill('[data-testid="template-description"]', 'Standard monthly closing checklist for 2025')
    
    // Add first task
    await page.click('[data-testid="add-task-button"]')
    await page.fill('[data-testid="task-code"]', 'tb_validate')
    await page.fill('[data-testid="task-title"]', 'Validate Trial Balance')
    await page.selectOption('[data-testid="task-category"]', 'trial_balance')
    await page.check('[data-testid="task-required"]')
    await page.click('[data-testid="save-task-button"]')
    
    // Add second task
    await page.click('[data-testid="add-task-button"]')
    await page.fill('[data-testid="task-code"]', 'gl_reconcile')
    await page.fill('[data-testid="task-title"]', 'Reconcile General Ledger')
    await page.selectOption('[data-testid="task-category"]', 'reconciliations')
    await page.check('[data-testid="task-required"]')
    await page.click('[data-testid="save-task-button"]')
    
    // Add third task (optional)
    await page.click('[data-testid="add-task-button"]')
    await page.fill('[data-testid="task-code"]', 'reports_generate')
    await page.fill('[data-testid="task-title"]', 'Generate Financial Reports')
    await page.selectOption('[data-testid="task-category"]', 'reporting')
    await page.uncheck('[data-testid="task-required"]')
    await page.fill('[data-testid="task-notes"]', 'Generate standard financial statements')
    await page.click('[data-testid="save-task-button"]')
    
    // Set as default template
    await page.check('[data-testid="template-default"]')
    
    // Save template
    await page.click('[data-testid="save-template-button"]')
    
    // Should show success message
    await expect(page.locator('[data-testid="success-message"]')).toContainText('Template created successfully')
    
    // Template should appear in templates list
    await expect(page.locator('[data-testid="template-list"]')).toContainText('Monthly Close Template 2025')
  })

  test('can edit an existing template', async ({ page }) => {
    // First create a template
    await createTestTemplate(page, 'Test Template for Editing')
    
    // Go to templates management
    await page.goto('/ledger/period-close')
    await page.click('[data-testid="templates-manage-button"]')
    
    // Find and click edit button for our template
    const templateRow = page.locator(`[data-testid="template-row"]:has-text("Test Template for Editing")`)
    await templateRow.locator('[data-testid="edit-template-button"]').click()
    
    // Should see edit mode
    await expect(page.locator('[data-testid="template-drawer"]')).toBeVisible()
    await expect(page.locator('[data-testid="template-name"]')).toHaveValue('Test Template for Editing')
    
    // Update template details
    await page.fill('[data-testid="template-name"]', 'Updated Template Name')
    await page.fill('[data-testid="template-description"]', 'Updated description for testing')
    
    // Edit first task
    await page.click('[data-testid="edit-task-button"]:first-child')
    await page.fill('[data-testid="task-title"]', 'Updated Task Title')
    await page.click('[data-testid="save-task-button"]')
    
    // Add new task
    await page.click('[data-testid="add-task-button"]')
    await page.fill('[data-testid="task-code"]', 'new_task')
    await page.fill('[data-testid="task-title"]', 'New Additional Task')
    await page.selectOption('[data-testid="task-category"]', 'other')
    await page.click('[data-testid="save-task-button"]')
    
    // Save changes
    await page.click('[data-testid="save-template-button"]')
    
    // Should show success message
    await expect(page.locator('[data-testid="success-message"]')).toContainText('Template updated successfully')
  })

  test('can duplicate an existing template', async ({ page }) => {
    // Create a template with tasks
    await createTestTemplate(page, 'Original Template')
    
    // Go to templates management
    await page.goto('/ledger/period-close')
    await page.click('[data-testid="templates-manage-button"]')
    
    // Find and click duplicate button
    const templateRow = page.locator(`[data-testid="template-row"]:has-text("Original Template")`)
    await templateRow.locator('[data-testid="duplicate-template-button"]').click()
    
    // Should show duplicate dialog
    await expect(page.locator('[data-testid="duplicate-template-dialog"]')).toBeVisible()
    
    // Fill new template details
    await page.fill('[data-testid="duplicate-template-name"]', 'Duplicated Template')
    await page.fill('[data-testid="duplicate-template-description"]', 'Copy of original template')
    
    // Confirm duplication
    await page.click('[data-testid="confirm-duplicate-button"]')
    
    // Should show success message
    await expect(page.locator('[data-testid="success-message"]')).toContainText('Template duplicated successfully')
    
    // Should see both templates in list
    await expect(page.locator('[data-testid="template-list"]')).toContainText('Original Template')
    await expect(page.locator('[data-testid="template-list"]')).toContainText('Duplicated Template')
  })

  test('can archive a template', async ({ page }) => {
    // Create a template first
    await createTestTemplate(page, 'Template to Archive')
    
    // Go to templates management
    await page.goto('/ledger/period-close')
    await page.click('[data-testid="templates-manage-button"]')
    
    // Find and click edit button
    const templateRow = page.locator(`[data-testid="template-row"]:has-text("Template to Archive")`)
    await templateRow.locator('[data-testid="edit-template-button"]').click()
    
    // Click archive button in edit mode
    await page.click('[data-testid="archive-template-button"]')
    
    // Should show confirmation dialog
    await expect(page.locator('[data-testid="archive-confirmation-dialog"]')).toBeVisible()
    
    // Confirm archive
    await page.click('[data-testid="confirm-archive-button"]')
    
    // Should show success message
    await expect(page.locator('[data-testid="success-message"]')).toContainText('Template archived successfully')
    
    // Template should no longer appear in active templates list
    await expect(page.locator('[data-testid="template-list"]')).not.toContainText('Template to Archive')
  })

  test('can sync template to period close', async ({ page }) => {
    // Create a template with tasks
    await createTestTemplate(page, 'Sync Test Template')
    
    // Start a period close
    await page.goto('/ledger/period-close/2025-10')
    await page.click('[data-testid="start-close-button"]')
    
    // Go to templates management
    await page.click('[data-testid="templates-manage-button"]')
    
    // Find and click edit button for our template
    const templateRow = page.locator(`[data-testid="template-row"]:has-text("Sync Test Template")`)
    await templateRow.locator('[data-testid="edit-template-button"]').click()
    
    // Click sync button
    await page.click('[data-testid="sync-template-button"]')
    
    // Should show sync dialog
    await expect(page.locator('[data-testid="sync-template-dialog"]')).toBeVisible()
    
    // Select current period close
    await page.selectOption('[data-testid="period-close-select"]', '2025-10')
    
    // Confirm sync
    await page.click('[data-testid="confirm-sync-button"]')
    
    // Should show success message
    await expect(page.locator('[data-testid="success-message"]')).toContainText('Template synced successfully')
    
    // Return to period close and check if tasks were synced
    await page.goto('/ledger/period-close/2025-10')
    
    // Should see tasks from template
    await expect(page.locator('[data-testid="checklist-tasks"]')).toContainText('Validate Trial Balance')
    await expect(page.locator('[data-testid="checklist-tasks"]')).toContainText('Reconcile General Ledger')
  })

  test('validates template creation requirements', async ({ page }) => {
    await page.goto('/ledger/period-close')
    await page.click('[data-testid="templates-manage-button"]')
    await page.click('[data-testid="create-template-button"]')
    
    // Try to save without name
    await page.click('[data-testid="save-template-button"]')
    await expect(page.locator('[data-testid="validation-error"]')).toContainText('Template name is required')
    
    // Try to save without frequency
    await page.fill('[data-testid="template-name"]', 'Test Template')
    await page.click('[data-testid="save-template-button"]')
    await expect(page.locator('[data-testid="validation-error"]')).toContainText('Template frequency is required')
    
    // Try to save without tasks
    await page.selectOption('[data-testid="template-frequency"]', 'monthly')
    await page.click('[data-testid="save-template-button"]')
    await expect(page.locator('[data-testid="validation-error"]')).toContainText('At least one task is required')
    
    // Add task with invalid data
    await page.click('[data-testid="add-task-button"]')
    await page.click('[data-testid="save-task-button"]')
    await expect(page.locator('[data-testid="task-validation-error"]')).toContainText('Task code is required')
  })

  test('prevents duplicate template names', async ({ page }) => {
    // Create first template
    await createTestTemplate(page, 'Duplicate Name Template')
    
    // Try to create second template with same name
    await page.goto('/ledger/period-close')
    await page.click('[data-testid="templates-manage-button"]')
    await page.click('[data-testid="create-template-button"]')
    
    await page.fill('[data-testid="template-name"]', 'Duplicate Name Template')
    await page.selectOption('[data-testid="template-frequency"]', 'monthly')
    
    // Add a task
    await page.click('[data-testid="add-task-button"]')
    await page.fill('[data-testid="task-code"]', 'test_task')
    await page.fill('[data-testid="task-title"]', 'Test Task')
    await page.selectOption('[data-testid="task-category"]', 'other')
    await page.click('[data-testid="save-task-button"]')
    
    // Try to save
    await page.click('[data-testid="save-template-button"]')
    
    // Should show validation error
    await expect(page.locator('[data-testid="validation-error"]')).toContainText('A template with this name already exists')
  })

  test('manages template task ordering', async ({ page }) => {
    await page.goto('/ledger/period-close')
    await page.click('[data-testid="templates-manage-button"]')
    await page.click('[data-testid="create-template-button"]')
    
    await page.fill('[data-testid="template-name"]', 'Task Order Test')
    await page.selectOption('[data-testid="template-frequency"]', 'monthly')
    
    // Add multiple tasks
    const tasks = [
      { code: 'task_1', title: 'First Task', category: 'trial_balance' },
      { code: 'task_2', title: 'Second Task', category: 'reconciliations' },
      { code: 'task_3', title: 'Third Task', category: 'reporting' }
    ]
    
    for (const task of tasks) {
      await page.click('[data-testid="add-task-button"]')
      await page.fill('[data-testid="task-code"]', task.code)
      await page.fill('[data-testid="task-title"]', task.title)
      await page.selectOption('[data-testid="task-category"]', task.category)
      await page.click('[data-testid="save-task-button"]')
    }
    
    // Verify initial order
    await expect(page.locator('[data-testid="task-table"] tr:nth-child(1)')).toContainText('First Task')
    await expect(page.locator('[data-testid="task-table"] tr:nth-child(2)')).toContainText('Second Task')
    await expect(page.locator('[data-testid="task-table"] tr:nth-child(3)')).toContainText('Third Task')
    
    // Move second task up
    await page.click('[data-testid="move-task-up"]:nth-child(2)')
    
    // Verify new order
    await expect(page.locator('[data-testid="task-table"] tr:nth-child(1)')).toContainText('Second Task')
    await expect(page.locator('[data-testid="task-table"] tr:nth-child(2)')).toContainText('First Task')
    await expect(page.locator('[data-testid="task-table"] tr:nth-child(3)')).toContainText('Third Task')
    
    // Move first task down
    await page.click('[data-testid="move-task-down"]:first-child')
    
    // Verify final order
    await expect(page.locator('[data-testid="task-table"] tr:nth-child(1)')).toContainText('First Task')
    await expect(page.locator('[data-testid="task-table"] tr:nth-child(2)')).toContainText('Third Task')
    await expect(page.locator('[data-testid="task-table"] tr:nth-child(3)')).toContainText('Second Task')
  })

  test('displays template statistics', async ({ page }) => {
    // Create templates with different properties
    await createTestTemplate(page, 'Active Template 1')
    await createTestTemplate(page, 'Active Template 2')
    
    // Go to templates management
    await page.goto('/ledger/period-close')
    await page.click('[data-testid="templates-manage-button"]')
    
    // Should show statistics
    await expect(page.locator('[data-testid="template-statistics"]')).toBeVisible()
    await expect(page.locator('[data-testid="total-templates"]')).toContainText('2')
    await expect(page.locator('[data-testid="active-templates"]')).toContainText('2')
  })
})

// Helper function to complete full close workflow
async function completeFullCloseWorkflow(page: any) {
  await page.goto('/ledger/period-close/2025-10')
  await page.click('[data-testid="start-close-button"]')
  
  // Complete all tasks
  const tasks = ['tb-validate', 'subledger-ap', 'subledger-ar', 'bank-reconcile', 'management-reports']
  for (const task of tasks) {
    await page.click(`[data-testid="task-${task}"] [data-testid="complete-button"]`)
  }
  
  // Run validations
  await page.click('[data-testid="run-validations-button"]')
  await page.waitForSelector('[data-testid="validation-results"]')
  
  // Lock period
  await page.click('[data-testid="lock-period-button"]')
  await page.fill('[data-testid="lock-summary"]', 'Period ready for closing')
  await page.click('[data-testid="confirm-lock-button"]')
  
  // Complete close
  await page.click('[data-testid="complete-close-button"]')
  await page.fill('[data-testid="closing-summary"]', 'Period closed successfully')
  await page.click('[data-testid="confirm-close-button"]')
  
  // Wait for completion
  await expect(page.locator('[data-testid="period-status"]')).toContainText('closed')
}

// Helper function to create a test template
async function createTestTemplate(page: any, templateName: string) {
  await page.goto('/ledger/period-close')
  await page.click('[data-testid="templates-manage-button"]')
  await page.click('[data-testid="create-template-button"]')
  
  // Fill template details
  await page.fill('[data-testid="template-name"]', templateName)
  await page.selectOption('[data-testid="template-frequency"]', 'monthly')
  await page.fill('[data-testid="template-description"]', `Test template: ${templateName}`)
  
  // Add standard tasks
  const tasks = [
    { code: 'tb_validate', title: 'Validate Trial Balance', category: 'trial_balance' },
    { code: 'gl_reconcile', title: 'Reconcile General Ledger', category: 'reconciliations' },
    { code: 'reports_generate', title: 'Generate Financial Reports', category: 'reporting' }
  ]
  
  for (const task of tasks) {
    await page.click('[data-testid="add-task-button"]')
    await page.fill('[data-testid="task-code"]', task.code)
    await page.fill('[data-testid="task-title"]', task.title)
    await page.selectOption('[data-testid="task-category"]', task.category)
    await page.check('[data-testid="task-required"]')
    await page.click('[data-testid="save-task-button"]')
  }
  
  // Save template
  await page.click('[data-testid="save-template-button"]')
  
  // Wait for success message
  await expect(page.locator('[data-testid="success-message"]')).toContainText('Template created successfully')
}