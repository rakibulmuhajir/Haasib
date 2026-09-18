<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import InputError from '@/components/InputError.vue'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { ArrowLeft, Save } from 'lucide-vue-next'

interface Employee { id: string; first_name: string; last_name: string; employee_number?: string | null }
interface LeaveType { id: string; code: string; name: string; is_paid: boolean }
interface LeaveRequest {
  id: string
  employee_id: string
  leave_type_id: string
  start_date: string
  end_date: string
  hours: number | string
  reason: string | null
  notes: string | null
  status: string
}

const props = defineProps<{
  company: { id: string; name: string; slug: string }
  leaveRequest: LeaveRequest
  employees: Employee[]
  leaveTypes: LeaveType[]
}>()

const { companySlug } = useCompanyRoute()
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Payroll', href: `/${companySlug.value}/payroll` },
  { title: 'Leave requests', href: `/${companySlug.value}/leave-requests` },
  { title: 'Edit', href: `/${companySlug.value}/leave-requests/${props.leaveRequest.id}/edit` },
])

/**
 * `start_date` and `end_date` are date casts, so they arrive as full ISO
 * timestamps. A native date input only accepts YYYY-MM-DD and silently shows
 * nothing for anything longer — which would read as "no date was set".
 */
const dateOnly = (value: string) => (value ? value.slice(0, 10) : '')

const form = useForm({
  employee_id: props.leaveRequest.employee_id,
  leave_type_id: props.leaveRequest.leave_type_id,
  start_date: dateOnly(props.leaveRequest.start_date),
  end_date: dateOnly(props.leaveRequest.end_date),
  hours: Number(props.leaveRequest.hours ?? 0),
  reason: props.leaveRequest.reason ?? '',
  notes: props.leaveRequest.notes ?? '',
})

const submit = () => form.put(`/${companySlug.value}/leave-requests/${props.leaveRequest.id}`)
</script>

<template>
  <Head title="Edit leave request" />
  <PageShell title="Edit leave request" description="Change a pending request before it is approved." :breadcrumbs="breadcrumbs">
    <form novalidate class="max-w-2xl space-y-6" @submit.prevent="submit">
      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Request details</CardTitle>
          <CardDescription>Select the employee, policy, dates, and requested hours.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label>Employee *</Label>
            <Select v-model="form.employee_id">
              <SelectTrigger><SelectValue placeholder="Select employee" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="employee in props.employees" :key="employee.id" :value="employee.id">
                  {{ employee.first_name }} {{ employee.last_name }}{{ employee.employee_number ? ` (${employee.employee_number})` : '' }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.employee_id" />
          </div>
          <div class="space-y-2">
            <Label>Leave type *</Label>
            <Select v-model="form.leave_type_id">
              <SelectTrigger><SelectValue placeholder="Select leave type" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="leave in props.leaveTypes" :key="leave.id" :value="leave.id">
                  {{ leave.code }} — {{ leave.name }}{{ leave.is_paid ? ' (paid)' : ' (unpaid)' }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.leave_type_id" />
          </div>
          <div class="grid gap-4 sm:grid-cols-3">
            <div class="space-y-2">
              <Label for="start">Start date *</Label>
              <Input id="start" v-model="form.start_date" type="date" />
              <InputError :message="form.errors.start_date" />
            </div>
            <div class="space-y-2">
              <Label for="end">End date *</Label>
              <Input id="end" v-model="form.end_date" type="date" />
              <InputError :message="form.errors.end_date" />
            </div>
            <div class="space-y-2">
              <Label for="hours">Hours *</Label>
              <Input id="hours" v-model.number="form.hours" type="number" min="0.5" step="0.5" />
              <InputError :message="form.errors.hours" />
            </div>
          </div>
          <div class="space-y-2">
            <Label for="reason">Reason</Label>
            <Textarea id="reason" v-model="form.reason" />
            <InputError :message="form.errors.reason" />
          </div>
          <div class="space-y-2">
            <Label for="notes">Notes</Label>
            <Textarea id="notes" v-model="form.notes" />
            <InputError :message="form.errors.notes" />
          </div>
        </CardContent>
      </Card>
      <div class="flex gap-2">
        <Button type="button" variant="outline" as-child>
          <a :href="`/${companySlug}/leave-requests/${leaveRequest.id}`"><ArrowLeft class="mr-2 h-4 w-4" />Cancel</a>
        </Button>
        <Button type="submit" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />Save changes</Button>
      </div>
    </form>
  </PageShell>
</template>
