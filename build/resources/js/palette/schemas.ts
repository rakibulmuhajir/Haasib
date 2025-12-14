/**
 * Command Palette Schemas
 *
 * Field Input Types:
 * - text:   User types free text (name, description, reference)
 * - number: User types numeric value (amount, limit)
 * - email:  User types email with validation hint
 * - phone:  User types phone number
 * - date:   User types date with format hint (dd/mm/yyyy)
 * - select: User picks from static enum list (no DB)
 * - lookup: User picks from small DB list, shown immediately (currency, country, industry)
 * - search: User types to search large DB (customer, invoice, company) - requires ≥2 chars
 * - image:  User picks file (logo, avatar)
 */

// ============================================================================
// Types
// ============================================================================

export type InputType = 'text' | 'number' | 'email' | 'phone' | 'date' | 'select' | 'lookup' | 'search' | 'image'

/** Entity types that use lookup (small cached lists, shown immediately) */
export const LOOKUP_ENTITIES = ['currency', 'country', 'industry', 'role'] as const

/** Entity types that use search (large lists, type to search) */
export const SEARCH_ENTITIES = ['customer', 'company', 'invoice', 'user', 'vendor'] as const

export interface FieldDef {
  name: string
  required?: boolean
  inputType: InputType
  hint?: string
  placeholder?: string
  /** Static options for 'select' type */
  enum?: string[]
  /** Entity type for 'search' type (e.g., 'customer', 'invoice') */
  searchEntity?: string
  /** Accepted file types for 'image' type */
  accept?: string
}

export interface CommandSchema {
  entity: string
  verb: string
  args: FieldDef[]
  flags: FieldDef[]
}

// ============================================================================
// Reusable Field Definitions
// ============================================================================

const FIELDS = {
  // Text fields
  name: (hint: string): FieldDef => ({
    name: 'name',
    required: true,
    inputType: 'text',
    hint,
  }),

  description: (): FieldDef => ({
    name: 'description',
    inputType: 'text',
    hint: 'Description',
  }),

  reference: (): FieldDef => ({
    name: 'reference',
    inputType: 'text',
    hint: 'External reference',
  }),

  notes: (): FieldDef => ({
    name: 'notes',
    inputType: 'text',
    hint: 'Notes',
  }),

  message: (): FieldDef => ({
    name: 'message',
    inputType: 'text',
    hint: 'Message',
  }),

  search: (): FieldDef => ({
    name: 'search',
    inputType: 'text',
    hint: 'Search term',
  }),

  tax_id: (): FieldDef => ({
    name: 'tax_id',
    inputType: 'text',
    hint: 'Tax ID (e.g., VAT number)',
  }),

  // Number fields
  amount: (hint = 'Amount'): FieldDef => ({
    name: 'amount',
    required: true,
    inputType: 'number',
    hint,
    placeholder: '0.00',
  }),

  limit: (): FieldDef => ({
    name: 'limit',
    inputType: 'number',
    hint: 'Max results',
    placeholder: '50',
  }),

  // Email field
  email: (required = false): FieldDef => ({
    name: 'email',
    required,
    inputType: 'email',
    hint: 'Email address',
    placeholder: 'name@example.com',
  }),

  // Phone field
  phone: (): FieldDef => ({
    name: 'phone',
    inputType: 'phone',
    hint: 'Phone number',
    placeholder: '+1 234 567 8900',
  }),

  // Date fields
  date: (name: string, hint: string): FieldDef => ({
    name,
    inputType: 'date',
    hint,
    placeholder: 'dd/mm/yyyy',
  }),

  due_date: (): FieldDef => ({
    name: 'due',
    inputType: 'date',
    hint: 'Due date',
    placeholder: 'dd/mm/yyyy or +30d',
  }),

  from_date: (): FieldDef => ({
    name: 'from',
    inputType: 'date',
    hint: 'From date',
    placeholder: 'dd/mm/yyyy',
  }),

  to_date: (): FieldDef => ({
    name: 'to',
    inputType: 'date',
    hint: 'To date',
    placeholder: 'dd/mm/yyyy',
  }),

  // Select fields (static enum)
  status: (options: string[]): FieldDef => ({
    name: 'status',
    inputType: 'select',
    hint: 'Status',
    enum: options,
  }),

  payment_terms: (): FieldDef => ({
    name: 'payment_terms',
    inputType: 'select',
    hint: 'Payment terms (days)',
    enum: ['7', '14', '30', '45', '60', '90'],
  }),

  customer_type: (): FieldDef => ({
    name: 'type',
    inputType: 'select',
    hint: 'Customer type',
    enum: ['business', 'individual'],
  }),

  payment_method: (): FieldDef => ({
    name: 'method',
    inputType: 'select',
    hint: 'Payment method',
    enum: ['cash', 'bank_transfer', 'card', 'cheque', 'other'],
  }),

  invoice_status: (): FieldDef => ({
    name: 'status',
    inputType: 'select',
    hint: 'Invoice status',
    enum: ['draft', 'sent', 'paid', 'overdue', 'cancelled'],
  }),

  // Lookup fields (small DB lists, shown immediately)
  currency: (required = false): FieldDef => ({
    name: 'currency',
    required,
    inputType: 'lookup',
    hint: 'Currency',
    searchEntity: 'currency',
  }),

  country: (): FieldDef => ({
    name: 'country',
    inputType: 'lookup',
    hint: 'Country',
    searchEntity: 'country',
  }),

  industry: (): FieldDef => ({
    name: 'industry',
    inputType: 'lookup',
    hint: 'Industry',
    searchEntity: 'industry',
  }),

  role: (): FieldDef => ({
    name: 'role',
    inputType: 'lookup',
    hint: 'Role',
    searchEntity: 'role',
  }),

  // Search fields (large DB, type to search)
  customer: (required = false): FieldDef => ({
    name: 'customer',
    required,
    inputType: 'search',
    hint: 'Customer',
    searchEntity: 'customer',
  }),

  invoice: (required = false): FieldDef => ({
    name: 'invoice',
    required,
    inputType: 'search',
    hint: 'Invoice',
    searchEntity: 'invoice',
  }),

  company: (required = false): FieldDef => ({
    name: 'company',
    required,
    inputType: 'search',
    hint: 'Company',
    searchEntity: 'company',
  }),

  user: (required = false): FieldDef => ({
    name: 'user',
    required,
    inputType: 'search',
    hint: 'User',
    searchEntity: 'user',
  }),

  // Image fields
  logo: (): FieldDef => ({
    name: 'logo',
    inputType: 'image',
    hint: 'Company logo',
    accept: 'image/png,image/jpeg,image/svg+xml',
  }),

  avatar: (): FieldDef => ({
    name: 'avatar',
    inputType: 'image',
    hint: 'Profile picture',
    accept: 'image/png,image/jpeg',
  }),

  // ID fields (for view/edit commands)
  id: (entity: string): FieldDef => ({
    name: 'id',
    required: true,
    inputType: 'search',
    hint: `${entity} ID`,
    searchEntity: entity,
  }),

  slug: (entity: string): FieldDef => ({
    name: 'slug',
    required: true,
    inputType: 'search',
    hint: `${entity} slug`,
    searchEntity: entity,
  }),
} as const

// ============================================================================
// Schema Definitions
// ============================================================================

const SCHEMAS: Record<string, CommandSchema> = {
  // --------------------------------------------------------------------------
  // COMPANY
  // --------------------------------------------------------------------------
  'company.create': {
    entity: 'company',
    verb: 'create',
    args: [
      FIELDS.name('Company name'),
      FIELDS.currency(true),
    ],
    flags: [
      FIELDS.industry(),
      FIELDS.country(),
      FIELDS.tax_id(),
      FIELDS.logo(),
    ],
  },
  'company.list': {
    entity: 'company',
    verb: 'list',
    args: [],
    flags: [
      FIELDS.status(['active', 'inactive', 'all']),
      FIELDS.limit(),
    ],
  },
  'company.view': {
    entity: 'company',
    verb: 'view',
    args: [FIELDS.id('company')],
    flags: [],
  },
  'company.switch': {
    entity: 'company',
    verb: 'switch',
    args: [FIELDS.slug('company')],
    flags: [],
  },
  'company.delete': {
    entity: 'company',
    verb: 'delete',
    args: [FIELDS.slug('company')],
    flags: [],
  },

  // --------------------------------------------------------------------------
  // CUSTOMER
  // --------------------------------------------------------------------------
  'customer.create': {
    entity: 'customer',
    verb: 'create',
    args: [
      FIELDS.name('Customer name'),
    ],
    flags: [
      FIELDS.email(),
      FIELDS.phone(),
      FIELDS.currency(),
      FIELDS.payment_terms(),
      FIELDS.customer_type(),
      FIELDS.country(),
    ],
  },
  'customer.list': {
    entity: 'customer',
    verb: 'list',
    args: [],
    flags: [
      FIELDS.search(),
      FIELDS.status(['active', 'inactive', 'all']),
      FIELDS.limit(),
    ],
  },
  'customer.view': {
    entity: 'customer',
    verb: 'view',
    args: [FIELDS.id('customer')],
    flags: [],
  },
  'customer.delete': {
    entity: 'customer',
    verb: 'delete',
    args: [FIELDS.id('customer')],
    flags: [],
  },

  // --------------------------------------------------------------------------
  // INVOICE
  // --------------------------------------------------------------------------
  'invoice.create': {
    entity: 'invoice',
    verb: 'create',
    args: [
      FIELDS.customer(true),
      {
        name: 'line_items',
        required: true,
        inputType: 'text',
        hint: 'Line items (JSON array or format: "Description:Quantity:Price")',
        placeholder: '[{"description":"Service","quantity":1,"unit_price":100}]',
      },
    ],
    flags: [
      FIELDS.currency(),
      FIELDS.due_date(),
      {
        name: 'description',
        inputType: 'text',
        hint: 'Invoice description',
      },
      FIELDS.reference(),
    ],
  },
  'invoice.list': {
    entity: 'invoice',
    verb: 'list',
    args: [],
    flags: [
      FIELDS.customer(),
      FIELDS.invoice_status(),
      FIELDS.from_date(),
      FIELDS.to_date(),
      FIELDS.limit(),
    ],
  },
  'invoice.view': {
    entity: 'invoice',
    verb: 'view',
    args: [FIELDS.id('invoice')],
    flags: [],
  },
  'invoice.send': {
    entity: 'invoice',
    verb: 'send',
    args: [FIELDS.id('invoice')],
    flags: [
      FIELDS.email(),
      FIELDS.message(),
    ],
  },
  'invoice.void': {
    entity: 'invoice',
    verb: 'void',
    args: [FIELDS.id('invoice')],
    flags: [],
  },
  'invoice.duplicate': {
    entity: 'invoice',
    verb: 'duplicate',
    args: [FIELDS.id('invoice')],
    flags: [],
  },

  // --------------------------------------------------------------------------
  // PAYMENT
  // --------------------------------------------------------------------------
  'payment.create': {
    entity: 'payment',
    verb: 'create',
    args: [
      FIELDS.invoice(true),
      FIELDS.amount('Payment amount'),
    ],
    flags: [
      FIELDS.payment_method(),
      FIELDS.reference(),
      FIELDS.date('date', 'Payment date'),
      FIELDS.notes(),
    ],
  },
  'payment.list': {
    entity: 'payment',
    verb: 'list',
    args: [],
    flags: [
      FIELDS.invoice(),
      FIELDS.customer(),
      FIELDS.from_date(),
      FIELDS.to_date(),
      FIELDS.limit(),
    ],
  },
  'payment.view': {
    entity: 'payment',
    verb: 'view',
    args: [FIELDS.id('payment')],
    flags: [],
  },
  'payment.void': {
    entity: 'payment',
    verb: 'void',
    args: [FIELDS.id('payment')],
    flags: [],
  },

  // --------------------------------------------------------------------------
  // USER
  // --------------------------------------------------------------------------
  'user.create': {
    entity: 'user',
    verb: 'create',
    args: [
      FIELDS.name('User name'),
      FIELDS.email(true),
    ],
    flags: [
      FIELDS.role(),
      FIELDS.company(),
      FIELDS.avatar(),
    ],
  },
  'user.list': {
    entity: 'user',
    verb: 'list',
    args: [],
    flags: [
      FIELDS.company(),
      FIELDS.role(),
      FIELDS.status(['active', 'inactive', 'all']),
      FIELDS.limit(),
    ],
  },
  'user.view': {
    entity: 'user',
    verb: 'view',
    args: [FIELDS.id('user')],
    flags: [],
  },

  // --------------------------------------------------------------------------
  // ROLE
  // --------------------------------------------------------------------------
  'role.create': {
    entity: 'role',
    verb: 'create',
    args: [
      FIELDS.name('Role name'),
    ],
    flags: [
      FIELDS.description(),
      {
        name: 'permissions',
        inputType: 'text',
        hint: 'Permissions (comma-separated)',
      },
    ],
  },
  'role.list': {
    entity: 'role',
    verb: 'list',
    args: [],
    flags: [
      FIELDS.search(),
      FIELDS.limit(),
    ],
  },
  'role.view': {
    entity: 'role',
    verb: 'view',
    args: [FIELDS.id('role')],
    flags: [],
  },
}

// ============================================================================
// Exports
// ============================================================================

export function getSchema(entity: string, verb: string): CommandSchema | null {
  const key = `${entity}.${verb}`
  return SCHEMAS[key] || null
}

export function getEntities(): string[] {
  const entities = new Set<string>()
  Object.keys(SCHEMAS).forEach(key => {
    entities.add(key.split('.')[0])
  })
  return Array.from(entities).sort()
}

export function getVerbsForEntity(entity: string): string[] {
  const verbs: string[] = []
  Object.keys(SCHEMAS).forEach(key => {
    const [e, v] = key.split('.')
    if (e === entity) verbs.push(v)
  })
  return verbs
}

export function getAllSchemas(): CommandSchema[] {
  return Object.values(SCHEMAS)
}

// Helper to check if a field needs dropdown/sidebar
export function fieldHasDropdown(entity: string, verb: string, fieldName: string): boolean {
  const schema = getSchema(entity, verb)
  if (!schema) return false

  const allFields = [...schema.args, ...schema.flags]
  const field = allFields.find(f => f.name === fieldName)
  if (!field) return false

  return field.inputType === 'select' || field.inputType === 'lookup' || field.inputType === 'search'
}

// Helper to check if field shows options immediately (lookup/select) vs requires typing (search)
export function fieldShowsImmediately(field: FieldDef): boolean {
  return field.inputType === 'select' || field.inputType === 'lookup'
}

// Helper to check if field requires typing before showing options
export function fieldRequiresTyping(field: FieldDef): boolean {
  return field.inputType === 'search'
}

// Helper to get field definition
export function getFieldDef(entity: string, verb: string, fieldName: string): FieldDef | null {
  const schema = getSchema(entity, verb)
  if (!schema) return null

  const allFields = [...schema.args, ...schema.flags]
  return allFields.find(f => f.name === fieldName) || null
}

// Helper to check if field is image type
export function isImageField(field: FieldDef): boolean {
  return field.inputType === 'image'
}

// Helper to check if field is date type
export function isDateField(field: FieldDef): boolean {
  return field.inputType === 'date'
}

// Helper to get placeholder for field
export function getFieldPlaceholder(field: FieldDef): string {
  if (field.placeholder) return field.placeholder

  switch (field.inputType) {
    case 'date':
      return 'dd/mm/yyyy'
    case 'email':
      return 'name@example.com'
    case 'number':
      return '0'
    case 'image':
      return 'Click to select file'
    default:
      return ''
  }
}
