<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
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
  payment_number: string
  vendor?: { id: string; name: string }
  payment_date: string
  amount: number
  currency: string
   base_currency?: string
  payment_method: string
  reference_number: string | null
  notes: string | null
  allocations?: Allocation[]
}

const props = defineProps<{
  company: CompanyRef
  payment: PaymentRef
  journalTransactionId?: string | null
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Bill Payments', href: `/${props.company.slug}/bill-payments` },
  { title: props.payment.payment_number, href: `/${props.company.slug}/bill-payments/${props.payment.id}` },
]

const columns = [
  { key: 'bill_number', label: 'Bill #' },
  { key: 'amount_allocated', label: 'Allocated' },
  { key: 'base_amount_allocated', label: 'Base Allocated' },
  { key: 'applied_at', label: 'Applied At' },
]

const formatMoney = (val: number, currency: string) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'USD' }).format(val)

const allocationRows = computed(() =>
  (props.payment.allocations || []).map((a) => ({
    bill_number: a.bill?.bill_number || a.bill_id,
    amount_allocated: formatMoney(a.amount_allocated, props.payment.currency),
    base_amount_allocated: formatMoney(a.base_amount_allocated, props.payment.base_currency || props.company.base_currency),
    applied_at: a.applied_at,
  }))
)
</script>

<template>
  <Head :title="`Payment ${payment.payment_number}`" />
  <PageShell
    :title="`Payment ${payment.payment_number}`"
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
        <div class="font-semibold">{{ payment.payment_date }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Amount</div>
        <div class="font-semibold">{{ formatMoney(payment.amount, payment.currency) }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Method</div>
        <Badge variant="outline">{{ payment.payment_method }}</Badge>
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
      <div class="text-lg font-semibold mb-2">Allocations</div>
      <DataTable :columns="columns" :data="allocationRows" />
    </div>
  </PageShell>
</template>
