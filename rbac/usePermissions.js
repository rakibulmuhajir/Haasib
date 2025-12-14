// composables/usePermissions.js
// Use with Inertia - permissions are shared via HandleInertiaRequests middleware

import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function usePermissions() {
    const page = usePage()

    // Get permissions array from Inertia shared data
    const permissions = computed(() => page.props.permissions || [])

    // Get current role
    const role = computed(() => page.props.role || null)

    // Get current company
    const currentCompany = computed(() => page.props.currentCompany || null)

    /**
     * Check if user has a specific permission
     * @param {string} permission - Permission name (e.g., 'accounts_invoice_create')
     */
    function can(permission) {
        return permissions.value.includes(permission)
    }

    /**
     * Check if user has any of the given permissions
     * @param {string[]} permissionList - Array of permission names
     */
    function canAny(permissionList) {
        return permissionList.some(p => permissions.value.includes(p))
    }

    /**
     * Check if user has all of the given permissions
     * @param {string[]} permissionList - Array of permission names
     */
    function canAll(permissionList) {
        return permissionList.every(p => permissions.value.includes(p))
    }

    /**
     * Check if user has a specific role
     * @param {string} roleName - Role name (e.g., 'owner')
     */
    function hasRole(roleName) {
        return role.value === roleName
    }

    /**
     * Check if user is owner
     */
    const isOwner = computed(() => role.value === 'owner')

    /**
     * Check if user is accountant
     */
    const isAccountant = computed(() => role.value === 'accountant')

    /**
     * Check if user is viewer
     */
    const isViewer = computed(() => role.value === 'viewer')

    return {
        permissions,
        role,
        currentCompany,
        can,
        canAny,
        canAll,
        hasRole,
        isOwner,
        isAccountant,
        isViewer,
    }
}

// Example usage in a component:
//
// <script setup>
// import { usePermissions } from '@/composables/usePermissions'
//
// const { can, isOwner, currentCompany } = usePermissions()
// </script>
//
// <template>
//   <div>
//     <h1>{{ currentCompany.name }}</h1>
//
//     <button v-if="can('accounts_invoice_create')">
//       Create Invoice
//     </button>
//
//     <button v-if="can('accounts_invoice_approve')">
//       Approve
//     </button>
//
//     <div v-if="isOwner">
//       Owner-only content
//     </div>
//   </div>
// </template>
