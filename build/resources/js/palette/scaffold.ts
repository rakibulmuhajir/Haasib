import type { ParsedCommand } from '@/types/palette'
import type { CommandSchema } from './schemas'

export interface ArgState {
  name: string
  displayValue: string
  status: 'current' | 'filled' | 'default' | 'pending'
  hint?: string
}

export interface ScaffoldState {
  args: ArgState[]
  currentArg?: string
  requiredRemaining: string[]
  optionalFlags: Array<{ name: string; value?: string; source?: string; loading?: boolean; hint?: string }>
  statusMessage: string
  optionalHint: string
  isReady: boolean
  pointerHint: string
}

export function buildScaffold(
  parsed: ParsedCommand,
  schema: CommandSchema | null,
  context: {
    companyName?: string
    companyCurrency?: string
    defaults?: Record<string, { value: string; source?: string }>
  },
  activeArg?: string
): ScaffoldState | null {
  if (!schema) return null

  const args: ArgState[] = []
  const requiredRemaining: string[] = []

  // Determine which arg is current (first unfilled required arg, or activeArg)
  let firstUnfilledRequired: string | undefined

  // First pass: find first unfilled required arg
  schema.args.forEach(arg => {
    const userValue = parsed.flags?.[arg.name]
    const defaultMeta = context.defaults?.[arg.name]
    const hasUserValue = userValue !== undefined && userValue !== null && String(userValue).trim() !== ''
    const hasDefault = defaultMeta?.value !== undefined && defaultMeta?.value !== null

    if (arg.required && !hasUserValue && !hasDefault && !firstUnfilledRequired) {
      firstUnfilledRequired = arg.name
    }
  })

  const currentArgName = activeArg || firstUnfilledRequired

  // Second pass: build arg states
  schema.args.forEach(arg => {
    const userValue = parsed.flags?.[arg.name]
    const defaultMeta = context.defaults?.[arg.name]
    const defaultValue = defaultMeta?.value
    const hasUserValue = userValue !== undefined && userValue !== null && String(userValue).trim() !== ''
    const hasDefault = defaultValue !== undefined && defaultValue !== null && String(defaultValue).trim() !== ''

    // Track missing required args
    if (arg.required && !hasUserValue && !hasDefault) {
      requiredRemaining.push(arg.name)
    }

    // Determine status
    let status: ArgState['status']
    let displayValue: string

    if (arg.name === currentArgName && !hasUserValue) {
      status = 'current'
      displayValue = hasDefault ? `${defaultValue}` : arg.name
    } else if (hasUserValue) {
      status = 'filled'
      displayValue = String(userValue)
    } else if (hasDefault) {
      status = 'default'
      displayValue = `${defaultValue}`
    } else {
      status = 'pending'
      displayValue = arg.name
    }

    args.push({
      name: arg.name,
      displayValue,
      status,
      hint: arg.hint,
    })
  })

  // Build flag chips
  const flagChips = schema.flags.map(flag => {
    const defaultMeta = context.defaults?.[flag.name]
    const userValue = parsed.flags?.[flag.name] as string | undefined
    const value = userValue || defaultMeta?.value
    const source = userValue ? 'user' : defaultMeta?.source
    return {
      name: flag.name,
      value,
      source,
      hint: flag.hint,
      loading: false,
    }
  })

  const isReady = requiredRemaining.length === 0

  // Build pointer hint
  let pointerHint = ''
  if (currentArgName) {
    const argDef = schema.args.find(a => a.name === currentArgName)
    pointerHint = argDef?.hint || ''
  }

  // Build status message
  let statusMessage: string
  if (isReady) {
    statusMessage = 'Ready — press Enter to execute'
  } else if (requiredRemaining.length === 1) {
    statusMessage = `Enter ${requiredRemaining[0]}`
  } else {
    statusMessage = `Need: ${requiredRemaining.join(', ')}`
  }

  // Build optional hint
  let optionalHint = ''
  if (flagChips.length > 0) {
    const flagNames = flagChips.slice(0, 3).map(f => `--${f.name}`).join(', ')
    const moreCount = flagChips.length > 3 ? ` +${flagChips.length - 3} more` : ''
    optionalHint = `Optional: ${flagNames}${moreCount}`
  }

  return {
    args,
    currentArg: currentArgName,
    requiredRemaining,
    optionalFlags: flagChips,
    statusMessage,
    optionalHint,
    isReady,
    pointerHint,
  }
}
