<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { BreadcrumbItem } from '@/types'
import { Save, ArrowLeft, Plus, Trash2 } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface Employee {
  id: string
  first_name: string
  last_name: string
  employee_number: string
  base_salary: number
  currency: string
}

interface Period {
  id: string
  period_start: string
  period_end: string
  status: string
}

interface EarningType {
  id: string
  name: string
  code: string
  is_taxable: boolean
}

interface DeductionType {
  id: string
  name: string
  code: string
}

interface PayslipLine {
  line_type: 'earning' | 'deduction'
  earning_type_id: string
  deduction_type_id: string
  description: string
  amount: number
  quantity: number | null
  rate: number | null
}

const props = defineProps<{
  company: CompanyRef
  employees: Employee[]
  periods: Period[]
  earningTypes: EarningType[]
  deductionTypes: DeductionType[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Payslips', href: `/${props.company.slug}/payslips` },
  { title: 'Create', href: `/${props.company.slug}/payslips/create` },
]

const selectedEmployee = ref<Employee | null>(null)

const form = useForm({
  employee_id: '',
  payroll_period_id: '',
  currency: props.company.base_currency,
  notes: '',
  lines: [] as PayslipLine[],
})

// Watch for employee selection to set currency
watch(() => form.employee_id, (newVal) => {
  const emp = props.employees.find((e) => e.id === newVal)
  if (emp) {
    selectedEmployee.value = emp
    form.currency = emp.currency
    // Add base salary as default earning if no lines exist
    if (form.lines.length === 0) {
      addEarning()
      form.lines[0].description = 'Base Salary'
      form.lines[0].amount = emp.base_salary
    }
  }
})

const addEarning = () => {
  form.lines.push({
    line_type: 'earning',
    earning_type_id: '',
    deduction_type_id: '',
    description: '',
    amount: 0,
    quantity: null,
    rate: null,
  })
}

const addDeduction = () => {
  form.lines.push({
    line_type: 'deduction',
    earning_type_id: '',
    deduction_type_id: '',
    description: '',
    amount: 0,
    quantity: null,
    rate: null,
  })
}

const removeLine = (index: number) => {
  form.lines.splice(index, 1)
}

const earnings = computed(() => form.lines.filter((l) => l.line_type === 'earning'))
const deductions = computed(() => form.lines.filter((l) => l.line_type === 'deduction'))

const grossPay = computed(() => {
  return earnings.value.reduce((sum, line) => sum + Number(line.amount || 0), 0)
})

const totalDeductions = computed(() => {
  return deductions.value.reduce((sum, line) => sum + Number(line.amount || 0), 0)
})

const netPay = computed(() => grossPay.value - totalDeductions.value)

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const submit = () => {
  form.post(`/${props.company.slug}/payslips`)
}
</script>

<template>
  <Head title="Create Payslip" />

  <PageShell
    title="Create Payslip"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="$inertia.get(`/${company.slug}/payslips`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <form @submit.prevent="submit" class="space-y-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Payslip Info -->
          <Card>
            <CardHeader>
              <CardTitle>Payslip Information</CardTitle>
              <CardDescription>Select the employee and payroll period</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label for="employee">Employee *</Label>
                  <Select v-model="form.employee_id">
                    <SelectTrigger :class="{ 'border-destructive': form.errors.employee_id }">
                      <SelectValue placeholder="Select employee" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="emp in employees" :key="emp.id" :value="emp.id">
                        {{ emp.first_name }} {{ emp.last_name }} ({{ emp.employee_number }})
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <p v-if="form.errors.employee_id" class="text-sm text-destructive">
                    {{ form.errors.employee_id }}
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="period">Payroll Period *</Label>
                  <Select v-model="form.payroll_period_id">
                    <SelectTrigger :class="{ 'border-destructive': form.errors.payroll_period_id }">
                      <SelectValue placeholder="Select period" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="period in periods" :key="period.id" :value="period.id">
                        {{ formatDate(period.period_start) }} - {{ formatDate(period.period_end) }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <p v-if="form.errors.payroll_period_id" class="text-sm text-destructive">
                    {{ form.errors.payroll_period_id }}
                  </p>
                </div>
              </div>

              <div class="space-y-2">
                <Label for="currency">Currency</Label>
                <Input
                  id="currency"
                  v-model="form.currency"
                  maxlength="3"
                  readonly
                  class="w-24"
                />
              </div>
            </CardContent>
          </Card>

          <!-- Earnings -->
          <Card>
            <CardHeader>
              <div class="flex items-center justify-between">
                <div>
                  <CardTitle>Earnings</CardTitle>
                  <CardDescription>Add earnings for this payslip</CardDescription>
                </div>
                <Button type="button" variant="outline" size="sm" @click="addEarning">
                  <Plus class="mr-2 h-4 w-4" />
                  Add Earning
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Type</TableHead>
                    <TableHead>Description</TableHead>
                    <TableHead class="w-28">Qty</TableHead>
                    <TableHead class="w-28">Rate</TableHead>
                    <TableHead class="w-32">Amount</TableHead>
                    <TableHead class="w-12"></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <template v-for="(line, index) in form.lines" :key="index">
                    <TableRow v-if="line.line_type === 'earning'">
                      <TableCell>
                        <Select v-model="line.earning_type_id">
                          <SelectTrigger class="w-32">
                            <SelectValue placeholder="Type" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem v-for="et in earningTypes" :key="et.id" :value="et.id">
                              {{ et.name }}
                            </SelectItem>
                          </SelectContent>
                        </Select>
                      </TableCell>
                      <TableCell>
                        <Input v-model="line.description" placeholder="Description" />
                      </TableCell>
                      <TableCell>
                        <Input
                          v-model.number="line.quantity"
                          type="number"
                          step="0.01"
                          placeholder="Qty"
                        />
                      </TableCell>
                      <TableCell>
                        <Input
                          v-model.number="line.rate"
                          type="number"
                          step="0.01"
                          placeholder="Rate"
                        />
                      </TableCell>
                      <TableCell>
                        <Input
                          v-model.number="line.amount"
                          type="number"
                          step="0.01"
                          min="0"
                        />
                      </TableCell>
                      <TableCell>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          class="h-8 w-8 text-destructive"
                          @click="removeLine(index)"
                        >
                          <Trash2 class="h-4 w-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  </template>
                  <TableRow v-if="earnings.length === 0">
                    <TableCell colspan="6" class="text-center text-muted-foreground">
                      No earnings added. Click "Add Earning" to start.
                    </TableCell>
                  </TableRow>
                  <TableRow class="font-bold bg-muted/50">
                    <TableCell colspan="4">Gross Pay</TableCell>
                    <TableCell colspan="2">
                      {{ formatCurrency(grossPay, form.currency) }}
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          <!-- Deductions -->
          <Card>
            <CardHeader>
              <div class="flex items-center justify-between">
                <div>
                  <CardTitle>Deductions</CardTitle>
                  <CardDescription>Add deductions for this payslip</CardDescription>
                </div>
                <Button type="button" variant="outline" size="sm" @click="addDeduction">
                  <Plus class="mr-2 h-4 w-4" />
                  Add Deduction
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Type</TableHead>
                    <TableHead>Description</TableHead>
                    <TableHead class="w-32">Amount</TableHead>
                    <TableHead class="w-12"></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <template v-for="(line, index) in form.lines" :key="index">
                    <TableRow v-if="line.line_type === 'deduction'">
                      <TableCell>
                        <Select v-model="line.deduction_type_id">
                          <SelectTrigger class="w-32">
                            <SelectValue placeholder="Type" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem v-for="dt in deductionTypes" :key="dt.id" :value="dt.id">
                              {{ dt.name }}
                            </SelectItem>
                          </SelectContent>
                        </Select>
                      </TableCell>
                      <TableCell>
                        <Input v-model="line.description" placeholder="Description" />
                      </TableCell>
                      <TableCell>
                        <Input
                          v-model.number="line.amount"
                          type="number"
                          step="0.01"
                          min="0"
                        />
                      </TableCell>
                      <TableCell>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          class="h-8 w-8 text-destructive"
                          @click="removeLine(index)"
                        >
                          <Trash2 class="h-4 w-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  </template>
                  <TableRow v-if="deductions.length === 0">
                    <TableCell colspan="4" class="text-center text-muted-foreground">
                      No deductions added. Click "Add Deduction" to start.
                    </TableCell>
                  </TableRow>
                  <TableRow class="font-bold bg-muted/50">
                    <TableCell colspan="2">Total Deductions</TableCell>
                    <TableCell colspan="2" class="text-destructive">
                      -{{ formatCurrency(totalDeductions, form.currency) }}
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          <!-- Notes -->
          <Card>
            <CardHeader>
              <CardTitle>Notes</CardTitle>
            </CardHeader>
            <CardContent>
              <Textarea
                v-model="form.notes"
                placeholder="Any additional notes..."
                rows="3"
              />
            </CardContent>
          </Card>
        </div>

        <!-- Summary Sidebar -->
        <div class="space-y-6">
          <Card class="sticky top-6">
            <CardHeader>
              <CardTitle>Pay Summary</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="flex justify-between items-center">
                <span class="text-muted-foreground">Gross Pay</span>
                <span class="font-medium">{{ formatCurrency(grossPay, form.currency) }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-muted-foreground">Deductions</span>
                <span class="font-medium text-destructive">
                  -{{ formatCurrency(totalDeductions, form.currency) }}
                </span>
              </div>
              <hr />
              <div class="flex justify-between items-center">
                <span class="font-semibold">Net Pay</span>
                <span class="font-bold text-xl text-primary">
                  {{ formatCurrency(netPay, form.currency) }}
                </span>
              </div>

              <div class="pt-4">
                <Button type="submit" class="w-full" :disabled="form.processing">
                  <Save class="mr-2 h-4 w-4" />
                  {{ form.processing ? 'Creating...' : 'Create Payslip' }}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </form>
  </PageShell>
</template>
