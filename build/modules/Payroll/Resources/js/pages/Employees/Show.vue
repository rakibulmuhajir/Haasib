<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Pencil, ArrowLeft, User, Briefcase, DollarSign, Users } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface Manager {
  id: string
  first_name: string
  last_name: string
}

interface DirectReport {
  id: string
  first_name: string
  last_name: string
  employee_number: string
  position: string | null
}

interface Employee {
  id: string
  employee_number: string
  first_name: string
  last_name: string
  email: string | null
  phone: string | null
  date_of_birth: string | null
  gender: string | null
  hire_date: string
  termination_date: string | null
  employment_type: string
  employment_status: string
  department: string | null
  position: string | null
  manager: Manager | null
  direct_reports: DirectReport[]
  pay_frequency: string
  base_salary: number
  currency: string
  is_active: boolean
  notes: string | null
}

const props = defineProps<{
  company: CompanyRef
  employee: Employee
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Employees', href: `/${props.company.slug}/employees` },
  { title: `${props.employee.first_name} ${props.employee.last_name}`, href: `/${props.company.slug}/employees/${props.employee.id}` },
]

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: currency || 'USD',
  }).format(amount)
}

const formatDate = (date: string | null) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

const getStatusVariant = (status: string) => {
  const variants: Record<string, 'success' | 'secondary' | 'destructive' | 'outline'> = {
    active: 'success',
    on_leave: 'outline',
    suspended: 'destructive',
    terminated: 'secondary',
  }
  return variants[status] || 'secondary'
}

const formatStatus = (status: string) => {
  return status.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase())
}

const formatEmploymentType = (type: string) => {
  return type.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase())
}

const formatPayFrequency = (freq: string) => {
  const labels: Record<string, string> = {
    weekly: 'Weekly',
    biweekly: 'Bi-weekly',
    semimonthly: 'Semi-monthly',
    monthly: 'Monthly',
  }
  return labels[freq] || freq
}
</script>

<template>
  <Head :title="`${employee.first_name} ${employee.last_name}`" />

  <PageShell
    :title="`${employee.first_name} ${employee.last_name}`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/employees`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button @click="router.get(`/${company.slug}/employees/${employee.id}/edit`)">
        <Pencil class="mr-2 h-4 w-4" />
        Edit
      </Button>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Personal Information -->
        <Card>
          <CardHeader>
            <div class="flex items-center justify-between">
              <CardTitle class="flex items-center gap-2">
                <User class="h-5 w-5" />
                Personal Information
              </CardTitle>
              <Badge :variant="getStatusVariant(employee.employment_status)">
                {{ formatStatus(employee.employment_status) }}
              </Badge>
            </div>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p class="text-muted-foreground">Employee ID</p>
                <p class="font-medium">{{ employee.employee_number }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Full Name</p>
                <p class="font-medium">{{ employee.first_name }} {{ employee.last_name }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Email</p>
                <p class="font-medium">{{ employee.email ?? '-' }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Phone</p>
                <p class="font-medium">{{ employee.phone ?? '-' }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Employment Details -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Briefcase class="h-5 w-5" />
              Employment Details
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p class="text-muted-foreground">Department</p>
                <p class="font-medium">{{ employee.department ?? '-' }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Position</p>
                <p class="font-medium">{{ employee.position ?? '-' }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Employment Type</p>
                <p class="font-medium">{{ formatEmploymentType(employee.employment_type) }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Hire Date</p>
                <p class="font-medium">{{ formatDate(employee.hire_date) }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Manager</p>
                <p class="font-medium">
                  {{ employee.manager ? `${employee.manager.first_name} ${employee.manager.last_name}` : '-' }}
                </p>
              </div>
              <div v-if="employee.termination_date">
                <p class="text-muted-foreground">Termination Date</p>
                <p class="font-medium">{{ formatDate(employee.termination_date) }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Direct Reports -->
        <Card v-if="employee.direct_reports && employee.direct_reports.length > 0">
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Users class="h-5 w-5" />
              Direct Reports
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div class="space-y-2">
              <div
                v-for="report in employee.direct_reports"
                :key="report.id"
                class="flex items-center justify-between py-2 px-3 border rounded-lg cursor-pointer hover:bg-muted/50"
                @click="router.get(`/${company.slug}/employees/${report.id}`)"
              >
                <div>
                  <p class="font-medium">{{ report.first_name }} {{ report.last_name }}</p>
                  <p class="text-sm text-muted-foreground">{{ report.employee_number }}</p>
                </div>
                <Badge variant="secondary">{{ report.position ?? 'No position' }}</Badge>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Notes -->
        <Card v-if="employee.notes">
          <CardHeader>
            <CardTitle>Notes</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm whitespace-pre-wrap">{{ employee.notes }}</p>
          </CardContent>
        </Card>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <DollarSign class="h-5 w-5" />
              Compensation
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Base Salary</span>
              <span class="font-medium text-lg">{{ formatCurrency(employee.base_salary, employee.currency) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Pay Frequency</span>
              <span class="font-medium">{{ formatPayFrequency(employee.pay_frequency) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Currency</span>
              <span class="font-medium">{{ employee.currency }}</span>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>
