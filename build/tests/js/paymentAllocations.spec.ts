import { describe, expect, it } from 'vitest'
import {
  allocationDisplayAmount,
  appliedAllocations,
  unappliedAmount,
  type PaymentAllocation,
} from '../../modules/Accounting/Resources/js/lib/paymentAllocations'

/**
 * Covers commit f4811b93: an allocation with a null invoice is the on-account credit,
 * not "Applied to invoice", and the unapplied figure must equal that credit rather than
 * zero.
 */

function allocation(overrides: Partial<PaymentAllocation> = {}): PaymentAllocation {
  return {
    id: 'alloc-1',
    invoice_id: 'invoice-1',
    invoice: { id: 'invoice-1', invoice_number: 'INV-1', currency: 'PKR' },
    amount_allocated: 500,
    base_amount_allocated: 500,
    ...overrides,
  }
}

describe('appliedAllocations', () => {
  it('excludes an allocation with a null invoice (the on-account credit row)', () => {
    const onAccount = allocation({ id: 'alloc-credit', invoice_id: null, invoice: null, amount_allocated: 300 })
    const applied = allocation({ id: 'alloc-applied' })

    const result = appliedAllocations([applied, onAccount])

    expect(result).toHaveLength(1)
    expect(result[0].id).toBe('alloc-applied')
  })
})

describe('unappliedAmount', () => {
  it('is zero when every allocation is applied to an invoice and they sum to the payment', () => {
    const applied = allocation({ amount_allocated: 1000, base_amount_allocated: 1000 })
    expect(unappliedAmount([applied], 1000, 'PKR')).toBe(0)
  })

  it('equals the on-account credit, not zero, when an allocation has a null invoice', () => {
    const onAccount = allocation({ id: 'alloc-credit', invoice_id: null, invoice: null, amount_allocated: 400 })
    // 400 was paid but never allocated to any invoice row, so the payment total (1000)
    // minus what actually landed on an invoice (0) is the unapplied figure -- it must NOT
    // read as zero just because a payment_allocations row exists for it.
    expect(unappliedAmount([onAccount], 1000, 'PKR')).toBe(1000)
  })

  it('is the remainder when some allocations are applied and the rest is on account', () => {
    const applied = allocation({ amount_allocated: 600, base_amount_allocated: 600 })
    const onAccount = allocation({ id: 'alloc-credit', invoice_id: null, invoice: null, amount_allocated: 400 })
    expect(unappliedAmount([applied, onAccount], 1000, 'PKR')).toBe(400)
  })

  it('never goes negative', () => {
    const applied = allocation({ amount_allocated: 1200, base_amount_allocated: 1200 })
    expect(unappliedAmount([applied], 1000, 'PKR')).toBe(0)
  })
})

describe('allocationDisplayAmount', () => {
  it('uses the invoice-currency amount when the invoice matches the payment currency', () => {
    const a = allocation({ amount_allocated: 500, base_amount_allocated: 550 })
    expect(allocationDisplayAmount(a, 'PKR')).toBe(500)
  })

  it('uses the base amount when the invoice currency differs from the payment currency', () => {
    const a = allocation({
      invoice: { id: 'invoice-1', invoice_number: 'INV-1', currency: 'USD' },
      amount_allocated: 500,
      base_amount_allocated: 550,
    })
    expect(allocationDisplayAmount(a, 'PKR')).toBe(550)
  })
})
