export function useCustomerLookups() {
  const CUSTOMER_TYPE_MAP = {
    individual: 'Individual',
    business: 'Business',
    non_profit: 'Non-Profit',
    government: 'Government'
  }

  const CUSTOMER_TYPE_SEVERITY = {
    individual: 'info',
    business: 'success',
    non_profit: 'warning',
    government: 'secondary'
  }

  const STATUS_SEVERITY_MAP = {
    active: 'success',
    inactive: 'secondary',
    suspended: 'danger'
  }

  const formatCustomerType = (type: string): string => {
    return CUSTOMER_TYPE_MAP[type as keyof typeof CUSTOMER_TYPE_MAP] || type
  }

  const getCustomerTypeSeverity = (type: string): string => {
    return CUSTOMER_TYPE_SEVERITY[type as keyof typeof CUSTOMER_TYPE_SEVERITY] || 'secondary'
  }

  const formatStatus = (status: string): string => {
    return STATUS_SEVERITY_MAP[status as keyof typeof STATUS_SEVERITY_MAP] ? 
      status.charAt(0).toUpperCase() + status.slice(1) : 
      status
  }

  const getStatusSeverity = (status: string): string => {
    return STATUS_SEVERITY_MAP[status as keyof typeof STATUS_SEVERITY_MAP] || 'secondary'
  }

  return {
    formatCustomerType,
    getCustomerTypeSeverity,
    formatStatus,
    getStatusSeverity
  }
}