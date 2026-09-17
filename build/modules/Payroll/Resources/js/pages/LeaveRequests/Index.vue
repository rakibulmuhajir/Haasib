<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { CalendarDays, Plus } from 'lucide-vue-next'
interface LeaveRequest { id: string; start_date: string; end_date: string; hours: number; reason?: string | null; status: string; employee?: { first_name: string; last_name: string; employee_number?: string | null } | null; leave_type?: { code: string; name: string } | null }
const props = defineProps<{ company: { id: string; name: string; slug: string }; leaveRequests: { data: LeaveRequest[] } }>()
const { companySlug } = useCompanyRoute()
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: 'Dashboard', href: `/${companySlug.value}` }, { title: 'Payroll', href: `/${companySlug.value}/payroll` }, { title: 'Leave requests', href: `/${companySlug.value}/leave-requests` }])
const dateOnly = (value: string) => value?.slice(0, 10) || '—'
</script>
<template><Head title="Leave requests" /><PageShell title="Leave requests" description="Review employee leave requests and their approval status." :icon="CalendarDays" :breadcrumbs="breadcrumbs"><template #actions><Button as-child><a :href="`/${companySlug}/leave-requests/create`"><Plus class="mr-2 h-4 w-4" />New leave request</a></Button></template><Card class="border-border/80"><CardHeader><CardTitle class="text-base">Requests</CardTitle><CardDescription>{{ props.leaveRequests.data.length }} leave requests</CardDescription></CardHeader><CardContent><div v-if="!props.leaveRequests.data.length" class="py-8 text-center text-sm text-text-secondary">No leave requests yet.</div><div v-else class="overflow-x-auto"><table class="w-full text-sm"><thead class="border-b text-left text-text-secondary"><tr><th class="px-3 py-2">Employee</th><th class="px-3 py-2">Leave</th><th class="px-3 py-2">Dates</th><th class="px-3 py-2">Hours</th><th class="px-3 py-2">Status</th></tr></thead><tbody class="divide-y"><tr v-for="request in props.leaveRequests.data" :key="request.id"><td class="px-3 py-3 font-medium">{{ request.employee ? `${request.employee.first_name} ${request.employee.last_name}` : '—' }}</td><td class="px-3 py-3">{{ request.leave_type?.name || '—' }}</td><td class="px-3 py-3">{{ dateOnly(request.start_date) }} → {{ dateOnly(request.end_date) }}</td><td class="px-3 py-3">{{ request.hours }}</td><td class="px-3 py-3"><Badge variant="outline">{{ request.status }}</Badge></td></tr></tbody></table></div></CardContent></Card></PageShell></template>
