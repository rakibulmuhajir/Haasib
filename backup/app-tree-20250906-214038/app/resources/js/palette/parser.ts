// resources/js/palette/parser.ts
import type { EntityDef, VerbDef } from '@/palette/entities'

export type Parsed = {
  entityId: string
  verbId: string
  params: Record<string, any>
  confidence: number
}

const EMAIL_RE = /[^\s]+@[^\s]+\.[^\s]+/

function norm(s: string) { return s.trim().toLowerCase() }
function stripQuotes(s: string) {
  if (!s) return s
  if ((s.startsWith('"') && s.endsWith('"')) || (s.startsWith("'") && s.endsWith("'"))) {
    return s.slice(1, -1)
  }
  return s
}

function findEntity(entities: EntityDef[], token: string): EntityDef | null {
  const t = norm(token)
  for (const e of entities) {
    if (norm(e.id) === t) return e
    if ((e.aliases || []).some(a => norm(a) === t)) return e
  }
  return null
}

function findVerb(e: EntityDef, token: string): VerbDef | null {
  const t = norm(token)
  for (const v of e.verbs) {
    if (norm(v.id) === t || norm(v.label) === t) return v
    if ((v as any).aliases && (v as any).aliases!.some((a: string) => norm(a) === t)) return v
  }
  return null
}

function parseFlags(words: string[]): { map: Record<string, string>, consumed: Set<number> } {
  const map: Record<string, string> = {}
  const consumed = new Set<number>()
  const isFlagToken = (tok: string) => (tok.startsWith('--') && tok.length > 2) || (tok.startsWith('-') && tok.length > 1)
  for (let i = 0; i < words.length; i++) {
    const w = words[i]
    if (!isFlagToken(w)) continue
    const isLong = w.startsWith('--')
    const nameAndMaybeVal = isLong ? w.slice(2) : w.slice(1)
    const eqIdx = nameAndMaybeVal.indexOf('=')
    let name = nameAndMaybeVal
    let val: string | null = null
    if (eqIdx !== -1) {
      name = nameAndMaybeVal.slice(0, eqIdx)
      val = nameAndMaybeVal.slice(eqIdx + 1)
    } else if (i + 1 < words.length && !isFlagToken(words[i + 1])) {
      val = words[i + 1]
      consumed.add(i + 1)
    }
    consumed.add(i)
    if (name) {
      if (val !== null) map[name] = val
      else map[name] = 'true'
    }
  }
  return { map, consumed }
}

function tokenize(input: string): string[] {
  const tokens: string[] = []
  let cur = ''
  let inQuote = false
  for (let i = 0; i < input.length; i++) {
    const ch = input[i]
    if (ch === '"') { inQuote = !inQuote; continue }
    if (!inQuote && /\s/.test(ch)) { if (cur) { tokens.push(cur); cur = '' } }
    else cur += ch
  }
  if (cur) tokens.push(cur)
  return tokens
}

// Very small set of patterns targeting current verbs
export function parseCommand(input: string, entities: EntityDef[]): Parsed | null {
  const raw = input.trim()
  if (!raw) return null
  const words = tokenize(raw)
  if (words.length === 0) return null

  // Flags are optional and can appear anywhere; collect them
  const { map: flags, consumed } = parseFlags(words)

  // Try patterns: "<entity> <verb> ..." or "<verb> <entity> ..."
  const firstEntity = findEntity(entities, words[0])
  // Prefer an explicit entity specified as the second token when the first token is a verb
  let firstVerbEntity: EntityDef | null = null
  const possible = entities.filter(e => !!findVerb(e, words[0]))
  if (possible.length > 0) {
    const secondAsEntity = words[1] ? findEntity(entities, words[1]) : null
    // Heuristic: if the command contains an email and 'user' is a valid target for the verb, prefer 'user'
    const anyEmail = words.join(' ').match(EMAIL_RE)
    const possibleIds = new Set(possible.map(e => e.id))
    if (anyEmail && possibleIds.has('user')) {
      firstVerbEntity = possible.find(e => e.id === 'user') || possible[0]
    } else if (secondAsEntity && possibleIds.has(secondAsEntity.id)) {
      firstVerbEntity = secondAsEntity
    } else {
      firstVerbEntity = possible[0]
    }
  }

  // Helper to build Parsed using an entity + verb ids
  const build = (e: EntityDef, v: VerbDef, params: Record<string, any>, confidence = 0.7): Parsed => ({
    entityId: e.id, verbId: v.id, params, confidence,
  })

  // Heuristics for subjects
  const subjectTail = (from: number) => {
    const out: string[] = []
    for (let i = from; i < words.length; i++) {
      if (!consumed.has(i)) out.push(words[i])
    }
    return out.join(' ').trim()
  }
  const subjectOne = (i: number) => (words[i] || '').trim()

  // Pattern A: entity verb subject
  if (firstEntity) {
    const e = firstEntity
    const v = words[1] ? findVerb(e, words[1]) : null
    if (v) {
      // company create Acme
      if (e.id === 'company' && v.id === 'create') {
        const name = flags.name || subjectTail(2)
        const p: any = {}
        if (name) p.name = name
        if (flags.base_currency) p.base_currency = flags.base_currency
        if (flags.language) p.language = flags.language
        if (flags.locale) p.locale = flags.locale
        if (Object.keys(p).length) return build(e, v, p, 0.9)
      }
      if (e.id === 'company' && v.id === 'delete') {
        const company = flags.company || subjectTail(2)
        if (company) return build(e, v, { company }, 0.85)
      }
      if (e.id === 'user' && v.id === 'create') {
        // user create Jane jane@example.com
        const rest = subjectTail(2)
        const email = flags.email || (rest.match(EMAIL_RE)?.[0] ?? '')
        const name = flags.name || rest.replace(EMAIL_RE, '').trim()
        const p: any = {}
        if (name) p.name = name
        if (email) p.email = email
        if (flags.password) p.password = flags.password
        if (flags.password_confirm) p.password_confirm = flags.password_confirm
        if (Object.keys(p).length) return build(e, v, p, 0.85)
      }
      if (e.id === 'user' && v.id === 'delete') {
        const target = flags.email || subjectTail(2)
        if (target) return build(e, v, { email: target }, 0.85)
      }
      if (e.id === 'company' && v.id === 'assign') {
        // company assign jane@example.com to Acme as admin
        const rest = subjectTail(2)
        const email = flags.email || (rest.match(EMAIL_RE)?.[0] ?? '')
        let role = flags.role || ''
        let company = flags.company || ''
        const lower = rest.toLowerCase()
        const toIdx = lower.indexOf(' to ')
        const forIdx = lower.indexOf(' for ')
        const asIdx = lower.indexOf(' as ')
        if (toIdx !== -1) {
          const afterTo = rest.slice(toIdx + 4)
          company = asIdx !== -1 ? afterTo.slice(0, asIdx - toIdx - 4).trim() : afterTo.trim()
        } else if (forIdx !== -1) {
          const afterFor = rest.slice(forIdx + 5)
          company = asIdx !== -1 ? afterFor.slice(0, asIdx - forIdx - 5).trim() : afterFor.trim()
        }
        if (asIdx !== -1) role = rest.slice(asIdx + 4).trim()
        company = stripQuotes(company)
        role = stripQuotes(role)
        const p: any = {}
        if (email) p.email = email
        if (company) p.company = company
        if (role) p.role = role
        if (Object.keys(p).length) return build(e, v, p, 0.8)
      }
      if (e.id === 'company' && v.id === 'unassign') {
        // company unassign jane@example.com from Acme
        const rest = subjectTail(2)
        const email = flags.email || (rest.match(EMAIL_RE)?.[0] ?? '')
        let company = flags.company || ''
        const lower = rest.toLowerCase()
        const fromIdx = lower.indexOf(' from ')
        if (fromIdx !== -1) company = rest.slice(fromIdx + 6).trim()
        company = stripQuotes(company)
        const p: any = {}
        if (email) p.email = email
        if (company) p.company = company
        if (Object.keys(p).length) return build(e, v, p, 0.8)
      }
    }
  }

  // Pattern B: verb entity subject
  if (firstVerbEntity) {
    const e = firstVerbEntity
    const v = findVerb(e, words[0])!
    if (e.id === 'company' && v.id === 'create') {
      const name = flags.name || subjectTail(1).replace(/^company\s+/i, '')
      const p: any = {}
      if (name) p.name = name
      if (flags.base_currency) p.base_currency = flags.base_currency
      if (flags.language) p.language = flags.language
      if (flags.locale) p.locale = flags.locale
      if (Object.keys(p).length) return build(e, v, p, 0.85)
    }
    if (e.id === 'company' && v.id === 'delete') {
      const company = flags.company || subjectTail(1).replace(/^company\s+/i, '')
      if (company) return build(e, v, { company }, 0.8)
    }
    if (e.id === 'user' && v.id === 'create') {
      const rest = subjectTail(1).replace(/^user\s+/i, '')
      const email = flags.email || (rest.match(EMAIL_RE)?.[0] ?? '')
      const name = flags.name || rest.replace(EMAIL_RE, '').trim()
      const p: any = {}
      if (name) p.name = name
      if (email) p.email = email
      if (flags.password) p.password = flags.password
      if (flags.password_confirm) p.password_confirm = flags.password_confirm
      if (Object.keys(p).length) return build(e, v, p, 0.8)
    }
    if (e.id === 'user' && v.id === 'delete') {
      const target = flags.email || subjectTail(1).replace(/^user\s+/i, '')
      if (target) return build(e, v, { email: target }, 0.8)
    }
  }

  // Pattern C: terse assign/unassign without explicit entity
  const w0 = norm(words[0])
  if (['assign','invite'].includes(w0)) {
    const rest = subjectTail(1)
    const email = flags.email || (rest.match(EMAIL_RE)?.[0] ?? '')
    let role = flags.role || ''
    let company = flags.company || ''
    const lower = rest.toLowerCase()
    const toIdx = lower.indexOf(' to ')
    const forIdx = lower.indexOf(' for ')
    const asIdx = lower.indexOf(' as ')
    if (toIdx !== -1) {
      const afterTo = rest.slice(toIdx + 4)
      company = asIdx !== -1 ? afterTo.slice(0, asIdx - toIdx - 4).trim() : afterTo.trim()
    } else if (forIdx !== -1) {
      const afterFor = rest.slice(forIdx + 5)
      company = asIdx !== -1 ? afterFor.slice(0, asIdx - forIdx - 5).trim() : afterFor.trim()
    }
    if (asIdx !== -1) role = rest.slice(asIdx + 4).trim()
    company = stripQuotes(company)
    role = stripQuotes(role)
    const e = entities.find(x => x.id === 'company');
    const v = e?.verbs.find(x => x.id === 'assign');
    if (e && v && (email || company)) return build(e, v, { ...(email?{email}:{}) , ...(company?{company}:{}) , ...(role?{role}:{}) }, 0.75)
  }
  if (['unassign','remove'].includes(w0)) {
    const rest = subjectTail(1)
    const email = flags.email || (rest.match(EMAIL_RE)?.[0] ?? '')
    let company = flags.company || ''
    const fromIdx = rest.toLowerCase().indexOf(' from ')
    if (fromIdx !== -1) company = rest.slice(fromIdx + 6).trim()
    const e = entities.find(x => x.id === 'company');
    const v = e?.verbs.find(x => x.id === 'unassign');
    if (e && v && (email || company)) return build(e, v, { ...(email?{email}:{}) , ...(company?{company}:{}) }, 0.75)
  }

  return null
}
