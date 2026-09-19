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
import StatusBadge from '@/components/StatusBadge.vue'

type Line = {
  id: string
  code: string
  name: string
  subtype: string | null
  amount: number
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  filters: { as_of: string }
  report: {
    as_of: string
    assets: Line[]
    liabilities: Line[]
    equity: Line[]
    retained_earnings: number
    totals: {
      assets: number
      liabilities: number
      equity: number
      liabilities_and_equity: number
      difference: number
    }
    is_balanced: boolean
  }
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Balance Sheet' },
])

const asOf = ref(props.filters.as_of)
watch(() => props.filters, (f) => { asOf.value = f.as_of })

const currency = computed(() => props.company.base_currency || 'PKR')
const moneyLocale = computed(() => (currency.value === 'PKR' ? 'en-PK' : 'en-US'))

const apply = () => {
  router.get(`/${props.company.slug}/reports/balance-sheet`, { as_of: asOf.value }, { preserveScroll: true })
}

const openAccount = (row: Line) => {
  router.get(`/${props.company.slug}/journals`, { account_id: row.id, end: asOf.value, status: 'posted' })
}

const columns: RegisterColumn<Line>[] = [
  { key: 'code', label: 'Code', kind: 'ref' },
  { key: 'name', label: 'Account', kind: 'text' },
  { key: 'amount', label: 'Amount', kind: 'amount' },
]

// Retained earnings is not an account in the ledger: nothing closes revenue and expenses
// out to it, so it is computed and shown as its own line. Without it the sheet cannot add up.
const equityLines = computed<Line[]>(() => [
  ...props.report.equity,
  {
    id: 'retained-earnings',
    code: '—',
    name: 'Retained earnings (result to date)',
    subtype: null,
    amount: props.report.retained_earnings,
  },
])
</script>

<template>
  <Head title="Balance Sheet" />

  <PageShell
    title="Balance Sheet"
    description="What the business owns, what it owes, and what is left over — at one date."
    :breadcrumbs="breadcrumbs"
  >
    <div class="mx-auto w-full max-w-5xl space-y-6">
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
          <CardHeader><CardTitle>Assets</CardTitle></CardHeader>
          <CardContent>
            <CardFigure><MoneyText :amount="report.totals.assets" :currency="currency" :locale="moneyLocale" /></CardFigure>
          </CardContent>
        </Card>
        <Card variant="figure">
          <CardHeader><CardTitle>Liabilities</CardTitle></CardHeader>
          <CardContent>
            <CardFigure><MoneyText :amount="report.totals.liabilities" :currency="currency" :locale="moneyLocale" /></CardFigure>
          </CardContent>
        </Card>
        <Card variant="figure">
          <CardHeader><CardTitle>Equity</CardTitle></CardHeader>
          <CardContent>
            <CardFigure><MoneyText :amount="report.totals.equity" :currency="currency" :locale="moneyLocale" /></CardFigure>
          </CardContent>
        </Card>
      </div>

      <!-- Assets = Liabilities + Equity is a real yes-or-no, so a pass/fail state is earned. -->
      <Card variant="detail">
        <CardContent class="flex items-center gap-3 py-4">
          <StatusBadge :status="report.is_balanced ? 'balanced' : 'out_of_balance'" />
          <div>
            <p class="font-medium">
              {{ report.is_balanced ? 'Assets equal liabilities plus equity.' : 'The sheet does not balance.' }}
            </p>
            <p v-if="!report.is_balanced" class="text-sm text-text-secondary">
              Out by
              <MoneyText :amount="report.totals.difference" :currency="currency" :locale="moneyLocale" />.
              Check the trial balance first — a sheet that will not balance usually means a one-sided entry.
            </p>
          </div>
        </CardContent>
      </Card>

      <LedgerRegister
        :data="report.assets"
        :columns="columns"
        key-field="id"
        clickable
        title="Assets"
        description="What the business owns — cash, stock in the tanks, money owed to it."
        :totals="{ amount: report.totals.assets }"
        totals-label="Total assets"
        @row-click="openAccount"
      >
        <template #empty>No assets recorded on or before this date.</template>
        <template #cell-amount="{ row }">
          <MoneyText :amount="row.amount" :currency="currency" :locale="moneyLocale" :show-currency="false" />
        </template>
        <template #total-amount>
          <MoneyText :amount="report.totals.assets" :currency="currency" :locale="moneyLocale" :show-currency="false" />
        </template>
      </LedgerRegister>

      <LedgerRegister
        :data="report.liabilities"
        :columns="columns"
        key-field="id"
        clickable
        title="Liabilities"
        description="What the business owes — the supplier, the bank, wages not yet paid."
        :totals="{ amount: report.totals.liabilities }"
        totals-label="Total liabilities"
        @row-click="openAccount"
      >
        <template #empty>No liabilities recorded on or before this date.</template>
        <template #cell-amount="{ row }">
          <MoneyText :amount="row.amount" :currency="currency" :locale="moneyLocale" :show-currency="false" />
        </template>
        <template #total-amount>
          <MoneyText :amount="report.totals.liabilities" :currency="currency" :locale="moneyLocale" :show-currency="false" />
        </template>
      </LedgerRegister>

      <LedgerRegister
        :data="equityLines"
        :columns="columns"
        key-field="id"
        title="Equity"
        description="Capital put in, plus everything the trading has earned since the books opened."
        :totals="{ amount: report.totals.equity }"
        totals-label="Total equity"
      >
        <template #empty>No equity recorded on or before this date.</template>
        <template #cell-amount="{ row }">
          <MoneyText :amount="row.amount" :currency="currency" :locale="moneyLocale" :show-currency="false" />
        </template>
        <template #total-amount>
          <MoneyText :amount="report.totals.equity" :currency="currency" :locale="moneyLocale" :show-currency="false" />
        </template>
      </LedgerRegister>
    </div>
  </PageShell>
</template>
