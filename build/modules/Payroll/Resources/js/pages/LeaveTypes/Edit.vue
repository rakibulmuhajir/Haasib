<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import InputError from '@/components/InputError.vue'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { ArrowLeft, Save } from 'lucide-vue-next'

interface LeaveType {
  id: string
  code: string
  name: string
  description: string | null
  is_paid: boolean
  accrual_rate_hours: number | string | null
  max_carryover_hours: number | string | null
  max_balance_hours: number | string | null
  requires_approval: boolean
  is_active: boolean
}

const props = defineProps<{ company: { id: string; name: string; slug: string }; leaveType: LeaveType }>()
const { companySlug } = useCompanyRoute()
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Payroll', href: `/${companySlug.value}/payroll` },
  { title: 'Leave types', href: `/${companySlug.value}/leave-types` },
  { title: props.leaveType.code, href: `/${companySlug.value}/leave-types/${props.leaveType.id}/edit` },
])

/**
 * The hour columns arrive as decimal strings ("14.000"), and the accrual rate
 * is validated as `numeric` without `nullable` — an empty field would come back
 * as null and fail. So the rate is always a number, and only the two caps,
 * which really are optional, may be null.
 */
const optionalHours = (value: number | string | null): number | null =>
  value === null || value === '' ? null : Number(value)

const form = useForm({
  code: props.leaveType.code,
  name: props.leaveType.name,
  description: props.leaveType.description ?? '',
  is_paid: props.leaveType.is_paid,
  accrual_rate_hours: Number(props.leaveType.accrual_rate_hours ?? 0),
  max_carryover_hours: optionalHours(props.leaveType.max_carryover_hours),
  max_balance_hours: optionalHours(props.leaveType.max_balance_hours),
  requires_approval: props.leaveType.requires_approval,
  is_active: props.leaveType.is_active,
})

const submit = () => form.put(`/${companySlug.value}/leave-types/${props.leaveType.id}`)
</script>

<template>
  <Head :title="`Edit ${leaveType.name}`" />
  <PageShell :title="`Edit ${leaveType.name}`" description="Change the paid status, accrual, and approval rules for this leave policy." :breadcrumbs="breadcrumbs">
    <form novalidate class="max-w-2xl space-y-6" @submit.prevent="submit">
      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Leave policy</CardTitle>
          <CardDescription>Set the paid status, accrual, and approval rules.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="code">Code *</Label>
              <Input id="code" v-model="form.code" placeholder="ANNUAL" />
              <InputError :message="form.errors.code" />
            </div>
            <div class="space-y-2">
              <Label for="name">Name *</Label>
              <Input id="name" v-model="form.name" placeholder="Annual leave" />
              <InputError :message="form.errors.name" />
            </div>
          </div>
          <div class="space-y-2">
            <Label for="description">Description</Label>
            <Textarea id="description" v-model="form.description" />
            <InputError :message="form.errors.description" />
          </div>
          <div class="grid gap-4 sm:grid-cols-3">
            <div class="space-y-2">
              <Label for="accrual">Accrual hours</Label>
              <Input id="accrual" v-model.number="form.accrual_rate_hours" type="number" min="0" step="0.001" />
              <InputError :message="form.errors.accrual_rate_hours" />
            </div>
            <div class="space-y-2">
              <Label for="carryover">Max carryover hours</Label>
              <Input id="carryover" v-model.number="form.max_carryover_hours" type="number" min="0" step="0.001" />
              <InputError :message="form.errors.max_carryover_hours" />
            </div>
            <div class="space-y-2">
              <Label for="balance">Max balance hours</Label>
              <Input id="balance" v-model.number="form.max_balance_hours" type="number" min="0" step="0.001" />
              <InputError :message="form.errors.max_balance_hours" />
            </div>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex items-center gap-2"><Checkbox v-model="form.is_paid" />Paid leave</label>
            <label class="flex items-center gap-2"><Checkbox v-model="form.requires_approval" />Requires approval</label>
            <label class="flex items-center gap-2"><Checkbox v-model="form.is_active" />Active</label>
          </div>
        </CardContent>
      </Card>
      <div class="flex gap-2">
        <Button type="button" variant="outline" as-child>
          <a :href="`/${companySlug}/leave-types`"><ArrowLeft class="mr-2 h-4 w-4" />Cancel</a>
        </Button>
        <Button type="submit" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />Save changes</Button>
      </div>
    </form>
  </PageShell>
</template>
