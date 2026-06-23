<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Badge } from '@/components/ui/badge'
import type { BreadcrumbItem } from '@/types'
import { useLexicon } from '@/composables/useLexicon'
import MoneyText from '@/components/MoneyText.vue'
import { formatDateTime } from '@/lib/datetime'
import { ExternalLink } from 'lucide-vue-next'

type CompanyRef = {
  id: string
  name: string
  slug: string
  base_currency: string
}

type ReportAccount = {
  id: string
  code: string
  name: string
  type: string
  debit: number
  credit: number
  net: number
  line_count: number
  transaction_count: number
}

type PeriodBreakdown = {
  period: string
  income: number
  expenses: number
  profit: number
}

type SourceBreakdown = {
  source: string
  income: number
  expenses: number
  profit: number
  transaction_count: number
}

type RecentLine = {
  id: string
  transaction_id: string
  transaction_number: string
  transaction_type: string
  transaction_date: string
  account_id: string
  account_code: string
  account_name: string
  account_type: string
  description: string | null
  debit: number
  credit: number
  net: number
}

const props = defineProps<{
  company: CompanyRef
  filters: { start: string; end: string }
  report: {
    income: ReportAccount[]
    expenses: ReportAccount[]
    period_breakdown: PeriodBreakdown[]
    source_breakdown: SourceBreakdown[]
    recent_lines: RecentLine[]
    totals: { income: number; expenses: number; profit: number }
  }
}>()

const { t } = useLexicon()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: t('profitAndLoss') },
])

const start = ref(props.filters.start)
const end = ref(props.filters.end)

watch(
  () => props.filters,
  (f) => {
    start.value = f.start
    end.value = f.end
  }
)

const moneyLocale = computed(() => {
  return props.company.base_currency === 'PKR' ? 'en-PK' : 'en-US'
}).value

const apply = () => {
  router.get(`/${props.company.slug}/reports/profit-loss`, { start: start.value, end: end.value }, { preserveScroll: true })
}

const openAccountDrilldown = (row: ReportAccount) => {
  router.get(`/${props.company.slug}/journals`, {
    account_id: row.id,
    start: start.value,
    end: end.value,
    status: 'posted',
  })
}

const openJournal = (id: string) => {
  router.get(`/${props.company.slug}/journals/${id}`)
}

const formatDate = (value: string) => formatDateTime(value, { mode: 'date' })
const formatSource = (value: string) => value.replace(/[._-]/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())

const incomeRows = computed(() => props.report.income)
const expenseRows = computed(() => props.report.expenses)
const periodRows = computed(() => props.report.period_breakdown ?? [])
const sourceRows = computed(() => props.report.source_breakdown ?? [])
const recentLines = computed(() => props.report.recent_lines ?? [])
</script>

<template>
  <Head :title="t('profitAndLoss')" />

  <PageShell :title="t('profitAndLoss')" :breadcrumbs="breadcrumbs">
    <div class="mx-auto w-full max-w-5xl space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>{{ t('dateRange') }}</CardTitle>
        </CardHeader>
        <CardContent class="grid gap-4 md:grid-cols-5">
          <div class="space-y-2 md:col-span-2">
            <Label>{{ t('startDate') }}</Label>
            <Input v-model="start" type="date" />
          </div>
          <div class="space-y-2 md:col-span-2">
            <Label>{{ t('endDate') }}</Label>
            <Input v-model="end" type="date" />
          </div>
          <div class="flex items-end">
            <Button class="w-full" @click="apply">{{ t('apply') }}</Button>
          </div>
        </CardContent>
      </Card>

      <div class="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader><CardTitle>{{ t('moneyIn') }}</CardTitle></CardHeader>
          <CardContent class="text-2xl font-semibold tabular-nums">
            <MoneyText :amount="report.totals.income" :currency="company.base_currency" :locale="moneyLocale" />
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle>{{ t('moneyOut') }}</CardTitle></CardHeader>
          <CardContent class="text-2xl font-semibold tabular-nums">
            <MoneyText :amount="report.totals.expenses" :currency="company.base_currency" :locale="moneyLocale" />
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle>{{ t('profit') }}</CardTitle></CardHeader>
          <CardContent class="text-2xl font-semibold tabular-nums">
            <MoneyText :amount="report.totals.profit" :currency="company.base_currency" :locale="moneyLocale" />
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{{ t('moneyIn') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-12 gap-3 text-sm font-medium text-muted-foreground">
            <div class="col-span-3">{{ t('category') }}</div>
            <div class="col-span-5">{{ t('description') }}</div>
            <div class="col-span-2 text-right">Entries</div>
            <div class="col-span-2 text-right">{{ t('amount') }}</div>
          </div>
          <Separator class="my-3" />
          <div v-if="incomeRows.length === 0" class="py-6 text-sm text-muted-foreground">
            {{ t('noReportData') }}
          </div>
          <div v-else class="space-y-2">
            <Button
              v-for="row in incomeRows"
              :key="row.id"
              variant="ghost"
              class="grid h-auto w-full grid-cols-12 gap-3 px-2 py-2 text-left text-sm"
              @click="openAccountDrilldown(row)"
            >
              <div class="col-span-3 font-mono text-muted-foreground">{{ row.code }}</div>
              <div class="col-span-5">{{ row.name }}</div>
              <div class="col-span-2 text-right text-muted-foreground">{{ row.transaction_count }} journals</div>
              <div class="col-span-2 text-right tabular-nums">
                <MoneyText :amount="row.net" :currency="company.base_currency" :locale="moneyLocale" />
              </div>
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{{ t('moneyOut') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-12 gap-3 text-sm font-medium text-muted-foreground">
            <div class="col-span-3">{{ t('category') }}</div>
            <div class="col-span-5">{{ t('description') }}</div>
            <div class="col-span-2 text-right">Entries</div>
            <div class="col-span-2 text-right">{{ t('amount') }}</div>
          </div>
          <Separator class="my-3" />
          <div v-if="expenseRows.length === 0" class="py-6 text-sm text-muted-foreground">
            {{ t('noReportData') }}
          </div>
          <div v-else class="space-y-2">
            <Button
              v-for="row in expenseRows"
              :key="row.id"
              variant="ghost"
              class="grid h-auto w-full grid-cols-12 gap-3 px-2 py-2 text-left text-sm"
              @click="openAccountDrilldown(row)"
            >
              <div class="col-span-3 font-mono text-muted-foreground">{{ row.code }}</div>
              <div class="col-span-5">{{ row.name }}</div>
              <div class="col-span-2 text-right text-muted-foreground">{{ row.transaction_count }} journals</div>
              <div class="col-span-2 text-right tabular-nums">
                <MoneyText :amount="row.net" :currency="company.base_currency" :locale="moneyLocale" />
              </div>
            </Button>
          </div>
        </CardContent>
      </Card>

      <div class="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>By Period</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="periodRows.length === 0" class="py-6 text-sm text-muted-foreground">{{ t('noReportData') }}</div>
            <div v-else class="space-y-2">
              <div v-for="row in periodRows" :key="row.period" class="grid grid-cols-4 gap-3 text-sm">
                <div class="font-medium">{{ row.period }}</div>
                <div class="text-right tabular-nums"><MoneyText :amount="row.income" :currency="company.base_currency" :locale="moneyLocale" /></div>
                <div class="text-right tabular-nums"><MoneyText :amount="row.expenses" :currency="company.base_currency" :locale="moneyLocale" /></div>
                <div class="text-right font-medium tabular-nums"><MoneyText :amount="row.profit" :currency="company.base_currency" :locale="moneyLocale" /></div>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>By Source</CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="sourceRows.length === 0" class="py-6 text-sm text-muted-foreground">{{ t('noReportData') }}</div>
            <div v-else class="space-y-2">
              <div v-for="row in sourceRows" :key="row.source" class="grid grid-cols-12 gap-3 text-sm">
                <div class="col-span-5">
                  <div class="font-medium">{{ formatSource(row.source) }}</div>
                  <div class="text-xs text-muted-foreground">{{ row.transaction_count }} journals</div>
                </div>
                <div class="col-span-3 text-right tabular-nums"><MoneyText :amount="row.income" :currency="company.base_currency" :locale="moneyLocale" /></div>
                <div class="col-span-2 text-right tabular-nums"><MoneyText :amount="row.expenses" :currency="company.base_currency" :locale="moneyLocale" /></div>
                <div class="col-span-2 text-right font-medium tabular-nums"><MoneyText :amount="row.profit" :currency="company.base_currency" :locale="moneyLocale" /></div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Recent Postings</CardTitle>
        </CardHeader>
        <CardContent>
          <div v-if="recentLines.length === 0" class="py-6 text-sm text-muted-foreground">{{ t('noReportData') }}</div>
          <div v-else class="space-y-2">
            <Button
              v-for="line in recentLines"
              :key="line.id"
              variant="ghost"
              class="grid h-auto w-full grid-cols-12 gap-3 px-2 py-2 text-left text-sm"
              @click="openJournal(line.transaction_id)"
            >
              <div class="col-span-2 text-muted-foreground">{{ formatDate(line.transaction_date) }}</div>
              <div class="col-span-3">
                <div class="font-medium">{{ line.transaction_number }}</div>
                <div class="text-xs text-muted-foreground">{{ formatSource(line.transaction_type) }}</div>
              </div>
              <div class="col-span-4">
                <div>{{ line.account_code }} - {{ line.account_name }}</div>
                <div v-if="line.description" class="truncate text-xs text-muted-foreground">{{ line.description }}</div>
              </div>
              <div class="col-span-2 text-right tabular-nums">
                <MoneyText :amount="line.net" :currency="company.base_currency" :locale="moneyLocale" />
              </div>
              <div class="col-span-1 flex justify-end">
                <Badge variant="outline">
                  <ExternalLink class="h-3 w-3" />
                </Badge>
              </div>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
