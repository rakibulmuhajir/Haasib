/**
 * Command Palette Schemas
 *
 * This file defines the schema for each entity+verb combination.
 * Adding new accounting modules (bills, vendors, reports, etc.) is as simple
 * as adding entries to the SCHEMAS object.
 *
 * Schema Structure:
 * - entity: string - The entity name (invoice, bill, vendor, etc.)
 * - verb: string - The action (create, list, view, delete, etc.)
 * - args: Array - Required positional arguments
 * - flags: Array - Optional named flags
 *
 * Arg/Flag Properties:
 * - name: string - Field name (used as key in API params)
 * - required: boolean - Whether field is required
 * - hint: string - Help text shown to user
 * - hasDropdown: boolean - Whether to show dropdown with suggestions
 * - enum: string[] - Static list of allowed values (optional)
 * - options: string - API endpoint to fetch dynamic options (optional)
 */

export interface ArgDef {
  name: string
  required: boolean
  hint?: string
  hasDropdown?: boolean
  enum?: string[]
  options?: string
  /** Entity type for DB search (e.g., 'customer', 'invoice', 'company') */
  searchEntity?: string
}

export interface FlagDef {
  name: string
  hint?: string
  hasDropdown?: boolean
  enum?: string[]
  options?: string
  /** Entity type for DB search (e.g., 'customer', 'invoice', 'company') */
  searchEntity?: string
}

export interface CommandSchema {
  entity: string
  verb: string
  args: ArgDef[]
  flags: FlagDef[]
}

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
      { name: 'name', required: true, hint: 'Company name' },
      { name: 'currency', required: true, hint: 'Base currency', hasDropdown: true },
    ],
    flags: [
      { name: 'industry', hint: 'Industry sector', hasDropdown: true },
      { name: 'country', hint: 'Country code', hasDropdown: true },
      { name: 'tax_id', hint: 'Tax identification number' },
    ],
  },
  'company.list': {
    entity: 'company',
    verb: 'list',
    args: [],
    flags: [
      { name: 'status', hint: 'Filter by status', hasDropdown: true, enum: ['active', 'inactive', 'all'] },
      { name: 'limit', hint: 'Max results' },
    ],
  },
  'company.view': {
    entity: 'company',
    verb: 'view',
    args: [
      { name: 'id', required: true, hint: 'Company ID or slug', hasDropdown: true, searchEntity: 'company' },
    ],
    flags: [],
  },
  'company.switch': {
    entity: 'company',
    verb: 'switch',
    args: [
      { name: 'slug', required: true, hint: 'Company slug', hasDropdown: true, searchEntity: 'company' },
    ],
    flags: [],
  },

  // --------------------------------------------------------------------------
  // CUSTOMER
  // --------------------------------------------------------------------------
  'customer.create': {
    entity: 'customer',
    verb: 'create',
    args: [
      { name: 'name', required: true, hint: 'Customer name' },
    ],
    flags: [
      { name: 'email', hint: 'Email address' },
      { name: 'phone', hint: 'Phone number' },
      { name: 'currency', hint: 'Preferred currency', hasDropdown: true },
      { name: 'payment_terms', hint: 'Payment terms (days)', hasDropdown: true, enum: ['7', '14', '30', '45', '60', '90'] },
      { name: 'type', hint: 'Customer type', hasDropdown: true, enum: ['business', 'individual'] },
      { name: 'country', hint: 'Country', hasDropdown: true },
    ],
  },
  'customer.list': {
    entity: 'customer',
    verb: 'list',
    args: [],
    flags: [
      { name: 'search', hint: 'Search by name' },
      { name: 'status', hint: 'Filter status', hasDropdown: true, enum: ['active', 'inactive', 'all'] },
      { name: 'limit', hint: 'Max results' },
    ],
  },
  'customer.view': {
    entity: 'customer',
    verb: 'view',
    args: [
      { name: 'id', required: true, hint: 'Customer ID or name', hasDropdown: true, searchEntity: 'customer' },
    ],
    flags: [],
  },

  // --------------------------------------------------------------------------
  // INVOICE
  // --------------------------------------------------------------------------
  'invoice.create': {
    entity: 'invoice',
    verb: 'create',
    args: [
      { name: 'customer', required: true, hint: 'Customer name', hasDropdown: true, searchEntity: 'customer' },
      { name: 'amount', required: true, hint: 'Invoice amount' },
    ],
    flags: [
      { name: 'currency', hint: 'Currency code', hasDropdown: true },
      { name: 'due', hint: 'Due date (YYYY-MM-DD or +30d)', hasDropdown: true },
      { name: 'description', hint: 'Invoice description' },
      { name: 'reference', hint: 'External reference' },
    ],
  },
  'invoice.list': {
    entity: 'invoice',
    verb: 'list',
    args: [],
    flags: [
      { name: 'customer', hint: 'Filter by customer', hasDropdown: true, searchEntity: 'customer' },
      { name: 'status', hint: 'Filter status', hasDropdown: true, enum: ['draft', 'sent', 'paid', 'overdue', 'cancelled'] },
      { name: 'from', hint: 'From date', hasDropdown: true },
      { name: 'to', hint: 'To date', hasDropdown: true },
      { name: 'limit', hint: 'Max results' },
    ],
  },
  'invoice.view': {
    entity: 'invoice',
    verb: 'view',
    args: [
      { name: 'id', required: true, hint: 'Invoice ID or number', hasDropdown: true, searchEntity: 'invoice' },
    ],
    flags: [],
  },
  'invoice.send': {
    entity: 'invoice',
    verb: 'send',
    args: [
      { name: 'id', required: true, hint: 'Invoice ID or number', hasDropdown: true, searchEntity: 'invoice' },
    ],
    flags: [
      { name: 'email', hint: 'Override recipient email' },
      { name: 'message', hint: 'Custom message' },
    ],
  },

  // --------------------------------------------------------------------------
  // PAYMENT
  // --------------------------------------------------------------------------
  'payment.create': {
    entity: 'payment',
    verb: 'create',
    args: [
      { name: 'invoice', required: true, hint: 'Invoice number', hasDropdown: true, searchEntity: 'invoice' },
      { name: 'amount', required: true, hint: 'Payment amount' },
    ],
    flags: [
      { name: 'method', hint: 'Payment method', hasDropdown: true, enum: ['cash', 'bank_transfer', 'card', 'cheque', 'other'] },
      { name: 'reference', hint: 'Payment reference' },
      { name: 'date', hint: 'Payment date', hasDropdown: true },
      { name: 'notes', hint: 'Notes' },
    ],
  },
  'payment.list': {
    entity: 'payment',
    verb: 'list',
    args: [],
    flags: [
      { name: 'invoice', hint: 'Filter by invoice', hasDropdown: true, searchEntity: 'invoice' },
      { name: 'customer', hint: 'Filter by customer', hasDropdown: true, searchEntity: 'customer' },
      { name: 'from', hint: 'From date', hasDropdown: true },
      { name: 'to', hint: 'To date', hasDropdown: true },
      { name: 'limit', hint: 'Max results' },
    ],
  },

  // --------------------------------------------------------------------------
  // VENDOR (NEW)
  // --------------------------------------------------------------------------
  'vendor.create': {
    entity: 'vendor',
    verb: 'create',
    args: [
      { name: 'name', required: true, hint: 'Vendor name' },
    ],
    flags: [
      { name: 'email', hint: 'Email address' },
      { name: 'phone', hint: 'Phone number' },
      { name: 'currency', hint: 'Preferred currency', hasDropdown: true },
      { name: 'payment_terms', hint: 'Payment terms (days)', hasDropdown: true, enum: ['7', '14', '30', '45', '60', '90'] },
      { name: 'type', hint: 'Vendor type', hasDropdown: true, enum: ['supplier', 'contractor', 'service'] },
      { name: 'country', hint: 'Country', hasDropdown: true },
      { name: 'tax_id', hint: 'Tax ID' },
    ],
  },
  'vendor.list': {
    entity: 'vendor',
    verb: 'list',
    args: [],
    flags: [
      { name: 'search', hint: 'Search by name' },
      { name: 'status', hint: 'Filter status', hasDropdown: true, enum: ['active', 'inactive', 'all'] },
      { name: 'limit', hint: 'Max results' },
    ],
  },
  'vendor.view': {
    entity: 'vendor',
    verb: 'view',
    args: [
      { name: 'id', required: true, hint: 'Vendor ID or name', hasDropdown: true, searchEntity: 'vendor' },
    ],
    flags: [],
  },

  // --------------------------------------------------------------------------
  // BILL (NEW - Vendor Invoices)
  // --------------------------------------------------------------------------
  'bill.create': {
    entity: 'bill',
    verb: 'create',
    args: [
      { name: 'vendor', required: true, hint: 'Vendor name', hasDropdown: true, searchEntity: 'vendor' },
      { name: 'amount', required: true, hint: 'Bill amount' },
    ],
    flags: [
      { name: 'currency', hint: 'Currency code', hasDropdown: true },
      { name: 'due', hint: 'Due date (YYYY-MM-DD or +30d)', hasDropdown: true },
      { name: 'bill_number', hint: 'Vendor bill number' },
      { name: 'description', hint: 'Bill description' },
      { name: 'category', hint: 'Expense category', hasDropdown: true },
    ],
  },
  'bill.list': {
    entity: 'bill',
    verb: 'list',
    args: [],
    flags: [
      { name: 'vendor', hint: 'Filter by vendor', hasDropdown: true, searchEntity: 'vendor' },
      { name: 'status', hint: 'Filter status', hasDropdown: true, enum: ['draft', 'pending', 'paid', 'overdue', 'cancelled'] },
      { name: 'category', hint: 'Filter by category', hasDropdown: true },
      { name: 'from', hint: 'From date', hasDropdown: true },
      { name: 'to', hint: 'To date', hasDropdown: true },
      { name: 'limit', hint: 'Max results' },
    ],
  },
  'bill.pay': {
    entity: 'bill',
    verb: 'pay',
    args: [
      { name: 'id', required: true, hint: 'Bill ID or number', hasDropdown: true, searchEntity: 'bill' },
    ],
    flags: [
      { name: 'amount', hint: 'Payment amount (partial payment)' },
      { name: 'method', hint: 'Payment method', hasDropdown: true, enum: ['cash', 'bank_transfer', 'card', 'cheque', 'other'] },
      { name: 'reference', hint: 'Payment reference' },
      { name: 'date', hint: 'Payment date', hasDropdown: true },
    ],
  },

  // --------------------------------------------------------------------------
  // EXPENSE (NEW)
  // --------------------------------------------------------------------------
  'expense.create': {
    entity: 'expense',
    verb: 'create',
    args: [
      { name: 'amount', required: true, hint: 'Expense amount' },
      { name: 'category', required: true, hint: 'Expense category', hasDropdown: true },
    ],
    flags: [
      { name: 'vendor', hint: 'Vendor (if applicable)', hasDropdown: true, searchEntity: 'vendor' },
      { name: 'date', hint: 'Expense date', hasDropdown: true },
      { name: 'description', hint: 'Description' },
      { name: 'receipt', hint: 'Receipt reference' },
      { name: 'currency', hint: 'Currency', hasDropdown: true },
      { name: 'payment_method', hint: 'Payment method', hasDropdown: true },
    ],
  },
  'expense.list': {
    entity: 'expense',
    verb: 'list',
    args: [],
    flags: [
      { name: 'category', hint: 'Filter by category', hasDropdown: true },
      { name: 'vendor', hint: 'Filter by vendor', hasDropdown: true, searchEntity: 'vendor' },
      { name: 'from', hint: 'From date', hasDropdown: true },
      { name: 'to', hint: 'To date', hasDropdown: true },
      { name: 'limit', hint: 'Max results' },
    ],
  },

  // --------------------------------------------------------------------------
  // REPORT (NEW)
  // --------------------------------------------------------------------------
  'report.generate': {
    entity: 'report',
    verb: 'generate',
    args: [
      { name: 'type', required: true, hint: 'Report type', hasDropdown: true, enum: [
        'profit_loss', 'balance_sheet', 'cash_flow', 'aged_receivables',
        'aged_payables', 'tax_summary', 'sales_by_customer', 'expenses_by_category'
      ]},
    ],
    flags: [
      { name: 'from', hint: 'Start date', hasDropdown: true },
      { name: 'to', hint: 'End date', hasDropdown: true },
      { name: 'period', hint: 'Period', hasDropdown: true, enum: ['this_month', 'last_month', 'this_quarter', 'last_quarter', 'this_year', 'last_year', 'custom'] },
      { name: 'format', hint: 'Output format', hasDropdown: true, enum: ['table', 'csv', 'pdf'] },
      { name: 'compare', hint: 'Compare with previous period', hasDropdown: true, enum: ['none', 'previous_period', 'previous_year'] },
    ],
  },
  'report.list': {
    entity: 'report',
    verb: 'list',
    args: [],
    flags: [
      { name: 'type', hint: 'Filter by report type', hasDropdown: true },
    ],
  },

  // --------------------------------------------------------------------------
  // ACCOUNT (Chart of Accounts)
  // --------------------------------------------------------------------------
  'account.create': {
    entity: 'account',
    verb: 'create',
    args: [
      { name: 'name', required: true, hint: 'Account name' },
      { name: 'type', required: true, hint: 'Account type', hasDropdown: true, enum: [
        'asset', 'liability', 'equity', 'revenue', 'expense'
      ]},
    ],
    flags: [
      { name: 'code', hint: 'Account code' },
      { name: 'parent', hint: 'Parent account', hasDropdown: true },
      { name: 'description', hint: 'Description' },
      { name: 'currency', hint: 'Currency (for multi-currency)', hasDropdown: true },
    ],
  },
  'account.list': {
    entity: 'account',
    verb: 'list',
    args: [],
    flags: [
      { name: 'type', hint: 'Filter by type', hasDropdown: true, enum: ['asset', 'liability', 'equity', 'revenue', 'expense'] },
      { name: 'search', hint: 'Search by name or code' },
    ],
  },

  // --------------------------------------------------------------------------
  // JOURNAL (Manual Journal Entries)
  // --------------------------------------------------------------------------
  'journal.create': {
    entity: 'journal',
    verb: 'create',
    args: [
      { name: 'description', required: true, hint: 'Entry description' },
    ],
    flags: [
      { name: 'date', hint: 'Entry date', hasDropdown: true },
      { name: 'reference', hint: 'Reference number' },
    ],
  },
  'journal.list': {
    entity: 'journal',
    verb: 'list',
    args: [],
    flags: [
      { name: 'from', hint: 'From date', hasDropdown: true },
      { name: 'to', hint: 'To date', hasDropdown: true },
      { name: 'limit', hint: 'Max results' },
    ],
  },

  // --------------------------------------------------------------------------
  // PRODUCT/SERVICE
  // --------------------------------------------------------------------------
  'product.create': {
    entity: 'product',
    verb: 'create',
    args: [
      { name: 'name', required: true, hint: 'Product/service name' },
      { name: 'price', required: true, hint: 'Unit price' },
    ],
    flags: [
      { name: 'type', hint: 'Type', hasDropdown: true, enum: ['product', 'service'] },
      { name: 'sku', hint: 'SKU code' },
      { name: 'description', hint: 'Description' },
      { name: 'category', hint: 'Category', hasDropdown: true },
      { name: 'tax_rate', hint: 'Tax rate %', hasDropdown: true },
      { name: 'unit', hint: 'Unit of measure', hasDropdown: true, enum: ['unit', 'hour', 'day', 'kg', 'meter', 'liter'] },
    ],
  },
  'product.list': {
    entity: 'product',
    verb: 'list',
    args: [],
    flags: [
      { name: 'type', hint: 'Filter by type', hasDropdown: true, enum: ['product', 'service', 'all'] },
      { name: 'category', hint: 'Filter by category', hasDropdown: true },
      { name: 'search', hint: 'Search by name' },
      { name: 'limit', hint: 'Max results' },
    ],
  },

  // --------------------------------------------------------------------------
  // TAX
  // --------------------------------------------------------------------------
  'tax.create': {
    entity: 'tax',
    verb: 'create',
    args: [
      { name: 'name', required: true, hint: 'Tax name (e.g., VAT, GST)' },
      { name: 'rate', required: true, hint: 'Tax rate %' },
    ],
    flags: [
      { name: 'type', hint: 'Tax type', hasDropdown: true, enum: ['sales', 'purchase', 'both'] },
      { name: 'account', hint: 'Tax account', hasDropdown: true },
    ],
  },
  'tax.list': {
    entity: 'tax',
    verb: 'list',
    args: [],
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

// Helper to check if a field has dropdown
export function fieldHasDropdown(entity: string, verb: string, fieldName: string): boolean {
  const schema = getSchema(entity, verb)
  if (!schema) return false

  const arg = schema.args.find(a => a.name === fieldName)
  if (arg) return !!arg.hasDropdown

  const flag = schema.flags.find(f => f.name === fieldName)
  if (flag) return !!flag.hasDropdown

  return false
}
