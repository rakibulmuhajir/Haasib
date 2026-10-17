/**
 * Quick entry: one typed line becomes one posting command.
 *
 *   deposit 50000 meezan            withdraw 20000
 *   transfer 100000 meezan to hbl   bank charge 500 meezan
 *   received 15000 from al noor     paid 8000 to karachi office
 *   expense 2500 tea                sale 12000
 *   deposit 5000 meezan yesterday   paid 8000 to karachi office on 14 oct
 *
 * parseQuickEntry only reads the line. resolveQuickEntry turns names into ids and fills
 * what was left out (the date, the default cash or bank account). Neither posts anything:
 * the server previews the result and, on Enter, posts it.
 *
 * Names are never guessed. A name that matches several records comes back as choices;
 * one that matches none is an error.
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

/** A date typed at the end of the line: a word relative to the company's today, or day-month[-year]. */
export type DateSpec =
  | { kind: 'relative'; word: 'today' | 'yesterday' | 'tomorrow' }
  | { kind: 'explicit'; day: number; month: number; year: number | null; text: string }

export type QuickEntry = (
  | { kind: 'deposit'; amount: number; bank: string | null }
  | { kind: 'withdraw'; amount: number; bank: string | null }
  | { kind: 'transfer'; amount: number; from: string; to: string }
  | { kind: 'charge'; amount: number; tail: string; note: string | null }
  | { kind: 'received'; amount: number; party: string; into: string | null }
  | { kind: 'paid'; amount: number; party: string; from: string | null }
  | { kind: 'expense'; amount: number; what: string; from: string | null }
  | { kind: 'sale'; amount: number; note: string | null }
) & {
  /** Present only when the line carried a date; otherwise the entry is for the company's today. */
  date?: DateSpec
}

export interface Account {
  id: string
  code: string
  name: string
}

export interface QuickDefaults {
  today: string
  currency: string
  default_bank_id: string | null
  default_cash_id: string | null
  bank_charges_account_id: string | null
  banks: Account[]
  cash: Account[]
  expense: Account[]
}

export interface Option {
  value: string
  label: string
  meta?: string
}

export interface OpenBill {
  id: string
  bill_number: string | null
  balance: number
}

export interface Lookups {
  defaults: QuickDefaults
  search: (entity: 'customer' | 'vendor', query: string) => Promise<Option[]>
  openBills: (vendorId: string) => Promise<OpenBill[]>
}

/** Where "Open" goes after posting, and which command (if any) takes it back. */
export type OpenKind = 'payment' | 'bill_payment' | 'journal' | 'redirect'

export interface ReadyEntry {
  status: 'ready'
  key: string
  params: Record<string, unknown>
  /** One line saying what will be booked. */
  summary: string
  /** Extra facts worth a glance (e.g. what stays on account). */
  notes: string[]
  open: OpenKind
  /** The command that reverses this one, when there is one. */
  undoKey: string | null
  /** The day it will be booked on (YYYY-MM-DD). */
  date: string
  /** That day for the preview header, e.g. `Thu 15 Oct 2026`. */
  dateLabel: string
}

export interface ChoicesEntry {
  status: 'choices'
  /** Which name was ambiguous, used to remember the pick. */
  field: string
  /** Shown above the list, e.g. `Customers matching "al"`. */
  prompt: string
  choices: Option[]
}

export interface ErrorEntry {
  status: 'error'
  message: string
}

export type Resolved = ReadyEntry | ChoicesEntry | ErrorEntry

/** Names the user already chose between, by field. */
export type Picks = Record<string, Option>

// ---------------------------------------------------------------------------
// Amounts
// ---------------------------------------------------------------------------

const MAX_AMOUNT = 999_999_999.99
const SUFFIX: Record<string, number> = { k: 1_000, lakh: 100_000, lakhs: 100_000, lac: 100_000, lacs: 100_000 }

/** Words that scale a number but are not supported: refuse the line rather than read just the digits. */
const MAGNITUDE_WORDS = /^(?:hundreds?|thousands?|millions?|billions?|crores?|cr|m|mn|bn|b|l|lk|lks)$/i

const PLAIN = /^\d+(?:\.\d+)?$/
// 50,000 / 1,234,567 (thousands) and 1,50,000 (lakhs): the grouping must be consistent.
const GROUPED_INTL = /^\d{1,3}(?:,\d{3})+(?:\.\d+)?$/
const GROUPED_LAKH = /^\d{1,2}(?:,\d{2})*,\d{3}(?:\.\d+)?$/

function numberPart(token: string): number | null {
  if (!PLAIN.test(token) && !GROUPED_INTL.test(token) && !GROUPED_LAKH.test(token)) return null
  return Number(token.replace(/,/g, ''))
}

/**
 * Reads an amount off the front of `text`. Returns the amount and what is left, or null
 * when the text does not start with an amount we understand exactly. Never rounds a figure
 * into a different one: more than two decimal places in the result is refused.
 */
export function parseAmount(text: string): { amount: number; rest: string } | null {
  const tokens = text.trim().split(/\s+/)
  const first = tokens[0] ?? ''
  if (first === '') return null

  let base: number | null = null
  let multiplier = 1
  let used = 1

  const attached = /^(.*?)(k|lakhs?|lacs?)$/i.exec(first)
  if (attached && attached[1] !== '') {
    base = numberPart(attached[1])
    multiplier = SUFFIX[attached[2].toLowerCase()]
  } else {
    base = numberPart(first)
    const next = (tokens[1] ?? '').toLowerCase()
    if (base !== null && Object.prototype.hasOwnProperty.call(SUFFIX, next)) {
      multiplier = SUFFIX[next]
      used = 2
    }
  }
  if (base === null) return null
  // "50 thousand" must not become 50 with a note. Only k, lakh and lac are understood.
  if (MAGNITUDE_WORDS.test(tokens[used] ?? '')) return null

  const cents = Math.round(base * multiplier * 100)
  if (Math.abs(base * multiplier * 100 - cents) > 1e-6) return null // 1.234 lakh is fine, 12.345 is not
  const amount = cents / 100
  if (!(amount > 0) || amount > MAX_AMOUNT) return null

  return { amount, rest: tokens.slice(used).join(' ') }
}

// ---------------------------------------------------------------------------
// Parsing
// ---------------------------------------------------------------------------

const VERBS: Array<{ kind: QuickEntry['kind']; re: RegExp; usage: string }> = [
  { kind: 'deposit', re: /^deposit\b\s*/i, usage: 'deposit 50000 [into bank]' },
  { kind: 'withdraw', re: /^withdraw\b\s*/i, usage: 'withdraw 20000 [from bank]' },
  { kind: 'transfer', re: /^transfer\b\s*/i, usage: 'transfer 100000 bank to bank' },
  { kind: 'charge', re: /^bank\s+charges?\b\s*/i, usage: 'bank charge 500 [bank] [for note]' },
  { kind: 'received', re: /^received\b\s*/i, usage: 'received 15000 from customer [into account]' },
  { kind: 'paid', re: /^paid\b\s*/i, usage: 'paid 8000 to supplier [from account]' },
  { kind: 'expense', re: /^expense\b\s*/i, usage: 'expense 2500 what [from account]' },
  { kind: 'sale', re: /^sale\b\s*/i, usage: 'sale 12000 [note]' },
]

function clean(text: string | undefined | null): string | null {
  const t = (text ?? '').trim()
  return t === '' ? null : t
}

// ---------------------------------------------------------------------------
// Dates
// ---------------------------------------------------------------------------

const MONTHS: Record<string, number> = {
  jan: 1, january: 1, feb: 2, february: 2, mar: 3, march: 3, apr: 4, april: 4, may: 5, jun: 6, june: 6,
  jul: 7, july: 7, aug: 8, august: 8, sep: 9, sept: 9, september: 9, oct: 10, october: 10,
  nov: 11, november: 11, dec: 12, december: 12,
}

const RELATIVE_DATE = /\s+(today|yesterday|tomorrow)$/i
// "on 14 oct", "on 14 oct 2026", "on 14/10", "on 14-10-2026", "on 2026-10-14"
const ON_DATE = /\s+on\s+(\d{1,2}\s+[a-z]{3,9}(?:\s+\d{2,4})?|\d{1,2}[/-]\d{1,2}(?:[/-]\d{2,4})?|\d{4}-\d{1,2}-\d{1,2})$/i

function yearOf(token: string | undefined): number | null | undefined {
  if (token === undefined) return null // no year typed
  if (token.length === 2) return 2000 + Number(token)
  if (token.length === 4) return Number(token)
  return undefined // 3 digits: not a year
}

function explicitDate(phrase: string): DateSpec | null {
  const text = phrase.trim().replace(/\s+/g, ' ')
  let day: number
  let month: number | undefined
  let year: number | null | undefined

  const isoForm = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(text)
  const numeric = /^(\d{1,2})[/-](\d{1,2})(?:[/-](\d{2,4}))?$/.exec(text)
  const named = /^(\d{1,2}) ([a-z]+)(?: (\d{2,4}))?$/i.exec(text)
  if (isoForm) {
    year = Number(isoForm[1]); month = Number(isoForm[2]); day = Number(isoForm[3])
  } else if (numeric) {
    day = Number(numeric[1]); month = Number(numeric[2]); year = yearOf(numeric[3])
  } else if (named) {
    day = Number(named[1]); month = MONTHS[named[2].toLowerCase()]; year = yearOf(named[3])
  } else {
    return null
  }
  // A word that is not a month ("on 3 tea") is part of the line, not a date.
  if (month === undefined || year === undefined) return null
  return { kind: 'explicit', day, month, year, text }
}

/** Takes a trailing date off the line. The rest is what the verbs read. */
function splitDate(line: string): { line: string; date?: DateSpec } {
  const rel = RELATIVE_DATE.exec(line)
  if (rel) {
    return { line: line.slice(0, rel.index), date: { kind: 'relative', word: rel[1].toLowerCase() as 'today' | 'yesterday' | 'tomorrow' } }
  }
  const on = ON_DATE.exec(line)
  if (on) {
    const date = explicitDate(on[1])
    if (date) return { line: line.slice(0, on.index), date }
  }
  return { line }
}

const isoDay = (y: number, m: number, d: number) => `${String(y).padStart(4, '0')}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`

function addDays(day: string, n: number): string {
  const [y, m, d] = day.split('-').map(Number)
  return new Date(Date.UTC(y, m - 1, d + n)).toISOString().slice(0, 10)
}

/**
 * The day a typed date means. Relative words count from the company's today (never the browser's
 * clock). With no year, the company's year is used, unless that lands more than six months ahead
 * (typing `on 28 dec` in January means last December).
 */
export function resolveDate(spec: DateSpec, today: string): { ok: true; date: string } | { ok: false; message: string } {
  if (spec.kind === 'relative') {
    const offset = spec.word === 'yesterday' ? -1 : spec.word === 'tomorrow' ? 1 : 0
    return { ok: true, date: addDays(today, offset) }
  }
  const valid = (y: number) => {
    const t = new Date(Date.UTC(y, spec.month - 1, spec.day))
    return spec.month >= 1 && spec.month <= 12 && t.getUTCFullYear() === y && t.getUTCMonth() === spec.month - 1 && t.getUTCDate() === spec.day
  }
  let year = spec.year ?? Number(today.slice(0, 4))
  if (!valid(year)) return { ok: false, message: `${spec.text} is not a date` }
  let date = isoDay(year, spec.month, spec.day)
  if (spec.year === null && date > addDays(today, 183) && valid(year - 1)) {
    year -= 1
    date = isoDay(year, spec.month, spec.day)
  }
  return { ok: true, date }
}

const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

/** `2026-10-15` as `Thu 15 Oct 2026`, without going through the browser's time zone. */
export function formatDay(day: string): string {
  const [y, m, d] = day.split('-').map(Number)
  const weekday = new Date(Date.UTC(y, m - 1, d)).getUTCDay()
  return `${DAY_NAMES[weekday]} ${d} ${MONTH_NAMES[m - 1]} ${y}`
}

/** Reads one line. Null when it is not a quick entry (or the amount is not one we accept). */
export function parseQuickEntry(text: string): QuickEntry | null {
  const { line, date } = splitDate(text.trim().replace(/\s+/g, ' '))
  const entry = parseLine(line)
  return entry && date ? { ...entry, date } : entry
}

function parseLine(line: string): QuickEntry | null {
  const verb = VERBS.find((v) => v.re.test(line))
  if (!verb) return null

  const parsed = parseAmount(line.replace(verb.re, ''))
  if (!parsed) return null
  const { amount } = parsed
  const tail = parsed.rest

  switch (verb.kind) {
    case 'deposit':
      return { kind: 'deposit', amount, bank: clean(tail.replace(/^(?:into|in|to)\b\s*/i, '')) }
    case 'withdraw':
      return { kind: 'withdraw', amount, bank: clean(tail.replace(/^from\b\s*/i, '')) }
    case 'transfer': {
      const m = /^(?:from\s+)?(.+?)\s+to\s+(.+)$/i.exec(tail)
      const from = clean(m?.[1])
      const to = clean(m?.[2])
      return from && to ? { kind: 'transfer', amount, from, to } : null
    }
    case 'charge': {
      const forNote = /^for\b\s*(.*)$/i.exec(tail)
      if (forNote) return { kind: 'charge', amount, tail: '', note: clean(forNote[1]) }
      return { kind: 'charge', amount, tail, note: null }
    }
    case 'received': {
      const m = /^from\s+(.+?)(?:\s+into\s+(.+))?$/i.exec(tail)
      const party = clean(m?.[1])
      return party ? { kind: 'received', amount, party, into: clean(m?.[2]) } : null
    }
    case 'paid': {
      const m = /^to\s+(.+?)(?:\s+from\s+(.+))?$/i.exec(tail)
      const party = clean(m?.[1])
      return party ? { kind: 'paid', amount, party, from: clean(m?.[2]) } : null
    }
    case 'expense': {
      const m = /^(.+?)(?:\s+from\s+(.+))?$/i.exec(tail)
      const what = clean(m?.[1])
      return what ? { kind: 'expense', amount, what, from: clean(m?.[2]) } : null
    }
    case 'sale':
      return { kind: 'sale', amount, note: clean(tail) }
  }
}

/** Usage line to show once a verb is typed but the rest is not (yet) a complete entry. */
export function quickEntryHint(text: string): string | null {
  const line = text.trim().replace(/\s+/g, ' ')
  const verb = VERBS.find((v) => v.re.test(line))
  if (!verb || parseQuickEntry(line)) return null
  // `expense create` is a regular command, not a quick entry: only hint while an amount is expected or being typed.
  const afterVerb = line.replace(verb.re, '')
  if (afterVerb !== '' && !/^\d/.test(afterVerb)) return null
  return verb.usage
}

/**
 * True once a quick-entry verb is followed by something starting like an amount, even if the
 * line is not complete yet. While it is, the palette must leave the line alone (no entity
 * suggestions, no rewriting of the first word).
 */
export function looksLikeQuickEntry(text: string): boolean {
  const line = text.trim().replace(/\s+/g, ' ')
  const verb = VERBS.find((v) => v.re.test(line))
  return !!verb && /^\d/.test(line.replace(verb.re, ''))
}

/** First words that only ever start a quick entry; they must not be auto-completed into an entity. */
export function startsQuickEntryWord(firstWord: string): boolean {
  return ['deposit', 'withdraw', 'transfer', 'received', 'paid', 'bank'].includes(firstWord.toLowerCase())
}

export function isQuickEntry(text: string): boolean {
  return parseQuickEntry(text) !== null
}

// ---------------------------------------------------------------------------
// Names
// ---------------------------------------------------------------------------

const norm = (s: string) => s.toLowerCase().trim()

/** Accounts whose code or name contains every word typed. An exact name or code wins outright. */
export function matchAccounts(list: Account[], query: string): Account[] {
  const q = norm(query)
  if (q === '') return []
  const exact = list.filter((a) => norm(a.name) === q || norm(a.code) === q)
  if (exact.length === 1) return exact
  const words = q.split(/\s+/)
  return list.filter((a) => {
    const hay = norm(`${a.code} ${a.name}`)
    return words.every((w) => hay.includes(w))
  })
}

function accountOption(a: Account): Option {
  return { value: a.id, label: a.name, meta: a.code }
}

type Picked<T> = { ok: true; value: T } | { ok: false; result: ChoicesEntry | ErrorEntry }

function pickAccount(
  field: string,
  noun: string,
  list: Account[],
  query: string,
  picks: Picks,
  /** When nothing matches, offer the whole list instead of an error (a free-text description is not a name). */
  offerAllWhenNone = false,
): Picked<Account> {
  const earlier = picks[field]
  if (earlier) {
    const hit = list.find((a) => a.id === earlier.value)
    if (hit) return { ok: true, value: hit }
  }
  const matches = matchAccounts(list, query)
  if (matches.length === 1) return { ok: true, value: matches[0] }
  if (matches.length === 0) {
    if (offerAllWhenNone && list.length > 0) {
      return {
        ok: false,
        result: { status: 'choices', field, prompt: `No ${noun} named "${query}". Choose one`, choices: list.map(accountOption) },
      }
    }
    return { ok: false, result: { status: 'error', message: `No ${noun} matches "${query}"` } }
  }
  return {
    ok: false,
    result: { status: 'choices', field, prompt: `${noun[0].toUpperCase()}${noun.slice(1)}s matching "${query}"`, choices: matches.map(accountOption) },
  }
}

async function pickParty(
  field: 'customer' | 'vendor',
  query: string,
  lookups: Lookups,
  picks: Picks,
): Promise<Picked<Option>> {
  if (picks[field]) return { ok: true, value: picks[field] }
  const found = await lookups.search(field, query)
  const exact = found.filter((o) => norm(o.label) === norm(query))
  if (exact.length === 1) return { ok: true, value: exact[0] }
  if (found.length === 1) return { ok: true, value: found[0] }
  if (found.length === 0) return { ok: false, result: { status: 'error', message: `No ${field} matches "${query}"` } }
  return {
    ok: false,
    result: { status: 'choices', field, prompt: `${field === 'customer' ? 'Customers' : 'Suppliers'} matching "${query}"`, choices: found },
  }
}

const fmt = (n: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(n)

// ---------------------------------------------------------------------------
// Resolving
// ---------------------------------------------------------------------------

function defaultCash(d: QuickDefaults): Account | null {
  return d.cash.find((a) => a.id === d.default_cash_id) ?? d.cash[0] ?? null
}

function defaultBank(d: QuickDefaults): Account | null {
  return d.banks.find((a) => a.id === d.default_bank_id) ?? d.banks[0] ?? null
}

/** Cash or bank account the money moves through when the line names none: the cash drawer first. */
function defaultSettlement(d: QuickDefaults): Account | null {
  return defaultCash(d) ?? defaultBank(d)
}

/** Names the bank at the front of a charge line; whatever follows is the note. */
function splitChargeTail(tail: string, banks: Account[]): { bank: string; note: string | null } | null {
  const words = tail.split(' ').filter(Boolean)
  for (let n = words.length; n >= 1; n--) {
    const candidate = words.slice(0, n).join(' ')
    if (matchAccounts(banks, candidate).length > 0) {
      return { bank: candidate, note: clean(words.slice(n).join(' ')) }
    }
  }
  return null
}

export async function resolveQuickEntry(entry: QuickEntry, lookups: Lookups, picks: Picks = {}): Promise<Resolved> {
  const d = lookups.defaults
  const money = `${d.currency} ${fmt(entry.amount)}`

  let day = d.today
  if (entry.date) {
    const typed = resolveDate(entry.date, d.today)
    if (!typed.ok) return { status: 'error', message: typed.message }
    day = typed.date
  }
  const base = { date: day }
  const when = { date: day, dateLabel: formatDay(day) }

  // A named bank, or the company default when none was typed.
  const bankFor = (field: string, name: string | null): Picked<Account> => {
    if (name) return pickAccount(field, 'bank', d.banks, name, picks)
    const fallback = defaultBank(d)
    return fallback
      ? { ok: true, value: fallback }
      : { ok: false, result: { status: 'error', message: 'Add a bank account first' } }
  }
  const settlementFor = (field: string, name: string | null): Picked<Account> => {
    if (name) return pickAccount(field, 'account', [...d.cash, ...d.banks], name, picks)
    const fallback = defaultSettlement(d)
    return fallback
      ? { ok: true, value: fallback }
      : { ok: false, result: { status: 'error', message: 'Add a cash or bank account first' } }
  }
  const cashDrawer = (): Picked<Account> => {
    const cash = defaultCash(d)
    return cash ? { ok: true, value: cash } : { ok: false, result: { status: 'error', message: 'Add a cash account first' } }
  }

  switch (entry.kind) {
    case 'deposit':
    case 'withdraw': {
      const bank = bankFor('bank', entry.bank)
      if (!bank.ok) return bank.result
      const cash = cashDrawer()
      if (!cash.ok) return cash.result
      const deposit = entry.kind === 'deposit'
      return {
        status: 'ready',
        key: 'bank_transaction.create',
        params: {
          kind: deposit ? 'deposit' : 'withdrawal', ...base, amount: entry.amount,
          bank_account_id: bank.value.id, cash_account_id: cash.value.id,
        },
        summary: deposit
          ? `Deposit ${money}: ${cash.value.name} to ${bank.value.name}`
          : `Withdraw ${money}: ${bank.value.name} to ${cash.value.name}`,
        notes: [], open: 'journal', undoKey: null, ...when,
      }
    }

    case 'transfer': {
      const from = pickAccount('from', 'bank', d.banks, entry.from, picks)
      if (!from.ok) return from.result
      const to = pickAccount('to', 'bank', d.banks, entry.to, picks)
      if (!to.ok) return to.result
      if (from.value.id === to.value.id) return { status: 'error', message: 'Choose two different banks' }
      return {
        status: 'ready',
        key: 'bank_transaction.create',
        params: { kind: 'transfer', ...base, amount: entry.amount, from_bank_account_id: from.value.id, to_bank_account_id: to.value.id },
        summary: `Transfer ${money}: ${from.value.name} to ${to.value.name}`,
        notes: [], open: 'journal', undoKey: null, ...when,
      }
    }

    case 'charge': {
      let bankName: string | null = null
      let note = entry.note
      if (entry.tail !== '') {
        const split = splitChargeTail(entry.tail, d.banks)
        if (!split) return { status: 'error', message: `No bank matches "${entry.tail}"` }
        bankName = split.bank
        note = split.note
      }
      const bank = bankFor('bank', bankName)
      if (!bank.ok) return bank.result
      if (!d.bank_charges_account_id) return { status: 'error', message: 'Add a Bank Charges account first' }
      const charges = d.expense.find((a) => a.id === d.bank_charges_account_id)
      return {
        status: 'ready',
        key: 'bank_transaction.create',
        params: {
          kind: 'charge', ...base, amount: entry.amount, bank_account_id: bank.value.id,
          expense_account_id: d.bank_charges_account_id, notes: note ?? undefined,
        },
        summary: `Bank charge ${money} on ${bank.value.name}${charges ? ` (${charges.name})` : ''}`,
        notes: [], open: 'journal', undoKey: null, ...when,
      }
    }

    case 'received': {
      const customer = await pickParty('customer', entry.party, lookups, picks)
      if (!customer.ok) return customer.result
      const into = settlementFor('into', entry.into)
      if (!into.ok) return into.result
      return {
        status: 'ready',
        key: 'payment.create',
        params: {
          customer_id: customer.value.value, amount: entry.amount,
          // As the payments page does: a cash account raises the drawer, a bank account never does.
          method: d.cash.some((a) => a.id === into.value.id) ? 'cash' : 'bank_transfer',
          date: day, deposit_account_id: into.value.id,
        },
        summary: `Received ${money} from ${customer.value.label} into ${into.value.name}`,
        notes: [], open: 'payment', undoKey: 'payment.void', ...when,
      }
    }

    case 'paid': {
      const vendor = await pickParty('vendor', entry.party, lookups, picks)
      if (!vendor.ok) return vendor.result
      const from = settlementFor('from', entry.from)
      if (!from.ok) return from.result

      // Settle the supplier's open bills oldest first; whatever is left stays as an advance.
      const bills = await lookups.openBills(vendor.value.value)
      let remaining = entry.amount
      const allocations: Array<{ bill_id: string; amount_allocated: number }> = []
      const settled: string[] = []
      for (const bill of bills) {
        if (remaining <= 0.004) break
        const take = Math.round(Math.min(remaining, bill.balance) * 100) / 100
        if (take <= 0) continue
        allocations.push({ bill_id: bill.id, amount_allocated: take })
        settled.push(bill.bill_number ?? 'bill')
        remaining = Math.round((remaining - take) * 100) / 100
      }
      const notes: string[] = []
      if (settled.length > 0) notes.push(`Settles ${settled.join(', ')}`)
      if (remaining > 0.004) notes.push(settled.length > 0 ? `${d.currency} ${fmt(remaining)} stays as an advance` : 'No open bills: held as an advance')

      return {
        status: 'ready',
        key: 'bill_payment.create',
        params: {
          vendor_id: vendor.value.value, payment_date: day, amount: entry.amount,
          currency: d.currency, base_currency: d.currency,
          payment_method: d.cash.some((a) => a.id === from.value.id) ? 'cash' : 'bank_transfer',
          payment_account_id: from.value.id,
          allocations,
        },
        summary: `Paid ${money} to ${vendor.value.label} from ${from.value.name}`,
        notes, open: 'bill_payment', undoKey: 'bill_payment.void', ...when,
      }
    }

    case 'expense': {
      const account = pickAccount('expense', 'expense account', d.expense, entry.what, picks, true)
      if (!account.ok) return account.result
      const from = settlementFor('from', entry.from)
      if (!from.ok) return from.result
      return {
        status: 'ready',
        key: 'expense.create',
        params: {
          ...base, account_id: account.value.id, amount: entry.amount,
          paid_from_account_id: from.value.id, description: entry.what,
        },
        summary: `Expense ${money}: ${account.value.name}, paid from ${from.value.name}`,
        notes: [], open: 'journal', undoKey: null, ...when,
      }
    }

    case 'sale': {
      const into = defaultSettlement(d)
      return {
        status: 'ready',
        key: 'sale.create',
        params: {
          ...base, amount: entry.amount, description: entry.note ?? undefined,
          deposit_account_id: into?.id,
        },
        summary: `Sale ${money}${entry.note ? `: ${entry.note}` : ''}${into ? `, into ${into.name}` : ''}`,
        notes: [], open: 'redirect', undoKey: null, ...when,
      }
    }
  }
}

// ---------------------------------------------------------------------------
// Help
// ---------------------------------------------------------------------------

/** The verbs, one example each, for `help`. */
export const QUICK_ENTRY_HELP: ReadonlyArray<{ verb: string; example: string; does: string }> = [
  { verb: 'deposit', example: 'deposit 50000 meezan', does: 'Cash to bank' },
  { verb: 'withdraw', example: 'withdraw 20000 meezan', does: 'Bank to cash' },
  { verb: 'transfer', example: 'transfer 100000 meezan to hbl', does: 'Bank to bank' },
  { verb: 'bank charge', example: 'bank charge 500 meezan', does: 'Fee taken by the bank' },
  { verb: 'received', example: 'received 15000 from al noor', does: 'Customer payment' },
  { verb: 'paid', example: 'paid 8000 to karachi office', does: 'Supplier payment' },
  { verb: 'expense', example: 'expense 2500 tea', does: 'Everyday expense' },
  { verb: 'sale', example: 'sale 12000', does: 'Cash sale' },
]

export function quickEntryHelpLines(): string[] {
  return [
    'TYPING TRANSACTIONS',
    '',
    ...QUICK_ENTRY_HELP.map((h) => `  ${h.example}  -  ${h.does}`),
    '',
    'Amounts: 50000, 50,000, 50k, 1.5 lakh.',
    'Dates: end the line with yesterday, tomorrow, or on 14 oct, on 14/10, on 14-10-2026.',
    'The palette shows what will be booked. Enter posts it.',
  ]
}

export function isHelpRequest(text: string): boolean {
  const t = text.trim().toLowerCase()
  return t === 'help' || t === '?' || t === 'help typing' || t === 'help transactions'
}
