<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import SidebarMenu from '@/Components/Sidebar/SidebarMenu.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Toolbar from 'primevue/toolbar'
import Message from 'primevue/message'
import { http } from '@/lib/http'
import CurrencyPicker from '@/Components/Pickers/CurrencyPicker.vue'
import LanguagePicker from '@/Components/Pickers/LanguagePicker.vue'
import LocalePicker from '@/Components/Pickers/LocalePicker.vue'

const form = ref({ name: '', base_currency: '', language: '', locale: '' })
const loading = ref(false)
const error = ref('')
const created = ref(null)

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.post('/api/v1/companies', form.value)
    created.value = data.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to create company'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Head title="Create Company" />
  
  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Admin Panel">
        <SidebarMenu iconSet="line" :sections="[
          { title: 'Admin', items: [
            { label: 'Companies', path: '/admin/companies', icon: 'companies', routeName: 'admin.companies.index' },
            { label: 'Users', path: '/admin/users', icon: 'users', routeName: 'admin.users.index' }
          ]}
        ]" />
      </Sidebar>
    </template>

    <template #topbar>
      <Toolbar class="border-0 bg-transparent px-0">
        <template #start>
          <h1 class="text-2xl font-bold">Create Company</h1>
        </template>
        <template #end>
          <Link :href="route('admin.companies.index')">
            <Button label="Back to Companies" icon="pi pi-arrow-left" severity="secondary" />
          </Link>
        </template>
      </Toolbar>
    </template>

    <div class="max-w-2xl">
      <Card>
        <template #content>
          <Message v-if="created" severity="success" class="mb-4">
            Company created: <strong>{{ created.name }}</strong>
          </Message>
          
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
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium mb-2">Base Currency</label>
                <CurrencyPicker v-model="form.base_currency" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-2">Language</label>
                <LanguagePicker v-model="form.language" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-2">Locale</label>
                <LocalePicker v-model="form.locale" :language="form.language" />
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
