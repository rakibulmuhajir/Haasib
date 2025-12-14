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
import { ReceiptText, Plus, Eye, Pencil, Trash2 } from 'lucide-vue-next'

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

const allVendorsValue = '__all_vendors'
const allStatusValue = '__all_status'
const vendorId = ref(props.filters.vendor_id ?? allVendorsValue)
const status = ref(props.filters.status ?? allStatusValue)

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
  { key: 'actions', label: 'Actions' },
]

const formatMoney = (val: number, currency: string) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'USD' }).format(val)

const tableData = computed(() =>
  props.credits.data.map((c) => ({
    ...c, // Keep original data for actions
    id: c.id,
    credit_number: c.credit_number,
    vendor: c.vendor?.name ?? '—',
    credit_date: c.credit_date,
    amount: formatMoney(c.amount, c.currency),
    reason: c.reason,
    status: c.status,
    _original: c, // Store original data reference
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
      vendor_id: vendorId.value === allVendorsValue ? '' : vendorId.value,
      status: status.value === allStatusValue ? '' : status.value,
    },
    { preserveState: true }
  )
}

// Action methods
const viewCredit = (id: string) => {
  router.get(`/${props.company.slug}/vendor-credits/${id}`)
}

const editCredit = (id: string) => {
  router.get(`/${props.company.slug}/vendor-credits/${id}/edit`)
}

const deleteCredit = (id: string) => {
  if (confirm('Are you sure you want to delete this vendor credit?')) {
    router.delete(`/${props.company.slug}/vendor-credits/${id}`)
  }
}

const handleRowClick = (row: any) => {
  viewCredit(row.id)
}
</script>

<template>
  <Head title="Vendor Credits" />
  <PageShell
    title="Vendor Credits"
    :breadcrumbs="breadcrumbs"
    :icon="ReceiptText"
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
          <SelectItem :value="allVendorsValue">All vendors</SelectItem>
          <SelectItem v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</SelectItem>
        </SelectContent>
      </Select>
      <Select v-model="status" @update:modelValue="handleSearch">
        <SelectTrigger>
          <SelectValue placeholder="All status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="allStatusValue">All status</SelectItem>
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
        clickable
        hoverable
        @row-click="handleRowClick"
      >
        <template #credit_number="{ row }">
          <button
            class="font-medium text-primary hover:text-primary/80 transition-colors"
            @click.stop="viewCredit(row.id)"
          >
            {{ row.credit_number }}
          </button>
        </template>

        <template #vendor="{ row }">
          <button
            v-if="row._original.vendor"
            class="text-blue-600 hover:text-blue-800 transition-colors"
            @click.stop="router.get(`/${company.slug}/vendors/${row._original.vendor.id}`)"
          >
            {{ row.vendor }}
          </button>
          <span v-else>{{ row.vendor }}</span>
        </template>

        <template #status="{ value }">
          <Badge :variant="statusVariant(value)">{{ value }}</Badge>
        </template>

        <template #cell-actions="{ row }">
          <div class="flex items-center gap-1">
            <Button
              variant="ghost"
              size="icon"
              class="h-8 w-8"
              @click.stop="viewCredit(row.id)"
              title="View"
            >
              <Eye class="h-4 w-4" />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              class="h-8 w-8"
              @click.stop="editCredit(row.id)"
              title="Edit"
            >
              <Pencil class="h-4 w-4" />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              class="h-8 w-8 text-destructive hover:text-destructive"
              @click.stop="deleteCredit(row.id)"
              title="Delete"
            >
              <Trash2 class="h-4 w-4" />
            </Button>
          </div>
        </template>

        <!-- Mobile Card Template -->
        <template #mobile-card="{ row }">
          <div class="p-4 space-y-3 border-b">
            <div class="flex justify-between items-start">
              <div>
                <button
                  class="font-semibold text-primary hover:text-primary/80 transition-colors"
                  @click.stop="viewCredit(row.id)"
                >
                  {{ row.credit_number }}
                </button>
                <div class="text-sm text-muted-foreground mt-1">
                  <span v-if="row._original.vendor">
                    <button
                      class="text-blue-600 hover:text-blue-800 transition-colors"
                      @click.stop="router.get(`/${company.slug}/vendors/${row._original.vendor.id}`)"
                    >
                      {{ row.vendor }}
                    </button>
                  </span>
                  <span v-else>{{ row.vendor }}</span>
                </div>
              </div>
              <Badge :variant="statusVariant(row.status)">
                {{ row.status }}
              </Badge>
            </div>
            <div class="flex justify-between items-center">
              <div>
                <div class="text-sm text-muted-foreground">{{ row.credit_date }}</div>
                <div class="font-medium">{{ row.reason }}</div>
              </div>
              <div class="text-lg font-bold">{{ row.amount }}</div>
            </div>
            <div class="flex gap-2 pt-2">
              <Button size="sm" @click.stop="viewCredit(row.id)">View</Button>
              <Button size="sm" variant="outline" @click.stop="editCredit(row.id)">Edit</Button>
              <Button size="sm" variant="destructive" @click.stop="deleteCredit(row.id)">Delete</Button>
            </div>
          </div>
        </template>
      </DataTable>
    </div>
  </PageShell>
</template>
