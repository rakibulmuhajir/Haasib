import { registry } from './registry'

export function parse(input: string): { action: string; params: Record<string, string> } {
  const tokens = input.trim().split(/\s+/)
  const first = tokens.shift() || ''
  const item = registry.find(r => r.id === first || r.label === first || r.aliases.includes(first))
  const action = item ? item.id : first
  const params: Record<string, string> = {}
  tokens.forEach(t => {
    const m = t.match(/^--([^=]+)=(.+)$/)
    if (m) params[m[1]] = m[2]
  })
  return { action, params }
}
