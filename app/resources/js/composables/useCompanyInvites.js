import { ref, unref } from 'vue'
import { http } from '@/lib/http'
import { useToasts } from './useToasts.js'
import { useApiForm } from './useApiForm.js'

export function useCompanyInvites(company) {
  const invites = ref([])
  const invitesLoading = ref(false)
  const invitesError = ref('')

  const revokeId = ref('')

  const { addToast } = useToasts()

  const companySlug = () => encodeURIComponent(unref(company))

  const {
    loading: inviteLoading,
    error: inviteError,
    data: inviteOk,
    execute: sendInvite,
    form: invite,
  } = useApiForm(
    (formData) => http.post(`/web/companies/${companySlug()}/invite`, formData),
    {
      initialFormState: { email: '', role: 'viewer', expires_in_days: 14 },
      onSuccess: (newInvite) => {
        invites.value.unshift(newInvite)
        addToast('Invitation sent successfully.', 'success')
      },
    }
  )

  async function revokeInvite(id) {
    const target = id || revokeId.value
    if (!target) return
    try {
      await http.post(`/web/invitations/${target}/revoke`)
      if (inviteOk.value && inviteOk.value.id === target) inviteOk.value.status = 'revoked'
      revokeId.value = ''
      invites.value = invites.value.filter(i => i.id !== target)
      addToast('Invitation revoked.', 'success')
    } catch (e) {
      addToast(e?.response?.data?.message || 'Failed to revoke invitation', 'danger')
    }
  }

  async function loadInvites() {
    invitesLoading.value = true
    invitesError.value = ''
    try {
      const { data } = await http.get(`/web/companies/${companySlug()}/invitations`, { params: { status: 'pending' } })
      invites.value = data.data || []
    } catch (e) {
      invitesError.value = e?.response?.data?.message || 'Failed to load invitations'
    } finally {
      invitesLoading.value = false
    }
  }

  return {
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
  }
}
