export interface FrecencyEntry {
  command: string
  count: number
  lastUsed: number
}

const STORAGE_KEY = 'palette-frecency-v1'

function load(): Record<string, FrecencyEntry> {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : {}
  } catch {
    return {}
  }
}

function save(entries: Record<string, FrecencyEntry>) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(entries))
  } catch {
    /* ignore storage errors */
  }
}

export function recordCommandUse(command: string): void {
  const entries = load()
  const key = command.trim()
  if (!key) return

  const existing = entries[key] || { command: key, count: 0, lastUsed: Date.now() }
  entries[key] = {
    command: key,
    count: existing.count + 1,
    lastUsed: Date.now(),
  }

  save(entries)
}

export function getFrecencyScores(): Record<string, number> {
  const now = Date.now()
  const entries = load()
  const scores: Record<string, number> = {}

  Object.values(entries).forEach((entry) => {
    const daysSince = (now - entry.lastUsed) / (1000 * 60 * 60 * 24)
    const recencyWeight = Math.max(0.2, 1 - daysSince / 7) // decays over 7 days
    scores[entry.command] = entry.count * recencyWeight
  })

  return scores
}
