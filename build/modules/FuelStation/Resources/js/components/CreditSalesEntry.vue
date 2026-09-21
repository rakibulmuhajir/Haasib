<script setup lang="ts">
import { ref } from 'vue'
import EntitySearch from '@/components/forms/EntitySearch.vue'
import QuickAddModal from '@/components/forms/QuickAddModal.vue'
import InputError from '@/components/InputError.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useLexicon } from '@/composables/useLexicon'
import { Plus, Trash2, Lock, TriangleAlert, Ban } from 'lucide-vue-next'

const rows = defineModel<Array<{
    customer_id: string
    customer_name: string
    amount: number
    reference: string
    invoice_id?: string
    invoice_number?: string
    pending_fuel_invoice?: boolean
    // Credit-limit context captured at selection time (see onCustomerSelected) so the
    // row can warn inline without a second round trip per keystroke on amount.
    credit_limit?: number
    current_balance?: number
    is_credit_blocked?: boolean
}>>({ required: true })
const props = defineProps<{ errors: Record<string, string>; disabled: boolean; companySlug?: string; currency?: string }>()
const { t } = useLexicon()

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
      class="grid gap-3 sm:grid-cols-[2fr_1fr_1fr_auto] sm:items-end"
      :class="row.pending_fuel_invoice ? 'rounded-md bg-muted/50 p-3' : ''">
      <div class="space-y-1">
        <Label :id="`credit-customer-${index}`">Customer</Label>
        <EntitySearch v-if="!row.pending_fuel_invoice" v-model="row.customer_id" entity-type="customer" :allow-quick-add="true" :disabled="disabled"
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
        <p v-if="!row.pending_fuel_invoice && row.is_credit_blocked"
          class="flex items-center gap-1 text-xs font-medium text-destructive">
          <Ban class="h-3.5 w-3.5" />{{ row.customer_name || 'This buyer' }} is blocked from credit sales.
        </p>
      </div>
      <div class="space-y-1">
        <Label :for="`credit-amount-${index}`">Amount</Label>
        <Input v-if="!row.pending_fuel_invoice" :id="`credit-amount-${index}`" v-model.number="row.amount" type="number" min="0.01" step="0.01" :disabled="disabled" />
        <div v-else :id="`credit-amount-${index}`" class="flex h-9 items-center text-sm text-muted-foreground">{{ row.amount }}</div>
        <InputError :message="errors[`credit_sales.${index}.amount`]" />
        <!-- Non-blocking: this amount is still allowed to post (warn-don't-block), it
             just needs saying out loud before the buyer's balance moves. -->
        <p v-if="!row.pending_fuel_invoice && !row.is_credit_blocked && isOverLimit(row)"
          class="flex items-start gap-1 text-xs text-status-attention">
          <TriangleAlert class="mt-0.5 h-3.5 w-3.5 shrink-0" />
          <span>
            Over the <MoneyText :amount="row.credit_limit ?? 0" :currency="currency ?? 'PKR'" /> limit:
            balance <MoneyText :amount="row.current_balance ?? 0" :currency="currency ?? 'PKR'" /> would become
            <MoneyText :amount="resultingBalance(row)" :currency="currency ?? 'PKR'" />.
          </span>
        </p>
      </div>
      <div class="space-y-1">
        <Label :for="`credit-reference-${index}`">{{ t('creditReference') }}</Label>
        <Input v-if="!row.pending_fuel_invoice" :id="`credit-reference-${index}`" v-model="row.reference" maxlength="100" :disabled="disabled" />
        <a v-else-if="row.invoice_id && companySlug" :href="`/${companySlug}/invoices/${row.invoice_id}`"
          class="flex h-9 items-center gap-1 text-sm text-primary underline-offset-2 hover:underline">
          <Lock class="h-3 w-3" />From invoice {{ row.invoice_number ?? row.reference }}
        </a>
        <div v-else class="flex h-9 items-center gap-1 text-sm text-muted-foreground">
          <Lock class="h-3 w-3" />From invoice {{ row.invoice_number ?? row.reference }}
        </div>
        <InputError :message="errors[`credit_sales.${index}.reference`]" />
      </div>
      <Button v-if="!row.pending_fuel_invoice" type="button" variant="ghost" size="icon" aria-label="Remove credit sale" :disabled="disabled" @click="rows.splice(index, 1)"><Trash2 class="h-4 w-4" /></Button>
      <div v-else class="h-9 w-9" aria-hidden="true" />
    </div>
    <Button type="button" variant="outline" size="sm" :disabled="disabled" @click="rows.push({ customer_id: '', customer_name: '', amount: 0, reference: '' })">
      <Plus class="mr-2 h-4 w-4" />{{ t('addCreditCustomer') }}
    </Button>
    <QuickAddModal v-model:open="showQuickAdd" entity-type="customer" :initial-name="quickAddQuery" @created="onCustomerCreated" />
  </section>
</template>
