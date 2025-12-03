export type ArgDefinition = {
  name: string
  type: 'string' | 'number' | 'date' | 'currency' | 'reference'
  required: boolean
  hint?: string
}

export type FlagDefinition = {
  name: string
  short?: string | null
  type: 'string' | 'number' | 'date' | 'currency' | 'enum' | 'boolean'
  required?: boolean
  hint?: string
  defaultSource?: string
  values?: string[]
}

export type CommandSchema = {
  entity: string
  verb: string
  description?: string
  args: ArgDefinition[]
  flags: FlagDefinition[]
}

export type VerbSchema = {
  name: string
  aliases?: string[]
  requiresSubject?: boolean
  description?: string
  example?: string
  args: ArgDefinition[]
  flags: FlagDefinition[]
}

export type EntitySchema = {
  name: string
  shortcuts: string[]
  defaultVerb: string
  icon?: string
  verbs: VerbSchema[]
}

export const ENTITY_SCHEMAS: Record<string, EntitySchema> = {
  company: {
    name: 'company',
    shortcuts: ['co', 'comp'],
    defaultVerb: 'list',
    icon: '🏢',
    verbs: [
      {
        name: 'create',
        aliases: ['new', 'add'],
        requiresSubject: true,
        description: 'Create a new company',
        example: 'company create Acme Inc USD',
        args: [
          { name: 'name', type: 'string', required: true, hint: 'Company name' },
          { name: 'currency', type: 'currency', required: true, hint: 'Base currency (ISO)' },
        ],
        flags: [
          { name: 'industry', type: 'string', hint: 'Industry' },
          { name: 'country', type: 'string', hint: 'Country code' },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all', 'show'],
        requiresSubject: false,
        description: 'Show all companies you have access to',
        args: [],
        flags: [],
      },
      {
        name: 'view',
        aliases: ['get', 'info'],
        requiresSubject: true,
        description: 'View company details',
        example: 'company view acme-corp',
        args: [
          { name: 'slug', type: 'string', required: true, hint: 'Company slug' },
        ],
        flags: [],
      },
      {
        name: 'switch',
        aliases: ['sw', 'use', 'select'],
        requiresSubject: true,
        description: 'Switch to a different company',
        example: 'company switch acme-corp',
        args: [
          { name: 'slug', type: 'string', required: true, hint: 'Company slug' },
        ],
        flags: [],
      },
      {
        name: 'delete',
        aliases: ['del', 'rm', 'remove'],
        requiresSubject: true,
        description: 'Delete a company (cannot be undone)',
        example: 'company delete acme-corp',
        args: [
          { name: 'slug', type: 'string', required: true, hint: 'Company slug' },
        ],
        flags: [],
      },
    ],
  },

  user: {
    name: 'user',
    shortcuts: ['u', 'usr'],
    defaultVerb: 'list',
    icon: '👤',
    verbs: [
      {
        name: 'invite',
        aliases: ['add', 'new'],
        requiresSubject: true,
        description: 'Invite a new user to the company',
        example: 'user invite john@example.com',
        args: [
          { name: 'email', type: 'string', required: true, hint: 'User email' },
        ],
        flags: [
          { name: 'role', type: 'string', hint: 'Role to assign' },
          { name: 'name', type: 'string', hint: 'Full name' },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all', 'show'],
        requiresSubject: false,
        description: 'Show all users in the company',
        args: [],
        flags: [],
      },
      {
        name: 'view',
        aliases: ['get', 'info'],
        requiresSubject: true,
        description: 'View user details',
        example: 'user view john@example.com',
        args: [
          { name: 'email', type: 'string', required: true, hint: 'User email' },
        ],
        flags: [],
      },
      {
        name: 'assign-role',
        aliases: ['assign', 'grant'],
        requiresSubject: true,
        description: 'Assign a role to a user',
        example: 'user assign-role john@example.com admin',
        args: [
          { name: 'email', type: 'string', required: true, hint: 'User email' },
          { name: 'role', type: 'string', required: true, hint: 'Role name' },
        ],
        flags: [],
      },
      {
        name: 'remove-role',
        aliases: ['revoke', 'unassign'],
        requiresSubject: true,
        description: 'Remove a role from a user',
        example: 'user remove-role john@example.com admin',
        args: [
          { name: 'email', type: 'string', required: true, hint: 'User email' },
          { name: 'role', type: 'string', required: true, hint: 'Role name' },
        ],
        flags: [],
      },
      {
        name: 'deactivate',
        aliases: ['disable', 'suspend'],
        requiresSubject: true,
        description: 'Deactivate a user account',
        example: 'user deactivate john@example.com',
        args: [
          { name: 'email', type: 'string', required: true, hint: 'User email' },
        ],
        flags: [],
      },
      {
        name: 'activate',
        aliases: ['enable', 'restore'],
        requiresSubject: true,
        description: 'Activate a deactivated user',
        example: 'user activate john@example.com',
        args: [
          { name: 'email', type: 'string', required: true, hint: 'User email' },
        ],
        flags: [],
      },
      {
        name: 'delete',
        aliases: ['del', 'rm', 'remove'],
        requiresSubject: true,
        description: 'Delete a user (cannot be undone)',
        example: 'user delete john@example.com',
        args: [
          { name: 'email', type: 'string', required: true, hint: 'User email' },
        ],
        flags: [],
      },
    ],
  },

  role: {
    name: 'role',
    shortcuts: ['r'],
    defaultVerb: 'list',
    icon: '🔑',
    verbs: [
      {
        name: 'list',
        aliases: ['ls', 'all', 'show'],
        requiresSubject: false,
        description: 'Show all available roles',
        args: [],
        flags: [],
      },
      {
        name: 'view',
        aliases: ['get', 'info'],
        requiresSubject: true,
        description: 'View role details and permissions',
        example: 'role view admin',
        args: [
          { name: 'name', type: 'string', required: true, hint: 'Role name' },
        ],
        flags: [],
      },
      {
        name: 'assign',
        aliases: ['grant', 'give'],
        requiresSubject: true,
        description: 'Assign a permission to a role',
        example: 'role assign users:create admin',
        args: [
          { name: 'permission', type: 'string', required: true, hint: 'Permission name' },
          { name: 'role', type: 'string', required: true, hint: 'Role name' },
        ],
        flags: [],
      },
      {
        name: 'revoke',
        aliases: ['remove', 'take'],
        requiresSubject: true,
        description: 'Revoke a permission from a role',
        example: 'role revoke users:create admin',
        args: [
          { name: 'permission', type: 'string', required: true, hint: 'Permission name' },
          { name: 'role', type: 'string', required: true, hint: 'Role name' },
        ],
        flags: [],
      },
    ],
  },

  customer: {
    name: 'customer',
    shortcuts: ['cust', 'c'],
    defaultVerb: 'list',
    icon: '👥',
    verbs: [
      {
        name: 'create',
        aliases: ['new', 'add'],
        requiresSubject: true,
        description: 'Create a new customer',
        example: 'customer create "Acme Corp" --email=team@acme.com --currency=USD',
        args: [
          { name: 'name', type: 'string', required: true, hint: 'Customer name' },
          { name: 'email', type: 'string', required: false, hint: 'Email' },
          { name: 'currency', type: 'currency', required: false, hint: 'ISO currency' },
        ],
        flags: [
          { name: 'phone', type: 'string', hint: 'Phone' },
          { name: 'payment_terms', short: 't', type: 'number', hint: 'Payment terms (days)' },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all'],
        requiresSubject: false,
        description: 'List all customers',
        args: [],
        flags: [
          { name: 'search', type: 'string', hint: 'Search' },
          { name: 'inactive', type: 'boolean', hint: 'Include inactive' },
          { name: 'limit', type: 'number', hint: 'Limit results' },
        ],
      },
      {
        name: 'view',
        aliases: ['get', 'show', 'info'],
        requiresSubject: true,
        description: 'View customer details and stats',
        example: 'customer view "Acme Corp"',
        args: [
          { name: 'id', type: 'string', required: true, hint: 'Customer ID or name' },
        ],
        flags: [],
      },
      {
        name: 'update',
        aliases: ['edit', 'modify'],
        requiresSubject: true,
        description: 'Update customer information',
        example: 'customer update "Acme Corp" --email=new@acme.com',
        args: [
          { name: 'id', type: 'string', required: true, hint: 'Customer ID or name' },
        ],
        flags: [
          { name: 'email', type: 'string', hint: 'Email' },
          { name: 'phone', type: 'string', hint: 'Phone' },
          { name: 'payment_terms', type: 'number', hint: 'Payment terms' },
          { name: 'currency', type: 'currency', hint: 'Currency' },
        ],
      },
      {
        name: 'delete',
        aliases: ['del', 'rm', 'remove'],
        requiresSubject: true,
        description: 'Deactivate a customer',
        example: 'customer delete "Acme Corp"',
        args: [
          { name: 'id', type: 'string', required: true, hint: 'Customer ID or name' },
        ],
        flags: [],
      },
      {
        name: 'restore',
        aliases: ['undelete', 'reactivate'],
        requiresSubject: true,
        description: 'Restore a deactivated customer',
        example: 'customer restore "Acme Corp"',
        args: [
          { name: 'id', type: 'string', required: true, hint: 'Customer ID or name' },
        ],
        flags: [],
      },
    ],
  },

  invoice: {
    name: 'invoice',
    shortcuts: ['inv', 'i'],
    defaultVerb: 'list',
    icon: '📄',
    verbs: [
      {
        name: 'create',
        aliases: ['new', 'add'],
        requiresSubject: true,
        description: 'Create a new invoice',
        example: 'invoice create "Acme Corp" --amount=1200 --currency=USD',
        args: [
          { name: 'customer', type: 'reference', required: true, hint: 'Customer name or ID' },
          { name: 'amount', type: 'currency', required: true, hint: 'Invoice amount' },
          { name: 'currency', type: 'currency', required: true, hint: 'ISO currency (e.g., USD)' },
        ],
        flags: [
          { name: 'due', short: 'd', type: 'date', hint: 'Due date (YYYY-MM-DD or +30d)', defaultSource: 'customer.payment_terms' },
          { name: 'reference', short: 'r', type: 'string', hint: 'PO number or reference' },
          { name: 'description', type: 'string', hint: 'Description / memo' },
          { name: 'draft', type: 'boolean', hint: 'Save as draft' },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all'],
        requiresSubject: false,
        description: 'List invoices (filter by status, customer)',
        args: [],
        flags: [
          { name: 'status', short: 's', type: 'enum', values: ['draft', 'sent', 'posted', 'overdue', 'paid', 'cancelled'], hint: 'Status filter' },
          { name: 'customer', short: 'c', type: 'string', hint: 'Customer filter' },
          { name: 'unpaid', type: 'boolean', hint: 'Unpaid only' },
          { name: 'overdue', type: 'boolean', hint: 'Overdue only' },
          { name: 'from', type: 'date', hint: 'Start date' },
          { name: 'to', type: 'date', hint: 'End date' },
        ],
      },
      {
        name: 'view',
        aliases: ['get', 'show', 'info'],
        requiresSubject: true,
        description: 'View invoice details and payments',
        example: 'invoice view INV-1001',
        args: [
          { name: 'id', type: 'string', required: true, hint: 'Invoice ID or number' },
        ],
        flags: [],
      },
      {
        name: 'send',
        aliases: ['email', 'deliver'],
        requiresSubject: true,
        description: 'Mark as sent / email to customer',
        example: 'invoice send INV-1001',
        args: [
          { name: 'id', type: 'string', required: true, hint: 'Invoice ID or number' },
        ],
        flags: [
          { name: 'email', type: 'boolean', hint: 'Send via email' },
          { name: 'to', type: 'string', hint: 'Recipient email' },
        ],
      },
      {
        name: 'void',
        aliases: ['cancel'],
        requiresSubject: true,
        description: 'Void an invoice',
        example: 'invoice void INV-1001',
        args: [
          { name: 'id', type: 'string', required: true, hint: 'Invoice ID or number' },
        ],
        flags: [
          { name: 'reason', type: 'string', hint: 'Reason' },
        ],
      },
      {
        name: 'duplicate',
        aliases: ['dup', 'copy', 'clone'],
        requiresSubject: true,
        description: 'Create a copy of an invoice',
        example: 'invoice duplicate INV-1001',
        args: [
          { name: 'id', type: 'string', required: true, hint: 'Invoice ID or number' },
        ],
        flags: [
          { name: 'customer', short: 'c', type: 'string', hint: 'Override customer' },
          { name: 'draft', type: 'boolean', hint: 'Save duplicate as draft' },
        ],
      },
    ],
  },

  payment: {
    name: 'payment',
    shortcuts: ['pay', 'p'],
    defaultVerb: 'list',
    icon: '💰',
    verbs: [
      {
        name: 'create',
        aliases: ['new', 'add', 'record'],
        requiresSubject: true,
        description: 'Record a payment on an invoice',
        example: 'payment create INV-1001 1200',
        args: [
          { name: 'invoice', type: 'reference', required: true, hint: 'Invoice number or ID' },
          { name: 'amount', type: 'currency', required: true, hint: 'Payment amount' },
        ],
        flags: [
          { name: 'method', short: 'm', type: 'enum', values: ['cash', 'check', 'card', 'bank_transfer', 'other'], defaultSource: 'user.pref.method' },
          { name: 'reference', short: 'r', type: 'string', hint: 'Reference or note' },
          { name: 'date', short: 'd', type: 'date', hint: 'Payment date', defaultSource: 'system.today' },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all'],
        requiresSubject: false,
        description: 'List payment history',
        args: [],
        flags: [
          { name: 'invoice', type: 'string', hint: 'Invoice filter' },
          { name: 'customer', type: 'string', hint: 'Customer filter' },
          { name: 'method', type: 'enum', values: ['cash', 'check', 'card', 'bank_transfer', 'other'], hint: 'Method filter' },
          { name: 'from', type: 'date', hint: 'Start date' },
          { name: 'to', type: 'date', hint: 'End date' },
        ],
      },
      {
        name: 'void',
        aliases: ['cancel', 'reverse'],
        requiresSubject: true,
        description: 'Void/reverse a payment',
        example: 'payment void PAY-1001',
        args: [
          { name: 'id', type: 'string', required: true, hint: 'Payment ID or number' },
        ],
        flags: [
          { name: 'reason', type: 'string', hint: 'Reason' },
        ],
      },
    ],
  },

  account: {
    name: 'account',
    shortcuts: ['coa', 'acct'],
    defaultVerb: 'list',
    icon: '📒',
    verbs: [
      {
        name: 'list',
        aliases: ['ls', 'all', 'chart'],
        requiresSubject: false,
        description: 'Chart of accounts',
        args: [],
        flags: [],
      },
      {
        name: 'view',
        aliases: ['get', 'show'],
        requiresSubject: true,
        description: 'View account details',
        example: 'account view 1200',
        args: [
          { name: 'id', type: 'string', required: true, hint: 'Account code or ID' },
        ],
        flags: [],
      },
      {
        name: 'create',
        aliases: ['new', 'add'],
        requiresSubject: true,
        description: 'Create a new account',
        example: 'account create 1200 "Cash" --type=asset',
        args: [
          { name: 'code', type: 'string', required: true, hint: 'Account code' },
          { name: 'name', type: 'string', required: true, hint: 'Account name' },
          { name: 'type', type: 'enum', required: true, values: ['asset', 'liability', 'equity', 'income', 'expense'], hint: 'Account type' },
        ],
        flags: [],
      },
    ],
  },

  journal: {
    name: 'journal',
    shortcuts: ['jr', 'jnl'],
    defaultVerb: 'list',
    icon: '📔',
    verbs: [
      {
        name: 'create',
        aliases: ['new', 'add'],
        requiresSubject: true,
        description: 'Create a journal entry',
        example: 'journal create --reference=JV-1001',
        args: [],
        flags: [
          { name: 'date', type: 'date', hint: 'Journal date' },
          { name: 'reference', type: 'string', hint: 'Reference number' },
        ],
      },
      {
        name: 'list',
        aliases: ['ls', 'all'],
        requiresSubject: false,
        description: 'List journal entries',
        args: [],
        flags: [
          { name: 'from', type: 'date', hint: 'Start date' },
          { name: 'to', type: 'date', hint: 'End date' },
        ],
      },
      {
        name: 'view',
        aliases: ['get', 'show'],
        requiresSubject: true,
        description: 'View journal entry',
        example: 'journal view JNL-1001',
        args: [
          { name: 'id', type: 'string', required: true, hint: 'Journal ID' },
        ],
        flags: [],
      },
    ],
  },
}

const COMMAND_SCHEMAS: Record<string, CommandSchema> = {}

Object.values(ENTITY_SCHEMAS).forEach((entity) => {
  entity.verbs.forEach((verb) => {
    COMMAND_SCHEMAS[`${entity.name}.${verb.name}`] = {
      entity: entity.name,
      verb: verb.name,
      description: verb.description,
      args: verb.args,
      flags: verb.flags,
    }
  })
})

export function getSchema(entity?: string | null, verb?: string | null): CommandSchema | null {
  if (!entity || !verb) return null
  return COMMAND_SCHEMAS[`${entity}.${verb}`] ?? null
}
