<script setup lang="ts">
/**
 * Statement — the one statement report: bank & cash accounts, customers and
 * suppliers, through the same engine call and the same LedgerRegister table.
 * See AccountStatementService / CustomerStatementService / VendorStatementService
 * for how each `kind` builds its rows; this page only chooses which one and
 * shows what comes back.
 */
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import LedgerRegister from '@/components/LedgerRegister.vue'
import type { RegisterColumn } from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import type { BreadcrumbItem } from '@/types'
import { Printer } from 'lucide-vue-next'

type Kind = 'bank' | 'customer' | 'supplier'

type Row = {
  date: string | null
  type: string
  reference: string | null
  description: string
  money_in: number
  money_out: number
  balance: number
  link: string | null
}

type BankOption = { id: string; code: string; name: string }
type PartyOption = { id: string; name: string; customer_number?: string; vendor_number?: string }

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  filters: { kind: Kind; id: string | null; from: string; to: string }
  options: { bank: BankOption[]; customer: PartyOption[]; supplier: PartyOption[] }
  columns: { money_in: string; money_out: string; balance: string }
  statement: {
    rows: Row[]
    opening_balance: number
    closing_balance: number
    from: string
    to: string
    account?: string | null
    party?: string | null
  }
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Statements' },
])

const kind = ref<Kind>(props.filters.kind)
const partyId = ref(props.filters.id ?? '')
const from = ref(props.filters.from)
const to = ref(props.filters.to)
const search = ref('')

watch(() => props.filters, (f) => {
  kind.value = f.kind
  partyId.value = f.id ?? ''
  from.value = f.from
  to.value = f.to
  search.value = ''
})

const currency = computed(() => props.company.base_currency || 'PKR')
const moneyLocale = computed(() => (currency.value === 'PKR' ? 'en-PK' : 'en-US'))

const currentOptions = computed<{ id: string; label: string; sublabel?: string }[]>(() => {
  if (kind.value === 'bank') {
    return props.options.bank.map((a) => ({ id: a.id, label: a.name, sublabel: a.code }))
  }
  if (kind.value === 'customer') {
    return props.options.customer.map((c) => ({ id: c.id, label: c.name, sublabel: c.customer_number }))
  }
  return props.options.supplier.map((v) => ({ id: v.id, label: v.name, sublabel: v.vendor_number }))
})

const filteredOptions = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term) return currentOptions.value
  return currentOptions.value.filter((o) =>
    o.label.toLowerCase().includes(term) || (o.sublabel ?? '').toLowerCase().includes(term),
  )
})

const reload = () => {
  router.get(`/${props.company.slug}/reports/statements`, {
    kind: kind.value,
    id: partyId.value || undefined,
    from: from.value,
    to: to.value,
  }, { preserveState: true, preserveScroll: true })
}

const changeKind = (value: Kind | string) => {
  kind.value = value as Kind
  partyId.value = ''
  search.value = ''
  reload()
}

const changeParty = (value: string) => {
  partyId.value = value
  reload()
}

const partyLabel = computed(() => {
  if (kind.value === 'bank') return 'Account'
  if (kind.value === 'customer') return 'Customer'
  return 'Supplier'
})

const columns = computed<RegisterColumn<Row>[]>(() => [
  { key: 'date', label: 'Date', kind: 'date' },
  { key: 'reference', label: 'Reference', kind: 'ref' },
  { key: 'description', label: 'Description', kind: 'text' },
  { key: 'money_in', label: props.columns.money_in, kind: 'in' },
  { key: 'money_out', label: props.columns.money_out, kind: 'out' },
  { key: 'balance', label: props.columns.balance, kind: 'amount' },
])

const openRow = (row: Row) => {
  if (!row.link) return
  router.get(`/${props.company.slug}/${row.link}`)
}

const printStatement = () => window.print()

const statementTitle = computed(() => props.statement.account || props.statement.party || 'No account or party selected')
</script>

<template>
  <Head title="Statements" />

  <PageShell
    title="Statements"
    description="A running balance over a date range, like a bank statement — for a bank or cash account, a customer, or a supplier."
    :breadcrumbs="breadcrumbs"
  >
    <div class="mx-auto w-full max-w-6xl space-y-6 stmt-page">
      <Card variant="form" class="print:hidden">
        <CardHeader><CardTitle>{{ statementTitle }}</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <Tabs :model-value="kind" @update:model-value="changeKind">
            <TabsList>
              <TabsTrigger value="bank">Bank &amp; Cash</TabsTrigger>
              <TabsTrigger value="customer">Customer</TabsTrigger>
              <TabsTrigger value="supplier">Supplier</TabsTrigger>
            </TabsList>
          </Tabs>

          <div class="grid gap-4 md:grid-cols-4">
            <div class="space-y-2 md:col-span-2">
              <Label>{{ partyLabel }}</Label>
              <Input v-model="search" placeholder="Search…" class="mb-2" />
              <Select :model-value="partyId" @update:model-value="changeParty">
                <SelectTrigger>
                  <SelectValue :placeholder="`Select ${partyLabel.toLowerCase()}`" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="opt in filteredOptions" :key="opt.id" :value="opt.id">
                    {{ opt.label }}<span v-if="opt.sublabel" class="text-text-tertiary"> · {{ opt.sublabel }}</span>
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="from">From</Label>
              <Input id="from" v-model="from" type="date" />
            </div>
            <div class="space-y-2">
              <Label for="to">To</Label>
              <Input id="to" v-model="to" type="date" />
            </div>
          </div>

          <div class="flex items-center gap-2">
            <Button @click="reload">Apply</Button>
            <Button variant="outline" @click="printStatement">
              <Printer class="h-4 w-4" />
              Print
            </Button>
          </div>
        </CardContent>
      </Card>

      <LedgerRegister
        :data="statement.rows"
        :columns="columns"
        :key-field="(row, index) => `${row.type}-${row.date}-${index}`"
        :clickable="true"
        title="Statement"
        :description="`${statement.from} to ${statement.to}`"
        @row-click="openRow"
      >
        <template #empty>No movements in this range.</template>
        <template #cell-money_in="{ row }">
          <MoneyText :amount="row.money_in" :currency="currency" :locale="moneyLocale" :show-currency="false" :fraction-digits="0" dash-zero />
        </template>
        <template #cell-money_out="{ row }">
          <MoneyText :amount="row.money_out" :currency="currency" :locale="moneyLocale" :show-currency="false" :fraction-digits="0" dash-zero />
        </template>
        <template #cell-balance="{ row }">
          <MoneyText :amount="row.balance" :currency="currency" :locale="moneyLocale" :show-currency="false" :fraction-digits="0" />
        </template>
      </LedgerRegister>
    </div>
  </PageShell>
</template>

<style>
/* Printing a statement should produce the statement, not the application
   chrome around it — same intent as LedgerDocument's own print rules. */
@media print {
  [data-sidebar='sidebar'],
  [data-slot='sidebar-rail'],
  .stmt-page .print\:hidden {
    display: none !important;
  }
}
</style>
