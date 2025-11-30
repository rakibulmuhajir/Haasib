import { GRAMMAR, ENTITY_ICONS, COMMAND_DESCRIPTIONS } from './grammar'
import type { Suggestion } from '@/types/palette'

/**
 * Generate command suggestions based on partial input
 */
export function generateSuggestions(input: string, maxResults = 8): Suggestion[] {
  if (!input.trim()) return getQuickStartSuggestions()

  const inputLower = input.toLowerCase()
  const suggestions: Suggestion[] = []

  // Generate all possible completions
  const completions = getAllCompletions()

  for (const completion of completions) {
    const score = scoreSuggestion(inputLower, completion.value.toLowerCase())
    if (score > 0) {
      suggestions.push({ ...completion, score })
    }
  }

  // Sort by score (higher = better match), then by length (shorter = simpler)
  return suggestions
    .sort((a, b) => {
      if (b.score !== a.score) return b.score! - a.score!
      return a.value.length - b.value.length
    })
    .slice(0, maxResults)
}

/**
 * Quick start suggestions when input is empty
 */
function getQuickStartSuggestions(): Suggestion[] {
  return [
    {
      type: 'command',
      value: 'company list',
      label: 'company list',
      description: 'View all companies',
      icon: ENTITY_ICONS.company,
    },
    {
      type: 'command',
      value: 'user invite ',
      label: 'user invite',
      description: 'Invite a new user',
      icon: ENTITY_ICONS.user,
    },
    {
      type: 'command',
      value: 'user list',
      label: 'user list',
      description: 'View all users',
      icon: ENTITY_ICONS.user,
    },
    {
      type: 'command',
      value: 'help',
      label: 'help',
      description: 'Show all commands',
      icon: '❓',
    },
  ]
}

/**
 * Score how well input matches a completion
 * Higher score = better match
 */
function scoreSuggestion(input: string, completion: string): number {
  // Exact prefix match - highest score
  if (completion.startsWith(input)) {
    return 100 + (input.length / completion.length) * 50
  }

  // Match after the dot (e.g., "list" matches "company.list")
  const dotIndex = completion.indexOf('.')
  if (dotIndex > 0) {
    const afterDot = completion.substring(dotIndex + 1)
    if (afterDot.startsWith(input)) {
      return 80 + (input.length / afterDot.length) * 30
    }
  }

  // Fuzzy match - each character in order
  let inputIdx = 0
  let score = 0
  for (let i = 0; i < completion.length && inputIdx < input.length; i++) {
    if (completion[i] === input[inputIdx]) {
      // Bonus for consecutive matches
      score += (i === 0 || completion[i - 1] === '.' || completion[i - 1] === ' ') ? 10 : 5
      inputIdx++
    }
  }

  // Only return score if all input chars were found
  return inputIdx === input.length ? score : 0
}

/**
 * Get all possible command completions from grammar
 */
function getAllCompletions(): Suggestion[] {
  const completions: Suggestion[] = []

  for (const [entityName, entity] of Object.entries(GRAMMAR)) {
    // Entity alone
    completions.push({
      type: 'entity',
      value: `${entityName} `,
      label: entityName,
      description: `${entity.verbs.length} commands available`,
      icon: ENTITY_ICONS[entityName] || '📦',
    })

    // Entity verb combinations (canonical only)
    for (const verb of entity.verbs) {
      const commandKey = `${entityName}.${verb.name}`
      const description = COMMAND_DESCRIPTIONS[commandKey] || `${verb.name} ${entityName}`

      // Only suggest canonical verb name, not aliases
      completions.push({
        type: 'command',
        value: `${entityName} ${verb.name} `,
        label: `${entityName} ${verb.name}`,
        description,
        icon: ENTITY_ICONS[entityName] || '📦',
      })
    }
  }

  // Built-in commands
  completions.push({
    type: 'command',
    value: 'help',
    label: 'help',
    description: COMMAND_DESCRIPTIONS['help'] || 'Show help',
    icon: '❓',
  })

  completions.push({
    type: 'command',
    value: 'clear',
    label: 'clear',
    description: COMMAND_DESCRIPTIONS['clear'] || 'Clear output',
    icon: '🗑️',
  })

  return completions
}

/**
 * Get suggestions for flag values (for future use with entity catalogs)
 */
export function getFlagSuggestions(
  entity: string,
  verb: string,
  flag: string,
  partial: string,
  catalog: Record<string, string[]> = {}
): string[] {
  // For now, just return from catalog if available
  const key = `${entity}.${flag}`
  const values = catalog[key] || []

  if (!partial) return values.slice(0, 5)

  const partialLower = partial.toLowerCase()
  return values
    .filter(v => v.toLowerCase().includes(partialLower))
    .slice(0, 5)
}
