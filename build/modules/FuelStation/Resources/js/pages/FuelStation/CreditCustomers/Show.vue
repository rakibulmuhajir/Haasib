<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
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
import type { BreadcrumbItem } from '@/types'
import { formatDateTime } from '@/lib/datetime'
import { User, ArrowLeft, Wallet, TrendingUp, TrendingDown, Ban, Edit, Unlock, PiggyBank, ScrollText } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'
import MoneyText from '@/components/MoneyText.vue'
import InputError from '@/components/InputError.vue'
import CustomerConsolidatedInvoices from '@/components/CustomerConsolidatedInvoices.vue'
import type { SentDocument } from '@/components/CustomerConsolidatedInvoices.vue'
import Hint from '@/components/Hint.vue'
import CustomerUnitsCard from '@/components/CustomerUnitsCard.vue'
import type { CustomerUnit } from '@/components/CustomerUnitsCard.vue'

interface Customer {
  id: string
  name: string
  code: string | null
  phone: string | null
  email: string | null
  address: string | null
  credit_limit: number
  current_balance: number
  available_credit: number
  is_credit_blocked: boolean
}

interface OpenInvoice {
  id: string
  invoice_number: string
  balance: number
  currency: string
}

interface FuelDiscount {
  item_id: string
  item_name: string
  fuel_category: string | null
  discount_type: 'percent' | 'per_litre' | null
  value: number | null
}

interface StatementRow {
  date: string | null
  type: 'opening_balance' | 'invoice' | 'payment' | 'credit_note'
  reference: string | null
  description: string
  debit: number
  credit: number
  source_id: string | null
  link: string | null
  balance: number
}

interface SummaryProduct {
  item_id: string | null
  name: string
  unit: string | null
  quantity: number
  gross: number
  discount: number
  net: number
  link: string
}

interface PeriodSummary {
  from: string
  to: string
  products: SummaryProduct[]
  totals: { gross: number; discount: number; net: number }
  money: {
    opening: number
    bought: number
    paid: number
    payment_count: number
    payments_link: string
    other: number
    closing: number
  }
}

const props = defineProps<{
  summary: PeriodSummary
  consolidatedInvoices?: SentDocument[]
  customer: Customer
  statement: StatementRow[]
  openInvoices: OpenInvoice[]
  discounts: FuelDiscount[]
  units?: CustomerUnit[]
  currency: string
}>()

const { companySlug } = useCompanyRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Customers', href: `/${companySlug.value}/fuel/credit-customers` },
  { title: props.customer.name, href: `/${companySlug.value}/fuel/credit-customers/${props.customer.id}` },
])

const currency = computed(() => currencySymbol(props.currency))

const formatDate = (dateStr: string) => {
  return formatDateTime(dateStr, { mode: 'date' })
}

// Credit limit dialog
const limitDialogOpen = ref(false)
const limitForm = useForm({
  credit_limit: props.customer.credit_limit,
})

const submitLimit = () => {
  limitForm.post(`/${companySlug.value}/fuel/credit-customers/${props.customer.id}/limit`, {
    preserveScroll: true,
    onSuccess: () => {
      limitDialogOpen.value = false
    },
  })
}

const toggleBlock = () => {
  router.post(`/${companySlug.value}/fuel/credit-customers/${props.customer.id}/toggle-block`, {}, {
    preserveScroll: true,
  })
}

// Apply an on-account credit (an advance, or the leftover of a bigger payment) to one of
// this buyer's open invoices - a subsidiary reclass, no new cash movement.
const applyCreditDialogOpen = ref(false)
const applyCreditForm = useForm({
  invoice_id: '',
  amount: 0,
})
const selectedInvoice = computed(() => props.openInvoices.find((inv) => inv.id === applyCreditForm.invoice_id))
const openApplyCredit = () => {
  applyCreditForm.reset()
  const first = props.openInvoices[0]
  if (first) {
    applyCreditForm.invoice_id = first.id
    applyCreditForm.amount = Math.min(first.balance, props.customer.available_credit)
  }
  applyCreditDialogOpen.value = true
}
const submitApplyCredit = () => {
  applyCreditForm.post(`/${companySlug.value}/fuel/credit-customers/${props.customer.id}/apply-credit`, {
    preserveScroll: true,
    onSuccess: () => {
      applyCreditDialogOpen.value = false
    },
  })
}

// Fuel discounts: one row per active fuel item, "None" by default. Radix's Select cannot
// hold a null/empty value, so "none" is the sentinel here and mapped back to null on submit;
// a null row clears whatever was set for that item (see CreditCustomerController::updateDiscounts).
const discountsForm = useForm({
  discounts: props.discounts.map((d) => ({
    item_id: d.item_id,
    discount_type: (d.discount_type ?? 'none') as 'none' | 'percent' | 'per_litre',
    value: d.value,
  })),
})
const submitDiscounts = () => {
  discountsForm.transform((data) => ({
    discounts: data.discounts.map((row) => ({
      item_id: row.item_id,
      discount_type: row.discount_type === 'none' ? null : row.discount_type,
      value: row.discount_type === 'none' ? null : row.value,
    })),
  })).post(`/${companySlug.value}/fuel/credit-customers/${props.customer.id}/discounts`, {
    preserveScroll: true,
  })
}

const columns = [
  { key: 'date', label: 'Date', kind: 'date' as const },
  { key: 'type', label: 'Type', kind: 'status' as const },
  { key: 'description', label: 'Description', kind: 'text' as const },
  { key: 'debit', label: 'Debit', kind: 'amount' as const },
  { key: 'credit', label: 'Credit', kind: 'amount' as const },
  { key: 'balance', label: 'Balance', kind: 'amount' as const },
]

const tableData = computed(() => {
  return props.statement.map((row, index) => ({
    id: row.source_id ?? `opening-${index}`,
    date: row.date ? formatDate(row.date) : '—',
    type: row.type,
    description: row.description,
    debit: row.debit,
    credit: row.credit,
    balance: row.balance,
    _raw: row,
  }))
})

// Summary period: the page reloads with ?from=&to= (other cards do not depend on it).
const range = useForm({ from: props.summary.from, to: props.summary.to })
const applyRange = () => {
  router.get(`/${companySlug.value}/fuel/credit-customers/${props.customer.id}`, { from: range.from, to: range.to }, {
    preserveScroll: true,
    preserveState: true,
    only: ['summary'],
  })
}
const formatQty = (n: number) => n.toLocaleString(undefined, { maximumFractionDigits: 2 })

const goBack = () => {
  router.get(`/${companySlug.value}/fuel/credit-customers`)
}
</script>

<template>
  <Head :title="customer.name" />

  <PageShell
    :title="customer.name"
    :description="customer.phone || customer.email || 'Customer details'"
    :icon="User"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <!-- Contact details, address and credit limit live on the customer's detail form. -->
      <Button variant="outline" as-child>
        <Link :href="`/${companySlug}/customers/${customer.id}/edit`">Edit details</Link>
      </Button>
      <!-- Pick this customer's unpaid sales into one invoice to send or print. -->
      <Button variant="outline" as-child>
        <Link :href="`/${companySlug}/consolidated-invoices/create?customer_id=${customer.id}`">
          <ScrollText class="mr-2 h-4 w-4" />
          Consolidated invoice
        </Link>
      </Button>
      <Button variant="outline" @click="goBack">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button variant="outline" @click="limitDialogOpen = true">
        <Edit class="mr-2 h-4 w-4" />
        Set Limit
      </Button>
      <Button v-if="customer.available_credit > 0 && openInvoices.length" variant="outline" @click="openApplyCredit">
        <PiggyBank class="mr-2 h-4 w-4" />
        Apply Credit
      </Button>
      <Button
        :variant="customer.is_credit_blocked ? 'default' : 'destructive'"
        @click="toggleBlock"
      >
        <component :is="customer.is_credit_blocked ? Unlock : Ban" class="mr-2 h-4 w-4" />
        {{ customer.is_credit_blocked ? 'Unblock' : 'Block Credit' }}
      </Button>
    </template>

    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-4">
      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Current Balance</CardDescription>
          <CardTitle class="text-2xl" :class="customer.current_balance > 0 ? 'text-status-attention' : 'text-status-success'">
            <MoneyText :amount="customer.current_balance" :currency="props.currency" />
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4" />
            <span>Outstanding receivable</span>
          </div>
        </CardContent>
      </Card>

      <Card v-if="customer.available_credit > 0" class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>On Account</CardDescription>
          <CardTitle class="text-2xl text-status-info">
            <MoneyText :amount="customer.available_credit" :currency="props.currency" />
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <PiggyBank class="h-4 w-4" />
            <span>Unapplied credit, ready to apply to an invoice</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Credit Limit</CardDescription>
          <CardTitle class="text-2xl">
            <template v-if="customer.credit_limit > 0"><MoneyText :amount="customer.credit_limit" :currency="props.currency" /></template>
            <template v-else>No Limit</template>
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div v-if="customer.credit_limit > 0" class="text-sm text-text-secondary">
            <MoneyText :amount="Math.max(0, customer.credit_limit - customer.current_balance)" :currency="props.currency" /> available
          </div>
          <div v-else class="text-sm text-text-secondary">Unlimited credit</div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Status</CardDescription>
          <CardTitle class="text-2xl">
            <Badge
              :class="{
                'bg-status-critical/10 text-status-critical': customer.is_credit_blocked,
                'bg-status-attention/10 text-status-attention': !customer.is_credit_blocked && customer.credit_limit > 0 && customer.current_balance > customer.credit_limit,
                'bg-status-success/10 text-status-success': !customer.is_credit_blocked && (customer.credit_limit === 0 || customer.current_balance <= customer.credit_limit),
              }"
            >
              {{ customer.is_credit_blocked ? 'Blocked' : (customer.credit_limit > 0 && customer.current_balance > customer.credit_limit ? 'Over Limit' : 'Active') }}
            </Badge>
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="text-sm text-text-secondary">
            {{ customer.is_credit_blocked ? 'Cannot make credit purchases' : 'Credit enabled' }}
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Period summary -->
    <Card class="border-border/80">
      <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <CardTitle class="text-base">Summary</CardTitle>
        <form novalidate class="flex flex-wrap items-end gap-2" @submit.prevent="applyRange">
          <div class="space-y-1">
            <Label for="summary-from">From</Label>
            <Input id="summary-from" v-model="range.from" type="date" class="h-8 w-40" />
          </div>
          <div class="space-y-1">
            <Label for="summary-to">To</Label>
            <Input id="summary-to" v-model="range.to" type="date" class="h-8 w-40" />
          </div>
          <Button type="submit" size="sm" variant="outline" :disabled="!range.from || !range.to">Apply</Button>
        </form>
      </CardHeader>
      <CardContent class="space-y-6">
        <div class="overflow-x-auto">
          <table class="w-full text-sm tabular-nums">
            <thead>
              <tr class="border-b border-rule-emphasis text-left text-xs text-text-secondary">
                <th class="py-1.5 pr-3 font-medium">Product</th>
                <th class="px-3 py-1.5 text-right font-medium">Qty</th>
                <th class="px-3 py-1.5 text-right font-medium">Gross</th>
                <th class="px-3 py-1.5 text-right font-medium">Discount</th>
                <th class="py-1.5 pl-3 text-right font-medium">Net</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!summary.products.length">
                <td colspan="5" class="py-4 text-center text-muted-foreground">No purchases in this period</td>
              </tr>
              <tr v-for="p in summary.products" :key="p.item_id ?? 'other'" class="border-b border-rule-subtle">
                <td class="py-1.5 pr-3"><Link :href="p.link" class="underline-offset-2 hover:underline">{{ p.name }}</Link></td>
                <td class="whitespace-nowrap px-3 py-1.5 text-right">{{ formatQty(p.quantity) }}<span v-if="p.unit" class="ml-1 text-text-secondary">{{ p.unit }}</span></td>
                <td class="whitespace-nowrap px-3 py-1.5 text-right"><MoneyText :amount="p.gross" :currency="props.currency" /></td>
                <td class="whitespace-nowrap px-3 py-1.5 text-right"><MoneyText :amount="p.discount" :currency="props.currency" /></td>
                <td class="whitespace-nowrap py-1.5 pl-3 text-right"><MoneyText :amount="p.net" :currency="props.currency" /></td>
              </tr>
            </tbody>
            <tfoot v-if="summary.products.length">
              <tr class="border-t border-rule-emphasis font-semibold">
                <td class="py-1.5 pr-3">Total</td>
                <td class="px-3 py-1.5" />
                <td class="whitespace-nowrap px-3 py-1.5 text-right"><MoneyText :amount="summary.totals.gross" :currency="props.currency" /></td>
                <td class="whitespace-nowrap px-3 py-1.5 text-right"><MoneyText :amount="summary.totals.discount" :currency="props.currency" /></td>
                <td class="whitespace-nowrap py-1.5 pl-3 text-right"><MoneyText :amount="summary.totals.net" :currency="props.currency" /></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <ul class="max-w-md space-y-1 text-sm tabular-nums">
          <li class="flex items-baseline justify-between gap-3">
            <span>Opening</span>
            <span class="whitespace-nowrap"><MoneyText :amount="summary.money.opening" :currency="props.currency" /></span>
          </li>
          <li class="flex items-baseline justify-between gap-3">
            <span>+ Bought</span>
            <span class="whitespace-nowrap"><MoneyText :amount="summary.money.bought" :currency="props.currency" /></span>
          </li>
          <li class="flex items-baseline justify-between gap-3">
            <span>
              − Paid
              <Link :href="summary.money.payments_link" class="ml-1 text-text-secondary underline-offset-2 hover:underline">
                ({{ summary.money.payment_count }} {{ summary.money.payment_count === 1 ? 'payment' : 'payments' }})
              </Link>
            </span>
            <span class="whitespace-nowrap"><MoneyText :amount="summary.money.paid" :currency="props.currency" /></span>
          </li>
          <li v-if="summary.money.other !== 0" class="flex items-baseline justify-between gap-3">
            <span>
              <Hint>± Other<template #content>Credit notes, refunds and corrections: whatever bought and paid do not explain.</template></Hint>
            </span>
            <span class="whitespace-nowrap"><MoneyText :amount="summary.money.other" :currency="props.currency" /></span>
          </li>
          <li class="flex items-baseline justify-between gap-3 border-t border-rule-emphasis pt-1 font-semibold">
            <span>= Owes at {{ formatDate(summary.to) }}</span>
            <span class="whitespace-nowrap"><MoneyText :amount="summary.money.closing" :currency="props.currency" /></span>
          </li>
        </ul>
      </CardContent>
    </Card>

    <!-- Customer Details & Transactions -->
    <div class="grid gap-6 lg:grid-cols-3">
      <Card class="lg:col-span-1">
        <CardHeader>
          <CardTitle class="text-base">Customer Details</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div v-if="customer.code">
            <div class="text-sm text-muted-foreground">Code</div>
            <div class="font-medium">{{ customer.code }}</div>
          </div>
          <div v-if="customer.phone">
            <div class="text-sm text-muted-foreground">Phone</div>
            <div class="font-medium">{{ customer.phone }}</div>
          </div>
          <div v-if="customer.email">
            <div class="text-sm text-muted-foreground">Email</div>
            <div class="font-medium">{{ customer.email }}</div>
          </div>
          <div v-if="customer.address">
            <div class="text-sm text-muted-foreground">Address</div>
            <div class="font-medium">{{ customer.address }}</div>
          </div>
        </CardContent>
      </Card>

      <Card class="lg:col-span-2">
        <CardHeader class="flex flex-row items-start justify-between gap-4">
          <div>
            <CardTitle class="text-base">Statement</CardTitle>
            <CardDescription>Every invoice, payment and credit note against this buyer's receivable account, from every entry point, oldest first.</CardDescription>
          </div>
          <Button
            variant="outline"
            size="sm"
            @click="router.get(`/${companySlug}/reports/statements`, { kind: 'customer', id: customer.id })"
          >
            Full statement
          </Button>
        </CardHeader>
        <CardContent class="p-0">
          <!-- Each row opens its invoice, payment or credit note (to view or correct it). -->
          <LedgerRegister :data="tableData" :columns="columns" @row-click="(row) => row._raw.link && router.get(`/${companySlug}/${row._raw.link}`)">
            <template #empty>
              <div class="py-8 text-center text-muted-foreground">
                No activity yet
              </div>
            </template>

            <template #cell-type="{ row }">
              <Badge
                :class="{
                  invoice: 'bg-status-attention/10 text-status-attention',
                  payment: 'bg-status-success/10 text-status-success',
                  credit_note: 'bg-status-info/10 text-status-info',
                  opening_balance: 'bg-muted text-muted-foreground',
                }[row._raw.type as string]"
              >
                {{ { invoice: 'Invoice', payment: 'Payment', credit_note: 'Credit note', opening_balance: 'Opening' }[row._raw.type as string] }}
              </Badge>
            </template>

            <template #cell-debit="{ row }">
              <span v-if="row._raw.debit > 0" class="font-medium text-status-attention"><MoneyText :amount="row._raw.debit" :currency="props.currency" /></span>
            </template>

            <template #cell-credit="{ row }">
              <span v-if="row._raw.credit > 0" class="font-medium text-status-success"><MoneyText :amount="row._raw.credit" :currency="props.currency" /></span>
            </template>

            <template #cell-balance="{ row }">
              <span class="font-semibold"><MoneyText :amount="row._raw.balance" :currency="props.currency" /></span>
            </template>
          </LedgerRegister>
        </CardContent>
      </Card>
    </div>

    <CustomerConsolidatedInvoices
      :company-slug="companySlug"
      :customer-id="customer.id"
      :documents="consolidatedInvoices ?? []"
      currency="PKR"
    />

    <CustomerUnitsCard :company-slug="companySlug" :customer-id="customer.id" :units="units ?? []" />

    <!-- Fuel Discounts -->
    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="text-base">Discounts</CardTitle>
        <CardDescription>A negotiated rate for this buyer, per fuel item. Applies automatically on a credit sale unless overridden.</CardDescription>
      </CardHeader>
      <CardContent>
        <form novalidate @submit.prevent="submitDiscounts" class="space-y-4">
          <div v-for="(row, index) in discountsForm.discounts" :key="row.item_id"
            class="grid gap-3 sm:grid-cols-[1fr_10rem_10rem] sm:items-end">
            <div>
              <Label>{{ props.discounts[index]?.item_name }}</Label>
              <p class="text-xs text-muted-foreground">{{ props.discounts[index]?.fuel_category }}</p>
            </div>
            <div class="space-y-1">
              <Label :for="`discount-type-${index}`">Discount</Label>
              <Select v-model="row.discount_type" :id="`discount-type-${index}`">
                <SelectTrigger><SelectValue placeholder="None" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">None</SelectItem>
                  <SelectItem value="percent">% of sale</SelectItem>
                  <SelectItem value="per_litre">Rs per litre</SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="discountsForm.errors[`discounts.${index}.discount_type`]" />
            </div>
            <div class="space-y-1">
              <Label :for="`discount-value-${index}`">Value</Label>
              <Input v-if="row.discount_type !== 'none'" :id="`discount-value-${index}`" v-model.number="row.value"
                type="number" min="0.0001" :max="row.discount_type === 'percent' ? 100 : undefined" step="0.01" />
              <div v-else class="flex h-9 items-center text-sm text-muted-foreground">—</div>
              <InputError :message="discountsForm.errors[`discounts.${index}.value`]" />
            </div>
          </div>
          <p v-if="!discountsForm.discounts.length" class="text-sm text-muted-foreground">No active fuel items to discount yet.</p>
          <Button type="submit" :disabled="discountsForm.processing">
            <span v-if="discountsForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
            Save Discounts
          </Button>
        </form>
      </CardContent>
    </Card>

    <!-- Credit Limit Dialog -->
    <Dialog v-model:open="limitDialogOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Set Credit Limit</DialogTitle>
          <DialogDescription>Set the maximum credit allowed for {{ customer.name }}.</DialogDescription>
        </DialogHeader>

        <form novalidate @submit.prevent="submitLimit" class="space-y-4">
          <div class="space-y-2">
            <Label for="credit_limit">Credit Limit</Label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">{{ currency }}</span>
              <Input
                id="credit_limit"
                v-model.number="limitForm.credit_limit"
                type="number"
                min="0"
                step="1"
                class="pl-14"
                :class="{ 'border-destructive': limitForm.errors.credit_limit }"
              />
            </div>
            <p class="text-sm text-muted-foreground">Set to 0 for unlimited credit.</p>
            <InputError :message="limitForm.errors.credit_limit" />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="limitDialogOpen = false" :disabled="limitForm.processing">
              Cancel
            </Button>
            <Button type="submit" :disabled="limitForm.processing">
              <span v-if="limitForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
              Save Limit
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Apply On-Account Credit Dialog -->
    <Dialog v-model:open="applyCreditDialogOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Apply On-Account Credit</DialogTitle>
          <DialogDescription>
            Match <MoneyText :amount="customer.available_credit" :currency="props.currency" /> of {{ customer.name }}'s unapplied
            credit to an invoice. No new payment is recorded - this only reassigns money already received.
          </DialogDescription>
        </DialogHeader>

        <form novalidate @submit.prevent="submitApplyCredit" class="space-y-4">
          <div class="space-y-2">
            <Label for="apply-credit-invoice">Invoice</Label>
            <Select v-model="applyCreditForm.invoice_id">
              <SelectTrigger id="apply-credit-invoice"><SelectValue placeholder="Select invoice" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="invoice in openInvoices" :key="invoice.id" :value="invoice.id">
                  {{ invoice.invoice_number }} — <MoneyText :amount="invoice.balance" :currency="invoice.currency" /> due
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="applyCreditForm.errors.invoice_id" />
          </div>

          <div class="space-y-2">
            <Label for="apply-credit-amount">Amount</Label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">{{ currency }}</span>
              <Input
                id="apply-credit-amount"
                v-model.number="applyCreditForm.amount"
                type="number"
                min="0.01"
                step="0.01"
                :max="Math.min(customer.available_credit, selectedInvoice?.balance ?? customer.available_credit)"
                class="pl-14"
                :class="{ 'border-destructive': applyCreditForm.errors.amount }"
              />
            </div>
            <InputError :message="applyCreditForm.errors.amount" />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="applyCreditDialogOpen = false" :disabled="applyCreditForm.processing">
              Cancel
            </Button>
            <Button type="submit" :disabled="applyCreditForm.processing || !applyCreditForm.invoice_id">
              <span v-if="applyCreditForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
              Apply Credit
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
