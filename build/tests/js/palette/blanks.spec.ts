import { describe, expect, it } from 'vitest'
import { applyResolve, enterOutcome, fromMatch, lineOf, nextRequired, pick, setText, showsSentence, tab } from '@/palette/v2/blanks'
import type { CatalogCommand, SlotSpec } from '@/palette/v2/types'

function cmd(over: Partial<CatalogCommand> = {}): CatalogCommand {
  const slots: SlotSpec[] = [
    { name: 'vendor', kind: 'search', optional: false },
    { name: 'amount', kind: 'amount', optional: false },
    { name: 'account', kind: 'account', optional: true },
  ]
  return {
    name: 'pay', aliases: [], label: 'Pay', sentence: 'Pay {vendor} {amount} from {account?}', key: 'money.pay', slots,
    generic: false, form_url: null, query: false, opens: null, undo: null, previewable: true, ...over,
  }
}

const set = (texts: Record<string, string>, over: Partial<CatalogCommand> = {}) =>
  fromMatch({ command: cmd(over), verbText: 'pay', slotTexts: texts })

const resolvedBoth = () =>
  applyResolve(set({ vendor: 'a', amount: '1' }), {
    status: 'pending',
    slots: { vendor: { state: 'resolved', value: 'v', label: 'A' }, amount: { state: 'resolved', value: '1', label: '1' } },
  })

describe('fromMatch', () => {
  it('points at the first required blank with no text, else the last blank', () => {
    expect(set({ vendor: 'ali' }).current).toBe(1)
    expect(set({ vendor: 'ali', amount: '10' }).current).toBe(2)
  })
})

describe('enterOutcome', () => {
  it('picks when choices are open', () => {
    expect(enterOutcome(set({ vendor: 'ali' }), false, true)).toBe('pick')
  })
  it('opens a form_url command', () => {
    expect(enterOutcome(set({}, { form_url: '/{company}/invoices/create' }), false, false)).toBe('open')
  })
  it('posts when ready, opens when a query is ready', () => {
    expect(enterOutcome(set({ vendor: 'a', amount: '1' }), true, false)).toBe('post')
    expect(enterOutcome(set({}, { query: true }), true, false)).toBe('open')
  })
  it('advances when the current blank has text and something required is left', () => {
    expect(enterOutcome({ ...set({ vendor: 'ali' }), current: 0 }, false, false)).toBe('advance')
  })
  it('flags when the current blank is empty, or nothing required is left but it is not ready', () => {
    expect(enterOutcome(set({ vendor: 'ali' }), false, false)).toBe('flag')
    expect(enterOutcome(resolvedBoth(), false, false)).toBe('flag')
  })
})

describe('setText', () => {
  it('clears the pick and the answer when the text changes', () => {
    const picked = pick(set({ vendor: 'ali' }), 0, { value: 'id-1', label: 'Ali Traders' })
    expect(picked.blanks[0].picked).not.toBeNull()
    const edited = setText(picked, 0, 'alif')
    expect(edited.blanks[0].picked).toBeNull()
    expect(edited.blanks[0].state).toBeNull()
  })
})

describe('nextRequired', () => {
  it('skips optional blanks', () => {
    expect(nextRequired(resolvedBoth())).toBeNull()
    expect(nextRequired(set({ vendor: 'a' }))).toBe(0)
  })
})

describe('tab', () => {
  it('wraps backwards from the first blank to the last', () => {
    const s = { ...set({}), current: 0 }
    expect(tab(s, -1).current).toBe(2)
    expect(tab({ ...s, current: 2 }, 1).current).toBe(0)
  })
})

describe('lineOf and showsSentence', () => {
  const full = { sentence: 'Pay {vendor} {amount} from {account?} on {date?}' }
  it('rebuilds the line without the anchors of empty blanks', () => {
    const s = set({ vendor: '7 star', amount: '5000' }, full)
    expect(lineOf(s)).toBe('Pay 7 star 5000')
    expect(lineOf(setText(s, 2, 'cash'))).toBe('Pay 7 star 5000 from cash')
    expect(lineOf(setText(s, 3, 'today'))).toBe('Pay 7 star 5000 today')
  })
  it('shows the sentence only once the verb is followed by a space', () => {
    const s = set({ vendor: '' })
    expect(showsSentence(s, 'pay', false)).toBe(false)
    expect(showsSentence(s, 'payroll', false)).toBe(false)
    expect(showsSentence(s, 'pay ', false)).toBe(true)
    expect(showsSentence(s, 'payroll', true)).toBe(true)
    expect(showsSentence(null, 'pay ', false)).toBe(false)
    expect(showsSentence({ ...set({}), verb: 'bank charge' }, 'bank  charge ', false)).toBe(true)
    expect(showsSentence({ ...set({}), verb: 'bank charge' }, 'bank  charge', false)).toBe(false)
  })
})
