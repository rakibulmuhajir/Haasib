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

interface AccountOption {
  id: string
  code: string
  name: string
}

interface CustomerRef {
  id: string
  customer_number: string
  name: string
  email: string | null
  phone: string | null
  base_currency: string | null
  payment_terms: number | null
  tax_id: string | null
  credit_limit: number | null
  notes: string | null
  logo_url: string | null
  billing_address?: {
    street?: string | null
    city?: string | null
    state?: string | null
    zip?: string | null
    country?: string | null
  } | null
  shipping_address?: {
    street?: string | null
    city?: string | null
    state?: string | null
    zip?: string | null
    country?: string | null
  } | null
  is_active: boolean
}

const props = defineProps<{
  company: CompanyRef
  customer: CustomerRef
  currencies: CurrencyOption[]
  arAccounts?: AccountOption[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Customers', href: `/${props.company.slug}/customers` },
  { title: props.customer.name, href: `/${props.company.slug}/customers/${props.customer.id}` },
  { title: 'Edit', href: `/${props.company.slug}/customers/${props.customer.id}/edit` },
]

const currencyOptions = computed(() =>
  props.currencies.length ? props.currencies : [{ currency_code: props.company.base_currency, is_base: true }]
)

const form = useForm({
  name: props.customer.name ?? '',
  email: props.customer.email ?? '',
  phone: props.customer.phone ?? '',
  base_currency: props.customer.base_currency ?? props.company.base_currency,
  payment_terms: props.customer.payment_terms ?? '',
  tax_id: props.customer.tax_id ?? '',
  credit_limit: props.customer.credit_limit ?? '',
  notes: props.customer.notes ?? '',
  logo_url: props.customer.logo_url ?? '',
  ar_account_id: (props.customer as any).ar_account_id ?? '',
  billing_street: props.customer.billing_address?.street ?? '',
  billing_city: props.customer.billing_address?.city ?? '',
  billing_state: props.customer.billing_address?.state ?? '',
  billing_zip: props.customer.billing_address?.zip ?? '',
  billing_country: props.customer.billing_address?.country ?? '',
  shipping_street: props.customer.shipping_address?.street ?? '',
  shipping_city: props.customer.shipping_address?.city ?? '',
  shipping_state: props.customer.shipping_address?.state ?? '',
  shipping_zip: props.customer.shipping_address?.zip ?? '',
  shipping_country: props.customer.shipping_address?.country ?? '',
  is_active: props.customer.is_active,
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
        is_active: !!data.is_active,
      }
    })
    .put(`/${props.company.slug}/customers/${props.customer.id}`, { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Edit Customer ${customer.customer_number}`" />
  <PageShell
    :title="`Edit ${customer.customer_number}`"
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
          <Label for="ar_account_id">AR Account</Label>
          <Select v-model="form.ar_account_id">
            <SelectTrigger id="ar_account_id">
              <SelectValue placeholder="Select AR account" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none">Use company default</SelectItem>
              <SelectItem
                v-for="acct in props.arAccounts || []"
                :key="acct.id"
                :value="acct.id"
              >
                {{ acct.code }} — {{ acct.name }}
              </SelectItem>
            </SelectContent>
          </Select>
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
        <div>
          <Label for="is_active">Status</Label>
          <Select v-model="form.is_active">
            <SelectTrigger id="is_active">
              <SelectValue placeholder="Status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="true">Active</SelectItem>
              <SelectItem :value="false">Inactive</SelectItem>
            </SelectContent>
          </Select>
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
          Save Changes
        </Button>
      </div>
    </form>
  </PageShell>
</template>
