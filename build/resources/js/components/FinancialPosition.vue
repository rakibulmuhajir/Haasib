<script setup lang="ts">
/**
 * Where the company stands, at a glance, with every figure openable.
 *
 * A number on a dashboard that cannot be opened is a number nobody can act on: "in bank
 * 3,065,692" prompts "in which bank", and if answering that means leaving the page, the
 * figure gets ignored. Each line here expands in place to what it is made of.
 *
 * Every figure comes from the general ledger, so these four cannot disagree with each other
 * or with the reports. The breakdown is reconciled to its headline server-side - if the
 * documents behind a control account do not add up to it, the difference appears as its own
 * row rather than being quietly dropped.
 */
import { ref, computed } from 'vue'
import { ChevronRight } from 'lucide-vue-next'
import MoneyText from '@/components/MoneyText.vue'

interface BreakdownItem {
  label: string
  amount: number
}

interface Position {
  cash: number
  bank: number
  receivable: number
  payable: number
  net: number
  breakdown: {
    cash: BreakdownItem[]
    bank: BreakdownItem[]
    receivable: BreakdownItem[]
    payable: BreakdownItem[]
  }
}

const props = defineProps<{
  position: Position
  currency: string
}>()

type LineKey = 'cash' | 'bank' | 'receivable' | 'payable'

const open = ref<LineKey | null>(null)

const toggle = (key: LineKey) => {
  open.value = open.value === key ? null : key
}

/**
 * `sign` is what the line does to the total, shown in its own gutter so the arithmetic reads
 * without colour doing the work. Money owed out is ordinary, not adverse - it is ink with a
 * minus, which is why none of these carries a tone.
 */
const lines = computed(() => [
  { key: 'cash' as const, label: 'Cash at hand', sign: '', amount: props.position.cash },
  { key: 'bank' as const, label: 'In bank', sign: '+', amount: props.position.bank },
  { key: 'receivable' as const, label: 'Owed to us', sign: '+', amount: props.position.receivable },
  { key: 'payable' as const, label: 'We owe', sign: '−', amount: props.position.payable },
])

const detailFor = (key: LineKey): BreakdownItem[] => props.position.breakdown?.[key] ?? []
</script>

<template>
  <section class="rounded-lg border border-rule-default bg-surface-raised">
    <header class="border-b border-rule-default px-4 py-3">
      <h2 class="font-serif text-lg">Where you stand</h2>
      <p class="text-sm text-text-secondary">From the ledger. Select a line to see what it is made of.</p>
    </header>

    <ul class="divide-y divide-rule-subtle">
      <li v-for="line in lines" :key="line.key">
        <button
          type="button"
          class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-surface-band focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          :aria-expanded="open === line.key"
          :aria-controls="`position-detail-${line.key}`"
          @click="toggle(line.key)"
        >
          <ChevronRight
            class="h-4 w-4 shrink-0 text-text-tertiary transition-transform"
            :class="open === line.key ? 'rotate-90' : ''"
            aria-hidden="true"
          />
          <span class="w-4 shrink-0 font-mono text-sm text-text-tertiary">{{ line.sign }}</span>
          <span class="flex-1 text-sm">{{ line.label }}</span>
          <MoneyText :amount="line.amount" :currency="currency" />
        </button>

        <div
          v-if="open === line.key"
          :id="`position-detail-${line.key}`"
          class="bg-surface-sunken px-4 pb-3 pt-1"
        >
          <p v-if="detailFor(line.key).length === 0" class="py-2 pl-11 text-sm text-text-secondary">
            Nothing to show — this is zero.
          </p>
          <ul v-else class="pl-11">
            <li
              v-for="item in detailFor(line.key)"
              :key="item.label"
              class="flex items-center gap-3 py-1.5 text-sm"
            >
              <span class="flex-1 text-text-secondary">{{ item.label }}</span>
              <MoneyText :amount="item.amount" :currency="currency" :show-currency="false" />
            </li>
          </ul>
        </div>
      </li>
    </ul>

    <!-- The conclusion the four lines reach, under the double rule the register grammar uses
         for a total. -->
    <footer class="flex items-center gap-3 border-t-2 border-double border-rule-emphasis px-4 py-3">
      <span class="flex-1 text-sm font-medium">Net position</span>
      <MoneyText :amount="position.net" :currency="currency" scale="conclusion" />
    </footer>
  </section>
</template>
