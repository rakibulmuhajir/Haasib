<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted, watch } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import SidebarMenu from '@/Components/Sidebar/SidebarMenu.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Badge from 'primevue/badge'
import Breadcrumb from '@/Components/Breadcrumb.vue'
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

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Admin', url: '/admin', icon: 'settings' },
  { label: 'Users', url: '/admin/users', icon: 'users' }
])
</script>

<template>
  <Head title="Users" />
  
  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Admin Panel" />
    </template>

    <template #topbar>
      <div class="flex items-center justify-between w-full">
        <Breadcrumb :items="breadcrumbItems" />
        <Link :href="route('admin.users.create')">
          <Button label="Create User" icon="pi pi-user-plus" />
        </Link>
      </div>
    </template>

    <div class="space-y-4">
      <div class="flex items-center gap-2">
        <InputText 
          v-model="q" 
          placeholder="Search users by name or email…" 
          class="w-96"
          @keyup.enter="fetchUsers"
        />
        <Button label="Search" @click="fetchUsers" />
      </div>

      <div v-if="error" class="p-3 rounded bg-red-50 text-red-700 border border-red-200">{{ error }}</div>
      
      <DataTable 
        :value="items" 
        :loading="loading" 
        stripedRows 
        class="w-full"
        :paginator="items.length > 10"
        :rows="10"
        :rowsPerPageOptions="[10, 25, 50]"
      >
        <Column field="name" header="Name">
          <template #body="slotProps">
            <div class="font-medium">{{ slotProps.data.name }}</div>
          </template>
        </Column>
        <Column field="email" header="Email">
          <template #body="slotProps">
            <div class="text-sm">{{ slotProps.data.email }}</div>
          </template>
        </Column>
        <Column field="system_role" header="Role">
          <template #body="slotProps">
            <Badge 
              v-if="slotProps.data.system_role === 'superadmin'" 
              severity="danger" 
              value="Super Admin"
            />
            <span v-else class="text-sm text-gray-500">User</span>
          </template>
        </Column>
        <Column header="Actions">
          <template #body="slotProps">
            <Link :href="route('admin.users.show', slotProps.data.id)">
              <Button label="Manage" size="small" icon="pi pi-settings" />
            </Link>
          </template>
        </Column>
        <template #empty>
          <div class="text-center py-8 text-gray-500">No users found.</div>
        </template>
        <template #loading>
          <div class="text-center py-8 text-gray-500">Loading…</div>
        </template>
      </DataTable>
    </div>
  </LayoutShell>
</template>
