import { ref, computed, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/inertia-vue3'

export function useCompanyContext() {
    const page = usePage()
    const loading = ref(false)
    const error = ref(null)

    // Computed properties
    const currentCompany = computed(() => page.props.currentCompany)
    const userCompanies = computed(() => page.props.userCompanies || [])
    const user = computed(() => page.props.auth?.user)
    
    const hasCompanies = computed(() => userCompanies.value.length > 0)
    const canCreateCompany = computed(() => {
        return user.value?.system_role === 'system_owner' || 
               userCompanies.value.some(c => c.userRole === 'owner')
    })
    
    const currentCompanyRole = computed(() => currentCompany.value?.userRole)
    const currentCompanyId = computed(() => currentCompany.value?.id)
    
    // Company permissions
    const permissions = computed(() => {
        const companyPerms = currentCompany.value?.permissions || []
        return {
            canManage: companyPerms.includes('company.manage'),
            canInvite: companyPerms.includes('company.invite'),
            canViewSettings: companyPerms.includes('settings.manage'),
            canViewUsers: companyPerms.includes('company.users.view'),
            canManageUsers: companyPerms.includes('company.users.manage'),
            canAccessInvoicing: companyPerms.includes('invoices.view'),
            canManageInvoicing: companyPerms.includes('invoices.manage'),
            canAccessAccounting: companyPerms.includes('accounting.view'),
            canManageAccounting: companyPerms.includes('accounting.manage'),
            canViewReports: companyPerms.includes('reports.view')
        }
    })

    // Form for switching companies
    const switchForm = useForm({
        company_id: null
    })

    // Methods
    const switchToCompany = async (companyId) => {
        if (!companyId || companyId === currentCompanyId.value) {
            return false
        }

        loading.value = true
        error.value = null

        try {
            switchForm.company_id = companyId
            
            const response = await fetch('/api/v1/company-context/switch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    company_id: companyId
                })
            })

            const data = await response.json()

            if (response.ok) {
                // Reload the page to update context
                setTimeout(() => {
                    window.location.reload()
                }, 500)
                return true
            } else {
                throw new Error(data.message || 'Failed to switch company')
            }
        } catch (err) {
            error.value = err.message || 'Failed to switch company'
            console.error('Company context switch error:', err)
            return false
        } finally {
            loading.value = false
            switchForm.reset()
        }
    }

    const getCurrentCompanyUser = () => {
        if (!currentCompany.value || !user.value) {
            return null
        }

        return userCompanies.value.find(uc => 
            uc.id === currentCompany.value.id
        )
    }

    const getUserRoleInCompany = (companyId) => {
        const companyUser = userCompanies.value.find(uc => uc.id === companyId)
        return companyUser?.userRole || null
    }

    const hasPermission = (permission) => {
        return permissions.value[`can${permission.charAt(0).toUpperCase() + permission.slice(1)}`] || false
    }

    const hasAnyPermission = (permissions) => {
        return permissions.some(perm => hasPermission(perm))
    }

    const canPerformAction = (action, resource = null) => {
        // Check if user has the specific permission
        if (hasPermission(action)) {
            return true
        }

        // Additional logic for resource-specific permissions
        if (resource) {
            // Special cases for owners and admins
            if (currentCompanyRole.value === 'owner' || currentCompanyRole.value === 'admin') {
                const ownerAdminActions = ['manage', 'invite', 'manageUsers']
                return ownerAdminActions.some(action => 
                    action.toLowerCase().includes(action.toLowerCase())
                )
            }
        }

        return false
    }

    const getCompanyById = (companyId) => {
        return userCompanies.value.find(uc => uc.id === companyId)
    }

    const getCompanyBySlug = (slug) => {
        return userCompanies.value.find(uc => uc.slug === slug)
    }

    const refreshCompanyContext = async () => {
        try {
            const response = await fetch('/api/v1/company-context', {
                headers: {
                    'Accept': 'application/json'
                }
            })

            if (response.ok) {
                const data = await response.json()
                // This would trigger a page reload if the context has changed
                router.reload()
            }
        } catch (err) {
            console.error('Failed to refresh company context:', err)
        }
    }

    // Utility methods
    const formatCompanyDisplay = (company) => {
        if (!company) return 'No Company'
        return `${company.name} (${company.industry})`
    }

    const getCompanyAvatarData = (company) => {
        if (!company) {
            return { label: '?', color: 'bg-gray-400' }
        }

        const initials = company.name
            .split(' ')
            .map(word => word.charAt(0))
            .join('')
            .toUpperCase()
            .slice(0, 2)

        const colors = [
            'bg-blue-500',
            'bg-green-500',
            'bg-purple-500',
            'bg-orange-500',
            'bg-pink-500',
            'bg-indigo-500',
            'bg-red-500',
            'bg-yellow-500'
        ]

        const colorIndex = company.name.charCodeAt(0) % colors.length

        return {
            label: initials,
            color: colors[colorIndex]
        }
    }

    const isCurrentCompany = (companyId) => {
        return companyId === currentCompanyId.value
    }

    const validateCompanyAccess = (companyId) => {
        const company = getCompanyById(companyId)
        return !!company && company.userRole && company.isActive
    }

    // Event watchers
    watch(currentCompanyId, (newCompanyId, oldCompanyId) => {
        if (newCompanyId && newCompanyId !== oldCompanyId) {
            // Company context changed
            console.log('Company context changed from', oldCompanyId, 'to', newCompanyId)
        }
    })

    // Return reactive state and methods
    return {
        // State
        currentCompany,
        userCompanies,
        user,
        loading,
        error,
        
        // Computed
        hasCompanies,
        canCreateCompany,
        currentCompanyRole,
        currentCompanyId,
        permissions,
        
        // Methods
        switchToCompany,
        getCurrentCompanyUser,
        getUserRoleInCompany,
        hasPermission,
        hasAnyPermission,
        canPerformAction,
        getCompanyById,
        getCompanyBySlug,
        refreshCompanyContext,
        
        // Utilities
        formatCompanyDisplay,
        getCompanyAvatarData,
        isCurrentCompany,
        validateCompanyAccess,
        
        // Form
        switchForm
    }
}

// Default export for easy importing
export default useCompanyContext