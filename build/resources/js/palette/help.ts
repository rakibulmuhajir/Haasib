import { GRAMMAR } from './grammar'

/**
 * Get help text for a topic
 */
export function getHelp(topic?: string): string {
  if (!topic) {
    return getGeneralHelp()
  }

  const topicLower = topic.toLowerCase()

  // Help for specific entity
  if (GRAMMAR[topicLower]) {
    return getEntityHelp(topicLower)
  }

  // Help for entity.verb
  if (topicLower.includes('.')) {
    const [entity, verb] = topicLower.split('.')
    if (GRAMMAR[entity]) {
      return getVerbHelp(entity, verb)
    }
  }

  // Try to find entity by shortcut
  for (const [entityName, entityDef] of Object.entries(GRAMMAR)) {
    if (entityDef.shortcuts.includes(topicLower)) {
      return getEntityHelp(entityName)
    }
  }

  // Topic not found
  return `Unknown topic: ${topic}\n\nAvailable: ${Object.keys(GRAMMAR).join(', ')}, or type 'help' for overview.`
}

/**
 * General help - list all commands
 */
function getGeneralHelp(): string {
  const lines: string[] = [
    'COMMAND PALETTE',
    '═══════════════',
    '',
    'Usage: entity verb [arguments] [--flags]',
    '',
    'AVAILABLE ENTITIES:',
    '',
  ]

  for (const [entityName, entity] of Object.entries(GRAMMAR)) {
    const shortcuts = entity.shortcuts.length > 0 
      ? ` (${entity.shortcuts.join(', ')})` 
      : ''
    const verbs = entity.verbs.map(v => v.name).join(', ')
    lines.push(`  ${entityName}${shortcuts}`)
    lines.push(`    verbs: ${verbs}`)
    lines.push('')
  }

  lines.push('BUILT-IN COMMANDS:')
  lines.push('')
  lines.push('  help [topic]     Show help (e.g., help company)')
  lines.push('  clear            Clear output')
  lines.push('')
  lines.push('SHORTCUTS:')
  lines.push('')
  lines.push('  ↑/↓              Navigate history')
  lines.push('  Tab              Accept suggestion')
  lines.push('  Ctrl+L           Clear output')
  lines.push('  Ctrl+U           Clear input')
  lines.push('  Esc              Close palette')
  lines.push('')
  lines.push('EXAMPLES:')
  lines.push('')
  lines.push('  company.list                    List all companies')
  lines.push('  co.create "Acme Corp" USD       Create company')
  lines.push('  user.invite john@example.com    Invite user')
  lines.push('')
  lines.push('Type "help <entity>" for detailed help (e.g., help company)')

  return lines.join('\n')
}

/**
 * Help for a specific entity
 */
function getEntityHelp(entityName: string): string {
  const entity = GRAMMAR[entityName]
  if (!entity) return `Unknown entity: ${entityName}`

  const lines: string[] = [
    `${entityName.toUpperCase()}`,
    '═'.repeat(entityName.length),
    '',
  ]

  if (entity.shortcuts.length > 0) {
    lines.push(`Shortcuts: ${entity.shortcuts.join(', ')}`)
    lines.push('')
  }

  lines.push('COMMANDS:')
  lines.push('')

  for (const verb of entity.verbs) {
    const aliases = verb.aliases.length > 0 
      ? ` (aliases: ${verb.aliases.join(', ')})` 
      : ''
    lines.push(`  ${entityName} ${verb.name}${aliases}`)

    // Show flags
    if (verb.flags.length > 0) {
      for (const flag of verb.flags) {
        const required = flag.required ? ' (required)' : ''
        const shorthand = flag.shorthand ? ` (-${flag.shorthand})` : ''
        const defaultVal = flag.default !== undefined ? ` [default: ${flag.default}]` : ''
        lines.push(`    --${flag.name}${shorthand}: ${flag.type}${required}${defaultVal}`)
      }
    } else {
      lines.push('    (no flags)')
    }

    // Example using canonical entity and verb
    lines.push(`    Example: ${buildExample(entityName, verb)}`)
    lines.push('')
  }

  lines.push('EXAMPLES:')
  lines.push('')
  lines.push(...getEntityExamples(entityName))

  return lines.join('\n')
}

/**
 * Help for a specific verb
 */
function getVerbHelp(entityName: string, verbName: string): string {
  const entity = GRAMMAR[entityName]
  if (!entity) return `Unknown entity: ${entityName}`

  const verb = entity.verbs.find(v => 
    v.name === verbName || v.aliases.includes(verbName)
  )
  if (!verb) return `Unknown verb: ${entityName}.${verbName}`

  const lines: string[] = [
    `${entityName.toUpperCase()} ${verb.name.toUpperCase()}`,
    '═'.repeat(entityName.length + verb.name.length + 1),
    '',
  ]

  if (verb.aliases.length > 0) {
    lines.push(`Aliases: ${verb.aliases.join(', ')}`)
    lines.push('')
  }

  lines.push('FLAGS:')
  lines.push('')

  if (verb.flags.length > 0) {
    for (const flag of verb.flags) {
      const required = flag.required ? ' (required)' : ''
      const shorthand = flag.shorthand ? `, -${flag.shorthand}` : ''
      const defaultVal = flag.default !== undefined ? `\n      Default: ${flag.default}` : ''
      lines.push(`  --${flag.name}${shorthand}`)
      lines.push(`      Type: ${flag.type}${required}${defaultVal}`)
      lines.push('')
    }
  } else {
    lines.push('  (no flags)')
    lines.push('')
  }

  lines.push('EXAMPLES:')
  lines.push('')
  lines.push(`  ${buildExample(entityName, verb)}`)
  lines.push(...getVerbExamples(entityName, verb.name))

  return lines.join('\n')
}

/**
 * Get example commands for an entity
 */
function getEntityExamples(entityName: string): string[] {
  const examples: Record<string, string[]> = {
    company: [
      '  company list',
      '  company create "Acme Corp" USD',
      '  co create "My Company" USD --industry=tech',
      '  company switch acme-corp',
      '  company delete acme-corp',
    ],
    user: [
      '  user list',
      '  user invite john@example.com',
      '  user invite jane@example.com --role=admin',
      '  user assign-role john@example.com --role=accountant',
      '  user deactivate john@example.com',
    ],
    role: [
      '  role list',
      '  role assign --permission=invoice:create --role=accountant',
      '  role revoke --permission=invoice:delete --role=member',
    ],
  }

  return examples[entityName] || ['  (no examples available)']
}

/**
 * Get example commands for a specific verb
 */
function getVerbExamples(entityName: string, verbName: string): string[] {
  const key = `${entityName}.${verbName}`
  const examples: Record<string, string[]> = {
    'company.list': [
      '  company list',
      '  co list',
    ],
    'company.create': [
      '  company create "Acme Corp" USD',
      '  co create "My Company" CAD',
      '  company create --name="Big Corp" --currency=EUR --industry=finance',
    ],
    'company.switch': [
      '  company switch acme-corp',
      '  co switch my-company',
    ],
    'company.delete': [
      '  company delete acme-corp',
      '  co delete old-company',
    ],
    'user.list': [
      '  user list',
      '  u list',
    ],
    'user.invite': [
      '  user invite john@example.com',
      '  user invite jane@example.com --role=admin',
      '  u invite test@test.com --name="John Doe"',
    ],
    'user.assign-role': [
      '  user assign-role john@example.com --role=admin',
      '  user assign --email=jane@example.com --role=accountant',
    ],
    'user.deactivate': [
      '  user deactivate john@example.com',
      '  user disable --email=test@test.com',
    ],
    'role.list': [
      '  role list',
      '  r list',
    ],
  }

  return examples[key] || ['  (no examples available)']
}

function buildExample(
  entityName: string,
  verb: { name: string; flags: Array<{ name: string; required: boolean }>; requiresSubject: boolean }
): string {
  const base = `${entityName}.${verb.name}`
  const requiredFlags = verb.flags
    .filter(f => f.required)
    .map(f => `--${f.name}=<${f.name}>`)
    .join(' ')

  const subjectHint = verb.requiresSubject ? '<value>' : ''
  return [base, subjectHint, requiredFlags].filter(Boolean).join(' ').trim()
}
