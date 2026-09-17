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
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: 'Dashboard', href: `/${companySlug.value}` }, { title: 'Payroll', href: `/${companySlug.value}/payroll` }, { title: 'Leave types', href: `/${companySlug.value}/leave-types` }, { title: 'New', href: `/${companySlug.value}/leave-types/create` }])
const form = useForm({ code: '', name: '', description: '', is_paid: true, accrual_rate_hours: 0, max_carryover_hours: null as number | null, max_balance_hours: null as number | null, requires_approval: true, is_active: true })
const submit = () => form.post(`/${companySlug.value}/leave-types`)
</script>
<template><Head title="New leave type" /><PageShell title="New leave type" description="Add a leave policy for employee requests." :breadcrumbs="breadcrumbs"><form class="max-w-2xl space-y-6" @submit.prevent="submit"><Card class="border-border/80"><CardHeader><CardTitle class="text-base">Leave policy</CardTitle><CardDescription>Set the paid status, accrual, and approval rules.</CardDescription></CardHeader><CardContent class="space-y-4"><div class="grid gap-4 sm:grid-cols-2"><div class="space-y-2"><Label for="code">Code *</Label><Input id="code" v-model="form.code" placeholder="ANNUAL" /><InputError :message="form.errors.code" /></div><div class="space-y-2"><Label for="name">Name *</Label><Input id="name" v-model="form.name" placeholder="Annual leave" /><InputError :message="form.errors.name" /></div></div><div class="space-y-2"><Label for="description">Description</Label><Textarea id="description" v-model="form.description" /></div><div class="grid gap-4 sm:grid-cols-2"><div class="space-y-2"><Label for="accrual">Accrual hours</Label><Input id="accrual" v-model="form.accrual_rate_hours" type="number" min="0" step="0.001" /></div><div class="space-y-2"><Label for="carryover">Max carryover hours</Label><Input id="carryover" v-model="form.max_carryover_hours" type="number" min="0" step="0.001" /></div></div><div class="grid gap-3 sm:grid-cols-2"><label class="flex items-center gap-2"><Checkbox v-model="form.is_paid" />Paid leave</label><label class="flex items-center gap-2"><Checkbox v-model="form.requires_approval" />Requires approval</label><label class="flex items-center gap-2"><Checkbox v-model="form.is_active" />Active</label></div></CardContent></Card><div class="flex gap-2"><Button type="button" variant="outline" as-child><a :href="`/${companySlug}/leave-types`"><ArrowLeft class="mr-2 h-4 w-4" />Cancel</a></Button><Button type="submit" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />Save leave type</Button></div></form></PageShell></template>
