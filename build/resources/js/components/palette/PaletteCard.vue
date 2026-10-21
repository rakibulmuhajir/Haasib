<script setup lang="ts">
/** The plain preview of what Enter will post: who, how much, from where, when. No debits or credits. */
import { computed } from 'vue'
import DateTimeText from '@/components/DateTimeText.vue'
import MoneyText from '@/components/MoneyText.vue'
import type { Card } from '@/palette/types'

interface Balance { account_name: string; before: number; after: number }

const props = defineProps<{ card: Card; balances?: Balance[]; whoLabel?: string; notes?: string[] }>()

const balanceOf = (name?: string) => (name ? (props.balances ?? []).find((b) => b.account_name === name) : undefined)
const accounts = computed(() =>
  [
    { term: 'From', name: props.card.from },
    { term: 'To', name: props.card.to },
  ].filter((a) => a.name),
)
</script>

<template>
  <div class="border-t border-rule-subtle px-4 py-3" data-testid="palette-card">
    <dl class="grid grid-cols-[6rem_1fr] items-baseline gap-x-4 gap-y-1.5 text-sm">
      <template v-if="card.what">
        <dt class="text-xs font-medium text-text-metadata">What</dt>
        <dd class="m-0 font-semibold text-text-primary">{{ card.what }}</dd>
      </template>

      <!-- Both rows when both are set: "Void" alone would hide which document Enter voids. -->
      <template v-if="card.who">
        <dt class="text-xs font-medium text-text-metadata">{{ whoLabel || 'Who' }}</dt>
        <dd class="m-0 font-semibold text-text-primary">{{ card.who }}</dd>
      </template>

      <template v-if="card.amount !== undefined">
        <dt class="text-xs font-medium text-text-metadata">Amount</dt>
        <dd class="m-0">
          <MoneyText :amount="card.amount" :currency="card.currency" scale="conclusion" />
        </dd>
      </template>

      <template v-for="a in accounts" :key="a.term">
        <dt class="text-xs font-medium text-text-metadata">{{ a.term }}</dt>
        <dd class="m-0 text-text-primary">
          {{ a.name }}
          <span v-if="balanceOf(a.name)" class="ml-1 text-text-metadata">
            ·
            <MoneyText :amount="balanceOf(a.name)!.before" :currency="card.currency" tone="muted" :show-currency="false" />
            →
            <MoneyText :amount="balanceOf(a.name)!.after" :currency="card.currency" tone="muted" :show-currency="false" />
          </span>
        </dd>
      </template>

      <template v-if="card.date">
        <dt class="text-xs font-medium text-text-metadata">Date</dt>
        <dd class="m-0 text-text-primary"><DateTimeText :value="card.date" mode="date" /></dd>
      </template>

      <template v-if="card.note">
        <dt class="text-xs font-medium text-text-metadata">Note</dt>
        <dd class="m-0 text-text-primary">{{ card.note }}</dd>
      </template>
    </dl>
    <p v-for="n in notes ?? []" :key="n" class="mt-2 text-xs text-text-metadata">{{ n }}</p>
  </div>
</template>
