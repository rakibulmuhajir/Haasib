<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Separator } from '@/components/ui/separator'
import { Input } from '@/components/ui/input'
import InputError from '@/components/InputError.vue'
import { toast } from 'vue-sonner'
import type { BreadcrumbItem } from '@/types'
import { Settings, Building2, CreditCard, Wallet, Save, ChevronDown, LoaderCircle } from 'lucide-vue-next'

interface Account {
  id: string
  code: string
  name: string
  type: string
  subtype: string
}

interface PaymentChannel {
  code: string
  label: string
  type: 'cash' | 'bank_transfer' | 'card_pos' | 'fuel_card' | 'mobile_wallet'
  enabled: boolean
  bank_account_id: string | null
  clearing_account_id: string | null
  settles_to?: 'clearing' | 'bank' | 'supplier'
  settles_to_vendor_id?: string | null
  // Bank's charge on this channel's sales, % (e.g. 0.8, 1, 1.2)
  fee_percent?: number | null
}

interface CompanyVendor {
  id: string
  name: string
}

interface FuelProductAccountMapping {
  id: string
  name: string
  fuel_category: string
  income_account_id: string | null
  expense_account_id: string | null
  asset_account_id: string | null
}

interface StationSettingsData {
  id: string
  fuel_vendor: string
  month_end_stock_valuation: 'inventory_cost' | 'next_month_purchase_rate'
  has_partners: boolean
  has_amanat: boolean
  has_lubricant_sales: boolean
  has_investors: boolean
  dual_meter_readings: boolean
  track_attendant_handovers: boolean
  payment_channels: PaymentChannel[]
  cash_account_id: string | null
  fuel_sales_account_id: string | null
  fuel_cogs_account_id: string | null
  fuel_inventory_account_id: string | null
  cash_over_short_account_id: string | null
  partner_drawings_account_id: string | null
  employee_advances_account_id: string | null
  operating_bank_account_id: string | null
  fuel_card_clearing_account_id: string | null
  card_pos_clearing_account_id: string | null
}

const props = defineProps<{
  company: { id: string; name: string; slug: string }
  settings: StationSettingsData
  vendors: Record<string, string>
  defaultPaymentChannels: PaymentChannel[]
  accountsByType: {
    cash: Account[]
    bank: Account[]
    receivable: Account[]
    clearing: Account[]
    inventory: Account[]
    revenue: Account[]
    cogs: Account[]
    expense: Account[]
    equity: Account[]
  }
  fuelProducts: FuelProductAccountMapping[]
  companyVendors: CompanyVendor[]
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Fuel Station', href: `/${props.company.slug}/fuel/dashboard` },
  { title: 'Settings', href: `/${props.company.slug}/fuel/settings` },
])

const form = useForm({
  month_end_stock_valuation: props.settings.month_end_stock_valuation,
  has_partners: props.settings.has_partners,
  has_amanat: props.settings.has_amanat,
  has_lubricant_sales: props.settings.has_lubricant_sales,
  has_investors: props.settings.has_investors,
  dual_meter_readings: props.settings.dual_meter_readings,
  payment_channels: props.settings.payment_channels,
  cash_account_id: props.settings.cash_account_id,
  fuel_sales_account_id: props.settings.fuel_sales_account_id,
  fuel_cogs_account_id: props.settings.fuel_cogs_account_id,
  fuel_inventory_account_id: props.settings.fuel_inventory_account_id,
  cash_over_short_account_id: props.settings.cash_over_short_account_id,
  partner_drawings_account_id: props.settings.partner_drawings_account_id,
  employee_advances_account_id: props.settings.employee_advances_account_id,
  operating_bank_account_id: props.settings.operating_bank_account_id,
  fuel_card_clearing_account_id: props.settings.fuel_card_clearing_account_id,
  card_pos_clearing_account_id: props.settings.card_pos_clearing_account_id,
  fuel_products: props.fuelProducts.map(product => ({
    id: product.id,
    name: product.name,
    fuel_category: product.fuel_category,
    income_account_id: product.income_account_id,
    expense_account_id: product.expense_account_id,
    asset_account_id: product.asset_account_id,
  })),
})

const accountMappingsOpen = ref(false)
const fallbackMappingsOpen = ref(false)

// Get fuel card label based on vendor
const fuelCardLabel = computed(() => {
  const labels: Record<string, string> = {
    parco: 'Vendor Card',
    pso: 'PSO Card',
    shell: 'Shell Card',
    total: 'Total Card',
    caltex: 'Caltex Card',
    attock: 'APL Card',
    hascol: 'Hascol Card',
    byco: 'Byco Card',
    go: 'GO Card',
  }
  return labels[props.settings.fuel_vendor] || 'Fuel Card'
})


// Toggle payment channel
const toggleChannel = (code: string, enabled: boolean) => {
  const channels = [...form.payment_channels]
  const idx = channels.findIndex(ch => ch.code === code)
  if (idx !== -1) {
    channels[idx] = { ...channels[idx], enabled }
    form.payment_channels = channels
  }
}

// Update channel label
const updateChannelLabel = (code: string, label: string) => {
  const channels = [...form.payment_channels]
  const idx = channels.findIndex(ch => ch.code === code)
  if (idx !== -1) {
    channels[idx] = { ...channels[idx], label }
    form.payment_channels = channels
  }
}

const updateChannelAccount = (code: string, key: 'bank_account_id' | 'clearing_account_id', value: string | null) => {
  const channels = [...form.payment_channels]
  const idx = channels.findIndex(ch => ch.code === code)
  if (idx !== -1) {
    channels[idx] = { ...channels[idx], [key]: value || null }
    form.payment_channels = channels
  }
}

const updateChannelFee = (code: string, value: string | number) => {
  const channels = [...form.payment_channels]
  const idx = channels.findIndex(ch => ch.code === code)
  if (idx !== -1) {
    channels[idx] = { ...channels[idx], fee_percent: value === '' || value === null ? null : Number(value) }
    form.payment_channels = channels
  }
}

const updateChannelSettlesTo = (code: string, value: 'clearing' | 'bank' | 'supplier') => {
  const channels = [...form.payment_channels]
  const idx = channels.findIndex(ch => ch.code === code)
  if (idx !== -1) {
    channels[idx] = { ...channels[idx], settles_to: value }
    form.payment_channels = channels
  }
}

const updateChannelVendor = (code: string, value: string | null) => {
  const channels = [...form.payment_channels]
  const idx = channels.findIndex(ch => ch.code === code)
  if (idx !== -1) {
    channels[idx] = { ...channels[idx], settles_to_vendor_id: value || null }
    form.payment_channels = channels
  }
}

const settlesToOptions = [
  { value: 'clearing', label: 'Clearing account — settle later on the Settlements screen' },
  { value: 'bank', label: 'Bank — straight to the bank at the daily close' },
  { value: 'supplier', label: 'Supplier — paid to the supplier at the daily close' },
]

const settlesToHint = (value: string | undefined) => {
  if (value === 'bank') {
    return 'The close debits the bank account below directly for these sales — nothing is parked in clearing.'
  }
  if (value === 'supplier') {
    return 'The close still posts these sales to the clearing account below, then immediately pays the chosen supplier from it, oldest bill first.'
  }
  return 'These sales sit in the clearing account below until someone settles them on the Settlements screen.'
}

const submit = () => {
  form.put(`/${props.company.slug}/fuel/settings`, {
    onError: () => toast.error('Check the highlighted settings and try again.'),
  })
}

/** Laravel returns these as `payment_channels.0.label`. */
const channelError = (index: number, field: string) =>
  (form.errors as Record<string, string>)[`payment_channels.${index}.${field}`]

/** Laravel returns these as `fuel_products.0.income_account_id`. */
const productError = (index: number, field: string) =>
  (form.errors as Record<string, string>)[`fuel_products.${index}.${field}`]

// All accounts for generic dropdowns
const allAccounts = computed(() => {
  return [
    ...props.accountsByType.cash,
    ...props.accountsByType.bank,
    ...props.accountsByType.receivable,
    ...props.accountsByType.inventory,
    ...props.accountsByType.revenue,
    ...props.accountsByType.cogs,
    ...props.accountsByType.expense,
    ...props.accountsByType.equity,
  ].sort((a, b) => a.code.localeCompare(b.code))
})

const accountLabel = (accountId: string | null) => {
  if (!accountId) return 'Automatic account pending'
  const account = allAccounts.value.find(acc => acc.id === accountId)
  return account ? `${account.code} - ${account.name}` : 'Automatic account pending'
}

const fallbackAccountSummaries = computed(() => [
  { label: 'Cash on Hand', accountId: form.cash_account_id },
  { label: 'Operating Bank', accountId: form.operating_bank_account_id },
  { label: 'Fallback Fuel Sales', accountId: form.fuel_sales_account_id },
  { label: 'Fallback Fuel COGS', accountId: form.fuel_cogs_account_id },
  { label: 'Fallback Fuel Inventory', accountId: form.fuel_inventory_account_id },
  { label: 'Cash Over/Short', accountId: form.cash_over_short_account_id },
  { label: 'Partner Drawings', accountId: form.partner_drawings_account_id, show: form.has_partners },
  { label: 'Employee Advances', accountId: form.employee_advances_account_id },
  { label: `${fuelCardLabel.value} Clearing`, accountId: form.fuel_card_clearing_account_id },
  { label: 'Card POS Clearing', accountId: form.card_pos_clearing_account_id },
].filter(item => item.show !== false))

const featureExplanations = {
  has_partners: 'Turn on when owners or partners put money into the station or take drawings from station cash.',
  has_amanat: 'Turn on when customers leave deposits with you and later buy fuel against that balance.',
  has_lubricant_sales: 'Turn on if you sell engine oil or other lubricants and want them included in station sales.',
  has_investors: 'Turn on only if outside investors fund fuel lots and need separate lot/entitlement tracking.',
  dual_meter_readings: 'Turn on when staff record both electronic and manual readings for extra verification.',
}

const paymentChannelHelp = (channel: PaymentChannel) => {
  if (channel.type === 'bank_transfer') {
    return 'Select the bank that receives direct transfers. No clearing account is needed because the money is already in the bank.'
  }
  if (channel.type === 'card_pos') {
    return 'Select a clearing account first. Use the bank field for the account where the POS provider settles the batch later.'
  }
  if (channel.type === 'fuel_card') {
    return 'Select a clearing account first. It tracks the amount the fuel-card company owes until settlement is received.'
  }
  if (channel.type === 'mobile_wallet') {
    return 'Use a clearing account if wallet payments settle later, or a bank account if the wallet is treated like a bank balance.'
  }
  return 'Cash uses the Cash on Hand account below.'
}

const accountMappingHelp = {
  cash: 'Pick the account that represents physical cash in the drawer or till. Cash sales increase this account; cash payouts reduce it.',
  bank: 'Pick the main bank account where daily deposits and settled payment receipts normally arrive.',
  sales: 'Fallback income category used only when a fuel product below does not have its own sales account.',
  cogs: 'Fallback purchase-cost category used only when a fuel product below does not have its own COGS account.',
  inventory: 'Fallback stock asset used only when a fuel product below does not have its own inventory account.',
  overShort: 'Pick the small difference category used when counted cash is higher or lower than expected.',
  drawings: 'Pick the equity account for owner or partner withdrawals. This is not treated as a station expense.',
  advances: 'Pick the receivable account for money given to employees that will be recovered later.',
  fuelCardClearing: 'Pick the temporary receivable for fuel-card sales before the vendor sends payment.',
  cardClearing: 'Pick the temporary receivable for POS/card sales before the card processor deposits the money.',
}

const formatFuelCategory = (category: string | null) => {
  if (!category) return 'Fuel'
  return category
    .split('_')
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

</script>

<template>
  <Head title="Station Settings" />

  <PageShell
    title="Station Settings"
    description="Maintain fuel station features, payment channels, and daily close posting accounts after the setup wizard"
    :icon="Settings"
    :breadcrumbs="breadcrumbs"
  >
    <form novalidate @submit.prevent="submit" class="space-y-6">
      <!-- General Settings -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Building2 class="h-5 w-5" />
            General Settings
          </CardTitle>
          <CardDescription>Choose the station behavior you actually use. Disabled features stay out of daily forms.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
          <!-- Feature Toggles -->
          <div class="space-y-4">
            <h4 class="font-medium">Features</h4>

            <div class="grid grid-cols-2 gap-4">
              <div class="flex items-center justify-between p-3 rounded-lg border">
                <div>
                  <div class="text-sm font-medium">Partners / Shareholders</div>
                  <p class="text-xs text-muted-foreground">{{ featureExplanations.has_partners }}</p>
                </div>
                <Switch v-model:checked="form.has_partners" />
                <InputError :message="form.errors.has_partners" />
              </div>

              <div class="flex items-center justify-between p-3 rounded-lg border">
                <div>
                  <div class="text-sm font-medium">Amanat (Trust Deposits)</div>
                  <p class="text-xs text-muted-foreground">{{ featureExplanations.has_amanat }}</p>
                </div>
                <Switch v-model:checked="form.has_amanat" />
                <InputError :message="form.errors.has_amanat" />
              </div>

              <div class="flex items-center justify-between p-3 rounded-lg border">
                <div>
                  <div class="text-sm font-medium">Lubricant Sales</div>
                  <p class="text-xs text-muted-foreground">{{ featureExplanations.has_lubricant_sales }}</p>
                </div>
                <Switch v-model:checked="form.has_lubricant_sales" />
                <InputError :message="form.errors.has_lubricant_sales" />
              </div>

              <div class="flex items-center justify-between p-3 rounded-lg border">
                <div>
                  <div class="text-sm font-medium">Investors</div>
                  <p class="text-xs text-muted-foreground">{{ featureExplanations.has_investors }}</p>
                </div>
                <Switch v-model:checked="form.has_investors" />
                <InputError :message="form.errors.has_investors" />
              </div>

              <div class="flex items-center justify-between p-3 rounded-lg border">
                <div>
                  <div class="text-sm font-medium">Dual Meter Readings</div>
                  <p class="text-xs text-muted-foreground">{{ featureExplanations.dual_meter_readings }}</p>
                </div>
                <Switch v-model:checked="form.dual_meter_readings" />
                <InputError :message="form.errors.dual_meter_readings" />
              </div>

            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Monthly fuel profit</CardTitle>
          <CardDescription>Choose how the station performance report values fuel left in the tanks. Locked monthly reports retain the method and values used when they were locked.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-2">
          <Label for="month_end_stock_valuation">Month-end remaining stock</Label>
          <Select v-model="form.month_end_stock_valuation">
            <SelectTrigger id="month_end_stock_valuation" class="max-w-md"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="inventory_cost">Recorded inventory cost</SelectItem>
              <SelectItem value="next_month_purchase_rate">Purchase rate on the next month's first day</SelectItem>
            </SelectContent>
          </Select>
          <!-- How each choice works out a month's fuel profit, in plain words, with a worked example. -->
          <div class="max-w-2xl rounded-md border border-rule-default p-3 text-sm">
            <p class="mb-2 font-medium">How a month's fuel profit is worked out</p>
            <template v-if="form.month_end_stock_valuation === 'next_month_purchase_rate'">
              <ol class="list-decimal space-y-0.5 pl-5">
                <li>Sales for the month</li>
                <li>+ fuel left in the tank (closing dip) × the purchase rate on the 1st of next month</li>
                <li>− last month's stock (its closing value, carried in)</li>
                <li>− everything bought this month (bills)</li>
                <li>= fuel profit — tank gains and losses are included, as the dip counts what is really there</li>
              </ol>
              <p class="mt-2 text-xs text-muted-foreground">
                Example, diesel: sales 14,415,246 + 725 L × 395.80 = 286,955 − 2,671,421 last month − 11,388,054 bought = 642,727.
                Last month's stock is counted in Bought on the stock statement and month summary.
                The books value that stock at the new rate on the month's last day, up or down, so their profit matches this.
              </p>
            </template>
            <template v-else>
              <ol class="list-decimal space-y-0.5 pl-5">
                <li>Sales for the month</li>
                <li>− what the fuel sold cost, at the average cost of the stock each day</li>
                <li>± tank gains and losses, shown on their own</li>
                <li>= fuel profit</li>
              </ol>
              <p class="mt-2 text-xs text-muted-foreground">
                Fuel left over stays at what it cost, or at the new rate if that is lower; a rise shows up only when it is sold. Last month's stock sits on its own Opening line, not in Bought.
              </p>
            </template>
          </div>
          <InputError :message="form.errors.month_end_stock_valuation" />
        </CardContent>
      </Card>

      <!-- Payment Channels -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <CreditCard class="h-5 w-5" />
            Payment Channels
          </CardTitle>
          <CardDescription>Enable only the ways customers pay you. Each enabled method needs enough routing for daily close.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div v-for="(channel, channelIndex) in form.payment_channels" :key="channel.code" class="space-y-3 rounded-lg border p-3">
            <div class="flex items-center gap-4">
              <Switch
                :checked="channel.enabled"
                @update:checked="(val: boolean) => toggleChannel(channel.code, val)"
                :disabled="channel.code === 'cash'"
              />
              <div class="flex-1">
                <Input
                  :model-value="channel.label"
                  @update:model-value="(val: string) => updateChannelLabel(channel.code, val)"
                  class="max-w-xs"
                  :disabled="channel.code === 'cash'"
                />
                <InputError :message="channelError(channelIndex, 'label')" />
              </div>
              <span class="text-xs text-muted-foreground capitalize">{{ channel.type.replace('_', ' ') }}</span>
            </div>
            <InputError :message="channelError(channelIndex, 'enabled')" />
            <p class="pl-12 text-xs text-muted-foreground">{{ paymentChannelHelp(channel) }}</p>

            <div v-if="channel.enabled && channel.code !== 'cash'" class="grid grid-cols-1 gap-3 pl-12 md:grid-cols-2">
              <div v-if="['bank_transfer', 'card_pos', 'fuel_card', 'mobile_wallet'].includes(channel.type)" class="space-y-2">
                <Label class="text-xs">Settlement / Destination Bank</Label>
                <p class="text-xs text-muted-foreground">The real bank account where the money finally lands.</p>
                <Select
                  :model-value="channel.bank_account_id || undefined"
                  @update:model-value="(val: string) => updateChannelAccount(channel.code, 'bank_account_id', val)"
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select bank account" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="acc in accountsByType.bank" :key="acc.id" :value="acc.id">
                      {{ acc.code }} - {{ acc.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <InputError :message="channelError(channelIndex, 'bank_account_id')" />
              </div>

              <div v-if="['card_pos', 'fuel_card', 'mobile_wallet'].includes(channel.type)" class="space-y-2">
                <Label class="text-xs">Clearing Account</Label>
                <p class="text-xs text-muted-foreground">Use this when someone owes you the money before it reaches the bank.</p>
                <Select
                  :model-value="channel.clearing_account_id || undefined"
                  @update:model-value="(val: string) => updateChannelAccount(channel.code, 'clearing_account_id', val)"
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select clearing account" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="acc in accountsByType.clearing" :key="acc.id" :value="acc.id">
                      {{ acc.code }} - {{ acc.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <InputError :message="channelError(channelIndex, 'clearing_account_id')" />
              </div>

              <div v-if="['card_pos', 'fuel_card', 'mobile_wallet', 'bank_transfer'].includes(channel.type)" class="space-y-2">
                <Label :for="`fee-${channel.code}`" class="text-xs">Bank charge %</Label>
                <Input
                  :id="`fee-${channel.code}`"
                  :model-value="channel.fee_percent ?? ''"
                  type="number"
                  min="0"
                  max="10"
                  step="0.01"
                  placeholder="0"
                  class="w-28"
                  @update:model-value="(v) => updateChannelFee(channel.code, v)"
                />
                <InputError :message="channelError(channelIndex, 'fee_percent')" />
              </div>

              <div v-if="['card_pos', 'fuel_card', 'mobile_wallet'].includes(channel.type)" class="space-y-2 md:col-span-2">
                <Label class="text-xs">Settles to</Label>
                <p class="text-xs text-muted-foreground">Where this channel's payment goes when the day is closed — their payment goes straight to that bank or the supplier respectively, instead of always waiting in clearing.</p>
                <Select
                  :model-value="channel.settles_to || 'clearing'"
                  @update:model-value="(val: 'clearing' | 'bank' | 'supplier') => updateChannelSettlesTo(channel.code, val)"
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select how this settles" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="opt in settlesToOptions" :key="opt.value" :value="opt.value">
                      {{ opt.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">{{ settlesToHint(channel.settles_to) }}</p>
                <InputError :message="channelError(channelIndex, 'settles_to')" />
              </div>

              <div v-if="['card_pos', 'fuel_card', 'mobile_wallet'].includes(channel.type) && channel.settles_to === 'supplier'" class="space-y-2 md:col-span-2">
                <Label class="text-xs">Supplier</Label>
                <p class="text-xs text-muted-foreground">The vendor this channel's sales pay off at the daily close (e.g. the oil company issuing the fuel card).</p>
                <Select
                  :model-value="channel.settles_to_vendor_id || undefined"
                  @update:model-value="(val: string) => updateChannelVendor(channel.code, val)"
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select supplier" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="vendor in companyVendors" :key="vendor.id" :value="vendor.id">
                      {{ vendor.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <InputError :message="channelError(channelIndex, 'settles_to_vendor_id')" />
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Account Mappings -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Wallet class="h-5 w-5" />
            Account Mappings
          </CardTitle>
          <CardDescription>Fuel product accounts are mapped automatically. Open advanced controls only when you need to override them.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
          <div class="rounded-lg border bg-muted/30 p-4">
            <div class="font-semibold">Automatic fuel account mapping</div>
            <p class="text-sm text-muted-foreground">
              Petrol, Diesel, Hi-Octane, and lubricant products are linked to matching sales, cost, and inventory accounts when they are created.
            </p>
          </div>

          <Collapsible v-model:open="accountMappingsOpen" class="space-y-4">
            <CollapsibleTrigger as-child>
              <Button type="button" variant="outline" class="w-full justify-between">
                Advanced account overrides
                <ChevronDown class="h-4 w-4 transition-transform" :class="accountMappingsOpen ? 'rotate-180' : ''" />
              </Button>
            </CollapsibleTrigger>

            <CollapsibleContent class="space-y-6">
              <div v-if="form.fuel_products.length" class="space-y-4 rounded-lg border p-4">
                <div>
                  <div class="font-semibold">Fuel product account detail</div>
                  <p class="text-sm text-muted-foreground">
                    These are filled automatically. Change them only if your accountant wants a different reporting layout.
                  </p>
                </div>

                <div class="space-y-4">
                  <div
                    v-for="(product, productIndex) in form.fuel_products"
                    :key="product.id"
                    class="rounded-lg border bg-muted/20 p-3"
                  >
                    <div class="mb-3 flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                      <div class="font-medium">{{ product.name }}</div>
                      <div class="text-xs text-muted-foreground">{{ formatFuelCategory(product.fuel_category) }}</div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                      <div class="space-y-2">
                        <div class="text-xs font-medium">Sales income account</div>
                        <Select v-model="product.income_account_id">
                          <SelectTrigger>
                            <SelectValue placeholder="Auto-mapped sales account" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem v-for="acc in accountsByType.revenue" :key="acc.id" :value="acc.id">
                              {{ acc.code }} - {{ acc.name }}
                            </SelectItem>
                          </SelectContent>
                        </Select>
                        <InputError :message="productError(productIndex, 'income_account_id')" />
                      </div>

                      <div class="space-y-2">
                        <div class="text-xs font-medium">Cost account</div>
                        <Select v-model="product.expense_account_id">
                          <SelectTrigger>
                            <SelectValue placeholder="Auto-mapped cost account" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem v-for="acc in accountsByType.cogs" :key="acc.id" :value="acc.id">
                              {{ acc.code }} - {{ acc.name }}
                            </SelectItem>
                          </SelectContent>
                        </Select>
                        <InputError :message="productError(productIndex, 'expense_account_id')" />
                      </div>

                      <div class="space-y-2">
                        <div class="text-xs font-medium">Inventory account</div>
                        <Select v-model="product.asset_account_id">
                          <SelectTrigger>
                            <SelectValue placeholder="Auto-mapped inventory account" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem v-for="acc in accountsByType.inventory" :key="acc.id" :value="acc.id">
                              {{ acc.code }} - {{ acc.name }}
                            </SelectItem>
                          </SelectContent>
                        </Select>
                        <InputError :message="productError(productIndex, 'asset_account_id')" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="rounded-lg border bg-muted/20 p-4 space-y-4">
                <div>
                  <div class="font-semibold">Automatic fallback accounts</div>
                  <p class="text-sm text-muted-foreground">
                    These are selected for you and used only when a product or payment channel has no detailed mapping.
                  </p>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                  <div
                    v-for="item in fallbackAccountSummaries"
                    :key="item.label"
                    class="rounded-md border bg-background p-3"
                  >
                    <div class="text-xs font-medium text-muted-foreground">{{ item.label }}</div>
                    <div class="mt-1 text-sm font-medium">{{ accountLabel(item.accountId) }}</div>
                  </div>
                </div>
              </div>

              <Collapsible v-model:open="fallbackMappingsOpen" class="space-y-4">
                <CollapsibleTrigger as-child>
                  <Button type="button" variant="outline" class="w-full justify-between">
                    Change fallback account overrides
                    <ChevronDown class="h-4 w-4 transition-transform" :class="fallbackMappingsOpen ? 'rotate-180' : ''" />
                  </Button>
                </CollapsibleTrigger>

                <CollapsibleContent class="space-y-4">
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Cash Account -->
            <div class="space-y-2">
              <div class="text-sm font-medium">Cash on Hand</div>
              <p class="text-xs text-muted-foreground">{{ accountMappingHelp.cash }}</p>
              <Select v-model="form.cash_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="acc in accountsByType.cash" :key="acc.id" :value="acc.id">
                    {{ acc.code }} - {{ acc.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.cash_account_id" />
            </div>

            <!-- Operating Bank -->
            <div class="space-y-2">
              <div class="text-sm font-medium">Operating Bank Account</div>
              <p class="text-xs text-muted-foreground">{{ accountMappingHelp.bank }}</p>
              <Select v-model="form.operating_bank_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="acc in accountsByType.bank" :key="acc.id" :value="acc.id">
                    {{ acc.code }} - {{ acc.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.operating_bank_account_id" />
            </div>

            <!-- Fuel Sales -->
            <div class="space-y-2">
              <div class="text-sm font-medium">Fallback Fuel Sales Revenue</div>
              <p class="text-xs text-muted-foreground">{{ accountMappingHelp.sales }}</p>
              <Select v-model="form.fuel_sales_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="acc in accountsByType.revenue" :key="acc.id" :value="acc.id">
                    {{ acc.code }} - {{ acc.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.fuel_sales_account_id" />
            </div>

            <!-- Fuel COGS -->
            <div class="space-y-2">
              <div class="text-sm font-medium">Fallback Fuel Cost of Goods Sold</div>
              <p class="text-xs text-muted-foreground">{{ accountMappingHelp.cogs }}</p>
              <Select v-model="form.fuel_cogs_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="acc in accountsByType.cogs" :key="acc.id" :value="acc.id">
                    {{ acc.code }} - {{ acc.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.fuel_cogs_account_id" />
            </div>

            <!-- Fuel Inventory -->
            <div class="space-y-2">
              <div class="text-sm font-medium">Fallback Fuel Inventory</div>
              <p class="text-xs text-muted-foreground">{{ accountMappingHelp.inventory }}</p>
              <Select v-model="form.fuel_inventory_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="acc in accountsByType.inventory" :key="acc.id" :value="acc.id">
                    {{ acc.code }} - {{ acc.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.fuel_inventory_account_id" />
            </div>

            <!-- Cash Over/Short -->
            <div class="space-y-2">
              <div class="text-sm font-medium">Cash Over/Short</div>
              <p class="text-xs text-muted-foreground">{{ accountMappingHelp.overShort }}</p>
              <Select v-model="form.cash_over_short_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="acc in accountsByType.expense" :key="acc.id" :value="acc.id">
                    {{ acc.code }} - {{ acc.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.cash_over_short_account_id" />
            </div>

            <!-- Partner Drawings -->
            <div v-if="form.has_partners" class="space-y-2">
              <div class="text-sm font-medium">Partner Drawings</div>
              <p class="text-xs text-muted-foreground">{{ accountMappingHelp.drawings }}</p>
              <Select v-model="form.partner_drawings_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="acc in accountsByType.equity" :key="acc.id" :value="acc.id">
                    {{ acc.code }} - {{ acc.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.partner_drawings_account_id" />
            </div>

            <!-- Employee Advances -->
            <div class="space-y-2">
              <div class="text-sm font-medium">Employee Advances</div>
              <p class="text-xs text-muted-foreground">{{ accountMappingHelp.advances }}</p>
              <Select v-model="form.employee_advances_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="acc in accountsByType.receivable" :key="acc.id" :value="acc.id">
                    {{ acc.code }} - {{ acc.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.employee_advances_account_id" />
            </div>

            <!-- Fuel Card Clearing -->
            <div class="space-y-2">
              <div class="text-sm font-medium">{{ fuelCardLabel }} Clearing</div>
              <p class="text-xs text-muted-foreground">{{ accountMappingHelp.fuelCardClearing }}</p>
              <Select v-model="form.fuel_card_clearing_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="acc in allAccounts" :key="acc.id" :value="acc.id">
                    {{ acc.code }} - {{ acc.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.fuel_card_clearing_account_id" />
            </div>

            <!-- Card POS Clearing -->
            <div class="space-y-2">
              <div class="text-sm font-medium">Card POS Clearing</div>
              <p class="text-xs text-muted-foreground">{{ accountMappingHelp.cardClearing }}</p>
              <Select v-model="form.card_pos_clearing_account_id">
                <SelectTrigger>
                  <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="acc in allAccounts" :key="acc.id" :value="acc.id">
                    {{ acc.code }} - {{ acc.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="form.errors.card_pos_clearing_account_id" />
            </div>
          </div>
                </CollapsibleContent>
              </Collapsible>
            </CollapsibleContent>
          </Collapsible>
        </CardContent>
      </Card>

      <!-- Submit -->
      <div class="flex justify-end">
        <Button type="submit" :disabled="form.processing" class="gap-2">
          <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
          <Save v-else class="h-4 w-4" />
          {{ form.processing ? 'Saving…' : 'Save Settings' }}
        </Button>
      </div>
    </form>
  </PageShell>
</template>
