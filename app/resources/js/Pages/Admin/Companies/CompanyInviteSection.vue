<script setup>
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import Collapsible from '@/Components/Collapsible.vue'
import { onMounted, computed } from 'vue'
import { useCompanyInvites } from '@/composables/useCompanyInvites.js'

const props = defineProps({
  company: { type: String, required: true }
})

const {
  invite,
  inviteLoading,
  inviteError,
  inviteOk,
  invites,
  invitesLoading,
  invitesError,
  revokeId,
  sendInvite,
  revokeInvite,
  loadInvites,
} = useCompanyInvites(computed(() => props.company))

onMounted(loadInvites)
</script>

<template>
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
          <option value="owner">Owner</option>
          <option value="admin">Admin</option>
          <option value="accountant">Accountant</option>
          <option value="viewer">Viewer</option>
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
</template>
