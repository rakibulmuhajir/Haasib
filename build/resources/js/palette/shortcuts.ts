/**
 * Preset shortcuts (non-configurable)
 *
 * Examples:
 *  ic -> invoice create
 *  il -> invoice list
 */
const PRESET_SHORTCUTS: Record<string, { entity: string; verb: string }> = {
  ic: { entity: 'invoice', verb: 'create' },
  il: { entity: 'invoice', verb: 'list' },
  pc: { entity: 'payment', verb: 'create' },
  pl: { entity: 'payment', verb: 'list' },
  cc: { entity: 'customer', verb: 'create' },
  cl: { entity: 'customer', verb: 'list' },
  jc: { entity: 'journal', verb: 'create' },
}

/**
 * Expand preset shortcut to canonical "entity verb" string.
 * Unknown shortcuts return the original input unchanged.
 */
export function expandPresetShortcut(raw: string): string {
  const trimmed = raw.trim()
  if (!trimmed) return trimmed

  const [first, ...rest] = trimmed.split(/\s+/)
  const preset = PRESET_SHORTCUTS[first.toLowerCase()]
  if (!preset) return trimmed

  const tail = rest.join(' ')
  const expanded = `${preset.entity} ${preset.verb}`.trim()
  return tail ? `${expanded} ${tail}` : `${expanded} `
}

/**
 * Check if token is a preset shortcut.
 */
export function isPresetShortcut(token: string): boolean {
  return Boolean(PRESET_SHORTCUTS[token.toLowerCase()])
}

/**
 * Expose preset map (read-only) for suggestion displays.
 */
export function getPresetShortcuts(): Record<string, { entity: string; verb: string }> {
  return { ...PRESET_SHORTCUTS }
}
