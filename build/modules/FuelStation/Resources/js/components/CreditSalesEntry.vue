<script setup lang="ts">
import { ref } from 'vue'
import EntitySearch from '@/components/forms/EntitySearch.vue'
import QuickAddModal from '@/components/forms/QuickAddModal.vue'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useLexicon } from '@/composables/useLexicon'
import { Plus, Trash2, Lock } from 'lucide-vue-next'

const rows = defineModel<Array<{
    customer_id: string
    customer_name: string
    amount: number
    reference: string
    invoice_id?: string
    invoice_number?: string
    pending_fuel_invoice?: boolean
}>>({ required: true })
const props = defineProps<{ errors: Record<string, string>; disabled: boolean; companySlug?: string }>()
const { t } = useLexicon()

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
    }
    showQuickAdd.value = false
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
          :aria-labelledby="`credit-customer-${index}`"
          :initial-entity="row.customer_name ? { id: row.customer_id, name: row.customer_name } : null"
          @entity-selected="row.customer_name = $event.name"
          @quick-add-click="(query) => openQuickAdd(index, query)" />
        <div v-else class="flex h-9 items-center text-sm text-muted-foreground">{{ row.customer_name }}</div>
        <InputError :message="errors[`credit_sales.${index}.customer_id`]" />
      </div>
      <div class="space-y-1">
        <Label :for="`credit-amount-${index}`">Amount</Label>
        <Input v-if="!row.pending_fuel_invoice" :id="`credit-amount-${index}`" v-model.number="row.amount" type="number" min="0.01" step="0.01" :disabled="disabled" />
        <div v-else :id="`credit-amount-${index}`" class="flex h-9 items-center text-sm text-muted-foreground">{{ row.amount }}</div>
        <InputError :message="errors[`credit_sales.${index}.amount`]" />
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
