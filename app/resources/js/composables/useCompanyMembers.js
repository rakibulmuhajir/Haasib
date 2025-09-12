import { ref, watch, unref } from 'vue'
import { http, withIdempotency } from '@/lib/http'
import { useToasts } from './useToasts.js'
import { useApiForm } from './useApiForm.js'

const roleOptions = [
  { value: 'owner', label: 'Owner' },
  { value: 'admin', label: 'Admin' },
  { value: 'accountant', label: 'Accountant' },
  { value: 'viewer', label: 'Viewer' },
]

export function useCompanyMembers(company) {
  const members = ref([])
  const loading = ref(false)
  const error = ref('')
  const q = ref('')

  const { addToast } = useToasts()

  const companySlug = () => encodeURIComponent(unref(company))

  async function loadMembers() {
    loading.value = true
    error.value = ''
    try {
      const { data } = await http.get(`/web/companies/${companySlug()}/users`, { params: { q: q.value, limit: 100 } })
      members.value = (data.data || []).map(m => ({ ...m }))
    } catch (e) {
      error.value = e?.response?.data?.message || 'Failed to load members'
    } finally {
      loading.value = false
    }
  }

  const {
    loading: assignLoading,
    error: assignError,
    execute: assignUser,
    form: assign,
  } = useApiForm(
    (formData) => http.post('/commands', { ...formData, company: unref(company) }, { headers: withIdempotency({ 'X-Action': 'company.assign' }) }),
    {
      initialFormState: { email: '', role: 'viewer' },
      onSuccess: (newMember) => {
        members.value.unshift(newMember)
        addToast('User assigned successfully.', 'success')
      },
    }
  )

  async function updateRole(m) {
    console.log('🚀 updateRole FUNCTION CALLED - useCompanyMembers.js')
    console.log('Input parameter m:', m)

    const originalRole = members.value.find(mem => mem.id === m.id)?.role
    console.log('Original role from members array:', originalRole)
    console.log('New role from parameter:', m.role)

    if (originalRole === m.role) {
      console.log('❌ Role unchanged, returning early')
      return
    }

    console.log('📡 Making API call to /commands...')

    try {
      const payload = {
        email: m.email,
        company: unref(company),
        role: m.role,
      }
      console.log('📤 Request payload:', payload)

      const { data } = await http.post('/commands', payload, {
        headers: withIdempotency({ 'X-Action': 'company.assign' })
      })

      console.log('📥 API response received:', data)

      const index = members.value.findIndex(mem => mem.id === m.id)
      if (index !== -1) {
        console.log('🔄 Updating member in array at index:', index)
        members.value.splice(index, 1, data.data)
        console.log('✅ Member array updated')
      }

      addToast('Role updated successfully.', 'success')
      console.log('🎉 Success toast shown')
    } catch (e) {
      console.error('💥 API call failed:', e)
      console.error('Error response:', e?.response?.data)
      addToast(e?.response?.data?.message || 'Failed to update role', 'danger')
    }
  }

  async function unassign(m) {
    if (!confirm(`Remove ${m.email} from ${unref(company)}?`)) return
    try {
      await http.post('/commands', {
        email: m.email,
        company: unref(company),
      }, { headers: withIdempotency({ 'X-Action': 'company.unassign' }) })
      members.value = members.value.filter(mem => mem.id !== m.id)
      addToast('User removed successfully.', 'success')
    } catch (e) {
      addToast(e?.response?.data?.message || 'Failed to remove user', 'danger')
    }
  }

  watch(q, () => { const t = setTimeout(loadMembers, 250); return () => clearTimeout(t) })

  return {
    members,
    loading,
    error,
    q,
    roleOptions,
    assign,
    assignLoading,
    assignError,
    loadMembers,
    assignUser,
    updateRole,
    unassign,
  }
}
