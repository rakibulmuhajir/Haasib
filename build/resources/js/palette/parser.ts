import type { ParsedCommand } from '@/types/palette'
import { GRAMMAR, resolveEntityShortcut, resolveVerbAlias, getVerbDefinition } from './grammar'
import { expandPresetShortcut } from './shortcuts'

/**
 * Parse a command string into a structured ParsedCommand
 */
export function parse(input: string): ParsedCommand {
  const normalizedInput = expandPresetShortcut(input)
  const result: ParsedCommand = {
    raw: normalizedInput.trim(),
    entity: '',
    verb: '',
    flags: {},
    complete: false,
    confidence: 0,
    errors: [],
    idemKey: '',
  }

  if (!result.raw) {
    result.errors.push('Empty command')
    return result
  }

  const tokens = tokenize(result.raw)
  
  if (tokens.length === 0) {
    result.errors.push('No tokens found')
    return result
  }

  const first = tokens[0]
  let rest = tokens.slice(1)

  // Parse entity.verb
  const entityVerb = parseEntityVerb(first, rest)
  if (entityVerb) {
    result.entity = entityVerb.entity
    result.verb = entityVerb.verb
    if (entityVerb.consumed > 1) {
      rest = rest.slice(entityVerb.consumed - 1)
    }
  } else {
    result.errors.push(`Unknown command: ${first}`)
    return result
  }

  // Extract flags and remaining positional args
  const { flags, remaining, flagErrors } = extractFlags(rest, result.entity, result.verb)
  result.flags = flags
  result.errors.push(...flagErrors)

  // Set subject from remaining tokens
  if (remaining.length > 0) {
    result.subject = remaining.join(' ')
  }

  // Infer flags from subject (smart parsing)
  inferFromSubject(result)

  // Calculate completion status
  result.complete = isComplete(result)
  result.confidence = calculateConfidence(result)
  result.idemKey = generateIdemKey(result)

  return result
}

/**
 * Parse entity.verb from tokens
 */
function parseEntityVerb(
  token: string, 
  remainingTokens: string[]
): { entity: string; verb: string; consumed: number } | null {
  
  // Handle "entity.verb" format
  if (token.includes('.')) {
    const [entityPart, verbPart] = token.split('.')
    const entity = resolveEntityShortcut(entityPart)
    if (!entity) return null
    
    const verb = resolveVerbAlias(entity, verbPart)
    if (!verb) return null
    
    return { entity, verb, consumed: 1 }
  }

  // Handle "entity verb" format (space separated)
  const entity = resolveEntityShortcut(token)
  if (entity) {
    if (remainingTokens.length > 0) {
      const candidateVerb = resolveVerbAlias(entity, remainingTokens[0])
      if (candidateVerb) {
        return { entity, verb: candidateVerb, consumed: 2 }
      }
    }
    // Use default verb
    return { entity, verb: GRAMMAR[entity].defaultVerb, consumed: 1 }
  }

  return null
}

/**
 * Tokenize input, respecting quotes
 */
function tokenize(input: string): string[] {
  const tokens: string[] = []
  let current = ''
  let inQuotes = false
  let quoteChar = ''

  for (const char of input) {
    if ((char === '"' || char === "'") && !inQuotes) {
      inQuotes = true
      quoteChar = char
    } else if (char === quoteChar && inQuotes) {
      inQuotes = false
      quoteChar = ''
    } else if (char === ' ' && !inQuotes) {
      if (current) {
        tokens.push(current)
        current = ''
      }
    } else {
      current += char
    }
  }

  if (current) tokens.push(current)
  return tokens
}

/**
 * Extract flags from tokens
 */
function extractFlags(
  tokens: string[],
  entity: string,
  verb: string
): { flags: Record<string, unknown>; remaining: string[]; flagErrors: string[] } {
  const flags: Record<string, unknown> = {}
  const remaining: string[] = []
  const errors: string[] = []

  const verbDef = getVerbDefinition(entity, verb)
  if (!verbDef) {
    return { flags, remaining: tokens, flagErrors: errors }
  }

  let i = 0
  while (i < tokens.length) {
    const token = tokens[i]

    if (token.startsWith('--')) {
      const { name, value, consumed, error } = parseLongFlag(token, tokens[i + 1], verbDef.flags)
      if (error) errors.push(error)
      else if (name) flags[name] = value
      i += consumed
    } else if (token.startsWith('-') && token.length === 2) {
      const { name, value, consumed, error } = parseShortFlag(token, tokens[i + 1], verbDef.flags)
      if (error) errors.push(error)
      else if (name) flags[name] = value
      i += consumed
    } else {
      remaining.push(token)
      i++
    }
  }

  return { flags, remaining, flagErrors: errors }
}

/**
 * Parse --flag or --flag=value
 */
function parseLongFlag(
  token: string,
  nextToken: string | undefined,
  flagDefs: Array<{ name: string; shorthand?: string; type: string; required: boolean; default?: unknown }>
): { name: string | null; value: unknown; consumed: number; error: string | null } {
  const withoutDashes = token.substring(2)
  
  // Handle --flag=value
  if (withoutDashes.includes('=')) {
    const [name, ...valueParts] = withoutDashes.split('=')
    const value = valueParts.join('=')
    const flagDef = flagDefs.find(f => f.name === name)
    
    if (!flagDef) {
      return { name: null, value: null, consumed: 1, error: `Unknown flag: --${name}` }
    }
    
    return { name, value: coerceValue(value, flagDef.type), consumed: 1, error: null }
  }

  // Handle --flag value or --flag (boolean)
  const flagDef = flagDefs.find(f => f.name === withoutDashes)
  
  if (!flagDef) {
    return { name: null, value: null, consumed: 1, error: `Unknown flag: --${withoutDashes}` }
  }

  if (flagDef.type === 'boolean') {
    return { name: withoutDashes, value: true, consumed: 1, error: null }
  }

  if (!nextToken || nextToken.startsWith('-')) {
    return { name: null, value: null, consumed: 1, error: `Flag --${withoutDashes} requires a value` }
  }

  return { name: withoutDashes, value: coerceValue(nextToken, flagDef.type), consumed: 2, error: null }
}

/**
 * Parse -f or -f value
 */
function parseShortFlag(
  token: string,
  nextToken: string | undefined,
  flagDefs: Array<{ name: string; shorthand?: string; type: string; required: boolean }>
): { name: string | null; value: unknown; consumed: number; error: string | null } {
  const shorthand = token[1]
  const flagDef = flagDefs.find(f => f.shorthand === shorthand)
  
  if (!flagDef) {
    return { name: null, value: null, consumed: 1, error: `Unknown flag: -${shorthand}` }
  }

  if (flagDef.type === 'boolean') {
    return { name: flagDef.name, value: true, consumed: 1, error: null }
  }

  if (!nextToken || nextToken.startsWith('-')) {
    return { name: null, value: null, consumed: 1, error: `Flag -${shorthand} requires a value` }
  }

  return { name: flagDef.name, value: coerceValue(nextToken, flagDef.type), consumed: 2, error: null }
}

/**
 * Coerce string value to appropriate type
 */
function coerceValue(value: string, type: string): unknown {
  if (type === 'number') {
    const num = parseFloat(value)
    return isNaN(num) ? value : num
  }
  
  if (type === 'boolean') {
    return value.toLowerCase() === 'true' || value === '1'
  }
  
  return value
}

/**
 * Infer flag values from positional subject
 */
function inferFromSubject(result: ParsedCommand): void {
  if (!result.subject) return

  const words = result.subject.split(/\s+/)

  // Customer create: accept positional name [email] [currency]
  if (result.entity === 'customer' && result.verb === 'create') {
    let email: string | undefined
    let currency: string | undefined

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    const parts: string[] = []

    for (const word of words) {
      if (!email && emailRegex.test(word)) {
        email = word
        continue
      }
      if (
        !currency &&
        word.length === 3 &&
        /^[A-Za-z]{3}$/.test(word)
      ) {
        currency = word.toUpperCase()
        continue
      }
      parts.push(word)
    }

    if (!result.flags.email && email) {
      result.flags.email = email
    }
    if (!result.flags.currency && currency) {
      result.flags.currency = currency
    }
    if (!result.flags.name) {
      const name = parts.join(' ').trim()
      if (name) {
        result.flags.name = name
      }
    }
  }

  // Invoice create: positional customer, amount, currency
  if (result.entity === 'invoice' && result.verb === 'create') {
    let amount: number | undefined
    let currency: string | undefined
    const parts: string[] = []

    for (const word of words) {
      if (!amount && /^\$?[\d,]+\.?\d*$/.test(word)) {
        const num = parseFloat(word.replace(/[,$]/g, ''))
        if (!isNaN(num)) {
          amount = num
          continue
        }
      }

      if (!currency && /^[A-Za-z]{3}$/.test(word)) {
        currency = word.toUpperCase()
        continue
      }

      parts.push(word)
    }

    if (!result.flags.amount && amount !== undefined) {
      result.flags.amount = amount
    }
    if (!result.flags.currency && currency) {
      result.flags.currency = currency
    }
    if (!result.flags.customer) {
      const name = parts.join(' ').trim()
      if (name) {
        result.flags.customer = name
      }
    }
  }

  // Payment create: positional invoice, amount
  if (result.entity === 'payment' && result.verb === 'create') {
    let amount: number | undefined
    const parts: string[] = []

    for (const word of words) {
      if (!amount && /^\$?[\d,]+\.?\d*$/.test(word)) {
        const num = parseFloat(word.replace(/[,$]/g, ''))
        if (!isNaN(num)) {
          amount = num
          continue
        }
      }
      parts.push(word)
    }

    if (!result.flags.amount && amount !== undefined) {
      result.flags.amount = amount
    }
    if (!result.flags.invoice) {
      const invoiceToken = parts.join(' ').trim()
      if (invoiceToken) {
        result.flags.invoice = invoiceToken
      }
    }
  }

  // Company create: "company.create Acme Corp USD"
  if (result.entity === 'company' && result.verb === 'create') {
    if (!result.flags.name && words.length > 0) {
      // Find currency (3 uppercase letters at the end)
      const lastWord = words[words.length - 1]
      if (lastWord && lastWord.length === 3 && lastWord.toUpperCase() === lastWord) {
        result.flags.currency = lastWord.toUpperCase()
        result.flags.name = words.slice(0, -1).join(' ')
      } else {
        // No currency found, entire subject is name
        result.flags.name = result.subject
      }
    }
  }

  // Company switch/delete: "company.switch acme-corp"
  if (result.entity === 'company' && ['switch', 'delete', 'view'].includes(result.verb)) {
    if (!result.flags.slug && words.length > 0) {
      result.flags.slug = words.join('-').toLowerCase()
    }
  }

  // User invite: "user.invite john@example.com"
  if (result.entity === 'user' && result.verb === 'invite') {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    const emailWord = words.find(w => emailRegex.test(w))
    
    if (emailWord && !result.flags.email) {
      result.flags.email = emailWord
      // Remaining words could be name
      const nameWords = words.filter(w => w !== emailWord)
      if (nameWords.length > 0 && !result.flags.name) {
        result.flags.name = nameWords.join(' ')
      }
    }
  }

  // User operations with email: deactivate, delete, assign-role
  if (result.entity === 'user' && ['deactivate', 'delete', 'assign-role', 'remove-role'].includes(result.verb)) {
    if (!result.flags.email && words.length > 0) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      const emailWord = words.find(w => emailRegex.test(w))
      if (emailWord) {
        result.flags.email = emailWord
      }
    }
  }
}

/**
 * Check if command has all required flags
 */
function isComplete(result: ParsedCommand): boolean {
  if (result.errors.length > 0) return false
  if (!result.entity || !result.verb) return false

  const verbDef = getVerbDefinition(result.entity, result.verb)
  if (!verbDef) return false

  const requiredFlags = verbDef.flags.filter(f => f.required)
  for (const flag of requiredFlags) {
    if (!(flag.name in result.flags)) {
      return false
    }
  }

  return true
}

/**
 * Calculate confidence score (0-1)
 */
function calculateConfidence(result: ParsedCommand): number {
  if (!result.entity || !result.verb) return 0
  if (result.errors.length > 0) return 0.3

  const verbDef = getVerbDefinition(result.entity, result.verb)
  if (!verbDef) return 0

  const totalFlags = verbDef.flags.length
  const providedFlags = Object.keys(result.flags).length

  if (totalFlags === 0) return 1

  const flagScore = providedFlags / totalFlags
  return result.complete ? 1 : Math.min(0.9, flagScore)
}

/**
 * Generate idempotency key for the command
 */
function generateIdemKey(result: ParsedCommand): string {
  if (!result.complete) return ''
  
  const parts = [
    result.entity,
    result.verb,
    ...Object.entries(result.flags)
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([k, v]) => `${k}=${v}`),
  ]
  
  return btoa(parts.join('|')).substring(0, 32)
}
