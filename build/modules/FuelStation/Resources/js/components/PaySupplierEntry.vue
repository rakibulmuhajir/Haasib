<script setup lang="ts">
import { computed } from 'vue'
import EntitySearch from '@/components/forms/EntitySearch.vue'
import InputError from '@/components/InputError.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Plus, Trash2 } from 'lucide-vue-next'

interface PaySupplierRow {
    vendor_id: string
    vendor_name: string
    amount: number
    payment_account_id: string
    reference: string
}

const rows = defineModel<PaySupplierRow[]>({ required: true })
const props = defineProps<{
    errors: Record<string, string>
    disabled: boolean
    paymentAccounts: Array<{ id: string; code: string; name: string }>
    defaultAccountId?: string | null
    currency?: string
}>()

const onVendorSelected = (row: PaySupplierRow, entity: { id: string; name: string }) => {
    row.vendor_id = entity.id
    row.vendor_name = entity.name
}

const addRow = () => {
    rows.value.push({
        vendor_id: '',
        vendor_name: '',
        amount: 0,
        payment_account_id: props.defaultAccountId ?? '',
        reference: '',
    })
}

const totalAmount = computed(() => rows.value.reduce((sum, row) => sum + Number(row.amount || 0), 0))
</script>

<template>
  <section class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h4 class="font-medium">Pay Supplier</h4>
        <p class="text-xs text-muted-foreground">Pays open bills first, oldest first; anything more is held as an advance for the supplier's next bills.</p>
      </div>
      <Button type="button" variant="outline" size="sm" :disabled="disabled" @click="addRow">
        <Plus class="mr-1 h-4 w-4" /> Add
      </Button>
    </div>
    <InputError :message="errors.pay_suppliers" />

    <div v-for="(row, index) in rows" :key="index" class="grid grid-cols-12 items-end gap-3">
      <div class="col-span-4 space-y-1">
        <Label :id="`pay-supplier-vendor-${index}`">Supplier</Label>
        <EntitySearch
          v-model="row.vendor_id"
          entity-type="vendor"
          :allow-quick-add="false"
          :disabled="disabled"
          :aria-labelledby="`pay-supplier-vendor-${index}`"
          :initial-entity="row.vendor_name ? { id: row.vendor_id, name: row.vendor_name } : null"
          @entity-selected="(entity) => onVendorSelected(row, entity)"
        />
        <InputError :message="errors[`pay_suppliers.${index}.vendor_id`]" />
      </div>
      <div class="col-span-3 space-y-1">
        <Label :for="`pay-supplier-account-${index}`">Paid from</Label>
        <Select v-model="row.payment_account_id" :disabled="disabled">
          <SelectTrigger :id="`pay-supplier-account-${index}`"><SelectValue placeholder="Account" /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="account in paymentAccounts" :key="account.id" :value="account.id">{{ account.code }} - {{ account.name }}</SelectItem>
          </SelectContent>
        </Select>
        <InputError :message="errors[`pay_suppliers.${index}.payment_account_id`]" />
      </div>
      <div class="col-span-2 space-y-1">
        <Label :for="`pay-supplier-amount-${index}`">Amount</Label>
        <Input :id="`pay-supplier-amount-${index}`" v-model.number="row.amount" type="number" min="0.01" step="0.01" :disabled="disabled" />
        <InputError :message="errors[`pay_suppliers.${index}.amount`]" />
      </div>
      <div class="col-span-2 space-y-1">
        <Label :for="`pay-supplier-reference-${index}`">Ref</Label>
        <Input :id="`pay-supplier-reference-${index}`" v-model="row.reference" maxlength="100" :disabled="disabled" />
      </div>
      <Button type="button" variant="ghost" size="icon" aria-label="Remove supplier payment row" :disabled="disabled" @click="rows.splice(index, 1)"><Trash2 class="h-4 w-4" /></Button>
    </div>

    <div v-if="rows.length" class="flex justify-between text-sm font-medium">
      <span>Total Pay Supplier</span>
      <MoneyText :amount="totalAmount" :currency="currency ?? 'PKR'" />
    </div>
  </section>
</template>
