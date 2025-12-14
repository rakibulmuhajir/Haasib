<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import type { BreadcrumbItem } from '@/types'
import { useLexicon } from '@/composables/useLexicon'
import MoneyText from '@/components/MoneyText.vue'

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
}

const props = defineProps<{
  company: CompanyRef
  filters: { start: string; end: string }
  report: {
    income: ReportAccount[]
    expenses: ReportAccount[]
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

const incomeRows = computed(() => props.report.income)
const expenseRows = computed(() => props.report.expenses)
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
            <div class="col-span-7">{{ t('description') }}</div>
            <div class="col-span-2 text-right">{{ t('amount') }}</div>
          </div>
          <Separator class="my-3" />
          <div v-if="incomeRows.length === 0" class="py-6 text-sm text-muted-foreground">
            {{ t('noReportData') }}
          </div>
          <div v-else class="space-y-2">
            <div v-for="row in incomeRows" :key="row.id" class="grid grid-cols-12 gap-3 text-sm">
              <div class="col-span-3 font-mono text-muted-foreground">{{ row.code }}</div>
              <div class="col-span-7">{{ row.name }}</div>
              <div class="col-span-2 text-right tabular-nums">
                <MoneyText :amount="row.net" :currency="company.base_currency" :locale="moneyLocale" />
              </div>
            </div>
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
            <div class="col-span-7">{{ t('description') }}</div>
            <div class="col-span-2 text-right">{{ t('amount') }}</div>
          </div>
          <Separator class="my-3" />
          <div v-if="expenseRows.length === 0" class="py-6 text-sm text-muted-foreground">
            {{ t('noReportData') }}
          </div>
          <div v-else class="space-y-2">
            <div v-for="row in expenseRows" :key="row.id" class="grid grid-cols-12 gap-3 text-sm">
              <div class="col-span-3 font-mono text-muted-foreground">{{ row.code }}</div>
              <div class="col-span-7">{{ row.name }}</div>
              <div class="col-span-2 text-right tabular-nums">
                <MoneyText :amount="row.net" :currency="company.base_currency" :locale="moneyLocale" />
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
