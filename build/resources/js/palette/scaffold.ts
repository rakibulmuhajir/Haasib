import type { ParsedCommand } from '@/types/palette'
import type { CommandSchema } from './schemas'

export interface ScaffoldState {
  skeleton: string
  pointerLabel: string
  requiredRemaining: string[]
  optionalFlags: Array<{ name: string; value?: string; source?: string }>
}

export function buildScaffold(parsed: ParsedCommand, schema: CommandSchema | null, context: { companyName?: string; companyCurrency?: string }): ScaffoldState | null {
  if (!schema) return null

  const tokens: string[] = []
  const requiredRemaining: string[] = []

  schema.args.forEach(arg => {
    const hasValue = Boolean(parsed.flags?.[arg.name] || parsed.subject)
    if (arg.required && !hasValue) {
      requiredRemaining.push(arg.name)
    }
    tokens.push(arg.required ? `<${arg.name}>` : `[${arg.name}]`)
  })

  const flagChips = schema.flags.map(flag => {
    let value: string | undefined
    let source: string | undefined

    // simple defaults: company currency, company name
    if (flag.name === 'currency') {
      value = (parsed.flags?.currency as string) || context.companyCurrency
      source = value ? (parsed.flags?.currency ? 'user' : 'company') : undefined
    }
    if (flag.name === 'due') {
      source = 'customer.payment_terms'
    }
    if (flag.name === 'method') {
      source = 'user.preference'
    }

    return { name: flag.name, value, source }
  })

  const skeleton = `${schema.entity} ${schema.verb} ${tokens.join(' ')}`
  const pointerLabel = requiredRemaining[0] ? `${requiredRemaining[0]} (${schema.args.find(a => a.name === requiredRemaining[0])?.hint || ''})` : 'Ready'

  return {
    skeleton,
    pointerLabel,
    requiredRemaining,
    optionalFlags: flagChips,
  }
}
