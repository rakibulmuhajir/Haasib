import type { QuickAction, TableState } from '@/types/palette'

/**
 * Quick Actions System
 *
 * Provides numbered shortcuts for common actions after list commands.
 * Actions are context-aware and can be row-specific.
 */

/**
 * Get quick actions for a given entity/verb combination
 */
export function getQuickActions(entity: string, verb: string): QuickAction[] {
  const key = `${entity}.${verb}`

  switch (key) {
    // --------------------------------------------------------------------------
    // COMPANY
    // --------------------------------------------------------------------------
    case 'company.list':
      return [
        {
          key: '1',
          label: 'Switch to company',
          command: 'company switch {slug}',
          needsRow: true,
        },
        {
          key: '2',
          label: 'View details',
          command: 'company view {slug}',
          needsRow: true,
        },
        {
          key: '3',
          label: 'Assign user',
          command: 'user invite {user_input}',
          needsRow: true,
          prompt: 'Enter user email (optionally add role, e.g., jane@x.com --role owner)',
        },
        {
          key: '4',
          label: 'Create new company',
          command: 'company create {user_input}',
          needsRow: false,
          prompt: 'Enter company name and currency (e.g., Acme Inc USD)',
        },
        {
          key: '0',
          label: 'Delete company',
          command: 'company delete {slug}',
          needsRow: true,
          prompt: 'Type "confirm" to delete this company permanently',
        },
      ]

    // --------------------------------------------------------------------------
    // USER
    // --------------------------------------------------------------------------
    case 'user.list':
      return [
        {
          key: '1',
          label: 'View user',
          command: 'user view {email}',
          needsRow: true,
        },
        {
          key: '2',
          label: 'Assign role',
          command: 'user assign-role {email} {user_input}',
          needsRow: true,
          prompt: 'Enter role name',
        },
        {
          key: '3',
          label: 'Deactivate user',
          command: 'user deactivate {email}',
          needsRow: true,
          prompt: 'Type "confirm" to deactivate this user',
        },
        {
          key: '4',
          label: 'Invite new user',
          command: 'user invite {user_input}',
          needsRow: false,
          prompt: 'Enter email address',
        },
      ]

    case 'role.list':
      return [
        {
          key: '1',
          label: 'View role',
          command: 'role view {name}',
          needsRow: true,
        },
        {
          key: '2',
          label: 'Assign permission',
          command: 'role assign {user_input}',
          needsRow: true,
          prompt: 'Enter permission name',
        },
        {
          key: '3',
          label: 'Revoke permission',
          command: 'role revoke {user_input}',
          needsRow: true,
          prompt: 'Enter permission name',
        },
      ]

    // --------------------------------------------------------------------------
    // CUSTOMER
    // --------------------------------------------------------------------------
    case 'customer.list':
      return [
        {
          key: '1',
          label: 'Create invoice',
          command: 'invoice create {name} {user_input}',
          needsRow: true,
          prompt: 'Enter invoice amount',
        },
        {
          key: '2',
          label: 'View customer',
          command: 'customer view {id}',
          needsRow: true,
        },
        {
          key: '3',
          label: 'View invoices',
          command: 'invoice list --customer={name}',
          needsRow: true,
        },
        {
          key: '4',
          label: 'View payments',
          command: 'payment list --customer={name}',
          needsRow: true,
        },
        {
          key: '5',
          label: 'Create new customer',
          command: 'customer create {user_input}',
          needsRow: false,
          prompt: 'Enter customer name',
        },
        {
          key: '0',
          label: 'Delete customer',
          command: 'customer delete {id}',
          needsRow: true,
          prompt: 'Type "confirm" to delete this customer',
        },
      ]

    // --------------------------------------------------------------------------
    // INVOICE
    // --------------------------------------------------------------------------
    case 'invoice.list':
      return [
        {
          key: '1',
          label: 'View invoice',
          command: 'invoice view {id}',
          needsRow: true,
        },
        {
          key: '2',
          label: 'Record payment',
          command: 'payment create {id} {user_input}',
          needsRow: true,
          prompt: 'Enter payment amount',
        },
        {
          key: '3',
          label: 'Send invoice',
          command: 'invoice send {id}',
          needsRow: true,
        },
        {
          key: '4',
          label: 'Duplicate invoice',
          command: 'invoice duplicate {id}',
          needsRow: true,
        },
        {
          key: '5',
          label: 'Create new invoice',
          command: 'invoice create {user_input}',
          needsRow: false,
          prompt: 'Enter customer name and amount (e.g., Acme Corp 1500)',
        },
        {
          key: '0',
          label: 'Void invoice',
          command: 'invoice void {id}',
          needsRow: true,
          prompt: 'Type "confirm" to void this invoice',
        },
      ]

    // --------------------------------------------------------------------------
    // PAYMENT
    // --------------------------------------------------------------------------
    case 'payment.list':
      return [
        {
          key: '1',
          label: 'View payment',
          command: 'payment view {id}',
          needsRow: true,
        },
        {
          key: '2',
          label: 'View invoice',
          command: 'invoice view {invoice}',
          needsRow: true,
        },
        {
          key: '3',
          label: 'Record new payment',
          command: 'payment create {user_input}',
          needsRow: false,
          prompt: 'Enter invoice number and amount (e.g., INV-001 500)',
        },
        {
          key: '0',
          label: 'Void payment',
          command: 'payment void {id}',
          needsRow: true,
          prompt: 'Type "confirm" to void this payment',
        },
      ]

    // --------------------------------------------------------------------------
    // VENDOR
    // --------------------------------------------------------------------------
    case 'vendor.list':
      return [
        {
          key: '1',
          label: 'Create bill',
          command: 'bill create {name} {user_input}',
          needsRow: true,
          prompt: 'Enter bill amount',
        },
        {
          key: '2',
          label: 'View vendor',
          command: 'vendor view {id}',
          needsRow: true,
        },
        {
          key: '3',
          label: 'View bills',
          command: 'bill list --vendor={name}',
          needsRow: true,
        },
        {
          key: '4',
          label: 'Create new vendor',
          command: 'vendor create {user_input}',
          needsRow: false,
          prompt: 'Enter vendor name',
        },
        {
          key: '0',
          label: 'Delete vendor',
          command: 'vendor delete {id}',
          needsRow: true,
          prompt: 'Type "confirm" to delete this vendor',
        },
      ]

    // --------------------------------------------------------------------------
    // BILL
    // --------------------------------------------------------------------------
    case 'bill.list':
      return [
        {
          key: '1',
          label: 'View bill',
          command: 'bill view {id}',
          needsRow: true,
        },
        {
          key: '2',
          label: 'Pay bill',
          command: 'bill pay {id} {user_input}',
          needsRow: true,
          prompt: 'Enter payment amount (leave empty for full payment)',
        },
        {
          key: '3',
          label: 'View vendor',
          command: 'vendor view {vendor}',
          needsRow: true,
        },
        {
          key: '4',
          label: 'Create new bill',
          command: 'bill create {user_input}',
          needsRow: false,
          prompt: 'Enter vendor name and amount (e.g., Supplier Inc 500)',
        },
        {
          key: '0',
          label: 'Void bill',
          command: 'bill void {id}',
          needsRow: true,
          prompt: 'Type "confirm" to void this bill',
        },
      ]

    // --------------------------------------------------------------------------
    // EXPENSE
    // --------------------------------------------------------------------------
    case 'expense.list':
      return [
        {
          key: '1',
          label: 'View expense',
          command: 'expense view {id}',
          needsRow: true,
        },
        {
          key: '2',
          label: 'Duplicate expense',
          command: 'expense duplicate {id}',
          needsRow: true,
        },
        {
          key: '3',
          label: 'Create new expense',
          command: 'expense create {user_input}',
          needsRow: false,
          prompt: 'Enter amount and category (e.g., 50 Office Supplies)',
        },
        {
          key: '0',
          label: 'Delete expense',
          command: 'expense delete {id}',
          needsRow: true,
          prompt: 'Type "confirm" to delete this expense',
        },
      ]

    // --------------------------------------------------------------------------
    // PRODUCT
    // --------------------------------------------------------------------------
    case 'product.list':
      return [
        {
          key: '1',
          label: 'View product',
          command: 'product view {id}',
          needsRow: true,
        },
        {
          key: '2',
          label: 'Edit product',
          command: 'product edit {id}',
          needsRow: true,
        },
        {
          key: '3',
          label: 'Create invoice with product',
          command: 'invoice create --product={name} {user_input}',
          needsRow: true,
          prompt: 'Enter customer name',
        },
        {
          key: '4',
          label: 'Create new product',
          command: 'product create {user_input}',
          needsRow: false,
          prompt: 'Enter product name and price (e.g., Widget 29.99)',
        },
        {
          key: '0',
          label: 'Delete product',
          command: 'product delete {id}',
          needsRow: true,
          prompt: 'Type "confirm" to delete this product',
        },
      ]

    // --------------------------------------------------------------------------
    // ACCOUNT (Chart of Accounts)
    // --------------------------------------------------------------------------
    case 'account.list':
      return [
        {
          key: '1',
          label: 'View account',
          command: 'account view {id}',
          needsRow: true,
        },
        {
          key: '2',
          label: 'View transactions',
          command: 'journal list --account={code}',
          needsRow: true,
        },
        {
          key: '3',
          label: 'Create sub-account',
          command: 'account create --parent={code} {user_input}',
          needsRow: true,
          prompt: 'Enter account name and type',
        },
        {
          key: '4',
          label: 'Create new account',
          command: 'account create {user_input}',
          needsRow: false,
          prompt: 'Enter account name and type (e.g., Cash asset)',
        },
      ]

    // --------------------------------------------------------------------------
    // JOURNAL
    // --------------------------------------------------------------------------
    case 'journal.list':
      return [
        {
          key: '1',
          label: 'View entry',
          command: 'journal view {id}',
          needsRow: true,
        },
        {
          key: '2',
          label: 'Reverse entry',
          command: 'journal reverse {id}',
          needsRow: true,
          prompt: 'Type "confirm" to create reversal entry',
        },
        {
          key: '3',
          label: 'Create new entry',
          command: 'journal create {user_input}',
          needsRow: false,
          prompt: 'Enter description',
        },
      ]

    // --------------------------------------------------------------------------
    // REPORT
    // --------------------------------------------------------------------------
    case 'report.list':
      return [
        {
          key: '1',
          label: 'Generate report',
          command: 'report generate {type}',
          needsRow: true,
        },
        {
          key: '2',
          label: 'Profit & Loss',
          command: 'report generate profit_loss --period=this_month',
          needsRow: false,
        },
        {
          key: '3',
          label: 'Balance Sheet',
          command: 'report generate balance_sheet',
          needsRow: false,
        },
        {
          key: '4',
          label: 'Aged Receivables',
          command: 'report generate aged_receivables',
          needsRow: false,
        },
        {
          key: '5',
          label: 'Aged Payables',
          command: 'report generate aged_payables',
          needsRow: false,
        },
      ]

    // --------------------------------------------------------------------------
    // TAX
    // --------------------------------------------------------------------------
    case 'tax.list':
      return [
        {
          key: '1',
          label: 'View tax',
          command: 'tax view {id}',
          needsRow: true,
        },
        {
          key: '2',
          label: 'Edit tax rate',
          command: 'tax edit {id}',
          needsRow: true,
        },
        {
          key: '3',
          label: 'Create new tax',
          command: 'tax create {user_input}',
          needsRow: false,
          prompt: 'Enter tax name and rate (e.g., VAT 15)',
        },
      ]

    default:
      return []
  }
}

/**
 * Check if entity.verb combination supports quick actions
 */
export function hasQuickActions(entity: string, verb: string): boolean {
  return getQuickActions(entity, verb).length > 0
}

/**
 * Resolve command template with row data
 *
 * @param template - Command template with {field} placeholders
 * @param tableState - Current table state
 * @returns Resolved command string
 */
export function resolveQuickActionCommand(
  template: string,
  tableState: TableState | null
): string | null {
  if (!template) return null

  // If template has no placeholders, return as-is
  if (!template.includes('{')) {
    return template
  }

  // Need table state to resolve placeholders
  if (!tableState) return null

  const { headers, rows, selectedRowIndex } = tableState

  // Need a selected row
  if (selectedRowIndex < 0 || selectedRowIndex >= rows.length) {
    return null
  }

  const selectedRow = rows[selectedRowIndex]
  let resolved = template

  // Handle {id} placeholder specially - use rowIds if available
  if (resolved.includes('{id}') && tableState.rowIds && tableState.rowIds[selectedRowIndex]) {
    resolved = resolved.replace(/\{id\}/g, tableState.rowIds[selectedRowIndex])
  }

  // Replace {field} placeholders with row values
  // Match headers to find the right column
  headers.forEach((header, index) => {
    const value = selectedRow[index] || ''
    const normalizedHeader = header.toLowerCase().replace(/\s+/g, '')

    // Try to match common patterns
    const patterns = [
      `{${normalizedHeader}}`,
      `{${header.toLowerCase()}}`,
      `{${header}}`,
    ]

    patterns.forEach(pattern => {
      resolved = resolved.replace(new RegExp(pattern.replace(/[{}]/g, '\\$&'), 'g'), value)
    })
  })

  // Handle common field name mappings
  const mappings: Record<string, string[]> = {
    slug: ['slug', 'id', 'name', 'company'],
    email: ['email', 'user', 'emailaddress'],
    name: ['name', 'rolename', 'role'],
  }

  Object.entries(mappings).forEach(([placeholder, possibleHeaders]) => {
    const pattern = `{${placeholder}}`
    if (resolved.includes(pattern)) {
      // Find matching header
      for (const possibleHeader of possibleHeaders) {
        const headerIndex = headers.findIndex(h =>
          h.toLowerCase().replace(/\s+/g, '') === possibleHeader
        )
        if (headerIndex >= 0) {
          const value = selectedRow[headerIndex] || ''
          resolved = resolved.replace(new RegExp(pattern.replace(/[{}]/g, '\\$&'), 'g'), value)
          break
        }
      }
    }
  })

  // If still has unresolved placeholders (except {user_input} which is handled separately), return null
  // Remove {user_input} temporarily to check for other unresolved placeholders
  const withoutUserInput = resolved.replace(/\{user_input\}/g, '')
  if (withoutUserInput.includes('{')) {
    return null
  }

  return resolved.trim()
}

/**
 * Get display label with row data
 *
 * @param action - Quick action
 * @param tableState - Current table state
 * @returns Display label with context
 */
export function getQuickActionLabel(
  action: QuickAction,
  tableState: TableState | null
): string {
  if (!action.needsRow || !tableState) {
    return action.label
  }

  const { headers, rows, selectedRowIndex } = tableState

  if (selectedRowIndex < 0 || selectedRowIndex >= rows.length) {
    return action.label
  }

  const selectedRow = rows[selectedRowIndex]

  // Try to find a meaningful identifier from the row
  // Priority: name/slug/email -> first column -> generic
  const identifierHeaders = ['name', 'slug', 'email', 'company']
  let identifier = ''

  for (const headerName of identifierHeaders) {
    const headerIndex = headers.findIndex(h =>
      h.toLowerCase().replace(/\s+/g, '') === headerName
    )
    if (headerIndex >= 0 && selectedRow[headerIndex]) {
      identifier = selectedRow[headerIndex]
      break
    }
  }

  // Fallback to first column
  if (!identifier && selectedRow.length > 0) {
    identifier = selectedRow[0]
  }

  if (!identifier) {
    return action.label
  }

  // Append identifier to label
  return `${action.label}: ${identifier}`
}
