<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Save, ArrowLeft } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

const props = defineProps<{
  company: CompanyRef
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Payroll Periods', href: `/${props.company.slug}/payroll-periods` },
  { title: 'Create', href: `/${props.company.slug}/payroll-periods/create` },
]

// Default to current month
const today = new Date()
const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)
const lastOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0)

const form = useForm({
  period_start: firstOfMonth.toISOString().split('T')[0],
  period_end: lastOfMonth.toISOString().split('T')[0],
  payment_date: lastOfMonth.toISOString().split('T')[0],
})

const submit = () => {
  form.post(`/${props.company.slug}/payroll-periods`)
}
</script>

<template>
  <Head title="Create Payroll Period" />

  <PageShell
    title="Create Payroll Period"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="$inertia.get(`/${company.slug}/payroll-periods`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <form @submit.prevent="submit" class="space-y-6 max-w-2xl">
      <Card>
        <CardHeader>
          <CardTitle>Period Details</CardTitle>
          <CardDescription>Define the payroll period dates</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="period_start">Period Start *</Label>
              <Input
                id="period_start"
                type="date"
                v-model="form.period_start"
                :class="{ 'border-destructive': form.errors.period_start }"
              />
              <p v-if="form.errors.period_start" class="text-sm text-destructive">{{ form.errors.period_start }}</p>
            </div>

            <div class="space-y-2">
              <Label for="period_end">Period End *</Label>
              <Input
                id="period_end"
                type="date"
                v-model="form.period_end"
                :class="{ 'border-destructive': form.errors.period_end }"
              />
              <p v-if="form.errors.period_end" class="text-sm text-destructive">{{ form.errors.period_end }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="payment_date">Payment Date *</Label>
            <Input
              id="payment_date"
              type="date"
              v-model="form.payment_date"
              :class="{ 'border-destructive': form.errors.payment_date }"
            />
            <p class="text-sm text-muted-foreground">The date when employees will be paid</p>
            <p v-if="form.errors.payment_date" class="text-sm text-destructive">{{ form.errors.payment_date }}</p>
          </div>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <Button variant="outline" type="button" @click="$inertia.get(`/${company.slug}/payroll-periods`)">
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing">
          <Save class="mr-2 h-4 w-4" />
          {{ form.processing ? 'Creating...' : 'Create Period' }}
        </Button>
      </div>
    </form>
  </PageShell>
</template>
