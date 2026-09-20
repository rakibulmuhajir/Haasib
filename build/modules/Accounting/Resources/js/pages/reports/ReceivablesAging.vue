<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Card, CardContent, CardFigure, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import LedgerRegister from '@/components/LedgerRegister.vue'
import type { RegisterColumn } from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import type { BreadcrumbItem } from '@/types'

type Row = {
  customer_id: string
  customer_name: string
  customer_number: string | null
  current: number
  d1_30: number
  d31_60: number
  d61_90: number
  d90_plus: number
  total: number
  oldest_days_past_due: number
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  filters: { as_of: string }
  report: {
    as_of: string
    buckets: Array<{ key: string; label: string }>
    rows: Row[]
    totals: { current: number; d1_30: number; d31_60: number; d61_90: number; d90_plus: number; total: number }
    customer_count: number
  }
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Receivables Aging' },
])

const asOf = ref(props.filters.as_of)
watch(() => props.filters, (f) => { asOf.value = f.as_of })

const currency = computed(() => props.company.base_currency || 'PKR')
const moneyLocale = computed(() => (currency.value === 'PKR' ? 'en-PK' : 'en-US'))

const apply = () => {
  router.get(`/${props.company.slug}/reports/receivables-aging`, { as_of: asOf.value }, { preserveScroll: true })
}

const openCustomer = (row: Row) => {
  if (row.customer_id === 'unassigned') return
  router.get(`/${props.company.slug}/customers/${row.customer_id}`)
}

const columns: RegisterColumn<Row>[] = [
  { key: 'customer_name', label: 'Buyer', kind: 'text' },
  { key: 'current', label: 'Not yet due', kind: 'amount' },
  { key: 'd1_30', label: '1–30 days', kind: 'amount' },
  { key: 'd31_60', label: '31–60 days', kind: 'amount' },
  { key: 'd61_90', label: '61–90 days', kind: 'amount' },
  { key: 'd90_plus', label: 'Over 90 days', kind: 'amount' },
  { key: 'total', label: 'Total owed', kind: 'amount' },
]

const totals = computed(() => ({
  current: props.report.totals.current,
  d1_30: props.report.totals.d1_30,
  d31_60: props.report.totals.d31_60,
  d61_90: props.report.totals.d61_90,
  d90_plus: props.report.totals.d90_plus,
  total: props.report.totals.total,
}))

// What is genuinely adverse, as opposed to merely outstanding: an invoice inside its
// terms is the ordinary state of a credit account, not a problem.
const overdueTotal = computed(() =>
  props.report.totals.d1_30 + props.report.totals.d31_60 + props.report.totals.d61_90 + props.report.totals.d90_plus
)
</script>

<template>
  <Head title="Receivables Aging" />

  <PageShell
    title="Receivables Aging"
    description="Who owes money, and how long they have owed it."
    :breadcrumbs="breadcrumbs"
  >
    <div class="mx-auto w-full max-w-6xl space-y-6">
      <Card variant="form">
        <CardHeader><CardTitle>As at</CardTitle></CardHeader>
        <CardContent class="grid gap-4 md:grid-cols-4">
          <div class="space-y-2 md:col-span-3">
            <Label for="as-of">Date</Label>
            <Input id="as-of" v-model="asOf" type="date" />
          </div>
          <div class="flex items-end">
            <Button class="w-full" @click="apply">Apply</Button>
          </div>
        </CardContent>
      </Card>

      <div class="grid gap-4 md:grid-cols-3">
        <Card variant="figure">
          <CardHeader><CardTitle>Total owed</CardTitle></CardHeader>
          <CardContent>
            <CardFigure><MoneyText :amount="report.totals.total" :currency="currency" :locale="moneyLocale" /></CardFigure>
          </CardContent>
        </Card>
        <Card variant="figure">
          <CardHeader><CardTitle>Past due</CardTitle></CardHeader>
          <CardContent>
            <CardFigure><MoneyText :amount="overdueTotal" :currency="currency" :locale="moneyLocale" tone="overdue" /></CardFigure>
          </CardContent>
        </Card>
        <Card variant="figure">
          <CardHeader><CardTitle>Buyers with a balance</CardTitle></CardHeader>
          <CardContent>
            <CardFigure>{{ report.customer_count }}</CardFigure>
          </CardContent>
        </Card>
      </div>

      <LedgerRegister
        :data="report.rows"
        :columns="columns"
        key-field="customer_id"
        clickable
        title="Balances by age"
        description="Aged from the due date, worst first. Click a buyer for their statement."
        :totals="totals"
        totals-label="Total"
        @row-click="openCustomer"
      >
        <template #empty>Nobody owes anything as at this date.</template>

        <template #cell-customer_name="{ row }">
          <span>{{ row.customer_name }}</span>
          <span v-if="row.customer_number" class="ml-2 text-text-metadata">{{ row.customer_number }}</span>
        </template>

        <template v-for="key in ['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus', 'total']" :key="key" #[`cell-${key}`]="{ row }">
          <MoneyText
            :amount="row[key]"
            :currency="currency"
            :locale="moneyLocale"
            :show-currency="false"
            :tone="key === 'd90_plus' && row[key] > 0 ? 'overdue' : 'default'"
            dash-zero
          />
        </template>

        <template v-for="key in ['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus', 'total']" :key="`t-${key}`" #[`total-${key}`]>
          <MoneyText :amount="report.totals[key]" :currency="currency" :locale="moneyLocale" :show-currency="false" />
        </template>
      </LedgerRegister>
    </div>
  </PageShell>
</template>
