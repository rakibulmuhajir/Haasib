<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { BreadcrumbItem } from '@/types'
import { CreditCard, AlertTriangle, CheckCircle, Clock, Search, Banknote } from 'lucide-vue-next'

interface ParcoSale {
  id: string
  invoice_id: string
  customer_name: string
  invoice_number: string
  invoice_date: string
  amount: number
  settled_amount: number
  outstanding: number
  status: 'pending' | 'settled' | 'overdue'
}

interface ParcoSummary {
  total_pending: number
  total_settled_today: number
  total_outstanding: number
  count_pending: number
}

const props = defineProps<{
  pendingSales: ParcoSale[]
  summary: ParcoSummary
  todaySettlements: ParcoSale[]
}>()

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/dashboard` },
  { title: 'Parco', href: `/${companySlug.value}/fuel/parco` },
])

const search = ref('')
const selectedSales = ref<Set<string>>(new Set())

const filteredPendingSales = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.pendingSales.filter(sale =>
    sale.customer_name.toLowerCase().includes(q) ||
    sale.invoice_number.toLowerCase().includes(q)
  )
})

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('en-PK', {
    style: 'currency',
    currency: 'PKR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-PK', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const selectedTotal = computed(() => {
  return Array.from(selectedSales.value).reduce((total, id) => {
    const sale = props.pendingSales.find(s => s.id === id)
    return total + (sale?.outstanding || 0)
  }, 0)
})

const settlementForm = useForm<{
  selected_sale_ids: string[]
  settlement_amount: number | null
  settlement_date: string
  reference: string
  notes: string
}>({
  selected_sale_ids: [],
  settlement_amount: null,
  settlement_date: new Date().toISOString().split('T')[0],
  reference: '',
  notes: '',
})

const showSettlementDialog = ref(false)

const openSettlementDialog = () => {
  if (selectedSales.value.size === 0) return

  settlementForm.selected_sale_ids = Array.from(selectedSales.value)
  settlementForm.settlement_amount = selectedTotal.value
  settlementForm.reference = `Parco Settlement ${new Date().toISOString().slice(0, 10)}`
  showSettlementDialog.value = true
}

const submitSettlement = () => {
  const slug = companySlug.value
  if (!slug) return

  settlementForm.post(`/${slug}/fuel/parco/settle`, {
    preserveScroll: true,
    onSuccess: () => {
      showSettlementDialog.value = false
      settlementForm.reset()
      selectedSales.value.clear()
    },
  })
}

const toggleSaleSelection = (saleId: string) => {
  if (selectedSales.value.has(saleId)) {
    selectedSales.value.delete(saleId)
  } else {
    selectedSales.value.add(saleId)
  }
}

const selectAllPending = () => {
  if (selectedSales.value.size === props.pendingSales.length) {
    selectedSales.value.clear()
  } else {
    selectedSales.value.clear()
    props.pendingSales.forEach(sale => selectedSales.value.add(sale.id))
  }
}

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'pending':
      return { class: 'bg-amber-100 text-amber-800', icon: Clock, label: 'Pending' }
    case 'settled':
      return { class: 'bg-emerald-100 text-emerald-800', icon: CheckCircle, label: 'Settled' }
    case 'overdue':
      return { class: 'bg-red-100 text-red-800', icon: AlertTriangle, label: 'Overdue' }
    default:
      return { class: 'bg-zinc-100 text-zinc-700', icon: Clock, label: status }
  }
}

const pendingColumns = [
  { key: 'select', label: '', sortable: false, width: '50px' },
  { key: 'customer', label: 'Customer' },
  { key: 'invoice', label: 'Invoice' },
  { key: 'date', label: 'Date' },
  { key: 'outstanding', label: 'Outstanding', align: 'right' as const },
  { key: 'status', label: 'Status' },
]

const todayColumns = [
  { key: 'customer', label: 'Customer' },
  { key: 'invoice', label: 'Invoice' },
  { key: 'date', label: 'Date' },
  { key: 'amount', label: 'Settled', align: 'right' as const },
]

const pendingTableData = computed(() => {
  return filteredPendingSales.value.map((sale) => ({
    id: sale.id,
    select: sale.id,
    customer: sale.customer_name,
    invoice: sale.invoice_number,
    date: formatDate(sale.invoice_date),
    outstanding: formatCurrency(sale.outstanding),
    status: sale.status,
    _raw: sale,
  }))
})

const todayTableData = computed(() => {
  return props.todaySettlements.map((sale) => ({
    id: sale.id,
    customer: sale.customer_name,
    invoice: sale.invoice_number,
    date: formatDate(sale.invoice_date),
    amount: formatCurrency(sale.settled_amount),
    _raw: sale,
  }))
})
</script>

<template>
  <Head title="Parco Card Settlements" />

  <PageShell
    title="Parco Card Settlements"
    description="Manage Parco card payments and settlements"
    :icon="CreditCard"
    :breadcrumbs="breadcrumbs"
  >
    <div class="grid gap-4 md:grid-cols-3">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-blue-500/10 via-indigo-500/5 to-purple-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Pending Settlements</CardDescription>
          <CardTitle class="text-2xl">{{ formatCurrency(props.summary.total_pending) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <AlertTriangle class="h-4 w-4 text-amber-600" />
            <span>{{ props.summary.count_pending }} transaction(s)</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Settled Today</CardDescription>
          <CardTitle class="text-2xl">{{ formatCurrency(props.summary.total_settled_today) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge class="bg-emerald-100 text-emerald-800 hover:bg-emerald-100">
            <CheckCircle class="mr-1 h-3 w-3" />
            Completed
          </Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Total Outstanding</CardDescription>
          <CardTitle class="text-2xl text-amber-600">{{ formatCurrency(props.summary.total_outstanding) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge variant="outline" class="border-amber-200 text-amber-700">
            <Clock class="mr-1 h-3 w-3" />
            Awaiting Settlement
          </Badge>
        </CardContent>
      </Card>
    </div>

    <!-- Pending Settlements -->
    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Pending Settlements</CardTitle>
            <CardDescription>Select transactions to settle with Parco</CardDescription>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative w-full sm:w-[200px]">
              <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-tertiary" />
              <Input v-model="search" placeholder="Search..." class="pl-9" />
            </div>
            <Button
              v-if="selectedSales.size > 0"
              class="bg-emerald-600 hover:bg-emerald-700"
              @click="openSettlementDialog"
            >
              <Banknote class="mr-2 h-4 w-4" />
              Settle Selected ({{ formatCurrency(selectedTotal) }})
            </Button>
          </div>
        </div>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable :data="pendingTableData" :columns="pendingColumns">
          <template #empty>
            <EmptyState
              title="No pending settlements"
              description="All Parco card transactions have been settled."
            />
          </template>

          <template #cell-select="{ row }">
            <input
              type="checkbox"
              :checked="selectedSales.has(row.select)"
              @change="toggleSaleSelection(row.select)"
              class="rounded border-border"
            />
          </template>

          <template #cell-status="{ row }">
            <Badge :class="getStatusBadge(row.status).class" class="hover:opacity-100">
              <component :is="getStatusBadge(row.status).icon" class="mr-1 h-3 w-3" />
              {{ getStatusBadge(row.status).label }}
            </Badge>
          </template>

          <template #header-select>
            <input
              type="checkbox"
              :checked="selectedSales.size === props.pendingSales.length && props.pendingSales.length > 0"
              :indeterminate="selectedSales.size > 0 && selectedSales.size < props.pendingSales.length"
              @change="selectAllPending"
              class="rounded border-border"
            />
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <!-- Today's Settlements -->
    <Card v-if="props.todaySettlements.length > 0" class="border-border/80">
      <CardHeader>
        <CardTitle class="text-base">Today's Settlements</CardTitle>
        <CardDescription>Parco card settlements processed today</CardDescription>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable :data="todayTableData" :columns="todayColumns" />
      </CardContent>
    </Card>

    <!-- Settlement Dialog -->
    <Dialog :open="showSettlementDialog" @update:open="(v) => showSettlementDialog = v">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <CreditCard class="h-5 w-5 text-blue-600" />
            Parco Settlement
          </DialogTitle>
          <DialogDescription>
            Settle {{ selectedSales.size }} transaction(s) totaling {{ formatCurrency(selectedTotal) }}
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submitSettlement">
          <div class="space-y-2">
            <Label>Settlement Amount *</Label>
            <Input
              v-model.number="settlementForm.settlement_amount"
              type="number"
              min="1"
              :max="selectedTotal"
              step="1"
              :class="{ 'border-destructive': settlementForm.errors.settlement_amount }"
            />
            <p v-if="settlementForm.errors.settlement_amount" class="text-sm text-destructive">
              {{ settlementForm.errors.settlement_amount[0] }}
            </p>
          </div>

          <div class="space-y-2">
            <Label>Settlement Date</Label>
            <Input v-model="settlementForm.settlement_date" type="date" />
          </div>

          <div class="space-y-2">
            <Label>Reference</Label>
            <Input
              v-model="settlementForm.reference"
              placeholder="Settlement reference number"
            />
          </div>

          <div class="space-y-2">
            <Label>Notes</Label>
            <Input
              v-model="settlementForm.notes"
              placeholder="Optional notes..."
            />
          </div>

          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" :disabled="settlementForm.processing" @click="showSettlementDialog = false">
              Cancel
            </Button>
            <Button type="submit" class="bg-blue-600 hover:bg-blue-700" :disabled="settlementForm.processing">
              <span
                v-if="settlementForm.processing"
                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
              />
              Process Settlement
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>