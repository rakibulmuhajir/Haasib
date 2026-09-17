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

defineProps<{ company: { id: string; name: string; slug: string } }>()
const { companySlug } = useCompanyRoute()
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Payroll', href: `/${companySlug.value}/payroll` },
  { title: 'Earning types', href: `/${companySlug.value}/earning-types` },
  { title: 'New', href: `/${companySlug.value}/earning-types/create` },
])

const form = useForm({
  code: '', name: '', description: '', is_taxable: true,
  affects_overtime: false, is_recurring: false, is_active: true,
})

const submit = () => form.post(`/${companySlug.value}/earning-types`)
</script>

<template>
  <Head title="New earning type" />
  <PageShell title="New earning type" description="Add an earning rule for payslips." :breadcrumbs="breadcrumbs">
    <form class="max-w-2xl space-y-6" @submit.prevent="submit">
      <Card class="border-border/80">
        <CardHeader><CardTitle class="text-base">Earning details</CardTitle><CardDescription>Use a stable code so payroll rules remain easy to audit.</CardDescription></CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2"><Label for="code">Code *</Label><Input id="code" v-model="form.code" placeholder="BONUS" /><InputError :message="form.errors.code" /></div>
            <div class="space-y-2"><Label for="name">Name *</Label><Input id="name" v-model="form.name" placeholder="Performance bonus" /><InputError :message="form.errors.name" /></div>
          </div>
          <div class="space-y-2"><Label for="description">Description</Label><Textarea id="description" v-model="form.description" /><InputError :message="form.errors.description" /></div>
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex items-center gap-2"><Checkbox v-model="form.is_taxable" />Taxable</label>
            <label class="flex items-center gap-2"><Checkbox v-model="form.affects_overtime" />Affects overtime</label>
            <label class="flex items-center gap-2"><Checkbox v-model="form.is_recurring" />Recurring</label>
            <label class="flex items-center gap-2"><Checkbox v-model="form.is_active" />Active</label>
          </div>
        </CardContent>
      </Card>
      <div class="flex gap-2"><Button type="button" variant="outline" as-child><a :href="`/${companySlug}/earning-types`"><ArrowLeft class="mr-2 h-4 w-4" />Cancel</a></Button><Button type="submit" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />Save earning type</Button></div>
    </form>
  </PageShell>
</template>
