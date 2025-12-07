<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { Users, Save } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface CurrencyOption {
  currency_code: string
  is_base: boolean
}

const props = defineProps<{
  company: CompanyRef
  currencies: CurrencyOption[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Customers', href: `/${props.company.slug}/customers` },
  { title: 'Create', href: `/${props.company.slug}/customers/create` },
]

const currencyOptions = computed(() =>
  props.currencies.length ? props.currencies : [{ currency_code: props.company.base_currency, is_base: true }]
)

const form = useForm({
  name: '',
  email: '',
  phone: '',
  base_currency: props.company.base_currency,
  payment_terms: '',
  tax_id: '',
  credit_limit: '',
  notes: '',
  logo_url: '',
  billing_street: '',
  billing_city: '',
  billing_state: '',
  billing_zip: '',
  billing_country: '',
  shipping_street: '',
  shipping_city: '',
  shipping_state: '',
  shipping_zip: '',
  shipping_country: '',
})

const handleSubmit = () => {
  form
    .transform((data) => {
      const billing = {
        street: data.billing_street || '',
        city: data.billing_city || '',
        state: data.billing_state || '',
        zip: data.billing_zip || '',
        country: data.billing_country || '',
      }
      const shipping = {
        street: data.shipping_street || '',
        city: data.shipping_city || '',
        state: data.shipping_state || '',
        zip: data.shipping_zip || '',
        country: data.shipping_country || '',
      }
      const hasBilling = Object.values(billing).some(Boolean)
      const hasShipping = Object.values(shipping).some(Boolean)

      return {
        name: data.name,
        email: data.email || null,
        phone: data.phone || null,
        base_currency: data.base_currency || props.company.base_currency,
        payment_terms: data.payment_terms ? Number(data.payment_terms) : null,
        tax_id: data.tax_id || null,
        credit_limit: data.credit_limit === '' ? null : Number(data.credit_limit),
        notes: data.notes || null,
        logo_url: data.logo_url || null,
        billing_address: hasBilling ? billing : null,
        shipping_address: hasShipping ? shipping : null,
      }
    })
    .post(`/${props.company.slug}/customers`, { preserveScroll: true })
}
</script>

<template>
  <Head title="Create Customer" />
  <PageShell
    title="Create Customer"
    :breadcrumbs="breadcrumbs"
    :icon="Users"
  >
    <form class="space-y-6" @submit.prevent="handleSubmit">
      <div class="grid gap-4 md:grid-cols-2">
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
          <Label for="base_currency">Currency</Label>
          <Select v-model="form.base_currency">
            <SelectTrigger id="base_currency">
              <SelectValue placeholder="Select currency" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="c in currencyOptions"
                :key="c.currency_code"
                :value="c.currency_code"
              >
                {{ c.currency_code }} <span v-if="c.is_base">· Base</span>
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label for="payment_terms">Payment Terms (days)</Label>
          <Input id="payment_terms" v-model="form.payment_terms" type="number" min="0" max="365" />
        </div>
        <div>
          <Label for="tax_id">Tax ID</Label>
          <Input id="tax_id" v-model="form.tax_id" />
        </div>
        <div>
          <Label for="credit_limit">Credit Limit</Label>
          <Input id="credit_limit" v-model="form.credit_limit" type="number" min="0" step="0.01" />
        </div>
        <div>
          <Label for="logo_url">Logo URL</Label>
          <Input id="logo_url" v-model="form.logo_url" />
        </div>
        <div class="md:col-span-2">
          <Label for="notes">Notes</Label>
          <Textarea id="notes" v-model="form.notes" />
        </div>
      </div>

      <div class="grid gap-6 md:grid-cols-2">
        <div class="space-y-3 rounded-md border p-4">
          <div class="font-semibold">Billing Address</div>
          <div class="space-y-2">
            <Label for="billing_street">Street</Label>
            <Input id="billing_street" v-model="form.billing_street" />
          </div>
          <div class="grid gap-2 md:grid-cols-2">
            <div>
              <Label for="billing_city">City</Label>
              <Input id="billing_city" v-model="form.billing_city" />
            </div>
            <div>
              <Label for="billing_state">State</Label>
              <Input id="billing_state" v-model="form.billing_state" />
            </div>
          </div>
          <div class="grid gap-2 md:grid-cols-2">
            <div>
              <Label for="billing_zip">ZIP</Label>
              <Input id="billing_zip" v-model="form.billing_zip" />
            </div>
            <div>
              <Label for="billing_country">Country (2-char)</Label>
              <Input id="billing_country" v-model="form.billing_country" maxlength="2" />
            </div>
          </div>
        </div>

        <div class="space-y-3 rounded-md border p-4">
          <div class="font-semibold">Shipping Address</div>
          <div class="space-y-2">
            <Label for="shipping_street">Street</Label>
            <Input id="shipping_street" v-model="form.shipping_street" />
          </div>
          <div class="grid gap-2 md:grid-cols-2">
            <div>
              <Label for="shipping_city">City</Label>
              <Input id="shipping_city" v-model="form.shipping_city" />
            </div>
            <div>
              <Label for="shipping_state">State</Label>
              <Input id="shipping_state" v-model="form.shipping_state" />
            </div>
          </div>
          <div class="grid gap-2 md:grid-cols-2">
            <div>
              <Label for="shipping_zip">ZIP</Label>
              <Input id="shipping_zip" v-model="form.shipping_zip" />
            </div>
            <div>
              <Label for="shipping_country">Country (2-char)</Label>
              <Input id="shipping_country" v-model="form.shipping_country" maxlength="2" />
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-3">
        <Button type="submit">
          <Save class="mr-2 h-4 w-4" />
          Save Customer
        </Button>
      </div>
    </form>
  </PageShell>
</template>
