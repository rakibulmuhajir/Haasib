<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Plus, Settings2 } from 'lucide-vue-next'

interface DeductionType { id: string; code: string; name: string; description?: string | null; is_pre_tax: boolean; is_statutory: boolean; is_recurring: boolean; is_active: boolean }
const props = defineProps<{ company: { id: string; name: string; slug: string }; deductionTypes: { data: DeductionType[] } }>()
const { companySlug } = useCompanyRoute()
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` }, { title: 'Payroll', href: `/${companySlug.value}/payroll` },
  { title: 'Deduction types', href: `/${companySlug.value}/deduction-types` },
])
</script>
<template>
  <Head title="Deduction types" />
  <PageShell title="Deduction types" description="Define deductions that can be applied to payslips." :icon="Settings2" :breadcrumbs="breadcrumbs">
    <template #actions><Button as-child><a :href="`/${companySlug}/deduction-types/create`"><Plus class="mr-2 h-4 w-4" />Add deduction type</a></Button></template>
    <Card class="border-border/80"><CardHeader><CardTitle class="text-base">Configured deductions</CardTitle><CardDescription>{{ props.deductionTypes.data.length }} deduction types</CardDescription></CardHeader><CardContent>
      <div v-if="!props.deductionTypes.data.length" class="py-8 text-center text-sm text-text-secondary">No deduction types configured yet.</div>
      <div v-else class="overflow-x-auto"><table class="w-full text-sm"><thead class="border-b text-left text-text-secondary"><tr><th class="px-3 py-2">Code</th><th class="px-3 py-2">Name</th><th class="px-3 py-2">Rules</th><th class="px-3 py-2 text-right">Action</th></tr></thead><tbody class="divide-y"><tr v-for="deduction in props.deductionTypes.data" :key="deduction.id"><td class="px-3 py-3 font-mono">{{ deduction.code }}</td><td class="px-3 py-3"><div class="font-medium">{{ deduction.name }}</div><div class="text-xs text-text-secondary">{{ deduction.description }}</div></td><td class="px-3 py-3"><div class="flex flex-wrap gap-1"><Badge v-if="deduction.is_pre_tax" variant="outline">Pre-tax</Badge><Badge v-if="deduction.is_statutory" variant="outline">Statutory</Badge><Badge v-if="deduction.is_recurring" variant="outline">Recurring</Badge><Badge v-if="!deduction.is_active" variant="secondary">Inactive</Badge></div></td><td class="px-3 py-3 text-right"><Button as-child variant="outline" size="sm"><a :href="`/${companySlug}/deduction-types/${deduction.id}/edit`">Edit</a></Button></td></tr></tbody></table></div>
    </CardContent></Card>
  </PageShell>
</template>
