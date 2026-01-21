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

interface Props {
  currencies: Currency[]
  countries: Country[]
  industries: Industry[]
}

const props = defineProps<Props>()

const form = useForm({
  name: '',
  industry_code: '',
  country: '',
  base_currency: '',
  timezone: '',
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

// Get currency display info
const selectedCurrency = computed(() => {
  return props.currencies.find(c => c.code === form.base_currency)
})

const selectedIndustry = computed(() => {
  return props.industries.find(industry => industry.code === form.industry_code)
})

const submit = () => {
  form.post('/companies')
}
</script>

<template>
  <Head title="Create Company" />

  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="container mx-auto px-4 py-16 max-w-2xl">
      <!-- Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900 mb-4">
          <Building2 class="w-8 h-8 text-blue-600 dark:text-blue-400" />
        </div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-2">
          Create Your Company
        </h1>
        <p class="text-slate-600 dark:text-slate-400">
          Tell us a few basics and we'll prepare the defaults automatically.
        </p>
      </div>

      <!-- Form Card -->
      <Card>
        <CardHeader>
          <CardTitle>Company Information</CardTitle>
          <CardDescription>
            Enter your basic company details. Currency and timezone are set from your country.
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form @submit.prevent="submit" class="space-y-6">
            <!-- Company Name -->
            <div class="space-y-2">
              <Label for="name" class="font-medium">
                Company Name <span class="text-red-500">*</span>
              </Label>
              <Input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="e.g., Acme Corporation"
                required
                autofocus
              />
              <p class="text-xs text-slate-500 dark:text-slate-400">
                This is your legal business name
              </p>
              <p v-if="form.errors.name" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.name }}
              </p>
            </div>

            <!-- Industry -->
            <div class="space-y-2">
              <Label for="industry" class="font-medium">
                Industry <span class="text-red-500">*</span>
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
              <p v-if="selectedIndustry?.description" class="text-xs text-slate-500 dark:text-slate-400">
                {{ selectedIndustry.description }}
              </p>
              <p v-if="form.errors.industry_code" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.industry_code }}
              </p>
            </div>

            <!-- Country -->
            <div class="space-y-2">
              <Label for="country" class="font-medium">
                Country <span class="text-red-500">*</span>
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
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Your country determines the default currency and timezone
              </p>
              <p v-if="form.errors.country" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.country }}
              </p>
            </div>

            <!-- Auto-filled info card -->
            <div v-if="selectedCountry" class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4">
              <div class="flex items-center gap-2 mb-3">
                <Globe class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                <span class="text-sm font-medium text-blue-900 dark:text-blue-100">Auto-configured for {{ selectedCountry.name }}</span>
              </div>
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span class="text-slate-600 dark:text-slate-400">Currency:</span>
                  <span class="ml-2 font-medium text-slate-900 dark:text-slate-100">
                    {{ selectedCurrency?.code || form.base_currency }}
                    <template v-if="selectedCurrency">
                      - {{ selectedCurrency.name }} ({{ selectedCurrency.symbol }})
                    </template>
                  </span>
                </div>
                <div>
                  <span class="text-slate-600 dark:text-slate-400">Timezone:</span>
                  <span class="ml-2 font-medium text-slate-900 dark:text-slate-100">{{ form.timezone }}</span>
                </div>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-3">
                You can review these defaults in Settings anytime.
              </p>
            </div>

            <!-- Submit Button -->
            <div class="pt-4">
              <Button type="submit" :disabled="form.processing || !form.country || !form.industry_code || !form.base_currency" class="w-full" size="lg">
                <Loader2 v-if="form.processing" class="w-4 h-4 mr-2 animate-spin" />
                Create Company
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <!-- Help Text -->
      <div class="text-center mt-6 text-sm text-slate-600 dark:text-slate-400">
        <p>Need help? <a href="#" class="text-blue-600 hover:underline">Contact support</a></p>
      </div>
    </div>
  </div>
</template>
