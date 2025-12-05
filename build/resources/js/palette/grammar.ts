/**
 * Command Palette Grammar
 *
 * Defines entities, verbs, shortcuts, and icons.
 * This file should be auto-generated from schemas.ts or kept in sync manually.
 */

import { getEntities, getVerbsForEntity, getSchema, getAllSchemas } from './schemas'

// ============================================================================
// Entity Icons
// ============================================================================

export const ENTITY_ICONS: Record<string, string> = {
  // Core entities
  company: '🏢',
  customer: '👤',
  invoice: '📄',
  payment: '💳',

  // Payables
  vendor: '🏪',
  bill: '📋',
  expense: '💸',

  // Accounting
  account: '📊',
  journal: '📒',
  report: '📈',

  // Inventory & Sales
  product: '📦',
  service: '🛠️',
  tax: '🏛️',

  // Settings
  user: '👥',
  settings: '⚙️',

  // Default
  default: '📄',
}

// ============================================================================
// Entity Shortcuts (for power users)
// ============================================================================

const ENTITY_SHORTCUTS: Record<string, string> = {
  // Single letter
  c: 'company',
  i: 'invoice',
  p: 'payment',
  v: 'vendor',
  b: 'bill',
  e: 'expense',
  r: 'report',
  a: 'account',

  // Two letter
  co: 'company',
  cu: 'customer',
  in: 'invoice',
  pa: 'payment',
  ve: 'vendor',
  bi: 'bill',
  ex: 'expense',
  re: 'report',
  ac: 'account',
  jo: 'journal',
  pr: 'product',
  ta: 'tax',

  // Common abbreviations
  inv: 'invoice',
  cust: 'customer',
  comp: 'company',
  pay: 'payment',
  vend: 'vendor',
  exp: 'expense',
  rep: 'report',
  acct: 'account',
  prod: 'product',
}

// ============================================================================
// Verb Shortcuts
// ============================================================================

const VERB_SHORTCUTS: Record<string, string> = {
  c: 'create',
  l: 'list',
  v: 'view',
  d: 'delete',
  e: 'edit',
  s: 'send',
  g: 'generate',

  // Common abbreviations
  cr: 'create',
  ls: 'list',
  vw: 'view',
  del: 'delete',
  ed: 'edit',
  gen: 'generate',
}

// ============================================================================
// Functions
// ============================================================================

/**
 * Resolve entity shortcut to full entity name
 */
export function resolveEntityShortcut(input: string): string | null {
  const lower = input.toLowerCase().trim()

  // Check direct shortcut
  if (ENTITY_SHORTCUTS[lower]) {
    return ENTITY_SHORTCUTS[lower]
  }

  // Check if it's already a valid entity
  const entities = getEntities()
  if (entities.includes(lower)) {
    return lower
  }

  // Check partial match
  const match = entities.find(e => e.startsWith(lower))
  if (match) {
    return match
  }

  return null
}

/**
 * Resolve verb shortcut to full verb name
 */
export function resolveVerbShortcut(input: string, entity: string): string | null {
  const lower = input.toLowerCase().trim()
  const verbs = getVerbsForEntity(entity)

  // Check direct shortcut
  if (VERB_SHORTCUTS[lower] && verbs.includes(VERB_SHORTCUTS[lower])) {
    return VERB_SHORTCUTS[lower]
  }

  // Check if it's already a valid verb
  if (verbs.includes(lower)) {
    return lower
  }

  // Check partial match
  const match = verbs.find(v => v.startsWith(lower))
  if (match) {
    return match
  }

  return null
}

/**
 * Get available verbs for an entity
 */
export function getVerbs(entity: string): string[] {
  return getVerbsForEntity(entity)
}

/**
 * Get icon for an entity
 */
export function getEntityIcon(entity: string): string {
  return ENTITY_ICONS[entity] || ENTITY_ICONS.default
}

/**
 * Get all available entities
 */
export function getAllEntities(): string[] {
  return getEntities()
}

/**
 * Get command example for help text
 */
export function getCommandExample(entity: string, verb: string): string {
  const examples: Record<string, string> = {
    'company.create': 'company create "Acme Corp" USD',
    'company.list': 'company list --status=active',
    'customer.create': 'customer create "John Doe" --email=john@example.com',
    'invoice.create': 'invoice create "Acme Corp" 1000 --currency=USD',
    'invoice.list': 'invoice list --status=unpaid',
    'payment.create': 'payment create INV-001 500',
    'vendor.create': 'vendor create "Supplier Inc"',
    'bill.create': 'bill create "Supplier Inc" 500',
    'expense.create': 'expense create 50 "Office Supplies"',
    'report.generate': 'report generate profit_loss --period=this_month',
  }

  return examples[`${entity}.${verb}`] || `${entity} ${verb}`
}

/**
 * Check if input matches a preset shortcut (e.g., "inv" for invoice)
 */
export function isPresetShortcut(input: string): boolean {
  const lower = input.toLowerCase().trim()
  return !!ENTITY_SHORTCUTS[lower]
}

// ============================================================================
// GRAMMAR Export (for autocomplete.ts compatibility)
// ============================================================================

interface VerbDef {
  name: string
  args?: string[]
  flags?: string[]
}

interface EntityDef {
  verbs: VerbDef[]
}

/**
 * Build GRAMMAR object from schemas
 * This provides backwards compatibility with autocomplete.ts
 */
function buildGrammar(): Record<string, EntityDef> {
  const grammar: Record<string, EntityDef> = {}
  const schemas = getAllSchemas()

  // Group schemas by entity
  const byEntity: Record<string, typeof schemas> = {}
  schemas.forEach(schema => {
    if (!byEntity[schema.entity]) {
      byEntity[schema.entity] = []
    }
    byEntity[schema.entity].push(schema)
  })

  // Build grammar structure
  Object.entries(byEntity).forEach(([entity, entitySchemas]) => {
    grammar[entity] = {
      verbs: entitySchemas.map(schema => ({
        name: schema.verb,
        args: schema.args.map(a => a.name),
        flags: schema.flags.map(f => f.name),
      }))
    }
  })

  return grammar
}

export const GRAMMAR = buildGrammar()

// ============================================================================
// COMMAND_DESCRIPTIONS Export (for autocomplete.ts compatibility)
// ============================================================================

/**
 * Build command descriptions from schemas
 */
function buildCommandDescriptions(): Record<string, string> {
  const descriptions: Record<string, string> = {}
  const schemas = getAllSchemas()

  schemas.forEach(schema => {
    const key = `${schema.entity}.${schema.verb}`
    // Generate description from args
    const requiredArgs = schema.args.filter(a => a.required).map(a => a.name)
    const optionalFlags = schema.flags.slice(0, 2).map(f => f.name)

    let desc = ''
    if (requiredArgs.length) {
      desc = `Requires: ${requiredArgs.join(', ')}`
    }
    if (optionalFlags.length) {
      desc += desc ? '. ' : ''
      desc += `Options: ${optionalFlags.join(', ')}${schema.flags.length > 2 ? '...' : ''}`
    }

    descriptions[key] = desc || `${schema.verb} ${schema.entity}`
  })

  return descriptions
}

export const COMMAND_DESCRIPTIONS = buildCommandDescriptions()

// ============================================================================
// Verb Helper Functions
// ============================================================================

/**
 * Resolve verb shortcut to full verb name (alias for resolveVerbShortcut)
 */
export function resolveVerbAlias(entity: string, input: string): string | null {
  return resolveVerbShortcut(input, entity)
}

/**
 * Get verb definition from GRAMMAR
 */
export function getVerbDefinition(entity: string, verb: string): { args: any[]; flags: any[] } | null {
  const grammar = GRAMMAR[entity]
  if (!grammar) return null

  const verbDef = grammar.verbs.find((v: any) => v.name === verb)
  if (!verbDef) return null

  return {
    args: verbDef.args || [],
    flags: verbDef.flags || []
  }
}
