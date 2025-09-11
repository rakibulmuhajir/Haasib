<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Message from 'primevue/message'
import Dropdown from 'primevue/dropdown'
import CompanyPicker from '@/Components/Pickers/CompanyPicker.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import { http, withIdempotency } from '@/lib/http'

const form = ref({ name: '', email: '', password: '', system_role: '' })
const loading = ref(false)
const error = ref('')
const ok = ref('')
const assign = ref({ company: '', role: { value: 'viewer', label: 'Viewer' } })

const roleOptions = [
  { value: 'owner', label: 'Owner' },
  { value: 'admin', label: 'Admin' },
  { value: 'accountant', label: 'Accountant' },
  { value: 'viewer', label: 'Viewer' },
]

function randomPassword() {
  return Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2)
}

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Admin', url: '/admin', icon: 'settings' },
  { label: 'Users', url: '/admin/users', icon: 'users' },
  { label: 'Create User', url: '#', icon: 'user-plus' }
])

async function submit() {
  loading.value = true
  error.value = ''
  ok.value = ''
  const payload = { ...form.value }
  if (!payload.password) payload.password = randomPassword()
  try {
    const { data } = await http.post('/commands', payload, { headers: withIdempotency({ 'X-Action': 'user.create' }) })
    ok.value = data?.message || 'User created'
    if (assign.value.company) {
      try {
        await http.post('/commands', {
          email: form.value.email,
          company: assign.value.company,
          role: assign.value.role.value,
        }, { headers: withIdempotency({ 'X-Action': 'company.assign' }) })
        ok.value += ' · Assigned to company'
      } catch (e) {
        // swallow, surface below
        throw e
      }
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to create user'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Head title="Create User" />
  
  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Admin Panel" />
    </template>

    <template #topbar>
      <div class="flex items-center justify-between w-full">
        <Breadcrumb :items="breadcrumbItems" />
      </div>
    </template>

    <div class="space-y-4">
      <Message v-if="ok" severity="success" :closable="false">{{ ok }}</Message>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

      <Card class="w-full max-w-2xl mx-auto">
        <template #title>Create New User</template>
        <template #content>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-2">Name</label>
              <InputText v-model="form.name" class="w-full" placeholder="Jane Doe" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">Email</label>
              <InputText v-model="form.email" class="w-full" placeholder="jane@example.com" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">Password (optional)</label>
              <InputText v-model="form.password" class="w-full" placeholder="Auto-generated if left blank" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">System Role (optional)</label>
              <InputText v-model="form.system_role" class="w-full" placeholder="superadmin" />
            </div>
            <div class="border-t pt-4">
              <div class="text-sm font-medium mb-2">Optional: Assign to a company</div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium mb-2">Company</label>
                  <CompanyPicker v-model="assign.company" />
                </div>
                <div>
                  <label class="block text-sm font-medium mb-2">Role</label>
                  <Dropdown v-model="assign.role" :options="roleOptions" optionLabel="label" class="w-full" />
                </div>
              </div>
            </div>
            <div class="pt-2">
              <Button @click="submit" :loading="loading" label="Create User" icon="pi pi-user-plus" />
            </div>
          </div>
        </template>
      </Card>
    </div>
  </LayoutShell>
</template>
