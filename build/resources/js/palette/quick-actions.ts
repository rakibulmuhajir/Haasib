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
          command: 'user invite',
          needsRow: true,
          prompt: 'Enter user email (optionally add role, e.g., jane@x.com --role owner)',
        },
        {
          key: '4',
          label: 'Create new company',
          command: 'company create',
          needsRow: false,
          prompt: 'Enter company name and currency (e.g., Acme Inc USD)',
        },
        {
          key: '0',
          label: 'Delete company',
          command: 'company delete {slug}',
          needsRow: true,
        },
      ]

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
          command: 'user assign-role {email}',
          needsRow: true,
          prompt: 'Enter role name',
        },
        {
          key: '3',
          label: 'Deactivate user',
          command: 'user deactivate {email}',
          needsRow: true,
        },
        {
          key: '4',
          label: 'Invite new user',
          command: 'user invite',
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
          command: 'role assign',
          needsRow: true,
          prompt: 'Enter permission name',
        },
        {
          key: '3',
          label: 'Revoke permission',
          command: 'role revoke',
          needsRow: true,
          prompt: 'Enter permission name',
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

  // If still has unresolved placeholders, return null
  if (resolved.includes('{')) {
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
