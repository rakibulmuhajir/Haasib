<script setup lang="ts">
/**
 * Statement — the one statement report: bank & cash accounts, customers and
 * suppliers, through the same engine call and the same LedgerRegister table.
 * See AccountStatementService / CustomerStatementService / VendorStatementService
 * for how each `kind` builds its rows; this page only chooses which one and
 * shows what comes back.
 */
import { computed, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Select, SelectContent, SelectItem, SelectSeparator, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'
import LedgerRegister from '@/components/LedgerRegister.vue'
import type { RegisterColumn } from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import type { BreadcrumbItem } from '@/types'
import { FileText, Printer } from 'lucide-vue-next'

type Kind = 'bank' | 'customer' | 'supplier' | 'amanat' | 'employee' | 'expense' | 'partner'

type Row = {
  date: string | null
  type: string
  reference: string | null
  description: string
  money_in: number
  money_out: number
  balance: number
  link: string | null
  party?: string
}

type BankOption = { id: string; code: string; name: string }
type PartyOption = { id: string; name: string; customer_number?: string; vendor_number?: string }

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  filters: { kind: Kind; id: string | null; ids?: string[]; category_id?: string | null; from: string; to: string; reversed?: boolean }
  options: { bank: BankOption[]; customer: PartyOption[]; supplier: PartyOption[]; amanat?: PartyOption[]; employee?: PartyOption[]; expense?: BankOption[]; partner?: PartyOption[]; groups?: { id: string; name: string; member_ids: string[] }[]; categories?: { id: string; name: string }[] }
  columns: { money_in: string; money_out: string; balance: string }
  statement: {
    rows: Row[]
    opening_balance: number
    closing_balance: number
    from: string
    to: string
    account?: string | null
    party?: string | null
    // Entries undone by a reversal, and the reversals, in this range (hidden unless asked for).
    reversed_count?: number
    // Everyone of the kind in one list ('all'): each row names its person.
    combined?: boolean
    // A customer category: each customer's opening, bought, paid and owes.
    category_summary?: { rows: { customer_id: string; name: string; opening: number; bought: number; paid: number; owes: number }[]; totals: { opening: number; bought: number; paid: number; owes: number } }
    // Employee statements: the period's totals, for the summary above the rows.
    totals?: { salary: number; earned: number; advances: number; advance_count: number; repaid: number; deductions: number; paid: number }
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
// Several people in one statement: a customer group, or any the user ticks.
const picked = ref<string[]>(props.filters.ids ?? [])
const categoryId = ref(props.filters.category_id ?? '')
const showReversed = ref(Boolean(props.filters.reversed))
const picking = ref(false)

watch(() => props.filters, (f) => {
  kind.value = f.kind
  partyId.value = f.id ?? ''
  picked.value = f.ids ?? []
  categoryId.value = f.category_id ?? ''
  picking.value = false
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
  if (kind.value === 'expense') {
    return (props.options.expense ?? []).map((a) => ({ id: a.id, label: a.name, sublabel: a.code }))
  }
  if (kind.value === 'partner') {
    return (props.options.partner ?? []).map((p) => ({ id: p.id, label: p.name }))
  }
  if (kind.value === 'employee') {
    return (props.options.employee ?? []).map((c) => ({ id: c.id, label: c.name, sublabel: c.customer_number ?? undefined }))
  }
  if (kind.value === 'amanat') {
    return (props.options.amanat ?? []).map((c) => ({ id: c.id, label: c.name, sublabel: c.customer_number }))
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
    id: partyId.value === 'some' || partyId.value === 'category' ? undefined : partyId.value || undefined,
    category_id: partyId.value === 'category' && categoryId.value ? categoryId.value : undefined,
    ids: partyId.value === 'some' && picked.value.length ? picked.value.join(',') : undefined,
    from: from.value,
    to: to.value,
    reversed: showReversed.value ? 1 : undefined,
  }, { preserveState: true, preserveScroll: true })
}
const toggleReversed = (v: boolean | 'indeterminate') => {
  showReversed.value = v === true
  reload()
}

// A fuel station's stock statement is a statement too: its tab opens that page.
const isFuelStation = computed(() => Boolean((usePage().props.auth as { fuelNavigation?: unknown } | undefined)?.fuelNavigation))

const changeKind = (value: Kind | string) => {
  if (value === 'stock') {
    router.visit(`/${props.company.slug}/fuel/reports/stock-statement?start_date=${from.value}&end_date=${to.value}`)
    return
  }
  kind.value = value as Kind
  partyId.value = ''
  categoryId.value = ''
  search.value = ''
  reload()
}

const groups = computed(() => (kind.value === 'customer' ? props.options.groups ?? [] : []))

const categories = computed(() => (kind.value === 'customer' ? props.options.categories ?? [] : []))

const changeParty = (value: string) => {
  if (value.startsWith('category:')) {
    categoryId.value = value.slice('category:'.length)
    partyId.value = 'category'
    picked.value = []
    reload()
    return
  }
  categoryId.value = ''
  if (value.startsWith('group:')) {
    picked.value = groups.value.find((g) => `group:${g.id}` === value)?.member_ids ?? []
    partyId.value = 'some'
    reload()
    return
  }
  if (value === 'some') {
    partyId.value = 'some'
    picking.value = true
    return
  }
  partyId.value = value
  picked.value = []
  reload()
}

const togglePick = (id: string, on: boolean) => {
  picked.value = on ? [...new Set([...picked.value, id])] : picked.value.filter((p) => p !== id)
}
const showPicked = () => {
  picking.value = false
  reload()
}

const partyLabel = computed(() => {
  if (kind.value === 'bank') return 'Account'
  if (kind.value === 'customer') return 'Customer'
  if (kind.value === 'amanat') return 'Holder'
  if (kind.value === 'employee') return 'Employee'
  if (kind.value === 'expense') return 'Account'
  if (kind.value === 'partner') return 'Partner'
  return 'Supplier'
})

const columns = computed<RegisterColumn<Row>[]>(() => [
  { key: 'date', label: 'Date', kind: 'date' },
  ...(props.statement.combined ? [{ key: 'party', label: 'Name', kind: 'text' } as RegisterColumn<Row>] : []),
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


const allLabel = computed(() => ({ customer: 'All customers', supplier: 'All suppliers', amanat: 'All holders', employee: 'All employees', expense: 'All expense accounts', partner: 'All partners', bank: '' })[kind.value])
const categoryName = computed(() => (props.options.categories ?? []).find((c) => c.id === categoryId.value)?.name ?? 'Category')
const pickedLabel = computed(() => {
  const group = (props.options.groups ?? []).find((g) => g.member_ids.length === picked.value.length && g.member_ids.every((id) => picked.value.includes(id)))
  return group ? `${group.name} · group` : `${picked.value.length} ${partyLabel.value.toLowerCase()}${picked.value.length === 1 ? '' : 's'}`
})
const statementTitle = computed(() => (props.statement.combined
  ? (props.filters.category_id ? `${categoryName.value} · category` : props.filters.ids?.length ? pickedLabel.value : allLabel.value)
  : props.statement.account || props.statement.party || 'No account or party selected'))
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
              <TabsTrigger v-if="(options.amanat ?? []).length > 0" value="amanat">Amanat</TabsTrigger>
              <TabsTrigger v-if="(options.employee ?? []).length > 0" value="employee">Employee</TabsTrigger>
              <TabsTrigger v-if="(options.expense ?? []).length > 0" value="expense">Expense</TabsTrigger>
              <TabsTrigger v-if="(options.partner ?? []).length > 0" value="partner">Partner</TabsTrigger>
              <TabsTrigger v-if="isFuelStation" value="stock">Stock</TabsTrigger>
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
                  <SelectItem v-if="kind !== 'bank'" value="all">{{ allLabel }}</SelectItem>
                  <SelectItem v-if="kind !== 'bank'" value="some">{{ partyId === 'some' && picked.length ? pickedLabel : 'Choose several…' }}</SelectItem>
                  <SelectItem v-for="g in groups" :key="g.id" :value="`group:${g.id}`">{{ g.name }} · group</SelectItem>
                  <SelectItem v-for="c in categories" :key="c.id" :value="`category:${c.id}`">{{ c.name }} · category</SelectItem>
                  <SelectSeparator v-if="kind !== 'bank'" />
                  <SelectItem v-for="opt in filteredOptions" :key="opt.id" :value="opt.id">
                    {{ opt.label }}<span v-if="opt.sublabel" class="text-text-tertiary"> · {{ opt.sublabel }}</span>
                  </SelectItem>
                </SelectContent>
              </Select>
              <div v-if="picking && kind !== 'bank'" class="max-h-64 space-y-1 overflow-y-auto rounded-md border p-2">
                <label v-for="opt in filteredOptions" :key="opt.id" class="flex items-center gap-2 rounded px-1 py-1 text-sm hover:bg-muted">
                  <Checkbox :model-value="picked.includes(opt.id)" @update:model-value="(v) => togglePick(opt.id, v === true)" />
                  <span class="truncate">{{ opt.label }}</span>
                </label>
                <div class="sticky bottom-0 flex items-center justify-between gap-2 bg-background pt-2">
                  <span class="text-xs text-muted-foreground">{{ picked.length }} chosen</span>
                  <Button size="sm" :disabled="!picked.length" @click="showPicked">Show</Button>
                </div>
              </div>
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
            <!-- An entry and its reversal cancel out, so they are hidden; the balance is the same. -->
            <label v-if="(statement.reversed_count ?? 0) > 0 || showReversed" class="flex items-center gap-2 text-sm text-muted-foreground">
              <Checkbox :model-value="showReversed" @update:model-value="toggleReversed" />
              Show reversed ({{ statement.reversed_count ?? 0 }})
            </label>
            <Button variant="outline" @click="printStatement">
              <Printer class="h-4 w-4" />
              Print
            </Button>
            <Button v-if="kind === 'customer' && partyId && !['all', 'some', 'category'].includes(partyId)" variant="outline" as-child>
              <Link :href="`/${company.slug}/consolidated-invoices/create?customer_id=${partyId}&from=${from}&to=${to}`">
                <FileText class="h-4 w-4" />
                Consolidated invoice
              </Link>
            </Button>
          </div>
        </CardContent>
      </Card>

      <div v-if="kind === 'employee' && statement.totals" class="grid gap-3 sm:grid-cols-5">
        <div class="rounded-lg border p-3">
          <div class="text-xs text-muted-foreground">Monthly salary</div>
          <div class="text-lg font-semibold tabular-nums"><MoneyText :amount="statement.totals.salary" :currency="currency" :fraction-digits="0" /></div>
        </div>
        <div class="rounded-lg border p-3">
          <div class="text-xs text-muted-foreground">Earned</div>
          <div class="text-lg font-semibold tabular-nums"><MoneyText :amount="statement.totals.earned - statement.totals.deductions" :currency="currency" :fraction-digits="0" /></div>
        </div>
        <div class="rounded-lg border p-3">
          <div class="text-xs text-muted-foreground">Advances · {{ statement.totals.advance_count }}</div>
          <div class="text-lg font-semibold tabular-nums"><MoneyText :amount="statement.totals.advances - statement.totals.repaid" :currency="currency" :fraction-digits="0" /></div>
        </div>
        <div class="rounded-lg border p-3">
          <div class="text-xs text-muted-foreground">Salary paid</div>
          <div class="text-lg font-semibold tabular-nums"><MoneyText :amount="statement.totals.paid" :currency="currency" :fraction-digits="0" /></div>
        </div>
        <div class="rounded-lg border p-3" :class="statement.closing_balance < 0 ? 'border-status-critical/40 bg-status-critical/10' : ''">
          <div class="text-xs text-muted-foreground">{{ statement.closing_balance < 0 ? 'They owe us' : 'We owe' }}</div>
          <div class="text-lg font-semibold tabular-nums"><MoneyText :amount="Math.abs(statement.closing_balance)" :currency="currency" :fraction-digits="0" /></div>
        </div>
      </div>

      <Card v-if="statement.category_summary">
        <CardHeader><CardTitle>By customer</CardTitle></CardHeader>
        <CardContent>
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs text-muted-foreground">
                <th class="py-1">Customer</th>
                <th class="py-1 text-right">Opening</th>
                <th class="py-1 text-right">Bought</th>
                <th class="py-1 text-right">Paid</th>
                <th class="py-1 text-right">Owes</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in statement.category_summary.rows" :key="r.customer_id" class="border-t">
                <td class="py-1">{{ r.name }}</td>
                <td class="py-1 text-right tabular-nums"><MoneyText :amount="r.opening" :currency="currency" :locale="moneyLocale" :show-currency="false" :fraction-digits="0" /></td>
                <td class="py-1 text-right tabular-nums"><MoneyText :amount="r.bought" :currency="currency" :locale="moneyLocale" :show-currency="false" :fraction-digits="0" /></td>
                <td class="py-1 text-right tabular-nums"><MoneyText :amount="r.paid" :currency="currency" :locale="moneyLocale" :show-currency="false" :fraction-digits="0" /></td>
                <td class="py-1 text-right tabular-nums"><MoneyText :amount="r.owes" :currency="currency" :locale="moneyLocale" :show-currency="false" :fraction-digits="0" /></td>
              </tr>
              <tr class="border-t font-semibold">
                <td class="py-1">Total</td>
                <td class="py-1 text-right tabular-nums"><MoneyText :amount="statement.category_summary.totals.opening" :currency="currency" :locale="moneyLocale" :show-currency="false" :fraction-digits="0" /></td>
                <td class="py-1 text-right tabular-nums"><MoneyText :amount="statement.category_summary.totals.bought" :currency="currency" :locale="moneyLocale" :show-currency="false" :fraction-digits="0" /></td>
                <td class="py-1 text-right tabular-nums"><MoneyText :amount="statement.category_summary.totals.paid" :currency="currency" :locale="moneyLocale" :show-currency="false" :fraction-digits="0" /></td>
                <td class="py-1 text-right tabular-nums"><MoneyText :amount="statement.category_summary.totals.owes" :currency="currency" :locale="moneyLocale" :show-currency="false" :fraction-digits="0" /></td>
              </tr>
            </tbody>
          </table>
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
