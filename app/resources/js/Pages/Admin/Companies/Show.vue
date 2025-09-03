<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted, watch, computed } from 'vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import UserPicker from '@/Components/Pickers/UserPicker.vue'
import Collapsible from '@/Components/Collapsible.vue'
import CompanyMemberList from '@/Components/CompanyMemberList.vue'
import { http, withIdempotency } from '@/lib/http';
import { TabsRoot as Tabs, TabsList, TabsTrigger, TabsContent } from 'reka-ui';
import { useToasts } from '@/composables/useToasts.js'
import { usePersistentTabs } from '@/composables/usePersistentTabs.js'

const props = defineProps({ company: { type: String, required: true } })
const { addToast } = useToasts()

const c = ref(null)
const members = ref([])
const loading = ref(false)
const error = ref('')
const q = ref('')

const roleOptions = [
  { value: 'owner', label: 'Owner' },
  { value: 'admin', label: 'Admin' },
  { value: 'accountant', label: 'Accountant' },
  { value: 'viewer', label: 'Viewer' },
]

async function loadCompany() {
  try {
    const { data } = await http.get(`/web/companies/${encodeURIComponent(props.company)}`)
    c.value = data.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load company'
  }
}

async function loadMembers() {
  loading.value = true
  try {
    const { data } = await http.get(`/web/companies/${encodeURIComponent(props.company)}/users`, { params: { q: q.value, limit: 100 } })
    // map to allow local edits of role select
    members.value = (data.data || []).map(m => ({ ...m }))
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load members'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadCompany()
  await loadMembers()
  await loadInvites()
})
watch(q, () => { const t = setTimeout(loadMembers, 250); return () => clearTimeout(t) })

// Assign existing user to this company
const assign = ref({ email: '', role: 'viewer' })
const assignLoading = ref(false)
const assignError = ref('')

async function assignUser() {
  if (!assign.value.email || !assign.value.role) return
  assignLoading.value = true
  assignError.value = ''
  try {
    const { data } = await http.post('/commands', {
      email: assign.value.email,
      company: c.value?.slug || props.company,
      role: assign.value.role,
    }, { headers: withIdempotency({ 'X-Action': 'company.assign' }) })
    members.value.unshift(data.data) // Add new member to the top of the list
    assign.value.email = '' // Reset form
    assign.value.role = 'viewer'
    addToast('User assigned successfully.', 'success')
  } catch (e) {
    const message = e?.response?.data?.message || 'Failed to assign user'
    assignError.value = message
    addToast(message, 'danger')
  } finally {
    assignLoading.value = false
  }
}

async function updateRole(m) {
  const originalRole = members.value.find(mem => mem.id === m.id)?.role
  if (originalRole === m.role) return; // No change
  try {
    const { data } = await http.post('/commands', {
      email: m.email,
      company: c.value?.slug || props.company,
      role: m.role,
    }, { headers: withIdempotency({ 'X-Action': 'company.assign' }) })
    const index = members.value.findIndex(mem => mem.id === m.id)
    if (index !== -1) members.value.splice(index, 1, data.data)
    addToast('Role updated successfully.', 'success')
  } catch (e) {
    m.role = originalRole // Revert UI on failure
    addToast(e?.response?.data?.message || 'Failed to update role', 'danger')
  }
}

async function unassign(m) {
  if (!confirm(`Remove ${m.email} from ${c.value?.name || props.company}?`)) return
  try {
    await http.post('/commands', {
      email: m.email,
      company: c.value?.slug || props.company,
    }, { headers: withIdempotency({ 'X-Action': 'company.unassign' }) })
    members.value = members.value.filter(mem => mem.id !== m.id)
    addToast('User removed successfully.', 'success')
  } catch (e) {
    addToast(e?.response?.data?.message || 'Failed to remove user', 'danger')
  }
}

// Invitations
const invite = ref({ email: '', role: 'viewer', expires_in_days: 14 })
const inviteLoading = ref(false)
const inviteError = ref('')
const inviteOk = ref(null) // last invite created (dev purposes)
const invites = ref([])
const invitesLoading = ref(false)
const invitesError = ref('')

async function sendInvite() {
  inviteLoading.value = true
  inviteError.value = ''
  inviteOk.value = null
  try {
    const { data } = await http.post('/commands', {
      ...invite.value,
      company: c.value?.slug || props.company,
    }, { headers: withIdempotency({ 'X-Action': 'company.invite' }) })
    inviteOk.value = data.data
    invites.value.unshift(data.data)
    invite.value.email = ''
    addToast('Invitation sent successfully.', 'success')
  } catch (e) {
    const message = e?.response?.data?.message || 'Failed to create invitation'
    inviteError.value = message
    addToast(message, 'danger')
  } finally {
    inviteLoading.value = false
  }
}

const revokeId = ref('')
async function revokeInvite(id) {
  const target = id || revokeId.value
  if (!target) return
  try {
    await http.post('/commands', {
      id: target,
    }, { headers: withIdempotency({ 'X-Action': 'invitation.revoke' }) })
    if (inviteOk.value && inviteOk.value.id === target) inviteOk.value.status = 'revoked'
    revokeId.value = ''
    invites.value = invites.value.filter(i => i.id !== target)
    addToast('Invitation revoked.', 'success')
  } catch (e) {
    addToast(e?.response?.data?.message || 'Failed to revoke invitation', 'danger')
  }
}

const tabNames = ['members', 'assign', 'invite']
const storageKey = computed(() => `admin.company.tab.${c.value?.slug || props.company}`)
const { selectedTab } = usePersistentTabs(tabNames, storageKey)

async function loadInvites() {
  invitesLoading.value = true
  invitesError.value = ''
  try {
    const target = encodeURIComponent(c.value?.slug || props.company)
    const { data } = await http.get(`/web/companies/${target}/invitations`, { params: { status: 'pending' } })
    invites.value = data.data || []
  } catch (e) {
    invitesError.value = e?.response?.data?.message || 'Failed to load invitations'
  } finally {
    invitesLoading.value = false
  }
}
  </script>

<template>
  <Head :title="c ? `Company · ${c.name}` : 'Company'" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Company</h2>
        <Link :href="route('admin.companies.index')" class="text-sm text-gray-600 hover:underline">Back to companies</Link>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div v-if="error" class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Company summary -->
          <div class="lg:col-span-1">
            <div class="overflow-hidden bg-white shadow sm:rounded-md p-6">
              <div class="text-lg font-semibold">{{ c?.name || '—' }}</div>
              <div class="text-xs text-gray-500 mt-1">Slug: {{ c?.slug }}</div>
              <div class="mt-3 text-sm text-gray-700">
                <div><span class="text-gray-500">Currency:</span> {{ c?.base_currency }}</div>
                <div><span class="text-gray-500">Language:</span> {{ c?.language }}</div>
                <div><span class="text-gray-500">Locale:</span> {{ c?.locale }}</div>
              </div>
            </div>
          </div>

          <!-- Members / Assign / Invite as tabs -->
          <div class="lg:col-span-2">
            <Tabs v-model="selectedTab" class="w-full">
              <div class="sticky top-16 z-10 bg-white/80 backdrop-blur supports-[backdrop-filter]:bg-white/60">
                <TabsList class="flex space-x-2 border-b border-gray-200 px-2">
                  <TabsTrigger value="0" class="focus:outline-none px-4 py-2 text-sm data-[state=active]:border-b-2 data-[state=active]:border-indigo-600 data-[state=active]:text-indigo-600 text-gray-600 hover:text-gray-800">
                    Members
                  </TabsTrigger>
                  <TabsTrigger value="1" class="focus:outline-none px-4 py-2 text-sm data-[state=active]:border-b-2 data-[state=active]:border-indigo-600 data-[state=active]:text-indigo-600 text-gray-600 hover:text-gray-800">
                    Assign
                  </TabsTrigger>
                  <TabsTrigger value="2" class="focus:outline-none px-4 py-2 text-sm data-[state=active]:border-b-2 data-[state=active]:border-indigo-600 data-[state=active]:text-indigo-600 text-gray-600 hover:text-gray-800">
                    Invite
                  </TabsTrigger>
                </TabsList>
              </div>
              <div class="mt-4">
                <!-- Members Tab -->
                <TabsContent value="0">
                  <CompanyMemberList
                    :members="members"
                    :loading="loading"
                    :role-options="roleOptions"
                    v-model:query="q"
                    @update-role="updateRole"
                    @unassign="unassign"
                  />
                </TabsContent>

                <!-- Assign Tab -->
                <TabsContent value="1">
                  <div class="overflow-hidden bg-white shadow sm:rounded-md p-6">
                    <div class="font-medium mb-3">Assign Existing User</div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                      <div>
                        <InputLabel value="User" />
                        <UserPicker v-model="assign.email" class="mt-1 block w-full" placeholder="Find user by name or email…" />
                      </div>
                      <div>
                        <InputLabel value="Role" />
                        <select v-model="assign.role" class="mt-1 block w-full rounded border-gray-300">
                          <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                      </div>
                      <div>
                        <PrimaryButton @click="assignUser" :disabled="assignLoading">Assign</PrimaryButton>
                        <span v-if="assignLoading" class="ms-2 text-sm text-gray-500">Assigning…</span>
                      </div>
                    </div>
                    <div v-if="assignError" class="mt-3 rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">{{ assignError }}</div>
                  </div>
                </TabsContent>

                <!-- Invite Tab -->
                <TabsContent value="2">
                  <div class="overflow-hidden bg-white shadow sm:rounded-md p-6">
                    <div class="font-medium mb-3">Invite by Email</div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                      <div class="md:col-span-2">
                        <InputLabel value="Email" />
                        <TextInput v-model="invite.email" class="mt-1 block w-full" placeholder="invitee@example.com" />
                      </div>
                      <div>
                        <InputLabel value="Role" />
                        <select v-model="invite.role" class="mt-1 block w-full rounded border-gray-300">
                          <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                      </div>
                      <div>
                        <InputLabel value="Expires in days" />
                        <TextInput v-model="invite.expires_in_days" class="mt-1 block w-full" placeholder="14" />
                      </div>
                    </div>
                    <div class="mt-3">
                      <PrimaryButton @click="sendInvite" :disabled="inviteLoading">Send Invitation</PrimaryButton>
                      <span v-if="inviteLoading" class="ms-2 text-sm text-gray-500">Sending…</span>
                    </div>
                    <div v-if="inviteError" class="mt-3 rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">{{ inviteError }}</div>
                    <div v-if="inviteOk" class="mt-3 rounded border border-green-200 bg-green-50 p-2 text-xs text-green-700">
                      Invitation created for <b>{{ inviteOk.email }}</b> (role: {{ inviteOk.role }}) · id: <code>{{ inviteOk.id }}</code>
                      <div class="mt-1">Token (dev only): <code class="break-all">{{ inviteOk.token }}</code></div>
                      <div class="mt-2 flex items-center gap-2">
                        <TextInput v-model="revokeId" placeholder="Paste invitation id to revoke" class="w-72" />
                        <SecondaryButton @click="revokeInvite(inviteOk.id)">Revoke this invite</SecondaryButton>
                        <SecondaryButton @click="revokeInvite()">Revoke by id</SecondaryButton>
                      </div>
                    </div>

                    <div class="mt-6">
                      <Collapsible :defaultOpen="true">
                        <template #trigger>
                          <button class="flex w-full items-center justify-between rounded bg-gray-50 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-100">
                            <span>Pending Invitations</span>
                            <span class="text-xs text-gray-500">(click to toggle)</span>
                          </button>
                        </template>
                        <div class="pt-2">
                          <div v-if="invitesLoading" class="text-sm text-gray-500">Loading…</div>
                          <div v-else-if="invitesError" class="text-sm text-red-600">{{ invitesError }}</div>
                          <div v-else>
                            <ul class="divide-y divide-gray-200">
                              <li v-for="i in invites" :key="i.id" class="py-3 flex items-center justify-between">
                                <div>
                                  <div class="text-sm font-medium text-gray-900">{{ i.email }}</div>
                                  <div class="text-xs text-gray-500">role: {{ i.role }} · invited by: {{ i.invited_by || '—' }} · expires: {{ i.expires_at || '—' }}</div>
                                </div>
                                <div>
                                  <SecondaryButton @click="revokeInvite(i.id)">Revoke</SecondaryButton>
                                </div>
                              </li>
                              <li v-if="(invites || []).length === 0" class="py-3 text-sm text-gray-500">No pending invitations.</li>
                            </ul>
                            </div>
                          </div>
                      </Collapsible>
                    </div>
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
