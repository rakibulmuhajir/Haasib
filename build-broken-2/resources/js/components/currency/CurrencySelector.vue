<script setup lang="ts">
import { computed } from 'vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface Currency {
  code: string
  name: string
  symbol: string
  display_name: string
  is_base?: boolean
  default_rate?: number
}

interface CurrencySelectorProps {
  modelValue?: string
  currencies: Currency[]
  placeholder?: string
  disabled?: boolean
  showSymbol?: boolean
  class?: string
}

const props = withDefaults(defineProps<CurrencySelectorProps>(), {
  placeholder: 'Select currency',
  disabled: false,
  showSymbol: true
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'change': [currency: Currency | null]
}>()

const selectedCurrency = computed(() => {
  return props.currencies.find(c => c.code === props.modelValue) || null
})

const updateValue = (value: string) => {
  emit('update:modelValue', value)
  const currency = props.currencies.find(c => c.code === value) || null
  emit('change', currency)
}
</script>

<template>
  <Select 
    :model-value="props.modelValue" 
    @update:model-value="updateValue"
    :disabled="props.disabled"
  >
    <SelectTrigger :class="props.class">
      <SelectValue>
        <span v-if="selectedCurrency" class="flex items-center gap-2">
          <span v-if="props.showSymbol" class="font-mono">{{ selectedCurrency.symbol }}</span>
          <span>{{ selectedCurrency.code }}</span>
          <span v-if="selectedCurrency.is_base" class="text-xs bg-blue-100 text-blue-800 px-1 rounded">BASE</span>
        </span>
        <span v-else class="text-muted-foreground">{{ props.placeholder }}</span>
      </SelectValue>
    </SelectTrigger>
    <SelectContent>
      <SelectItem
        v-for="currency in props.currencies"
        :key="currency.code"
        :value="currency.code"
        class="flex items-center justify-between"
      >
        <div class="flex items-center gap-2">
          <span v-if="props.showSymbol" class="font-mono w-6">{{ currency.symbol }}</span>
          <span class="font-medium">{{ currency.code }}</span>
          <span class="text-sm text-muted-foreground">{{ currency.name }}</span>
          <span v-if="currency.is_base" class="text-xs bg-blue-100 text-blue-800 px-1 rounded">BASE</span>
        </div>
        <span v-if="currency.default_rate && currency.default_rate !== 1" class="text-xs text-muted-foreground">
          Rate: {{ currency.default_rate }}
        </span>
      </SelectItem>
    </SelectContent>
  </Select>
</template>