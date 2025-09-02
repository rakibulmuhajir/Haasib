<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted, watch } from 'vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
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
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Users</h2>
        <Link :href="route('admin.users.create')" class="text-sm text-indigo-600 hover:underline">Create User</Link>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="mb-4 flex items-center gap-2">
          <TextInput v-model="q" placeholder="Search users by name or email…" class="w-72" />
          <PrimaryButton @click="fetchUsers">Search</PrimaryButton>
        </div>

        <div v-if="error" class="text-red-600 text-sm mb-2">{{ error }}</div>
        <div class="overflow-hidden bg-white shadow sm:rounded-md">
          <ul role="list" class="divide-y divide-gray-200">
            <li v-for="u in items" :key="u.id" class="px-4 py-4 sm:px-6">
              <div class="flex items-center justify-between">
                <div>
                  <div class="text-sm font-medium text-gray-900">{{ u.name }}</div>
                  <div class="text-xs text-gray-500">{{ u.email }}</div>
                </div>
                <div>
                  <Link :href="route('admin.users.show', u.id)" class="text-sm text-indigo-600 hover:underline">Manage</Link>
                </div>
              </div>
            </li>
            <li v-if="!loading && items.length === 0" class="px-4 py-6 text-sm text-gray-500">No users found.</li>
            <li v-if="loading" class="px-4 py-6 text-sm text-gray-500">Loading…</li>
          </ul>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

