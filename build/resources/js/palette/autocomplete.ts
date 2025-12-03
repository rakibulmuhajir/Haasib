import { GRAMMAR, ENTITY_ICONS, COMMAND_DESCRIPTIONS, resolveEntityShortcut } from './grammar'
import { getPresetShortcuts } from './shortcuts'
import type { Suggestion } from '@/types/palette'

export interface GenerateSuggestionOptions {
  maxResults?: number
  stage?: 'entity' | 'verb'
  entity?: string
  frecencyScores?: Record<string, number>
}

/**
 * Generate command suggestions based on partial input
 */
export function generateSuggestions(input: string, options: GenerateSuggestionOptions = {}): Suggestion[] {
  const maxResults = options.maxResults ?? 8
  const stage = options.stage ?? 'entity'
  const trimmed = input.trim()
  const frecencyScores = options.frecencyScores || {}

  if (!trimmed && stage === 'entity') {
    return getQuickStartSuggestions(frecencyScores).slice(0, maxResults)
  }

  const inputLower = input.toLowerCase()
  const suggestions: Array<Suggestion & { commandKey?: string }> = []

  const targetEntity = options.entity || resolveEntityShortcut(trimmed.split(/\s+/)[0] || '') || ''
  const completions = stage === 'verb'
    ? (targetEntity ? getVerbCompletions(targetEntity) : getEntityCompletions())
    : getEntityCompletions()

  for (const completion of completions) {
    const score = scoreSuggestion(inputLower, completion.value.toLowerCase())
    if (score > 0) {
      const frecencyBoost = completion.commandKey
        ? (frecencyScores[completion.commandKey] || 0) * 50
        : 0
      suggestions.push({ ...completion, score: score + frecencyBoost })
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
function getQuickStartSuggestions(frecencyScores: Record<string, number>): Suggestion[] {
  const topFrequent = Object.entries(frecencyScores)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 3)
    .map(([command]) => ({
      type: 'history' as const,
      value: `${command} `,
      label: command,
      description: 'Recent favorite',
      icon: '⏱',
    }))

  const presets = getPresetShortcuts()
  const shortcutPicks = Object.entries(presets).slice(0, 3).map(([shortcut, cmd]) => ({
    type: 'command' as const,
    value: `${cmd.entity} ${cmd.verb} `,
    label: `${cmd.entity} ${cmd.verb}`,
    description: `Shortcut: ${shortcut}`,
    icon: ENTITY_ICONS[cmd.entity] || '⌨️',
  }))

  return [
    ...topFrequent,
    ...shortcutPicks,
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

function getEntityCompletions(): Array<Suggestion & { commandKey?: string }> {
  const completions: Array<Suggestion & { commandKey?: string }> = []

  for (const [entityName, entity] of Object.entries(GRAMMAR)) {
    // Entity alone
    completions.push({
      type: 'entity',
      value: `${entityName} `,
      label: entityName,
      description: '',
      icon: ENTITY_ICONS[entityName] || '📦',
      commandKey: `${entityName}.list`,
    })
  }

  // Preset shortcuts as explicit items
  const presets = getPresetShortcuts()
  for (const [shortcut, cmd] of Object.entries(presets)) {
    const commandKey = `${cmd.entity}.${cmd.verb}`
    const description = COMMAND_DESCRIPTIONS[commandKey] || `${cmd.verb} ${cmd.entity}`
    completions.push({
      type: 'command',
      value: `${cmd.entity} ${cmd.verb} `,
      label: `${cmd.entity} ${cmd.verb}`,
      description: `Shortcut: ${shortcut} — ${description}`,
      icon: ENTITY_ICONS[cmd.entity] || '⌨️',
      commandKey,
    })
  }

  // Built-in commands
  completions.push({
    type: 'command',
    value: 'help',
    label: 'help',
    description: COMMAND_DESCRIPTIONS['help'] || 'Show help',
    icon: '❓',
    commandKey: 'help',
  })

  completions.push({
    type: 'command',
    value: 'clear',
    label: 'clear',
    description: COMMAND_DESCRIPTIONS['clear'] || 'Clear output',
    icon: '🗑️',
    commandKey: 'clear',
  })

  return completions
}

function getVerbCompletions(entityName: string): Array<Suggestion & { commandKey?: string }> {
  const entity = GRAMMAR[entityName]
  if (!entity) return []

  return entity.verbs.map((verb) => {
    const commandKey = `${entityName}.${verb.name}`
    const description = COMMAND_DESCRIPTIONS[commandKey] || ''
    return {
      type: 'verb' as const,
      value: `${entityName} ${verb.name} `,
      label: `${verb.name}`,
      description,
      icon: ENTITY_ICONS[entityName] || '📦',
      commandKey,
    }
  })
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
