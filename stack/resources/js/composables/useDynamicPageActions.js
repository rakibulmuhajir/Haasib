import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { usePageActions } from './usePageActions'
import { useCompanyContext } from './useCompanyContext'

export function useDynamicPageActions() {
  const page = usePage()
  const { setActions, clearActions } = usePageActions()
  const { hasPermission } = useCompanyContext()

  // Get current route information
  const currentRoute = computed(() => {
    const url = page.props.url || window.location.pathname
    return url
  })

  // Determine actions based on current route
  const pageActions = computed(() => {
    const route = currentRoute.value
    
    // Companies index page
    if (route.includes('/companies') && !route.includes('/create') && !route.match(/\/[a-f0-9-]{36}/)) {
      return getCompaniesIndexActions()
    }
    
    // Companies create page
    if (route.includes('/companies/create')) {
      return getCompaniesCreateActions()
    }
    
    // Companies show page
    if (route.includes('/companies/') && route.match(/\/[a-f0-9-]{36}/) && !route.includes('/edit')) {
      return getCompaniesShowActions()
    }
    
    // Customers index page
    if (route.includes('/customers') && !route.includes('/create') && !route.match(/\/[a-f0-9-]{36}/)) {
      return getCustomersIndexActions()
    }
    
    // Invoices index page
    if (route.includes('/invoices') && !route.includes('/create') && !route.match(/\/[a-f0-9-]{36}/)) {
      return getInvoicesIndexActions()
    }
    
    // Dashboard
    if (route.includes('/dashboard')) {
      return getDashboardActions()
    }
    
    // Settings pages
    if (route.includes('/settings')) {
      return getSettingsActions()
    }
    
    return []
  })

  function getCompaniesIndexActions() {
    const actions = []
    
    // Always add refresh
    actions.push({
      key: 'refresh',
      label: 'Refresh',
      icon: 'fas fa-sync-alt',
      severity: 'secondary',
      outlined: true,
      click: () => window.location.reload(),
      tooltip: 'Refresh companies list'
    })

    // Add company (if user has permission)
    if (hasPermission('company.manage') || page.props.auth?.user?.system_role === 'system_owner') {
      actions.push({
        key: 'create',
        label: 'Create Company',
        icon: 'fas fa-plus',
        severity: 'primary',
        click: () => router.visit('/companies/create'),
        tooltip: 'Create a new company'
      })
    }

    // Export companies
    if (hasPermission('company.manage')) {
      actions.push({
        key: 'export',
        label: 'Export',
        icon: 'fas fa-download',
        severity: 'secondary',
        outlined: true,
        click: () => exportCompanies(),
        tooltip: 'Export companies to CSV'
      })
    }

    return actions
  }

  function getCompaniesCreateActions() {
    return [
      {
        key: 'cancel',
        label: 'Cancel',
        icon: 'fas fa-times',
        severity: 'secondary',
        outlined: true,
        click: () => router.visit('/companies'),
        tooltip: 'Cancel and return to companies'
      },
      {
        key: 'save',
        label: 'Save Company',
        icon: 'fas fa-save',
        severity: 'primary',
        click: () => {
          // Trigger form submission
          const form = document.querySelector('form')
          if (form) form.requestSubmit()
        },
        tooltip: 'Save new company'
      }
    ]
  }

  function getCompaniesShowActions() {
    const actions = []
    
    actions.push({
      key: 'back',
      label: 'Back to Companies',
      icon: 'fas fa-arrow-left',
      severity: 'secondary',
      outlined: true,
      click: () => router.visit('/companies'),
      tooltip: 'Return to companies list'
    })

    // Edit company (if user has permission)
    if (hasPermission('company.manage')) {
      actions.push({
        key: 'edit',
        label: 'Edit Company',
        icon: 'fas fa-edit',
        severity: 'primary',
        outlined: true,
        click: () => {
          const companyId = window.location.pathname.split('/')[2]
          router.visit(`/companies/${companyId}/edit`)
        },
        tooltip: 'Edit company details'
      })
    }

    return actions
  }

  function getCustomersIndexActions() {
    const actions = []
    
    actions.push({
      key: 'refresh',
      label: 'Refresh',
      icon: 'fas fa-sync-alt',
      severity: 'secondary',
      outlined: true,
      click: () => window.location.reload(),
      tooltip: 'Refresh customers list'
    })

    if (hasPermission('customers.create')) {
      actions.push({
        key: 'create',
        label: 'Add Customer',
        icon: 'fas fa-plus',
        severity: 'primary',
        click: () => router.visit('/customers/create'),
        tooltip: 'Add a new customer'
      })
    }

    if (hasPermission('customers.export')) {
      actions.push({
        key: 'export',
        label: 'Export',
        icon: 'fas fa-download',
        severity: 'secondary',
        outlined: true,
        click: () => exportCustomers(),
        tooltip: 'Export customers to CSV'
      })
    }

    return actions
  }

  function getInvoicesIndexActions() {
    const actions = []
    
    actions.push({
      key: 'refresh',
      label: 'Refresh',
      icon: 'fas fa-sync-alt',
      severity: 'secondary',
      outlined: true,
      click: () => window.location.reload(),
      tooltip: 'Refresh invoices list'
    })

    if (hasPermission('invoices.create')) {
      actions.push({
        key: 'create',
        label: 'Create Invoice',
        icon: 'fas fa-plus',
        severity: 'primary',
        click: () => router.visit('/invoices/create'),
        tooltip: 'Create a new invoice'
      })
    }

    if (hasPermission('invoices.export')) {
      actions.push({
        key: 'export',
        label: 'Export',
        icon: 'fas fa-download',
        severity: 'secondary',
        outlined: true,
        click: () => exportInvoices(),
        tooltip: 'Export invoices to CSV'
      })
    }

    return actions
  }

  function getDashboardActions() {
    return [
      {
        key: 'refresh',
        label: 'Refresh',
        icon: 'fas fa-sync-alt',
        severity: 'secondary',
        outlined: true,
        click: () => window.location.reload(),
        tooltip: 'Refresh dashboard'
      }
    ]
  }

  function getSettingsActions() {
    return [
      {
        key: 'refresh',
        label: 'Refresh',
        icon: 'fas fa-sync-alt',
        severity: 'secondary',
        outlined: true,
        click: () => window.location.reload(),
        tooltip: 'Refresh settings'
      }
    ]
  }

  // Export functions (these would need to be implemented)
  function exportCompanies() {
    // Implementation for exporting companies
    console.log('Exporting companies...')
    window.open('/api/companies/export', '_blank')
  }

  function exportCustomers() {
    // Implementation for exporting customers
    console.log('Exporting customers...')
    window.open('/customers/export', '_blank')
  }

  function exportInvoices() {
    // Implementation for exporting invoices
    console.log('Exporting invoices...')
    window.open('/invoices/export', '_blank')
  }

  // Initialize actions based on current route
  const initializeActions = () => {
    const actions = pageActions.value
    if (actions.length > 0) {
      setActions(actions)
    }
  }

  // Add bulk actions support
  const addBulkActions = (selectedItems, itemType = 'items') => {
    const bulkActions = []
    
    if (selectedItems.length === 0) return []

    // Delete selected
    bulkActions.push({
      key: 'bulk-delete',
      label: `Delete Selected (${selectedItems.length})`,
      icon: 'fas fa-trash',
      severity: 'danger',
      click: () => performBulkAction('delete', selectedItems, itemType),
      tooltip: `Delete ${selectedItems.length} selected ${itemType}`
    })

    // Export selected
    bulkActions.push({
      key: 'bulk-export',
      label: `Export Selected (${selectedItems.length})`,
      icon: 'fas fa-download',
      severity: 'secondary',
      outlined: true,
      click: () => performBulkAction('export', selectedItems, itemType),
      tooltip: `Export ${selectedItems.length} selected ${itemType}`
    })

    return bulkActions
  }

  // Perform bulk action
  const performBulkAction = (action, selectedItems, itemType) => {
    console.log(`Performing ${action} on ${selectedItems.length} ${itemType}:`, selectedItems)
    
    // This would typically make an API call to perform the bulk action
    switch (action) {
      case 'delete':
        if (confirm(`Are you sure you want to delete ${selectedItems.length} ${itemType}?`)) {
          // Make API call for bulk deletion
          console.log('Deleting items...')
        }
        break
      case 'export':
        // Make API call for bulk export
        console.log('Exporting items...')
        break
      default:
        console.log('Unknown bulk action:', action)
    }
  }

  return {
    pageActions,
    initializeActions,
    addBulkActions,
    performBulkAction,
    setActions,
    clearActions
  }
}