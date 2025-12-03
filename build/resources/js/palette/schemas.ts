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

const SCHEMAS: Record<string, CommandSchema> = {}

function register(schema: CommandSchema) {
  SCHEMAS[`${schema.entity}.${schema.verb}`] = schema
}

register({
  entity: 'invoice',
  verb: 'create',
  description: 'Create a new invoice',
  args: [
    { name: 'customer', type: 'reference', required: true, hint: 'Customer name or ID' },
    { name: 'amount', type: 'currency', required: true, hint: 'Invoice amount' },
  ],
  flags: [
    { name: 'currency', short: 'c', type: 'currency', hint: 'Transaction currency', defaultSource: 'customer.currency' },
    { name: 'due', short: 'd', type: 'date', hint: 'Due date (YYYY-MM-DD or +30d)', defaultSource: 'customer.payment_terms' },
    { name: 'reference', short: 'r', type: 'string', hint: 'PO number or reference' },
    { name: 'description', type: 'string', hint: 'Description / memo' },
    { name: 'draft', type: 'boolean', hint: 'Save as draft' },
  ],
})

register({
  entity: 'payment',
  verb: 'create',
  description: 'Record a payment',
  args: [
    { name: 'invoice', type: 'reference', required: true, hint: 'Invoice number or ID' },
    { name: 'amount', type: 'currency', required: true, hint: 'Payment amount' },
  ],
  flags: [
    { name: 'method', short: 'm', type: 'enum', values: ['cash', 'check', 'card', 'bank_transfer', 'other'], defaultSource: 'user.pref.method' },
    { name: 'reference', short: 'r', type: 'string', hint: 'Reference or note' },
    { name: 'date', short: 'd', type: 'date', hint: 'Payment date', defaultSource: 'system.today' },
  ],
})

export function getSchema(entity?: string | null, verb?: string | null): CommandSchema | null {
  if (!entity || !verb) return null
  return SCHEMAS[`${entity}.${verb}`] ?? null
}
