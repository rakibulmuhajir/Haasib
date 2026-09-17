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

interface EarningType {
  id: string
  code: string
  name: string
  description?: string | null
  is_taxable: boolean
  affects_overtime: boolean
  is_recurring: boolean
  is_system: boolean
  is_active: boolean
}

const props = defineProps<{
  company: { id: string; name: string; slug: string }
  earningTypes: { data: EarningType[]; links?: unknown[] }
}>()

const { companySlug } = useCompanyRoute()
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Payroll', href: `/${companySlug.value}/payroll` },
  { title: 'Earning types', href: `/${companySlug.value}/earning-types` },
])
</script>

<template>
  <Head title="Earning types" />
  <PageShell
    title="Earning types"
    description="Define the earnings that can be added to payslips."
    :icon="Settings2"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button as-child>
        <a :href="`/${companySlug}/earning-types/create`"><Plus class="mr-2 h-4 w-4" />Add earning type</a>
      </Button>
    </template>

    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="text-base">Configured earnings</CardTitle>
        <CardDescription>{{ props.earningTypes.data.length }} earning types</CardDescription>
      </CardHeader>
      <CardContent>
        <div v-if="props.earningTypes.data.length === 0" class="py-8 text-center text-sm text-text-secondary">
          No earning types configured yet.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b text-left text-text-secondary">
              <tr><th class="px-3 py-2">Code</th><th class="px-3 py-2">Name</th><th class="px-3 py-2">Rules</th><th class="px-3 py-2 text-right">Action</th></tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="earning in props.earningTypes.data" :key="earning.id">
                <td class="px-3 py-3 font-mono">{{ earning.code }}</td>
                <td class="px-3 py-3"><div class="font-medium">{{ earning.name }}</div><div class="text-xs text-text-secondary">{{ earning.description }}</div></td>
                <td class="px-3 py-3"><div class="flex flex-wrap gap-1"><Badge v-if="earning.is_taxable" variant="outline">Taxable</Badge><Badge v-if="earning.is_recurring" variant="outline">Recurring</Badge><Badge v-if="earning.affects_overtime" variant="outline">Overtime</Badge><Badge v-if="!earning.is_active" variant="secondary">Inactive</Badge></div></td>
                <td class="px-3 py-3 text-right"><Button as-child variant="outline" size="sm"><a :href="`/${companySlug}/earning-types/${earning.id}/edit`">Edit</a></Button></td>
              </tr>
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  </PageShell>
</template>
