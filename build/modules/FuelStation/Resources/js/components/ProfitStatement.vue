<script setup lang="ts">
/**
 * The profit & loss statement on the fuel home, straight from the ledger (ProfitStatementService):
 * eight lines, each opening to what it is made of -- one open at a time. Costs read with a minus,
 * so the column adds up top to bottom.
 */
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import Hint from '@/components/Hint.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { ChevronDown, ChevronRight } from 'lucide-vue-next'

export interface StatementDetail {
  account_id: string | null
  item_id: string | null
  code: string | null
  name: string
  amount: number
  href: string | null
  sales?: number | null
  cost?: number | null
  working?: { opening: number; bought: number; closing: number; used: number } | null
}
export interface StatementLine { key: string; label: string; amount: number; details: StatementDetail[] }
export interface Statement {
  from: string
  to: string
  lines: StatementLine[]
  net_profit: number
  not_in_profit: { stock_bought: number; equipment_bought: number }
}

defineProps<{ statement: Statement; currency: string; trailPrefix?: string }>()

const open = ref<string | null>(null)
const toggle = (k: string) => { open.value = open.value === k ? null : k }

const hints: Record<string, string> = {
  sales: 'Everything sold, from the books.',
  cost_of_sales: 'What the stock you sold cost you.',
  gross_profit: 'Sales minus cost of sales: what you earned on what you sold.',
  dip: 'Stock the morning dip found missing (a loss) or extra (a gain), at cost.',
  expenses: 'Running costs from Money out → Expenses.',
  salaries: 'Pay and wages booked by payroll.',
  other_income: 'Rent and any other income outside sales.',
  other_costs: 'Cash short, bank and card charges, discounts.',
  net_profit: 'What is left after everything. Matches Profit & Loss.',
}
const costKeys = ['cost_of_sales', 'dip', 'expenses', 'salaries', 'other_costs']
const subtotals = ['gross_profit', 'net_profit']

// Cost lines are shown with a minus; a cost that went the other way (a tank gain) shows plus.
const shown = (key: string, v: number) => (costKeys.includes(key) ? -v : v)
const num = (v: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Math.round(v))
const workingText = (w: NonNullable<StatementDetail['working']>) =>
  `Opening ${num(w.opening)} + bought ${num(w.bought)} − closing ${num(w.closing)} = ${num(w.used)} of stock gone; what was sold cost the rest.`
const expandable = (l: StatementLine) => l.details.length > 0
</script>

<template>
  <div class="text-sm">
    <ul class="rounded-md border border-rule-subtle tabular-nums">
      <li
        v-for="l in statement.lines"
        :key="l.key"
        class="border-b border-rule-subtle last:border-0"
        :class="subtotals.includes(l.key) ? 'bg-surface-band font-semibold' : ''"
      >
        <div
          class="flex w-full items-baseline justify-between gap-3 px-3 py-2"
          :class="[expandable(l) ? 'cursor-pointer hover:bg-surface-sunken' : '', l.key === 'net_profit' ? 'border-t border-rule-emphasis' : '']"
          @click="expandable(l) && toggle(l.key)"
        >
          <span class="flex min-w-0 items-baseline gap-1.5">
            <Button v-if="expandable(l)" type="button" variant="ghost" class="h-auto p-0 self-center text-text-secondary" :aria-expanded="open === l.key" :aria-label="`${l.label} details`" @click.stop="toggle(l.key)">
              <component :is="open === l.key ? ChevronDown : ChevronRight" class="h-3.5 w-3.5" />
            </Button>
            <span v-else class="w-3.5 shrink-0" />
            <span @click.stop><Hint>{{ l.label }}<template #content>{{ hints[l.key] }}</template></Hint></span>
          </span>
          <span class="whitespace-nowrap" @click.stop><Hint :trail="trailPrefix ? `${trailPrefix}:${l.key}` : false" :preview="hints[l.key]"><MoneyText :amount="shown(l.key, l.amount)" :currency="currency" :fraction-digits="0" /></Hint></span>
        </div>

        <ul v-if="open === l.key" class="border-t border-rule-subtle bg-surface-sunken/40 pb-1">
          <li v-for="(d, i) in l.details" :key="`${d.account_id ?? d.item_id ?? d.name}-${i}`" class="flex items-baseline justify-between gap-3 py-1 pl-9 pr-3 font-normal">
            <span class="min-w-0 truncate">
              <Link v-if="d.href" :href="d.href" class="underline-offset-2 hover:underline">{{ d.name }}</Link>
              <template v-else>{{ d.name }}</template>
            </span>
            <span class="whitespace-nowrap">
              <Hint v-if="d.working" side="left">
                <MoneyText :amount="shown(l.key, d.amount)" :currency="currency" :fraction-digits="0" />
                <template #content>{{ workingText(d.working) }}</template>
              </Hint>
              <MoneyText v-else :amount="shown(l.key, d.amount)" :currency="currency" :fraction-digits="0" />
            </span>
          </li>
        </ul>
      </li>
    </ul>

    <p class="mt-2 text-xs text-text-secondary">
      <Hint>Not in profit<template #content>Stock becomes a cost when sold. Equipment is kept as an asset.</template></Hint>:
      stock bought <MoneyText :amount="statement.not_in_profit.stock_bought" :currency="currency" :fraction-digits="0" />
      · equipment bought <MoneyText :amount="statement.not_in_profit.equipment_bought" :currency="currency" :fraction-digits="0" />
    </p>
  </div>
</template>
