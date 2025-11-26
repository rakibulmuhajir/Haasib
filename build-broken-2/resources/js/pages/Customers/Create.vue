<script setup lang="ts">
import UniversalLayout from '@/layouts/UniversalLayout.vue'
import { Head } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useForm } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'

const breadcrumbs = [
  { label: 'Dashboard', href: '/dashboard' },
  { label: 'Accounting', href: '/dashboard/accounting' },
  { label: 'Customers', href: '/accounting/customers' },
  { label: 'Create Customer', active: true },
]

interface Currency {
  code: string
  name: string
  symbol: string
  display_name: string
  is_base?: boolean
}

const props = defineProps<{
  currencies: Currency[]
  baseCurrency?: {
    code: string
    name: string
    symbol: string
  }
}>()

const form = useForm({
  name: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  country: '',
  preferred_currency_code: props.baseCurrency?.code || '',
  credit_limit: '',
  notes: '',
})

const submit = () => {
  form.post('/accounting/customers', {
    onSuccess: () => {
      // Optional: Show success message or redirect
    },
  })
}

const cancel = () => {
  // Navigate back to customers list
  window.location.href = '/accounting/customers'
}
</script>

<template>
  <Head title="Create Customer" />
  <UniversalLayout
    title="Create Customer"
    subtitle="Add a new customer to your accounting system"
    :breadcrumbs="breadcrumbs"
  >
    <div class="p-6">
      <div class="mx-auto max-w-2xl">
        <Card>
          <CardHeader>
            <CardTitle>New Customer Information</CardTitle>
            <CardDescription>
              Enter the customer's details below. Fields marked with * are required.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form @submit.prevent="submit" class="space-y-6">
              <!-- Customer Information -->
              <div class="space-y-4">
                <h3 class="text-lg font-medium">Basic Information</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <Label for="name">Customer Name *</Label>
                    <Input
                      id="name"
                      v-model="form.name"
                      type="text"
                      placeholder="Enter customer name"
                      required
                      :class="{ 'border-red-500': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
                  </div>
                  
                  <div class="space-y-2">
                    <Label for="email">Email Address</Label>
                    <Input
                      id="email"
                      v-model="form.email"
                      type="email"
                      placeholder="customer@example.com"
                      :class="{ 'border-red-500': form.errors.email }"
                    />
                    <p v-if="form.errors.email" class="text-sm text-red-500">{{ form.errors.email }}</p>
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <Label for="phone">Phone Number</Label>
                    <Input
                      id="phone"
                      v-model="form.phone"
                      type="tel"
                      placeholder="+1 (555) 123-4567"
                      :class="{ 'border-red-500': form.errors.phone }"
                    />
                    <p v-if="form.errors.phone" class="text-sm text-red-500">{{ form.errors.phone }}</p>
                  </div>
                  
                  <div class="space-y-2">
                    <Label for="preferred_currency">Preferred Currency</Label>
                    <Input
                      id="preferred_currency"
                      v-model="form.preferred_currency_code"
                      type="text"
                      placeholder="USD"
                      :class="{ 'border-red-500': form.errors.preferred_currency_code }"
                    />
                    <p v-if="form.errors.preferred_currency_code" class="text-sm text-red-500">{{ form.errors.preferred_currency_code }}</p>
                    <p class="text-sm text-muted-foreground">This will be the default currency for invoices</p>
                  </div>
                </div>
              </div>

              <!-- Address Information -->
              <div class="space-y-4">
                <h3 class="text-lg font-medium">Address Information</h3>
                
                <div class="space-y-2">
                  <Label for="address">Street Address</Label>
                  <Input
                    id="address"
                    v-model="form.address"
                    type="text"
                    placeholder="123 Main Street, Suite 100"
                    :class="{ 'border-red-500': form.errors.address }"
                  />
                  <p v-if="form.errors.address" class="text-sm text-red-500">{{ form.errors.address }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <Label for="city">City</Label>
                    <Input
                      id="city"
                      v-model="form.city"
                      type="text"
                      placeholder="New York"
                      :class="{ 'border-red-500': form.errors.city }"
                    />
                    <p v-if="form.errors.city" class="text-sm text-red-500">{{ form.errors.city }}</p>
                  </div>
                  
                  <div class="space-y-2">
                    <Label for="country">Country</Label>
                    <Input
                      id="country"
                      v-model="form.country"
                      type="text"
                      placeholder="United States"
                      :class="{ 'border-red-500': form.errors.country }"
                    />
                    <p v-if="form.errors.country" class="text-sm text-red-500">{{ form.errors.country }}</p>
                  </div>
                </div>
              </div>

              <!-- Financial Information -->
              <div class="space-y-4">
                <h3 class="text-lg font-medium">Financial Information</h3>
                
                <div class="space-y-2">
                  <Label for="credit_limit">Credit Limit</Label>
                  <Input
                    id="credit_limit"
                    v-model="form.credit_limit"
                    type="number"
                    step="0.01"
                    placeholder="10000.00"
                    :class="{ 'border-red-500': form.errors.credit_limit }"
                  />
                  <p v-if="form.errors.credit_limit" class="text-sm text-red-500">{{ form.errors.credit_limit }}</p>
                  <p class="text-sm text-muted-foreground">Maximum credit amount for this customer (leave blank for unlimited)</p>
                </div>

                <div class="space-y-2">
                  <Label for="notes">Notes</Label>
                  <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    placeholder="Additional notes about this customer..."
                    :class="{ 'border-red-500': form.errors.notes }"
                  ></textarea>
                  <p v-if="form.errors.notes" class="text-sm text-red-500">{{ form.errors.notes }}</p>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="flex justify-end gap-3 pt-6 border-t">
                <Button 
                  type="button" 
                  variant="outline" 
                  @click="cancel"
                  :disabled="form.processing"
                >
                  Cancel
                </Button>
                <Link href="/accounting/customers">
                  <Button type="button" variant="ghost">
                    Back to Customers
                  </Button>
                </Link>
                <Button 
                  type="submit" 
                  :disabled="form.processing || !form.name.trim()"
                >
                  {{ form.processing ? 'Creating...' : 'Create Customer' }}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  </UniversalLayout>
</template>
