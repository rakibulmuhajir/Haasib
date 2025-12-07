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

const props = defineProps<{
  company: CompanyRef
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Vendors', href: `/${props.company.slug}/vendors` },
  { title: 'Create', href: `/${props.company.slug}/vendors/create` },
]

const form = useForm({
  vendor_number: '',
  name: '',
  email: '',
  phone: '',
  address: {
    street: '',
    city: '',
    state: '',
    zip: '',
    country: '',
  },
  tax_id: '',
  base_currency: props.company.base_currency,
  payment_terms: 30,
  account_number: '',
  notes: '',
  website: '',
})

const handleSubmit = () => {
  form.post(`/${props.company.slug}/vendors`, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head title="Create Vendor" />
  <PageShell
    title="Create Vendor"
    :breadcrumbs="breadcrumbs"
    :icon="Building2"
  >
    <form class="space-y-6" @submit.prevent="handleSubmit">
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <Label for="vendor_number">Vendor # (optional)</Label>
          <Input id="vendor_number" v-model="form.vendor_number" placeholder="Auto-generated if blank" />
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
          <Label for="base_currency">Base Currency</Label>
          <Input id="base_currency" v-model="form.base_currency" maxlength="3" />
        </div>
        <div>
          <Label for="payment_terms">Payment Terms (days)</Label>
          <Input id="payment_terms" v-model.number="form.payment_terms" type="number" min="0" max="365" />
        </div>
        <div>
          <Label for="account_number">Account #</Label>
          <Input id="account_number" v-model="form.account_number" />
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
        <Label for="website">Website</Label>
        <Input id="website" v-model="form.website" />
      </div>
      <div>
        <Label for="notes">Notes</Label>
        <Input id="notes" v-model="form.notes" />
      </div>

      <div class="flex justify-end gap-3">
        <Button type="submit">
          <Save class="mr-2 h-4 w-4" />
          Save Vendor
        </Button>
      </div>
    </form>
  </PageShell>
</template>
