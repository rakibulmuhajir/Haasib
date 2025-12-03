import type { EntityDefinition } from '@/types/palette'
import { ENTITY_SCHEMAS } from './schemas'

const FALLBACK_ICONS: Record<string, string> = {
  company: '🏢',
  user: '👤',
  role: '🔑',
  customer: '👥',
  invoice: '📄',
  payment: '💰',
  account: '📒',
  journal: '📔',
}

export const ENTITY_ICONS: Record<string, string> = {}
export const COMMAND_DESCRIPTIONS: Record<string, string> = {}
export const COMMAND_EXAMPLES: Record<string, string> = {}

export const GRAMMAR: Record<string, EntityDefinition> = buildGrammarFromSchemas()

function buildGrammarFromSchemas(): Record<string, EntityDefinition> {
  const grammar: Record<string, EntityDefinition> = {}

  Object.values(ENTITY_SCHEMAS).forEach((entity) => {
    ENTITY_ICONS[entity.name] = entity.icon || FALLBACK_ICONS[entity.name] || '📄'

    const verbs = entity.verbs.map((verb) => {
      const commandKey = `${entity.name}.${verb.name}`
      if (verb.description) COMMAND_DESCRIPTIONS[commandKey] = verb.description
      if (verb.example) COMMAND_EXAMPLES[commandKey] = verb.example

      const flags = [
        ...verb.args.map(arg => ({
          name: arg.name,
          shorthand: undefined,
          type: arg.type,
          required: arg.required,
          default: undefined,
          values: undefined,
        })),
        ...verb.flags.map(flag => ({
          name: flag.name,
          shorthand: flag.short ?? undefined,
          type: flag.type,
          required: Boolean(flag.required),
          default: undefined,
          values: flag.values,
        })),
      ]

      return {
        name: verb.name,
        aliases: verb.aliases ?? [],
        requiresSubject: verb.requiresSubject ?? verb.args.some(a => a.required),
        flags,
      }
    })

    grammar[entity.name] = {
      name: entity.name,
      shortcuts: entity.shortcuts,
      defaultVerb: entity.defaultVerb,
      verbs,
    }
  })

  COMMAND_DESCRIPTIONS['help'] = COMMAND_DESCRIPTIONS['help'] || 'Show available commands'
  COMMAND_DESCRIPTIONS['clear'] = COMMAND_DESCRIPTIONS['clear'] || 'Clear output history'

  return grammar
}

export function resolveEntityShortcut(shortcut: string): string | null {
  const normalized = shortcut.toLowerCase()

  for (const [entity, def] of Object.entries(GRAMMAR)) {
    if (entity === normalized || def.shortcuts.includes(normalized)) {
      return entity
    }
  }

  return null
}

export function resolveVerbAlias(entity: string, verb: string): string | null {
  const entityDef = GRAMMAR[entity]
  if (!entityDef) return null

  const normalized = verb.toLowerCase()

  for (const verbDef of entityDef.verbs) {
    if (verbDef.name === normalized || verbDef.aliases.includes(normalized)) {
      return verbDef.name
    }
  }

  return null
}

export function isValidVerb(entity: string, verb: string): boolean {
  return resolveVerbAlias(entity, verb) !== null
}

export function getVerbDefinition(entity: string, verb: string) {
  const entityDef = GRAMMAR[entity]
  if (!entityDef) return null

  const resolvedVerb = resolveVerbAlias(entity, verb)
  if (!resolvedVerb) return null

  return entityDef.verbs.find(v => v.name === resolvedVerb) || null
}

export function getEntities(): string[] {
  return Object.keys(GRAMMAR)
}

export function getVerbs(entity: string): string[] {
  const entityDef = GRAMMAR[entity]
  if (!entityDef) return []
  return entityDef.verbs.map(v => v.name)
}

export function getCommandExample(entity: string, verb: string): string {
  const key = `${entity}.${verb}`
  return COMMAND_EXAMPLES[key] || ''
}
