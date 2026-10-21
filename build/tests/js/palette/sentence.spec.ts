import { describe, expect, it } from 'vitest'
import { matchCommand, parseTemplate, suggestCommands } from '@/palette/sentence'
import type { CatalogCommand, SlotSpec } from '@/palette/types'

const KINDS: Record<string, SlotSpec['kind']> = { amount: 'amount', date: 'date', vendor: 'search', customer: 'search', bank: 'search', from: 'search', to: 'search', account: 'account' }

/** A catalog entry whose slots come from its sentence, the way the server builds it. */
function cmd(name: string, sentence: string, aliases: string[] = [], over: Partial<CatalogCommand> = {}): CatalogCommand {
  const slots = [...sentence.matchAll(/\{(\w+)(\?)?\}/g)].map((m): SlotSpec => ({ name: m[1], kind: KINDS[m[1]] ?? 'text', optional: m[2] === '?' }))
  return {
    name, aliases, label: name.replace(/^./, (c) => c.toUpperCase()), sentence, key: `money.${name}`, slots,
    generic: false, form_url: null, query: false, opens: null, undo: null, previewable: true, ...over,
  }
}

const catalog: CatalogCommand[] = [
  cmd('pay', 'Pay {vendor} {amount} from {account?} on {date?}', ['paid']),
  cmd('receive', 'Receive {amount} from {customer} into {account?} on {date?}', ['received', 'rcv']),
  cmd('spend', 'Spend {amount} on {what} from {account?} on {date?}', ['expense', 'exp']),
  cmd('bank charge', 'Bank charge {amount} on {bank?} for {note?} on {date?}', ['bank charges']),
  cmd('deposit', 'Deposit {amount} into {bank?} on {date?}'),
  cmd('withdraw', 'Withdraw {amount} from {bank?} on {date?}'),
  cmd('transfer', 'Transfer {amount} from {from} to {to} on {date?}'),
  cmd('sale', 'Sale {amount} for {note?} on {date?}'),
  cmd('add customer', 'Add customer {name}'),
  cmd('invoice', 'Invoice {customer}', ['inv']),
  cmd('void invoice', 'Void invoice {number}', [], { generic: true, label: 'Void an invoice' }),
  cmd('create bill', 'Create bill', [], { generic: true, label: 'Create a bill' }),
]

const slots = (text: string) => matchCommand(text, catalog)?.slotTexts
const named = (text: string) => matchCommand(text, catalog)?.command.name

describe('parseTemplate', () => {
  it('splits words from required and optional slots', () => {
    expect(parseTemplate('Pay {vendor} {amount} from {account?}')).toEqual([
      { type: 'word', text: 'Pay' },
      { type: 'slot', name: 'vendor', optional: false },
      { type: 'slot', name: 'amount', optional: false },
      { type: 'word', text: 'from' },
      { type: 'slot', name: 'account', optional: true },
    ])
  })
})

describe('verbs', () => {
  it('finds the command by name or alias', () => {
    expect(named('pay ali 1000')).toBe('pay')
    expect(named('paid ali 1000')).toBe('pay')
    expect(named('received 15000 from al noor')).toBe('receive')
    expect(named('rcv 15000 from al noor')).toBe('receive')
    expect(named('expense 2500 tea')).toBe('spend')
    expect(named('inv ali')).toBe('invoice')
  })

  it('takes the longest verb, two words included', () => {
    expect(named('bank charge 500')).toBe('bank charge')
    expect(named('bank charges 500')).toBe('bank charge')
    expect(matchCommand('bank charges 500', catalog)?.verbText).toBe('bank charges')
    expect(named('void invoice 12')).toBe('void invoice')
    expect(named('add customer ali')).toBe('add customer')
  })

  it('needs whole words', () => {
    expect(matchCommand('xyz', catalog)).toBeNull()
    expect(matchCommand('payroll 5', catalog)).toBeNull()
    expect(matchCommand('', catalog)).toBeNull()
  })

  it('leaves ordinary commands alone', () => {
    for (const text of ['company switch', 'help', 'customer list', 'payment create']) {
      expect(matchCommand(text, catalog)).toBeNull()
    }
  })

  it('ignores extra spaces and letter case', () => {
    expect(slots('  DEPOSIT   50k   Meezan  ')).toEqual({ amount: '50k', bank: 'meezan' })
  })
})

describe('slots', () => {
  it('pay', () => {
    expect(slots('pay ali 1000 from cash')).toEqual({ vendor: 'ali', amount: '1000', account: 'cash' })
    expect(slots('pay ali 1000')).toEqual({ vendor: 'ali', amount: '1000' })
    expect(slots('pay 1000 ali')).toEqual({ vendor: 'ali', amount: '1000' })
    expect(slots('pay')).toEqual({})
    expect(slots('pay ali')).toEqual({ vendor: 'ali' })
  })

  it('a name that contains a number keeps it', () => {
    expect(slots('pay 7 star traders 5000')).toEqual({ vendor: '7 star traders', amount: '5000' })
    expect(slots('pay 7 star traders 5000 from meezan')).toEqual({ vendor: '7 star traders', amount: '5000', account: 'meezan' })
  })

  it('amount text holds only the amount', () => {
    expect(slots('pay ali 1.5 lakh from cash')).toEqual({ vendor: 'ali', amount: '1.5 lakh', account: 'cash' })
    expect(slots('pay ali 50k')).toEqual({ vendor: 'ali', amount: '50k' })
    expect(slots('pay ali 50,000')).toEqual({ vendor: 'ali', amount: '50,000' })
    expect(slots('deposit 2%')).toEqual({ amount: '2%' })
  })

  it('paid drops the connector word in front of a name', () => {
    expect(slots('paid 8000 to karachi office')).toEqual({ amount: '8000', vendor: 'karachi office' })
    expect(slots('paid 8000 to karachi office from meezan')).toEqual({ amount: '8000', vendor: 'karachi office', account: 'meezan' })
  })

  it('receive', () => {
    expect(slots('received 15000 from al noor')).toEqual({ amount: '15000', customer: 'al noor' })
    expect(slots('received 15000 from al noor into meezan')).toEqual({ amount: '15000', customer: 'al noor', account: 'meezan' })
    expect(slots('received 15000')).toEqual({ amount: '15000' })
  })

  it('spend', () => {
    expect(slots('expense 2500 tea')).toEqual({ amount: '2500', what: 'tea' })
    expect(slots('expense 2500 office tea from meezan')).toEqual({ amount: '2500', what: 'office tea', account: 'meezan' })
    expect(slots('spend 2500 on tea')).toEqual({ amount: '2500', what: 'tea' })
    expect(slots('expense 2500')).toEqual({ amount: '2500' })
  })

  it('deposit and withdraw', () => {
    expect(slots('deposit 50000 meezan')).toEqual({ amount: '50000', bank: 'meezan' })
    expect(slots('deposit 50000 into meezan bank')).toEqual({ amount: '50000', bank: 'meezan bank' })
    expect(slots('Deposit 5k')).toEqual({ amount: '5k' })
    expect(slots('withdraw 20000')).toEqual({ amount: '20000' })
    expect(slots('withdraw 20,000 from hbl')).toEqual({ amount: '20,000', bank: 'hbl' })
  })

  it('transfer', () => {
    expect(slots('transfer 100000 from meezan to hbl')).toEqual({ amount: '100000', from: 'meezan', to: 'hbl' })
    expect(slots('transfer 100000 meezan to hbl')).toEqual({ amount: '100000', from: 'meezan', to: 'hbl' })
    expect(slots('transfer 1 lakh from meezan bank to hbl current')).toEqual({ amount: '1 lakh', from: 'meezan bank', to: 'hbl current' })
    expect(slots('transfer 100000 meezan')).toEqual({ amount: '100000', from: 'meezan' })
  })

  it('bank charge', () => {
    expect(slots('bank charge 500 meezan for sms')).toEqual({ amount: '500', bank: 'meezan', note: 'sms' })
    expect(slots('bank charge 500 meezan')).toEqual({ amount: '500', bank: 'meezan' })
    expect(slots('bank charge 500')).toEqual({ amount: '500' })
    expect(slots('bank charge 500 for monthly fee')).toEqual({ amount: '500', note: 'monthly fee' })
  })

  it('sale', () => {
    expect(slots('sale 12000')).toEqual({ amount: '12000' })
    expect(slots('sale 1.5 lakh walk-in')).toEqual({ amount: '1.5 lakh', note: 'walk-in' })
    expect(slots('sale 500 for walk-in')).toEqual({ amount: '500', note: 'walk-in' })
  })

  it('a verb alone fills nothing', () => {
    for (const text of ['sale', 'deposit', 'paid', 'transfer', 'add customer']) expect(slots(text)).toEqual({})
  })

  it('text with no amount-shaped run goes to the first non-amount slot; the server rejects it', () => {
    expect(slots('deposit lots')).toEqual({ bank: 'lots' })
    expect(slots('deposit 12,00 x y')).toEqual({ amount: '12,00', bank: 'x y' })
  })

  it('a command with no amount fills its own slot', () => {
    expect(slots('add customer al noor')).toEqual({ name: 'al noor' })
  })
})

describe('which run is the amount', () => {
  it('takes the first run when the amount comes before the other slots', () => {
    expect(slots('sale 12000 2 bags')).toEqual({ amount: '12000', note: '2 bags' })
    expect(slots('expense 2500 tea 2 cups')).toEqual({ amount: '2500', what: 'tea 2 cups' })
    expect(slots('bank charge 500 meezan 2 txns')).toEqual({ amount: '500', bank: 'meezan 2 txns' })
  })

  it('still takes the last run when a name comes first', () => {
    expect(slots('pay 7 star traders 5000')).toEqual({ vendor: '7 star traders', amount: '5000' })
  })

  it('keeps a magnitude word with the amount, so the server refuses it', () => {
    expect(slots('sale 50 thousand')).toEqual({ amount: '50 thousand' })
    expect(slots('pay ali 50 thousand')).toEqual({ vendor: 'ali', amount: '50 thousand' })
  })

  it('reads lakhs and lacs', () => {
    expect(slots('sale 2 lacs')).toEqual({ amount: '2 lacs' })
    expect(slots('sale 1.5 lakhs')).toEqual({ amount: '1.5 lakhs' })
    expect(slots('sale 2 lacs walk-in')).toEqual({ amount: '2 lacs', note: 'walk-in' })
  })

  it('drops "in" in front of a name', () => {
    expect(slots('deposit 50000 in meezan')).toEqual({ amount: '50000', bank: 'meezan' })
  })
})

describe('entity commands are not sentences', () => {
  const generic = cmd('expense.create', 'Create Expense {amount}', ['create expense'], { generic: true, label: 'Create Expense' })
  const withGeneric = [...catalog, generic]

  it('an entity verb after an alias does not match the sentence command', () => {
    expect(matchCommand('expense list', catalog)).toBeNull()
    expect(matchCommand('expense create', catalog)).toBeNull()
    expect(named('expense 2500 tea')).toBe('spend')
  })

  it('a generic command whose label is the two words wins, in either order', () => {
    expect(matchCommand('expense create', withGeneric)?.command.name).toBe('expense.create')
    expect(matchCommand('create expense', withGeneric)?.command.name).toBe('expense.create')
    expect(matchCommand('expense create 500', withGeneric)?.slotTexts).toEqual({ amount: '500' })
    expect(matchCommand('expense 2500 tea', withGeneric)?.command.name).toBe('spend')
  })
})

describe('dates', () => {
  it('takes a relative word out of the line', () => {
    expect(slots('paid ali 1000 yesterday')).toEqual({ vendor: 'ali', amount: '1000', date: 'yesterday' })
    expect(matchCommand('paid ali 1000 yesterday', catalog)?.command.name).toBe('pay')
    expect(slots('deposit 5000 meezan yesterday')).toEqual({ amount: '5000', bank: 'meezan', date: 'yesterday' })
    expect(slots('expense 250 tea from meezan tomorrow')).toEqual({ amount: '250', what: 'tea', account: 'meezan', date: 'tomorrow' })
    expect(slots('sale 500 today')).toEqual({ amount: '500', date: 'today' })
  })

  it('takes "on <date>" out of the line', () => {
    expect(slots('paid 8000 to karachi office on 14 oct')).toEqual({ amount: '8000', vendor: 'karachi office', date: '14 oct' })
    expect(slots('sale 500 walk-in on 3/4')).toEqual({ amount: '500', note: 'walk-in', date: '3/4' })
    expect(slots('deposit 100 on 14-10-2026')).toEqual({ amount: '100', date: '14-10-2026' })
    expect(slots('deposit 100 ON 14 Oct 2026')).toEqual({ amount: '100', date: '14 oct 2026' })
    expect(slots('deposit 100 on 2026-10-14')).toEqual({ amount: '100', date: '2026-10-14' })
  })

  it('"on" is a slot anchor once the date is out', () => {
    expect(slots('spend 2500 on tea yesterday')).toEqual({ amount: '2500', what: 'tea', date: 'yesterday' })
    expect(slots('spend 2500 on tea on 14 oct')).toEqual({ amount: '2500', what: 'tea', date: '14 oct' })
    expect(slots('bank charge 500 on meezan for sms on 14 oct')).toEqual({ amount: '500', bank: 'meezan', note: 'sms', date: '14 oct' })
    expect(slots('bank charge 500 on meezan')).toEqual({ amount: '500', bank: 'meezan' })
  })

  it('does not mistake other words for a date', () => {
    expect(slots('expense 250 on account')).toEqual({ amount: '250', what: 'account' })
    expect(slots('spend 250 on tea on 3 tea')).toEqual({ amount: '250', what: 'tea on 3 tea' })
    expect(slots('spend 250 on tea on 3 oct 202')).toEqual({ amount: '250', what: 'tea on 3 oct 202' })
  })

  it('leaves the date alone for a command with no date slot', () => {
    expect(slots('add customer today traders')).toEqual({ name: 'today traders' })
  })
})

describe('suggestCommands', () => {
  const scores = { transfer: 9, deposit: 5, pay: 7 }

  it('shows no generic command for empty text, top scores first', () => {
    const out = suggestCommands('', catalog, scores)
    expect(out.some((c) => c.generic)).toBe(false)
    expect(out.slice(0, 3).map((c) => c.name)).toEqual(['transfer', 'pay', 'deposit'])
    expect(out.length).toBeLessThanOrEqual(8)
  })

  it('verb prefixes come first, then label words, then generic commands', () => {
    expect(suggestCommands('pa', catalog, {}).map((c) => c.name)[0]).toBe('pay')
    expect(suggestCommands('pay ali 5', catalog, {}).map((c) => c.name)).toEqual(['pay'])
    expect(suggestCommands('rc', catalog, {}).map((c) => c.name)).toEqual(['receive'])
    const out = suggestCommands('invoice', catalog, {}).map((c) => c.name)
    expect(out).toEqual(['invoice', 'void invoice'])
  })

  it('ranks inside a group by score', () => {
    expect(suggestCommands('d', catalog, { deposit: 1 }).map((c) => c.name)[0]).toBe('deposit')
  })

  it('keeps generic commands for words in their label', () => {
    expect(suggestCommands('create', catalog, {}).map((c) => c.name)).toEqual(['create bill'])
  })

  it('returns at most 8', () => {
    const many = Array.from({ length: 12 }, (_, i) => cmd(`item${i}`, `Item${i} {amount}`))
    expect(suggestCommands('item', many, {}).length).toBe(8)
    expect(suggestCommands('', many, {}).length).toBe(8)
  })
})
