<script setup lang="ts">
/**
 * What a period's headline figures are made of, opened on demand: sales and profit per product
 * (packaged lubricants too), expenses per account, purchases per product. Each line links to its
 * statement. Used by the fuel home's Today and History tabs.
 */
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import Hint from '@/components/Hint.vue'
import MoneyText from '@/components/MoneyText.vue'
import { ChevronDown, ChevronRight } from 'lucide-vue-next'

export interface ProductLine { name: string; unit: string | null; quantity: number; revenue: number; cogs: number; gross_profit: number; book_profit?: number | null; book_opening?: number | null; book_closing?: number | null; book_purchases?: number | null; estimated_cogs: boolean; direct_quantity?: number; href: string | null }
export interface ExpenseLine { name: string; code: string; amount: number; asset: boolean; href: string | null }
export interface PurchaseLine { name: string; unit: string | null; quantity: number; amount: number; href: string | null }

defineProps<{
  products: ProductLine[]
  expenseAccounts: ExpenseLine[]
  purchaseProducts: PurchaseLine[]
  currency: string
}>()

const open = ref<'sales' | 'expenses' | 'purchases' | null>(null)
const toggle = (k: 'sales' | 'expenses' | 'purchases') => { open.value = open.value === k ? null : k }
const qty = (v: number, unit: string | null) => `${new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(v)}${unit === 'L' ? ' L' : ''}`
const sum = <T,>(rows: T[], pick: (r: T) => number) => rows.reduce((t, r) => t + pick(r), 0)
const shown = (p: ProductLine) => p.book_profit ?? p.gross_profit
const money = (v: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(v)
const lnk = 'tabular-nums underline-offset-2 hover:underline'
</script>

<template>
  <div class="space-y-2 text-sm">
    <div class="flex flex-wrap gap-x-5 gap-y-1">
      <button type="button" class="inline-flex items-center gap-1 text-status-info hover:underline" :aria-expanded="open === 'sales'" @click="toggle('sales')">
        <component :is="open === 'sales' ? ChevronDown : ChevronRight" class="h-3.5 w-3.5" />Sales &amp; profit by product
      </button>
      <button type="button" class="inline-flex items-center gap-1 text-status-info hover:underline" :aria-expanded="open === 'expenses'" @click="toggle('expenses')">
        <component :is="open === 'expenses' ? ChevronDown : ChevronRight" class="h-3.5 w-3.5" />Expenses by account
      </button>
      <button type="button" class="inline-flex items-center gap-1 text-status-info hover:underline" :aria-expanded="open === 'purchases'" @click="toggle('purchases')">
        <component :is="open === 'purchases' ? ChevronDown : ChevronRight" class="h-3.5 w-3.5" />Purchases by product
      </button>
    </div>

    <!-- Sales, cost and profit per product -->
    <div v-if="open === 'sales'" class="overflow-x-auto rounded-md border border-rule-subtle">
      <table class="w-full tabular-nums">
        <thead class="text-xs text-text-secondary">
          <tr>
            <th class="px-3 py-1.5 text-left font-normal">Product</th>
            <th class="px-3 py-1.5 text-right font-normal">Sold</th>
            <th class="px-3 py-1.5 text-right font-normal">Sales</th>
            <th class="px-3 py-1.5 text-right font-normal">
              <Hint side="left">Cost<template #content>What the stock sold had cost. Packaged items without a bill use their average cost (est.).</template></Hint>
            </th>
            <th class="px-3 py-1.5 text-right font-normal">Profit</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in products" :key="p.name" class="border-t border-rule-subtle">
            <td class="px-3 py-1">
              <Link v-if="p.href" :href="p.href" class="underline-offset-2 hover:underline">{{ p.name }}</Link>
              <template v-else>{{ p.name }}</template>
            </td>
            <td class="px-3 py-1 text-right">
              <Hint v-if="p.direct_quantity" side="left">
                {{ qty(p.quantity, p.unit) }}
                <template #content>Includes {{ qty(p.direct_quantity, 'L') }} sold straight off the tanker (invoiced, at the bill's cost).</template>
              </Hint>
              <template v-else>{{ qty(p.quantity, p.unit) }}</template>
            </td>
            <td class="px-3 py-1 text-right"><MoneyText :amount="p.revenue" :currency="currency" :fraction-digits="0" /></td>
            <td class="px-3 py-1 text-right">
              <MoneyText :amount="p.cogs" :currency="currency" :fraction-digits="0" /><span v-if="p.estimated_cogs" class="ml-1 text-xs text-text-secondary">est.</span>
            </td>
            <td class="px-3 py-1 text-right" :class="shown(p) < 0 ? 'text-status-attention' : ''">
              <Hint v-if="p.book_profit != null" side="left">
                <MoneyText :amount="p.book_profit" :currency="currency" :fraction-digits="0" />
                <template #content>Sales {{ money(p.revenue) }} + closing {{ money(p.book_closing ?? 0) }} - opening {{ money(p.book_opening ?? 0) }} - bought {{ money(p.book_purchases ?? 0) }}</template>
              </Hint>
              <MoneyText v-else :amount="p.gross_profit" :currency="currency" :fraction-digits="0" />
            </td>
          </tr>
          <tr v-if="!products.length"><td colspan="5" class="px-3 py-3 text-center text-text-secondary">No sales.</td></tr>
          <tr v-else class="border-t border-rule-default font-semibold">
            <td class="px-3 py-1.5" colspan="2">Total</td>
            <td class="px-3 py-1.5 text-right"><MoneyText :amount="sum(products, (p) => p.revenue)" :currency="currency" :fraction-digits="0" /></td>
            <td class="px-3 py-1.5 text-right"><MoneyText :amount="sum(products, (p) => p.cogs)" :currency="currency" :fraction-digits="0" /></td>
            <td class="px-3 py-1.5 text-right"><MoneyText :amount="sum(products, shown)" :currency="currency" :fraction-digits="0" /></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Expenses per account -->
    <ul v-if="open === 'expenses'" class="rounded-md border border-rule-subtle px-3 py-1 tabular-nums">
      <li v-for="e in expenseAccounts" :key="e.code" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1 last:border-0">
        <span>
          {{ e.name }}
          <Hint v-if="e.asset"><span class="ml-1 text-xs text-text-secondary">asset</span><template #content>Bought for the station, kept as an asset -- not a cost in Profit &amp; Loss.</template></Hint>
        </span>
        <Link v-if="e.href" :href="e.href" :class="lnk"><MoneyText :amount="e.amount" :currency="currency" :fraction-digits="0" /></Link>
        <MoneyText v-else :amount="e.amount" :currency="currency" :fraction-digits="0" />
      </li>
      <li v-if="!expenseAccounts.length" class="py-2 text-center text-text-secondary">No expenses.</li>
    </ul>

    <!-- Purchases per product -->
    <ul v-if="open === 'purchases'" class="rounded-md border border-rule-subtle px-3 py-1 tabular-nums">
      <li v-for="p in purchaseProducts" :key="p.name" class="flex items-baseline justify-between gap-3 border-b border-rule-subtle py-1 last:border-0">
        <Link v-if="p.href" :href="p.href" class="underline-offset-2 hover:underline">{{ p.name }}</Link>
        <span v-else>{{ p.name }}</span>
        <span>{{ qty(p.quantity, p.unit) }} · <MoneyText :amount="p.amount" :currency="currency" :fraction-digits="0" /></span>
      </li>
      <li v-if="!purchaseProducts.length" class="py-2 text-center text-text-secondary">No purchases.</li>
    </ul>
  </div>
</template>
