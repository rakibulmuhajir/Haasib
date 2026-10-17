import { describe, expect, it } from 'vitest'
import {
  formatDay,
  resolveDate,
  isHelpRequest,
  isQuickEntry,
  matchAccounts,
  parseAmount,
  parseQuickEntry,
  quickEntryHelpLines,
  quickEntryHint,
  resolveQuickEntry,
  type Lookups,
  type Option,
  type QuickDefaults,
} from '@/palette/quick-entry'

const defaults: QuickDefaults = {
  today: '2026-10-16',
  currency: 'PKR',
  default_bank_id: 'bank-meezan',
  default_cash_id: 'cash-1',
  bank_charges_account_id: 'exp-charges',
  banks: [
    { id: 'bank-meezan', code: '1060', name: 'Meezan Bank' },
    { id: 'bank-hbl', code: '1061', name: 'HBL Current' },
    { id: 'bank-hbl2', code: '1062', name: 'HBL Savings' },
  ],
  cash: [{ id: 'cash-1', code: '1050', name: 'Cash on Hand' }],
  expense: [
    { id: 'exp-tea', code: '6200', name: 'Tea and Refreshments' },
    { id: 'exp-charges', code: '6520', name: 'Bank Charges & Fees' },
    { id: 'exp-office', code: '6300', name: 'Office Supplies' },
    { id: 'exp-office2', code: '6310', name: 'Office Rent' },
  ],
}

function lookups(over: Partial<Record<'customer' | 'vendor', Option[]>> = {}, bills: Array<{ id: string; bill_number: string; balance: number }> = []): Lookups {
  return {
    defaults,
    search: async (entity, q) =>
      (over[entity] ?? []).filter((o) => o.label.toLowerCase().includes(q.toLowerCase())),
    openBills: async () => bills,
  }
}

describe('amounts', () => {
  it.each([
    ['50000', 50000],
    ['50,000', 50000],
    ['1,234,567', 1234567],
    ['1,50,000', 150000],
    ['50k', 50000],
    ['50K', 50000],
    ['1.5k', 1500],
    ['1.5 lakh', 150000],
    ['1.5lakh', 150000],
    ['2 lac', 200000],
    ['2 Lac', 200000],
    ['0.5 lakh', 50000],
    ['250.50', 250.5],
    ['1,250.75', 1250.75],
  ])('reads %s as %d', (text, expected) => {
    expect(parseAmount(text)?.amount).toBe(expected)
  })

  it.each([
    '', 'abc', '-50', '50.', '.5', '1e5', '0x10', '5o00', '50,00', '5,0000', '1,2,3', '12.345', '1.234 lakh0',
    '0', '0k', '50 thousand', '5 crore', '2 million', '3 m', '50kk', 'k', 'lakh', '1,000,000,000,000', '99999999999',
  ])('refuses %j instead of coercing it', (text) => {
    expect(parseAmount(text)).toBeNull()
  })

  it('never turns a figure into a different one', () => {
    // 12.345 would need rounding to two places; 1.2345 lakh is 123,450 exactly, so it is fine.
    expect(parseAmount('12.345')).toBeNull()
    expect(parseAmount('1.2345 lakh')?.amount).toBe(123450)
    expect(parseAmount('1.23456 lakh')?.amount).toBe(123456)
    expect(parseAmount('1.23456789 lakh')).toBeNull()
  })

  it('leaves the rest of the line', () => {
    expect(parseAmount('1.5 lakh meezan bank')).toEqual({ amount: 150000, rest: 'meezan bank' })
    expect(parseAmount('50k meezan')).toEqual({ amount: 50000, rest: 'meezan' })
    expect(parseAmount('50 constructor')).toEqual({ amount: 50, rest: 'constructor' })
  })
})

describe('verbs', () => {
  it('deposit', () => {
    expect(parseQuickEntry('deposit 50000 meezan')).toEqual({ kind: 'deposit', amount: 50000, bank: 'meezan' })
    expect(parseQuickEntry('deposit 50000 into meezan bank')).toEqual({ kind: 'deposit', amount: 50000, bank: 'meezan bank' })
    expect(parseQuickEntry('Deposit 5k')).toEqual({ kind: 'deposit', amount: 5000, bank: null })
  })

  it('withdraw', () => {
    expect(parseQuickEntry('withdraw 20000')).toEqual({ kind: 'withdraw', amount: 20000, bank: null })
    expect(parseQuickEntry('withdraw 20,000 from hbl')).toEqual({ kind: 'withdraw', amount: 20000, bank: 'hbl' })
  })

  it('transfer', () => {
    expect(parseQuickEntry('transfer 100000 meezan to hbl')).toEqual({ kind: 'transfer', amount: 100000, from: 'meezan', to: 'hbl' })
    expect(parseQuickEntry('transfer 1 lakh from meezan bank to hbl current')).toEqual({ kind: 'transfer', amount: 100000, from: 'meezan bank', to: 'hbl current' })
    expect(parseQuickEntry('transfer 100000 meezan')).toBeNull()
    expect(parseQuickEntry('transfer 100000')).toBeNull()
  })

  it('bank charge', () => {
    expect(parseQuickEntry('bank charge 500 meezan')).toEqual({ kind: 'charge', amount: 500, tail: 'meezan', note: null })
    expect(parseQuickEntry('bank charge 500')).toEqual({ kind: 'charge', amount: 500, tail: '', note: null })
    expect(parseQuickEntry('bank charge 500 for monthly fee')).toEqual({ kind: 'charge', amount: 500, tail: '', note: 'monthly fee' })
  })

  it('received', () => {
    expect(parseQuickEntry('received 15000 from al noor')).toEqual({ kind: 'received', amount: 15000, party: 'al noor', into: null })
    expect(parseQuickEntry('received 15000 from al noor into meezan')).toEqual({ kind: 'received', amount: 15000, party: 'al noor', into: 'meezan' })
    expect(parseQuickEntry('received 15000')).toBeNull()
    expect(parseQuickEntry('received 15000 al noor')).toBeNull()
  })

  it('paid', () => {
    expect(parseQuickEntry('paid 8000 to karachi office')).toEqual({ kind: 'paid', amount: 8000, party: 'karachi office', from: null })
    expect(parseQuickEntry('paid 8000 to karachi office from meezan')).toEqual({ kind: 'paid', amount: 8000, party: 'karachi office', from: 'meezan' })
    expect(parseQuickEntry('paid 8000 karachi office')).toBeNull()
  })

  it('expense', () => {
    expect(parseQuickEntry('expense 2500 tea')).toEqual({ kind: 'expense', amount: 2500, what: 'tea', from: null })
    expect(parseQuickEntry('expense 2500 office tea from meezan')).toEqual({ kind: 'expense', amount: 2500, what: 'office tea', from: 'meezan' })
    expect(parseQuickEntry('expense 2500')).toBeNull()
  })

  it('sale', () => {
    expect(parseQuickEntry('sale 12000')).toEqual({ kind: 'sale', amount: 12000, note: null })
    expect(parseQuickEntry('sale 1.5 lakh walk-in')).toEqual({ kind: 'sale', amount: 150000, note: 'walk-in' })
  })

  it('leaves ordinary commands alone', () => {
    for (const text of ['expense create', 'expense list', 'sale', 'invoice create', 'customer list', 'payment create', 'deposit', 'paid', 'company switch', 'help']) {
      expect(parseQuickEntry(text)).toBeNull()
      expect(isQuickEntry(text)).toBe(false)
    }
  })

  it('rejects garbage after a verb', () => {
    for (const text of ['deposit lots', 'deposit -5', 'sale 12,00', 'expense x tea', 'received a lot from bob', 'withdraw 1e3']) {
      expect(parseQuickEntry(text)).toBeNull()
    }
  })

  it('ignores extra spaces and letter case', () => {
    expect(parseQuickEntry('  DEPOSIT   50k   Meezan  ')).toEqual({ kind: 'deposit', amount: 50000, bank: 'Meezan' })
  })
})

describe('hints', () => {
  it('shows usage while an entry is incomplete, never for ordinary commands', () => {
    expect(quickEntryHint('deposit')).toBe('deposit 50000 [into bank]')
    expect(quickEntryHint('deposit ')).toBe('deposit 50000 [into bank]')
    expect(quickEntryHint('paid 500 karachi')).toContain('paid 8000 to')
    expect(quickEntryHint('deposit 500')).toBeNull()
    expect(quickEntryHint('expense create')).toBeNull()
    expect(quickEntryHint('invoice')).toBeNull()
  })

  it('help lists every verb with an example', () => {
    const text = quickEntryHelpLines().join('\n')
    for (const verb of ['deposit 50000', 'withdraw 20000', 'transfer 100000', 'bank charge 500', 'received 15000', 'paid 8000', 'expense 2500', 'sale 12000']) {
      expect(text).toContain(verb)
    }
    expect(isHelpRequest('help')).toBe(true)
    expect(isHelpRequest('Help typing')).toBe(true)
    expect(isHelpRequest('help company')).toBe(false)
  })
})

describe('names', () => {
  it('matches every word typed, exact name first', () => {
    expect(matchAccounts(defaults.banks, 'meezan').map((a) => a.id)).toEqual(['bank-meezan'])
    expect(matchAccounts(defaults.banks, 'hbl').map((a) => a.id)).toEqual(['bank-hbl', 'bank-hbl2'])
    expect(matchAccounts(defaults.banks, 'hbl savings').map((a) => a.id)).toEqual(['bank-hbl2'])
    expect(matchAccounts(defaults.banks, '1061').map((a) => a.id)).toEqual(['bank-hbl'])
    expect(matchAccounts(defaults.banks, 'nothing')).toEqual([])
  })
})

describe('resolving', () => {
  it('deposit fills the date, the default cash account and the named bank', async () => {
    const r = await resolveQuickEntry(parseQuickEntry('deposit 5000 meezan')!, lookups())
    expect(r).toMatchObject({
      status: 'ready',
      key: 'bank_transaction.create',
      params: { kind: 'deposit', date: '2026-10-16', amount: 5000, bank_account_id: 'bank-meezan', cash_account_id: 'cash-1' },
      undoKey: null,
    })
  })

  it('deposit without a bank uses the default bank; withdraw flips the kind', async () => {
    const d = await resolveQuickEntry(parseQuickEntry('deposit 5000')!, lookups())
    expect(d).toMatchObject({ status: 'ready', params: { bank_account_id: 'bank-meezan' } })
    const w = await resolveQuickEntry(parseQuickEntry('withdraw 1k')!, lookups())
    expect(w).toMatchObject({ status: 'ready', params: { kind: 'withdrawal', amount: 1000 } })
  })

  it('a bank name that matches two banks shows both and does not choose', async () => {
    const r = await resolveQuickEntry(parseQuickEntry('deposit 5000 hbl')!, lookups())
    expect(r.status).toBe('choices')
    if (r.status === 'choices') {
      expect(r.choices.map((c) => c.value)).toEqual(['bank-hbl', 'bank-hbl2'])
      const picked = await resolveQuickEntry(parseQuickEntry('deposit 5000 hbl')!, lookups(), { bank: r.choices[1] })
      expect(picked).toMatchObject({ status: 'ready', params: { bank_account_id: 'bank-hbl2' } })
    }
  })

  it('a bank that matches nothing is an error, not the default bank', async () => {
    const r = await resolveQuickEntry(parseQuickEntry('deposit 5000 nowhere')!, lookups())
    expect(r).toEqual({ status: 'error', message: 'No bank matches "nowhere"' })
  })

  it('transfer needs two different banks', async () => {
    const ok = await resolveQuickEntry(parseQuickEntry('transfer 100000 meezan to hbl savings')!, lookups())
    expect(ok).toMatchObject({ status: 'ready', params: { kind: 'transfer', from_bank_account_id: 'bank-meezan', to_bank_account_id: 'bank-hbl2' } })
    const same = await resolveQuickEntry(parseQuickEntry('transfer 100 meezan to meezan')!, lookups())
    expect(same).toEqual({ status: 'error', message: 'Choose two different banks' })
    const amb = await resolveQuickEntry(parseQuickEntry('transfer 100 meezan to hbl')!, lookups())
    expect(amb.status).toBe('choices')
  })

  it('bank charge splits the bank from the note and books to the bank charges account', async () => {
    const r = await resolveQuickEntry(parseQuickEntry('bank charge 500 meezan bank october fee')!, lookups())
    expect(r).toMatchObject({
      status: 'ready',
      params: { kind: 'charge', bank_account_id: 'bank-meezan', expense_account_id: 'exp-charges', notes: 'october fee' },
    })
    const bare = await resolveQuickEntry(parseQuickEntry('bank charge 500')!, lookups())
    expect(bare).toMatchObject({ status: 'ready', params: { bank_account_id: 'bank-meezan', expense_account_id: 'exp-charges' } })
    const typo = await resolveQuickEntry(parseQuickEntry('bank charge 500 meezam')!, lookups())
    expect(typo.status).toBe('error')
  })

  it('bank charge without a bank charges account says so', async () => {
    const none: Lookups = { ...lookups(), defaults: { ...defaults, bank_charges_account_id: null } }
    const r = await resolveQuickEntry(parseQuickEntry('bank charge 500')!, none)
    expect(r).toEqual({ status: 'error', message: 'Add a Bank Charges account first' })
  })

  it('received resolves the customer and picks the method from the account', async () => {
    const customers = { customer: [{ value: 'c1', label: 'Al Noor Traders' }] }
    const cash = await resolveQuickEntry(parseQuickEntry('received 15000 from al noor')!, lookups(customers))
    expect(cash).toMatchObject({
      status: 'ready', key: 'payment.create', undoKey: 'payment.void', open: 'payment',
      params: { customer_id: 'c1', amount: 15000, method: 'cash', deposit_account_id: 'cash-1', date: '2026-10-16' },
    })
    const bank = await resolveQuickEntry(parseQuickEntry('received 15000 from al noor into meezan')!, lookups(customers))
    expect(bank).toMatchObject({ status: 'ready', params: { method: 'bank_transfer', deposit_account_id: 'bank-meezan' } })
  })

  it('received from an ambiguous customer shows both to pick from', async () => {
    const customers = { customer: [{ value: 'c1', label: 'Al Noor Traders' }, { value: 'c2', label: 'Al Hamd Stores' }] }
    const r = await resolveQuickEntry(parseQuickEntry('received 15000 from al')!, lookups(customers))
    expect(r.status).toBe('choices')
    if (r.status === 'choices') {
      expect(r.choices.map((c) => c.value)).toEqual(['c1', 'c2'])
      const picked = await resolveQuickEntry(parseQuickEntry('received 15000 from al')!, lookups(customers), { customer: r.choices[1] })
      expect(picked).toMatchObject({ status: 'ready', params: { customer_id: 'c2' } })
    }
  })

  it('an exact customer name wins over longer ones that contain it', async () => {
    const customers = { customer: [{ value: 'c1', label: 'Al Noor' }, { value: 'c2', label: 'Al Noor Traders' }] }
    const r = await resolveQuickEntry(parseQuickEntry('received 100 from al noor')!, lookups(customers))
    expect(r).toMatchObject({ status: 'ready', params: { customer_id: 'c1' } })
  })

  it('an unknown customer is an error', async () => {
    const r = await resolveQuickEntry(parseQuickEntry('received 100 from nobody')!, lookups())
    expect(r).toEqual({ status: 'error', message: 'No customer matches "nobody"' })
  })

  it('paid settles open bills oldest first and keeps the rest as an advance', async () => {
    const vendors = { vendor: [{ value: 'v1', label: 'Karachi Office Supplies' }] }
    const bills = [
      { id: 'b1', bill_number: 'BILL-1', balance: 5000 },
      { id: 'b2', bill_number: 'BILL-2', balance: 2000 },
      { id: 'b3', bill_number: 'BILL-3', balance: 9000 },
    ]
    const r = await resolveQuickEntry(parseQuickEntry('paid 8000 to karachi office')!, lookups(vendors, bills))
    expect(r).toMatchObject({
      status: 'ready', key: 'bill_payment.create', undoKey: 'bill_payment.void', open: 'bill_payment',
      params: {
        vendor_id: 'v1', payment_date: '2026-10-16', amount: 8000, currency: 'PKR', base_currency: 'PKR',
        payment_method: 'cash', payment_account_id: 'cash-1',
        allocations: [{ bill_id: 'b1', amount_allocated: 5000 }, { bill_id: 'b2', amount_allocated: 2000 }, { bill_id: 'b3', amount_allocated: 1000 }],
      },
    })
    const none = await resolveQuickEntry(parseQuickEntry('paid 800 to karachi office from meezan')!, lookups(vendors, []))
    expect(none).toMatchObject({ status: 'ready', params: { allocations: [], payment_method: 'bank_transfer', payment_account_id: 'bank-meezan' } })
    if (none.status === 'ready') expect(none.notes.join(' ')).toContain('advance')
  })

  it('expense resolves the account by what was typed, and asks when several fit', async () => {
    const one = await resolveQuickEntry(parseQuickEntry('expense 250 tea')!, lookups())
    expect(one).toMatchObject({
      status: 'ready', key: 'expense.create',
      params: { account_id: 'exp-tea', amount: 250, paid_from_account_id: 'cash-1', description: 'tea', date: '2026-10-16' },
    })
    const many = await resolveQuickEntry(parseQuickEntry('expense 250 office')!, lookups())
    expect(many.status).toBe('choices')
    if (many.status === 'choices') expect(many.choices.map((c) => c.value)).toEqual(['exp-office', 'exp-office2'])
    // A description that names no account is not guessed at: every expense account is offered.
    const none = await resolveQuickEntry(parseQuickEntry('expense 250 zebra')!, lookups())
    expect(none.status).toBe('choices')
    if (none.status === 'choices') {
      expect(none.choices).toHaveLength(defaults.expense.length)
      const picked = await resolveQuickEntry(parseQuickEntry('expense 250 zebra')!, lookups(), { expense: none.choices[2] })
      expect(picked).toMatchObject({ status: 'ready', params: { account_id: 'exp-office', description: 'zebra' } })
    }
  })

  it('sale goes into the cash drawer with the note as description', async () => {
    const r = await resolveQuickEntry(parseQuickEntry('sale 1200 walk-in')!, lookups())
    expect(r).toMatchObject({
      status: 'ready', key: 'sale.create', open: 'redirect',
      params: { amount: 1200, description: 'walk-in', deposit_account_id: 'cash-1', date: '2026-10-16' },
    })
  })

  it('says so when the company has no cash account to use', async () => {
    const noCash: Lookups = { ...lookups(), defaults: { ...defaults, cash: [], default_cash_id: null } }
    const r = await resolveQuickEntry(parseQuickEntry('deposit 100 meezan')!, noCash)
    expect(r).toEqual({ status: 'error', message: 'Add a cash account first' })
  })
})

describe('dates', () => {
  const day = async (line: string, today = '2026-10-16') => {
    const r = await resolveQuickEntry(parseQuickEntry(line)!, { ...lookups(), defaults: { ...defaults, today } })
    return r.status === 'ready' ? r.params.date : r
  }

  it('keeps the rest of the line the same with or without a date', () => {
    expect(parseQuickEntry('deposit 5000 meezan yesterday')).toMatchObject({ kind: 'deposit', amount: 5000, bank: 'meezan', date: { kind: 'relative', word: 'yesterday' } })
    expect(parseQuickEntry('paid 8000 to karachi office on 14 oct')).toMatchObject({ kind: 'paid', party: 'karachi office', from: null, date: { kind: 'explicit', day: 14, month: 10, year: null } })
    expect(parseQuickEntry('expense 250 tea from meezan tomorrow')).toMatchObject({ what: 'tea', from: 'meezan', date: { word: 'tomorrow' } })
    expect(parseQuickEntry('sale 500 today')).toMatchObject({ kind: 'sale', note: null, date: { word: 'today' } })
    expect(parseQuickEntry('sale 500 walk-in on 3/4')).toMatchObject({ note: 'walk-in', date: { day: 3, month: 4 } })
    expect(parseQuickEntry('deposit 5000 meezan')).not.toHaveProperty('date')
  })

  it('does not mistake other words for a date', () => {
    expect(parseQuickEntry('expense 250 on account')).toMatchObject({ what: 'on account' })
    expect(parseQuickEntry('expense 250 tea on 3 tea')).toMatchObject({ what: 'tea on 3 tea' })
    expect(parseQuickEntry('expense 250 tea on 3 oct 202')).toMatchObject({ what: 'tea on 3 oct 202' })
    expect(parseQuickEntry('deposit yesterday')).toBeNull()
  })

  it.each([
    ['deposit 100 yesterday', '2026-10-15'],
    ['deposit 100 today', '2026-10-16'],
    ['deposit 100 tomorrow', '2026-10-17'],
    ['deposit 100 on 14 oct', '2026-10-14'],
    ['deposit 100 on 14 Oct 2026', '2026-10-14'],
    ['deposit 100 on 14 october 2025', '2025-10-14'],
    ['deposit 100 on 14/10', '2026-10-14'],
    ['deposit 100 on 14/10/2026', '2026-10-14'],
    ['deposit 100 on 14-10-2026', '2026-10-14'],
    ['deposit 100 on 14-10-26', '2026-10-14'],
    ['deposit 100 on 2026-10-14', '2026-10-14'],
    ['deposit 100 on 5 sept', '2026-09-05'],
    ['deposit 100 on 29 feb 2028', '2028-02-29'],
    ['deposit 100 ON 1/2', '2026-02-01'],
  ])('%s is booked on %s', async (line, expected) => {
    expect(await day(line)).toBe(expected)
  })

  it('counts relative words from the company date, across month and year ends', async () => {
    expect(await day('deposit 100 yesterday', '2026-11-01')).toBe('2026-10-31')
    expect(await day('deposit 100 yesterday', '2027-01-01')).toBe('2026-12-31')
    expect(await day('deposit 100 tomorrow', '2026-12-31')).toBe('2027-01-01')
    expect(await day('deposit 100 tomorrow', '2028-02-28')).toBe('2028-02-29')
  })

  it('takes a year-less date from the company year, unless that is months ahead', async () => {
    expect(await day('deposit 100 on 28 dec', '2027-01-03')).toBe('2026-12-28')
    expect(await day('deposit 100 on 28 dec', '2026-10-16')).toBe('2026-12-28')
    expect(await day('deposit 100 on 5 jan', '2026-12-30')).toBe('2026-01-05')
  })

  it('refuses a day that does not exist', async () => {
    expect(await day('deposit 100 on 31/02')).toEqual({ status: 'error', message: '31/02 is not a date' })
    expect(await day('deposit 100 on 29 feb 2027')).toMatchObject({ status: 'error' })
    expect(await day('deposit 100 on 14/13')).toMatchObject({ status: 'error' })
    expect(resolveDate({ kind: 'explicit', day: 0, month: 1, year: 2026, text: '0/1' }, '2026-10-16')).toMatchObject({ ok: false })
  })

  it('carries the date to every verb', async () => {
    const lk = lookups({ customer: [{ value: 'cust-1', label: 'Al Noor' }], vendor: [{ value: 'ven-1', label: 'Karachi Office' }] })
    const params = async (line: string) => {
      const r = await resolveQuickEntry(parseQuickEntry(line)!, lk)
      return r.status === 'ready' ? { ...r.params, shown: r.date, label: r.dateLabel } : r
    }
    expect(await params('withdraw 100 yesterday')).toMatchObject({ date: '2026-10-15', shown: '2026-10-15' })
    expect(await params('transfer 100 meezan to hbl current yesterday')).toMatchObject({ date: '2026-10-15' })
    expect(await params('bank charge 50 meezan yesterday')).toMatchObject({ date: '2026-10-15' })
    expect(await params('received 100 from al noor yesterday')).toMatchObject({ date: '2026-10-15' })
    expect(await params('paid 100 to karachi office on 14 oct')).toMatchObject({ payment_date: '2026-10-14', label: 'Wed 14 Oct 2026' })
    expect(await params('expense 100 tea yesterday')).toMatchObject({ date: '2026-10-15' })
    expect(await params('sale 100 yesterday')).toMatchObject({ date: '2026-10-15' })
  })

  it('names the day for the preview header', () => {
    expect(formatDay('2026-10-15')).toBe('Thu 15 Oct 2026')
    expect(formatDay('2028-02-29')).toBe('Tue 29 Feb 2028')
  })
})

describe('quick entry defaults from settings', () => {
  const withSettings = (over: Partial<QuickDefaults>): Lookups => ({
    ...lookups({ customer: [{ value: 'cust-1', label: 'Al Noor' }], vendor: [{ value: 'ven-1', label: 'Karachi Office' }] }),
    defaults: { ...defaults, ...over },
  })
  const run = (line: string, lk: Lookups) => resolveQuickEntry(parseQuickEntry(line)!, lk)

  it('received and sale go to the money-received account', async () => {
    const lk = withSettings({ receive_account_id: 'bank-hbl' })
    expect(await run('received 100 from al noor', lk)).toMatchObject({ params: { deposit_account_id: 'bank-hbl', method: 'bank_transfer' } })
    expect(await run('sale 100', lk)).toMatchObject({ params: { deposit_account_id: 'bank-hbl' } })
  })

  it('paid and expense come from the money-paid account', async () => {
    const lk = withSettings({ pay_account_id: 'bank-hbl' })
    expect(await run('paid 100 to karachi office', lk)).toMatchObject({ params: { payment_account_id: 'bank-hbl', payment_method: 'bank_transfer' } })
    expect(await run('expense 100 tea', lk)).toMatchObject({ params: { paid_from_account_id: 'bank-hbl' } })
  })

  it('an account named in the line wins over the setting', async () => {
    const lk = withSettings({ receive_account_id: 'bank-hbl', pay_account_id: 'bank-hbl' })
    expect(await run('received 100 from al noor into meezan', lk)).toMatchObject({ params: { deposit_account_id: 'bank-meezan' } })
    expect(await run('paid 100 to karachi office from cash on hand', lk)).toMatchObject({ params: { payment_account_id: 'cash-1', payment_method: 'cash' } })
    expect(await run('expense 100 tea from meezan', lk)).toMatchObject({ params: { paid_from_account_id: 'bank-meezan' } })
  })

  it('a cash account as the setting is used like any other', async () => {
    const lk = withSettings({ receive_account_id: 'cash-1', default_cash_id: 'cash-1' })
    expect(await run('received 100 from al noor', lk)).toMatchObject({ params: { deposit_account_id: 'cash-1', method: 'cash' } })
  })

  it('deposit uses the money-received account when it is a bank; withdraw and charge the money-paid one', async () => {
    const lk = withSettings({ receive_account_id: 'bank-hbl', pay_account_id: 'bank-hbl2' })
    expect(await run('deposit 100', lk)).toMatchObject({ params: { bank_account_id: 'bank-hbl' } })
    expect(await run('withdraw 100', lk)).toMatchObject({ params: { bank_account_id: 'bank-hbl2' } })
    expect(await run('bank charge 50', lk)).toMatchObject({ params: { bank_account_id: 'bank-hbl2' } })
    expect(await run('deposit 100 meezan', lk)).toMatchObject({ params: { bank_account_id: 'bank-meezan' } })
  })

  it('deposit falls back to the company default bank when the setting is cash or empty', async () => {
    expect(await run('deposit 100', withSettings({ receive_account_id: 'cash-1' }))).toMatchObject({ params: { bank_account_id: 'bank-meezan' } })
    expect(await run('deposit 100', withSettings({ receive_account_id: null, pay_account_id: null }))).toMatchObject({ params: { bank_account_id: 'bank-meezan' } })
  })

  it('empty or stale settings change nothing', async () => {
    expect(await run('received 100 from al noor', withSettings({ receive_account_id: 'gone' }))).toMatchObject({ params: { deposit_account_id: 'cash-1' } })
    expect(await run('expense 100 tea', withSettings({}))).toMatchObject({ params: { paid_from_account_id: 'cash-1' } })
  })
})
