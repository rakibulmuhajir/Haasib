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

interface DeductionType {
  id: string
  code: string
  name: string
  description: string | null
  is_pre_tax: boolean
  is_statutory: boolean
  is_recurring: boolean
  is_system: boolean
  is_active: boolean
}

const props = defineProps<{ company: { id: string; name: string; slug: string }; deductionType: DeductionType }>()
const { companySlug } = useCompanyRoute()
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Payroll', href: `/${companySlug.value}/payroll` },
  { title: 'Deduction types', href: `/${companySlug.value}/deduction-types` },
  { title: props.deductionType.code, href: `/${companySlug.value}/deduction-types/${props.deductionType.id}/edit` },
])

const form = useForm({
  code: props.deductionType.code,
  name: props.deductionType.name,
  description: props.deductionType.description ?? '',
  is_pre_tax: props.deductionType.is_pre_tax,
  is_statutory: props.deductionType.is_statutory,
  is_recurring: props.deductionType.is_recurring,
  is_active: props.deductionType.is_active,
})

/**
 * The controller refuses an update to a system deduction type with a flash
 * error. Disabling the fields here is the same refusal said earlier, so the
 * page never invites an edit the server is going to throw away.
 */
const isLocked = computed(() => props.deductionType.is_system)

const submit = () => form.put(`/${companySlug.value}/deduction-types/${props.deductionType.id}`)
</script>

<template>
  <Head :title="`Edit ${deductionType.name}`" />
  <PageShell :title="`Edit ${deductionType.name}`" description="Change how this deduction behaves in payroll." :breadcrumbs="breadcrumbs">
    <form novalidate class="max-w-2xl space-y-6" @submit.prevent="submit">
      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Deduction details</CardTitle>
          <CardDescription v-if="isLocked">This is a system deduction type and cannot be modified.</CardDescription>
          <CardDescription v-else>Define how this deduction behaves in payroll.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="code">Code *</Label>
              <Input id="code" v-model="form.code" :disabled="isLocked" placeholder="TAX" />
              <InputError :message="form.errors.code" />
            </div>
            <div class="space-y-2">
              <Label for="name">Name *</Label>
              <Input id="name" v-model="form.name" :disabled="isLocked" placeholder="Income tax" />
              <InputError :message="form.errors.name" />
            </div>
          </div>
          <div class="space-y-2">
            <Label for="description">Description</Label>
            <Textarea id="description" v-model="form.description" :disabled="isLocked" />
            <InputError :message="form.errors.description" />
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex items-center gap-2"><Checkbox v-model="form.is_pre_tax" :disabled="isLocked" />Pre-tax</label>
            <label class="flex items-center gap-2"><Checkbox v-model="form.is_statutory" :disabled="isLocked" />Statutory</label>
            <label class="flex items-center gap-2"><Checkbox v-model="form.is_recurring" :disabled="isLocked" />Recurring</label>
            <label class="flex items-center gap-2"><Checkbox v-model="form.is_active" :disabled="isLocked" />Active</label>
          </div>
        </CardContent>
      </Card>
      <div class="flex gap-2">
        <Button type="button" variant="outline" as-child>
          <a :href="`/${companySlug}/deduction-types`"><ArrowLeft class="mr-2 h-4 w-4" />Cancel</a>
        </Button>
        <Button type="submit" :disabled="form.processing || isLocked"><Save class="mr-2 h-4 w-4" />Save changes</Button>
      </div>
    </form>
  </PageShell>
</template>
