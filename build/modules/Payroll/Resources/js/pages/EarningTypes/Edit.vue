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

interface EarningType {
  id: string
  code: string
  name: string
  description: string | null
  is_taxable: boolean
  affects_overtime: boolean
  is_recurring: boolean
  is_system: boolean
  is_active: boolean
}

const props = defineProps<{ company: { id: string; name: string; slug: string }; earningType: EarningType }>()
const { companySlug } = useCompanyRoute()
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Payroll', href: `/${companySlug.value}/payroll` },
  { title: 'Earning types', href: `/${companySlug.value}/earning-types` },
  { title: props.earningType.code, href: `/${companySlug.value}/earning-types/${props.earningType.id}/edit` },
])

const form = useForm({
  code: props.earningType.code,
  name: props.earningType.name,
  description: props.earningType.description ?? '',
  is_taxable: props.earningType.is_taxable,
  affects_overtime: props.earningType.affects_overtime,
  is_recurring: props.earningType.is_recurring,
  is_active: props.earningType.is_active,
})

/**
 * The controller refuses an update to a system earning type with a flash
 * error. Disabling the fields here is the same refusal said earlier, so the
 * page never invites an edit the server is going to throw away.
 */
const isLocked = computed(() => props.earningType.is_system)

const submit = () => form.put(`/${companySlug.value}/earning-types/${props.earningType.id}`)
</script>

<template>
  <Head :title="`Edit ${earningType.name}`" />
  <PageShell :title="`Edit ${earningType.name}`" description="Change how this earning behaves in payroll." :breadcrumbs="breadcrumbs">
    <form novalidate class="max-w-2xl space-y-6" @submit.prevent="submit">
      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Earning details</CardTitle>
          <CardDescription v-if="isLocked">This is a system earning type and cannot be modified.</CardDescription>
          <CardDescription v-else>Use a stable code so payroll rules remain easy to audit.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="code">Code *</Label>
              <Input id="code" v-model="form.code" :disabled="isLocked" placeholder="BONUS" />
              <InputError :message="form.errors.code" />
            </div>
            <div class="space-y-2">
              <Label for="name">Name *</Label>
              <Input id="name" v-model="form.name" :disabled="isLocked" placeholder="Performance bonus" />
              <InputError :message="form.errors.name" />
            </div>
          </div>
          <div class="space-y-2">
            <Label for="description">Description</Label>
            <Textarea id="description" v-model="form.description" :disabled="isLocked" />
            <InputError :message="form.errors.description" />
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex items-center gap-2"><Checkbox v-model="form.is_taxable" :disabled="isLocked" />Taxable</label>
            <label class="flex items-center gap-2"><Checkbox v-model="form.affects_overtime" :disabled="isLocked" />Affects overtime</label>
            <label class="flex items-center gap-2"><Checkbox v-model="form.is_recurring" :disabled="isLocked" />Recurring</label>
            <label class="flex items-center gap-2"><Checkbox v-model="form.is_active" :disabled="isLocked" />Active</label>
          </div>
        </CardContent>
      </Card>
      <div class="flex gap-2">
        <Button type="button" variant="outline" as-child>
          <a :href="`/${companySlug}/earning-types`"><ArrowLeft class="mr-2 h-4 w-4" />Cancel</a>
        </Button>
        <Button type="submit" :disabled="form.processing || isLocked"><Save class="mr-2 h-4 w-4" />Save changes</Button>
      </div>
    </form>
  </PageShell>
</template>
