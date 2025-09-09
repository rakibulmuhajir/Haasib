<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted, watch } from 'vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import { http } from '@/lib/http'

const q = ref('')
const loading = ref(false)
const items = ref([])
const error = ref('')

async function fetchUsers() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.get('/web/users/suggest', { params: { q: q.value, limit: 50 } })
    items.value = data.data || []
  } catch (e) {
    error.value = 'Failed to load users'
  } finally {
    loading.value = false
  }
}

onMounted(fetchUsers)
watch(q, () => { const t = setTimeout(fetchUsers, 250); return () => clearTimeout(t) })
</script>

<template>
  <Head title="Users" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Users</h2>
        <Link :href="route('admin.users.create')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Create User</Link>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="mb-4 flex items-center gap-2">
          <TextInput v-model="q" placeholder="Search users by name or email…" class="w-72" />
          <PrimaryButton @click="fetchUsers">Search</PrimaryButton>
        </div>

        <div v-if="error" class="text-red-600 dark:text-red-400 text-sm mb-2">{{ error }}</div>
        <DataTable :value="items" :loading="loading" stripedRows class="w-full">
          <Column field="name" header="Name" />
          <Column field="email" header="Email" />
          <Column header="Actions">
            <template #body="slotProps">
              <Link :href="route('admin.users.show', slotProps.data.id)">
                <Button label="Manage" size="small" />
              </Link>
            </template>
          </Column>
          <template #empty>
            <div class="text-sm text-gray-500 dark:text-gray-400 px-4 py-6">No users found.</div>
          </template>
          <template #loading>
            <div class="text-sm text-gray-500 dark:text-gray-400 px-4 py-6">Loading…</div>
          </template>
        </DataTable>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
