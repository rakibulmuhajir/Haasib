/**
 * The blanks of a typed sentence and what Enter and Tab do to them. Pure: no Vue, no network.
 *
 *   pay ali 1000  ->  vendor "ali", amount "1000", account (optional, empty)
 */
import type { CatalogCommand, Option, ResolveResponse, SlotSpec, SlotState } from './types'
import { parseTemplate, type Match } from './sentence'

export interface Blank { name: string; spec: SlotSpec; text: string; picked: Option | null; state: SlotState | null }
export interface BlankSet { command: CatalogCommand; blanks: Blank[]; current: number; flagMissing: boolean; verb: string }

export type EnterOutcome = 'pick' | 'advance' | 'post' | 'open' | 'flag'

const withBlank = (s: BlankSet, i: number, change: Partial<Blank>): BlankSet => ({
  ...s,
  blanks: s.blanks.map((b, n) => (n === i ? { ...b, ...change } : b)),
})

export function fromMatch(m: Match): BlankSet {
  const blanks = m.command.slots.map((spec): Blank => ({
    name: spec.name,
    spec,
    text: m.slotTexts[spec.name] ?? '',
    picked: null,
    state: null,
  }))
  const empty = blanks.findIndex((b) => !b.spec.optional && b.text === '')
  return { command: m.command, blanks, current: empty >= 0 ? empty : Math.max(0, blanks.length - 1), flagMissing: false, verb: m.verbText }
}

/** New text means the old pick and the old answer no longer describe it. */
export function setText(s: BlankSet, i: number, text: string): BlankSet {
  if (!s.blanks[i] || s.blanks[i].text === text) return s
  return withBlank(s, i, { text, picked: null, state: null })
}

export function pick(s: BlankSet, i: number, o: Option): BlankSet {
  if (!s.blanks[i]) return s
  return withBlank(s, i, { picked: o, state: { state: 'resolved', value: o.value, label: o.label } })
}

export function applyResolve(s: BlankSet, r: ResolveResponse): BlankSet {
  if (r.status === 'error') return s
  if (r.status === 'pending') {
    return { ...s, blanks: s.blanks.map((b) => (r.slots[b.name] ? { ...b, state: r.slots[b.name] } : b)) }
  }
  // Ready: every blank with something in it is understood. The server's params carry the ids.
  return {
    ...s,
    flagMissing: false,
    blanks: s.blanks.map((b) => {
      if (b.state?.state === 'resolved' || (b.text === '' && !b.picked)) return b
      const param = r.params[b.name]
      const value = b.picked?.value ?? (typeof param === 'string' || typeof param === 'number' ? String(param) : b.text)
      return { ...b, state: { state: 'resolved', value, label: b.picked?.label ?? b.text } }
    }),
  }
}

/** The first required blank that is not resolved yet. */
export function nextRequired(s: BlankSet): number | null {
  const i = s.blanks.findIndex((b) => !b.spec.optional && b.state?.state !== 'resolved')
  return i >= 0 ? i : null
}

/** Through all blanks, wrapping at both ends. */
export function tab(s: BlankSet, dir: 1 | -1): BlankSet {
  const n = s.blanks.length
  if (n === 0) return s
  return { ...s, current: (s.current + dir + n) % n }
}

/** Marks what is missing and moves to it. */
export function flag(s: BlankSet): BlankSet {
  const i = nextRequired(s)
  return { ...s, flagMissing: true, current: i ?? s.current }
}

export function enterOutcome(s: BlankSet, ready: boolean, choicesOpen: boolean): EnterOutcome {
  if (choicesOpen) return 'pick'
  if (s.command.form_url) return 'open'
  if (ready) return s.command.query ? 'open' : 'post'
  const next = nextRequired(s)
  if (next !== null && (s.blanks[s.current]?.text ?? '') !== '') return 'advance'
  return 'flag'
}

const RELATIVE_DATE_WORD = /^(today|yesterday|tomorrow)$/i

/**
 * The line these blanks spell, built without re-splitting: the verb, then each filled blank with
 * the anchor word before it. A blank with nothing in it leaves its anchor out ("on today" is just "today").
 */
export function lineOf(s: BlankSet): string {
  const out: string[] = []
  let anchors: string[] = []
  let seenSlot = false
  for (const t of parseTemplate(s.command.sentence)) {
    if (t.type === 'word') {
      if (seenSlot) anchors.push(t.text)
      else out.push(t.text)
      continue
    }
    seenSlot = true
    const blank = s.blanks.find((b) => b.name === t.name)
    const value = (blank?.text ?? '').trim()
    const bare = blank?.spec.kind === 'date' && RELATIVE_DATE_WORD.test(value)
    if (value !== '') out.push(...(bare ? [] : anchors), value)
    anchors = []
  }
  return out.join(' ')
}

/** The sentence view starts once the matched verb is followed by a space: "pay" alone may be "payroll". */
export function showsSentence(s: BlankSet | null, rawText: string, blankMode: boolean): boolean {
  if (!s) return false
  if (blankMode) return true
  const line = rawText.trimStart().toLowerCase()
  const verb = s.verb.trim().toLowerCase()
  return verb !== '' && line.startsWith(verb) && /\s/.test(line.charAt(verb.length))
}
