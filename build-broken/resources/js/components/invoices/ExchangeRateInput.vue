<script setup lang="ts">
import { computed } from 'vue'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { RefreshCw, TrendingUp } from 'lucide-vue-next'

interface ExchangeRateInputProps {
  modelValue: number
  fromCurrency: string
  toCurrency: string
  disabled?: boolean
  showRefresh?: boolean
  class?: string
}

const props = withDefaults(defineProps<ExchangeRateInputProps>(), {
  disabled: false,
  showRefresh: false
})

const emit = defineEmits<{
  'update:modelValue': [value: number]
  'refresh': []
}>()

const displayValue = computed({
  get: () => props.modelValue,
  set: (value: number) => emit('update:modelValue', value)
})

const rateDescription = computed(() => {
  if (props.disabled || props.fromCurrency === props.toCurrency) {
    return 'Same currency'
  }
  return `1 ${props.fromCurrency} = ${props.modelValue} ${props.toCurrency}`
})

const refreshRate = () => {
  emit('refresh')
}
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-center gap-2">
      <Input
        v-model.number="displayValue"
        type="number"
        step="0.000001"
        min="0.000001"
        :disabled="props.disabled"
        :class="props.class"
        placeholder="1.000000"
      />
      <Button
        v-if="props.showRefresh && !props.disabled"
        type="button"
        variant="outline"
        size="sm"
        @click="refreshRate"
        class="px-2"
      >
        <RefreshCw class="h-3 w-3" />
      </Button>
    </div>
    
    <div class="flex items-center gap-1 text-xs text-muted-foreground">
      <TrendingUp class="h-3 w-3" />
      <span>{{ rateDescription }}</span>
    </div>
  </div>
</template>