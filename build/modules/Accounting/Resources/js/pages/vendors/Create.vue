<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import InputError from '@/components/InputError.vue'
import type { BreadcrumbItem } from '@/types'
import { Building2, Save, Mail, Phone, ImageIcon } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

const props = defineProps<{
  company: CompanyRef
  vendorTypes: Record<string, string>
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Vendors', href: `/${props.company.slug}/vendors` },
  { title: 'New Vendor', href: `/${props.company.slug}/vendors/create` },
]

const form = useForm({
  name: '',
  email: '',
  phone: '',
  vendor_type: 'general',
  logo_url: '',
})

const handleSubmit = () => {
  form
    .transform((data) => ({
      name: data.name,
      email: data.email || null,
      phone: data.phone || null,
      vendor_type: data.vendor_type,
      logo_url: data.logo_url || null,
    }))
    .post(`/${props.company.slug}/vendors`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Vendor created successfully')
      },
      onError: () => {
        toast.error('Failed to create vendor')
      },
    })
}
</script>

<template>
  <Head title="New Vendor" />
  <PageShell
    title="New Vendor"
    :breadcrumbs="breadcrumbs"
    :icon="Building2"
  >
    <div class="mx-auto max-w-xl">
      <Card class="border-rule-default bg-surface-raised" variant="form">
        <CardHeader>
          <CardTitle class="text-text-primary">Vendor Details</CardTitle>
          <CardDescription class="text-text-secondary">
            Enter the basic information. You can add more details after creating.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form novalidate class="space-y-5" @submit.prevent="handleSubmit">
            <div class="space-y-2">
              <Label for="name" class="flex items-center gap-2 text-text-primary">
                <Building2 class="h-4 w-4 text-text-tertiary" />
                Vendor Name
              </Label>
              <Input
                id="name"
                v-model="form.name"
                placeholder="Acme Supplies Inc."
                required
                class="border-rule-default"
              />
              <InputError :message="form.errors.name" />
            </div>

            <div class="space-y-2">
              <Label for="email" class="flex items-center gap-2 text-text-primary">
                <Mail class="h-4 w-4 text-text-tertiary" />
                Email
              </Label>
              <Input
                id="email"
                v-model="form.email"
                type="email"
                placeholder="accounts@acme.com"
                class="border-rule-default"
              />
              <InputError :message="form.errors.email" />
            </div>

            <div class="space-y-2">
              <Label for="phone" class="flex items-center gap-2 text-text-primary">
                <Phone class="h-4 w-4 text-text-tertiary" />
                Phone
              </Label>
              <Input
                id="phone"
                v-model="form.phone"
                placeholder="+1 (555) 123-4567"
                class="border-rule-default"
              />
              <InputError :message="form.errors.phone" />
            </div>

            <div class="space-y-2">
              <Label for="vendor_type" class="flex items-center gap-2 text-text-primary">
                <Building2 class="h-4 w-4 text-text-tertiary" />
                Supplier Type
              </Label>
              <Select v-model="form.vendor_type">
                <SelectTrigger id="vendor_type" class="border-rule-default">
                  <SelectValue placeholder="Select supplier type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="(label, value) in vendorTypes" :key="value" :value="value">
                    {{ label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p class="text-xs text-text-secondary">Use fuel refinery/distributor/station for fuel suppliers.</p>
              <InputError :message="form.errors.vendor_type" />
            </div>

            <div class="space-y-2">
              <Label for="logo_url" class="flex items-center gap-2 text-text-primary">
                <ImageIcon class="h-4 w-4 text-text-tertiary" />
                Logo URL
                <span class="text-xs text-text-tertiary">(optional)</span>
              </Label>
              <Input
                id="logo_url"
                v-model="form.logo_url"
                placeholder="https://example.com/logo.png"
                class="border-rule-default"
              />
              <InputError :message="form.errors.logo_url" />
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-rule-subtle">
              <Button
                type="button"
                variant="outline"
                @click="$inertia.visit(`/${props.company.slug}/vendors`)"
              >
                Cancel
              </Button>
              <Button type="submit" :disabled="form.processing">
                <span
                  v-if="form.processing"
                  class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
                />
                <Save v-else class="mr-2 h-4 w-4" />
                Create Vendor
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
