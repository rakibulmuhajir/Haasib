<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ref, onMounted, computed, watch } from 'vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import CompanyPicker from '@/Components/Pickers/CompanyPicker.vue'
import { http, withIdempotency } from '@/lib/http'
import { TabGroup, TabList, Tab, TabPanels, TabPanel, Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'

const props = defineProps({ id: { type: String, required: true } })
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
    await http.post('/commands', {
      email: user.value.email,
      company: assign.value.company,
      role: assign.value.role,
    }, { headers: withIdempotency({ 'X-Action': 'company.assign' }) })
    await load()
    assign.value.company = ''
    assign.value.role = 'viewer'
  } catch (e) {
    assignError.value = e?.response?.data?.message || 'Failed to assign'
  } finally {
    assignLoading.value = false
  }
}

async function changeRole(m) {
  try {
    await http.post('/commands', {
      email: user.value.email,
      company: m.slug || m.id,
      role: m.role,
    }, { headers: withIdempotency({ 'X-Action': 'company.assign' }) })
    await load()
  } catch (e) {
    alert(e?.response?.data?.message || 'Failed to change role')
  }
}

async function unassign(m) {
  if (!confirm(`Remove ${user.value.email} from ${m.name}?`)) return
  try {
    await http.post('/commands', {
      email: user.value.email,
      company: m.slug || m.id,
    }, { headers: withIdempotency({ 'X-Action': 'company.unassign' }) })
    await load()
  } catch (e) {
    alert(e?.response?.data?.message || 'Failed to remove')
  }
}

// Persist selected tab (hash or localStorage)
const tabNames = ['memberships','assign']
const selectedTab = ref(0)
function applyInitialTab() {
  const m = window.location.hash.match(/tab=([A-Za-z0-9_-]+)/)
  let name = m ? m[1] : null
  if (!name) {
    const saved = localStorage.getItem(`admin.user.tab.${props.id}`)
    name = saved || null
  }
  const idx = name ? Math.max(0, tabNames.indexOf(name)) : 0
  selectedTab.value = idx
}
applyInitialTab()

watch(selectedTab, (i) => {
  const name = tabNames[i] || tabNames[0]
  try { localStorage.setItem(`admin.user.tab.${props.id}`, name) } catch {}
  const base = window.location.hash.replace(/tab=[^&]*/,'').replace(/^#&?/,'')
  const next = base ? `#${base}&tab=${name}` : `#tab=${name}`
  if (window.location.hash !== next) window.location.hash = next
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
            <TabGroup v-model:selectedIndex="selectedTab" as="div">
              <div class="sticky top-16 z-10 bg-white/80 backdrop-blur supports-[backdrop-filter]:bg-white/60">
                <TabList class="flex space-x-2 border-b border-gray-200 px-2">
                  <Tab as="template" v-slot="{ selected }">
                    <button class="focus:outline-none" :class="['px-4 py-2 text-sm', selected ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-600 hover:text-gray-800']">Memberships</button>
                  </Tab>
                  <Tab as="template" v-slot="{ selected }">
                    <button class="focus:outline-none" :class="['px-4 py-2 text-sm', selected ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-600 hover:text-gray-800']">Assign</button>
                  </Tab>
                </TabList>
              </div>
              <TabPanels class="mt-4">
                <TabPanel>
                  <div class="overflow-hidden bg-white shadow sm:rounded-md">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                      <div class="font-medium">Company Memberships</div>
                    </div>
                    <ul role="list" class="divide-y divide-gray-200">
                      <li v-for="m in user?.memberships || []" :key="m.id + ':' + m.slug" class="px-6 py-4">
                        <div class="flex items-start justify-between gap-4">
                          <div>
                            <div class="text-sm font-medium text-gray-900">{{ m.name }}</div>
                            <div class="text-xs text-gray-500">{{ m.slug }}</div>
                          </div>
                          <div class="flex items-center gap-2">
                            <select v-model="m.role" class="rounded border-gray-300 text-sm">
                              <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                            </select>
                            <PrimaryButton @click="changeRole(m)">Update</PrimaryButton>
                            <SecondaryButton @click="unassign(m)">Remove</SecondaryButton>
                          </div>
                        </div>
                        <Disclosure>
                          <DisclosureButton class="mt-2 text-xs text-indigo-600 hover:underline">More</DisclosureButton>
                          <Transition enter="transition ease-out duration-150" enter-from="opacity-0 -translate-y-1" enter-to="opacity-100 translate-y-0" leave="transition ease-in duration-100" leave-from="opacity-100" leave-to="opacity-0 -translate-y-1">
                            <DisclosurePanel class="mt-2 rounded border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700">
                              <div class="flex items-center justify-between">
                                <div>
                                  <div><span class="text-gray-500">Company ID:</span> {{ m.id }}</div>
                                  <div><span class="text-gray-500">Slug:</span> {{ m.slug }}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                  <Link :href="route('admin.companies.show', m.slug)" class="text-indigo-600 hover:underline">Open company</Link>
                                  <button type="button" class="text-gray-600 hover:text-gray-900" @click="navigator.clipboard?.writeText(m.slug)">Copy slug</button>
                                </div>
                              </div>
                            </DisclosurePanel>
                          </Transition>
                        </Disclosure>
                      </li>
                      <li v-if="!loading && (user?.memberships?.length || 0) === 0" class="px-6 py-4 text-sm text-gray-500">No memberships.</li>
                      <li v-if="loading" class="px-6 py-4 text-sm text-gray-500">Loading…</li>
                    </ul>
                  </div>
                </TabPanel>
                <TabPanel>
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
                </TabPanel>
              </TabPanels>
            </TabGroup>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
