<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Card, CardContent, CardFigure, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import LedgerRegister from '@/components/LedgerRegister.vue'
import type { RegisterColumn } from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import type { BreadcrumbItem } from '@/types'
import { Download } from 'lucide-vue-next'

type Row = {
  payment_id: string
  payment_number: string
  reference: string | null
  url: string
  side: 'made' | 'received'
  kind: 'deduction' | 'reversal'
  date: string
  party: string | null
  tax_id: string | null
  filer_status: string | null
  section: string | null
  gross: number
  rate: number | null
  wht: number
}

type Section = { side: 'made' | 'received'; section: string | null; count: number; gross: number; wht: number }

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  filters: { from: string; to: string; side: 'all' | 'made' | 'received' }
  taxOn: boolean
  report: { rows: Row[]; sections: Section[]; totals: { gross: number; wht: number } }
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Withholding Tax' },
])

const from = ref(props.filters.from)
const to = ref(props.filters.to)
const side = ref(props.filters.side)
watch(() => props.filters, (f) => { from.value = f.from; to.value = f.to; side.value = f.side })

const currency = computed(() => props.company.base_currency || 'PKR')
const locale = computed(() => (currency.value === 'PKR' ? 'en-PK' : 'en-US'))

const query = computed(() => ({ from: from.value, to: to.value, side: side.value }))
const apply = () => router.get(`/${props.company.slug}/reports/withholding-tax`, query.value, { preserveScroll: true })
const csvHref = computed(() => `/${props.company.slug}/reports/withholding-tax?${new URLSearchParams({ ...query.value, format: 'csv' }).toString()}`)

const sideLabel = (s: 'made' | 'received') => (s === 'made' ? 'By us' : 'From us')
const statusLabel: Record<string, string> = { filer: 'Filer', non_filer: 'Non-filer', unknown: '' }

const made = computed(() => props.report.sections.filter((s) => s.side === 'made').reduce((n, s) => n + s.wht, 0))
const received = computed(() => props.report.sections.filter((s) => s.side === 'received').reduce((n, s) => n + s.wht, 0))

const columns: RegisterColumn<Row>[] = [
  { key: 'date', label: 'Date', kind: 'date' },
  { key: 'side', label: 'Deducted', kind: 'text' },
  { key: 'party', label: 'Party', kind: 'text' },
  { key: 'section', label: 'Section', kind: 'ref' },
  { key: 'gross', label: 'Gross', kind: 'amount' },
  { key: 'rate', label: 'Rate', kind: 'amount' },
  { key: 'wht', label: 'WHT', kind: 'amount' },
  { key: 'payment_number', label: 'Payment', kind: 'ref' },
]

const sectionColumns: RegisterColumn<Section>[] = [
  { key: 'side', label: 'Deducted', kind: 'text' },
  { key: 'section', label: 'Section', kind: 'ref' },
  { key: 'count', label: 'Payments', kind: 'amount' },
  { key: 'gross', label: 'Gross', kind: 'amount' },
  { key: 'wht', label: 'WHT', kind: 'amount' },
]

const rowKey = (row: Row) => `${row.payment_id}-${row.kind}`
const sectionKey = (row: Section) => `${row.side}-${row.section ?? ''}`
const open = (row: Row) => router.get(row.url)
</script>

<template>
  <Head title="Withholding Tax" />

  <PageShell
    title="Withholding Tax"
    description="Tax deducted from vendors and tax customers deducted from us (WHT), by section."
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" as-child>
        <a :href="csvHref" download>
          <Download class="mr-2 h-4 w-4" />
          CSV
        </a>
      </Button>
    </template>

    <div class="mx-auto w-full max-w-6xl space-y-6">
      <Card variant="form">
        <CardHeader><CardTitle>Period</CardTitle></CardHeader>
        <CardContent class="grid gap-4 md:grid-cols-4">
          <div class="space-y-2">
            <Label for="from">From</Label>
            <Input id="from" v-model="from" type="date" />
          </div>
          <div class="space-y-2">
            <Label for="to">To</Label>
            <Input id="to" v-model="to" type="date" />
          </div>
          <div class="space-y-2">
            <Label for="side">Deducted</Label>
            <Select v-model="side">
              <SelectTrigger id="side"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">By us and from us</SelectItem>
                <SelectItem value="made">By us</SelectItem>
                <SelectItem value="received">From us</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="flex items-end">
            <Button class="w-full" @click="apply">Apply</Button>
          </div>
        </CardContent>
      </Card>

      <p v-if="!taxOn" class="text-sm text-muted-foreground">
        Charge tax is off. Only payments that already withheld tax are listed.
      </p>

      <div class="grid gap-4 md:grid-cols-2">
        <Card variant="figure">
          <CardHeader><CardTitle>Deducted by us (owed to tax authority)</CardTitle></CardHeader>
          <CardContent>
            <CardFigure><MoneyText :amount="made" :currency="currency" :locale="locale" /></CardFigure>
          </CardContent>
        </Card>
        <Card variant="figure">
          <CardHeader><CardTitle>Deducted from us (advance tax)</CardTitle></CardHeader>
          <CardContent>
            <CardFigure><MoneyText :amount="received" :currency="currency" :locale="locale" /></CardFigure>
          </CardContent>
        </Card>
      </div>

      <LedgerRegister
        :data="report.sections"
        :columns="sectionColumns"
        :key-field="sectionKey"
        title="By section"
        :totals="{ gross: report.totals.gross, wht: report.totals.wht }"
        totals-label="Total"
      >
        <template #empty>No tax withheld in this period.</template>
        <template #cell-side="{ row }">{{ sideLabel(row.side) }}</template>
        <template #cell-section="{ row }">{{ row.section ?? '—' }}</template>
        <template #cell-gross="{ row }"><MoneyText :amount="row.gross" :currency="currency" :locale="locale" :show-currency="false" /></template>
        <template #cell-wht="{ row }"><MoneyText :amount="row.wht" :currency="currency" :locale="locale" :show-currency="false" /></template>
        <template #total-gross><MoneyText :amount="report.totals.gross" :currency="currency" :locale="locale" :show-currency="false" /></template>
        <template #total-wht><MoneyText :amount="report.totals.wht" :currency="currency" :locale="locale" :show-currency="false" /></template>
      </LedgerRegister>

      <LedgerRegister
        :data="report.rows"
        :columns="columns"
        :key-field="rowKey"
        clickable
        title="Deductions"
        description="A voided payment shows its reversal on the day it was reversed. Click a line to open the payment."
        @row-click="open"
      >
        <template #empty>No tax withheld in this period.</template>
        <template #cell-side="{ row }">
          {{ sideLabel(row.side) }}<span v-if="row.kind === 'reversal'" class="ml-2 text-text-metadata">Reversal</span>
        </template>
        <template #cell-party="{ row }">
          <span>{{ row.party ?? '—' }}</span>
          <span v-if="row.tax_id" class="ml-2 text-text-metadata">{{ row.tax_id }}</span>
          <span v-if="row.filer_status && statusLabel[row.filer_status]" class="ml-2 text-text-metadata">{{ statusLabel[row.filer_status] }}</span>
        </template>
        <template #cell-section="{ row }">{{ row.section ?? '—' }}</template>
        <template #cell-gross="{ row }"><MoneyText :amount="row.gross" :currency="currency" :locale="locale" :show-currency="false" /></template>
        <template #cell-rate="{ row }">{{ row.rate === null ? '—' : `${row.rate}%` }}</template>
        <template #cell-wht="{ row }"><MoneyText :amount="row.wht" :currency="currency" :locale="locale" :show-currency="false" /></template>
      </LedgerRegister>
    </div>
  </PageShell>
</template>
