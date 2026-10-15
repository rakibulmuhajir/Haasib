<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import MoneyText from '@/components/MoneyText.vue'
import { formatDateTime } from '@/lib/datetime'
import type { DailyClosePeriodDiscount } from '../types/dailyCloseDiscount'

defineProps<{
  discounts: DailyClosePeriodDiscount[]
  companySlug: string
  title?: string
}>()
</script>

<template>
  <section v-if="discounts.length" class="rounded-md border border-rule-default p-4">
    <h3 class="font-semibold">{{ title || 'Discount settlements · non-cash' }}</h3>
    <p class="mt-1 text-xs text-muted-foreground">Reduce customer balances. No cash received or paid; excluded from drawer totals.</p>
    <ul class="mt-3 space-y-2 text-sm">
      <li v-for="discount in discounts" :key="discount.id" class="flex justify-between gap-4">
        <div>
          <Link :href="`/${companySlug}/credit-notes/${discount.id}`" class="font-medium hover:underline">{{ discount.customer_name }} · {{ discount.number }}</Link>
          <p class="text-xs text-muted-foreground">{{ formatDateTime(discount.from, { mode: 'date' }) }} – {{ formatDateTime(discount.to, { mode: 'date' }) }}</p>
        </div>
        <MoneyText :amount="discount.amount" :currency="discount.currency" />
      </li>
    </ul>
  </section>
</template>
