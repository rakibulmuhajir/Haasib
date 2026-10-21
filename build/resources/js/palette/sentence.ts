/**
 * Sentence matcher: splits typed text into slot texts using the catalog's sentence templates.
 *
 *   pay ali 1000 from cash   ->  Pay {vendor} {amount} from {account?} on {date?}
 *                                vendor "ali", amount "1000", account "cash"
 *
 * It only splits; names, amounts and dates are read by the server. Pure: no Vue, no network.
 */
import type { CatalogCommand } from './types'

export type Token = { type: 'word'; text: string } | { type: 'slot'; name: string; optional: boolean }

export interface Match { command: CatalogCommand; verbText: string; slotTexts: Record<string, string> }

/** "Pay {vendor} {amount} from {account?}" -> words and slots. */
export function parseTemplate(sentence: string): Token[] {
  return sentence.split(/\s+/).filter(Boolean).map((part): Token => {
    const slot = /^\{(\w+)(\?)?\}$/.exec(part)
    return slot ? { type: 'slot', name: slot[1], optional: slot[2] === '?' } : { type: 'word', text: part }
  })
}

// ---------------------------------------------------------------------------
// Dates (same shapes quick-entry reads, but found anywhere in the text)
// ---------------------------------------------------------------------------

const MONTHS = new Set([
  'jan', 'january', 'feb', 'february', 'mar', 'march', 'apr', 'april', 'may', 'jun', 'june',
  'jul', 'july', 'aug', 'august', 'sep', 'sept', 'september', 'oct', 'october', 'nov', 'november', 'dec', 'december',
])

const RELATIVE_DATE = /(?:^|\s)(today|yesterday|tomorrow)(?=\s|$)/gi
// "on 14 oct", "on 14 oct 2026", "on 14/10", "on 14-10-2026", "on 2026-10-14"
const ON_DATE = /(?:^|\s)on\s+(\d{4}-\d{1,2}-\d{1,2}|\d{1,2}[/-]\d{1,2}(?:[/-]\d{2,4})?|\d{1,2}\s+[a-z]{3,9}(?:\s+\d{2,4})?(?!\s+\d))(?=\s|$)/gi

/** A word that is not a month ("on 3 tea") or a 3-digit year is part of the text, not a date. */
function isDatePhrase(phrase: string): boolean {
  const named = /^\d{1,2}\s+([a-z]+)(?:\s+(\d+))?$/i.exec(phrase)
  if (named) return MONTHS.has(named[1].toLowerCase()) && (named[2] === undefined || [2, 4].includes(named[2].length))
  const numeric = /^\d{1,2}[/-]\d{1,2}[/-](\d+)$/.exec(phrase)
  if (numeric) return [2, 4].includes(numeric[1].length)
  return true
}

/** Takes the last date phrase out of the text. */
function extractDate(text: string): { text: string; date?: string } {
  let found: { index: number; length: number; phrase: string } | null = null
  for (const m of text.matchAll(RELATIVE_DATE)) {
    found = { index: m.index ?? 0, length: m[0].length, phrase: m[1] }
  }
  for (const m of text.matchAll(ON_DATE)) {
    const index = m.index ?? 0
    if (isDatePhrase(m[1]) && (!found || index > found.index)) found = { index, length: m[0].length, phrase: m[1] }
  }
  if (!found) return { text }
  return { text: `${text.slice(0, found.index)} ${text.slice(found.index + found.length)}`, date: found.phrase.replace(/\s+/g, ' ') }
}

// ---------------------------------------------------------------------------
// Matching
// ---------------------------------------------------------------------------

const AMOUNT_SHAPE = /^\d[\d,]*(\.\d+)?\s*(k|lakhs?|lacs?)?%?$/
const CONNECTORS = new Set(['to', 'from', 'into', 'in', 'for', 'on'])
// Words the server does not read as amounts. Kept with the amount so "50 thousand" is refused, not posted as 50.
const MAGNITUDE = new Set(['hundred', 'hundreds', 'thousand', 'thousands', 'million', 'millions', 'billion', 'billions', 'crore', 'crores'])
// What follows an entity in an entity command ("expense list"): never the start of a sentence.
const ENTITY_VERBS = new Set(['list', 'view', 'show', 'create', 'new', 'update', 'edit', 'delete'])

const normalise = (text: string) => text.trim().toLowerCase().replace(/\s+/g, ' ')

/**
 * The first or last amount-shaped run of one or two words: [start, end) in `words`, or null.
 * A magnitude word right after it joins the run, so the server sees "50 thousand" whole.
 */
function amountRun(words: string[], from: 'first' | 'last'): [number, number] | null {
  const starts = words.map((_, i) => i)
  if (from === 'last') starts.reverse()
  for (const start of starts) {
    for (const len of [2, 1]) {
      if (start + len <= words.length && AMOUNT_SHAPE.test(words.slice(start, start + len).join(' '))) {
        const end = start + len
        return MAGNITUDE.has(words[end] ?? '') ? [start, end + 1] : [start, end]
      }
    }
  }
  return null
}

/** "to karachi office" -> "karachi office". Only one connector goes, and never the whole text. */
function clean(words: string[]): string {
  const rest = words.length > 1 && CONNECTORS.has(words[0]) ? words.slice(1) : words
  return rest.join(' ').trim()
}

interface SlotToken { name: string; amount: boolean }

/** Shares the words between two anchors among the slots that sit there. */
function fill(slots: SlotToken[], span: string[], out: Record<string, string>): void {
  if (slots.length === 0 || span.length === 0) return
  const put = (slot: SlotToken | undefined, words: string[]) => {
    const text = slot ? clean(words) : ''
    if (slot && text !== '') out[slot.name] = text
  }
  if (slots.length === 1) return put(slots[0], span)

  const at = slots.findIndex((s) => s.amount)
  // Slots next to each other with no anchor. With a slot before the amount (Pay {vendor} {amount})
  // the amount is the last amount-shaped run, so a name that contains a number ("7 star traders")
  // stays whole. With the amount first (Sale {amount} {note}) it is the first run, so the number
  // in "12000 2 bags" stays in the note.
  const run = at >= 0 ? amountRun(span, at > 0 ? 'last' : 'first') : null
  if (at < 0 || !run) {
    // Nothing to tell the slots apart: the first one that is not an amount takes the lot.
    return put(slots.find((s) => !s.amount) ?? slots[0], span)
  }
  out[slots[at].name] = span.slice(run[0], run[1]).join(' ')
  const before = span.slice(0, run[0])
  const after = span.slice(run[1])
  const left = slots[at - 1]
  const right = slots[at + 1]
  if (left && right && before.length > 0) {
    put(left, before)
    put(right, after)
  } else {
    // Only one side has a slot, or nothing came first: the text goes to the nearest slot, in typed order.
    put(left ?? right, [...before, ...after])
  }
}

/** The command a typed line starts with, longest name or alias first, whole words only. */
function findVerb(text: string, commands: CatalogCommand[]): { command: CatalogCommand; verb: string } | null {
  let best: { command: CatalogCommand; verb: string } | null = null
  for (const command of commands) {
    for (const raw of [command.name, ...command.aliases]) {
      const verb = normalise(raw)
      if (verb === '' || !(text === verb || text.startsWith(`${verb} `))) continue
      if (!best || verb.length > best.verb.length) best = { command, verb }
    }
  }
  return best
}

/**
 * "expense create" is the entity command, not Spend with "create" in it: a generic command whose
 * label is these two words (either order) wins, and an entity verb alone after the name matches nothing.
 */
function entityCommand(line: string, found: { command: CatalogCommand; verb: string }, commands: CatalogCommand[]) {
  if (found.command.generic) return found
  const next = line.slice(found.verb.length).trim().split(' ')[0] ?? ''
  if (next === '') return found
  const pair = [`${found.verb} ${next}`, `${next} ${found.verb}`]
  const generic = commands.find((c) => c.generic && c.aliases.some((a) => pair.includes(normalise(a))))
  if (generic) return { command: generic, verb: `${found.verb} ${next}` }
  return ENTITY_VERBS.has(next) && line === pair[0] ? null : found
}

export function matchCommand(text: string, commands: CatalogCommand[]): Match | null {
  const line = normalise(text)
  const verbFound = findVerb(line, commands)
  const found = verbFound && entityCommand(line, verbFound, commands)
  if (!found) return null
  const { command, verb } = found

  let rest = line.slice(verb.length)
  const slotTexts: Record<string, string> = {}

  // The date goes first, so a leftover "on" is a slot anchor ("spend 2500 on tea yesterday").
  const dateSlot = command.slots.find((s) => s.kind === 'date')
  if (dateSlot) {
    const taken = extractDate(rest)
    rest = taken.text
    if (taken.date) slotTexts[dateSlot.name] = taken.date
  }

  // Template after the verb, without the date slot or the "on" that led to it.
  let tokens = parseTemplate(command.sentence)
  while (tokens[0]?.type === 'word') tokens = tokens.slice(1)
  if (dateSlot) {
    const i = tokens.findIndex((t) => t.type === 'slot' && t.name === dateSlot.name)
    if (i >= 0) tokens = tokens.filter((t, n) => n !== i && !(n === i - 1 && t.type === 'word'))
  }

  const kinds = new Map(command.slots.map((s) => [s.name, s.kind]))
  const words = rest.split(' ').filter(Boolean)

  // An anchor that is not in the text drops out, and the slots on each side share one span.
  let slots: SlotToken[] = []
  let start = 0
  let cursor = 0
  for (const token of tokens) {
    if (token.type === 'slot') {
      slots.push({ name: token.name, amount: kinds.get(token.name) === 'amount' })
      continue
    }
    const at = words.indexOf(token.text.toLowerCase(), cursor)
    if (at < 0) continue
    fill(slots, words.slice(start, at), slotTexts)
    slots = []
    start = cursor = at + 1
  }
  fill(slots, words.slice(start), slotTexts)

  return { command, verbText: verb, slotTexts }
}

// ---------------------------------------------------------------------------
// Suggestions
// ---------------------------------------------------------------------------

/** Highest score first; ties keep catalog order. */
function byScore(list: CatalogCommand[], scores: Record<string, number>): CatalogCommand[] {
  return list
    .map((command, i) => ({ command, i }))
    .sort((a, b) => (scores[b.command.name] ?? 0) - (scores[a.command.name] ?? 0) || a.i - b.i)
    .map((x) => x.command)
}

export function suggestCommands(text: string, commands: CatalogCommand[], scores: Record<string, number>): CatalogCommand[] {
  const line = normalise(text)
  const declared = commands.filter((c) => !c.generic)
  if (line === '') return byScore(declared, scores).slice(0, 8)

  const typed = line.split(' ')
  const verbs = (c: CatalogCommand) => [c.name, ...c.aliases].map(normalise)
  const labelWords = (c: CatalogCommand) => normalise(c.label).split(' ')

  const byVerb = declared.filter((c) => verbs(c).some((v) => v.startsWith(line) || line.startsWith(`${v} `)))
  const byLabel = declared.filter((c) => typed.every((w) => labelWords(c).some((l) => l.startsWith(w))))
  const generic = commands.filter((c) => c.generic && typed.every((w) => normalise(c.label).includes(w)))

  const seen = new Set<string>()
  const out: CatalogCommand[] = []
  for (const group of [byVerb, byLabel, generic]) {
    for (const c of byScore(group, scores)) {
      if (seen.has(c.name)) continue
      seen.add(c.name)
      out.push(c)
    }
  }
  return out.slice(0, 8)
}
