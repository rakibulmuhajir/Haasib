<script setup>
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import SidebarMenu from '@/Components/Sidebar/SidebarMenu.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Message from 'primevue/message'
import Dropdown from 'primevue/dropdown'
import Badge from 'primevue/badge'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CompanyPicker from '@/Components/Pickers/CompanyPicker.vue'
import UserMembershipList from '@/Components/UserMembershipList.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
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
    // Update breadcrumb with actual user name
    breadcrumbItems.value[2].label = user.value.name || 'User'
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load user'
  } finally {
    loading.value = false
  }
}

onMounted(load)

// Assign to company
const assign = ref({ company: null, role: { value: 'viewer', label: 'Viewer' } })
const assignLoading = ref(false)
const assignError = ref('')

async function assignToCompany() {
  console.log('🔍 assignToCompany called with:', assign.value)
  console.log('🔍 Validation check:', {
    company: assign.value.company,
    companyId: assign.value.company?.slug || assign.value.company?.id,
    role: assign.value.role,
    roleValue: assign.value.role?.value
  })
  
  if (!assign.value.company || !assign.value.role || !assign.value.role.value) {
    console.log('❌ Validation failed - missing required fields')
    return
  }
  
  console.log('✅ Validation passed, proceeding with assignment')
  
  // Check if user is already assigned to this company
  const companyId = assign.value.company.slug || assign.value.company.id
  const existingMembership = user.value.memberships.find(
    membership => membership.id === companyId
  )
  
  if (existingMembership) {
    const message = `User is already assigned to this company as ${existingMembership.role}`
    assignError.value = message
    addToast(message, 'danger')
    return
  }
  
  assignLoading.value = true
  assignError.value = ''
  try {
    const companyId = assign.value.company.slug || assign.value.company.id
    console.log('🚀 Making API call to /commands...', {
      email: user.value.email,
      company: companyId,
      role: assign.value.role.value,
    })
    
    const response = await http.post('/commands', {
      email: user.value.email,
      company: companyId,
      role: assign.value.role.value,
    }, { headers: withIdempotency({ 'X-Action': 'company.assign' }) })
    
    console.log('📥 API response received:', response)
    console.log('📥 Response data:', response.data)
    
    const { data } = response
    console.log('📦 Raw response data:', data)
    console.log('📦 Data role:', data?.data?.role)
    console.log('📦 Data structure:', JSON.stringify(data, null, 2))
    
    // Use the company object from the assign ref
    const company = assign.value.company
    console.log('🏢 Company object:', company)
    
    const membershipData = {
      id: company.id, // Company UUID (for display as Company ID)
      name: company.name, // Company name (for display)
      slug: company.slug, // Company slug (for display as Company Slug)
      role: data.data.role, // User's role in this company (nested in data.data)
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    }
    
    console.log('🏢 Constructed membership data:', membershipData)
    
    user.value.memberships.unshift(membershipData)
    assign.value.company = null
    assign.value.role = { value: 'viewer', label: 'Viewer' }
    addToast('User assigned to company.', 'success')
  } catch (e) {
    console.error('💥 API call failed:', e)
    console.error('Error response:', e?.response?.data)
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
    
    // Update the membership in the array using the response data
    const index = user.value.memberships.findIndex(mem => mem.id === m.id)
    if (index !== -1) {
      console.log('🔄 Updating membership in array at index:', index)
      user.value.memberships.splice(index, 1, {
        ...user.value.memberships[index],
        ...data, // Use the response data which includes the updated role
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

// Check if selected company is already assigned
const isCompanyAlreadyAssigned = computed(() => {
  if (!assign.value.company) return false
  const companyId = assign.value.company.slug || assign.value.company.id
  return user.value?.memberships?.some(
    membership => membership.id === companyId
  ) || false
})

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Admin', url: '/admin', icon: 'settings' },
  { label: 'Users', url: '/admin/users', icon: 'users' },
  { label: user?.name || 'User', url: '#' }
])

// Custom styles for copy behavior and tabs
const customStyles = `
<style>
/* Copy group hover behavior */
.copy-group:hover .copy-icon {
  opacity: 1 !important;
}
.copy-icon:hover {
  opacity: 1 !important;
}

/* Fix tab indicator once and for all */
.p-tabview .p-tabview-nav {
  position: relative;
  border-bottom: 1px solid var(--p-content-border-color);
  background: transparent;
}

.p-tabview .p-tabview-nav-container {
  position: relative;
}

.p-tabview .p-tabview-tab {
  position: relative;
}

.p-tabview .p-tabview-nav-link {
  padding: 1rem 1.5rem;
  font-weight: 500;
  color: var(--p-text-muted-color);
  background: transparent;
  border: none;
  transition: color 0.2s;
}

.p-tabview .p-tabview-nav-link:hover {
  color: var(--p-text-color);
}

.p-tabview .p-tabview-nav-link.p-tabview-active {
  color: var(--p-primary-color);
}

.p-tabview .p-tabview-active-bar {
  position: absolute;
  bottom: -1px;
  height: 2px;
  background: var(--p-primary-color) !important;
  transition: all 0.3s ease;
  border-radius: 2px 2px 0 0;
  z-index: 1;
}

/* Force proper width calculation */
.p-tabview .p-tabview-tab {
  flex: 0 0 auto !important;
}

.p-tabview .p-tabview-active-bar {
  width: auto !important;
  left: 0 !important;
  right: 0 !important;
}
</style>
`

const copyToClipboard = async (text) => {
  try {
    await navigator.clipboard.writeText(text)
    addToast('Copied to clipboard', 'success')
  } catch (err) {
    console.error('Failed to copy text: ', err)
    // Fallback for older browsers
    const textArea = document.createElement('textarea')
    textArea.value = text
    document.body.appendChild(textArea)
    textArea.select()
    document.execCommand('copy')
    document.body.removeChild(textArea)
    addToast('Copied to clipboard', 'success')
  }
}
</script>

<template>
  <Head :title="user ? `User · ${user.name}` : 'User'" />
  
  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Admin Panel" />
    </template>

    <template #topbar>
      <div class="flex items-center justify-between">
        <!-- Breadcrumb Navigation -->
        <Breadcrumb :items="breadcrumbItems" />
      </div>
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
                <div class="copy-group flex items-center gap-2">
                  <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ user?.name || '—' }}</h1>
                  <button 
                    @click="copyToClipboard(user?.name)"
                    class="copy-icon opacity-0 hover:opacity-100 transition-opacity text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    v-tooltip="'Copy User Name'"
                  >
                    <SvgIcon name="copy" set="line" class="w-4 h-4" />
                  </button>
                </div>
                <div class="copy-group flex items-center gap-2 mt-1">
                  <p class="text-gray-600 dark:text-gray-400">{{ user?.email }}</p>
                  <button 
                    @click="copyToClipboard(user?.email)"
                    class="copy-icon opacity-0 hover:opacity-100 transition-opacity text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    v-tooltip="'Copy Email'"
                  >
                    <SvgIcon name="copy" set="line" class="w-4 h-4" />
                  </button>
                </div>
                <div class="copy-group flex items-center gap-2 mt-1">
                  <p class="text-xs text-gray-500 dark:text-gray-500 font-mono">ID: {{ user?.id }}</p>
                  <button 
                    @click="copyToClipboard(user?.id)"
                    class="copy-icon opacity-0 hover:opacity-100 transition-opacity text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    v-tooltip="'Copy User ID'"
                  >
                    <SvgIcon name="copy" set="line" class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <Badge :value="user?.is_active ? 'Active' : 'Inactive'" :severity="user?.is_active ? 'success' : 'danger'" />
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
                    <CompanyPicker v-model="assign.company" :exclude-user-id="props.id" class="w-full" placeholder="Search for company…" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-2">Role</label>
                    <Dropdown v-model="assign.role" :options="roleOptions" optionLabel="label" class="w-full" placeholder="Select role" />
                  </div>
                  <div>
                    <Button 
                      @click="assignToCompany" 
                      :loading="assignLoading" 
                      :disabled="!assign.company || !assign.role || isCompanyAlreadyAssigned"
                      :label="isCompanyAlreadyAssigned ? 'Already Assigned' : 'Assign User'" 
                      icon="pi pi-user-plus" 
                      class="w-full" 
                      v-tooltip="isCompanyAlreadyAssigned ? 'User is already assigned to this company' : ''"
                    />
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
    
    <!-- Custom styles for tab indicator -->
    <div v-html="customStyles"></div>
  </LayoutShell>
</template>
