<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { Fuel, Plus, Calculator, CreditCard, Banknote, Smartphone, Building2, Search } from 'lucide-vue-next'

interface FuelItem {
  id: string
  name: string
  fuel_category: string
  current_stock?: number | null
}

interface Pump {
  id: string
  name: string
  tank_id: string
  tank?: {
    linked_item?: FuelItem | null
  } | null
}

interface Customer {
  id: string
  name: string
  email?: string | null
  phone?: string | null
}

interface Rate {
  item_id: string
  sale_rate: number
  purchase_rate: number
  margin: number
}

const props = defineProps<{
  pumps: Pump[]
  fuelItems: FuelItem[]
  customers: Customer[]
  rates: Rate[]
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
  { title: 'Fuel', href: `/${companySlug.value}/fuel/dashboard` },
  { title: 'Quick Sale', href: `/${companySlug.value}/fuel/sales/form` },
])

// Sale form state
const selectedPump = ref<Pump | null>(null)
const selectedFuelItem = ref<FuelItem | null>(null)
const quantity = ref<number | null>(null)
const saleType = ref<'retail' | 'bulk' | 'amanat' | 'investor' | 'credit' | 'parco_card'>('retail')
const selectedCustomer = ref<Customer | null>(null)
const selectedInvestor = ref(null)
const discountPerLiter = ref<number | null>(null)

// Payment breakdown
const cashAmount = ref<number>(0)
const easypaisaAmount = ref<number>(0)
const jazzcashAmount = ref<number>(0)
const bankTransferAmount = ref<number>(0)
const cardSwipeAmount = ref<number>(0)
const parcoCardAmount = ref<number>(0)

// Customer search
const customerSearch = ref('')
const showCustomerDialog = ref(false)

// Form validation
const formErrors = ref<Record<string, string[]>>({})

// Computed properties
const currentRate = computed(() => {
  if (!selectedFuelItem.value) return null
  return props.rates.find(r => r.item_id === selectedFuelItem.value?.id) || null
})

const unitPrice = computed(() => {
  if (!currentRate.value) return 0
  if (saleType.value === 'investor') {
    return currentRate.value.purchase_rate // No margin for investors
  }
  return currentRate.value.sale_rate
})

const subtotal = computed(() => {
  return (quantity.value || 0) * unitPrice.value
})

const discount = computed(() => {
  if (saleType.value === 'bulk' && discountPerLiter.value && quantity.value) {
    return discountPerLiter.value * quantity.value
  }
  return 0
})

const total = computed(() => {
  return subtotal.value - discount.value
})

const totalPaid = computed(() => {
  return cashAmount.value + easypaisaAmount.value + jazzcashAmount.value +
         bankTransferAmount.value + cardSwipeAmount.value + parcoCardAmount.value
})

const balance = computed(() => {
  return total.value - totalPaid.value
})

const filteredCustomers = computed(() => {
  const q = customerSearch.value.trim().toLowerCase()
  if (!q) return props.customers.slice(0, 10) // Show first 10
  return props.customers.filter(c =>
    c.name.toLowerCase().includes(q) ||
    (c.phone ?? '').toLowerCase().includes(q)
  ).slice(0, 10)
})

// Watchers
watch(selectedPump, (newPump) => {
  if (newPump?.tank?.linked_item) {
    selectedFuelItem.value = newPump.tank.linked_item
  }
})

watch(saleType, (newType) => {
  if (newType === 'investor' || newType === 'amanat' || newType === 'credit') {
    showCustomerDialog.value = true
  } else {
    selectedCustomer.value = null
  }
})

// Methods
const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('en-PK', {
    style: 'currency',
    currency: 'PKR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

const selectCustomer = (customer: Customer) => {
  selectedCustomer.value = customer
  showCustomerDialog.value = false
  customerSearch.value = ''
}

const clearCustomer = () => {
  selectedCustomer.value = null
}

const resetForm = () => {
  selectedPump.value = null
  selectedFuelItem.value = null
  quantity.value = null
  saleType.value = 'retail'
  selectedCustomer.value = null
  discountPerLiter.value = null
  cashAmount.value = 0
  easypaisaAmount.value = 0
  jazzcashAmount.value = 0
  bankTransferAmount.value = 0
  cardSwipeAmount.value = 0
  parcoCardAmount.value = 0
  formErrors.value = {}
}

const validateForm = () => {
  const errors: Record<string, string[]> = {}

  if (!selectedPump.value) errors.pump_id = ['Please select a pump']
  if (!selectedFuelItem.value) errors.item_id = ['Please select a fuel item']
  if (!quantity.value || quantity.value <= 0) errors.quantity = ['Please enter a valid quantity']
  if (saleType.value === 'bulk' && (!discountPerLiter.value || discountPerLiter.value < 0)) {
    errors.discount_per_liter = ['Please enter a valid discount per liter']
  }
  if (totalPaid.value > total.value) {
    errors.payment_total = ['Total payment cannot exceed sale amount']
  }

  formErrors.value = errors
  return Object.keys(errors).length === 0
}

const submitSale = () => {
  if (!validateForm()) return

  const slug = companySlug.value
  if (!slug) return

  const formData = {
    pump_id: selectedPump.value!.id,
    item_id: selectedFuelItem.value!.id,
    quantity: quantity.value!,
    sale_type: saleType.value,
    customer_id: selectedCustomer.value?.id || null,
    investor_id: selectedInvestor.value?.id || null,
    discount_per_liter: discountPerLiter.value || null,
    payment_breakdown: {
      cash: cashAmount.value,
      easypaisa: easypaisaAmount.value,
      jazzcash: jazzcashAmount.value,
      bank_transfer: bankTransferAmount.value,
      card_swipe: cardSwipeAmount.value,
      parco_card: parcoCardAmount.value,
    },
    description: `${quantity.value}L ${selectedFuelItem.value!.name} - ${saleType.value}`,
  }

  router.post(`/${slug}/fuel/sales`, formData, {
    preserveScroll: true,
    onSuccess: () => {
      resetForm()
      // Could show success toast here
    },
    onError: (errors) => {
      formErrors.value = errors
    },
  })
}

const setPaymentTotal = () => {
  cashAmount.value = total.value
  easypaisaAmount.value = 0
  jazzcashAmount.value = 0
  bankTransferAmount.value = 0
  cardSwipeAmount.value = 0
  parcoCardAmount.value = 0
}
</script>

<template>
  <Head title="Quick Fuel Sale" />

  <PageShell
    title="Quick Fuel Sale"
    description="Fast fuel sale entry with POS-style interface"
    :icon="Fuel"
    :breadcrumbs="breadcrumbs"
  >
    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Left Column - Sale Details -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Pump & Fuel Selection -->
        <Card class="border-border/80">
          <CardHeader>
            <CardTitle class="text-base flex items-center gap-2">
              <Fuel class="h-5 w-5 text-blue-600" />
              Pump & Fuel Selection
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label>Pump *</Label>
                <Select v-model="selectedPump">
                  <SelectTrigger :class="{ 'border-destructive': formErrors.pump_id }">
                    <SelectValue placeholder="Select pump..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="pump in pumps" :key="pump.id" :value="pump">
                      {{ pump.name }} • {{ pump.tank?.linked_item?.name || 'No fuel' }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="formErrors.pump_id" class="text-sm text-destructive">{{ formErrors.pump_id[0] }}</p>
              </div>

              <div class="space-y-2">
                <Label>Fuel Item *</Label>
                <Select v-model="selectedFuelItem">
                  <SelectTrigger :class="{ 'border-destructive': formErrors.item_id }">
                    <SelectValue placeholder="Select fuel..." />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="item in fuelItems" :key="item.id" :value="item">
                      {{ item.name }} ({{ item.fuel_category }})
                      <span v-if="item.current_stock" class="text-sm text-text-secondary ml-2">
                        • {{ item.current_stock }}L available
                      </span>
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="formErrors.item_id" class="text-sm text-destructive">{{ formErrors.item_id[0] }}</p>
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
              <div class="space-y-2">
                <Label>Quantity (Liters) *</Label>
                <Input
                  v-model.number="quantity"
                  type="number"
                  min="0.1"
                  step="0.1"
                  placeholder="0.00"
                  :class="{ 'border-destructive': formErrors.quantity }"
                />
                <p v-if="formErrors.quantity" class="text-sm text-destructive">{{ formErrors.quantity[0] }}</p>
              </div>

              <div class="space-y-2">
                <Label>Unit Price</Label>
                <Input
                  :model-value="formatCurrency(unitPrice)"
                  readonly
                  class="bg-muted"
                />
              </div>

              <div class="space-y-2">
                <Label>Sale Type</Label>
                <Select v-model="saleType">
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="retail">Retail</SelectItem>
                    <SelectItem value="bulk">Bulk</SelectItem>
                    <SelectItem value="credit">Credit</SelectItem>
                    <SelectItem value="amanat">Amanat</SelectItem>
                    <SelectItem value="investor">Investor</SelectItem>
                    <SelectItem value="parco_card">Parco Card</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <!-- Customer Selection for special types -->
            <div v-if="['credit', 'amanat', 'investor'].includes(saleType)" class="space-y-2">
              <Label>Customer *</Label>
              <div v-if="selectedCustomer" class="flex items-center gap-3 p-3 rounded-lg border border-border/70 bg-muted/30">
                <div class="flex-1">
                  <p class="font-medium">{{ selectedCustomer.name }}</p>
                  <p class="text-sm text-text-secondary">{{ selectedCustomer.phone }}</p>
                </div>
                <Button variant="outline" size="sm" @click="showCustomerDialog = true">
                  Change
                </Button>
              </div>
              <div v-else class="flex items-center gap-3 p-3 rounded-lg border border-amber-200 bg-amber-50">
                <Building2 class="h-5 w-5 text-amber-600" />
                <div class="flex-1">
                  <p class="font-medium text-amber-800">No customer selected</p>
                  <p class="text-sm text-amber-700">Required for {{ saleType }} sales</p>
                </div>
                <Button size="sm" class="border-amber-300 text-amber-700 hover:bg-amber-100" @click="showCustomerDialog = true">
                  Select Customer
                </Button>
              </div>
            </div>

            <!-- Bulk discount -->
            <div v-if="saleType === 'bulk'" class="space-y-2">
              <Label>Discount per Liter</Label>
              <Input
                v-model.number="discountPerLiter"
                type="number"
                min="0"
                step="0.01"
                placeholder="0.00"
                :class="{ 'border-destructive': formErrors.discount_per_liter }"
              />
              <p v-if="formErrors.discount_per_liter" class="text-sm text-destructive">{{ formErrors.discount_per_liter[0] }}</p>
            </div>
          </CardContent>
        </Card>

        <!-- Payment Breakdown -->
        <Card class="border-border/80">
          <CardHeader>
            <CardTitle class="text-base flex items-center gap-2">
              <CreditCard class="h-5 w-5 text-green-600" />
              Payment Breakdown
            </CardTitle>
            <CardDescription>How was this sale paid?</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <div class="space-y-1">
                <Label class="text-xs flex items-center gap-1">
                  <Banknote class="h-3 w-3" />
                  Cash
                </Label>
                <Input v-model.number="cashAmount" type="number" min="0" step="1" placeholder="0" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs flex items-center gap-1">
                  <Smartphone class="h-3 w-3" />
                  EasyPaisa
                </Label>
                <Input v-model.number="easypaisaAmount" type="number" min="0" step="1" placeholder="0" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs flex items-center gap-1">
                  <Smartphone class="h-3 w-3" />
                  JazzCash
                </Label>
                <Input v-model.number="jazzcashAmount" type="number" min="0" step="1" placeholder="0" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs flex items-center gap-1">
                  <Banknote class="h-3 w-3" />
                  Bank Transfer
                </Label>
                <Input v-model.number="bankTransferAmount" type="number" min="0" step="1" placeholder="0" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs flex items-center gap-1">
                  <CreditCard class="h-3 w-3" />
                  Card Swipe
                </Label>
                <Input v-model.number="cardSwipeAmount" type="number" min="0" step="1" placeholder="0" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs flex items-center gap-1">
                  <CreditCard class="h-3 w-3" />
                  Parco Card
                </Label>
                <Input v-model.number="parcoCardAmount" type="number" min="0" step="1" placeholder="0" />
              </div>
            </div>

            <div class="pt-4 border-t border-border/50 space-y-2">
              <Button variant="outline" size="sm" @click="setPaymentTotal">
                Set Full Cash Payment
              </Button>
              <div class="flex justify-between items-center">
                <span class="text-lg font-medium">Total Paid</span>
                <span class="text-xl font-bold" :class="totalPaid >= total ? 'text-green-600' : 'text-amber-600'">
                  {{ formatCurrency(totalPaid) }}
                </span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-text-secondary">Balance</span>
                <span class="text-sm font-medium" :class="balance <= 0 ? 'text-green-600' : 'text-red-600'">
                  {{ formatCurrency(balance) }}
                </span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Right Column - Summary & Actions -->
      <div class="space-y-6">
        <!-- Sale Summary -->
        <Card class="border-border/80">
          <CardHeader>
            <CardTitle class="text-base">Sale Summary</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="flex justify-between">
              <span>Subtotal</span>
              <span>{{ formatCurrency(subtotal) }}</span>
            </div>
            <div v-if="discount > 0" class="flex justify-between text-green-600">
              <span>Discount</span>
              <span>-{{ formatCurrency(discount) }}</span>
            </div>
            <div class="flex justify-between text-lg font-semibold pt-2 border-t border-border/50">
              <span>Total</span>
              <span>{{ formatCurrency(total) }}</span>
            </div>

            <div v-if="selectedFuelItem && currentRate" class="pt-4 space-y-2 text-sm text-text-secondary">
              <div class="flex justify-between">
                <span>Rate</span>
                <span>{{ formatCurrency(currentRate.sale_rate) }}/L</span>
              </div>
              <div v-if="saleType === 'investor'" class="flex justify-between">
                <span>Investor Rate</span>
                <span>{{ formatCurrency(currentRate.purchase_rate) }}/L</span>
              </div>
              <div class="flex justify-between">
                <span>Margin</span>
                <span>{{ formatCurrency(currentRate.margin) }}/L</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Action Buttons -->
        <Card class="border-border/80">
          <CardContent class="pt-6">
            <div class="space-y-3">
              <Button
                class="w-full bg-blue-600 hover:bg-blue-700"
                size="lg"
                :disabled="!selectedPump || !selectedFuelItem || !quantity || totalPaid !== total"
                @click="submitSale"
              >
                <Calculator class="mr-2 h-5 w-5" />
                Complete Sale
              </Button>
              <Button variant="outline" class="w-full" @click="resetForm">
                Reset Form
              </Button>
            </div>

            <div v-if="Object.keys(formErrors).length > 0" class="mt-4 p-3 rounded-lg border border-red-200 bg-red-50">
              <p class="text-sm font-medium text-red-800 mb-2">Please fix the following errors:</p>
              <ul class="text-sm text-red-700 space-y-1">
                <li v-for="(messages, field) in formErrors" :key="field">
                  {{ messages[0] }}
                </li>
              </ul>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <!-- Customer Selection Dialog -->
    <Dialog :open="showCustomerDialog" @update:open="(v) => showCustomerDialog = v">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Select Customer</DialogTitle>
          <DialogDescription>
            Search and select a customer for this {{ saleType }} sale.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4">
          <div class="space-y-2">
            <Label>Search Customers</Label>
            <div class="relative">
              <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-tertiary" />
              <Input v-model="customerSearch" placeholder="Name or phone..." class="pl-9" />
            </div>
          </div>

          <div class="max-h-64 overflow-y-auto space-y-2">
            <div
              v-for="customer in filteredCustomers"
              :key="customer.id"
              class="flex items-center justify-between p-3 rounded-lg border border-border/70 hover:bg-muted/50 cursor-pointer"
              @click="selectCustomer(customer)"
            >
              <div>
                <p class="font-medium">{{ customer.name }}</p>
                <p class="text-sm text-text-secondary">{{ customer.phone }}</p>
              </div>
              <Button size="sm">Select</Button>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="showCustomerDialog = false">
            Cancel
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>