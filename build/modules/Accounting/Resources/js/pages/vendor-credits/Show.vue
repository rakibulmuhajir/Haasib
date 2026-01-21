<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { ReceiptText, Edit, ArrowLeft, DollarSign, Trash2 } from 'lucide-vue-next'

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
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
    currencyDisplay: 'narrowSymbol',
  }).format(val)

const applicationRows = computed(() =>
  (props.credit.applications || []).map((a) => ({
    bill_number: a.bill?.bill_number || a.bill_id,
    amount_applied: formatMoney(a.amount_applied, props.credit.currency),
    bill_balance_after: formatMoney(a.bill_balance_after, props.credit.currency),
    applied_at: a.applied_at,
  }))
)

const isEditable = computed(() => {
  return ['draft', 'received'].includes(props.credit.status)
})

const isApplicable = computed(() => {
  return ['received', 'draft'].includes(props.credit.status)
})

const canDelete = computed(() => {
  return ['draft', 'received'].includes(props.credit.status)
})

const editCredit = () => {
  router.get(`/${props.company.slug}/vendor-credits/${props.credit.id}/edit`)
}

const applyCredit = () => {
  router.get(`/${props.company.slug}/vendor-credits/${props.credit.id}/apply`)
}

const deleteCredit = () => {
  if (confirm('Are you sure you want to delete this vendor credit?')) {
    router.delete(`/${props.company.slug}/vendor-credits/${props.credit.id}`)
  }
}

const statusVariant = (status: string) => {
  if (status === 'draft') return 'secondary'
  if (status === 'received') return 'default'
  if (status === 'applied') return 'success'
  if (status === 'void') return 'destructive'
  return 'secondary'
}

const amountRemaining = computed(() => {
  const applied = props.credit.applications?.reduce((sum, app) => sum + app.amount_applied, 0) || 0
  return props.credit.amount - applied
})
</script>

<template>
  <Head :title="`Vendor Credit ${credit.credit_number}`" />
  <PageShell
    :title="`Credit ${credit.credit_number}`"
    :breadcrumbs="breadcrumbs"
    :icon="ReceiptText"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/vendor-credits`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back to Credits
      </Button>
      <Button v-if="isEditable" @click="editCredit">
        <Edit class="mr-2 h-4 w-4" />
        Edit
      </Button>
      <Button v-if="isApplicable" variant="outline" @click="applyCredit">
        <DollarSign class="mr-2 h-4 w-4" />
        Apply to Bills
      </Button>
      <Button v-if="canDelete" variant="destructive" @click="deleteCredit">
        <Trash2 class="mr-2 h-4 w-4" />
        Delete
      </Button>
    </template>

    <div class="grid gap-6 md:grid-cols-3">
      <div class="md:col-span-2 space-y-4">
        <div class="rounded-lg border bg-card p-4">
          <div class="text-lg font-semibold mb-4">Vendor Credit Details</div>
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">Vendor</div>
              <div class="font-semibold">
                <button
                  v-if="credit.vendor"
                  @click="router.get(`/${company.slug}/vendors/${credit.vendor.id}`)"
                  class="text-primary hover:text-primary/80 transition-colors"
                >
                  {{ credit.vendor.name }}
                </button>
                <span v-else>—</span>
              </div>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">Credit Date</div>
              <div class="font-semibold">{{ credit.credit_date }}</div>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">Credit Amount</div>
              <div class="font-semibold text-lg">{{ formatMoney(credit.amount, credit.currency) }}</div>
            </div>
            <div class="space-y-1">
              <div class="text-sm text-muted-foreground">Status</div>
              <Badge :variant="statusVariant(credit.status)">
                {{ credit.status }}
              </Badge>
            </div>
            <div class="space-y-1 md:col-span-2">
              <div class="text-sm text-muted-foreground">Reason</div>
              <div class="font-semibold">{{ credit.reason }}</div>
            </div>
            <div class="space-y-1 md:col-span-2">
              <div class="text-sm text-muted-foreground">Notes</div>
              <div class="font-semibold">{{ credit.notes || '—' }}</div>
            </div>
          </div>
        </div>

        <div v-if="amountRemaining > 0 && isApplicable" class="rounded-lg border border-green-200 bg-green-50 p-4">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm font-medium text-green-800">Available to Apply</div>
              <div class="text-lg font-bold text-green-900">{{ formatMoney(amountRemaining, credit.currency) }}</div>
            </div>
            <Button @click="applyCredit" variant="outline" class="border-green-300 text-green-800 hover:bg-green-100">
              <DollarSign class="mr-2 h-4 w-4" />
              Apply to Bills
            </Button>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div class="rounded-lg border bg-card p-4">
          <div class="text-lg font-semibold mb-4">Financial Summary</div>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-sm text-muted-foreground">Original Amount</span>
              <span class="font-medium">{{ formatMoney(credit.amount, credit.currency) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-muted-foreground">Applied Amount</span>
              <span class="font-medium">{{ formatMoney(credit.amount - amountRemaining, credit.currency) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-muted-foreground">Remaining Balance</span>
              <span class="font-medium">{{ formatMoney(amountRemaining, credit.currency) }}</span>
            </div>
            <div class="border-t pt-2">
              <div class="flex justify-between text-lg font-bold">
                <span>Status</span>
                <Badge :variant="statusVariant(credit.status)">
                  {{ credit.status }}
                </Badge>
              </div>
            </div>
          </div>
        </div>

        <div v-if="!isEditable" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
          <div class="text-sm font-medium text-yellow-800">
            This vendor credit cannot be modified in its current status.
          </div>
        </div>
      </div>
    </div>

    <div v-if="credit.applications && credit.applications.length > 0" class="mt-6">
      <div class="text-lg font-semibold mb-4">Applied to Bills</div>
      <div class="rounded-lg border bg-card">
        <DataTable :columns="columns" :data="applicationRows" />
      </div>
    </div>

    <div v-else class="mt-6">
      <div class="text-lg font-semibold mb-4">Applications</div>
      <div class="rounded-lg border bg-card p-6 text-center text-muted-foreground">
        <div class="text-sm">This credit hasn't been applied to any bills yet.</div>
        <Button v-if="isApplicable" @click="applyCredit" variant="outline" class="mt-3">
          <DollarSign class="mr-2 h-4 w-4" />
          Apply to Bills
        </Button>
      </div>
    </div>
  </PageShell>
</template>
