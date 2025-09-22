<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Badge from 'primevue/badge'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import { useApiList } from '@/composables/useApiList.js'
import { useToasts } from '@/composables/useToasts.js'
import { http, withIdempotency } from '@/lib/http'

const q = ref('')
const selectedUsers = ref([])
const { addToast } = useToasts()
const batchLoading = ref(false)
const { items, loading, error, fetch: fetchUsers } = useApiList('/web/users/suggest', {
  query: q,
  initialParams: { limit: 50 }
})

onMounted(fetchUsers)

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Admin', url: '/admin', icon: 'settings' },
  { label: 'Users', url: '/admin/users', icon: 'users' }
])

// Batch actions
async function activateSelectedUsers() {
  if (!selectedUsers.value.length) {
    addToast('No users selected', 'warning')
    return
  }
  
  if (!confirm(`Activate ${selectedUsers.value.length} selected users?`)) return
  
  batchLoading.value = true
  try {
    await Promise.all(
      selectedUsers.value.map(user => 
        http.post('/commands', {
          user: user.id
        }, { headers: withIdempotency({ 'X-Action': 'user.activate' }) })
      )
    )
    addToast(`${selectedUsers.value.length} users activated successfully`, 'success')
    selectedUsers.value = []
    fetchUsers()
  } catch (e) {
    addToast(e?.response?.data?.message || 'Failed to activate users', 'danger')
  } finally {
    batchLoading.value = false
  }
}

async function deactivateSelectedUsers() {
  if (!selectedUsers.value.length) {
    addToast('No users selected', 'warning')
    return
  }
  
  if (!confirm(`Deactivate ${selectedUsers.value.length} selected users?`)) return
  
  batchLoading.value = true
  try {
    await Promise.all(
      selectedUsers.value.map(user => 
        http.post('/commands', {
          user: user.id
        }, { headers: withIdempotency({ 'X-Action': 'user.deactivate' }) })
      )
    )
    addToast(`${selectedUsers.value.length} users deactivated successfully`, 'success')
    selectedUsers.value = []
    fetchUsers()
  } catch (e) {
    addToast(e?.response?.data?.message || 'Failed to deactivate users', 'danger')
  } finally {
    batchLoading.value = false
  }
}

async function toggleUserStatus(user) {
  const action = user.is_active ? 'user.deactivate' : 'user.activate'
  const actionText = user.is_active ? 'deactivate' : 'activate'
  
  if (!confirm(`${actionText.charAt(0).toUpperCase() + actionText.slice(1)} user ${user.name}?`)) return
  
  batchLoading.value = true
  try {
    await http.post('/commands', {
      user: user.id
    }, { headers: withIdempotency({ 'X-Action': action }) })
    
    addToast(`User ${actionText}d successfully`, 'success')
    fetchUsers()
  } catch (e) {
    addToast(e?.response?.data?.message || `Failed to ${actionText} user`, 'danger')
  } finally {
    batchLoading.value = false
  }
}
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
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <InputText
            v-model="q"
            placeholder="Search users by name or email…"
            class="w-96"
            @keyup.enter="fetchUsers"
          />
          <Button label="Search" @click="fetchUsers" />
        </div>
        
        <!-- Batch Actions -->
        <div v-if="selectedUsers.length" class="flex items-center gap-2">
          <span class="text-sm text-gray-600">{{ selectedUsers.length }} selected</span>
          <Button 
            label="Activate" 
            icon="pi pi-check" 
            size="small" 
            severity="success"
            :loading="batchLoading"
            :disabled="selectedUsers.every(user => user.is_active)"
            @click="activateSelectedUsers"
          />
          <Button 
            label="Deactivate" 
            icon="pi pi-times" 
            size="small" 
            severity="warning"
            :loading="batchLoading"
            :disabled="selectedUsers.every(user => !user.is_active)"
            @click="deactivateSelectedUsers"
          />
          <Button 
            label="Clear" 
            icon="pi pi-times" 
            size="small" 
            text
            @click="selectedUsers = []"
          />
        </div>
      </div>

      <div v-if="error" class="p-3 rounded bg-red-50 text-red-700 border border-red-200">{{ error }}</div>

      <DataTable
        v-model:selection="selectedUsers"
        :value="items"
        :loading="loading"
        stripedRows
        class="w-full"
        :paginator="items.length > 10"
        :rows="10"
        :rowsPerPageOptions="[10, 25, 50]"
        dataKey="id"
      >
        <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
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
        <Column field="is_active" header="Status">
          <template #body="slotProps">
            <Badge
              :value="slotProps.data.is_active ? 'Active' : 'Inactive'"
              :severity="slotProps.data.is_active ? 'success' : 'danger'"
            />
          </template>
        </Column>
        <Column header="Actions">
          <template #body="slotProps">
            <div class="flex items-center gap-2">
              <Button 
                v-if="!slotProps.data.is_active"
                label="Activate" 
                size="small" 
                icon="pi pi-check"
                severity="success"
                :loading="batchLoading && selectedUsers.some(u => u.id === slotProps.data.id)"
                @click="toggleUserStatus(slotProps.data)"
              />
              <Button 
                v-if="slotProps.data.is_active"
                label="Deactivate" 
                size="small" 
                icon="pi pi-times"
                severity="warning"
                :loading="batchLoading && selectedUsers.some(u => u.id === slotProps.data.id)"
                @click="toggleUserStatus(slotProps.data)"
              />
              <Link :href="route('admin.users.show', slotProps.data.id)">
                <Button label="Manage" size="small" icon="pi pi-settings" />
              </Link>
            </div>
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
