<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import type { BreadcrumbItem } from '@/types'
import { Receipt, ArrowLeft, Banknote, Building2, ArrowRightLeft, FileText } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface Customer {
  id: string
  name: string
  code: string | null
  current_balance: number
}

const props = defineProps<{
  customers: Customer[]
  selectedCustomerId: string | null
  currency: string
}>()

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Collections', href: `/${companySlug.value}/fuel/collections` },
  { title: 'New Collection', href: `/${companySlug.value}/fuel/collections/create` },
])

const currency = computed(() => currencySymbol(props.currency))

const form = useForm({
  customer_id: props.selectedCustomerId || '',
  amount: '',
  payment_method: 'cash',
  reference: '',
  notes: '',
  collection_date: new Date().toISOString().split('T')[0],
})

const selectedCustomer = computed(() => {
  return props.customers.find(c => c.id === form.customer_id)
})

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

const submit = () => {
  form.post(`/${companySlug.value}/fuel/collections`, {
    preserveScroll: true,
  })
}

const goBack = () => {
  router.get(`/${companySlug.value}/fuel/collections`)
}

const collectFull = () => {
  if (selectedCustomer.value) {
    form.amount = selectedCustomer.value.current_balance.toString()
  }
}
</script>

<template>
  <Head title="New Collection" />

  <PageShell
    title="New Collection"
    description="Record a payment from a credit customer."
    :icon="Receipt"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="goBack">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <div class="mx-auto max-w-2xl">
      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-lg">Collection Details</CardTitle>
          <CardDescription>Enter the payment details received from the customer.</CardDescription>
        </CardHeader>

        <CardContent>
          <form @submit.prevent="submit" class="space-y-6">
            <!-- Customer Selection -->
            <div class="space-y-2">
              <Label for="customer">Customer *</Label>
              <Select v-model="form.customer_id">
                <SelectTrigger :class="{ 'border-destructive': form.errors.customer_id }">
                  <SelectValue placeholder="Select customer" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="c in customers" :key="c.id" :value="c.id">
                    <div class="flex items-center justify-between gap-4">
                      <span>{{ c.name }}</span>
                      <span class="text-muted-foreground">{{ currency }} {{ formatCurrency(c.current_balance) }}</span>
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.customer_id" class="text-sm text-destructive">{{ form.errors.customer_id }}</p>

              <!-- Show customer balance -->
              <div v-if="selectedCustomer" class="rounded-md bg-amber-50 p-3 text-sm">
                <div class="flex items-center justify-between">
                  <span class="text-amber-800">Outstanding Balance:</span>
                  <span class="font-semibold text-amber-900">{{ currency }} {{ formatCurrency(selectedCustomer.current_balance) }}</span>
                </div>
                <Button
                  v-if="selectedCustomer.current_balance > 0"
                  type="button"
                  variant="link"
                  size="sm"
                  class="mt-1 h-auto p-0 text-amber-700"
                  @click="collectFull"
                >
                  Collect full amount
                </Button>
              </div>
            </div>

            <!-- Amount -->
            <div class="space-y-2">
              <Label for="amount">Amount *</Label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">{{ currency }}</span>
                <Input
                  id="amount"
                  v-model="form.amount"
                  type="number"
                  min="0.01"
                  step="0.01"
                  class="pl-14"
                  :class="{ 'border-destructive': form.errors.amount }"
                  placeholder="0.00"
                />
              </div>
              <p v-if="form.errors.amount" class="text-sm text-destructive">{{ form.errors.amount }}</p>
            </div>

            <!-- Collection Date -->
            <div class="space-y-2">
              <Label for="collection_date">Collection Date *</Label>
              <Input
                id="collection_date"
                v-model="form.collection_date"
                type="date"
                :class="{ 'border-destructive': form.errors.collection_date }"
              />
              <p v-if="form.errors.collection_date" class="text-sm text-destructive">{{ form.errors.collection_date }}</p>
            </div>

            <!-- Payment Method -->
            <div class="space-y-3">
              <Label>Payment Method *</Label>
              <RadioGroup v-model="form.payment_method" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                  <RadioGroupItem value="cash" id="method-cash" class="peer sr-only" />
                  <Label
                    for="method-cash"
                    class="flex cursor-pointer flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary [&:has([data-state=checked])]:border-primary"
                  >
                    <Banknote class="mb-2 h-6 w-6" />
                    Cash
                  </Label>
                </div>
                <div>
                  <RadioGroupItem value="bank" id="method-bank" class="peer sr-only" />
                  <Label
                    for="method-bank"
                    class="flex cursor-pointer flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary [&:has([data-state=checked])]:border-primary"
                  >
                    <Building2 class="mb-2 h-6 w-6" />
                    Bank
                  </Label>
                </div>
                <div>
                  <RadioGroupItem value="transfer" id="method-transfer" class="peer sr-only" />
                  <Label
                    for="method-transfer"
                    class="flex cursor-pointer flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary [&:has([data-state=checked])]:border-primary"
                  >
                    <ArrowRightLeft class="mb-2 h-6 w-6" />
                    Transfer
                  </Label>
                </div>
                <div>
                  <RadioGroupItem value="cheque" id="method-cheque" class="peer sr-only" />
                  <Label
                    for="method-cheque"
                    class="flex cursor-pointer flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary [&:has([data-state=checked])]:border-primary"
                  >
                    <FileText class="mb-2 h-6 w-6" />
                    Cheque
                  </Label>
                </div>
              </RadioGroup>
              <p v-if="form.errors.payment_method" class="text-sm text-destructive">{{ form.errors.payment_method }}</p>
            </div>

            <!-- Reference -->
            <div class="space-y-2">
              <Label for="reference">Reference Number</Label>
              <Input
                id="reference"
                v-model="form.reference"
                placeholder="Receipt or cheque number"
                :class="{ 'border-destructive': form.errors.reference }"
              />
              <p v-if="form.errors.reference" class="text-sm text-destructive">{{ form.errors.reference }}</p>
            </div>

            <!-- Notes -->
            <div class="space-y-2">
              <Label for="notes">Notes</Label>
              <Textarea
                id="notes"
                v-model="form.notes"
                placeholder="Additional notes about this collection"
                rows="3"
                :class="{ 'border-destructive': form.errors.notes }"
              />
              <p v-if="form.errors.notes" class="text-sm text-destructive">{{ form.errors.notes }}</p>
            </div>

            <!-- Submit -->
            <div class="flex justify-end gap-3 pt-4">
              <Button type="button" variant="outline" @click="goBack" :disabled="form.processing">
                Cancel
              </Button>
              <Button type="submit" :disabled="form.processing">
                <span v-if="form.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                Record Collection
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
