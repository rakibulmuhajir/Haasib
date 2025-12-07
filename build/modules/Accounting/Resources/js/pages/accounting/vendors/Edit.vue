<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { BreadcrumbItem } from '@/types'
import { Building2, Save } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface VendorRef {
  id: string
  vendor_number: string
  name: string
  email: string | null
  phone: string | null
  base_currency: string
  payment_terms: number
  tax_id: string | null
  account_number: string | null
  website: string | null
  notes: string | null
  is_active: boolean
  address?: Record<string, string | null>
}

const props = defineProps<{
  company: CompanyRef
  vendor: VendorRef
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Vendors', href: `/${props.company.slug}/vendors` },
  { title: props.vendor.vendor_number, href: `/${props.company.slug}/vendors/${props.vendor.id}` },
  { title: 'Edit', href: `/${props.company.slug}/vendors/${props.vendor.id}/edit` },
]

const form = useForm({
  name: props.vendor.name,
  email: props.vendor.email ?? '',
  phone: props.vendor.phone ?? '',
  address: {
    street: props.vendor.address?.street ?? '',
    city: props.vendor.address?.city ?? '',
    state: props.vendor.address?.state ?? '',
    zip: props.vendor.address?.zip ?? '',
    country: props.vendor.address?.country ?? '',
  },
  tax_id: props.vendor.tax_id ?? '',
  payment_terms: props.vendor.payment_terms,
  account_number: props.vendor.account_number ?? '',
  notes: props.vendor.notes ?? '',
  website: props.vendor.website ?? '',
  is_active: props.vendor.is_active,
})

const handleSubmit = () => {
  form.put(`/${props.company.slug}/vendors/${props.vendor.id}`, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head :title="`Edit ${vendor.vendor_number}`" />
  <PageShell
    :title="`Edit ${vendor.vendor_number}`"
    :breadcrumbs="breadcrumbs"
    :icon="Building2"
  >
    <form class="space-y-6" @submit.prevent="handleSubmit">
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <Label>Vendor #</Label>
          <Input :value="vendor.vendor_number" disabled />
        </div>
        <div>
          <Label for="name">Name</Label>
          <Input id="name" v-model="form.name" required />
        </div>
        <div>
          <Label for="email">Email</Label>
          <Input id="email" v-model="form.email" type="email" />
        </div>
        <div>
          <Label for="phone">Phone</Label>
          <Input id="phone" v-model="form.phone" />
        </div>
        <div>
          <Label for="tax_id">Tax ID</Label>
          <Input id="tax_id" v-model="form.tax_id" />
        </div>
        <div>
          <Label for="payment_terms">Payment Terms</Label>
          <Input id="payment_terms" v-model.number="form.payment_terms" type="number" min="0" max="365" />
        </div>
        <div>
          <Label for="account_number">Account #</Label>
          <Input id="account_number" v-model="form.account_number" />
        </div>
        <div>
          <Label for="website">Website</Label>
          <Input id="website" v-model="form.website" />
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <Label>Street</Label>
          <Input v-model="form.address.street" />
        </div>
        <div>
          <Label>City</Label>
          <Input v-model="form.address.city" />
        </div>
        <div>
          <Label>State</Label>
          <Input v-model="form.address.state" />
        </div>
        <div>
          <Label>ZIP</Label>
          <Input v-model="form.address.zip" />
        </div>
        <div>
          <Label>Country</Label>
          <Input v-model="form.address.country" maxlength="2" />
        </div>
      </div>

      <div>
        <Label for="notes">Notes</Label>
        <Input id="notes" v-model="form.notes" />
      </div>

      <div class="flex justify-end gap-3">
        <Button type="submit">
          <Save class="mr-2 h-4 w-4" />
          Save Changes
        </Button>
      </div>
    </form>
  </PageShell>
</template>
