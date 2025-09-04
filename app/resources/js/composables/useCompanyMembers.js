import { ref, watch, unref } from 'vue'
import { http, withIdempotency } from '@/lib/http'
import { useToasts } from './useToasts.js'

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

  const assign = ref({ email: '', role: 'viewer' })
  const assignLoading = ref(false)
  const assignError = ref('')

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

  async function assignUser() {
    if (!assign.value.email || !assign.value.role) return
    assignLoading.value = true
    assignError.value = ''
    try {
      const { data } = await http.post('/commands', {
        email: assign.value.email,
        company: unref(company),
        role: assign.value.role,
      }, { headers: withIdempotency({ 'X-Action': 'company.assign' }) })
      members.value.unshift(data.data)
      assign.value.email = ''
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
    if (originalRole === m.role) return
    try {
      const { data } = await http.post('/commands', {
        email: m.email,
        company: unref(company),
        role: m.role,
      }, { headers: withIdempotency({ 'X-Action': 'company.assign' }) })
      const index = members.value.findIndex(mem => mem.id === m.id)
      if (index !== -1) members.value.splice(index, 1, data.data)
      addToast('Role updated successfully.', 'success')
    } catch (e) {
      m.role = originalRole
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
