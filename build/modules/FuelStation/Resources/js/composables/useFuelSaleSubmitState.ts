import { computed, type Ref } from 'vue'

export type FuelSaleType = 'retail' | 'bulk' | 'amanat' | 'investor' | 'credit' | 'parco_card'

/**
 * Whether a sale of this type needs to be paid at the counter. A credit sale is owed by
 * the buyer, a vendor-card (parco) sale is collected from the card issuer later, and
 * amanat and investor sales draw on a balance the server applies
 * (FuelSaleService::handleSaleTypeLogic) rather than on cash taken now. Requiring the
 * payment breakdown to equal the total for those left Complete Sale permanently disabled
 * -- see commit 11318f67.
 */
export function settlesAtCounter(saleType: FuelSaleType): boolean {
  return !['credit', 'amanat', 'investor', 'parco_card'].includes(saleType)
}

export interface FuelSaleSubmitStateInputs {
  pumpId: Ref<string | null | undefined>
  itemId: Ref<string | null | undefined>
  quantity: Ref<number | null | undefined>
  saleType: Ref<FuelSaleType>
  customerId: Ref<string | null | undefined>
  totalPaid: Ref<number>
  total: Ref<number>
}

/**
 * The Complete Sale button's enabled condition, extracted out of
 * FuelStation/Resources/js/pages/FuelStation/Sales/Form.vue so it can be unit tested
 * without mounting the full form (Shadcn Dialog/Select imports make that impractical in
 * isolation). Kept byte-for-byte equivalent to the component's own `canSubmit` computed.
 */
export function useFuelSaleCanSubmit(inputs: FuelSaleSubmitStateInputs) {
  const settlesAtCounterState = computed(() => settlesAtCounter(inputs.saleType.value))

  const canSubmit = computed(() =>
    !!inputs.pumpId.value
    && !!inputs.itemId.value
    && !!inputs.quantity.value
    && (inputs.quantity.value as number) > 0
    && (!settlesAtCounterState.value || inputs.totalPaid.value === inputs.total.value)
    && (inputs.saleType.value !== 'credit' || !!inputs.customerId.value),
  )

  return { settlesAtCounter: settlesAtCounterState, canSubmit }
}
