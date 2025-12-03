import type { EntityDefinition } from '@/types/palette'

/**
 * Entity icons
 */
export const ENTITY_ICONS: Record<string, string> = {
  company: '🏢',
  user: '👤',
  role: '🔑',
  customer: '👥',
  invoice: '📄',
  payment: '💰',
}

/**
 * Command descriptions
 */
export const COMMAND_DESCRIPTIONS: Record<string, string> = {
  'company.create': 'Create a new company',
  'company.list': 'Show all companies you have access to',
  'company.view': 'View company details',
  'company.switch': 'Switch to a different company',
  'company.delete': 'Delete a company (cannot be undone)',

  'user.invite': 'Invite a new user to the company',
  'user.list': 'Show all users in the company',
  'user.view': 'View user details',
  'user.assign-role': 'Assign a role to a user',
  'user.remove-role': 'Remove a role from a user',
  'user.deactivate': 'Deactivate a user account',
  'user.activate': 'Activate a deactivated user',
  'user.delete': 'Delete a user (cannot be undone)',

  'role.list': 'Show all available roles',
  'role.view': 'View role details and permissions',
  'role.assign': 'Assign a permission to a role',
  'role.revoke': 'Revoke a permission from a role',

  // Customer
  'customer.create': 'Create a new customer',
  'customer.list': 'List all customers',
  'customer.view': 'View customer details and stats',
  'customer.update': 'Update customer information',
  'customer.delete': 'Deactivate a customer',
  'customer.restore': 'Restore a deactivated customer',

  // Invoice
  'invoice.create': 'Create a new invoice',
  'invoice.list': 'List invoices (filter by status, customer)',
  'invoice.view': 'View invoice details and payments',
  'invoice.send': 'Mark as sent / email to customer',
  'invoice.void': 'Void an invoice',
  'invoice.duplicate': 'Create a copy of an invoice',

  // Payment
  'payment.create': 'Record a payment on an invoice',
  'payment.list': 'List payment history',
  'payment.void': 'Void/reverse a payment',

  'help': 'Show available commands',
  'clear': 'Clear output history',
}

/**
 * Command grammar definitions
 *
 * Each entity defines:
 * - name: canonical name
 * - shortcuts: short aliases (e.g., "co" for "company")
 * - defaultVerb: verb used when only entity is specified
 * - verbs: available operations with their flags
 */
export const GRAMMAR: Record<string, EntityDefinition> = {
  company: {
    name: 'company',
    shortcuts: ['co', 'comp'],
    defaultVerb: 'list',
    verbs: [
      {
        name: 'create',
        aliases: ['new', 'add'],
        requiresSubject: true,
        flags: [
          { name: 'name', type: 'string', required: true },
          { name: 'currency', type: 'string', required: true },
          { name: 'industry', type: 'string', required: false },
          { name: 'country', type: 'string', required: false },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all', 'show'],
        requiresSubject: false,
        flags: [],
      },
      {
        name: 'view',
        aliases: ['get', 'info'],
        requiresSubject: true,
        flags: [
          { name: 'slug', type: 'string', required: true },
        ],
      },
      {
        name: 'switch',
        aliases: ['sw', 'use', 'select'],
        requiresSubject: true,
        flags: [
          { name: 'slug', type: 'string', required: true },
        ],
      },
      {
        name: 'delete',
        aliases: ['del', 'rm', 'remove'],
        requiresSubject: true,
        flags: [
          { name: 'slug', type: 'string', required: true },
        ],
      },
    ],
  },

  user: {
    name: 'user',
    shortcuts: ['u', 'usr'],
    defaultVerb: 'list',
    verbs: [
      {
        name: 'invite',
        aliases: ['add', 'new'],
        requiresSubject: true,
        flags: [
          { name: 'email', type: 'string', required: true },
          { name: 'role', type: 'string', required: false, default: 'member' },
          { name: 'name', type: 'string', required: false },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all', 'show'],
        requiresSubject: false,
        flags: [],
      },
      {
        name: 'view',
        aliases: ['get', 'info'],
        requiresSubject: true,
        flags: [
          { name: 'email', type: 'string', required: true },
        ],
      },
      {
        name: 'assign-role',
        aliases: ['assign', 'grant'],
        requiresSubject: true,
        flags: [
          { name: 'email', type: 'string', required: true },
          { name: 'role', type: 'string', required: true },
        ],
      },
      {
        name: 'remove-role',
        aliases: ['revoke', 'unassign'],
        requiresSubject: true,
        flags: [
          { name: 'email', type: 'string', required: true },
          { name: 'role', type: 'string', required: true },
        ],
      },
      {
        name: 'deactivate',
        aliases: ['disable', 'suspend'],
        requiresSubject: true,
        flags: [
          { name: 'email', type: 'string', required: true },
        ],
      },
      {
        name: 'activate',
        aliases: ['enable', 'restore'],
        requiresSubject: true,
        flags: [
          { name: 'email', type: 'string', required: true },
        ],
      },
      {
        name: 'delete',
        aliases: ['del', 'rm', 'remove'],
        requiresSubject: true,
        flags: [
          { name: 'email', type: 'string', required: true },
        ],
      },
    ],
  },

  role: {
    name: 'role',
    shortcuts: ['r'],
    defaultVerb: 'list',
    verbs: [
      {
        name: 'list',
        aliases: ['ls', 'all', 'show'],
        requiresSubject: false,
        flags: [],
      },
      {
        name: 'view',
        aliases: ['get', 'info'],
        requiresSubject: true,
        flags: [
          { name: 'name', type: 'string', required: true },
        ],
      },
      {
        name: 'assign',
        aliases: ['grant', 'give'],
        requiresSubject: true,
        flags: [
          { name: 'permission', type: 'string', required: true },
          { name: 'role', type: 'string', required: true },
        ],
      },
      {
        name: 'revoke',
        aliases: ['remove', 'take'],
        requiresSubject: true,
        flags: [
          { name: 'permission', type: 'string', required: true },
          { name: 'role', type: 'string', required: true },
        ],
      },
    ],
  },

  customer: {
    name: 'customer',
    shortcuts: ['cust', 'c'],
    defaultVerb: 'list',
    verbs: [
      {
        name: 'create',
        aliases: ['new', 'add'],
        requiresSubject: true,
        flags: [
          { name: 'name', type: 'string', required: true },
          { name: 'email', type: 'string', required: false },
          { name: 'phone', type: 'string', required: false },
          { name: 'currency', type: 'string', required: false },
          { name: 'payment_terms', shorthand: 't', type: 'number', required: false },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all'],
        requiresSubject: false,
        flags: [
          { name: 'search', shorthand: 's', type: 'string', required: false },
          { name: 'inactive', type: 'boolean', required: false },
          { name: 'limit', type: 'number', required: false },
        ],
      },
      {
        name: 'view',
        aliases: ['get', 'show', 'info'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
        ],
      },
      {
        name: 'update',
        aliases: ['edit', 'modify'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
          { name: 'name', type: 'string', required: false },
          { name: 'email', type: 'string', required: false },
          { name: 'phone', type: 'string', required: false },
          { name: 'currency', type: 'string', required: false },
          { name: 'payment_terms', shorthand: 't', type: 'number', required: false },
        ],
      },
      {
        name: 'delete',
        aliases: ['del', 'rm', 'remove'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
        ],
      },
      {
        name: 'restore',
        aliases: ['undelete', 'reactivate'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
        ],
      },
    ],
  },

  invoice: {
    name: 'invoice',
    shortcuts: ['inv', 'i'],
    defaultVerb: 'list',
    verbs: [
      {
        name: 'create',
        aliases: ['new', 'add'],
        requiresSubject: true,
        flags: [
          { name: 'customer', shorthand: 'c', type: 'string', required: true },
          { name: 'amount', shorthand: 'a', type: 'number', required: true },
          { name: 'due', shorthand: 'd', type: 'string', required: false },
          { name: 'description', type: 'string', required: false },
          { name: 'reference', shorthand: 'r', type: 'string', required: false },
          { name: 'draft', type: 'boolean', required: false },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all'],
        requiresSubject: false,
        flags: [
          { name: 'status', shorthand: 's', type: 'string', required: false },
          { name: 'customer', shorthand: 'c', type: 'string', required: false },
          { name: 'unpaid', type: 'boolean', required: false },
          { name: 'overdue', type: 'boolean', required: false },
          { name: 'from', type: 'string', required: false },
          { name: 'to', type: 'string', required: false },
          { name: 'limit', type: 'number', required: false },
        ],
      },
      {
        name: 'view',
        aliases: ['get', 'show', 'info'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
        ],
      },
      {
        name: 'send',
        aliases: ['email', 'deliver'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
          { name: 'email', type: 'boolean', required: false },
          { name: 'to', type: 'string', required: false },
        ],
      },
      {
        name: 'void',
        aliases: ['cancel'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
          { name: 'reason', type: 'string', required: false },
        ],
      },
      {
        name: 'duplicate',
        aliases: ['dup', 'copy', 'clone'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
          { name: 'customer', shorthand: 'c', type: 'string', required: false },
          { name: 'draft', type: 'boolean', required: false },
        ],
      },
    ],
  },

  payment: {
    name: 'payment',
    shortcuts: ['pay', 'p'],
    defaultVerb: 'list',
    verbs: [
      {
        name: 'create',
        aliases: ['new', 'add', 'record'],
        requiresSubject: true,
        flags: [
          { name: 'invoice', shorthand: 'i', type: 'string', required: true },
          { name: 'amount', shorthand: 'a', type: 'number', required: true },
          { name: 'method', shorthand: 'm', type: 'string', required: false },
          { name: 'date', shorthand: 'd', type: 'string', required: false },
          { name: 'reference', shorthand: 'r', type: 'string', required: false },
          { name: 'notes', type: 'string', required: false },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all'],
        requiresSubject: false,
        flags: [
          { name: 'invoice', shorthand: 'i', type: 'string', required: false },
          { name: 'customer', shorthand: 'c', type: 'string', required: false },
          { name: 'method', shorthand: 'm', type: 'string', required: false },
          { name: 'from', type: 'string', required: false },
          { name: 'to', type: 'string', required: false },
          { name: 'limit', type: 'number', required: false },
        ],
      },
      {
        name: 'void',
        aliases: ['cancel', 'reverse'],
        requiresSubject: true,
        flags: [
          { name: 'id', type: 'string', required: true },
          { name: 'reason', type: 'string', required: false },
        ],
      },
    ],
  },
}

/**
 * Resolve entity shortcut to canonical name
 */
export function resolveEntityShortcut(shortcut: string): string | null {
  const normalized = shortcut.toLowerCase()
  
  for (const [entity, def] of Object.entries(GRAMMAR)) {
    if (entity === normalized || def.shortcuts.includes(normalized)) {
      return entity
    }
  }
  
  return null
}

/**
 * Resolve verb alias to canonical name
 */
export function resolveVerbAlias(entity: string, verb: string): string | null {
  const entityDef = GRAMMAR[entity]
  if (!entityDef) return null
  
  const normalized = verb.toLowerCase()
  
  for (const verbDef of entityDef.verbs) {
    if (verbDef.name === normalized || verbDef.aliases.includes(normalized)) {
      return verbDef.name
    }
  }
  
  return null
}

/**
 * Check if verb is valid for entity
 */
export function isValidVerb(entity: string, verb: string): boolean {
  return resolveVerbAlias(entity, verb) !== null
}

/**
 * Get full verb definition
 */
export function getVerbDefinition(entity: string, verb: string) {
  const entityDef = GRAMMAR[entity]
  if (!entityDef) return null
  
  const resolvedVerb = resolveVerbAlias(entity, verb)
  if (!resolvedVerb) return null
  
  return entityDef.verbs.find(v => v.name === resolvedVerb) || null
}

/**
 * Get all entities
 */
export function getEntities(): string[] {
  return Object.keys(GRAMMAR)
}

/**
 * Get all verbs for an entity
 */
export function getVerbs(entity: string): string[] {
  const entityDef = GRAMMAR[entity]
  if (!entityDef) return []
  return entityDef.verbs.map(v => v.name)
}

/**
 * Command examples for inline placeholder hints
 */
export const COMMAND_EXAMPLES: Record<string, string> = {
  'company.create': 'company create Acme Inc USD',
  'company.list': 'company list',
  'company.view': 'company view acme-corp',
  'company.switch': 'company switch acme-corp',
  'company.delete': 'company delete acme-corp',

  // Customer
  'customer.create': 'customer create "Acme Corp" --email=team@acme.com --currency=USD',
  'customer.list': 'customer list',
  'customer.view': 'customer view "Acme Corp"',
  'customer.update': 'customer update "Acme Corp" --email=new@acme.com',
  'customer.delete': 'customer delete "Acme Corp"',
  'customer.restore': 'customer restore "Acme Corp"',

  'user.invite': 'user invite john@example.com',
  'user.list': 'user list',
  'user.view': 'user view john@example.com',
  'user.assign-role': 'user assign-role john@example.com admin',
  'user.remove-role': 'user remove-role john@example.com admin',
  'user.deactivate': 'user deactivate john@example.com',
  'user.activate': 'user activate john@example.com',
  'user.delete': 'user delete john@example.com',

  // Invoice
  'invoice.create': 'invoice create "Acme Corp" --amount=1200 --currency=USD',
  'invoice.list': 'invoice list --status=sent',
  'invoice.view': 'invoice view INV-1001',
  'invoice.send': 'invoice send INV-1001',
  'invoice.void': 'invoice void INV-1001',
  'invoice.duplicate': 'invoice duplicate INV-1001',

  'role.list': 'role list',
  'role.view': 'role view admin',
  'role.assign': 'role assign users:create admin',
  'role.revoke': 'role revoke users:create admin',
}

/**
 * Get command example for inline placeholder
 */
export function getCommandExample(entity: string, verb: string): string {
  const key = `${entity}.${verb}`
  return COMMAND_EXAMPLES[key] || ''
}
