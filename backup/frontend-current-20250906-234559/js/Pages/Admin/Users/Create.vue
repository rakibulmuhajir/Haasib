<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import CompanyPicker from '@/Components/Pickers/CompanyPicker.vue'
import { http, withIdempotency } from '@/lib/http'

const form = ref({ name: '', email: '', password: '', system_role: '' })
const loading = ref(false)
const error = ref('')
const ok = ref('')
const assign = ref({ company: '', role: 'viewer' })

function randomPassword() {
  return Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2)
}

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
          role: assign.value.role,
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
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Create User</h2>
        <Link :href="route('admin.users.index')" class="text-sm text-gray-600 hover:underline">Back to users</Link>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow sm:rounded-md p-6">
          <div v-if="ok" class="mb-4 rounded border border-green-200 bg-green-50 p-3 text-sm text-green-700">{{ ok }}</div>
          <div v-if="error" class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>

          <div class="space-y-4">
            <div>
              <InputLabel value="Name" />
              <TextInput v-model="form.name" class="mt-1 block w-full" placeholder="Jane Doe" />
            </div>
            <div>
              <InputLabel value="Email" />
              <TextInput v-model="form.email" class="mt-1 block w-full" placeholder="jane@example.com" />
            </div>
            <div>
              <InputLabel value="Password (optional)" />
              <TextInput v-model="form.password" class="mt-1 block w-full" placeholder="Auto-generated if left blank" />
            </div>
            <div>
              <InputLabel value="System Role (optional)" />
              <TextInput v-model="form.system_role" class="mt-1 block w-full" placeholder="superadmin" />
            </div>
            <div class="border-t border-gray-200 pt-4">
              <div class="text-sm text-gray-700 font-medium mb-2">Optional: Assign to a company</div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <div class="md:col-span-2">
                  <InputLabel value="Company" />
                  <CompanyPicker v-model="assign.company" />
                </div>
                <div>
                  <InputLabel value="Role" />
                  <select v-model="assign.role" class="mt-1 block w-full rounded border-gray-300">
                    <option value="owner">Owner</option>
                    <option value="admin">Admin</option>
                    <option value="accountant">Accountant</option>
                    <option value="viewer">Viewer</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="pt-2">
              <PrimaryButton @click="submit" :disabled="loading">Create</PrimaryButton>
              <span v-if="loading" class="ms-2 text-sm text-gray-500">Saving…</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
