<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import InlineEditable from '@/components/InlineEditable.vue'
import { useInlineEdit } from '@/composables/useInlineEdit'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import type { BreadcrumbItem } from '@/types'
import {
  Building2,
  BarChart3,
  Settings,
  MapPin,
  Receipt,
  AlertTriangle,
  Wallet,
  Calendar,
  FileText,
  Plus,
  DollarSign,
  Mail,
  Phone,
  Hash,
  Globe,
  CheckCircle2,
  XCircle,
} from 'lucide-vue-next'
import { toast } from 'vue-sonner'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface AddressRef {
  street?: string | null
  city?: string | null
  state?: string | null
  zip?: string | null
  country?: string | null
}

interface VendorRef {
  id: string
  vendor_number: string
  name: string
  email: string | null
  phone: string | null
  base_currency: string
  payment_terms: number | null
  tax_id: string | null
  account_number: string | null
  website: string | null
  logo_url: string | null
  notes: string | null
  address?: AddressRef | null
  is_active: boolean
}

interface SummaryRef {
  open_balance: number
  overdue_balance: number
  bill_count: number
  paid_ytd: number
}

interface CurrencyOption {
  currency_code: string
  is_base: boolean
}

interface BillRef {
  id: string
  bill_number: string
  bill_date: string
  due_date: string
  total_amount: number
  balance: number
  currency: string
  status: string
}

interface PaymentRef {
  id: string
  payment_number: string
  payment_date: string
  amount: number
  currency: string
  payment_method: string
  reference_number: string | null
}

const props = defineProps<{
  company: CompanyRef
  vendor: VendorRef
  summary: SummaryRef
  bills: BillRef[]
  payments: PaymentRef[]
  currencies: CurrencyOption[]
  canEdit: boolean
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Vendors', href: `/${props.company.slug}/vendors` },
  { title: props.vendor.name },
])

const activeTab = ref('overview')

// Setup inline editing for settings
const settingsEdit = useInlineEdit({
  endpoint: `/${props.company.slug}/vendors/${props.vendor.id}`,
  successMessage: 'Vendor updated successfully',
  errorMessage: 'Failed to update vendor',
})

// Register editable fields
const nameField = settingsEdit.registerField('name', props.vendor.name)
const emailField = settingsEdit.registerField('email', props.vendor.email || '')
const phoneField = settingsEdit.registerField('phone', props.vendor.phone || '')
const paymentTermsField = settingsEdit.registerField('payment_terms', props.vendor.payment_terms ?? 30)
const currencyField = settingsEdit.registerField('base_currency', props.vendor.base_currency || props.company.base_currency)
const taxIdField = settingsEdit.registerField('tax_id', props.vendor.tax_id || '')
const websiteField = settingsEdit.registerField('website', props.vendor.website || '')
const accountNumberField = settingsEdit.registerField('account_number', props.vendor.account_number || '')
const statusField = settingsEdit.registerField('is_active', props.vendor.is_active)
const notesField = settingsEdit.registerField('notes', props.vendor.notes || '')

// Address fields
const addressStreet = ref(props.vendor.address?.street || '')
const addressCity = ref(props.vendor.address?.city || '')
const addressState = ref(props.vendor.address?.state || '')
const addressZip = ref(props.vendor.address?.zip || '')
const addressCountry = ref(props.vendor.address?.country || '')
const addressEditing = ref(false)
const addressSaving = ref(false)

// Currency options for select
const currencyOptions = computed(() =>
  props.currencies.map((c) => ({
    value: c.currency_code,
    label: c.is_base ? `${c.currency_code} (Base)` : c.currency_code,
  }))
)

// Status options
const statusOptions = [
  { value: true, label: 'Active' },
  { value: false, label: 'Inactive' },
]

// Payment terms options
const paymentTermsOptions = [
  { value: 0, label: 'Due on Receipt' },
  { value: 7, label: 'Net 7' },
  { value: 14, label: 'Net 14' },
  { value: 15, label: 'Net 15' },
  { value: 30, label: 'Net 30' },
  { value: 45, label: 'Net 45' },
  { value: 60, label: 'Net 60' },
  { value: 90, label: 'Net 90' },
]

const canManage = computed(() => props.canEdit !== false)

const money = (val: number | null | undefined) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: props.vendor.base_currency || props.company.base_currency,
  }).format(val ?? 0)

const getInitials = (name: string) => {
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

const billColumns = [
  { key: 'bill_number', label: 'Bill #' },
  { key: 'bill_date', label: 'Date' },
  { key: 'due_date', label: 'Due' },
  { key: 'total_amount', label: 'Total' },
  { key: 'balance', label: 'Balance' },
  { key: 'status', label: 'Status' },
]

const paymentColumns = [
  { key: 'payment_number', label: 'Payment #' },
  { key: 'payment_date', label: 'Date' },
  { key: 'amount', label: 'Amount' },
  { key: 'payment_method', label: 'Method' },
  { key: 'reference_number', label: 'Reference' },
]

const billRows = computed(() =>
  props.bills.map((b) => ({
    ...b,
    total_amount: money(b.total_amount),
    balance: money(b.balance),
  }))
)

const paymentRows = computed(() =>
  props.payments.map((p) => ({
    ...p,
    amount: money(p.amount),
    reference_number: p.reference_number ?? '—',
  }))
)

const getStatusVariant = (status: string) => {
  const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    paid: 'default',
    received: 'default',
    open: 'secondary',
    draft: 'outline',
    overdue: 'destructive',
    void: 'secondary',
    cancelled: 'secondary',
  }
  return variants[status.toLowerCase()] || 'outline'
}

const formatAddress = (addr: AddressRef | null | undefined) => {
  if (!addr) return null
  const parts = [addr.street, addr.city, addr.state, addr.zip, addr.country].filter(Boolean)
  return parts.length > 0 ? parts.join(', ') : null
}

const saveAddress = async () => {
  addressSaving.value = true
  router.patch(
    `/${props.company.slug}/vendors/${props.vendor.id}`,
    {
      address: {
        street: addressStreet.value || '',
        city: addressCity.value || '',
        state: addressState.value || '',
        zip: addressZip.value || '',
        country: addressCountry.value || '',
      },
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        addressEditing.value = false
        addressSaving.value = false
        toast.success('Address updated')
      },
      onError: () => {
        addressSaving.value = false
        toast.error('Failed to update address')
      },
    }
  )
}

const cancelAddressEdit = () => {
  addressStreet.value = props.vendor.address?.street || ''
  addressCity.value = props.vendor.address?.city || ''
  addressState.value = props.vendor.address?.state || ''
  addressZip.value = props.vendor.address?.zip || ''
  addressCountry.value = props.vendor.address?.country || ''
  addressEditing.value = false
}
</script>

<template>
  <Head :title="`Vendor: ${vendor.name}`" />
  <PageShell
    :title="vendor.name"
    :icon="Building2"
    :breadcrumbs="breadcrumbs"
    :badge="{ text: vendor.is_active ? 'Active' : 'Inactive', variant: vendor.is_active ? 'default' : 'secondary' }"
    compact
  >
    <template #description>
      <div class="flex items-center gap-3">
        <Avatar class="h-8 w-8">
          <AvatarImage v-if="vendor.logo_url" :src="vendor.logo_url" :alt="vendor.name" />
          <AvatarFallback class="bg-zinc-100 text-zinc-600 text-xs">{{ getInitials(vendor.name) }}</AvatarFallback>
        </Avatar>
        <span class="font-mono text-zinc-400">{{ vendor.vendor_number }}</span>
        <span v-if="vendor.email" class="text-zinc-300">•</span>
        <span v-if="vendor.email" class="text-zinc-500">{{ vendor.email }}</span>
      </div>
    </template>

    <template #actions>
      <Button size="sm" @click="router.visit(`/${company.slug}/bills/create?vendor=${vendor.id}`)">
        <Plus class="mr-2 h-4 w-4" />
        New Bill
      </Button>
    </template>

    <Tabs v-model="activeTab" class="w-full">
      <TabsList class="mb-6 bg-zinc-100">
        <TabsTrigger value="overview" class="gap-2">
          <BarChart3 class="h-4 w-4" />
          Overview
        </TabsTrigger>
        <TabsTrigger value="settings" class="gap-2">
          <Settings class="h-4 w-4" />
          Settings
        </TabsTrigger>
        <TabsTrigger value="address" class="gap-2">
          <MapPin class="h-4 w-4" />
          Address
        </TabsTrigger>
      </TabsList>

      <!-- Overview Tab -->
      <TabsContent value="overview" class="space-y-6">
        <!-- Key Financial Stats -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">Amount Owed</CardTitle>
              <Receipt class="h-4 w-4 text-zinc-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-semibold text-zinc-900">{{ money(summary.open_balance) }}</div>
              <p class="text-xs text-zinc-500 mt-1">{{ summary.bill_count }} bill{{ summary.bill_count === 1 ? '' : 's' }}</p>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">Overdue</CardTitle>
              <AlertTriangle class="h-4 w-4 text-amber-500" />
            </CardHeader>
            <CardContent>
              <div :class="['text-2xl font-semibold', summary.overdue_balance > 0 ? 'text-amber-600' : 'text-zinc-900']">
                {{ money(summary.overdue_balance) }}
              </div>
              <p class="text-xs text-zinc-500 mt-1">Past due date</p>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">Paid YTD</CardTitle>
              <Wallet class="h-4 w-4 text-emerald-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-semibold text-zinc-900">{{ money(summary.paid_ytd) }}</div>
              <p class="text-xs text-zinc-500 mt-1">This year</p>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500">Payment Terms</CardTitle>
              <Calendar class="h-4 w-4 text-zinc-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-semibold text-zinc-900">{{ vendor.payment_terms || 30 }} days</div>
              <p class="text-xs text-zinc-500 mt-1">Net terms</p>
            </CardContent>
          </Card>
        </div>

        <!-- Quick Actions -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-sm font-medium text-zinc-500">Quick Actions</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="flex flex-wrap gap-2">
              <Button size="sm" variant="outline" @click="router.visit(`/${company.slug}/bills/create?vendor=${vendor.id}`)">
                <Plus class="mr-1 h-3 w-3" />
                Create Bill
              </Button>
              <Button size="sm" variant="outline" @click="router.visit(`/${company.slug}/bill-payments/create?vendor=${vendor.id}`)">
                <DollarSign class="mr-1 h-3 w-3" />
                Record Payment
              </Button>
              <Button size="sm" variant="outline" @click="router.visit(`/${company.slug}/vendor-credits/create?vendor=${vendor.id}`)">
                <FileText class="mr-1 h-3 w-3" />
                Vendor Credit
              </Button>
            </div>
          </CardContent>
        </Card>

        <!-- Bills & Payments Tables -->
        <div class="grid gap-4 lg:grid-cols-2">
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between">
              <div>
                <CardTitle class="text-sm font-medium text-zinc-500">Recent Bills</CardTitle>
                <CardDescription>Last 25 bills</CardDescription>
              </div>
              <Button size="sm" variant="ghost" @click="router.visit(`/${company.slug}/bills?vendor=${vendor.id}`)">
                View All
              </Button>
            </CardHeader>
            <CardContent>
              <DataTable :columns="billColumns" :data="billRows" key-field="id">
                <template #cell-status="{ row }">
                  <Badge :variant="getStatusVariant(row.status)" class="capitalize">
                    {{ row.status }}
                  </Badge>
                </template>
              </DataTable>
            </CardContent>
          </Card>

          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between">
              <div>
                <CardTitle class="text-sm font-medium text-zinc-500">Recent Payments</CardTitle>
                <CardDescription>Last 25 payments</CardDescription>
              </div>
              <Button size="sm" variant="ghost" @click="router.visit(`/${company.slug}/bill-payments?vendor=${vendor.id}`)">
                View All
              </Button>
            </CardHeader>
            <CardContent>
              <DataTable :columns="paymentColumns" :data="paymentRows" key-field="id" />
            </CardContent>
          </Card>
        </div>
      </TabsContent>

      <!-- Settings Tab -->
      <TabsContent value="settings" class="space-y-6">
        <!-- Contact Information -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-zinc-900">Contact Information</CardTitle>
            <CardDescription class="text-zinc-500">
              {{ canManage ? 'Click the pencil icon to edit' : 'Contact an admin to make changes' }}
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-6 md:grid-cols-2">
              <InlineEditable
                v-model="nameField.value.value"
                label="Vendor Name"
                :editing="nameField.isEditing.value"
                :saving="nameField.isSaving.value"
                :can-edit="canManage"
                type="text"
                :icon="Building2"
                @start-edit="nameField.startEditing()"
                @save="nameField.save()"
                @cancel="nameField.cancelEditing()"
              />

              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Vendor Number</Label>
                <div class="flex items-center gap-2 font-mono text-base text-zinc-900">
                  <Hash class="h-4 w-4 text-zinc-400" />
                  {{ vendor.vendor_number }}
                </div>
                <p class="text-xs text-zinc-400">Auto-generated, cannot be changed</p>
              </div>

              <InlineEditable
                v-model="emailField.value.value"
                label="Email"
                :editing="emailField.isEditing.value"
                :saving="emailField.isSaving.value"
                :can-edit="canManage"
                type="email"
                :icon="Mail"
                placeholder="accounts@example.com"
                @start-edit="emailField.startEditing()"
                @save="emailField.save()"
                @cancel="emailField.cancelEditing()"
              />

              <InlineEditable
                v-model="phoneField.value.value"
                label="Phone"
                :editing="phoneField.isEditing.value"
                :saving="phoneField.isSaving.value"
                :can-edit="canManage"
                type="text"
                :icon="Phone"
                placeholder="+1 (555) 123-4567"
                @start-edit="phoneField.startEditing()"
                @save="phoneField.save()"
                @cancel="phoneField.cancelEditing()"
              />
            </div>
          </CardContent>
        </Card>

        <!-- Payment Settings -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-zinc-900 flex items-center gap-2">
              <DollarSign class="h-4 w-4" />
              Payment Settings
            </CardTitle>
            <CardDescription class="text-zinc-500">
              Payment terms, currency, and account information
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-6 md:grid-cols-2">
              <InlineEditable
                v-model="paymentTermsField.value.value"
                label="Payment Terms"
                :editing="paymentTermsField.isEditing.value"
                :saving="paymentTermsField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="paymentTermsOptions"
                :icon="Calendar"
                helper-text="Default terms for new bills"
                @start-edit="paymentTermsField.startEditing()"
                @save="paymentTermsField.save()"
                @cancel="paymentTermsField.cancelEditing()"
              />

              <InlineEditable
                v-model="currencyField.value.value"
                label="Currency"
                :editing="currencyField.isEditing.value"
                :saving="currencyField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="currencyOptions"
                :icon="Globe"
                helper-text="Default currency for bills"
                @start-edit="currencyField.startEditing()"
                @save="currencyField.save()"
                @cancel="currencyField.cancelEditing()"
              />

              <InlineEditable
                v-model="taxIdField.value.value"
                label="Tax ID"
                :editing="taxIdField.isEditing.value"
                :saving="taxIdField.isSaving.value"
                :can-edit="canManage"
                type="text"
                :icon="FileText"
                placeholder="Enter tax ID"
                helper-text="VAT, GST, or tax registration number"
                @start-edit="taxIdField.startEditing()"
                @save="taxIdField.save()"
                @cancel="taxIdField.cancelEditing()"
              />

              <InlineEditable
                v-model="accountNumberField.value.value"
                label="Account Number"
                :editing="accountNumberField.isEditing.value"
                :saving="accountNumberField.isSaving.value"
                :can-edit="canManage"
                type="text"
                :icon="Hash"
                placeholder="Your account # with vendor"
                helper-text="Reference number with this vendor"
                @start-edit="accountNumberField.startEditing()"
                @save="accountNumberField.save()"
                @cancel="accountNumberField.cancelEditing()"
              />

              <InlineEditable
                v-model="websiteField.value.value"
                label="Website"
                :editing="websiteField.isEditing.value"
                :saving="websiteField.isSaving.value"
                :can-edit="canManage"
                type="text"
                :icon="Globe"
                placeholder="https://example.com"
                @start-edit="websiteField.startEditing()"
                @save="websiteField.save()"
                @cancel="websiteField.cancelEditing()"
              />

              <InlineEditable
                v-model="statusField.value.value"
                label="Status"
                :editing="statusField.isEditing.value"
                :saving="statusField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="statusOptions"
                helper-text="Inactive vendors won't appear in dropdowns"
                @start-edit="statusField.startEditing()"
                @save="statusField.save()"
                @cancel="statusField.cancelEditing()"
              >
                <template #display>
                  <Badge :variant="statusField.value.value ? 'default' : 'secondary'">
                    <component :is="statusField.value.value ? CheckCircle2 : XCircle" class="mr-1 h-3 w-3" />
                    {{ statusField.value.value ? 'Active' : 'Inactive' }}
                  </Badge>
                </template>
              </InlineEditable>
            </div>
          </CardContent>
        </Card>

        <!-- Notes -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-zinc-900">Internal Notes</CardTitle>
            <CardDescription class="text-zinc-500">
              Private notes about this vendor (not visible on bills)
            </CardDescription>
          </CardHeader>
          <CardContent>
            <InlineEditable
              v-model="notesField.value.value"
              label="Notes"
              :editing="notesField.isEditing.value"
              :saving="notesField.isSaving.value"
              :can-edit="canManage"
              type="textarea"
              placeholder="Add notes about this vendor..."
              @start-edit="notesField.startEditing()"
              @save="notesField.save()"
              @cancel="notesField.cancelEditing()"
            />
          </CardContent>
        </Card>
      </TabsContent>

      <!-- Address Tab -->
      <TabsContent value="address" class="space-y-6">
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader class="flex flex-row items-center justify-between">
            <div>
              <CardTitle class="text-zinc-900">Vendor Address</CardTitle>
              <CardDescription class="text-zinc-500">Mailing and payment address</CardDescription>
            </div>
            <Button
              v-if="canManage && !addressEditing"
              variant="ghost"
              size="sm"
              @click="addressEditing = true"
            >
              Edit
            </Button>
          </CardHeader>
          <CardContent>
            <div v-if="!addressEditing" class="space-y-2 text-sm text-zinc-700">
              <div v-if="formatAddress(vendor.address)">
                <p v-if="vendor.address?.street">{{ vendor.address.street }}</p>
                <p v-if="vendor.address?.city || vendor.address?.state || vendor.address?.zip">
                  {{ [vendor.address?.city, vendor.address?.state, vendor.address?.zip].filter(Boolean).join(', ') }}
                </p>
                <p v-if="vendor.address?.country">{{ vendor.address.country }}</p>
              </div>
              <p v-else class="text-zinc-400">No address set</p>
            </div>

            <form v-else class="space-y-3" @submit.prevent="saveAddress">
              <div class="space-y-2">
                <Label for="street">Street</Label>
                <Input id="street" v-model="addressStreet" placeholder="123 Main St" />
              </div>
              <div class="grid gap-2 md:grid-cols-2">
                <div class="space-y-2">
                  <Label for="city">City</Label>
                  <Input id="city" v-model="addressCity" placeholder="New York" />
                </div>
                <div class="space-y-2">
                  <Label for="state">State</Label>
                  <Input id="state" v-model="addressState" placeholder="NY" />
                </div>
              </div>
              <div class="grid gap-2 md:grid-cols-2">
                <div class="space-y-2">
                  <Label for="zip">ZIP Code</Label>
                  <Input id="zip" v-model="addressZip" placeholder="10001" />
                </div>
                <div class="space-y-2">
                  <Label for="country">Country</Label>
                  <Input id="country" v-model="addressCountry" placeholder="US" maxlength="2" />
                </div>
              </div>
              <div class="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" size="sm" @click="cancelAddressEdit" :disabled="addressSaving">
                  Cancel
                </Button>
                <Button type="submit" size="sm" :disabled="addressSaving">
                  <span v-if="addressSaving" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                  Save
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </TabsContent>
    </Tabs>
  </PageShell>
</template>
