<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MetaChip from '@/components/MetaChip.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { BreadcrumbItem } from '@/types'
import { ReceiptText } from 'lucide-vue-next'

// One list of where the money went: expenses, approved salaries and card charges, by account.
// ExpenseReportService says what counts; other costs on expense accounts are listed apart.

interface Company { id: string; name: string; slug: string; base_currency: string }
type Kind = 'expense' | 'salary' | 'card_charge'

interface Line {
  kind: Kind
  kind_label: string
  line_id: string
  transaction_number: string
  date_label: string
  description: string
  amount: number
  href: string
}

interface AccountRow {
  account_id: string
  account_code: string
  account_name: string
  kinds: string[]
  amount: number
  line_count: number
  share: number
  lines: Line[]
}

interface KindRow { kind: Kind; label: string; amount: number }
interface OtherRow { account_id: string; account_code: string; account_name: string; label: string; amount: number }

const props = defineProps<{
  company: Company
  filters: { start_date: string; end_date: string }
  totals: { amount: number; line_count: number; account_count: number; transaction_count: number; other_amount: number }
  kindRows: KindRow[]
  accountRows: AccountRow[]
  otherRows: OtherRow[]
}>()

const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Expenses', href: `/${props.company.slug}/fuel/reports/expenses` },
])

const apply = () => {
  router.get(`/${props.company.slug}/fuel/reports/expenses`, { start_date: startDate.value, end_date: endDate.value }, { preserveScroll: true, preserveState: true })
}

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const setMonth = (back: number) => {
  const now = new Date()
  startDate.value = iso(new Date(now.getFullYear(), now.getMonth() - back, 1))
  endDate.value = iso(back === 0 ? now : new Date(now.getFullYear(), now.getMonth() - back + 1, 0))
  apply()
}

const rangeLabel = computed(() => {
  const f = new Date(`${props.filters.start_date}T00:00:00`)
  const t = new Date(`${props.filters.end_date}T00:00:00`)
  const opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric' }
  return `${f.toLocaleDateString('en-GB', opts)} – ${t.toLocaleDateString('en-GB', opts)}`
})

// A category opens to its lines.
const open = ref<string | null>(null)
const toggle = (row: AccountRow) => { open.value = open.value === row.account_id ? null : row.account_id }

const columns = [
  { key: 'account', label: 'Category', kind: 'text' as const },
  { key: 'share', label: 'Share', kind: 'text' as const },
  { key: 'line_count', label: 'Entries', kind: 'amount' as const },
  { key: 'amount', label: 'Spent', kind: 'amount' as const },
]
</script>

<template>
  <Head title="Expenses" />

  <PageShell title="Expenses" :description="rangeLabel" :icon="ReceiptText" :breadcrumbs="breadcrumbs">
    <div class="space-y-5">
      <div class="flex flex-wrap items-end gap-3">
        <Button variant="outline" size="sm" @click="setMonth(0)">This month</Button>
        <Button variant="outline" size="sm" @click="setMonth(1)">Last month</Button>
        <div class="grid gap-1.5">
          <Label for="start_date">From</Label>
          <Input id="start_date" v-model="startDate" type="date" class="w-40" />
        </div>
        <div class="grid gap-1.5">
          <Label for="end_date">To</Label>
          <Input id="end_date" v-model="endDate" type="date" class="w-40" />
        </div>
        <Button size="sm" @click="apply">Show</Button>
      </div>

      <!-- The total, and what it is made of. -->
      <Card>
        <CardContent class="flex flex-wrap items-end gap-x-10 gap-y-3 pt-6">
          <div>
            <div class="text-sm text-muted-foreground">Spent</div>
            <div class="text-3xl font-semibold"><MoneyText :amount="totals.amount" :currency="company.base_currency" :fraction-digits="0" /></div>
          </div>
          <div v-for="k in kindRows" :key="k.kind">
            <div class="text-sm text-muted-foreground">{{ k.label }}</div>
            <div class="text-lg"><MoneyText :amount="k.amount" :currency="company.base_currency" :fraction-digits="0" /></div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Where it went</CardTitle>
          <CardDescription>Click a category for its entries.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <LedgerRegister
            :data="accountRows"
            :columns="columns"
            key-field="account_id"
            clickable
            :expanded="(row: any) => open === row.account_id"
            :totals="{ amount: totals.amount, line_count: totals.line_count }"
            totals-label="Total"
            @row-click="toggle"
          >
            <template #empty>Nothing spent in this range.</template>

            <template #cell-account="{ row }">
              <div class="font-medium">{{ row.account_name }}</div>
              <div class="mt-0.5 flex flex-wrap gap-1.5 text-xs text-muted-foreground">
                <span>{{ row.account_code }}</span>
                <MetaChip v-for="k in row.kinds.filter((x: string) => x !== 'Expenses')" :key="k" tone="neutral" bare>{{ k }}</MetaChip>
              </div>
            </template>
            <template #cell-share="{ row }">
              <div class="flex items-center gap-2">
                <div class="h-1.5 w-24 rounded-full bg-muted">
                  <div class="h-1.5 rounded-full bg-foreground/60" :style="{ width: `${Math.min(100, Math.max(0, row.share))}%` }" />
                </div>
                <span class="text-xs tabular-nums text-muted-foreground">{{ row.share }}%</span>
              </div>
            </template>
            <template #cell-amount="{ row }"><MoneyText :amount="row.amount" :currency="company.base_currency" :fraction-digits="0" /></template>
            <template #total-amount><MoneyText :amount="totals.amount" :currency="company.base_currency" :fraction-digits="0" /></template>

            <template #row-detail="{ row }">
              <ul class="divide-y divide-rule-default px-4 py-1 text-sm">
                <li v-for="l in row.lines" :key="l.line_id" class="flex items-baseline justify-between gap-4 py-1.5">
                  <span class="w-24 shrink-0 tabular-nums text-muted-foreground">{{ l.date_label }}</span>
                  <span class="min-w-0 flex-1">{{ l.description }}</span>
                  <Link :href="`/${company.slug}/${l.href}`" class="shrink-0 text-xs text-primary underline-offset-4 hover:underline" @click.stop>{{ l.transaction_number }}</Link>
                  <MoneyText class="w-28 shrink-0 text-right" :amount="l.amount" :currency="company.base_currency" :fraction-digits="0" />
                </li>
              </ul>
            </template>
          </LedgerRegister>
        </CardContent>
      </Card>

      <!-- On expense accounts but not spending: tank shrinkage, cash short/over, bills, journals.
           Outside the total, so Spent + this ties to the Profit & Loss. -->
      <Card v-if="otherRows.length">
        <CardHeader>
          <CardTitle class="text-base">Also in Profit &amp; Loss</CardTitle>
          <CardDescription>Not counted in Spent.</CardDescription>
        </CardHeader>
        <CardContent>
          <ul class="space-y-1.5 text-sm tabular-nums">
            <li v-for="row in otherRows" :key="row.account_id" class="flex justify-between gap-3">
              <span><span class="font-medium">{{ row.account_name }}</span> <span class="text-muted-foreground">· {{ row.label }}</span></span>
              <MoneyText :amount="row.amount" :currency="company.base_currency" :fraction-digits="0" />
            </li>
            <li class="flex justify-between gap-3 border-t border-rule-default pt-2 font-semibold">
              <span>Total</span>
              <MoneyText :amount="totals.other_amount" :currency="company.base_currency" :fraction-digits="0" />
            </li>
          </ul>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
