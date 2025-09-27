export function useLookups() {
  const formatByMap = (value: string, map: Record<string, string>, defaultValue?: string): string => {
    return map[value] || defaultValue || value
  }

  // --- Customer Type ---
  const customerTypeMap: Record<string, string> = {
    'individual': 'Individual',
    'small_business': 'Small Business',
    'medium_business': 'Medium Business',
    'large_business': 'Large Business',
    'non_profit': 'Non-Profit',
    'government': 'Government',
  }
  const customerTypeSeverityMap: Record<string, string> = {
    'individual': 'info',
    'small_business': 'success',
    'medium_business': 'success',
    'large_business': 'success',
    'non_profit': 'warning',
    'government': 'secondary',
  }
  const formatCustomerType = (type: string) => formatByMap(type, customerTypeMap)
  const getCustomerTypeSeverity = (type: string) => formatByMap(type, customerTypeSeverityMap, 'secondary')

  // --- Status ---
  const statusMap: Record<string, string> = {
    'active': 'Active',
    'inactive': 'Inactive',
    'suspended': 'Suspended',
  }
  const statusSeverityMap: Record<string, string> = {
    'active': 'success',
    'inactive': 'secondary',
    'suspended': 'danger',
  }
  const formatStatus = (status: string) => formatByMap(status, statusMap)

  // --- Invoice Status ---
  const invoiceStatusSeverityMap: Record<string, string> = {
    draft: 'secondary',
    sent: 'info',
    posted: 'warning',
    paid: 'success',
    cancelled: 'danger',
    void: 'contrast'
  }
  const getInvoiceStatusSeverity = (status: string) => formatByMap(status, invoiceStatusSeverityMap, 'secondary')
  const getStatusSeverity = (status: string) => formatByMap(status, statusSeverityMap, 'secondary')

  // --- Activity ---
  const activityIconMap: Record<string, string> = {
    'invoice': 'fas fa-file-invoice',
    'payment': 'fas fa-money-bill-wave',
    'credit': 'fas fa-credit-card',
    'debit': 'fas fa-arrow-down',
    'adjustment': 'fas fa-edit',
  }
  const getActivityIcon = (type: string) => formatByMap(type, activityIconMap, 'fas fa-circle')

  // --- Payment Method ---
  const paymentMethodMap: Record<string, string> = {
    'cash': 'Cash',
    'check': 'Check',
    'bank_transfer': 'Bank Transfer',
    'credit_card': 'Credit Card',
    'debit_card': 'Debit Card',
    'paypal': 'PayPal',
    'stripe': 'Stripe',
    'other': 'Other'
  }
  const paymentMethodSeverityMap: Record<string, string> = {
    cash: 'success',
    bank_transfer: 'success',
    credit_card: 'info',
    debit_card: 'info',
    paypal: 'warning',
    stripe: 'warning',
    check: 'secondary',
    other: 'secondary'
  }
  const formatPaymentMethod = (method: string) => formatByMap(method, paymentMethodMap)
  const getPaymentMethodSeverity = (method: string) => formatByMap(method, paymentMethodSeverityMap, 'secondary')

  return {
    formatCustomerType,
    getCustomerTypeSeverity,
    formatStatus,
    getInvoiceStatusSeverity,
    getStatusSeverity,
    getActivityIcon,
    formatPaymentMethod,
    getPaymentMethodSeverity,
  }
}
