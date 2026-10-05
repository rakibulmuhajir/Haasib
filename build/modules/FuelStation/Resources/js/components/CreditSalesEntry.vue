<script setup lang="ts">
import { computed, ref } from 'vue'
import EntitySearch from '@/components/forms/EntitySearch.vue'
import { usePage } from '@inertiajs/vue3'
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
    unit_id?: string
    item_id?: string
    litres?: number
    invoice_id?: string
    invoice_number?: string
    pending_fuel_invoice?: boolean
    pending_accounting_invoice?: boolean
    // Edit day kept this row's invoice because a later payment settled it; posting re-uses it.
    kept_invoice_id?: string
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
    // The day's sale rate per fuel item (RateChange::getRateForDate), the same rate the
    // meters are priced at -- a row's amount is litres x this rate.
    rates?: Record<string, { sale_rate: number }>
    // Each customer's active units, for the unit picker (DailyCloseController::customerChoices).
    customerChoices?: Array<{ id: string; units?: Array<{ id: string; name: string }> }>
}>()

// This row's customer's active units (vehicles), if they use them; empty for a customer with none.
// The reference is the slip number either way.
const unitsFor = (row: { customer_id: string }): Array<{ id: string; name: string }> =>
    props.customerChoices?.find((c) => c.id === row.customer_id)?.units ?? []

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

type Row = (typeof rows.value)[number]
const rateFor = (row: Row): number => (row.item_id ? Number(props.rates?.[row.item_id]?.sale_rate ?? 0) : 0)
const round2 = (n: number) => Math.round(n * 100) / 100

// Litres drive the amount at the day's rate; typing an amount instead works back to litres,
// so either figure off the slip can be entered. With no fuel (or no rate) the amount is manual.
const onLitresInput = (row: Row, value: unknown) => {
    row.litres = value === '' || value === null ? undefined : Number(value)
    const rate = rateFor(row)
    if (rate > 0 && row.litres) row.amount = round2(row.litres * rate)
}
const onAmountInput = (row: Row, value: unknown) => {
    row.amount = Number(value || 0)
    const rate = rateFor(row)
    if (rate > 0 && row.amount > 0) row.litres = round2(row.amount / rate)
}
const onFuelChange = (row: Row) => {
    const rate = rateFor(row)
    if (rate > 0 && row.litres) row.amount = round2(Number(row.litres) * rate)
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
// Amanat (safe-deposit) customers take fuel against their deposit under Amanat Disbursements;
// they are never offered as credit buyers. Read from the close page's own props.
const amanatHolderIds = computed<string[]>(() =>
    ((usePage().props as any).amanatHolders ?? []).map((holder: { id: string }) => holder.id),
)

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
const isLocked = (row: { pending_fuel_invoice?: boolean; pending_accounting_invoice?: boolean; kept_invoice_id?: string }) =>
    !!row.pending_fuel_invoice || !!row.pending_accounting_invoice || !!row.kept_invoice_id

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
  <section class="space-y-2">
    <div class="flex items-baseline justify-between">
      <h4 class="font-medium">Sale</h4>
      <MoneyText class="text-sm font-medium" :amount="rows.reduce((t, r) => t + Number(r.amount || 0), 0)" :currency="currency ?? 'PKR'" :fraction-digits="0" />
    </div>
    <InputError :message="errors.credit_sales" />
    <div v-for="(row, index) in rows" :key="index">
      <!-- Pre-loaded from an invoice: read-only, links to it. -->
      <p v-if="isLocked(row)" class="flex flex-wrap items-center gap-x-2 text-sm">
        <Lock class="h-3 w-3 text-muted-foreground" />
        <span class="font-medium">{{ row.customer_name }}</span>
        <a v-if="(row.invoice_id || row.kept_invoice_id) && companySlug" :href="`/${companySlug}/invoices/${row.invoice_id || row.kept_invoice_id}`" class="text-primary underline-offset-2 hover:underline">
          {{ row.invoice_number ?? row.reference }}
        </a>
        <span v-else class="text-muted-foreground">{{ row.invoice_number ?? row.reference }}</span>
        <MoneyText class="ml-auto font-medium" :amount="row.amount" :currency="currency ?? 'PKR'" :fraction-digits="0" />
      </p>
      <div v-else class="grid items-start gap-2 md:grid-cols-[16rem_12rem_7rem_9rem_minmax(0,12rem)_minmax(0,12rem)_2.25rem]">
        <div>
          <EntitySearch v-model="row.customer_id" entity-type="customer" :allow-quick-add="true" :disabled="disabled"
            :company-slug="companySlug"
            :exclude-ids="amanatHolderIds"
            :aria-label="`Customer, row ${index + 1}`"
            :initial-entity="row.customer_name ? { id: row.customer_id, name: row.customer_name } : null"
            @entity-selected="(entity) => onCustomerSelected(row, entity)"
            @quick-add-click="(query) => openQuickAdd(index, query)" />
          <InputError :message="errors[`credit_sales.${index}.customer_id`]" />
          <p v-if="row.is_credit_blocked" class="flex items-center gap-1 text-xs font-medium text-destructive">
            <Ban class="h-3.5 w-3.5" />Credit blocked
          </p>
        </div>
        <div>
          <Select v-model="row.item_id" :disabled="disabled" @update:model-value="onFuelChange(row)">
            <SelectTrigger class="h-9" :aria-label="`Fuel, row ${index + 1}`"><SelectValue placeholder="Fuel" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="item in fuelItems ?? []" :key="item.id" :value="item.id">{{ item.name }}</SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="errors[`credit_sales.${index}.item_id`]" />
        </div>
        <div>
          <Input class="h-9 text-right" :model-value="row.litres" type="number" min="0.01" step="0.01" placeholder="Litres" :aria-label="`Litres, row ${index + 1}`" :disabled="disabled" @focus="(e: FocusEvent) => (e.target as HTMLInputElement).select()" @update:model-value="(v) => onLitresInput(row, v)" />
          <p v-if="rateFor(row) > 0" class="text-xs text-muted-foreground">@ {{ rateFor(row) }}</p>
          <InputError :message="errors[`credit_sales.${index}.litres`]" />
          <p v-if="missingLitres(row)" class="text-xs text-status-attention">Litres needed for discount</p>
        </div>
        <div>
          <Input class="h-9 text-right" :model-value="row.amount" type="number" min="0.01" step="0.01" placeholder="Amount" :aria-label="`Amount, row ${index + 1}`" :disabled="disabled" @focus="(e: FocusEvent) => (e.target as HTMLInputElement).select()" @update:model-value="(v) => onAmountInput(row, v)" />
          <InputError :message="errors[`credit_sales.${index}.amount`]" />
          <p v-if="!row.is_credit_blocked && isOverLimit(row)" class="text-xs text-status-attention">
            Over limit <MoneyText :amount="row.credit_limit ?? 0" :currency="currency ?? 'PKR'" :fraction-digits="0" />
          </p>
          <p v-if="discountFor(row) && !missingLitres(row)" class="text-xs text-status-success">
            Discount <MoneyText :amount="discountAmount(row)" :currency="currency ?? 'PKR'" :fraction-digits="0" />
          </p>
        </div>
        <div v-if="unitsFor(row).length">
          <Select v-model="row.unit_id" :disabled="disabled">
            <SelectTrigger class="h-9" :aria-label="`Vehicle, row ${index + 1}`"><SelectValue placeholder="Vehicle" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="unit in unitsFor(row)" :key="unit.id" :value="unit.id">{{ unit.name }}</SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="errors[`credit_sales.${index}.unit_id`]" />
        </div>
        <div v-else />
        <div>
          <Input class="h-9" v-model="row.reference" maxlength="100" placeholder="Slip no." :aria-label="`Slip number, row ${index + 1}`" :disabled="disabled" />
          <InputError :message="errors[`credit_sales.${index}.reference`]" />
        </div>
        <Button type="button" variant="ghost" size="icon" class="h-9 w-9" aria-label="Remove sale" :disabled="disabled" @click="rows.splice(index, 1)"><Trash2 class="h-4 w-4" /></Button>
      </div>
    </div>
    <button type="button" class="text-xs text-primary underline-offset-2 hover:underline" :disabled="disabled" @click="rows.push({ customer_id: '', customer_name: '', amount: 0, reference: '' })">+ Add another</button>
    <QuickAddModal v-model:open="showQuickAdd" entity-type="customer" :initial-name="quickAddQuery" @created="onCustomerCreated" />
  </section>
</template>
