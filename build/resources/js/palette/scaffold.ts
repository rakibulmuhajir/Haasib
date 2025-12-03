import type { ParsedCommand } from '@/types/palette'
import type { CommandSchema } from './schemas'

export interface ScaffoldState {
  skeleton: string
  pointerLabel: string
  currentArg?: string
  requiredRemaining: string[]
  optionalFlags: Array<{ name: string; value?: string; source?: string; loading?: boolean }>
  ghosts: Array<{ label: string; completed: boolean }>
}

export function buildScaffold(
  parsed: ParsedCommand,
  schema: CommandSchema | null,
  context: { companyName?: string; companyCurrency?: string; defaults?: Record<string, { value: string; source?: string }> }
  ,
  activeArg?: string
): ScaffoldState | null {
  if (!schema) return null

  const tokens: string[] = []
  const requiredRemaining: string[] = []
  const ghosts: Array<{ label: string; completed: boolean }> = []

  schema.args.forEach(arg => {
    const value = parsed.flags?.[arg.name] ?? context.defaults?.[arg.name]?.value
    const hasValue = value !== undefined && value !== null && value !== ''
    if (arg.required && !hasValue) {
      requiredRemaining.push(arg.name)
    }
    tokens.push(arg.required ? `<${arg.name}>` : `[${arg.name}]`)
    ghosts.push({ label: arg.name, completed: hasValue })
  })

  const flagChips = schema.flags.map(flag => {
    const def = context.defaults?.[flag.name]
    const value = (parsed.flags?.[flag.name] as string) || def?.value
    const source = parsed.flags?.[flag.name] ? 'user' : def?.source
    return { name: flag.name, value, source }
  })

  const skeleton = `${schema.entity} ${schema.verb} ${tokens.join(' ')}`
  const currentArg = activeArg || requiredRemaining[0]
  const pointerLabel = currentArg
    ? `${currentArg}${schema.args.find(a => a.name === currentArg)?.hint ? ` (${schema.args.find(a => a.name === currentArg)?.hint})` : ''}`
    : 'Ready'

  return {
    skeleton,
    pointerLabel,
    currentArg,
    requiredRemaining,
    optionalFlags: flagChips,
    ghosts,
  }
}
