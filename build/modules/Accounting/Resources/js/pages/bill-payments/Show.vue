<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import DateTimeText from '@/components/DateTimeText.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { CreditCard } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface Allocation {
  bill_id: string
  amount_allocated: number
  base_amount_allocated: number
  applied_at: string
  bill?: {
    bill_number: string
  }
}

interface PaymentRef {
  id: string
  payment_group_id?: string | null
  payment_group_number?: string | null
  payment_number: string
  vendor?: { id: string; name: string }
  payment_date: string
  amount: number
  currency: string
  base_currency?: string
  payment_method: string
  reference_number: string | null
  notes: string | null
  payment_account?: { id: string; code: string; name: string } | null
  allocations?: Allocation[]
}

const props = defineProps<{
  company: CompanyRef
  payment: PaymentRef
  groupPayments?: PaymentRef[]
  journalTransactionId?: string | null
}>()

const paymentTitle = computed(() => props.payment.payment_group_number || props.payment.payment_number)

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Bill Payments', href: `/${props.company.slug}/bill-payments` },
  { title: paymentTitle.value, href: `/${props.company.slug}/bill-payments/${props.payment.id}` },
]

const columns = [
  { key: 'bill_number', label: 'Bill #' },
  { key: 'amount_allocated', label: 'Allocated' },
  { key: 'base_amount_allocated', label: 'Base Allocated' },
  { key: 'applied_at', label: 'Applied At' },
]

const sourceColumns = [
  { key: 'payment_number', label: 'Source #' },
  { key: 'account', label: 'Account' },
  { key: 'method', label: 'Method' },
  { key: 'amount', label: 'Amount' },
  { key: 'reference', label: 'Reference' },
]

const formatMoney = (val: number, currency: string) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
    currencyDisplay: 'narrowSymbol',
  }).format(val)

const allocationRows = computed(() =>
  (props.groupPayments || [props.payment])
    .flatMap((payment) => payment.allocations || [])
    .map((a) => ({
      bill_number: a.bill?.bill_number || a.bill_id,
      amount_allocated: formatMoney(a.amount_allocated, props.payment.currency),
      base_amount_allocated: formatMoney(a.base_amount_allocated, props.payment.base_currency || props.company.base_currency),
      applied_at: a.applied_at,
    }))
)

const sourceRows = computed(() =>
  (props.groupPayments || [props.payment]).map((payment) => ({
    payment_number: payment.payment_number,
    account: payment.payment_account
      ? `${payment.payment_account.code} — ${payment.payment_account.name}`
      : '—',
    method: payment.payment_method.replace(/_/g, ' '),
    amount: formatMoney(payment.amount, payment.currency),
    reference: payment.reference_number || '—',
  }))
)

const groupedAmount = computed(() =>
  (props.groupPayments || [props.payment]).reduce((sum, payment) => sum + Number(payment.amount || 0), 0)
)
</script>

<template>
  <Head :title="`Payment ${paymentTitle}`" />
  <PageShell
    :title="`Payment ${paymentTitle}`"
    :breadcrumbs="breadcrumbs"
    :icon="CreditCard"
  >
    <template #actions>
      <Button
        v-if="journalTransactionId"
        variant="outline"
        @click="router.get(`/${company.slug}/journals/${journalTransactionId}`)"
      >
        View Journal
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-3">
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Vendor</div>
        <div class="font-semibold">{{ payment.vendor?.name ?? '—' }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Date</div>
        <div class="font-semibold">
          <DateTimeText :value="payment.payment_date" mode="date" />
        </div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Amount</div>
        <div class="font-semibold">{{ formatMoney(groupedAmount, payment.currency) }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Sources</div>
        <Badge variant="outline">{{ sourceRows.length }}</Badge>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Reference</div>
        <div class="font-semibold">{{ payment.reference_number || '—' }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Notes</div>
        <div class="font-semibold">{{ payment.notes || '—' }}</div>
      </div>
    </div>

    <div class="mt-6">
      <div class="text-lg font-semibold mb-2">Payment Sources</div>
      <DataTable :columns="sourceColumns" :data="sourceRows" />
    </div>

    <div class="mt-6">
      <div class="text-lg font-semibold mb-2">Allocations</div>
      <DataTable :columns="columns" :data="allocationRows">
        <template #cell-applied_at="{ value }">
          <DateTimeText :value="value" mode="datetime" />
        </template>
      </DataTable>
    </div>
  </PageShell>
</template>
