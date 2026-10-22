import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import PaletteCard from '@/components/palette/PaletteCard.vue'

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ props: { auth: { currentCompany: { base_currency: 'PKR' } } } }),
}))
import type { Card } from '@/palette/types'

const card: Card = { who: 'Ali Traders', amount: 1000, currency: 'PKR', from: 'Cash in hand', date: '2026-10-21' }
const balances = [{ account_name: 'Cash in hand', before: 45200, after: 44200 }]

describe('PaletteCard', () => {
  it('shows who, the amount, and the account balance before and after', () => {
    const text = mount(PaletteCard, { props: { card, balances, whoLabel: 'Paying' } }).text()
    expect(text).toContain('Paying')
    expect(text).toContain('Ali Traders')
    expect(text).toContain('1,000')
    expect(text).toContain('Cash in hand')
    expect(text).toContain('45,200')
    expect(text).toContain('44,200')
  })

  it('never shows debit or credit', () => {
    const text = mount(PaletteCard, { props: { card, balances } }).text()
    expect(text).not.toMatch(/\bDr\b|\bCr\b|debit|credit/i)
  })

  it('shows the balance line only for an account that has one', () => {
    const text = mount(PaletteCard, { props: { card: { ...card, from: 'Bank' }, balances } }).text()
    expect(text).toContain('Bank')
    expect(text).not.toContain('45,200')
  })

  it('names a thing rather than a person with What', () => {
    const text = mount(PaletteCard, { props: { card: { what: 'Fuel delivery', currency: 'PKR', amount: 5 } } }).text()
    expect(text).toContain('What')
    expect(text).toContain('Fuel delivery')
  })

  it('shows both What and Who when both are set', () => {
    const text = mount(PaletteCard, { props: { card: { what: 'Void', who: 'INV-0012', currency: 'PKR' } } }).text()
    expect(text).toContain('What')
    expect(text).toContain('Void')
    expect(text).toContain('Who')
    expect(text).toContain('INV-0012')
  })
})
