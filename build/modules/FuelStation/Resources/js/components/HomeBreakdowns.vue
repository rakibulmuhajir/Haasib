<script setup lang="ts">
/**
 * What a period's purchases are made of, opened on demand: bought per product, each line linking to
 * its statement. Sales, cost and profit live in the profit statement (ProfitStatement.vue). Used by
 * the fuel home's Today and History tabs.
 */
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import MoneyText from '@/components/MoneyText.vue'
import { ChevronDown, ChevronRight } from 'lucide-vue-next'

export interface PurchaseLine { name: string; unit: string | null; quantity: number; amount: number; href: string | null }

defineProps<{
  purchaseProducts: PurchaseLine[]
  currency: string
}>()

const open = ref(false)
const qty = (v: number, unit: string | null) => `${new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(v)}${unit === 'L' ? ' L' : ''}`
</script>

<template>
  <div class="space-y-2 text-sm">
    <button type="button" class="inline-flex items-center gap-1 text-status-info hover:underline" :aria-expanded="open" @click="open = !open">
      <component :is="open ? ChevronDown : ChevronRight" class="h-3.5 w-3.5" />Purchases by product
    </button>

    <ul v-if="open" class="rounded-md border border-rule-subtle px-3 py-1 tabular-nums">
      <li v-for="p in purchaseProducts" :key="p.name" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1 last:border-0">
        <Link v-if="p.href" :href="p.href" class="underline-offset-2 hover:underline">{{ p.name }}</Link>
        <span v-else>{{ p.name }}</span>
        <span>{{ qty(p.quantity, p.unit) }} · <MoneyText :amount="p.amount" :currency="currency" :fraction-digits="0" /></span>
      </li>
      <li v-if="!purchaseProducts.length" class="py-2 text-center text-text-secondary">No purchases.</li>
    </ul>
  </div>
</template>
