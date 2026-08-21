<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Building2, Loader2, Globe } from 'lucide-vue-next'

interface Currency {
  code: string
  name: string
  symbol: string
}

interface Country {
  code: string
  name: string
  currency: string
  timezone: string
}

interface Industry {
  code: string
  name: string
  description?: string | null
}

interface UserOption {
  id: string
  name: string
  email: string
}

interface Props {
  currencies: Currency[]
  countries: Country[]
  industries: Industry[]
  users: UserOption[]
  canAssignOwner: boolean
}

const props = defineProps<Props>()

const form = useForm({
  name: '',
  owner_user_id: '',
  industry_code: '',
  country: '',
  base_currency: '',
  timezone: '',
  secondary_currency: '',
  secondary_exchange_rate: '',
})

// Find selected country details
const selectedCountry = computed(() => {
  return props.countries.find(c => c.code === form.country)
})

// Auto-fill currency and timezone when country changes
watch(() => form.country, (countryCode) => {
  const country = props.countries.find(c => c.code === countryCode)
  if (country) {
    // Auto-fill currency if available in our currencies list
    const currencyExists = props.currencies.some(c => c.code === country.currency)
    if (currencyExists) {
      form.base_currency = country.currency.toUpperCase()
    } else if (!form.base_currency) {
      form.base_currency = props.currencies[0]?.code || 'USD'
    }
    // Auto-fill timezone
    form.timezone = country.timezone
  }
})

const selectedIndustry = computed(() => {
  return props.industries.find(industry => industry.code === form.industry_code)
})

// Secondary currency options exclude whatever is currently primary
const secondaryCurrencyOptions = computed(() => {
  return props.currencies.filter(c => c.code !== form.base_currency)
})

const NONE_SECONDARY_CURRENCY = 'none'

// The Select needs a non-empty sentinel for "no secondary currency"; translate
// it to/from the empty string the backend expects.
const secondaryCurrencyModel = computed({
  get: () => form.secondary_currency || NONE_SECONDARY_CURRENCY,
  set: (value: string) => {
    form.secondary_currency = value === NONE_SECONDARY_CURRENCY ? '' : value
  },
})

// If the primary currency changes to match the chosen secondary, reset the secondary
watch(() => form.base_currency, (baseCurrency) => {
  if (form.secondary_currency && form.secondary_currency === baseCurrency) {
    form.secondary_currency = ''
    form.secondary_exchange_rate = ''
  }
})

// Clear the exchange rate whenever the secondary currency is cleared
watch(() => form.secondary_currency, (secondaryCurrency) => {
  if (!secondaryCurrency) {
    form.secondary_exchange_rate = ''
  }
})

const submit = () => {
  form.post('/companies')
}
</script>

<template>
  <Head title="Create Company" />

  <div class="min-h-screen bg-surface-canvas">
    <div class="container mx-auto px-4 py-16 max-w-2xl">
      <!-- Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-status-info/15 mb-4">
          <Building2 class="w-8 h-8 text-status-info" />
        </div>
        <h1 class="text-3xl font-bold text-text-primary mb-2">
          Create Your Company
        </h1>
        <p class="text-text-secondary">
          Tell us a few basics and we'll prepare the defaults automatically.
        </p>
      </div>

      <!-- Form Card -->
      <Card variant="form">
        <CardHeader>
          <CardTitle>Company Information</CardTitle>
          <CardDescription>
            Enter your basic company details. Currency and timezone are set from your country.
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form novalidate @submit.prevent="submit" class="space-y-6">
            <div v-if="canAssignOwner" class="space-y-2">
              <Label for="owner" class="font-medium">
                Owner <span class="text-status-critical">*</span>
              </Label>
              <Select v-model="form.owner_user_id" required>
                <SelectTrigger id="owner">
                  <SelectValue placeholder="Select the user who will own this company..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="user in users"
                    :key="user.id"
                    :value="user.id"
                  >
                    {{ user.name }} · {{ user.email }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.owner_user_id" class="text-sm text-status-critical">
                {{ form.errors.owner_user_id }}
              </p>
            </div>

            <!-- Company Name -->
            <div class="space-y-2">
              <Label for="name" class="font-medium">
                Company Name <span class="text-status-critical">*</span>
              </Label>
              <Input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="e.g., Acme Corporation"
                required
                autofocus
              />
              <p class="text-xs text-text-secondary">
                This is your legal business name
              </p>
              <p v-if="form.errors.name" class="text-sm text-status-critical">
                {{ form.errors.name }}
              </p>
            </div>

            <!-- Industry -->
            <div class="space-y-2">
              <Label for="industry" class="font-medium">
                Industry <span class="text-status-critical">*</span>
              </Label>
              <Select v-model="form.industry_code" required>
                <SelectTrigger id="industry">
                  <SelectValue placeholder="Select your industry..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="industry in industries"
                    :key="industry.code"
                    :value="industry.code"
                  >
                    {{ industry.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="selectedIndustry?.description" class="text-xs text-text-secondary">
                {{ selectedIndustry.description }}
              </p>
              <p v-if="form.errors.industry_code" class="text-sm text-status-critical">
                {{ form.errors.industry_code }}
              </p>
            </div>

            <!-- Country -->
            <div class="space-y-2">
              <Label for="country" class="font-medium">
                Country <span class="text-status-critical">*</span>
              </Label>
              <Select v-model="form.country" required>
                <SelectTrigger id="country">
                  <SelectValue placeholder="Select your country..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="country in countries"
                    :key="country.code"
                    :value="country.code"
                  >
                    {{ country.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p class="text-xs text-text-secondary">
                Your country determines the default currency and timezone
              </p>
              <p v-if="form.errors.country" class="text-sm text-status-critical">
                {{ form.errors.country }}
              </p>
            </div>

            <!-- Primary Currency -->
            <div class="space-y-2">
              <Label for="base_currency" class="font-medium">
                Primary currency <span class="text-status-critical">*</span>
              </Label>
              <Select v-model="form.base_currency" required>
                <SelectTrigger id="base_currency">
                  <SelectValue placeholder="Select a currency..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="currency in currencies"
                    :key="currency.code"
                    :value="currency.code"
                  >
                    {{ currency.code }} - {{ currency.name }} ({{ currency.symbol }})
                  </SelectItem>
                </SelectContent>
              </Select>
              <p class="text-xs text-text-secondary">
                Your country sets the default, but you can change it.
              </p>
              <p v-if="form.industry_code === 'travel'" class="text-xs text-text-secondary">
                Travel companies often bill in Saudi Riyal even when based elsewhere. Pick the currency your books are kept in.
              </p>
              <p v-if="form.errors.base_currency" class="text-sm text-status-critical">
                {{ form.errors.base_currency }}
              </p>
            </div>

            <!-- Secondary Currency (optional) -->
            <div class="space-y-2">
              <Label for="secondary_currency" class="font-medium">
                Secondary currency
              </Label>
              <Select v-model="secondaryCurrencyModel">
                <SelectTrigger id="secondary_currency">
                  <SelectValue placeholder="None" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="NONE_SECONDARY_CURRENCY">
                    None
                  </SelectItem>
                  <SelectItem
                    v-for="currency in secondaryCurrencyOptions"
                    :key="currency.code"
                    :value="currency.code"
                  >
                    {{ currency.code }} - {{ currency.name }} ({{ currency.symbol }})
                  </SelectItem>
                </SelectContent>
              </Select>
              <p class="text-xs text-text-secondary">
                Optional. Transactions may be recorded in either currency, and the rate can be updated later in Settings.
              </p>
              <p v-if="form.errors.secondary_currency" class="text-sm text-status-critical">
                {{ form.errors.secondary_currency }}
              </p>

              <div v-if="form.secondary_currency" class="space-y-2 pt-2">
                <Label for="secondary_exchange_rate" class="font-medium">
                  1 {{ form.secondary_currency }} = ___ {{ form.base_currency }}
                </Label>
                <Input
                  id="secondary_exchange_rate"
                  v-model="form.secondary_exchange_rate"
                  type="number"
                  step="0.00000001"
                  min="0"
                  placeholder="e.g., 75.00"
                  required
                />
                <p v-if="form.errors.secondary_exchange_rate" class="text-sm text-status-critical">
                  {{ form.errors.secondary_exchange_rate }}
                </p>
              </div>
            </div>

            <!-- Auto-filled info card -->
            <div v-if="selectedCountry" class="rounded-lg border border-status-info/30 bg-status-info/10 p-4">
              <div class="flex items-center gap-2 mb-3">
                <Globe class="w-4 h-4 text-status-info" />
                <span class="text-sm font-medium text-text-primary">Auto-configured for {{ selectedCountry.name }}</span>
              </div>
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span class="text-text-secondary">Timezone:</span>
                  <span class="ml-2 font-medium text-text-primary">{{ form.timezone }}</span>
                </div>
              </div>
              <p class="text-xs text-text-secondary mt-3">
                You can review these defaults in Settings anytime.
              </p>
            </div>

            <!-- Submit Button -->
            <div class="pt-4">
              <Button
                type="submit"
                :disabled="form.processing || (canAssignOwner && !form.owner_user_id) || !form.country || !form.industry_code || !form.base_currency || (!!form.secondary_currency && !form.secondary_exchange_rate)"
                class="w-full"
                size="lg"
              >
                <Loader2 v-if="form.processing" class="w-4 h-4 mr-2 animate-spin" />
                Create Company
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <!-- Help Text -->
      <div class="text-center mt-6 text-sm text-text-secondary">
        <p>Need help? <a href="#" class="text-status-info hover:underline">Contact support</a></p>
      </div>
    </div>
  </div>
</template>
