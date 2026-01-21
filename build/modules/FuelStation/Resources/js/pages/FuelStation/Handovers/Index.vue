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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { HandCoins, Plus, Eye, Check, Search, Clock, CheckCircle, Banknote } from 'lucide-vue-next'

interface Handover {
  id: string
  attendant_id: string
  attendant_name: string
  handover_date: string
  pump_id: string
  pump_name: string
  shift: 'day' | 'night'
  cash_amount: number
  easypaisa_amount: number
  jazzcash_amount: number
  bank_transfer_amount: number
  card_swipe_amount: number
  parco_card_amount: number
  total_amount: number
  status: 'pending' | 'received' | 'reconciled'
}

interface Pump {
  id: string
  name: string
}

interface Attendant {
  id: string
  name: string
}

interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  handovers: PaginatedResponse<Handover>
  pumps: Pump[]
  attendants: Attendant[]
  summary: {
    pending_count: number
    pending_amount: number
  }
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
  { title: 'Handovers', href: `/${companySlug.value}/fuel/handovers` },
])

const currencyCode = computed(() => ((page.props as any)?.auth?.currentCompany?.base_currency as string) || 'PKR')

const search = ref('')
const statusFilter = ref<'all' | 'pending' | 'received'>('pending')

const filteredHandovers = computed(() => {
  return (props.handovers.data || []).filter((h) => {
    if (statusFilter.value !== 'all' && h.status !== statusFilter.value) return false
    const q = search.value.trim().toLowerCase()
    if (!q) return true
    return (
      h.attendant_name?.toLowerCase().includes(q) ||
      h.pump_name?.toLowerCase().includes(q)
    )
  })
})

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('en-PK', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: currencyCode.value,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-PK', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const columns = [
  { key: 'date', label: 'Date' },
  { key: 'attendant', label: 'Attendant' },
  { key: 'pump', label: 'Pump' },
  { key: 'shift', label: 'Shift' },
  { key: 'total', label: 'Total', align: 'right' as const },
  { key: 'status', label: 'Status' },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return filteredHandovers.value.map((h) => ({
    id: h.id,
    date: formatDate(h.handover_date),
    attendant: h.attendant_name,
    pump: h.pump_name,
    shift: h.shift === 'day' ? 'Day' : 'Night',
    total: formatCurrency(h.total_amount),
    status: h.status,
    _actions: h.id,
    _raw: h,
  }))
})

// Create handover dialog
const dialogOpen = ref(false)
const form = useForm({
  attendant_id: '',
  pump_id: '',
  shift: 'day' as 'day' | 'night',
  handover_date: new Date().toISOString().slice(0, 16),
  cash_amount: 0,
  easypaisa_amount: 0,
  jazzcash_amount: 0,
  bank_transfer_amount: 0,
  card_swipe_amount: 0,
  parco_card_amount: 0,
})

const totalAmount = computed(() => {
  return (
    (form.cash_amount || 0) +
    (form.easypaisa_amount || 0) +
    (form.jazzcash_amount || 0) +
    (form.bank_transfer_amount || 0) +
    (form.card_swipe_amount || 0) +
    (form.parco_card_amount || 0)
  )
})

const openCreate = () => {
  form.reset()
  form.clearErrors()
  form.handover_date = new Date().toISOString().slice(0, 16)
  dialogOpen.value = true
}

const submit = () => {
  const slug = companySlug.value
  if (!slug) return

  form.post(`/${slug}/fuel/handovers`, {
    preserveScroll: true,
    onSuccess: () => {
      dialogOpen.value = false
      form.reset()
    },
  })
}

const receiveHandover = (id: string) => {
  const slug = companySlug.value
  if (!slug) return

  router.post(`/${slug}/fuel/handovers/${id}/receive`, {}, {
    preserveScroll: true,
  })
}

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'pending':
      return { class: 'bg-amber-100 text-amber-800', icon: Clock, label: 'Pending' }
    case 'received':
      return { class: 'bg-emerald-100 text-emerald-800', icon: CheckCircle, label: 'Received' }
    case 'reconciled':
      return { class: 'bg-sky-100 text-sky-800', icon: Check, label: 'Reconciled' }
    default:
      return { class: 'bg-zinc-100 text-zinc-700', icon: Clock, label: status }
  }
}
</script>

<template>
  <Head title="Attendant Handovers" />

  <PageShell
    title="Attendant Handovers"
    description="Track cash and payment collections from pump attendants."
    :icon="HandCoins"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="openCreate">
        <Plus class="mr-2 h-4 w-4" />
        Record Handover
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-2">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-amber-500/10 via-orange-500/5 to-red-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Pending Handovers</CardDescription>
          <CardTitle class="text-2xl">{{ props.summary.pending_count }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Clock class="h-4 w-4 text-amber-600" />
            <span>Awaiting receipt</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Pending Amount</CardDescription>
          <CardTitle class="text-2xl text-amber-600">{{ formatCurrency(props.summary.pending_amount) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Banknote class="h-4 w-4 text-amber-600" />
            <span>In attendant transit</span>
          </div>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Handover List</CardTitle>
            <CardDescription>Mark pending handovers as received when you collect the cash.</CardDescription>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative w-full sm:w-[200px]">
              <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-tertiary" />
              <Input v-model="search" placeholder="Search..." class="pl-9" />
            </div>
            <Select v-model="statusFilter">
              <SelectTrigger class="w-full sm:w-[140px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Status</SelectItem>
                <SelectItem value="pending">Pending</SelectItem>
                <SelectItem value="received">Received</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable :data="tableData" :columns="columns">
          <template #empty>
            <EmptyState
              title="No handovers"
              description="Record handovers when attendants submit their shift collections."
            >
              <template #actions>
                <Button @click="openCreate">
                  <Plus class="mr-2 h-4 w-4" />
                  Record Handover
                </Button>
              </template>
            </EmptyState>
          </template>

          <template #cell-shift="{ row }">
            <Badge variant="outline" :class="row._raw.shift === 'day' ? 'border-amber-200 text-amber-700' : 'border-indigo-200 text-indigo-700'">
              {{ row.shift }}
            </Badge>
          </template>

          <template #cell-status="{ row }">
            <Badge :class="getStatusBadge(row.status).class" class="hover:opacity-100">
              <component :is="getStatusBadge(row.status).icon" class="mr-1 h-3 w-3" />
              {{ getStatusBadge(row.status).label }}
            </Badge>
          </template>

          <template #cell-_actions="{ row }">
            <div class="flex items-center justify-end gap-2">
              <Button
                v-if="row.status === 'pending'"
                size="sm"
                class="bg-emerald-600 hover:bg-emerald-700"
                @click.stop="receiveHandover(row.id)"
              >
                <Check class="mr-1 h-4 w-4" />
                Receive
              </Button>
              <Button
                variant="outline"
                size="sm"
                @click.stop="router.get(`/${companySlug}/fuel/handovers/${row.id}`)"
              >
                <Eye class="h-4 w-4" />
              </Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <!-- Create Handover Dialog -->
    <Dialog :open="dialogOpen" @update:open="(v) => (dialogOpen = v)">
      <DialogContent class="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <HandCoins class="h-5 w-5 text-amber-600" />
            Record Handover
          </DialogTitle>
          <DialogDescription>
            Record collections from an attendant for a shift.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submit">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label>Attendant *</Label>
              <Select v-model="form.attendant_id">
                <SelectTrigger :class="{ 'border-destructive': form.errors.attendant_id }">
                  <SelectValue placeholder="Select attendant..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="att in attendants" :key="att.id" :value="att.id">
                    {{ att.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.attendant_id" class="text-sm text-destructive">{{ form.errors.attendant_id }}</p>
            </div>

            <div class="space-y-2">
              <Label>Pump *</Label>
              <Select v-model="form.pump_id">
                <SelectTrigger :class="{ 'border-destructive': form.errors.pump_id }">
                  <SelectValue placeholder="Select pump..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="pump in pumps" :key="pump.id" :value="pump.id">
                    {{ pump.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.pump_id" class="text-sm text-destructive">{{ form.errors.pump_id }}</p>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label>Shift *</Label>
              <Select v-model="form.shift">
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="day">Day</SelectItem>
                  <SelectItem value="night">Night</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label>Date/Time</Label>
              <Input v-model="form.handover_date" type="datetime-local" />
            </div>
          </div>

          <div class="rounded-lg border border-border/70 bg-muted/30 p-4 space-y-3">
            <h4 class="font-medium text-sm">Payment Breakdown</h4>
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="space-y-1">
                <Label class="text-xs">Cash</Label>
                <Input v-model.number="form.cash_amount" type="number" min="0" step="1" placeholder="0" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs">EasyPaisa</Label>
                <Input v-model.number="form.easypaisa_amount" type="number" min="0" step="1" placeholder="0" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs">JazzCash</Label>
                <Input v-model.number="form.jazzcash_amount" type="number" min="0" step="1" placeholder="0" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs">Bank Transfer</Label>
                <Input v-model.number="form.bank_transfer_amount" type="number" min="0" step="1" placeholder="0" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs">Card Swipe</Label>
                <Input v-model.number="form.card_swipe_amount" type="number" min="0" step="1" placeholder="0" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs">Vendor Card</Label>
                <Input v-model.number="form.parco_card_amount" type="number" min="0" step="1" placeholder="0" />
              </div>
            </div>
            <div class="pt-2 border-t border-border/50 flex justify-between items-center">
              <span class="text-sm text-text-secondary">Total</span>
              <span class="text-lg font-semibold">{{ formatCurrency(totalAmount) }}</span>
            </div>
          </div>

          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" :disabled="form.processing" @click="dialogOpen = false">
              Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
              <span
                v-if="form.processing"
                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
              />
              Record Handover
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
