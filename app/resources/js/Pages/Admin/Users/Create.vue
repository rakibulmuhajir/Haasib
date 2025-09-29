<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Message from 'primevue/message'
import Dropdown from 'primevue/dropdown'
import CompanyPicker from '@/Components/Pickers/CompanyPicker.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

interface FormData {
  name: string
  email: string
  password: string
  system_role: string
}

const form = useForm<FormData>({ name: '', email: '', password: '', system_role: '' })
const ok = ref('')
const assign = ref({ company: null, role: { value: 'viewer', label: 'Viewer' } })

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
  form.clearErrors()
  ok.value = ''
  
  const payload = { ...form }
  if (!payload.password) payload.password = randomPassword()
  
  // First create the user
  form.transform(data => ({
    ...data,
    _action: 'user.create',
    password: payload.password
  })).post('/commands', {
    onSuccess: () => {
      ok.value = 'User created'
      
      // If company assignment is needed, do it separately
      if (assign.value.company) {
        const assignForm = useForm({
          email: form.email,
          company: assign.value.company.id || assign.value.company,
          role: assign.value.role.value,
          _action: 'company.assign'
        })
        
        assignForm.post('/commands', {
          onSuccess: () => {
            ok.value += ' · Assigned to company'
          },
          onError: () => {
            // Don't show error for assignment failure, just log it
            console.error('Company assignment failed')
          }
        })
      }
    },
    onError: (errors) => {
      console.error('Create user error:', errors);
    }
  })
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
      <Message v-if="form.errors.name" severity="error" :closable="false">{{ form.errors.name }}</Message>
    <Message v-if="form.errors.email" severity="error" :closable="false">{{ form.errors.email }}</Message>
    <Message v-if="form.errors.password" severity="error" :closable="false">{{ form.errors.password }}</Message>
    <Message v-if="form.errors.system_role" severity="error" :closable="false">{{ form.errors.system_role }}</Message>

      <Card class="w-full max-w-2xl mx-auto">
        <template #title>Create New User</template>
        <template #content>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-2">Name</label>
              <InputText v-model="form.name" class="w-full" placeholder="Jane Doe" :class="{ 'p-invalid': form.errors.name }" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">Email</label>
              <InputText v-model="form.email" class="w-full" placeholder="jane@example.com" :class="{ 'p-invalid': form.errors.email }" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">Password (optional)</label>
              <InputText v-model="form.password" class="w-full" placeholder="Auto-generated if left blank" :class="{ 'p-invalid': form.errors.password }" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">System Role (optional)</label>
              <InputText v-model="form.system_role" class="w-full" placeholder="superadmin" :class="{ 'p-invalid': form.errors.system_role }" />
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
              <Button @click="submit" :loading="form.processing" label="Create User" icon="pi pi-user-plus" />
            </div>
          </div>
        </template>
      </Card>
    </div>
  </LayoutShell>
</template>
