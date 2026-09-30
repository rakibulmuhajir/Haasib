<script setup lang="ts">
/** The corrections made to a record, newest first (acct.corrections). */
import { Link } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { formatDateTime } from '@/lib/datetime'

export interface Correction {
  id: string
  correction_number: string
  action: string
  reason: string
  transaction_id: string | null
  created_at: string
  by: string | null
  changes: {
    before?: { customer?: string | null; vendor?: string | null; amount?: number }
    after?: { customer?: string | null; vendor?: string | null; amount?: number; shares?: { customer?: string; vendor?: string; amount: number; invoice?: string; bill?: string; payment?: string; credit_note?: string; vendor_credit?: string }[] }
    payments_unapplied?: { payment: string; amount: number }[]
    unapplied_from?: { invoice?: string; bill?: string; amount: number }[]
  }
}

defineProps<{ corrections: Correction[]; slug: string }>()

const money = (n: number) => Number(n).toLocaleString(undefined, { maximumFractionDigits: 2 })
const party = (p?: { customer?: string | null; vendor?: string | null }) => p?.customer ?? p?.vendor ?? ''
const doc = (s: { invoice?: string; bill?: string; payment?: string }) => s.invoice ?? s.bill ?? s.payment ?? ''
</script>

<template>
  <Card v-if="corrections.length" variant="detail">
    <CardHeader><CardTitle>Corrections</CardTitle></CardHeader>
    <CardContent class="space-y-3 text-sm">
      <div v-for="c in corrections" :key="c.id" class="space-y-1 border-b pb-3 last:border-0 last:pb-0">
        <div class="flex items-center justify-between gap-2">
          <Link v-if="c.transaction_id" :href="`/${slug}/journals/${c.transaction_id}`" class="font-medium text-primary underline-offset-2 hover:underline">{{ c.correction_number }}</Link>
          <span v-else class="font-medium">{{ c.correction_number }}</span>
          <span class="text-xs text-muted-foreground">{{ formatDateTime(c.created_at, { mode: 'date' }) }}<template v-if="c.by"> · {{ c.by }}</template></span>
        </div>
        <p v-if="c.action === 'change_customer' || c.action === 'change_supplier'">{{ party(c.changes.before) }} → {{ party(c.changes.after) }}</p>
        <template v-else-if="c.action === 'split'">
          <p v-if="(c.changes.after?.amount ?? 0) > 0">{{ party(c.changes.after) }} keeps {{ money(c.changes.after?.amount ?? 0) }}</p>
          <p v-else>Cancelled for {{ party(c.changes.before) }}, split to:</p>
          <p v-for="s in c.changes.after?.shares ?? []" :key="doc(s)">{{ s.customer ?? s.vendor }} {{ money(s.amount) }} · {{ doc(s) }}</p>
        </template>
        <p class="text-muted-foreground" dir="auto">{{ c.reason }}</p>
      </div>
    </CardContent>
  </Card>
</template>
