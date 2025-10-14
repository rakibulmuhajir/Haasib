import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function usePermissions() {
    const page = usePage()
    
    const has = (permission) => {
        return page.props.auth.companyPermissions?.includes(permission) ?? false
    }
    
    const hasSystemPermission = (permission) => {
        return page.props.auth.permissions?.includes(permission) ?? false
    }
    
    const hasRole = (role) => {
        return page.props.auth.roles?.company?.includes(role) ?? false
    }
    
    const hasSystemRole = (role) => {
        return page.props.auth.roles?.system?.includes(role) ?? false
    }
    
    const canManageCompany = computed(() => 
        page.props.auth.canManageCompany ?? false
    )
    
    const isSuperAdmin = computed(() => 
        page.props.auth.isSuperAdmin ?? false
    )
    
    const currentCompanyId = computed(() => 
        page.props.auth.currentCompanyId
    )
    
    // Helper methods for common permission checks
    const can = {
        // Company management
        viewCompany: () => has('companies.view'),
        manageCompanySettings: () => has('companies.settings.update'),
        viewCurrencies: () => isSuperAdmin.value || has('companies.currencies.view'),
        manageCurrencies: () => isSuperAdmin.value || has('companies.currencies.enable') || has('companies.currencies.disable'),
        
        // User management
        inviteUsers: () => has('users.invite'),
        manageUsers: () => has('users.update') || has('users.deactivate'),
        assignRoles: () => has('users.roles.assign'),
        
        // Ledger
        viewLedger: () => has('ledger.view'),
        createLedgerEntries: () => has('ledger.entries.create'),
        postLedgerEntries: () => has('ledger.entries.post'),
        
        // Invoices
        viewInvoices: () => has('invoices.view'),
        createInvoices: () => has('invoices.create'),
        editInvoices: () => has('invoices.update'),
        deleteInvoices: () => has('invoices.delete'),
        sendInvoices: () => has('invoices.send'),
        
        // Payments
        viewPayments: () => has('payments.view'),
        createPayments: () => has('payments.create'),
        allocatePayments: () => has('payments.allocate'),
        
        // Customers
        viewCustomers: () => has('customers.view'),
        createCustomers: () => has('customers.create'),
        editCustomers: () => has('customers.update'),
        deleteCustomers: () => has('customers.delete'),
        
        // Vendors
        viewVendors: () => has('vendors.view'),
        createVendors: () => has('vendors.create'),
        editVendors: () => has('vendors.update'),
        deleteVendors: () => has('vendors.delete'),
        
        // Reports
        viewReports: () => has('reports.financial.view'),
        createReports: () => has('reports.custom.create'),
        exportReports: () => has('reports.export'),
        
        // System permissions (for super admins)
        manageSystem: () => hasSystemPermission('system.companies.manage'),
        manageSystemCurrencies: () => hasSystemPermission('system.currencies.manage'),
        manageSystemUsers: () => hasSystemPermission('system.users.manage'),
    }
    
    // Role-based helpers
    const is = {
        owner: () => hasRole('owner'),
        admin: () => hasRole('admin'),
        manager: () => hasRole('manager'),
        accountant: () => hasRole('accountant'),
        employee: () => hasRole('employee'),
        viewer: () => hasRole('viewer'),
        superAdmin: () => hasSystemRole('super_admin'),
    }
    
    return {
        has,
        hasSystemPermission,
        hasRole,
        hasSystemRole,
        canManageCompany,
        isSuperAdmin,
        currentCompanyId,
        can,
        is,
    }
}