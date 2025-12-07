<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { UserPlus, ArrowLeft, Save } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

const props = defineProps<{
  company: CompanyRef
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Home', href: '/dashboard' },
  { title: 'Companies', href: '/companies' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: 'Customers', href: `/${props.company.slug}/customers` },
  { title: 'Create' },
])

const form = useForm({
  name: '',
  email: '',
  phone: '',
  base_currency: props.company.base_currency,
  payment_terms: 30,
  tax_id: '',
  credit_limit: null as number | null,
  notes: '',
  billing_address: {
    street: '',
    city: '',
    state: '',
    zip: '',
    country: '',
  },
  shipping_address: {
    street: '',
    city: '',
    state: '',
    zip: '',
    country: '',
  },
})

const handleSubmit = () => {
  form.post(`/${props.company.slug}/customers`)
}

const handleCancel = () => {
  router.visit(`/${props.company.slug}/customers`)
}
</script>

<template>
  <Head :title="`Create Customer - ${company.name}`" />
  <PageShell
    title="Create Customer"
    :icon="UserPlus"
    :breadcrumbs="breadcrumbs"
    :back-button="{
      label: 'Back to Customers',
      onClick: handleCancel,
      icon: ArrowLeft,
    }"
  >
    <template #description>
      Add a new customer to <span class="font-medium text-slate-300">{{ company.name }}</span>
    </template>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Basic Information -->
      <Card>
        <CardHeader>
          <CardTitle>Basic Information</CardTitle>
          <CardDescription>Customer's primary details</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="name">Name <span class="text-red-500">*</span></Label>
              <Input
                id="name"
                v-model="form.name"
                placeholder="Customer name"
                :class="{ 'border-red-500': form.errors.name }"
              />
              <p v-if="form.errors.name" class="text-xs text-red-400">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="email">Email</Label>
              <Input
                id="email"
                v-model="form.email"
                type="email"
                placeholder="customer@example.com"
                :class="{ 'border-red-500': form.errors.email }"
              />
              <p v-if="form.errors.email" class="text-xs text-red-400">{{ form.errors.email }}</p>
            </div>

            <div class="space-y-2">
              <Label for="phone">Phone</Label>
              <Input
                id="phone"
                v-model="form.phone"
                placeholder="+1 (555) 000-0000"
                :class="{ 'border-red-500': form.errors.phone }"
              />
              <p v-if="form.errors.phone" class="text-xs text-red-400">{{ form.errors.phone }}</p>
            </div>

            <div class="space-y-2">
              <Label for="tax_id">Tax ID</Label>
              <Input
                id="tax_id"
                v-model="form.tax_id"
                placeholder="Tax identification number"
                :class="{ 'border-red-500': form.errors.tax_id }"
              />
              <p v-if="form.errors.tax_id" class="text-xs text-red-400">{{ form.errors.tax_id }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Financial Settings -->
      <Card>
        <CardHeader>
          <CardTitle>Financial Settings</CardTitle>
          <CardDescription>Payment terms and credit settings</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-2">
              <Label for="base_currency">Currency</Label>
              <Input
                id="base_currency"
                v-model="form.base_currency"
                placeholder="USD"
                maxlength="3"
                class="uppercase"
                :class="{ 'border-red-500': form.errors.base_currency }"
              />
              <p v-if="form.errors.base_currency" class="text-xs text-red-400">{{ form.errors.base_currency }}</p>
            </div>

            <div class="space-y-2">
              <Label for="payment_terms">Payment Terms (days)</Label>
              <Input
                id="payment_terms"
                v-model.number="form.payment_terms"
                type="number"
                min="0"
                max="365"
                :class="{ 'border-red-500': form.errors.payment_terms }"
              />
              <p v-if="form.errors.payment_terms" class="text-xs text-red-400">{{ form.errors.payment_terms }}</p>
            </div>

            <div class="space-y-2">
              <Label for="credit_limit">Credit Limit</Label>
              <Input
                id="credit_limit"
                v-model.number="form.credit_limit"
                type="number"
                min="0"
                step="0.01"
                placeholder="No limit"
                :class="{ 'border-red-500': form.errors.credit_limit }"
              />
              <p v-if="form.errors.credit_limit" class="text-xs text-red-400">{{ form.errors.credit_limit }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Billing Address -->
      <Card>
        <CardHeader>
          <CardTitle>Billing Address</CardTitle>
          <CardDescription>Where invoices should be sent</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label for="billing_street">Street</Label>
            <Input
              id="billing_street"
              v-model="form.billing_address.street"
              placeholder="123 Main Street"
            />
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="space-y-2">
              <Label for="billing_city">City</Label>
              <Input id="billing_city" v-model="form.billing_address.city" placeholder="City" />
            </div>
            <div class="space-y-2">
              <Label for="billing_state">State</Label>
              <Input id="billing_state" v-model="form.billing_address.state" placeholder="State" />
            </div>
            <div class="space-y-2">
              <Label for="billing_zip">ZIP Code</Label>
              <Input id="billing_zip" v-model="form.billing_address.zip" placeholder="12345" />
            </div>
            <div class="space-y-2">
              <Label for="billing_country">Country</Label>
              <Input id="billing_country" v-model="form.billing_address.country" placeholder="US" maxlength="2" class="uppercase" />
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Shipping Address -->
      <Card>
        <CardHeader>
          <CardTitle>Shipping Address</CardTitle>
          <CardDescription>Where goods should be delivered (if different)</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label for="shipping_street">Street</Label>
            <Input
              id="shipping_street"
              v-model="form.shipping_address.street"
              placeholder="123 Main Street"
            />
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="space-y-2">
              <Label for="shipping_city">City</Label>
              <Input id="shipping_city" v-model="form.shipping_address.city" placeholder="City" />
            </div>
            <div class="space-y-2">
              <Label for="shipping_state">State</Label>
              <Input id="shipping_state" v-model="form.shipping_address.state" placeholder="State" />
            </div>
            <div class="space-y-2">
              <Label for="shipping_zip">ZIP Code</Label>
              <Input id="shipping_zip" v-model="form.shipping_address.zip" placeholder="12345" />
            </div>
            <div class="space-y-2">
              <Label for="shipping_country">Country</Label>
              <Input id="shipping_country" v-model="form.shipping_address.country" placeholder="US" maxlength="2" class="uppercase" />
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Notes -->
      <Card>
        <CardHeader>
          <CardTitle>Notes</CardTitle>
          <CardDescription>Additional information about this customer</CardDescription>
        </CardHeader>
        <CardContent>
          <Textarea
            v-model="form.notes"
            placeholder="Internal notes about this customer..."
            rows="3"
          />
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button type="button" variant="outline" @click="handleCancel" :disabled="form.processing">
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing">
          <span
            v-if="form.processing"
            class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
          />
          <Save v-else class="mr-2 h-4 w-4" />
          Create Customer
        </Button>
      </div>
    </form>
  </PageShell>
</template>
