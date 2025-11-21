<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import UniversalLayout from '@/layouts/UniversalLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const breadcrumbs = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Companies', href: '/companies' },
    { label: 'Create Company', active: true }
]

const headerActions = [
    { label: 'Cancel', variant: 'outline' as const, href: '/companies' }
]

// Form setup with required fields
const form = useForm({
    name: '',
    industry: '',
    country: '',
    base_currency: '',
    timezone: ''
})

// Industry options
const industryOptions = [
    { value: 'hospitality', label: 'Hospitality' },
    { value: 'retail', label: 'Retail' },
    { value: 'professional_services', label: 'Professional Services' },
    { value: 'technology', label: 'Technology' },
    { value: 'healthcare', label: 'Healthcare' },
    { value: 'education', label: 'Education' },
    { value: 'manufacturing', label: 'Manufacturing' },
    { value: 'other', label: 'Other' }
]

// Country options
const countryOptions = [
    { value: 'US', label: 'United States' },
    { value: 'CA', label: 'Canada' },
    { value: 'GB', label: 'United Kingdom' },
    { value: 'AU', label: 'Australia' },
    { value: 'DE', label: 'Germany' },
    { value: 'FR', label: 'France' },
    { value: 'JP', label: 'Japan' },
    { value: 'CN', label: 'China' },
    { value: 'IN', label: 'India' },
    { value: 'PK', label: 'Pakistan' },
    { value: 'AE', label: 'United Arab Emirates' },
    { value: 'SA', label: 'Saudi Arabia' }
]

// Currency options
const currencyOptions = [
    { value: 'USD', label: 'USD - US Dollar' },
    { value: 'EUR', label: 'EUR - Euro' },
    { value: 'GBP', label: 'GBP - British Pound' },
    { value: 'CAD', label: 'CAD - Canadian Dollar' },
    { value: 'AUD', label: 'AUD - Australian Dollar' },
    { value: 'JPY', label: 'JPY - Japanese Yen' },
    { value: 'CNY', label: 'CNY - Chinese Yuan' },
    { value: 'INR', label: 'INR - Indian Rupee' },
    { value: 'PKR', label: 'PKR - Pakistani Rupee' },
    { value: 'AED', label: 'AED - UAE Dirham' },
    { value: 'SAR', label: 'SAR - Saudi Riyal' }
]

// Handle country change - auto-set currency and timezone
const handleCountryChange = (value: string) => {
    form.country = value
    
    // Auto-set currency based on country
    switch (value) {
        case 'US':
            form.base_currency = 'USD'
            form.timezone = 'America/New_York'
            break
        case 'CA':
            form.base_currency = 'CAD'
            form.timezone = 'America/Toronto'
            break
        case 'GB':
            form.base_currency = 'GBP'
            form.timezone = 'Europe/London'
            break
        case 'AU':
            form.base_currency = 'AUD'
            form.timezone = 'Australia/Sydney'
            break
        case 'IN':
            form.base_currency = 'INR'
            form.timezone = 'Asia/Kolkata'
            break
        case 'PK':
            form.base_currency = 'PKR'
            form.timezone = 'Asia/Karachi'
            break
        case 'AE':
            form.base_currency = 'AED'
            form.timezone = 'Asia/Dubai'
            break
        case 'SA':
            form.base_currency = 'SAR'
            form.timezone = 'Asia/Riyadh'
            break
        default:
            form.base_currency = 'USD'
            form.timezone = 'UTC'
    }
}

// Submit form
const submit = () => {
    form.post('/companies', {
        onSuccess: () => {
            router.visit('/companies')
        },
        onError: (errors) => {
            console.error('Form errors:', errors)
        }
    })
}

// Cancel form
const cancel = () => {
    router.visit('/companies')
}
</script>

<template>
  <Head title="Create Company" />
  
  <UniversalLayout
    title="Create Company"
    subtitle="Add a new company to your account"
    :breadcrumbs="breadcrumbs"
    :header-actions="headerActions"
  >
    <div class="p-6">
      <div class="max-w-2xl mx-auto">
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Company Name -->
          <div class="space-y-2">
            <Label for="name">Company Name *</Label>
            <Input 
              id="name" 
              v-model="form.name" 
              placeholder="Enter company name"
              :class="{ 'border-red-500': form.errors.name }"
              required
            />
            <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
          </div>
          
          <!-- Industry -->
          <div class="space-y-2">
            <Label>Industry *</Label>
            <Select v-model="form.industry" required>
              <SelectTrigger :class="{ 'border-red-500': form.errors.industry }">
                <SelectValue placeholder="Select industry" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in industryOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="form.errors.industry" class="text-sm text-red-500">{{ form.errors.industry }}</p>
          </div>
          
          <!-- Country and Currency Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Country -->
            <div class="space-y-2">
              <Label>Country *</Label>
              <Select v-model="form.country" @update:model-value="handleCountryChange" required>
                <SelectTrigger :class="{ 'border-red-500': form.errors.country }">
                  <SelectValue placeholder="Select country" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in countryOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.country" class="text-sm text-red-500">{{ form.errors.country }}</p>
            </div>
            
            <!-- Base Currency -->
            <div class="space-y-2">
              <Label>Base Currency *</Label>
              <Select v-model="form.base_currency" required>
                <SelectTrigger :class="{ 'border-red-500': form.errors.base_currency }">
                  <SelectValue placeholder="Select currency" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in currencyOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.base_currency" class="text-sm text-red-500">{{ form.errors.base_currency }}</p>
            </div>
          </div>
          
          <!-- Auto-population Info -->
          <div v-if="form.country && form.base_currency" class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
            <p class="text-sm text-green-700 dark:text-green-300">
              ✓ Currency automatically set to <strong>{{ form.base_currency }}</strong> based on country selection
            </p>
          </div>
          
          <!-- Form Actions -->
          <div class="flex gap-3 pt-4">
            <Button 
              type="submit" 
              :disabled="form.processing"
              class="flex-1 md:flex-none"
            >
              {{ form.processing ? 'Creating...' : 'Create Company' }}
            </Button>
            <Button 
              type="button" 
              variant="outline" 
              @click="cancel"
              :disabled="form.processing"
              class="flex-1 md:flex-none"
            >
              Cancel
            </Button>
          </div>
        </form>
      </div>
    </div>
  </UniversalLayout>
</template>