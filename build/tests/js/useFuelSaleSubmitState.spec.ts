import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { useFuelSaleCanSubmit, settlesAtCounter } from '../../modules/FuelStation/Resources/js/composables/useFuelSaleSubmitState'

/**
 * Covers the Complete Sale enabled condition from commit 11318f67, extracted out of
 * FuelStation/Resources/js/pages/FuelStation/Sales/Form.vue into
 * useFuelSaleSubmitState.ts so it can be tested without mounting the whole form (its
 * Shadcn Dialog/Select imports make in-isolation mounting impractical).
 */

function baseInputs(overrides: Partial<Parameters<typeof useFuelSaleCanSubmit>[0]> = {}) {
  return {
    pumpId: ref<string | null>('pump-1'),
    itemId: ref<string | null>('item-1'),
    quantity: ref<number | null>(10),
    saleType: ref<'retail' | 'bulk' | 'amanat' | 'investor' | 'credit' | 'parco_card'>('retail'),
    customerId: ref<string | null>(null),
    totalPaid: ref(0),
    total: ref(0),
    ...overrides,
  }
}

describe('settlesAtCounter', () => {
  it('is true for retail and bulk sales', () => {
    expect(settlesAtCounter('retail')).toBe(true)
    expect(settlesAtCounter('bulk')).toBe(true)
  })

  it('is false for credit, amanat, investor and parco_card sales', () => {
    expect(settlesAtCounter('credit')).toBe(false)
    expect(settlesAtCounter('amanat')).toBe(false)
    expect(settlesAtCounter('investor')).toBe(false)
    expect(settlesAtCounter('parco_card')).toBe(false)
  })
})

describe('useFuelSaleCanSubmit', () => {
  it('allows a credit sale to submit with no counter payment, as long as a buyer is chosen', () => {
    const inputs = baseInputs({
      saleType: ref('credit'),
      customerId: ref('customer-1'),
      totalPaid: ref(0),
      total: ref(3000),
    })
    const { canSubmit } = useFuelSaleCanSubmit(inputs)
    expect(canSubmit.value).toBe(true)
  })

  it('blocks a credit sale with no buyer chosen, even if everything else is filled in', () => {
    const inputs = baseInputs({
      saleType: ref('credit'),
      customerId: ref(null),
      totalPaid: ref(0),
      total: ref(3000),
    })
    const { canSubmit } = useFuelSaleCanSubmit(inputs)
    expect(canSubmit.value).toBe(false)
  })

  it.each(['amanat', 'investor', 'parco_card'] as const)(
    'allows a %s sale to submit with no counter payment',
    (saleType) => {
      const inputs = baseInputs({ saleType: ref(saleType), totalPaid: ref(0), total: ref(3000) })
      const { canSubmit } = useFuelSaleCanSubmit(inputs)
      expect(canSubmit.value).toBe(true)
    },
  )

  it('blocks a retail sale until the payment breakdown equals the total', () => {
    const inputs = baseInputs({ saleType: ref('retail'), totalPaid: ref(1500), total: ref(3000) })
    const { canSubmit } = useFuelSaleCanSubmit(inputs)
    expect(canSubmit.value).toBe(false)
  })

  it('allows a retail sale once the payment breakdown equals the total', () => {
    const inputs = baseInputs({ saleType: ref('retail'), totalPaid: ref(3000), total: ref(3000) })
    const { canSubmit } = useFuelSaleCanSubmit(inputs)
    expect(canSubmit.value).toBe(true)
  })

  it('blocks a bulk sale until the payment breakdown equals the total', () => {
    const inputs = baseInputs({ saleType: ref('bulk'), totalPaid: ref(0), total: ref(2500) })
    const { canSubmit } = useFuelSaleCanSubmit(inputs)
    expect(canSubmit.value).toBe(false)
  })

  it('blocks submit when no pump, item or quantity is chosen', () => {
    expect(useFuelSaleCanSubmit(baseInputs({ pumpId: ref(null) })).canSubmit.value).toBe(false)
    expect(useFuelSaleCanSubmit(baseInputs({ itemId: ref(null) })).canSubmit.value).toBe(false)
    expect(useFuelSaleCanSubmit(baseInputs({ quantity: ref(null) })).canSubmit.value).toBe(false)
    expect(useFuelSaleCanSubmit(baseInputs({ quantity: ref(0) })).canSubmit.value).toBe(false)
  })
})
