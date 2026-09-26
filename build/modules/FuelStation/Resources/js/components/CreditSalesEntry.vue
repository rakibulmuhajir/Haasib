<script setup lang="ts">
import { ref } from 'vue'
import EntitySearch from '@/components/forms/EntitySearch.vue'
import QuickAddModal from '@/components/forms/QuickAddModal.vue'
import InputError from '@/components/InputError.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useLexicon } from '@/composables/useLexicon'
import { Plus, Trash2, Lock, TriangleAlert, Ban } from 'lucide-vue-next'

interface FuelItem {
    id: string
    name: string
    fuel_category: string
}

interface FuelDiscount {
    discount_type: 'percent' | 'per_litre'
    value: number
}

const rows = defineModel<Array<{
    customer_id: string
    customer_name: string
    amount: number
    reference: string
    item_id?: string
    litres?: number
    invoice_id?: string
    invoice_number?: string
    pending_fuel_invoice?: boolean
    pending_accounting_invoice?: boolean
    // Credit-limit context captured at selection time (see onCustomerSelected) so the
    // row can warn inline without a second round trip per keystroke on amount.
    credit_limit?: number
    current_balance?: number
    is_credit_blocked?: boolean
}>>({ required: true })
const props = defineProps<{
    errors: Record<string, string>
    disabled: boolean
    companySlug?: string
    currency?: string
    fuelItems?: FuelItem[]
    // Keyed by customer id then fuel item id -- the same rate FuelSaleController::create
    // hands to the standalone sale form. See CustomerFuelDiscountService.
    customerFuelDiscounts?: Record<string, Record<string, FuelDiscount>>
}>()
const { t } = useLexicon()

// The discount for a row's chosen customer + fuel item, purely for display -- the server
// (DailyCloseCreditSaleService::prepare) recomputes it authoritatively on post, never trusts
// this number.
const discountFor = (row: { customer_id: string; item_id?: string }): FuelDiscount | null => {
    if (!row.customer_id || !row.item_id) return null
    return props.customerFuelDiscounts?.[row.customer_id]?.[row.item_id] ?? null
}

const discountAmount = (row: { amount: number; litres?: number; item_id?: string; customer_id: string }): number => {
    const discount = discountFor(row)
    if (!discount) return 0
    const gross = Number(row.amount || 0)
    if (discount.discount_type === 'per_litre') {
        if (!row.litres) return 0
        return Math.min(Number(row.litres) * discount.value, gross)
    }
    return Math.min(Math.round((gross * discount.value / 100) * 100) / 100, gross)
}

const missingLitres = (row: { item_id?: string; customer_id: string; litres?: number }): boolean => {
    const discount = discountFor(row)
    return !!discount && discount.discount_type === 'per_litre' && !row.litres
}

// A blocked buyer is refused outright server-side (DailyCloseCreditSaleService::prepare);
// an over-limit buyer is only ever warned, never blocked, per the owner's warn-don't-
// block rule (see FuelSaleService::createSale's own comment on this). These two cases
// must look nothing alike: blocked is a stop sign, over-limit is a heads-up.
const resultingBalance = (row: { current_balance?: number; amount: number }) =>
    Number(row.current_balance ?? 0) + Number(row.amount || 0)

const isOverLimit = (row: { credit_limit?: number; current_balance?: number; amount: number }) =>
    !!row.credit_limit && row.credit_limit > 0 && resultingBalance(row) > row.credit_limit

// Inline "new credit buyer" without leaving the close: EntitySearch's own quick-add
// button opens the same QuickAddModal used by /invoices and /bills, then selects the
// created buyer into whichever row asked for it.
const quickAddIndex = ref<number | null>(null)
const quickAddQuery = ref('')
const showQuickAdd = ref(false)
const openQuickAdd = (index: number, query: string) => {
    quickAddIndex.value = index
    quickAddQuery.value = query
    showQuickAdd.value = true
}
const onCustomerCreated = (customer: { id: string; name: string }) => {
    if (quickAddIndex.value !== null && rows.value[quickAddIndex.value]) {
        rows.value[quickAddIndex.value].customer_id = customer.id
        rows.value[quickAddIndex.value].customer_name = customer.name
        // A freshly quick-added buyer has no credit history yet.
        rows.value[quickAddIndex.value].credit_limit = 0
        rows.value[quickAddIndex.value].current_balance = 0
        rows.value[quickAddIndex.value].is_credit_blocked = false
    }
    showQuickAdd.value = false
}

// Both kinds of pre-loaded row -- a Fuel -> Sales credit invoice and a plain Accounting
// invoice on a fuel revenue account -- are locked the same way: read-only, not removable,
// linked to the invoice they came from. Only the label differs.
const isLocked = (row: { pending_fuel_invoice?: boolean; pending_accounting_invoice?: boolean }) =>
    !!row.pending_fuel_invoice || !!row.pending_accounting_invoice

const onCustomerSelected = (row: (typeof rows.value)[number], entity: {
    name: string
    credit_limit?: number
    current_balance?: number
    is_credit_blocked?: boolean
}) => {
    row.customer_name = entity.name
    row.credit_limit = entity.credit_limit ?? 0
    row.current_balance = entity.current_balance ?? 0
    row.is_credit_blocked = entity.is_credit_blocked ?? false
}
</script>

<template>
  <section class="space-y-4">
    <div>
      <h4 class="text-sm font-semibold">{{ t('meterCreditSales') }}</h4>
      <p class="text-xs text-muted-foreground">{{ t('meterCreditHelp') }}</p>
    </div>
    <InputError :message="errors.credit_sales" />
    <div v-for="(row, index) in rows" :key="index"
      class="grid gap-3 sm:grid-cols-[2fr_1fr_1fr_1fr_1fr_auto] sm:items-end"
      :class="isLocked(row) ? 'rounded-md bg-muted/50 p-3' : ''">
      <div class="space-y-1">
        <Label :id="`credit-customer-${index}`">Customer</Label>
        <EntitySearch v-if="!isLocked(row)" v-model="row.customer_id" entity-type="customer" :allow-quick-add="true" :disabled="disabled"
          :company-slug="companySlug"
          :aria-labelledby="`credit-customer-${index}`"
          :initial-entity="row.customer_name ? { id: row.customer_id, name: row.customer_name } : null"
          @entity-selected="(entity) => onCustomerSelected(row, entity)"
          @quick-add-click="(query) => openQuickAdd(index, query)" />
        <div v-else class="flex h-9 items-center text-sm text-muted-foreground">{{ row.customer_name }}</div>
        <InputError :message="errors[`credit_sales.${index}.customer_id`]" />
        <!-- Blocking: a stop sign, distinct from the over-limit warning below. The
             server refuses this outright (DailyCloseCreditSaleService::prepare); this
             tells the user before they try. -->
        <p v-if="!isLocked(row) && row.is_credit_blocked"
          class="flex items-center gap-1 text-xs font-medium text-destructive">
          <Ban class="h-3.5 w-3.5" />{{ row.customer_name || 'This buyer' }} is blocked from credit sales.
        </p>
      </div>
      <div class="space-y-1">
        <Label :for="`credit-fuel-${index}`">Fuel</Label>
        <Select v-if="!isLocked(row)" v-model="row.item_id" :disabled="disabled">
          <SelectTrigger :id="`credit-fuel-${index}`"><SelectValue placeholder="None" /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="item in fuelItems ?? []" :key="item.id" :value="item.id">{{ item.name }}</SelectItem>
          </SelectContent>
        </Select>
        <InputError :message="errors[`credit_sales.${index}.item_id`]" />
      </div>
      <div class="space-y-1">
        <Label :for="`credit-litres-${index}`">Litres</Label>
        <Input v-if="!isLocked(row)" :id="`credit-litres-${index}`" v-model.number="row.litres" type="number" min="0.01" step="0.01" :disabled="disabled" />
        <InputError :message="errors[`credit_sales.${index}.litres`]" />
        <p v-if="!isLocked(row) && missingLitres(row)" class="flex items-start gap-1 text-xs text-status-attention">
          <TriangleAlert class="mt-0.5 h-3.5 w-3.5 shrink-0" />Enter litres to apply this buyer's per-litre discount.
        </p>
      </div>
      <div class="space-y-1">
        <Label :for="`credit-amount-${index}`">Amount</Label>
        <Input v-if="!isLocked(row)" :id="`credit-amount-${index}`" v-model.number="row.amount" type="number" min="0.01" step="0.01" :disabled="disabled" />
        <div v-else :id="`credit-amount-${index}`" class="flex h-9 items-center text-sm text-muted-foreground">{{ row.amount }}</div>
        <InputError :message="errors[`credit_sales.${index}.amount`]" />
        <!-- Non-blocking: this amount is still allowed to post (warn-don't-block), it
             just needs saying out loud before the buyer's balance moves. -->
        <p v-if="!isLocked(row) && !row.is_credit_blocked && isOverLimit(row)"
          class="flex items-start gap-1 text-xs text-status-attention">
          <TriangleAlert class="mt-0.5 h-3.5 w-3.5 shrink-0" />
          <span>
            Over the <MoneyText :amount="row.credit_limit ?? 0" :currency="currency ?? 'PKR'" /> limit:
            balance <MoneyText :amount="row.current_balance ?? 0" :currency="currency ?? 'PKR'" /> would become
            <MoneyText :amount="resultingBalance(row)" :currency="currency ?? 'PKR'" />.
          </span>
        </p>
        <p v-if="!isLocked(row) && discountFor(row) && !missingLitres(row)" class="text-xs text-status-success">
          Discount <MoneyText :amount="discountAmount(row)" :currency="currency ?? 'PKR'" /> ·
          Owes <MoneyText :amount="Math.max(0, Number(row.amount || 0) - discountAmount(row))" :currency="currency ?? 'PKR'" />
        </p>
      </div>
      <div class="space-y-1">
        <Label :for="`credit-reference-${index}`">{{ t('creditReference') }}</Label>
        <Input v-if="!isLocked(row)" :id="`credit-reference-${index}`" v-model="row.reference" maxlength="100" :disabled="disabled" />
        <a v-else-if="row.invoice_id && companySlug" :href="`/${companySlug}/invoices/${row.invoice_id}`"
          class="flex h-9 items-center gap-1 text-sm text-primary underline-offset-2 hover:underline">
          <Lock class="h-3 w-3" />
          <span v-if="row.pending_accounting_invoice">Invoiced in Accounting · {{ row.invoice_number ?? row.reference }} · {{ row.customer_name }}</span>
          <span v-else>From invoice {{ row.invoice_number ?? row.reference }}</span>
        </a>
        <div v-else class="flex h-9 items-center gap-1 text-sm text-muted-foreground">
          <Lock class="h-3 w-3" />From invoice {{ row.invoice_number ?? row.reference }}
        </div>
        <InputError :message="errors[`credit_sales.${index}.reference`]" />
      </div>
      <Button v-if="!isLocked(row)" type="button" variant="ghost" size="icon" aria-label="Remove credit sale" :disabled="disabled" @click="rows.splice(index, 1)"><Trash2 class="h-4 w-4" /></Button>
      <div v-else class="h-9 w-9" aria-hidden="true" />
    </div>
    <Button type="button" variant="outline" size="sm" :disabled="disabled" @click="rows.push({ customer_id: '', customer_name: '', amount: 0, reference: '' })">
      <Plus class="mr-2 h-4 w-4" />{{ t('addCreditCustomer') }}
    </Button>
    <QuickAddModal v-model:open="showQuickAdd" entity-type="customer" :initial-name="quickAddQuery" @created="onCustomerCreated" />
  </section>
</template>
