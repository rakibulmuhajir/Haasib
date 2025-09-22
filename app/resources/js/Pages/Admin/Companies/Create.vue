<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Message from 'primevue/message'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { useToast } from 'primevue/usetoast'
import { http, withIdempotency } from '@/lib/http'
import CurrencyPicker from '@/Components/Pickers/CurrencyPicker.vue'
import LanguagePicker from '@/Components/Pickers/LanguagePicker.vue'
import LocalePicker from '@/Components/Pickers/LocalePicker.vue'
import CountryPicker from '@/Components/Pickers/CountryPicker.vue'

const form = ref({ name: '', base_currency: '', country: '', language: '', locale: '' })
const loading = ref(false)
const error = ref('')
const toast = useToast()

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Admin', url: '/admin', icon: 'settings' },
  { label: 'Companies', url: '/admin/companies', icon: 'companies' },
  { label: 'Create Company', url: '#', icon: 'plus' }
])

async function submit() {
  // Client-side validation
  if (!form.value.name?.trim()) {
    error.value = 'Company name is required'
    toast.add({
      severity: 'error',
      summary: 'Validation Error',
      detail: error.value,
      life: 3000
    })
    return
  }
  
  if (!form.value.country) {
    error.value = 'Country is required'
    toast.add({
      severity: 'error',
      summary: 'Validation Error',
      detail: error.value,
      life: 3000
    })
    return
  }
  
  if (!form.value.base_currency) {
    error.value = 'Base currency is required'
    toast.add({
      severity: 'error',
      summary: 'Validation Error',
      detail: error.value,
      life: 3000
    })
    return
  }
  
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.post('/commands', form.value, { 
      headers: withIdempotency({ 'X-Action': 'company.create' })
    })
    
    // Show success toast
    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: `Company "${form.value.name}" created successfully`,
      life: 3000
    })
    
    // Redirect to company show page
    setTimeout(() => {
      router.visit(route('admin.companies.show', data.data.slug))
    }, 500)
  } catch (e) {
    console.error('Company creation error:', e.response?.data)
    error.value = e?.response?.data?.message || 'Failed to create company'
    
    // Show validation errors if any
    if (e?.response?.data?.errors) {
      const validationErrors = Object.values(e.response.data.errors).flat()
      error.value = validationErrors.join(', ')
    }
    
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.value,
      life: 3000
    })
  } finally {
    loading.value = false
  }
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
          <Message v-if="error" severity="error" class="mb-4">
            {{ error }}
          </Message>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-2">Name</label>
              <InputText 
                v-model="form.name" 
                class="w-full" 
                placeholder="Acme LLC" 
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
                :disabled="loading"
                :loading="loading"
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
