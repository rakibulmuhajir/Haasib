<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { BreadcrumbItem } from '@/types'
import { CalendarDays, ReceiptText, Tags, WalletCards } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface Company {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface Filters {
  start_date: string
  end_date: string
  group_by: 'day' | 'week' | 'month'
  account_id: string
  source: string
}

interface Totals {
  amount: number
  line_count: number
  account_count: number
  transaction_count: number
}

interface PeriodRow {
  key: string
  label: string
  amount: number
  line_count: number
  transaction_count: number
  detail_url_id: string | null
}

interface AccountRow {
  account_id: string
  account_code: string
  account_name: string
  account_type: string
  amount: number
  line_count: number
}

interface SourceRow {
  source: string
  label: string
  amount: number
  line_count: number
}

interface DetailRow {
  line_id: string
  transaction_id: string
  transaction_number: string
  date_label: string
  account_code: string
  account_name: string
  description: string
  amount: number
  source_key: string
  source_label: string
  reference_id: string | null
  detail_route: 'daily_close' | 'bill' | 'journal'
}

interface AccountOption {
  id: string
  code: string
  name: string
}

interface SourceOption {
  value: string
  label: string
}

const props = defineProps<{
  company: Company
  filters: Filters
  totals: Totals
  periodRows: PeriodRow[]
  accountRows: AccountRow[]
  sourceRows: SourceRow[]
  detailRows: DetailRow[]
  accountOptions: AccountOption[]
  sourceOptions: SourceOption[]
}>()

const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)
const groupBy = ref(props.filters.group_by)
const accountId = ref(props.filters.account_id)
const source = ref(props.filters.source)

const symbol = computed(() => currencySymbol(props.company.base_currency))
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Reports', href: `/${props.company.slug}/fuel/reports/performance` },
  { title: 'Expenses', href: `/${props.company.slug}/fuel/reports/expenses` },
])

const money = (amount: number) => `${symbol.value} ${new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
}).format(amount || 0)}`

const number = (amount: number) => new Intl.NumberFormat('en-US').format(amount || 0)

const applyFilters = () => {
  router.get(`/${props.company.slug}/fuel/reports/expenses`, {
    start_date: startDate.value,
    end_date: endDate.value,
    group_by: groupBy.value,
    account_id: accountId.value,
    source: source.value,
  }, {
    preserveScroll: true,
    preserveState: true,
  })
}

const isoDate = (date: Date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const setRange = (range: 'today' | 'last7' | 'month' | 'lastMonth') => {
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())

  if (range === 'today') {
    startDate.value = isoDate(today)
    endDate.value = isoDate(today)
  } else if (range === 'last7') {
    const start = new Date(today)
    start.setDate(today.getDate() - 6)
    startDate.value = isoDate(start)
    endDate.value = isoDate(today)
  } else if (range === 'lastMonth') {
    const start = new Date(today.getFullYear(), today.getMonth() - 1, 1)
    const end = new Date(today.getFullYear(), today.getMonth(), 0)
    startDate.value = isoDate(start)
    endDate.value = isoDate(end)
  } else {
    const start = new Date(today.getFullYear(), today.getMonth(), 1)
    startDate.value = isoDate(start)
    endDate.value = isoDate(today)
  }

  applyFilters()
}

const detailHref = (row: DetailRow) => {
  if (row.detail_route === 'daily_close') return `/${props.company.slug}/fuel/daily-close/${row.transaction_id}`
  if (row.detail_route === 'bill' && row.reference_id) return `/${props.company.slug}/bills/${row.reference_id}`
  return `/${props.company.slug}/journals/${row.transaction_id}`
}
</script>

<template>
  <Head title="Expenses" />

  <PageShell
    title="Expenses"
    description="Posted operating expenses by date, account, and source."
    :icon="ReceiptText"
    :breadcrumbs="breadcrumbs"
  >
    <div class="space-y-5">
      <Card>
        <CardHeader class="pb-3">
          <CardTitle class="text-base">Filters</CardTitle>
          <CardDescription>Fuel COGS stays in Product Profitability. This report focuses on operating expenses.</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap gap-2">
              <Button variant="outline" size="sm" @click="setRange('today')">Today</Button>
              <Button variant="outline" size="sm" @click="setRange('last7')">Last 7 days</Button>
              <Button variant="outline" size="sm" @click="setRange('month')">This month</Button>
              <Button variant="outline" size="sm" @click="setRange('lastMonth')">Last month</Button>
            </div>

            <div class="grid gap-1.5">
              <Label for="start_date">From</Label>
              <Input id="start_date" v-model="startDate" type="date" class="w-40" />
            </div>

            <div class="grid gap-1.5">
              <Label for="end_date">To</Label>
              <Input id="end_date" v-model="endDate" type="date" class="w-40" />
            </div>

            <div class="grid gap-1.5">
              <Label>Group</Label>
              <Select v-model="groupBy">
                <SelectTrigger class="w-36">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="day">Daily</SelectItem>
                  <SelectItem value="week">Weekly</SelectItem>
                  <SelectItem value="month">Monthly</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="grid gap-1.5">
              <Label>Account</Label>
              <Select v-model="accountId">
                <SelectTrigger class="w-64">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All expense accounts</SelectItem>
                  <SelectItem v-for="account in accountOptions" :key="account.id" :value="account.id">
                    {{ account.code }} — {{ account.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="grid gap-1.5">
              <Label>Source</Label>
              <Select v-model="source">
                <SelectTrigger class="w-44">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in sourceOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <Button @click="applyFilters">Apply</Button>
          </div>
        </CardContent>
      </Card>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Total expenses</CardDescription>
            <CardTitle class="text-2xl">{{ money(totals.amount) }}</CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <ReceiptText class="h-4 w-4 text-red-700" />
            {{ number(totals.line_count) }} posted lines
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Accounts used</CardDescription>
            <CardTitle class="text-2xl">{{ number(totals.account_count) }}</CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <Tags class="h-4 w-4 text-sky-700" />
            Expense categories
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Source documents</CardDescription>
            <CardTitle class="text-2xl">{{ number(totals.transaction_count) }}</CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <CalendarDays class="h-4 w-4 text-violet-700" />
            Posted transactions
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardDescription>Average per line</CardDescription>
            <CardTitle class="text-2xl">{{ money(totals.line_count ? totals.amount / totals.line_count : 0) }}</CardTitle>
          </CardHeader>
          <CardContent class="flex items-center gap-2 text-sm text-muted-foreground">
            <WalletCards class="h-4 w-4 text-amber-700" />
            Operating expenses only
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-5 xl:grid-cols-3">
        <Card class="xl:col-span-2">
          <CardHeader>
            <CardTitle class="text-base">Expenses by {{ groupBy }}</CardTitle>
            <CardDescription>Grouped from posted expense journal lines.</CardDescription>
          </CardHeader>
          <CardContent class="p-0">
            <div v-if="periodRows.length === 0" class="py-12 text-center text-muted-foreground">
              No posted expenses found for this range.
            </div>
            <div v-else class="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Period</TableHead>
                    <TableHead class="text-right">Amount</TableHead>
                    <TableHead class="text-right">Lines</TableHead>
                    <TableHead class="text-right">Transactions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="row in periodRows" :key="row.key">
                    <TableCell class="font-medium">{{ row.label }}</TableCell>
                    <TableCell class="text-right">{{ money(row.amount) }}</TableCell>
                    <TableCell class="text-right">{{ number(row.line_count) }}</TableCell>
                    <TableCell class="text-right">
                      <Link
                        v-if="row.detail_url_id"
                        :href="`/${company.slug}/journals/${row.detail_url_id}`"
                        class="text-primary underline-offset-4 hover:underline"
                      >
                        {{ number(row.transaction_count) }}
                      </Link>
                      <span v-else>{{ number(row.transaction_count) }}</span>
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="text-base">By Source</CardTitle>
            <CardDescription>Where the expense entered the system.</CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="sourceRows.length === 0" class="py-8 text-center text-muted-foreground">
              No source data.
            </div>
            <div v-else class="space-y-3">
              <div v-for="row in sourceRows" :key="row.source" class="rounded-md border p-3">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <div class="font-medium">{{ row.label }}</div>
                    <div class="text-sm text-muted-foreground">{{ number(row.line_count) }} lines</div>
                  </div>
                  <div class="font-semibold">{{ money(row.amount) }}</div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">By Account</CardTitle>
          <CardDescription>Expense categories ranked by amount.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <div v-if="accountRows.length === 0" class="py-12 text-center text-muted-foreground">
            No expense account totals found.
          </div>
          <div v-else class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Account</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead class="text-right">Lines</TableHead>
                  <TableHead class="text-right">Amount</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="row in accountRows" :key="row.account_id">
                  <TableCell>
                    <div class="font-medium">{{ row.account_code }} — {{ row.account_name }}</div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline">{{ row.account_type.replace('_', ' ') }}</Badge>
                  </TableCell>
                  <TableCell class="text-right">{{ number(row.line_count) }}</TableCell>
                  <TableCell class="text-right font-medium">{{ money(row.amount) }}</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">Expense Lines</CardTitle>
          <CardDescription>Drill down to Daily Close, bill, or journal entry.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <div v-if="detailRows.length === 0" class="py-12 text-center text-muted-foreground">
            No expense lines found.
          </div>
          <div v-else class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Source</TableHead>
                  <TableHead>Account</TableHead>
                  <TableHead>Description</TableHead>
                  <TableHead class="text-right">Amount</TableHead>
                  <TableHead class="text-right">Open</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="row in detailRows" :key="row.line_id">
                  <TableCell>{{ row.date_label }}</TableCell>
                  <TableCell>
                    <Badge variant="outline">{{ row.source_label }}</Badge>
                  </TableCell>
                  <TableCell>
                    <div class="font-medium">{{ row.account_code }}</div>
                    <div class="text-xs text-muted-foreground">{{ row.account_name }}</div>
                  </TableCell>
                  <TableCell>{{ row.description }}</TableCell>
                  <TableCell class="text-right font-medium">{{ money(row.amount) }}</TableCell>
                  <TableCell class="text-right">
                    <Link :href="detailHref(row)" class="text-primary underline-offset-4 hover:underline">
                      {{ row.transaction_number }}
                    </Link>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
