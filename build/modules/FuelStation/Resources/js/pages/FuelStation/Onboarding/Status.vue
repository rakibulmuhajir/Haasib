<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import type { BreadcrumbItem } from '@/types'
import { CheckCircle2, Circle, ClipboardCheck } from 'lucide-vue-next'

interface WizardStep {
  name: string
  description: string
  complete: boolean
  hidden?: boolean
}

interface OnboardingStatus {
  is_complete: boolean
  progress_percentage: number
  completed_steps: string[]
  total_steps: number
  current_step: string
  steps: Record<string, WizardStep>
  company_name: string
  industry: string
}

const props = defineProps<{
  status: OnboardingStatus
}>()

const { companySlug } = useCompanyRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/dashboard` },
  { title: 'Setup status', href: `/${companySlug.value}/fuel/onboarding/status` },
])

const steps = computed(() => Object.entries(props.status.steps ?? {})
  .filter(([, step]) => !step.hidden)
  .map(([id, step]) => ({
    id,
    name: step.name || ({
      lubricants: 'Lubricants',
      initial_stock: 'Initial stock',
      opening_cash: 'Opening cash',
    } as Record<string, string>)[id] || id.replaceAll('_', ' '),
    description: step.description || 'Optional station setup item',
    complete: step.complete,
  })))

const currentStep = computed(() => props.status.steps?.[props.status.current_step])
const completedVisibleStepCount = computed(() => steps.value.filter((step) => step.complete).length)
const visibleProgress = computed(() => steps.value.length
  ? Math.round((completedVisibleStepCount.value / steps.value.length) * 100)
  : 0)
</script>

<template>
  <Head title="Fuel onboarding status" />

  <PageShell
    title="Fuel onboarding status"
    description="Review setup progress and the next required step for this station."
    :icon="ClipboardCheck"
    :breadcrumbs="breadcrumbs"
  >
    <div class="space-y-6">
      <Card class="border-border/80">
        <CardHeader>
          <div class="flex items-start justify-between gap-4">
            <div>
              <CardTitle>{{ props.status.company_name || 'Fuel station' }}</CardTitle>
              <CardDescription>
                {{ completedVisibleStepCount }} of {{ steps.length }} setup steps complete
              </CardDescription>
            </div>
            <Badge :class="props.status.is_complete ? 'bg-status-success/10 text-status-success' : 'bg-status-attention/10 text-status-attention'">
              {{ props.status.is_complete ? 'Complete' : 'In progress' }}
            </Badge>
          </div>
        </CardHeader>
        <CardContent class="space-y-3">
          <Progress :value="visibleProgress" class="h-2" />
          <div class="flex justify-between text-sm text-text-secondary">
            <span>0%</span>
            <span>{{ visibleProgress }}% complete</span>
            <span>100%</span>
          </div>
          <div v-if="currentStep" class="rounded-lg border border-status-info/30 bg-status-info/10 p-3 text-sm">
            <div class="font-medium">Next step: {{ currentStep.name }}</div>
            <div class="mt-1 text-text-secondary">{{ currentStep.description }}</div>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Setup steps</CardTitle>
          <CardDescription>Required and optional station setup items.</CardDescription>
        </CardHeader>
        <CardContent class="divide-y divide-border p-0">
          <div v-for="step in steps" :key="step.id" class="flex items-center gap-3 px-6 py-4">
            <CheckCircle2 v-if="step.complete" class="h-5 w-5 shrink-0 text-status-success" />
            <Circle v-else class="h-5 w-5 shrink-0 text-text-tertiary" />
            <div class="min-w-0 flex-1">
              <div class="font-medium">{{ step.name }}</div>
              <div class="text-sm text-text-secondary">{{ step.description }}</div>
            </div>
            <Badge v-if="step.complete" variant="outline">Done</Badge>
            <Badge v-else variant="secondary">Pending</Badge>
          </div>
        </CardContent>
      </Card>

      <Button as-child>
        <a :href="`/${companySlug}/fuel/onboarding`">Open setup wizard</a>
      </Button>
    </div>
  </PageShell>
</template>
