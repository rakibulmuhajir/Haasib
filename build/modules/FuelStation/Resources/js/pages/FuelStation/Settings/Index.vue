<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Separator } from '@/components/ui/separator'
import { Input } from '@/components/ui/input'
import type { BreadcrumbItem } from '@/types'
import { Settings, Building2, CreditCard, Wallet, Users, Save } from 'lucide-vue-next'

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
}

interface StationSettingsData {
  id: string
  fuel_vendor: string
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
    inventory: Account[]
    revenue: Account[]
    cogs: Account[]
    expense: Account[]
    equity: Account[]
  }
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Fuel Station', href: `/${props.company.slug}/fuel/dashboard` },
  { title: 'Settings', href: `/${props.company.slug}/fuel/settings` },
])

const form = useForm({
  fuel_vendor: props.settings.fuel_vendor,
  has_partners: props.settings.has_partners,
  has_amanat: props.settings.has_amanat,
  has_lubricant_sales: props.settings.has_lubricant_sales,
  has_investors: props.settings.has_investors,
  dual_meter_readings: props.settings.dual_meter_readings,
  track_attendant_handovers: props.settings.track_attendant_handovers,
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
})

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
  return labels[form.fuel_vendor] || 'Fuel Card'
})

// Update fuel card label when vendor changes
const onVendorChange = (vendor: string) => {
  form.fuel_vendor = vendor
  // Update fuel card label in payment channels
  const channels = [...form.payment_channels]
  const fuelCardIdx = channels.findIndex(ch => ch.code === 'fuel_card')
  if (fuelCardIdx !== -1) {
    channels[fuelCardIdx] = { ...channels[fuelCardIdx], label: fuelCardLabel.value }
    form.payment_channels = channels
  }
}

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

const submit = () => {
  form.put(`/${props.company.slug}/fuel/settings`)
}

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
</script>

<template>
  <Head title="Station Settings" />

  <PageShell
    title="Station Settings"
    description="Configure your fuel station module"
    :icon="Settings"
    :breadcrumbs="breadcrumbs"
  >
    <form @submit.prevent="submit" class="space-y-6">
      <!-- General Settings -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Building2 class="h-5 w-5" />
            General Settings
          </CardTitle>
          <CardDescription>Basic configuration for your fuel station</CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
          <!-- Fuel Vendor -->
          <div class="grid grid-cols-2 gap-6">
            <div class="space-y-2">
              <Label>Fuel Vendor</Label>
              <Select :model-value="form.fuel_vendor" @update:model-value="onVendorChange">
                <SelectTrigger>
                  <SelectValue placeholder="Select vendor" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="(label, code) in vendors" :key="code" :value="code">
                    {{ label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p class="text-xs text-muted-foreground">Your fuel supply company</p>
            </div>
          </div>

          <Separator />

          <!-- Feature Toggles -->
          <div class="space-y-4">
            <h4 class="font-medium">Features</h4>

            <div class="grid grid-cols-2 gap-4">
              <div class="flex items-center justify-between p-3 rounded-lg border">
                <div>
                  <Label>Partners / Shareholders</Label>
                  <p class="text-xs text-muted-foreground">Track partner investments & withdrawals</p>
                </div>
                <Switch v-model:checked="form.has_partners" />
              </div>

              <div class="flex items-center justify-between p-3 rounded-lg border">
                <div>
                  <Label>Amanat (Trust Deposits)</Label>
                  <p class="text-xs text-muted-foreground">Customer deposit accounts</p>
                </div>
                <Switch v-model:checked="form.has_amanat" />
              </div>

              <div class="flex items-center justify-between p-3 rounded-lg border">
                <div>
                  <Label>Lubricant Sales</Label>
                  <p class="text-xs text-muted-foreground">Track oil & lubricant sales</p>
                </div>
                <Switch v-model:checked="form.has_lubricant_sales" />
              </div>

              <div class="flex items-center justify-between p-3 rounded-lg border">
                <div>
                  <Label>Investors</Label>
                  <p class="text-xs text-muted-foreground">External investor lot tracking</p>
                </div>
                <Switch v-model:checked="form.has_investors" />
              </div>

              <div class="flex items-center justify-between p-3 rounded-lg border">
                <div>
                  <Label>Dual Meter Readings</Label>
                  <p class="text-xs text-muted-foreground">Electronic + manual verification</p>
                </div>
                <Switch v-model:checked="form.dual_meter_readings" />
              </div>

              <div class="flex items-center justify-between p-3 rounded-lg border">
                <div>
                  <Label>Attendant Handovers</Label>
                  <p class="text-xs text-muted-foreground">Track shift handovers</p>
                </div>
                <Switch v-model:checked="form.track_attendant_handovers" />
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Payment Channels -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <CreditCard class="h-5 w-5" />
            Payment Channels
          </CardTitle>
          <CardDescription>Configure accepted payment methods</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div v-for="channel in form.payment_channels" :key="channel.code" class="flex items-center gap-4 p-3 rounded-lg border">
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
            </div>
            <span class="text-xs text-muted-foreground capitalize">{{ channel.type.replace('_', ' ') }}</span>
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
          <CardDescription>Map GL accounts for automatic posting</CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
          <div class="grid grid-cols-2 gap-6">
            <!-- Cash Account -->
            <div class="space-y-2">
              <Label>Cash on Hand</Label>
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
            </div>

            <!-- Operating Bank -->
            <div class="space-y-2">
              <Label>Operating Bank Account</Label>
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
            </div>

            <!-- Fuel Sales -->
            <div class="space-y-2">
              <Label>Fuel Sales Revenue</Label>
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
            </div>

            <!-- Fuel COGS -->
            <div class="space-y-2">
              <Label>Fuel Cost of Goods Sold</Label>
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
            </div>

            <!-- Fuel Inventory -->
            <div class="space-y-2">
              <Label>Fuel Inventory</Label>
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
            </div>

            <!-- Cash Over/Short -->
            <div class="space-y-2">
              <Label>Cash Over/Short</Label>
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
            </div>

            <!-- Partner Drawings -->
            <div v-if="form.has_partners" class="space-y-2">
              <Label>Partner Drawings</Label>
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
            </div>

            <!-- Employee Advances -->
            <div class="space-y-2">
              <Label>Employee Advances</Label>
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
            </div>

            <!-- Fuel Card Clearing -->
            <div class="space-y-2">
              <Label>{{ fuelCardLabel }} Clearing</Label>
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
            </div>

            <!-- Card POS Clearing -->
            <div class="space-y-2">
              <Label>Card POS Clearing</Label>
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
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Submit -->
      <div class="flex justify-end">
        <Button type="submit" :disabled="form.processing" class="gap-2">
          <Save class="h-4 w-4" />
          Save Settings
        </Button>
      </div>
    </form>
  </PageShell>
</template>
