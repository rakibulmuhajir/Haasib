<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ref, onMounted, computed } from 'vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import CompanyPicker from '@/Components/Pickers/CompanyPicker.vue'
import UserMembershipList from '@/Components/UserMembershipList.vue'
import { http, withIdempotency } from '@/lib/http';
import { TabsRoot as Tabs, TabsList, TabsTrigger, TabsContent } from 'reka-ui';
import { useToasts } from '@/composables/useToasts.js'
import { usePersistentTabs } from '@/composables/usePersistentTabs.js'

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
    user.value = data.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load user'
  } finally {
    loading.value = false
  }
}

onMounted(load)

// Assign to company
const assign = ref({ company: '', role: 'viewer' })
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
      role: assign.value.role,
    }, { headers: withIdempotency({ 'X-Action': 'company.assign' }) })
    user.value.memberships.unshift(data.data)
    assign.value.company = ''
    assign.value.role = 'viewer'
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
  const originalRole = user.value.memberships.find(mem => mem.id === m.id)?.role
  if (originalRole === m.role) return // No change
  try {
    const { data } = await http.post('/commands', {
      email: user.value.email,
      company: m.slug || m.id,
      role: m.role,
    }, { headers: withIdempotency({ 'X-Action': 'company.assign' }) })
    const index = user.value.memberships.findIndex(mem => mem.id === m.id)
    if (index !== -1) user.value.memberships.splice(index, 1, data.data)
    addToast('Role changed successfully.', 'success')
  } catch (e) {
    m.role = originalRole // Revert UI on failure
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
const { selectedTab } = usePersistentTabs(tabNames, storageKey)
const tabValue = computed({
  get: () => String(selectedTab.value),
  set: (val) => { selectedTab.value = Number(val) }
})
</script>

<template>
  <Head :title="user ? `User · ${user.name}` : 'User'" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">User</h2>
        <Link :href="route('admin.users.index')" class="text-sm text-gray-600 hover:underline">Back to users</Link>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
        <div v-if="error" class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Profile -->
          <div class="lg:col-span-1">
            <div class="overflow-hidden bg-white shadow sm:rounded-md p-6">
              <div class="text-lg font-semibold">{{ user?.name || '—' }}</div>
              <div class="text-sm text-gray-600">{{ user?.email }}</div>
              <div class="mt-3 text-xs text-gray-500">ID: {{ user?.id }}</div>
            </div>
          </div>

          <!-- Tabs for memberships vs assign -->
          <div class="lg:col-span-2">
            <Tabs v-model="tabValue" class="w-full">
              <div class="sticky top-16 z-10 bg-white/80 backdrop-blur supports-[backdrop-filter]:bg-white/60">
                <TabsList class="flex space-x-2 border-b border-gray-200 px-2">
                  <TabsTrigger value="0" class="focus:outline-none px-4 py-2 text-sm data-[state=active]:border-b-2 data-[state=active]:border-indigo-600 data-[state=active]:text-indigo-600 text-gray-600 hover:text-gray-800">
                    Memberships
                  </TabsTrigger>
                  <TabsTrigger value="1" class="focus:outline-none px-4 py-2 text-sm data-[state=active]:border-b-2 data-[state=active]:border-indigo-600 data-[state=active]:text-indigo-600 text-gray-600 hover:text-gray-800">
                    Assign
                  </TabsTrigger>
                </TabsList>
              </div>
              <div class="mt-4">
                <TabsContent value="0">
                  <UserMembershipList
                    :memberships="user?.memberships || []"
                    :loading="loading"
                    :role-options="roleOptions"
                    @update-role="changeRole"
                    @unassign="unassign"
                  />
                </TabsContent>
                <TabsContent value="1">
                  <div class="overflow-hidden bg-white shadow sm:rounded-md p-6">
                    <div class="font-medium mb-3">Assign to Company</div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                      <div>
                        <InputLabel value="Company" />
                        <CompanyPicker v-model="assign.company" class="mt-1 block w-full" placeholder="Find company by name or slug…" />
                      </div>
                      <div>
                        <InputLabel value="Role" />
                        <select v-model="assign.role" class="mt-1 block w-full rounded border-gray-300">
                          <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                      </div>
                      <div>
                        <PrimaryButton @click="assignToCompany" :disabled="assignLoading">Assign</PrimaryButton>
                        <span v-if="assignLoading" class="ms-2 text-sm text-gray-500">Assigning…</span>
                      </div>
                    </div>
                    <div v-if="assignError" class="mt-3 rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">{{ assignError }}</div>
                  </div>
                </TabsContent>
              </div>
            </Tabs>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
