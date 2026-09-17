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
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: 'Dashboard', href: `/${companySlug.value}` }, { title: 'Payroll', href: `/${companySlug.value}/payroll` }, { title: 'Deduction types', href: `/${companySlug.value}/deduction-types` }, { title: 'New', href: `/${companySlug.value}/deduction-types/create` }])
const form = useForm({ code: '', name: '', description: '', is_pre_tax: false, is_statutory: false, is_recurring: false, is_active: true })
const submit = () => form.post(`/${companySlug.value}/deduction-types`)
</script>
<template>
  <Head title="New deduction type" /><PageShell title="New deduction type" description="Add a deduction rule for payslips." :breadcrumbs="breadcrumbs"><form class="max-w-2xl space-y-6" @submit.prevent="submit"><Card class="border-border/80"><CardHeader><CardTitle class="text-base">Deduction details</CardTitle><CardDescription>Define how this deduction behaves in payroll.</CardDescription></CardHeader><CardContent class="space-y-4"><div class="grid gap-4 sm:grid-cols-2"><div class="space-y-2"><Label for="code">Code *</Label><Input id="code" v-model="form.code" placeholder="TAX" /><InputError :message="form.errors.code" /></div><div class="space-y-2"><Label for="name">Name *</Label><Input id="name" v-model="form.name" placeholder="Income tax" /><InputError :message="form.errors.name" /></div></div><div class="space-y-2"><Label for="description">Description</Label><Textarea id="description" v-model="form.description" /><InputError :message="form.errors.description" /></div><div class="grid gap-3 sm:grid-cols-2"><label class="flex items-center gap-2"><Checkbox v-model="form.is_pre_tax" />Pre-tax</label><label class="flex items-center gap-2"><Checkbox v-model="form.is_statutory" />Statutory</label><label class="flex items-center gap-2"><Checkbox v-model="form.is_recurring" />Recurring</label><label class="flex items-center gap-2"><Checkbox v-model="form.is_active" />Active</label></div></CardContent></Card><div class="flex gap-2"><Button type="button" variant="outline" as-child><a :href="`/${companySlug}/deduction-types`"><ArrowLeft class="mr-2 h-4 w-4" />Cancel</a></Button><Button type="submit" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />Save deduction type</Button></div></form></PageShell>
</template>
