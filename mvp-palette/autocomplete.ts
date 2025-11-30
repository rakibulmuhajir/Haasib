import { GRAMMAR } from './grammar'

/**
 * Generate command suggestions based on partial input
 */
export function generateSuggestions(input: string, maxResults = 8): string[] {
  if (!input.trim()) return []

  const inputLower = input.toLowerCase()
  const suggestions: ScoredSuggestion[] = []

  // Generate all possible completions
  const completions = getAllCompletions()

  for (const completion of completions) {
    const score = scoreSuggestion(inputLower, completion.toLowerCase())
    if (score > 0) {
      suggestions.push({ text: completion, score })
    }
  }

  // Sort by score (higher = better match), then by length (shorter = simpler)
  return suggestions
    .sort((a, b) => {
      if (b.score !== a.score) return b.score - a.score
      return a.text.length - b.text.length
    })
    .slice(0, maxResults)
    .map(s => s.text)
}

interface ScoredSuggestion {
  text: string
  score: number
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
function getAllCompletions(): string[] {
  const completions: string[] = []

  for (const [entityName, entity] of Object.entries(GRAMMAR)) {
    // Add entity name alone (uses default verb)
    completions.push(entityName)

    // Add entity shortcuts
    for (const shortcut of entity.shortcuts) {
      completions.push(shortcut)
    }

    // Add entity.verb combinations
    for (const verb of entity.verbs) {
      // Full form: company.create
      completions.push(`${entityName}.${verb.name}`)

      // With shortcuts: co.create
      for (const shortcut of entity.shortcuts) {
        completions.push(`${shortcut}.${verb.name}`)
      }

      // With verb aliases: company.new
      for (const alias of verb.aliases) {
        completions.push(`${entityName}.${alias}`)

        for (const shortcut of entity.shortcuts) {
          completions.push(`${shortcut}.${alias}`)
        }
      }
    }
  }

  // Add built-in commands
  completions.push('help')
  completions.push('clear')

  // Deduplicate and sort
  return [...new Set(completions)].sort()
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
