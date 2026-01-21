<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Progress } from '@/components/ui/progress'
import type { BreadcrumbItem } from '@/types'
import { Wallet, Search, TrendingUp, Clock, CheckCircle, AlertCircle } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface Advance {
  id: string
  employee_id: string
  employee_name: string
  employee_position: string | null
  advance_date: string
  amount: number
  amount_recovered: number
  amount_outstanding: number
  status: string
  reason: string | null
  payment_method: string
}

interface Employee {
  id: string
  name: string
  position: string | null
}

interface Stats {
  total_advances: number
  total_amount: number
  total_outstanding: number
  total_recovered: number
  pending_count: number
  partially_recovered_count: number
}

const props = defineProps<{
  advances: Advance[]
  employees: Employee[]
  stats: Stats
  currency: string
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
  { title: 'Salary Advances', href: `/${companySlug.value}/salary-advances` },
])

const currency = computed(() => currencySymbol(props.currency))

const search = ref('')
const statusFilter = ref('all')
const employeeFilter = ref('all')

const filteredAdvances = computed(() => {
  return props.advances.filter((adv) => {
    // Status filter
    if (statusFilter.value !== 'all' && adv.status !== statusFilter.value) return false

    // Employee filter
    if (employeeFilter.value !== 'all' && adv.employee_id !== employeeFilter.value) return false

    // Search filter
    const q = search.value.trim().toLowerCase()
    if (!q) return true
    return (
      adv.employee_name.toLowerCase().includes(q) ||
      adv.reason?.toLowerCase().includes(q)
    )
  })
})

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'pending':
      return { class: 'bg-amber-100 text-amber-800', label: 'Pending' }
    case 'partially_recovered':
      return { class: 'bg-sky-100 text-sky-800', label: 'Partial' }
    case 'fully_recovered':
      return { class: 'bg-emerald-100 text-emerald-800', label: 'Recovered' }
    case 'cancelled':
      return { class: 'bg-zinc-100 text-zinc-800', label: 'Cancelled' }
    default:
      return { class: 'bg-zinc-100 text-zinc-800', label: status }
  }
}

const columns = [
  { key: 'date', label: 'Date' },
  { key: 'employee', label: 'Employee' },
  { key: 'amount', label: 'Amount' },
  { key: 'recovered', label: 'Recovered' },
  { key: 'outstanding', label: 'Outstanding' },
  { key: 'status', label: 'Status' },
]

const tableData = computed(() => {
  return filteredAdvances.value.map((adv) => ({
    id: adv.id,
    date: formatDate(adv.advance_date),
    employee: adv.employee_name,
    amount: `${currency.value} ${formatCurrency(adv.amount)}`,
    recovered: `${currency.value} ${formatCurrency(adv.amount_recovered)}`,
    outstanding: `${currency.value} ${formatCurrency(adv.amount_outstanding)}`,
    status: adv.status,
    _raw: adv,
  }))
})

const recoveryPercentage = computed(() => {
  if (props.stats.total_amount === 0) return 0
  return Math.round((props.stats.total_recovered / props.stats.total_amount) * 100)
})
</script>

<template>
  <Head title="Salary Advances" />

  <PageShell
    title="Salary Advances"
    description="View salary advances given to employees. Recovery happens via payroll deductions."
    :icon="Wallet"
    :breadcrumbs="breadcrumbs"
  >
    <!-- Stats -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-sky-500/10 via-indigo-500/5 to-emerald-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Total Given</CardDescription>
          <CardTitle class="text-2xl">{{ currency }} {{ formatCurrency(stats.total_amount) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <TrendingUp class="h-4 w-4 text-sky-600" />
            <span>{{ stats.total_advances }} advances</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Outstanding</CardDescription>
          <CardTitle class="text-2xl text-amber-600">{{ currency }} {{ formatCurrency(stats.total_outstanding) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Clock class="h-4 w-4 text-amber-600" />
            <span>{{ stats.pending_count + stats.partially_recovered_count }} pending</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Recovered</CardDescription>
          <CardTitle class="text-2xl text-emerald-600">{{ currency }} {{ formatCurrency(stats.total_recovered) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <CheckCircle class="h-4 w-4 text-emerald-600" />
            <span>Via payroll</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Recovery Rate</CardDescription>
          <CardTitle class="text-2xl">{{ recoveryPercentage }}%</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <Progress :model-value="recoveryPercentage" class="h-2" />
        </CardContent>
      </Card>
    </div>

    <!-- Info Banner -->
    <div class="rounded-lg border border-sky-200 bg-sky-50 p-4">
      <div class="flex items-start gap-3">
        <AlertCircle class="h-5 w-5 text-sky-600 mt-0.5" />
        <div>
          <h4 class="font-medium text-sky-900">About Salary Advances</h4>
          <p class="text-sm text-sky-700 mt-1">
            Salary advances are recorded through the Daily Close when cash is given to employees.
            Recovery happens automatically through payroll deductions when processing payslips.
          </p>
        </div>
      </div>
    </div>

    <!-- List -->
    <Card class="border-border/80">
      <CardHeader class="pb-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle class="text-base">Advance History</CardTitle>
            <CardDescription>All salary advances given to employees.</CardDescription>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative w-full sm:w-[200px]">
              <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-tertiary" />
              <Input v-model="search" placeholder="Search..." class="pl-9" />
            </div>

            <Select v-model="employeeFilter">
              <SelectTrigger class="w-full sm:w-[180px]">
                <SelectValue placeholder="All Employees" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Employees</SelectItem>
                <SelectItem v-for="emp in employees" :key="emp.id" :value="emp.id">
                  {{ emp.name }}
                </SelectItem>
              </SelectContent>
            </Select>

            <Select v-model="statusFilter">
              <SelectTrigger class="w-full sm:w-[150px]">
                <SelectValue placeholder="All Status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Status</SelectItem>
                <SelectItem value="pending">Pending</SelectItem>
                <SelectItem value="partially_recovered">Partial</SelectItem>
                <SelectItem value="fully_recovered">Recovered</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </CardHeader>

      <CardContent class="p-0">
        <DataTable :data="tableData" :columns="columns">
          <template #empty>
            <EmptyState
              title="No salary advances yet"
              description="Salary advances are recorded through the Daily Close when giving cash advances to employees."
            />
          </template>

          <template #cell-employee="{ row }">
            <div>
              <div class="font-medium">{{ row._raw.employee_name }}</div>
              <div v-if="row._raw.employee_position" class="text-sm text-muted-foreground">
                {{ row._raw.employee_position }}
              </div>
            </div>
          </template>

          <template #cell-amount="{ row }">
            <span class="font-medium">{{ currency }} {{ formatCurrency(row._raw.amount) }}</span>
          </template>

          <template #cell-recovered="{ row }">
            <span class="text-emerald-600">{{ currency }} {{ formatCurrency(row._raw.amount_recovered) }}</span>
          </template>

          <template #cell-outstanding="{ row }">
            <span :class="row._raw.amount_outstanding > 0 ? 'text-amber-600 font-medium' : 'text-muted-foreground'">
              {{ currency }} {{ formatCurrency(row._raw.amount_outstanding) }}
            </span>
          </template>

          <template #cell-status="{ row }">
            <Badge :class="getStatusBadge(row._raw.status).class">
              {{ getStatusBadge(row._raw.status).label }}
            </Badge>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </PageShell>
</template>
