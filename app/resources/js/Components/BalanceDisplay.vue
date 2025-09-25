<template>
  <div class="text-right">
    <div class="flex items-center justify-end gap-1">
      <i class="fas fa-wallet text-gray-400"></i>
      <span class="font-medium" :class="balanceClass">
        {{ formattedBalance }}
      </span>
    </div>
    <div class="text-xs text-gray-500" v-if="showRisk">
      <span :class="riskBadgeClass">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        {{ riskLevel || 'low' }} risk
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { formatMoney } from '@/Utils/formatting'

const props = defineProps<{
  balance: number
  currencyCode?: string
  riskLevel?: string
  showRisk?: boolean
}>()

const balanceClass = computed(() => {
  if (props.balance > 0) return 'text-red-600'
  if (props.balance < 0) return 'text-green-600'
  return 'text-gray-600'
})

const formattedBalance = computed(() => {
  return formatMoney(props.balance, props.currencyCode)
})

const riskBadgeClass = computed(() => {
  const risk = props.riskLevel?.toLowerCase() || 'low'
  switch (risk) {
    case 'high': return 'text-red-600 font-medium'
    case 'medium': return 'text-orange-600 font-medium'
    case 'low': return 'text-green-600 font-medium'
    default: return 'text-gray-600'
  }
})
</script>