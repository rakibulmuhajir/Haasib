<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Message from 'primevue/message'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { useToast } from 'primevue/usetoast'
import CurrencyPicker from '@/Components/Pickers/CurrencyPicker.vue'
import LanguagePicker from '@/Components/Pickers/LanguagePicker.vue'
import LocalePicker from '@/Components/Pickers/LocalePicker.vue'
import CountryPicker from '@/Components/Pickers/CountryPicker.vue'

interface Country {
  id: string
  name: string
}

interface FormData {
  name: string
  base_currency: string
  country: string
  language: string
  locale: string
  legal_name?: string
  tax_id?: string
  registration_number?: string
  website?: string
  phone?: string
  email?: string
  address_line_1?: string
  address_line_2?: string
  city?: string
  state_province?: string
  postal_code?: string
  fiscal_year_start?: string
  notes?: string
}

const form = useForm<FormData>({ 
  name: '', 
  base_currency: '', 
  country: '', 
  language: '', 
  locale: '' 
})

const toast = useToast()

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Admin', url: '/admin', icon: 'settings' },
  { label: 'Companies', url: '/admin/companies', icon: 'companies' },
  { label: 'Create Company', url: '#', icon: 'plus' }
])

async function submit() {
  // Clear previous errors
  form.clearErrors()
  
  // Client-side validation
  if (!form.name?.trim()) {
    form.setError('name', 'Company name is required')
    return
  }
  
  if (!form.country) {
    form.setError('country', 'Country is required')
    return
  }
  
  if (!form.base_currency) {
    form.setError('base_currency', 'Base currency is required')
    return
  }
  
  form.transform(data => ({
    ...data,
    _action: 'company.create'
  })).post('/commands', {
    onSuccess: (page) => {
      // Show success toast
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: `Company "${form.name}" created successfully`,
        life: 3000
      })
    },
    onError: (errors) => {
      // Show validation errors
      const firstError = Object.values(errors)[0]
      if (firstError) {
        toast.add({
          severity: 'error',
          summary: 'Validation Error',
          detail: firstError,
          life: 3000
        })
      }
    },
    onFinish: () => {
      // Form is no longer processing
    }
  })
}
</script>

<template>
  <Head title="Create Company" />
  
  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Admin Panel" />
    </template>

    <template #topbar>
      <Breadcrumb :items="breadcrumbItems" />
    </template>

    <div class="max-w-2xl">
      <PageHeader title="Create Company" subtitle="Add a new company to the system" />
      <Card>
        <template #content>
          <Message v-if="form.errors.name" severity="error" class="mb-4">
            {{ form.errors.name }}
          </Message>
          <Message v-if="form.errors.country" severity="error" class="mb-4">
            {{ form.errors.country }}
          </Message>
          <Message v-if="form.errors.base_currency" severity="error" class="mb-4">
            {{ form.errors.base_currency }}
          </Message>
          <Message v-if="form.errors.language" severity="error" class="mb-4">
            {{ form.errors.language }}
          </Message>
          <Message v-if="form.errors.locale" severity="error" class="mb-4">
            {{ form.errors.locale }}
          </Message>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-2">Name</label>
              <InputText 
                v-model="form.name" 
                class="w-full" 
                placeholder="Acme LLC" 
                :class="{ 'p-invalid': form.errors.name }"
              />
            </div>
            
            <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2">Country *</label>
              <CountryPicker v-model="form.country" />
            </div>
            
            <div>
              <label class="block text-sm font-medium mb-2">Base Currency *</label>
              <CurrencyPicker v-model="form.base_currency" />
            </div>
            
            <div>
              <label class="block text-sm font-medium mb-2">Language</label>
              <LanguagePicker v-model="form.language" />
            </div>
          </div>
          
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2">Locale</label>
              <LocalePicker v-model="form.locale" />
            </div>
          </div>

            <div class="pt-4 flex items-center gap-2">
              <Button 
                @click="submit" 
                :disabled="form.processing"
                :loading="form.processing"
                label="Create Company"
                icon="pi pi-check"
              />
            </div>
          </div>
        </template>
      </Card>
    </div>
  </LayoutShell>
</template>
