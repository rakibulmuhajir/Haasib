<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
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
  logo_url: '',
})

const handleSubmit = () => {
  form
    .transform((data) => ({
      name: data.name,
      email: data.email || null,
      phone: data.phone || null,
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
      <Card class="border-zinc-200/80 bg-white">
        <CardHeader>
          <CardTitle class="text-zinc-900">Vendor Details</CardTitle>
          <CardDescription class="text-zinc-500">
            Enter the basic information. You can add more details after creating.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form class="space-y-5" @submit.prevent="handleSubmit">
            <div class="space-y-2">
              <Label for="name" class="flex items-center gap-2 text-zinc-700">
                <Building2 class="h-4 w-4 text-zinc-400" />
                Vendor Name
              </Label>
              <Input
                id="name"
                v-model="form.name"
                placeholder="Acme Supplies Inc."
                required
                class="border-zinc-200"
              />
              <p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="email" class="flex items-center gap-2 text-zinc-700">
                <Mail class="h-4 w-4 text-zinc-400" />
                Email
              </Label>
              <Input
                id="email"
                v-model="form.email"
                type="email"
                placeholder="accounts@acme.com"
                class="border-zinc-200"
              />
              <p v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</p>
            </div>

            <div class="space-y-2">
              <Label for="phone" class="flex items-center gap-2 text-zinc-700">
                <Phone class="h-4 w-4 text-zinc-400" />
                Phone
              </Label>
              <Input
                id="phone"
                v-model="form.phone"
                placeholder="+1 (555) 123-4567"
                class="border-zinc-200"
              />
              <p v-if="form.errors.phone" class="text-xs text-red-600">{{ form.errors.phone }}</p>
            </div>

            <div class="space-y-2">
              <Label for="logo_url" class="flex items-center gap-2 text-zinc-700">
                <ImageIcon class="h-4 w-4 text-zinc-400" />
                Logo URL
                <span class="text-xs text-zinc-400">(optional)</span>
              </Label>
              <Input
                id="logo_url"
                v-model="form.logo_url"
                placeholder="https://example.com/logo.png"
                class="border-zinc-200"
              />
              <p v-if="form.errors.logo_url" class="text-xs text-red-600">{{ form.errors.logo_url }}</p>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-100">
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
