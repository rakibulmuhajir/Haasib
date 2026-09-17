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
interface LeaveType { id: string; code: string; name: string; description?: string | null; is_paid: boolean; accrual_rate_hours: number; requires_approval: boolean; is_active: boolean }
const props = defineProps<{ company: { id: string; name: string; slug: string }; leaveTypes: { data: LeaveType[] } }>()
const { companySlug } = useCompanyRoute()
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: 'Dashboard', href: `/${companySlug.value}` }, { title: 'Payroll', href: `/${companySlug.value}/payroll` }, { title: 'Leave types', href: `/${companySlug.value}/leave-types` }])
</script>
<template><Head title="Leave types" /><PageShell title="Leave types" description="Configure paid and unpaid leave policies." :icon="CalendarDays" :breadcrumbs="breadcrumbs"><template #actions><Button as-child><a :href="`/${companySlug}/leave-types/create`"><Plus class="mr-2 h-4 w-4" />Add leave type</a></Button></template><Card class="border-border/80"><CardHeader><CardTitle class="text-base">Configured leave</CardTitle><CardDescription>{{ props.leaveTypes.data.length }} leave types</CardDescription></CardHeader><CardContent><div v-if="!props.leaveTypes.data.length" class="py-8 text-center text-sm text-text-secondary">No leave types configured yet.</div><div v-else class="overflow-x-auto"><table class="w-full text-sm"><thead class="border-b text-left text-text-secondary"><tr><th class="px-3 py-2">Code</th><th class="px-3 py-2">Name</th><th class="px-3 py-2">Policy</th><th class="px-3 py-2 text-right">Action</th></tr></thead><tbody class="divide-y"><tr v-for="leave in props.leaveTypes.data" :key="leave.id"><td class="px-3 py-3 font-mono">{{ leave.code }}</td><td class="px-3 py-3"><div class="font-medium">{{ leave.name }}</div><div class="text-xs text-text-secondary">{{ leave.description }}</div></td><td class="px-3 py-3"><div class="flex flex-wrap gap-1"><Badge variant="outline">{{ leave.is_paid ? 'Paid' : 'Unpaid' }}</Badge><Badge v-if="leave.requires_approval" variant="outline">Approval</Badge><Badge v-if="!leave.is_active" variant="secondary">Inactive</Badge></div></td><td class="px-3 py-3 text-right"><Button as-child variant="outline" size="sm"><a :href="`/${companySlug}/leave-types/${leave.id}/edit`">Edit</a></Button></td></tr></tbody></table></div></CardContent></Card></PageShell></template>
