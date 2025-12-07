<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { ReceiptRefund, Plus } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface VendorRef {
  id: string
  name: string
}

interface CreditRow {
  id: string
  credit_number: string
  vendor: VendorRef | null
  credit_date: string
  amount: number
  currency: string
  reason: string
  status: string
}

interface PaginatedCredits {
  data: CreditRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  credits: PaginatedCredits
  vendors: VendorRef[]
  filters: {
    vendor_id?: string
    status?: string
  }
}>()

const vendorId = ref(props.filters.vendor_id ?? '')
const status = ref(props.filters.status ?? '')

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Vendor Credits', href: `/${props.company.slug}/vendor-credits` },
]

const columns = [
  { key: 'credit_number', label: 'Credit #' },
  { key: 'vendor', label: 'Vendor' },
  { key: 'credit_date', label: 'Date' },
  { key: 'amount', label: 'Amount' },
  { key: 'reason', label: 'Reason' },
  { key: 'status', label: 'Status' },
]

const formatMoney = (val: number, currency: string) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'USD' }).format(val)

const tableData = computed(() =>
  props.credits.data.map((c) => ({
    id: c.id,
    credit_number: c.credit_number,
    vendor: c.vendor?.name ?? '—',
    credit_date: c.credit_date,
    amount: formatMoney(c.amount, c.currency),
    reason: c.reason,
    status: c.status,
  }))
)

const statusVariant = (s: string) => {
  if (s === 'draft') return 'secondary'
  if (s === 'received') return 'default'
  if (s === 'applied') return 'success'
  if (s === 'void') return 'secondary'
  return 'secondary'
}

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/vendor-credits`,
    {
      vendor_id: vendorId.value,
      status: status.value,
    },
    { preserveState: true }
  )
}
</script>

<template>
  <Head title="Vendor Credits" />
  <PageShell
    title="Vendor Credits"
    :breadcrumbs="breadcrumbs"
    :icon="ReceiptRefund"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/vendor-credits/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Credit
      </Button>
    </template>

    <div class="mb-4 grid gap-3 md:grid-cols-3">
      <Select v-model="vendorId" @update:modelValue="handleSearch">
        <SelectTrigger>
          <SelectValue placeholder="All vendors" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="">All vendors</SelectItem>
          <SelectItem v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</SelectItem>
        </SelectContent>
      </Select>
      <Select v-model="status" @update:modelValue="handleSearch">
        <SelectTrigger>
          <SelectValue placeholder="All status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="">All status</SelectItem>
          <SelectItem value="draft">Draft</SelectItem>
          <SelectItem value="received">Received</SelectItem>
          <SelectItem value="applied">Applied</SelectItem>
          <SelectItem value="void">Void</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="!credits.data.length">
      <EmptyState
        title="No vendor credits yet"
        description="Create your first vendor credit."
        cta-text="New Credit"
        @click="router.get(`/${company.slug}/vendor-credits/create`)"
      />
    </div>

    <div v-else>
      <DataTable
        :columns="columns"
        :data="tableData"
        :pagination="credits"
      >
        <template #status="{ value }">
          <Badge :variant="statusVariant(value)">{{ value }}</Badge>
        </template>
      </DataTable>
    </div>
  </PageShell>
</template>
