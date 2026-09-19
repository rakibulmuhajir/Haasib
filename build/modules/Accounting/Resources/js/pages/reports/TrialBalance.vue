<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import LedgerRegister from '@/components/LedgerRegister.vue'
import type { RegisterColumn } from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import type { BreadcrumbItem } from '@/types'
import StatusBadge from '@/components/StatusBadge.vue'

type Row = {
  id: string
  code: string
  name: string
  type: string
  debit: number
  credit: number
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  filters: { as_of: string }
  report: {
    rows: Row[]
    totals: { debit: number; credit: number; difference: number }
    is_balanced: boolean
  }
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Trial Balance' },
])

const asOf = ref(props.filters.as_of)
watch(() => props.filters, (f) => { asOf.value = f.as_of })

const currency = computed(() => props.company.base_currency || 'PKR')
const moneyLocale = computed(() => (currency.value === 'PKR' ? 'en-PK' : 'en-US'))

const apply = () => {
  router.get(`/${props.company.slug}/reports/trial-balance`, { as_of: asOf.value }, { preserveScroll: true })
}

const openAccount = (row: Row) => {
  router.get(`/${props.company.slug}/journals`, { account_id: row.id, end: asOf.value, status: 'posted' })
}

const columns: RegisterColumn<Row>[] = [
  { key: 'code', label: 'Code', kind: 'ref' },
  { key: 'name', label: 'Account', kind: 'text' },
  { key: 'type', label: 'Type', kind: 'text' },
  { key: 'debit', label: 'Debit', kind: 'amount' },
  { key: 'credit', label: 'Credit', kind: 'amount' },
]

const totals = computed(() => ({
  debit: props.report.totals.debit,
  credit: props.report.totals.credit,
}))
</script>

<template>
  <Head title="Trial Balance" />

  <PageShell
    title="Trial Balance"
    description="Proof that the ledger is internally consistent: every account's balance, and the two columns agreed."
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

      <!-- Whether the books balance is a genuine pass or fail, so success/critical is
           earned here rather than decorative. The word carries it without the colour. -->
      <Card :variant="'detail'">
        <CardContent class="flex items-center gap-3 py-4">
          <StatusBadge :status="report.is_balanced ? 'balanced' : 'out_of_balance'" />
          <div>
            <p class="font-medium">
              {{ report.is_balanced ? 'The books balance.' : 'The books do not balance.' }}
            </p>
            <p v-if="!report.is_balanced" class="text-sm text-text-secondary">
              Debits and credits differ by
              <MoneyText :amount="report.totals.difference" :currency="currency" :locale="moneyLocale" />.
              Something wrote a one-sided entry; figures downstream cannot be relied on until it is found.
            </p>
            <p v-else class="text-sm text-text-secondary">
              Total debits equal total credits as at {{ filters.as_of }}.
            </p>
          </div>
        </CardContent>
      </Card>

      <LedgerRegister
        :data="report.rows"
        :columns="columns"
        key-field="id"
        clickable
        title="Account balances"
        :totals="totals"
        totals-label="Total"
        @row-click="openAccount"
      >
        <template #empty>Nothing posted on or before this date.</template>
        <template #cell-debit="{ row }">
          <MoneyText :amount="row.debit" :currency="currency" :locale="moneyLocale" :show-currency="false" dash-zero />
        </template>
        <template #cell-credit="{ row }">
          <MoneyText :amount="row.credit" :currency="currency" :locale="moneyLocale" :show-currency="false" dash-zero />
        </template>
        <template #total-debit>
          <MoneyText :amount="report.totals.debit" :currency="currency" :locale="moneyLocale" :show-currency="false" />
        </template>
        <template #total-credit>
          <MoneyText :amount="report.totals.credit" :currency="currency" :locale="moneyLocale" :show-currency="false" />
        </template>
      </LedgerRegister>
    </div>
  </PageShell>
</template>
