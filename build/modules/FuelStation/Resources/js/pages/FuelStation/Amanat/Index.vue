<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import type { BreadcrumbItem } from '@/types'
import { Wallet, Eye, Search, Users, Banknote, Plus, UserCog } from 'lucide-vue-next'

interface AmanatCustomer {
  id: string
  customer_id: string
  customer_name: string
  customer_phone?: string | null
  cnic?: string | null
  amanat_balance: number
  is_credit_customer: boolean
  relationship?: string | null
}

const props = withDefaults(defineProps<{
  customers: AmanatCustomer[]
  summary: {
    total_holders: number
    total_balance: number
  }
}>(), {
  customers: () => [],
  summary: () => ({
    total_holders: 0,
    total_balance: 0,
  }),
})

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
  { title: 'Amanat', href: `/${companySlug.value}/fuel/amanat` },
])

const currencyCode = computed(() => ((page.props as any)?.auth?.currentCompany?.base_currency as string) || 'PKR')

const search = ref('')
const addDialogOpen = ref(false)

const holderForm = useForm({
  name: '',
  phone: '',
  cnic: '',
  relationship: 'external',
  opening_deposit: '',
  reference: '',
  notes: '',
})

const openAddHolder = () => {
  holderForm.reset()
  holderForm.clearErrors()
  addDialogOpen.value = true
}

const submitHolder = () => {
  const slug = companySlug.value
  if (!slug) return

  holderForm.post(`/${slug}/fuel/amanat`, {
    preserveScroll: true,
    onSuccess: () => {
      addDialogOpen.value = false
      holderForm.reset()
    },
  })
}

const filteredCustomers = computed(() => {
  const q = search.value.trim().toLowerCase()
  const customers = props.customers ?? []
  if (!q) return customers
  return customers.filter((c) =>
    c.customer_name.toLowerCase().includes(q) ||
    (c.customer_phone ?? '').toLowerCase().includes(q) ||
    (c.cnic ?? '').toLowerCase().includes(q)
  )
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
  { key: 'name', label: 'Customer' },
  { key: 'phone', label: 'Phone' },
  { key: 'relationship', label: 'Type' },
  { key: 'balance', label: 'Balance', align: 'right' as const },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return filteredCustomers.value.map((c) => ({
    id: c.customer_id,
    name: c.customer_name,
    phone: c.customer_phone ?? '-',
    relationship: c.relationship ?? 'External',
    balance: formatCurrency(c.amanat_balance),
    _actions: c.customer_id,
    _raw: c,
  }))
})

const goToShow = (row: any) => {
  const slug = companySlug.value
  if (!slug) return
  router.get(`/${slug}/fuel/amanat/${row.id}`)
}

const getRelationshipBadge = (relationship: string | null | undefined) => {
  switch (relationship) {
    case 'owner':
      return { class: 'bg-purple-100 text-purple-800', label: 'Owner' }
    case 'employee':
      return { class: 'bg-sky-100 text-sky-800', label: 'Employee' }
    default:
      return { class: 'bg-zinc-100 text-zinc-700', label: 'External' }
  }
}
</script>

<template>
  <Head title="Amanat Deposits" />

  <PageShell
    title="Amanat Deposits"
    description="Manage trust deposits (amanat) for customers who prepay for fuel."
    :icon="Wallet"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${companySlug}/customers`)">
        <UserCog class="mr-2 h-4 w-4" />
        Manage Customers
      </Button>
      <Button @click="openAddHolder">
        <Plus class="mr-2 h-4 w-4" />
        Add Holder
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-2">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-purple-500/10 via-indigo-500/5 to-sky-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Total Balance</CardDescription>
          <CardTitle class="text-2xl">{{ formatCurrency(props.summary.total_balance) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Banknote class="h-4 w-4 text-purple-600" />
            <span>Liability to customers</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Active Holders</CardDescription>
          <CardTitle class="text-2xl">{{ props.summary.total_holders }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Users class="h-4 w-4 text-sky-600" />
            <span>Customers with balance</span>
          </div>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Amanat Holders</CardTitle>
            <CardDescription>Click on a customer to view transactions and manage deposits.</CardDescription>
          </div>

          <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <div class="relative w-full sm:w-[280px]">
              <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-tertiary" />
              <Input v-model="search" placeholder="Search customers..." class="pl-9" />
            </div>
            <Button variant="outline" @click="openAddHolder">
              <Plus class="mr-2 h-4 w-4" />
              Add
            </Button>
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
              title="No amanat holders"
              description="Add a depositor here, then record deposits or withdrawals from their account."
              :actions="[{ label: 'Add Holder', icon: Plus, onClick: openAddHolder }]"
            />
          </template>

          <template #cell-relationship="{ row }">
            <Badge :class="getRelationshipBadge(row._raw.relationship).class" class="hover:opacity-100">
              {{ getRelationshipBadge(row._raw.relationship).label }}
            </Badge>
          </template>

          <template #cell-balance="{ row }">
            <span class="font-medium" :class="row._raw.amanat_balance > 0 ? 'text-emerald-600' : ''">
              {{ row.balance }}
            </span>
          </template>

          <template #cell-_actions="{ row }">
            <Button
              variant="outline"
              size="sm"
              @click.stop="router.get(`/${companySlug}/fuel/amanat/${row.id}`)"
            >
              <Eye class="h-4 w-4" />
            </Button>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Dialog v-model:open="addDialogOpen">
      <DialogContent class="max-w-xl">
        <DialogHeader>
          <DialogTitle>Add Amanat Holder</DialogTitle>
          <DialogDescription>
            Create the depositor account. Opening deposit is optional.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4 py-2">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2 sm:col-span-2">
              <Label>Name</Label>
              <Input v-model="holderForm.name" placeholder="Customer name" />
              <p v-if="holderForm.errors.name" class="text-xs text-red-600">{{ holderForm.errors.name }}</p>
            </div>

            <div class="space-y-2">
              <Label>Phone</Label>
              <Input v-model="holderForm.phone" placeholder="Optional" />
              <p v-if="holderForm.errors.phone" class="text-xs text-red-600">{{ holderForm.errors.phone }}</p>
            </div>

            <div class="space-y-2">
              <Label>CNIC</Label>
              <Input v-model="holderForm.cnic" placeholder="Optional" />
              <p v-if="holderForm.errors.cnic" class="text-xs text-red-600">{{ holderForm.errors.cnic }}</p>
            </div>

            <div class="space-y-2">
              <Label>Type</Label>
              <Select v-model="holderForm.relationship">
                <SelectTrigger>
                  <SelectValue placeholder="Select type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="external">External</SelectItem>
                  <SelectItem value="employee">Employee</SelectItem>
                  <SelectItem value="owner">Owner</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="holderForm.errors.relationship" class="text-xs text-red-600">{{ holderForm.errors.relationship }}</p>
            </div>

            <div class="space-y-2">
              <Label>Opening Deposit</Label>
              <Input v-model="holderForm.opening_deposit" type="number" min="0" step="0.01" placeholder="Optional" />
              <p v-if="holderForm.errors.opening_deposit" class="text-xs text-red-600">{{ holderForm.errors.opening_deposit }}</p>
            </div>

            <div class="space-y-2">
              <Label>Reference</Label>
              <Input v-model="holderForm.reference" placeholder="Optional" />
              <p v-if="holderForm.errors.reference" class="text-xs text-red-600">{{ holderForm.errors.reference }}</p>
            </div>

            <div class="space-y-2">
              <Label>Notes</Label>
              <Input v-model="holderForm.notes" placeholder="Optional" />
              <p v-if="holderForm.errors.notes" class="text-xs text-red-600">{{ holderForm.errors.notes }}</p>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" @click="addDialogOpen = false">
            Cancel
          </Button>
          <Button type="button" :disabled="holderForm.processing" @click="submitHolder">
            Add Holder
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
