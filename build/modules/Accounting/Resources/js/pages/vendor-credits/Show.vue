<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import type { BreadcrumbItem } from '@/types'
import { ReceiptText } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface Application {
  bill_id: string
  amount_applied: number
  applied_at: string
  bill_balance_before: number
  bill_balance_after: number
  bill?: {
    bill_number: string
  }
}

interface CreditRef {
  id: string
  credit_number: string
  vendor?: { id: string; name: string }
  credit_date: string
  amount: number
  currency: string
  reason: string
  status: string
  notes: string | null
  applications?: Application[]
}

const props = defineProps<{
  company: CompanyRef
  credit: CreditRef
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Vendor Credits', href: `/${props.company.slug}/vendor-credits` },
  { title: props.credit.credit_number, href: `/${props.company.slug}/vendor-credits/${props.credit.id}` },
]

const columns = [
  { key: 'bill_number', label: 'Bill #' },
  { key: 'amount_applied', label: 'Applied' },
  { key: 'bill_balance_after', label: 'Bill Balance After' },
  { key: 'applied_at', label: 'Applied At' },
]

const formatMoney = (val: number, currency: string) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'USD' }).format(val)

const applicationRows = computed(() =>
  (props.credit.applications || []).map((a) => ({
    bill_number: a.bill?.bill_number || a.bill_id,
    amount_applied: formatMoney(a.amount_applied, props.credit.currency),
    bill_balance_after: formatMoney(a.bill_balance_after, props.credit.currency),
    applied_at: a.applied_at,
  }))
)
</script>

<template>
  <Head :title="`Vendor Credit ${credit.credit_number}`" />
  <PageShell
    :title="`Credit ${credit.credit_number}`"
    :breadcrumbs="breadcrumbs"
    :icon="ReceiptText"
  >
    <div class="grid gap-4 md:grid-cols-3">
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Vendor</div>
        <div class="font-semibold">{{ credit.vendor?.name ?? '—' }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Date</div>
        <div class="font-semibold">{{ credit.credit_date }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Amount</div>
        <div class="font-semibold">{{ formatMoney(credit.amount, credit.currency) }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Reason</div>
        <div class="font-semibold">{{ credit.reason }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Status</div>
        <Badge :variant="credit.status === 'applied' ? 'success' : credit.status === 'void' ? 'secondary' : 'default'">
          {{ credit.status }}
        </Badge>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Notes</div>
        <div class="font-semibold">{{ credit.notes || '—' }}</div>
      </div>
    </div>

    <div class="mt-6">
      <div class="text-lg font-semibold mb-2">Applications</div>
      <DataTable :columns="columns" :data="applicationRows" />
    </div>
  </PageShell>
</template>
