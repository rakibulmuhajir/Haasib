<template>
  <div class="space-y-4">
    <!-- Line Items Header -->
    <div class="flex justify-between items-center">
      <h3 class="text-lg font-medium text-gray-900">Line Items</h3>
      <Button
        label="Add Item"
        icon="pi pi-plus"
        class="p-button-text p-button-sm"
        @click="addLineItem"
      />
    </div>

    <!-- Line Items Table -->
    <div class="overflow-hidden border border-gray-200 rounded-lg">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Item/Service
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Description
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
              Qty
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
              Unit Price
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
              Discount
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
              Tax
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
              Total
            </th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="(item, index) in modelValue" :key="item.id || index" class="hover:bg-gray-50">
            <!-- Item/Service -->
            <td class="px-4 py-3">
              <InputText
                v-model="item.name"
                placeholder="Item name or service"
                class="w-full"
                @update:modelValue="updateLineItem(index, 'name', $event)"
              />
            </td>
            
            <!-- Description -->
            <td class="px-4 py-3">
              <Textarea
                v-model="item.description"
                :rows="1"
                placeholder="Description"
                class="w-full resize-none"
                @update:modelValue="updateLineItem(index, 'description', $event)"
              />
            </td>
            
            <!-- Quantity -->
            <td class="px-4 py-3">
              <InputNumber
                v-model="item.quantity"
                :min="0.01"
                :step="1"
                class="w-24 text-right"
                @update:modelValue="updateLineItem(index, 'quantity', $event)"
              />
            </td>
            
            <!-- Unit Price -->
            <td class="px-4 py-3">
              <InputNumber
                v-model="item.unit_price"
                :min="0"
                :locale="locale"
                mode="currency"
                :currency="currencyCode"
                class="w-32 text-right"
                @update:modelValue="updateLineItem(index, 'unit_price', $event)"
              />
            </td>
            
            <!-- Discount -->
            <td class="px-4 py-3">
              <div class="flex items-center space-x-1">
                <InputNumber
                  v-model="item.discount"
                  :min="0"
                  :max="100"
                  :suffix="discountType === 'percentage' ? '%' : ''"
                  class="w-20 text-right"
                  @update:modelValue="updateLineItem(index, 'discount', $event)"
                />
                <Button
                  icon="pi pi-percentage"
                  class="p-button-text p-button-xs"
                  :class="{ 'p-button-primary': discountType === 'percentage' }"
                  @click="toggleDiscountType"
                />
              </div>
            </td>
            
            <!-- Tax -->
            <td class="px-4 py-3">
              <Dropdown
                v-model="item.tax_rate_id"
                :options="taxRates"
                optionLabel="name"
                optionValue="id"
                placeholder="Select tax"
                class="w-32"
                @change="updateLineItem(index, 'tax_rate_id', $event.value)"
              >
                <template #option="{ option }">
                  <div class="flex justify-between items-center w-full">
                    <span>{{ option.name }}</span>
                    <span class="text-gray-500">{{ option.rate }}%</span>
                  </div>
                </template>
              </Dropdown>
            </td>
            
            <!-- Total -->
            <td class="px-4 py-3 text-right font-medium">
              {{ formatMoney(calculateLineTotal(item), typeof props.currency === 'string' ? { code: props.currency } : props.currency) }}
            </td>
            
            <!-- Actions -->
            <td class="px-4 py-3 text-center">
              <Button
                icon="pi pi-trash"
                class="p-button-text p-button-rounded p-button-sm text-red-600"
                @click="removeLineItem(index)"
              />
            </td>
          </tr>
          
          <!-- Empty State -->
          <tr v-if="modelValue.length === 0">
            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
              <div class="space-y-2">
                <i class="pi pi-inbox text-3xl text-gray-300"></i>
                <p>No line items added yet</p>
                <Button
                  label="Add your first item"
                  icon="pi pi-plus"
                  class="p-button-outlined p-button-sm"
                  @click="addLineItem"
                />
              </div>
            </td>
          </tr>
        </tbody>
        
        <!-- Footer with Totals -->
        <tfoot v-if="modelValue.length > 0" class="bg-gray-50">
          <tr>
            <td colspan="4" class="px-4 py-3"></td>
            <td colspan="2" class="px-4 py-3 text-right font-medium text-gray-700">
              Subtotal:
            </td>
            <td class="px-4 py-3 text-right font-medium">
              {{ formatMoney(subtotal, typeof props.currency === 'string' ? { code: props.currency } : props.currency) }}
            </td>
            <td></td>
          </tr>
          <tr>
            <td colspan="4" class="px-4 py-3"></td>
            <td colspan="2" class="px-4 py-3 text-right font-medium text-gray-700">
              Total Discount:
            </td>
            <td class="px-4 py-3 text-right font-medium text-green-600">
              -{{ formatMoney(totalDiscount, typeof props.currency === 'string' ? { code: props.currency } : props.currency) }}
            </td>
            <td></td>
          </tr>
          <tr>
            <td colspan="4" class="px-4 py-3"></td>
            <td colspan="2" class="px-4 py-3 text-right font-medium text-gray-700">
              Total Tax:
            </td>
            <td class="px-4 py-3 text-right font-medium">
              {{ formatMoney(totalTax, typeof props.currency === 'string' ? { code: props.currency } : props.currency) }}
            </td>
            <td></td>
          </tr>
          <tr class="border-t-2 border-gray-300">
            <td colspan="4" class="px-4 py-3"></td>
            <td colspan="2" class="px-4 py-3 text-right font-bold text-lg text-gray-900">
              Total:
            </td>
            <td class="px-4 py-3 text-right font-bold text-lg">
              {{ formatMoney(total, typeof props.currency === 'string' ? { code: props.currency } : props.currency) }}
            </td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import Dropdown from 'primevue/dropdown'
import Button from 'primevue/button'

interface LineItem {
  id?: number
  name: string
  description?: string
  quantity: number
  unit_price: number
  discount: number
  discount_type?: 'fixed' | 'percentage'
  tax_rate_id?: number | null
  tax_amount?: number
}

interface TaxRate {
  id: number
  name: string
  rate: number
}

interface Props {
  modelValue: LineItem[]
  currency?: string | { code?: string; symbol?: string }
  taxRates: TaxRate[]
  locale?: string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: () => [],
  currency: 'USD',
  taxRates: () => [],
  locale: 'en-US'
})

const emit = defineEmits<{
  'update:modelValue': [value: LineItem[]]
  'item-added': [item: LineItem]
  'item-removed': [item: LineItem, index: number]
  'item-updated': [item: LineItem, index: number]
}>()

const discountType = ref<'fixed' | 'percentage'>('percentage')

// Computed totals
const subtotal = computed(() => {
  return props.modelValue.reduce((sum, item) => {
    return sum + (item.quantity * item.unit_price)
  }, 0)
})

const totalDiscount = computed(() => {
  return props.modelValue.reduce((sum, item) => {
    if (discountType.value === 'percentage') {
      return sum + (item.quantity * item.unit_price * item.discount / 100)
    } else {
      return sum + item.discount
    }
  }, 0)
})

const totalTax = computed(() => {
  return props.modelValue.reduce((sum, item) => {
    const taxRate = props.taxRates.find(t => t.id === item.tax_rate_id)
    const taxableAmount = item.quantity * item.unit_price
    if (taxRate) {
      return sum + (taxableAmount * taxRate.rate / 100)
    }
    return sum + (item.tax_amount || 0)
  }, 0)
})

const total = computed(() => {
  return subtotal.value - totalDiscount.value + totalTax.value
})

const currencyCode = computed(() => {
  if (typeof props.currency === 'string') {
    return props.currency
  }
  return props.currency?.code || 'USD'
})

// Methods
const addLineItem = () => {
  const newItem: LineItem = {
    name: '',
    description: '',
    quantity: 1,
    unit_price: 0,
    discount: 0,
    discount_type: discountType.value,
    tax_rate_id: null,
    tax_amount: 0
  }
  
  emit('update:modelValue', [...props.modelValue, newItem])
  emit('item-added', newItem)
}

const removeLineItem = (index: number) => {
  const item = props.modelValue[index]
  const newItems = props.modelValue.filter((_, i) => i !== index)
  emit('update:modelValue', newItems)
  emit('item-removed', item, index)
}

const updateLineItem = (index: number, field: keyof LineItem, value: any) => {
  const newItems = [...props.modelValue]
  newItems[index] = { ...newItems[index], [field]: value }
  emit('update:modelValue', newItems)
  emit('item-updated', newItems[index], index)
}

const calculateLineTotal = (item: LineItem) => {
  const lineTotal = item.quantity * item.unit_price
  const discount = discountType.value === 'percentage' 
    ? lineTotal * item.discount / 100 
    : Math.min(item.discount, lineTotal)
  const taxRate = props.taxRates.find(t => t.id === item.tax_rate_id)
  const tax = taxRate ? (lineTotal - discount) * taxRate.rate / 100 : (item.tax_amount || 0)
  
  return lineTotal - discount + tax
}

const toggleDiscountType = () => {
  discountType.value = discountType.value === 'percentage' ? 'fixed' : 'percentage'
}

const formatMoney = (amount: number, currency: string) => {
  return new Intl.NumberFormat(props.locale, {
    style: 'currency',
    currency: currency || 'USD'
  }).format(amount)
}
</script>

<style scoped>
/* Component-specific styles */
</style>