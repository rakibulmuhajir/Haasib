<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Save, ArrowLeft } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface Manager {
  id: string
  first_name: string
  last_name: string
  employee_number: string
}

interface Employee {
  id: string
  employee_number: string
  first_name: string
  last_name: string
  email: string | null
  phone: string | null
  hire_date: string
  termination_date: string | null
  termination_reason: string | null
  employment_type: string
  employment_status: string
  department: string | null
  position: string | null
  manager_id: string | null
  pay_frequency: string
  base_salary: number
  currency: string
  is_active: boolean
  notes: string | null
}

const props = defineProps<{
  company: CompanyRef
  employee: Employee
  managers: Manager[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Employees', href: `/${props.company.slug}/employees` },
  { title: `${props.employee.first_name} ${props.employee.last_name}`, href: `/${props.company.slug}/employees/${props.employee.id}` },
  { title: 'Edit', href: `/${props.company.slug}/employees/${props.employee.id}/edit` },
]

const form = useForm({
  employee_number: props.employee.employee_number,
  first_name: props.employee.first_name,
  last_name: props.employee.last_name,
  email: props.employee.email ?? '',
  phone: props.employee.phone ?? '',
  hire_date: props.employee.hire_date,
  termination_date: props.employee.termination_date ?? '',
  termination_reason: props.employee.termination_reason ?? '',
  employment_type: props.employee.employment_type,
  employment_status: props.employee.employment_status,
  department: props.employee.department ?? '',
  position: props.employee.position ?? '',
  manager_id: props.employee.manager_id ?? '',
  pay_frequency: props.employee.pay_frequency,
  base_salary: props.employee.base_salary,
  currency: props.employee.currency,
  notes: props.employee.notes ?? '',
})

const submit = () => {
  form.put(`/${props.company.slug}/employees/${props.employee.id}`)
}
</script>

<template>
  <Head :title="`Edit ${employee.first_name} ${employee.last_name}`" />

  <PageShell
    :title="`Edit ${employee.first_name} ${employee.last_name}`"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="$inertia.get(`/${company.slug}/employees/${employee.id}`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <form @submit.prevent="submit" class="space-y-6 max-w-4xl">
      <!-- Personal Information -->
      <Card>
        <CardHeader>
          <CardTitle>Personal Information</CardTitle>
          <CardDescription>Basic employee details</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-2">
              <Label for="employee_number">Employee ID *</Label>
              <Input
                id="employee_number"
                v-model="form.employee_number"
                :class="{ 'border-destructive': form.errors.employee_number }"
              />
              <p v-if="form.errors.employee_number" class="text-sm text-destructive">{{ form.errors.employee_number }}</p>
            </div>

            <div class="space-y-2">
              <Label for="first_name">First Name *</Label>
              <Input
                id="first_name"
                v-model="form.first_name"
                :class="{ 'border-destructive': form.errors.first_name }"
              />
              <p v-if="form.errors.first_name" class="text-sm text-destructive">{{ form.errors.first_name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="last_name">Last Name *</Label>
              <Input
                id="last_name"
                v-model="form.last_name"
                :class="{ 'border-destructive': form.errors.last_name }"
              />
              <p v-if="form.errors.last_name" class="text-sm text-destructive">{{ form.errors.last_name }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="email">Email</Label>
              <Input
                id="email"
                type="email"
                v-model="form.email"
              />
            </div>

            <div class="space-y-2">
              <Label for="phone">Phone</Label>
              <Input
                id="phone"
                v-model="form.phone"
              />
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Employment Details -->
      <Card>
        <CardHeader>
          <CardTitle>Employment Details</CardTitle>
          <CardDescription>Job and employment information</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-2">
              <Label for="hire_date">Hire Date *</Label>
              <Input
                id="hire_date"
                type="date"
                v-model="form.hire_date"
                :class="{ 'border-destructive': form.errors.hire_date }"
              />
              <p v-if="form.errors.hire_date" class="text-sm text-destructive">{{ form.errors.hire_date }}</p>
            </div>

            <div class="space-y-2">
              <Label for="employment_type">Employment Type *</Label>
              <Select v-model="form.employment_type">
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="full_time">Full Time</SelectItem>
                  <SelectItem value="part_time">Part Time</SelectItem>
                  <SelectItem value="contract">Contract</SelectItem>
                  <SelectItem value="intern">Intern</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="employment_status">Status *</Label>
              <Select v-model="form.employment_status">
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="on_leave">On Leave</SelectItem>
                  <SelectItem value="suspended">Suspended</SelectItem>
                  <SelectItem value="terminated">Terminated</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <!-- Termination fields (shown when terminated) -->
          <div v-if="form.employment_status === 'terminated'" class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-destructive/10 rounded-lg">
            <div class="space-y-2">
              <Label for="termination_date">Termination Date</Label>
              <Input
                id="termination_date"
                type="date"
                v-model="form.termination_date"
              />
            </div>

            <div class="space-y-2">
              <Label for="termination_reason">Termination Reason</Label>
              <Input
                id="termination_reason"
                v-model="form.termination_reason"
                placeholder="Reason for termination"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-2">
              <Label for="department">Department</Label>
              <Input
                id="department"
                v-model="form.department"
              />
            </div>

            <div class="space-y-2">
              <Label for="position">Position</Label>
              <Input
                id="position"
                v-model="form.position"
              />
            </div>

            <div class="space-y-2">
              <Label for="manager">Manager</Label>
              <Select v-model="form.manager_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select manager" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">No manager</SelectItem>
                  <SelectItem v-for="mgr in managers" :key="mgr.id" :value="mgr.id">
                    {{ mgr.first_name }} {{ mgr.last_name }} ({{ mgr.employee_number }})
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Compensation -->
      <Card>
        <CardHeader>
          <CardTitle>Compensation</CardTitle>
          <CardDescription>Salary and payment details</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-2">
              <Label for="pay_frequency">Pay Frequency *</Label>
              <Select v-model="form.pay_frequency">
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="weekly">Weekly</SelectItem>
                  <SelectItem value="biweekly">Bi-weekly</SelectItem>
                  <SelectItem value="semimonthly">Semi-monthly</SelectItem>
                  <SelectItem value="monthly">Monthly</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="base_salary">Base Salary *</Label>
              <Input
                id="base_salary"
                type="number"
                step="0.01"
                min="0"
                v-model="form.base_salary"
                :class="{ 'border-destructive': form.errors.base_salary }"
              />
              <p v-if="form.errors.base_salary" class="text-sm text-destructive">{{ form.errors.base_salary }}</p>
            </div>

            <div class="space-y-2">
              <Label for="currency">Currency *</Label>
              <Input
                id="currency"
                v-model="form.currency"
                maxlength="3"
                :class="{ 'border-destructive': form.errors.currency }"
              />
              <p v-if="form.errors.currency" class="text-sm text-destructive">{{ form.errors.currency }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Notes -->
      <Card>
        <CardHeader>
          <CardTitle>Additional Notes</CardTitle>
        </CardHeader>
        <CardContent>
          <Textarea
            v-model="form.notes"
            placeholder="Any additional notes..."
            rows="3"
          />
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <Button variant="outline" type="button" @click="$inertia.get(`/${company.slug}/employees/${employee.id}`)">
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing">
          <Save class="mr-2 h-4 w-4" />
          {{ form.processing ? 'Saving...' : 'Update Employee' }}
        </Button>
      </div>
    </form>
  </PageShell>
</template>
