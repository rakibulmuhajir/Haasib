<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import DefinitionList from '@/components/DefinitionList.vue'
import PageShell from '@/components/PageShell.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { formatDateTime } from '@/lib/datetime'
import type { BreadcrumbItem } from '@/types'
import { ArrowLeft, Check, Pencil, X } from 'lucide-vue-next'

interface DefinitionItem {
  term: string
  value?: string | number | null
}

interface LeaveRequest {
  id: string
  start_date: string
  end_date: string
  hours: number | string
  reason: string | null
  notes: string | null
  status: string
  rejection_reason: string | null
  approved_at: string | null
  employee?: { id: string; first_name: string; last_name: string; employee_number?: string | null } | null
  leave_type?: { id: string; code: string; name: string } | null
  approved_by?: { id: string; name: string } | null
}

const props = defineProps<{ company: { id: string; name: string; slug: string }; leaveRequest: LeaveRequest }>()
const { companySlug } = useCompanyRoute()

const employeeName = computed(() =>
  props.leaveRequest.employee
    ? `${props.leaveRequest.employee.first_name} ${props.leaveRequest.employee.last_name}`
    : 'Unknown employee',
)

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Payroll', href: `/${companySlug.value}/payroll` },
  { title: 'Leave requests', href: `/${companySlug.value}/leave-requests` },
  { title: employeeName.value, href: `/${companySlug.value}/leave-requests/${props.leaveRequest.id}` },
])

const asDate = (value: string | null) => (value ? formatDateTime(value, { mode: 'date' }) : null)

const isPending = computed(() => props.leaveRequest.status === 'pending')

const details = computed<DefinitionItem[]>(() => [
  { term: 'Employee', value: employeeName.value },
  { term: 'Employee number', value: props.leaveRequest.employee?.employee_number ?? null },
  { term: 'Leave type', value: props.leaveRequest.leave_type ? `${props.leaveRequest.leave_type.code} — ${props.leaveRequest.leave_type.name}` : null },
  { term: 'Start date', value: asDate(props.leaveRequest.start_date) },
  { term: 'End date', value: asDate(props.leaveRequest.end_date) },
  { term: 'Hours', value: props.leaveRequest.hours },
])

/**
 * Who settled the request, and when. Shown only once it has been settled —
 * an empty "approved by" row on a pending request reads as a missing value
 * rather than as a decision nobody has taken yet.
 */
const decision = computed<DefinitionItem[]>(() => [
  { term: 'Decided by', value: props.leaveRequest.approved_by?.name ?? null },
  { term: 'Decided at', value: props.leaveRequest.approved_at ? formatDateTime(props.leaveRequest.approved_at, { mode: 'datetime' }) : null },
  { term: 'Rejection reason', value: props.leaveRequest.rejection_reason },
])

const approve = () => {
  router.post(`/${companySlug.value}/leave-requests/${props.leaveRequest.id}/approve`, {}, { preserveScroll: true })
}

const reject = () => {
  const reason = window.prompt('Why is this leave request being rejected?')?.trim()
  if (reason) {
    router.post(
      `/${companySlug.value}/leave-requests/${props.leaveRequest.id}/reject`,
      { rejection_reason: reason },
      { preserveScroll: true },
    )
  }
}
</script>

<template>
  <Head :title="`Leave request — ${employeeName}`" />
  <PageShell :title="`Leave request — ${employeeName}`" description="The request as submitted, and where it stands." :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button variant="outline" as-child>
        <a :href="`/${companySlug}/leave-requests`"><ArrowLeft class="mr-2 h-4 w-4" />Back</a>
      </Button>
      <Button v-if="isPending" variant="outline" as-child>
        <a :href="`/${companySlug}/leave-requests/${leaveRequest.id}/edit`"><Pencil class="mr-2 h-4 w-4" />Edit</a>
      </Button>
      <Button v-if="isPending" @click="approve"><Check class="mr-2 h-4 w-4" />Approve</Button>
      <Button v-if="isPending" variant="destructive" @click="reject"><X class="mr-2 h-4 w-4" />Reject</Button>
    </template>

    <div class="space-y-6">
      <Card class="border-border/80">
        <CardHeader>
          <div class="flex items-start justify-between gap-4">
            <div>
              <CardTitle class="text-base">Request</CardTitle>
              <CardDescription>What was asked for.</CardDescription>
            </div>
            <StatusBadge :status="leaveRequest.status" />
          </div>
        </CardHeader>
        <CardContent>
          <DefinitionList :items="details" />
        </CardContent>
      </Card>

      <Card v-if="!isPending" class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Decision</CardTitle>
          <CardDescription>Who settled this request, and on what grounds.</CardDescription>
        </CardHeader>
        <CardContent>
          <DefinitionList :items="decision" />
        </CardContent>
      </Card>

      <Card v-if="leaveRequest.reason || leaveRequest.notes" class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Reason and notes</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4 text-sm">
          <div v-if="leaveRequest.reason">
            <p class="text-text-secondary">Reason</p>
            <p class="mt-1 whitespace-pre-line">{{ leaveRequest.reason }}</p>
          </div>
          <div v-if="leaveRequest.notes">
            <p class="text-text-secondary">Notes</p>
            <p class="mt-1 whitespace-pre-line">{{ leaveRequest.notes }}</p>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
