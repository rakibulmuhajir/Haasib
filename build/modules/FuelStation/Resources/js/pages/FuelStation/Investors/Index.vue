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
import { Users, Plus, Eye, Pencil, Search, Wallet, TrendingUp, Banknote } from 'lucide-vue-next'

interface InvestorRow {
  id: string
  name: string
  phone?: string | null
  cnic?: string | null
  total_invested: number
  total_commission_earned: number
  total_commission_paid: number
  outstanding_commission: number
  is_active: boolean
  lots_count?: number
}

const props = defineProps<{
  investors: InvestorRow[]
  summary: {
    total_investors: number
    total_invested: number
    total_commission_earned: number
    total_commission_paid: number
    total_outstanding: number
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
  { title: 'Investors', href: `/${companySlug.value}/fuel/investors` },
])

const currencyCode = computed(() => ((page.props as any)?.auth?.currentCompany?.base_currency as string) || 'PKR')

const search = ref('')
const activeOnly = ref(true)

const filteredInvestors = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.investors.filter((investor) => {
    if (activeOnly.value && !investor.is_active) return false
    if (!q) return true
    return (
      investor.name.toLowerCase().includes(q) ||
      (investor.phone ?? '').toLowerCase().includes(q) ||
      (investor.cnic ?? '').toLowerCase().includes(q)
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

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'phone', label: 'Phone' },
  { key: 'invested', label: 'Invested', align: 'right' as const },
  { key: 'earned', label: 'Earned', align: 'right' as const },
  { key: 'outstanding', label: 'Outstanding', align: 'right' as const },
  { key: 'status', label: 'Status' },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return filteredInvestors.value.map((investor) => ({
    id: investor.id,
    name: investor.name,
    phone: investor.phone ?? '-',
    invested: formatCurrency(investor.total_invested),
    earned: formatCurrency(investor.total_commission_earned),
    outstanding: formatCurrency(investor.outstanding_commission),
    status: investor.is_active ? 'Active' : 'Inactive',
    _actions: investor.id,
    _raw: investor,
  }))
})

const dialogOpen = ref(false)
const selectedInvestor = ref<InvestorRow | null>(null)

const form = useForm<{
  name: string
  phone: string
  cnic: string
}>({
  name: '',
  phone: '',
  cnic: '',
})

const openCreate = () => {
  selectedInvestor.value = null
  form.reset()
  form.clearErrors()
  dialogOpen.value = true
}

const openEdit = (investor: InvestorRow) => {
  selectedInvestor.value = investor
  form.clearErrors()
  form.name = investor.name
  form.phone = investor.phone ?? ''
  form.cnic = investor.cnic ?? ''
  dialogOpen.value = true
}

const closeDialog = () => {
  dialogOpen.value = false
  form.reset()
  form.clearErrors()
}

const submit = () => {
  const slug = companySlug.value
  if (!slug) return

  if (selectedInvestor.value) {
    form.put(`/${slug}/fuel/investors/${selectedInvestor.value.id}`, {
      preserveScroll: true,
      onSuccess: () => closeDialog(),
    })
    return
  }

  form.post(`/${slug}/fuel/investors`, {
    preserveScroll: true,
    onSuccess: () => closeDialog(),
  })
}

const goToShow = (row: any) => {
  const slug = companySlug.value
  if (!slug) return
  router.get(`/${slug}/fuel/investors/${row.id}`)
}
</script>

<template>
  <Head title="Investors" />

  <PageShell
    title="Investors"
    description="Manage fuel investors, track deposits, and pay commissions."
    :icon="Users"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="openCreate">
        <Plus class="mr-2 h-4 w-4" />
        Add Investor
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-emerald-500/10 via-teal-500/5 to-cyan-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Total Invested</CardDescription>
          <CardTitle class="text-2xl">{{ formatCurrency(props.summary.total_invested) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4 text-emerald-600" />
            <span>{{ props.summary.total_investors }} investor(s)</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Commission Earned</CardDescription>
          <CardTitle class="text-2xl">{{ formatCurrency(props.summary.total_commission_earned) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge class="bg-sky-100 text-sky-800 hover:bg-sky-100">
            <TrendingUp class="mr-1 h-3 w-3" />
            Lifetime
          </Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Commission Paid</CardDescription>
          <CardTitle class="text-2xl">{{ formatCurrency(props.summary.total_commission_paid) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge variant="secondary" class="bg-zinc-100 text-zinc-700 hover:bg-zinc-100">
            <Banknote class="mr-1 h-3 w-3" />
            Disbursed
          </Badge>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Outstanding</CardDescription>
          <CardTitle class="text-2xl text-amber-600">{{ formatCurrency(props.summary.total_outstanding) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Badge variant="outline" class="border-amber-200 text-amber-700">Pending Payment</Badge>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Investor List</CardTitle>
            <CardDescription>Click on an investor to view lots and pay commission.</CardDescription>
          </div>

          <div class="relative w-full sm:w-[280px]">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-tertiary" />
            <Input v-model="search" placeholder="Search investors..." class="pl-9" />
          </div>
        </div>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable
          :data="tableData"
          :columns="columns"
          clickable
          @row-click="goToShow"
        >
          <template #empty>
            <EmptyState
              title="No investors yet"
              description="Add your first investor to start tracking deposits and commissions."
            >
              <template #actions>
                <Button @click="openCreate">
                  <Plus class="mr-2 h-4 w-4" />
                  Add Investor
                </Button>
              </template>
            </EmptyState>
          </template>

          <template #cell-outstanding="{ row }">
            <span :class="row._raw.outstanding_commission > 0 ? 'font-medium text-amber-600' : ''">
              {{ row.outstanding }}
            </span>
          </template>

          <template #cell-status="{ row }">
            <Badge
              :class="row._raw.is_active ? 'bg-emerald-600 text-white hover:bg-emerald-600' : 'bg-zinc-200 text-zinc-800 hover:bg-zinc-200'"
            >
              {{ row._raw.is_active ? 'Active' : 'Inactive' }}
            </Badge>
          </template>

          <template #cell-_actions="{ row }">
            <div class="flex items-center justify-end gap-2">
              <Button
                variant="outline"
                size="sm"
                @click.stop="router.get(`/${companySlug}/fuel/investors/${row.id}`)"
              >
                <Eye class="h-4 w-4" />
              </Button>
              <Button variant="outline" size="sm" @click.stop="openEdit(row._raw)">
                <Pencil class="h-4 w-4" />
              </Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Dialog :open="dialogOpen" @update:open="(v) => (v ? (dialogOpen = true) : closeDialog())">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Users class="h-5 w-5 text-emerald-600" />
            {{ selectedInvestor ? 'Edit Investor' : 'Add Investor' }}
          </DialogTitle>
          <DialogDescription>
            Investors deposit money and receive fuel at purchase rate plus commission.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submit">
          <div class="space-y-2">
            <Label for="name">Name *</Label>
            <Input
              id="name"
              v-model="form.name"
              placeholder="Full name"
              :class="{ 'border-destructive': form.errors.name }"
            />
            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="phone">Phone</Label>
              <Input
                id="phone"
                v-model="form.phone"
                placeholder="0300-1234567"
                :class="{ 'border-destructive': form.errors.phone }"
              />
              <p v-if="form.errors.phone" class="text-sm text-destructive">{{ form.errors.phone }}</p>
            </div>

            <div class="space-y-2">
              <Label for="cnic">CNIC</Label>
              <Input
                id="cnic"
                v-model="form.cnic"
                placeholder="35201-1234567-1"
                :class="{ 'border-destructive': form.errors.cnic }"
              />
              <p v-if="form.errors.cnic" class="text-sm text-destructive">{{ form.errors.cnic }}</p>
            </div>
          </div>

          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" :disabled="form.processing" @click="closeDialog">
              Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
              <span
                v-if="form.processing"
                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
              />
              {{ selectedInvestor ? 'Save changes' : 'Create investor' }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
