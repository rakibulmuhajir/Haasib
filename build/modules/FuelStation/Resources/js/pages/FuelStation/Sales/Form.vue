<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { entryDateDefault, rememberEntryDate } from '@/composables/useEntryDate'
import EntryDateNote from '@/components/EntryDateNote.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
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
import InputError from '@/components/InputError.vue'
import { Checkbox } from '@/components/ui/checkbox'
import type { BreadcrumbItem } from '@/types'
import { Fuel, Plus, Calculator, CreditCard, Banknote, Smartphone, Building2, Search } from 'lucide-vue-next'
import { formatMoneyText } from '@/lib/money'
import MoneyText from '@/components/MoneyText.vue'
import { useFuelSaleCanSubmit } from '../../../composables/useFuelSaleSubmitState'

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

interface FuelDiscount {
  discount_type: 'percent' | 'per_litre'
  value: number
}

const props = defineProps<{
  pumps: Pump[]
  fuelItems: FuelItem[]
  customers: Customer[]
  rates: Rate[]
  customerFuelDiscounts: Record<string, Record<string, FuelDiscount>>
}>()

const page = usePage()
const { companySlug } = useCompanyRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/dashboard` },
  { title: 'Quick Sale', href: `/${companySlug.value}/fuel/sales/form` },
])

const currencyCode = computed(() => ((page.props as any)?.auth?.currentCompany?.base_currency as string) || 'PKR')

// Sale form state
const selectedPump = ref<Pump | null>(null)
const selectedFuelItem = ref<FuelItem | null>(null)
const quantity = ref<number | null>(null)
// The business date this fuel left the pump. A credit sale is imported into the Daily
// Close for its own date, so backdating yesterday's sale has to be possible.
const saleDate = ref<string>(entryDateDefault(companySlug.value))
const saleType = ref<'retail' | 'bulk' | 'amanat' | 'investor' | 'credit' | 'parco_card'>('retail')
const selectedCustomer = ref<Customer | null>(null)
const selectedInvestor = ref(null)
const discountPerLiter = ref<number | null>(null)
const discountPercent = ref<number | null>(null)
// True once the operator has typed into a discount field themselves, so a customer's
// stored discount only prefills the field, it never overwrites a manual edit.
const discountTouched = ref(false)

// Direct from the supplier's tanker to a customer: no pump, no tank. It posts its own income
// as a direct-delivery invoice (FuelSaleController::storeDirect), so it takes a customer, a
// rate that can differ from the pump rate, and whether it was paid in cash or is on credit.
const isDirect = ref(false)
const directRate = ref<number | null>(null)
const directPaidInCash = ref(true)
const directTotal = computed(() => Math.round((quantity.value || 0) * (directRate.value || 0) * 100) / 100)
const canSubmitDirect = computed(() =>
  !!selectedFuelItem.value && (quantity.value || 0) > 0 && (directRate.value || 0) > 0 && !!saleDate.value
    && (directPaidInCash.value || !!selectedCustomer.value),
)

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
  if (saleType.value === 'investor') return 0
  if (discountPerLiter.value && quantity.value) {
    return Math.min(discountPerLiter.value * quantity.value, subtotal.value)
  }
  if (discountPercent.value && subtotal.value) {
    return Math.round((subtotal.value * discountPercent.value / 100) * 100) / 100
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

// The day's pump rate is only a starting point for a direct sale.
watch([isDirect, selectedFuelItem], ([direct]) => {
  if (direct) directRate.value = currentRate.value?.sale_rate ?? null
})
watch(isDirect, (direct) => {
  formErrors.value = {}
  if (direct) {
    selectedPump.value = null
  }
})

watch(saleType, (newType) => {
  if (newType === 'investor' || newType === 'amanat' || newType === 'credit') {
    showCustomerDialog.value = true
  } else if (!isDirect.value) {
    selectedCustomer.value = null
  }
})

// Prefill this buyer's stored discount for the chosen fuel item -- editable per sale, so a
// manual edit (discountTouched) is never overwritten.
watch([selectedCustomer, selectedFuelItem], ([customer, item]) => {
  if (discountTouched.value || !customer || !item) return
  const stored = props.customerFuelDiscounts?.[customer.id]?.[item.id]
  if (stored?.discount_type === 'per_litre') {
    discountPerLiter.value = stored.value
    discountPercent.value = null
  } else if (stored?.discount_type === 'percent') {
    discountPercent.value = stored.value
    discountPerLiter.value = null
  } else {
    discountPerLiter.value = null
    discountPercent.value = null
  }
})

// Methods
const formatCurrency = (value: number) => {
  return formatMoneyText(value, currencyCode.value, { locale: 'en-PK', fractionDigits: 0 })
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
  isDirect.value = false
  directRate.value = null
  directPaidInCash.value = true
  saleType.value = 'retail'
  selectedCustomer.value = null
  discountPerLiter.value = null
  discountPercent.value = null
  discountTouched.value = false
  cashAmount.value = 0
  easypaisaAmount.value = 0
  jazzcashAmount.value = 0
  bankTransferAmount.value = 0
  cardSwipeAmount.value = 0
  parcoCardAmount.value = 0
  formErrors.value = {}
}

// The Complete Sale enabled condition lives in useFuelSaleSubmitState so it can be unit
// tested without mounting this whole form (see commit 11318f67 and
// tests/js/useFuelSaleSubmitState.spec.ts).
const { settlesAtCounter, canSubmit } = useFuelSaleCanSubmit({
  pumpId: computed(() => selectedPump.value?.id),
  itemId: computed(() => selectedFuelItem.value?.id),
  quantity,
  saleType,
  customerId: computed(() => selectedCustomer.value?.id),
  totalPaid,
  total,
})

const validateForm = () => {
  const errors: Record<string, string[]> = {}

  if (saleType.value === 'credit' && !selectedCustomer.value) {
    errors.customer_id = ['Choose the buyer this sale is owed by']
  }

  if (!selectedPump.value) errors.pump_id = ['Please select a pump']
  if (!selectedFuelItem.value) errors.item_id = ['Please select a fuel item']
  if (!quantity.value || quantity.value <= 0) errors.quantity = ['Please enter a valid quantity']
  if (saleType.value === 'bulk' && !discountPerLiter.value && !discountPercent.value) {
    errors.discount_per_liter = ['Please enter a discount for this bulk sale']
  }
  if (discountPerLiter.value && discountPercent.value) {
    errors.discount_percent = ['Enter either a per-litre discount or a percent discount, not both']
  }
  if (totalPaid.value > total.value) {
    errors.payment_total = ['Total payment cannot exceed sale amount']
  }

  formErrors.value = errors
  return Object.keys(errors).length === 0
}

const submitDirectSale = () => {
  if (!canSubmitDirect.value || !companySlug.value) return
  router.post(`/${companySlug.value}/fuel/sales/direct`, {
    customer_id: selectedCustomer.value?.id ?? null,
    item_id: selectedFuelItem.value!.id,
    quantity: quantity.value!,
    unit_price: directRate.value!,
    sale_date: saleDate.value,
    paid_in_cash: directPaidInCash.value,
  }, {
    preserveScroll: true,
    onSuccess: () => resetForm(),
    onError: (errors) => {
      formErrors.value = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, [v as string]]))
    },
  })
}

const submitSale = () => {
  if (isDirect.value) return submitDirectSale()
  if (!validateForm()) return

  const slug = companySlug.value
  if (!slug) return

  const formData = {
    pump_id: selectedPump.value!.id,
    item_id: selectedFuelItem.value!.id,
    quantity: quantity.value!,
    sale_date: saleDate.value,
    sale_type: saleType.value,
    customer_id: selectedCustomer.value?.id || null,
    investor_id: selectedInvestor.value?.id || null,
    discount_per_liter: discountPerLiter.value || null,
    discount_percent: discountPercent.value || null,
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

// Start on the last date used in this tab, and remember changes - see useEntryDate.
rememberEntryDate(companySlug.value, saleDate)
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
              <Fuel class="h-5 w-5 text-status-info" />
              Pump & Fuel Selection
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex items-start gap-3 rounded-lg border border-border/70 bg-muted/30 p-3">
              <Checkbox id="direct-sale" v-model="isDirect" class="mt-0.5" />
              <div class="space-y-0.5">
                <Label for="direct-sale" class="font-medium">Direct from tanker — not from the pumps</Label>
                <p class="text-xs text-muted-foreground">
                  Fuel delivered straight to a customer. No pump or tank is touched; on its bill,
                  enter these litres under "Sold directly".
                </p>
              </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
              <div v-if="!isDirect" class="space-y-2">
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
                <InputError :message="formErrors.pump_id?.[0]" />
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
                <InputError :message="formErrors.item_id?.[0]" />
              </div>
            </div>

            <div class="space-y-2">
              <Label for="sale-date">Sale date *</Label>
              <Input id="sale-date" v-model="saleDate" type="date" />
              <EntryDateNote :date="saleDate" />
              <p class="text-xs text-muted-foreground">
                A credit sale is picked up by the Daily Close for this date.
              </p>
              <InputError :message="formErrors.sale_date?.[0]" />
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
                <InputError :message="formErrors.quantity?.[0]" />
              </div>

              <div v-if="isDirect" class="space-y-2">
                <Label for="direct-rate">Rate per litre *</Label>
                <Input id="direct-rate" v-model.number="directRate" type="number" min="0.01" step="0.01" />
                <InputError :message="formErrors.unit_price?.[0]" />
              </div>
              <div v-else class="space-y-2">
                <Label>Unit Price</Label>
                <Input
                  :model-value="formatCurrency(unitPrice)"
                  readonly
                  class="bg-muted"
                />
              </div>

              <div v-if="isDirect" class="space-y-2">
                <Label>Paid</Label>
                <Select :model-value="directPaidInCash ? 'cash' : 'credit'" @update:model-value="(v) => (directPaidInCash = v === 'cash')">
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="cash">In cash</SelectItem>
                    <SelectItem value="credit">On credit</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div v-else class="space-y-2">
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
                    <SelectItem value="parco_card">Vendor Card</SelectItem>
                  </SelectContent>
                </Select>
                <InputError :message="formErrors.sale_type?.[0]" />
              </div>
            </div>

            <!-- Customer Selection for special types -->
            <div v-if="isDirect || ['credit', 'amanat', 'investor'].includes(saleType)" class="space-y-2">
              <Label>Customer{{ isDirect && directPaidInCash ? ' (optional)' : ' *' }}</Label>
              <div v-if="selectedCustomer" class="flex items-center gap-3 p-3 rounded-lg border border-border/70 bg-muted/30">
                <div class="flex-1">
                  <p class="font-medium">{{ selectedCustomer.name }}</p>
                  <p class="text-sm text-text-secondary">{{ selectedCustomer.phone }}</p>
                </div>
                <Button variant="outline" size="sm" @click="showCustomerDialog = true">
                  Change
                </Button>
              </div>
              <div v-else-if="isDirect && directPaidInCash" class="flex items-center gap-3 p-3 rounded-lg border border-border/70">
                <p class="flex-1 text-sm text-muted-foreground">Walk-in customer</p>
                <Button variant="outline" size="sm" @click="showCustomerDialog = true">Select Customer</Button>
              </div>
              <div v-else class="flex items-center gap-3 p-3 rounded-lg border border-status-attention/30 bg-status-attention/10">
                <Building2 class="h-5 w-5 text-status-attention" />
                <div class="flex-1">
                  <p class="font-medium text-status-attention">No customer selected</p>
                  <p class="text-sm text-status-attention">Required for {{ isDirect ? 'direct' : saleType }} sales</p>
                </div>
                <Button size="sm" class="border-status-attention/30 text-status-attention hover:bg-status-attention/10" @click="showCustomerDialog = true">
                  Select Customer
                </Button>
              </div>
              <InputError :message="formErrors.customer_id?.[0]" />
            </div>

            <!-- Discount: every sale type except investor (an investor already prices at
                 purchase rate with no margin to discount from). Prefilled from this buyer's
                 stored fuel discount once both customer and fuel item are chosen; editable
                 per sale. -->
            <div v-if="!isDirect && saleType !== 'investor'" class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label>Discount per Liter</Label>
                <Input
                  v-model.number="discountPerLiter"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="0.00"
                  :disabled="!!discountPercent"
                  :class="{ 'border-destructive': formErrors.discount_per_liter }"
                  @input="discountTouched = true"
                />
                <InputError :message="formErrors.discount_per_liter?.[0]" />
              </div>
              <div class="space-y-2">
                <Label>Discount %</Label>
                <Input
                  v-model.number="discountPercent"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  placeholder="0.00"
                  :disabled="!!discountPerLiter"
                  :class="{ 'border-destructive': formErrors.discount_percent }"
                  @input="discountTouched = true"
                />
                <InputError :message="formErrors.discount_percent?.[0]" />
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Payment Breakdown -->
        <Card v-if="!isDirect" class="border-border/80">
          <CardHeader>
            <CardTitle class="text-base flex items-center gap-2">
              <CreditCard class="h-5 w-5 text-status-success" />
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
                <InputError :message="formErrors['payment_breakdown.cash']?.[0]" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs flex items-center gap-1">
                  <Smartphone class="h-3 w-3" />
                  EasyPaisa
                </Label>
                <Input v-model.number="easypaisaAmount" type="number" min="0" step="1" placeholder="0" />
                <InputError :message="formErrors['payment_breakdown.easypaisa']?.[0]" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs flex items-center gap-1">
                  <Smartphone class="h-3 w-3" />
                  JazzCash
                </Label>
                <Input v-model.number="jazzcashAmount" type="number" min="0" step="1" placeholder="0" />
                <InputError :message="formErrors['payment_breakdown.jazzcash']?.[0]" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs flex items-center gap-1">
                  <Banknote class="h-3 w-3" />
                  Bank Transfer
                </Label>
                <Input v-model.number="bankTransferAmount" type="number" min="0" step="1" placeholder="0" />
                <InputError :message="formErrors['payment_breakdown.bank_transfer']?.[0]" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs flex items-center gap-1">
                  <CreditCard class="h-3 w-3" />
                  Card Swipe
                </Label>
                <Input v-model.number="cardSwipeAmount" type="number" min="0" step="1" placeholder="0" />
                <InputError :message="formErrors['payment_breakdown.card_swipe']?.[0]" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs flex items-center gap-1">
                  <CreditCard class="h-3 w-3" />
                  Vendor Card
                </Label>
                <Input v-model.number="parcoCardAmount" type="number" min="0" step="1" placeholder="0" />
                <InputError :message="formErrors['payment_breakdown.parco_card']?.[0]" />
              </div>
            </div>

            <div class="pt-4 border-t border-border/50 space-y-2">
              <Button variant="outline" size="sm" @click="setPaymentTotal">
                Set Full Cash Payment
              </Button>
              <div class="flex justify-between items-center">
                <span class="text-lg font-medium">Total Paid</span>
                <span class="text-xl font-bold" :class="totalPaid >= total ? 'text-status-success' : 'text-status-attention'">
                  <MoneyText :amount="totalPaid" :currency="currencyCode" :fraction-digits="0" />
                </span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-text-secondary">Balance</span>
<!-- An unpaid balance on a sale in progress is the normal state of a
                     sale in progress, not a failure. Amber says it is still open;
                     settled says nothing, because there is nothing left to say. -->
                <span class="text-sm font-medium" :class="balance <= 0 ? '' : 'text-status-attention'">
                  <MoneyText :amount="balance" :currency="currencyCode" :fraction-digits="0" />
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
            <div v-if="!isDirect" class="flex justify-between">
              <span>Subtotal</span>
              <span><MoneyText :amount="subtotal" :currency="currencyCode" :fraction-digits="0" /></span>
            </div>
            <div v-if="!isDirect && discount > 0" class="flex justify-between text-status-success">
              <span>Discount</span>
              <span>-<MoneyText :amount="discount" :currency="currencyCode" :fraction-digits="0" /></span>
            </div>
            <div class="flex justify-between text-lg font-semibold pt-2 border-t border-border/50">
              <span>Total</span>
              <span><MoneyText :amount="isDirect ? directTotal : total" :currency="currencyCode" :fraction-digits="0" /></span>
            </div>

            <div v-if="!isDirect && selectedFuelItem && currentRate" class="pt-4 space-y-2 text-sm text-text-secondary">
              <div class="flex justify-between">
                <span>Rate</span>
                <span><MoneyText :amount="currentRate.sale_rate" :currency="currencyCode" :fraction-digits="0" />/L</span>
              </div>
              <div v-if="saleType === 'investor'" class="flex justify-between">
                <span>Investor Rate</span>
                <span><MoneyText :amount="currentRate.purchase_rate" :currency="currencyCode" :fraction-digits="0" />/L</span>
              </div>
              <div class="flex justify-between">
                <span>Margin</span>
                <span><MoneyText :amount="currentRate.margin" :currency="currencyCode" :fraction-digits="0" />/L</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Action Buttons -->
        <Card class="border-border/80">
          <CardContent class="pt-6">
            <div class="space-y-3">
              <Button
                class="w-full bg-status-info hover:bg-status-info"
                size="lg"
                :disabled="isDirect ? !canSubmitDirect : !canSubmit"
                @click="submitSale"
              >
                <Calculator class="mr-2 h-5 w-5" />
                Complete Sale
              </Button>
              <Button variant="outline" class="w-full" @click="resetForm">
                Reset Form
              </Button>
            </div>

            <div v-if="Object.keys(formErrors).length > 0" class="mt-4 p-3 rounded-lg border border-status-critical/30 bg-status-critical/10">
              <p class="text-sm font-medium text-status-critical mb-2">Please fix the following errors:</p>
              <ul class="text-sm text-status-critical space-y-1">
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
