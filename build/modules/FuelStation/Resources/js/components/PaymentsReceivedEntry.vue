<script setup lang="ts">
import { computed, ref } from 'vue'
import EntitySearch from '@/components/forms/EntitySearch.vue'
import QuickAddModal from '@/components/forms/QuickAddModal.vue'
import InputError from '@/components/InputError.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Plus, Trash2 } from 'lucide-vue-next'

interface PaymentRow {
    customer_id: string
    customer_name: string
    invoice_id: string
    invoice_number?: string
    amount: number
    payment_account_id: string
    reference: string
}

interface OpenInvoice {
    id: string
    invoice_number: string
    customer_id: string
    customer_name: string
    balance: number
    currency: string
}

const rows = defineModel<PaymentRow[]>({ required: true })
const props = defineProps<{
    errors: Record<string, string>
    disabled: boolean
    openInvoices: OpenInvoice[]
    paymentAccounts: Array<{ id: string; code: string; name: string }>
    currency?: string
}>()

const invoicesFor = (customerId: string) => props.openInvoices.filter((inv) => inv.customer_id === customerId)
const invoiceById = (id: string) => props.openInvoices.find((inv) => inv.id === id)

const onCustomerSelected = (row: PaymentRow, entity: { id: string; name: string }) => {
    row.customer_id = entity.id
    row.customer_name = entity.name
    row.invoice_id = ''
    row.invoice_number = ''
    // Auto-select the only open invoice for this buyer, same convenience as the
    // standalone Payment create page.
    const options = invoicesFor(entity.id)
    if (options.length === 1) {
        row.invoice_id = options[0].id
        row.invoice_number = options[0].invoice_number
        row.amount = options[0].balance
    }
}

const onInvoiceSelected = (row: PaymentRow, invoiceId: string) => {
    row.invoice_id = invoiceId
    const invoice = invoiceById(invoiceId)
    row.invoice_number = invoice?.invoice_number
    if (invoice && !row.amount) row.amount = invoice.balance
}

const totalAmount = computed(() => rows.value.reduce((sum, row) => sum + Number(row.amount || 0), 0))

// Inline "new credit buyer" without leaving the close, same pattern as CreditSalesEntry.
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
        onCustomerSelected(rows.value[quickAddIndex.value], customer)
    }
    showQuickAdd.value = false
}
</script>

<template>
  <section class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h4 class="font-medium">Payments Received</h4>
        <p class="text-xs text-muted-foreground">A buyer settling a credit invoice, entered here instead of at Payments.</p>
      </div>
      <Button type="button" variant="outline" size="sm" :disabled="disabled" @click="rows.push({ customer_id: '', customer_name: '', invoice_id: '', amount: 0, payment_account_id: '', reference: '' })">
        <Plus class="mr-1 h-4 w-4" /> Add
      </Button>
    </div>
    <InputError :message="errors.payments_received" />

    <div v-for="(row, index) in rows" :key="index" class="grid grid-cols-12 items-end gap-3">
      <div class="col-span-3 space-y-1">
        <Label :id="`payment-customer-${index}`">Buyer</Label>
        <EntitySearch v-model="row.customer_id" entity-type="customer" :allow-quick-add="true" :disabled="disabled"
          :aria-labelledby="`payment-customer-${index}`"
          :initial-entity="row.customer_name ? { id: row.customer_id, name: row.customer_name } : null"
          @entity-selected="(entity) => onCustomerSelected(row, entity)"
          @quick-add-click="(query) => openQuickAdd(index, query)" />
        <InputError :message="errors[`payments_received.${index}.customer_id`]" />
      </div>
      <div class="col-span-3 space-y-1">
        <Label :for="`payment-invoice-${index}`">Invoice</Label>
        <Select :model-value="row.invoice_id" :disabled="disabled || !row.customer_id" @update:model-value="(v) => onInvoiceSelected(row, String(v))">
          <SelectTrigger :id="`payment-invoice-${index}`"><SelectValue :placeholder="row.customer_id ? 'Select invoice' : 'Choose a buyer first'" /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="invoice in invoicesFor(row.customer_id)" :key="invoice.id" :value="invoice.id">
              {{ invoice.invoice_number }} — <MoneyText :amount="invoice.balance" :currency="invoice.currency" /> due
            </SelectItem>
          </SelectContent>
        </Select>
        <InputError :message="errors[`payments_received.${index}.invoice_id`]" />
      </div>
      <div class="col-span-2 space-y-1">
        <Label :for="`payment-account-${index}`">Received into</Label>
        <Select v-model="row.payment_account_id" :disabled="disabled">
          <SelectTrigger :id="`payment-account-${index}`"><SelectValue placeholder="Account" /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="account in paymentAccounts" :key="account.id" :value="account.id">{{ account.code }} - {{ account.name }}</SelectItem>
          </SelectContent>
        </Select>
        <InputError :message="errors[`payments_received.${index}.payment_account_id`]" />
      </div>
      <div class="col-span-2 space-y-1">
        <Label :for="`payment-amount-${index}`">Amount</Label>
        <Input :id="`payment-amount-${index}`" v-model.number="row.amount" type="number" min="0.01" step="0.01" :disabled="disabled" />
        <InputError :message="errors[`payments_received.${index}.amount`]" />
      </div>
      <div class="col-span-1 space-y-1">
        <Label :for="`payment-reference-${index}`">Ref</Label>
        <Input :id="`payment-reference-${index}`" v-model="row.reference" maxlength="100" :disabled="disabled" />
      </div>
      <Button type="button" variant="ghost" size="icon" aria-label="Remove payment" :disabled="disabled" @click="rows.splice(index, 1)"><Trash2 class="h-4 w-4" /></Button>
    </div>

    <div v-if="rows.length" class="flex justify-between text-sm font-medium">
      <span>Total Payments Received</span>
      <MoneyText :amount="totalAmount" :currency="currency ?? 'PKR'" />
    </div>

    <QuickAddModal v-model:open="showQuickAdd" entity-type="customer" :initial-name="quickAddQuery" @created="onCustomerCreated" />
  </section>
</template>
