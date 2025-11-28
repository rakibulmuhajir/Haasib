<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import UniversalLayout from '@/layouts/UniversalLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const form = useForm({
    name: '',
    slug: '',
    country: '',
    currency: ''
})


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

const handleCountryChange = (value: string) => {
    form.country = value
    
    switch (value) {
        case 'US':
            form.currency = 'USD'
            break
        case 'CA':
            form.currency = 'CAD'
            break
        case 'GB':
            form.currency = 'GBP'
            break
        case 'AU':
            form.currency = 'AUD'
            break
        case 'IN':
            form.currency = 'INR'
            break
        case 'PK':
            form.currency = 'PKR'
            break
        case 'AE':
            form.currency = 'AED'
            break
        case 'SA':
            form.currency = 'SAR'
            break
        default:
            form.currency = 'USD'
    }
}

const generateSlug = () => {
    if (form.name) {
        form.slug = form.name
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .trim()
    }
}

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

const cancel = () => {
    router.visit('/companies')
}
</script>

<template>
  <Head title="Create Company" />
  
  <UniversalLayout title="Create Company">
    <div class="max-w-2xl mx-auto">
      <form @submit.prevent="submit" class="space-y-6">
        <div class="space-y-2">
          <Label for="name">Company Name *</Label>
          <Input 
            id="name" 
            v-model="form.name" 
            placeholder="Enter company name"
            @input="generateSlug"
            :class="{ 'border-red-500': form.errors.name }"
            required
          />
          <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
        </div>
        
        <div class="space-y-2">
          <Label for="slug">Slug *</Label>
          <Input 
            id="slug" 
            v-model="form.slug" 
            placeholder="Enter company slug"
            :class="{ 'border-red-500': form.errors.slug }"
            required
          />
          <p v-if="form.errors.slug" class="text-sm text-red-500">{{ form.errors.slug }}</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
          
          <div class="space-y-2">
            <Label>Currency *</Label>
            <Select v-model="form.currency" required>
              <SelectTrigger :class="{ 'border-red-500': form.errors.currency }">
                <SelectValue placeholder="Select currency" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in currencyOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="form.errors.currency" class="text-sm text-red-500">{{ form.errors.currency }}</p>
          </div>
        </div>
        
        <div v-if="form.country && form.currency" class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
          <p class="text-sm text-green-700 dark:text-green-300">
            ✓ Currency automatically set to <strong>{{ form.currency }}</strong> based on country selection
          </p>
        </div>
        
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
  </UniversalLayout>
</template>
