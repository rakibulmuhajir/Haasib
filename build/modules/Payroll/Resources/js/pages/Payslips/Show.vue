<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { BreadcrumbItem } from '@/types'
import { ArrowLeft, CheckCircle, DollarSign, Printer } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface Employee {
  id: string
  first_name: string
  last_name: string
  employee_number: string
  department: string | null
  position: string | null
}

interface Period {
  id: string
  period_start: string
  period_end: string
  payment_date: string
}

interface PayslipLine {
  id: string
  line_type: 'earning' | 'deduction'
  description: string
  amount: number
  quantity: number | null
  rate: number | null
}

interface Payslip {
  id: string
  payslip_number: string
  employee: Employee
  payroll_period: Period
  currency: string
  gross_pay: number
  total_deductions: number
  net_pay: number
  status: string
  notes: string | null
  lines: PayslipLine[]
  created_at: string
}

const props = defineProps<{
  company: CompanyRef
  payslip: Payslip
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Payslips', href: `/${props.company.slug}/payslips` },
  { title: props.payslip.payslip_number, href: `/${props.company.slug}/payslips/${props.payslip.id}` },
]

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

const getStatusVariant = (status: string) => {
  const variants: Record<string, 'success' | 'secondary' | 'destructive' | 'outline'> = {
    draft: 'outline',
    approved: 'secondary',
    paid: 'success',
    cancelled: 'destructive',
  }
  return variants[status] || 'secondary'
}

const formatStatus = (status: string) => {
  return status.charAt(0).toUpperCase() + status.slice(1)
}

const earnings = props.payslip.lines.filter((line) => line.line_type === 'earning')
const deductions = props.payslip.lines.filter((line) => line.line_type === 'deduction')

const handleApprove = () => {
  router.post(`/${props.company.slug}/payslips/${props.payslip.id}/approve`)
}

const handleMarkPaid = () => {
  router.post(`/${props.company.slug}/payslips/${props.payslip.id}/mark-paid`)
}
</script>

<template>
  <Head :title="`Payslip ${payslip.payslip_number}`" />

  <PageShell
    :title="`Payslip ${payslip.payslip_number}`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/payslips`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button v-if="payslip.status === 'draft'" @click="handleApprove">
        <CheckCircle class="mr-2 h-4 w-4" />
        Approve
      </Button>
      <Button v-if="payslip.status === 'approved'" @click="handleMarkPaid">
        <DollarSign class="mr-2 h-4 w-4" />
        Mark Paid
      </Button>
      <Button variant="outline">
        <Printer class="mr-2 h-4 w-4" />
        Print
      </Button>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Employee & Period Info -->
        <Card>
          <CardHeader>
            <div class="flex items-center justify-between">
              <CardTitle>Payslip Details</CardTitle>
              <Badge :variant="getStatusVariant(payslip.status)">
                {{ formatStatus(payslip.status) }}
              </Badge>
            </div>
          </CardHeader>
          <CardContent>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p class="text-muted-foreground">Employee</p>
                <p class="font-medium">{{ payslip.employee.first_name }} {{ payslip.employee.last_name }}</p>
                <p class="text-muted-foreground">{{ payslip.employee.employee_number }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Department</p>
                <p class="font-medium">{{ payslip.employee.department ?? '-' }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Pay Period</p>
                <p class="font-medium">
                  {{ formatDate(payslip.payroll_period.period_start) }} -
                  {{ formatDate(payslip.payroll_period.period_end) }}
                </p>
              </div>
              <div>
                <p class="text-muted-foreground">Payment Date</p>
                <p class="font-medium">{{ formatDate(payslip.payroll_period.payment_date) }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Earnings -->
        <Card>
          <CardHeader>
            <CardTitle>Earnings</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Description</TableHead>
                  <TableHead class="text-right">Quantity</TableHead>
                  <TableHead class="text-right">Rate</TableHead>
                  <TableHead class="text-right">Amount</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="line in earnings" :key="line.id">
                  <TableCell>{{ line.description }}</TableCell>
                  <TableCell class="text-right">{{ line.quantity ?? '-' }}</TableCell>
                  <TableCell class="text-right">
                    {{ line.rate ? formatCurrency(line.rate, payslip.currency) : '-' }}
                  </TableCell>
                  <TableCell class="text-right font-medium">
                    {{ formatCurrency(line.amount, payslip.currency) }}
                  </TableCell>
                </TableRow>
                <TableRow v-if="earnings.length === 0">
                  <TableCell colspan="4" class="text-center text-muted-foreground">
                    No earnings recorded
                  </TableCell>
                </TableRow>
                <TableRow class="font-bold bg-muted/50">
                  <TableCell colspan="3">Gross Pay</TableCell>
                  <TableCell class="text-right">
                    {{ formatCurrency(payslip.gross_pay, payslip.currency) }}
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <!-- Deductions -->
        <Card>
          <CardHeader>
            <CardTitle>Deductions</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Description</TableHead>
                  <TableHead class="text-right">Amount</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="line in deductions" :key="line.id">
                  <TableCell>{{ line.description }}</TableCell>
                  <TableCell class="text-right font-medium text-destructive">
                    -{{ formatCurrency(line.amount, payslip.currency) }}
                  </TableCell>
                </TableRow>
                <TableRow v-if="deductions.length === 0">
                  <TableCell colspan="2" class="text-center text-muted-foreground">
                    No deductions recorded
                  </TableCell>
                </TableRow>
                <TableRow class="font-bold bg-muted/50">
                  <TableCell>Total Deductions</TableCell>
                  <TableCell class="text-right text-destructive">
                    -{{ formatCurrency(payslip.total_deductions, payslip.currency) }}
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <!-- Notes -->
        <Card v-if="payslip.notes">
          <CardHeader>
            <CardTitle>Notes</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm whitespace-pre-wrap">{{ payslip.notes }}</p>
          </CardContent>
        </Card>
      </div>

      <!-- Summary Sidebar -->
      <div class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Pay Summary</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Gross Pay</span>
              <span class="font-medium">{{ formatCurrency(payslip.gross_pay, payslip.currency) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Deductions</span>
              <span class="font-medium text-destructive">
                -{{ formatCurrency(payslip.total_deductions, payslip.currency) }}
              </span>
            </div>
            <hr />
            <div class="flex justify-between items-center">
              <span class="font-semibold">Net Pay</span>
              <span class="font-bold text-xl text-primary">
                {{ formatCurrency(payslip.net_pay, payslip.currency) }}
              </span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Details</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3 text-sm">
            <div class="flex justify-between">
              <span class="text-muted-foreground">Payslip #</span>
              <span class="font-medium">{{ payslip.payslip_number }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">Currency</span>
              <span class="font-medium">{{ payslip.currency }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">Created</span>
              <span class="font-medium">{{ formatDate(payslip.created_at) }}</span>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>
