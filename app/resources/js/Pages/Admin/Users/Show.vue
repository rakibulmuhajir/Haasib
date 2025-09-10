<script setup>
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import SidebarMenu from '@/Components/Sidebar/SidebarMenu.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Toolbar from 'primevue/toolbar'
import Message from 'primevue/message'
import Dropdown from 'primevue/dropdown'
import Badge from 'primevue/badge'
import CompanyPicker from '@/Components/Pickers/CompanyPicker.vue'
import UserMembershipList from '@/Components/UserMembershipList.vue'
import { http, withIdempotency } from '@/lib/http';
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import { useToasts } from '@/composables/useToasts.js'
import { usePersistentTabs } from '@/composables/usePersistentTabs.js'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ref, onMounted, computed } from 'vue'

const props = defineProps({ id: { type: String, required: true } })
const { addToast } = useToasts()
const loading = ref(false)
const error = ref('')
const user = ref(null)

const roleOptions = [
  { value: 'owner', label: 'Owner' },
  { value: 'admin', label: 'Admin' },
  { value: 'accountant', label: 'Accountant' },
  { value: 'viewer', label: 'Viewer' },
]

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.get(`/web/users/${encodeURIComponent(props.id)}`)
    console.log('📥 User data loaded:', data.data)
    console.log('📥 Memberships data:', data.data.memberships)
    user.value = data.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load user'
  } finally {
    loading.value = false
  }
}

onMounted(load)

// Assign to company
const assign = ref({ company: '', role: { value: 'viewer', label: 'Viewer' } })
const assignLoading = ref(false)
const assignError = ref('')

async function assignToCompany() {
  if (!assign.value.company || !assign.value.role) return
  assignLoading.value = true
  assignError.value = ''
  try {
    const { data } = await http.post('/commands', {
      email: user.value.email,
      company: assign.value.company,
      role: assign.value.role.value,
    }, { headers: withIdempotency({ 'X-Action': 'company.assign' }) })
    user.value.memberships.unshift(data.data)
    assign.value.company = ''
    assign.value.role = { value: 'viewer', label: 'Viewer' }
    addToast('User assigned to company.', 'success')
  } catch (e) {
    const message = e?.response?.data?.message || 'Failed to assign'
    assignError.value = message
    addToast(message, 'danger')
  } finally {
    assignLoading.value = false
  }
}

async function changeRole(m) {
  console.log('🚀 changeRole FUNCTION CALLED - Users/Show.vue')
  console.log('Input parameter m:', m)
  
  // Find the membership by company ID since memberships don't have unique IDs
  const membership = user.value.memberships.find(mem => mem.id === m.id)
  const originalRole = membership?.role
  console.log('Original role from memberships array:', originalRole)
  console.log('New role from parameter:', m.role)
  
  if (originalRole === m.role) {
    console.log('❌ Role unchanged, returning early')
    return // No change
  }
  
  console.log('📡 Making API call to /commands...')
  
  try {
    const payload = {
      email: user.value.email,
      company: m.id, // This is the company ID from the membership
      role: m.role,
    }
    console.log('📤 Request payload:', payload)
    
    const { data } = await http.post('/commands', payload, { 
      headers: withIdempotency({ 'X-Action': 'company.assign' }) 
    })
    
    console.log('📥 API response received:', data)
    
    // Update the membership in the array
    const index = user.value.memberships.findIndex(mem => mem.id === m.id)
    if (index !== -1) {
      console.log('🔄 Updating membership in array at index:', index)
      user.value.memberships.splice(index, 1, {
        ...user.value.memberships[index],
        role: m.role,
        updated_at: new Date().toISOString()
      })
      console.log('✅ Membership array updated')
    }
    
    addToast('Role changed successfully.', 'success')
    console.log('🎉 Success toast shown')
  } catch (e) {
    console.error('💥 API call failed:', e)
    console.error('Error response:', e?.response?.data)
    addToast(e?.response?.data?.message || 'Failed to change role', 'danger')
  }
}

async function unassign(m) {
  if (!confirm(`Remove ${user.value.email} from ${m.name}?`)) return
  try {
    await http.post('/commands', {
      email: user.value.email,
      company: m.slug || m.id,
    }, { headers: withIdempotency({ 'X-Action': 'company.unassign' }) })
    user.value.memberships = user.value.memberships.filter(mem => mem.id !== m.id)
    addToast('User removed from company.', 'success')
  } catch (e) {
    addToast(e?.response?.data?.message || 'Failed to remove', 'danger')
  }
}

const tabNames = ['memberships', 'assign']
const storageKey = computed(() => `admin.user.tab.${props.id}`)
const { selectedTab } = usePersistentTabs(tabNames, storageKey) // number index
</script>

<template>
  <Head :title="user ? `User · ${user.name}` : 'User'" />
  
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
          <h1 class="text-2xl font-bold">User</h1>
        </template>
        <template #end>
          <Link :href="route('admin.users.index')">
            <Button label="Back to Users" icon="pi pi-arrow-left" severity="secondary" />
          </Link>
        </template>
      </Toolbar>
    </template>

    <div class="space-y-6">
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

      <!-- User Profile Header -->
      <Card class="overflow-hidden">
        <template #content>
          <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
              <div class="w-16 h-16 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-2xl font-bold">
                {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
              </div>
              <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ user?.name || '—' }}</h1>
                <p class="text-gray-600 dark:text-gray-400">{{ user?.email }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">ID: {{ user?.id }}</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <Badge :value="`${user?.memberships?.length || 0} Companies`" severity="info" />
              <Badge :value="user?.system_role || 'User'" severity="secondary" />
            </div>
          </div>
        </template>
      </Card>

      <!-- Company Memberships Section -->
      <Card>
        <template #title>Company Memberships</template>
        <template #content>
          <TabView v-model:activeIndex="selectedTab" class="w-full">
            <TabPanel header="Current Memberships">
              <div v-if="!loading && (!user?.memberships || user.memberships.length === 0)" class="text-center py-8">
                <div class="text-gray-500 dark:text-gray-400 mb-4">User is not assigned to any companies</div>
                <Button label="Assign to Company" icon="pi pi-plus" @click="selectedTab = 1" />
              </div>
              <UserMembershipList
                v-else
                :memberships="user?.memberships || []"
                :loading="loading"
                :role-options="roleOptions"
                @update-role="(membership) => { 
                  console.log('🎯 update-role event RECEIVED - Users/Show.vue')
                  console.log('Received membership data:', membership)
                  console.log('About to call changeRole function...')
                  changeRole(membership)
                  console.log('✅ changeRole function called')
                }"
                @unassign="unassign"
              />
            </TabPanel>
            <TabPanel header="Assign to Company">
              <div class="space-y-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                  Assign this user to a company with a specific role.
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 items-end">
                  <div class="lg:col-span-2">
                    <label class="block text-sm font-medium mb-2">Company</label>
                    <CompanyPicker v-model="assign.company" class="w-full" placeholder="Search for company…" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-2">Role</label>
                    <Dropdown v-model="assign.role" :options="roleOptions" optionLabel="label" class="w-full" placeholder="Select role" />
                  </div>
                  <div>
                    <Button @click="assignToCompany" :loading="assignLoading" label="Assign User" icon="pi pi-user-plus" class="w-full" />
                  </div>
                </div>
                <Message v-if="assignError" severity="error" :closable="false">{{ assignError }}</Message>
              </div>
            </TabPanel>
          </TabView>
        </template>
      </Card>

      <!-- Activity Section (if available) -->
      <Card v-if="user?.last_activity">
        <template #title>Recent Activity</template>
        <template #content>
          <div class="flex items-center gap-4 text-sm">
            <div class="w-2 h-2 rounded-full bg-green-500"></div>
            <div>
              <span class="font-medium">{{ user.last_activity.action }}</span>
              <span class="text-gray-500 dark:text-gray-400 ml-2">
                {{ new Date(user.last_activity.created_at).toLocaleDateString() }}
              </span>
            </div>
          </div>
        </template>
      </Card>
    </div>
  </LayoutShell>
</template>
