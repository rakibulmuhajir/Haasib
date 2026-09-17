<script setup lang="ts">
import EntitySearch from '@/components/forms/EntitySearch.vue'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useLexicon } from '@/composables/useLexicon'
import { Plus, Trash2 } from 'lucide-vue-next'

const rows = defineModel<Array<{ customer_id: string; customer_name: string; amount: number; reference: string }>>({ required: true })
defineProps<{ errors: Record<string, string>; disabled: boolean }>()
const { t } = useLexicon()
</script>

<template>
  <section class="space-y-4">
    <div>
      <h4 class="text-sm font-semibold">{{ t('meterCreditSales') }}</h4>
      <p class="text-xs text-muted-foreground">{{ t('meterCreditHelp') }}</p>
    </div>
    <InputError :message="errors.credit_sales" />
    <div v-for="(row, index) in rows" :key="index" class="grid gap-3 sm:grid-cols-[2fr_1fr_1fr_auto] sm:items-end">
      <div class="space-y-1">
        <Label :id="`credit-customer-${index}`">Customer</Label>
        <EntitySearch v-model="row.customer_id" entity-type="customer" :allow-quick-add="false" :disabled="disabled"
          :aria-labelledby="`credit-customer-${index}`"
          :initial-entity="row.customer_name ? { id: row.customer_id, name: row.customer_name } : null"
          @entity-selected="row.customer_name = $event.name" />
        <InputError :message="errors[`credit_sales.${index}.customer_id`]" />
      </div>
      <div class="space-y-1">
        <Label :for="`credit-amount-${index}`">Amount</Label>
        <Input :id="`credit-amount-${index}`" v-model.number="row.amount" type="number" min="0.01" step="0.01" :disabled="disabled" />
        <InputError :message="errors[`credit_sales.${index}.amount`]" />
      </div>
      <div class="space-y-1">
        <Label :for="`credit-reference-${index}`">{{ t('creditReference') }}</Label>
        <Input :id="`credit-reference-${index}`" v-model="row.reference" maxlength="100" :disabled="disabled" />
        <InputError :message="errors[`credit_sales.${index}.reference`]" />
      </div>
      <Button type="button" variant="ghost" size="icon" aria-label="Remove credit sale" :disabled="disabled" @click="rows.splice(index, 1)"><Trash2 class="h-4 w-4" /></Button>
    </div>
    <Button type="button" variant="outline" size="sm" :disabled="disabled" @click="rows.push({ customer_id: '', customer_name: '', amount: 0, reference: '' })">
      <Plus class="mr-2 h-4 w-4" />{{ t('addCreditCustomer') }}
    </Button>
  </section>
</template>
