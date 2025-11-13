/**
 * Composable for TypeScript type utilities and helpers
 * 
 * This composable provides reusable type-safe functions and utilities
 * for working with TypeScript types in Vue components.
 */

import type { 
  Company, 
  User, 
  Customer, 
  Invoice, 
  Payment,
  PaginatedData,
  Filters,
  FormErrors,
  Currency
} from '@/types'

export function useTypes() {
  // ============================================================================
  // TYPE GUARDS
  // ============================================================================

  /**
   * Type guard to check if value is a valid Company
   */
  const isCompany = (value: any): value is Company => {
    return value && 
           typeof value.id === 'string' &&
           typeof value.name === 'string' &&
           typeof value.slug === 'string' &&
           typeof value.email === 'string'
  }

  /**
   * Type guard to check if value is a valid User
   */
  const isUser = (value: any): value is User => {
    return value && 
           typeof value.id === 'string' &&
           typeof value.name === 'string' &&
           typeof value.email === 'string' &&
           typeof value.username === 'string'
  }

  /**
   * Type guard to check if value is a valid Invoice
   */
  const isInvoice = (value: any): value is Invoice => {
    return value && 
           typeof value.id === 'string' &&
           typeof value.invoice_number === 'string' &&
           typeof value.total_amount === 'number' &&
           ['draft', 'sent', 'paid', 'overdue', 'cancelled'].includes(value.status)
  }

  // ============================================================================
  // CURRENCY VALIDATION
  // ============================================================================

  /**
   * Check if a string is a valid currency code
   */
  const isValidCurrency = (currency: string): currency is Currency => {
    const validCurrencies: Currency[] = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CNY']
    return validCurrencies.includes(currency as Currency)
  }

  /**
   * Format currency with proper typing
   */
  const formatCurrency = (amount: number, currency: Currency = 'USD'): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount)
  }

  // ============================================================================
  // FORM HELPERS
  // ============================================================================

  /**
   * Type-safe form error handler
   */
  const getFormErrorMessage = (errors: FormErrors, field: string): string => {
    const error = errors[field]
    if (typeof error === 'string') return error
    if (Array.isArray(error)) return error[0] || ''
    return ''
  }

  /**
   * Check if form has errors
   */
  const hasFormErrors = (errors: FormErrors): boolean => {
    return Object.keys(errors).length > 0
  }

  /**
   * Get first error message from form
   */
  const getFirstFormError = (errors: FormErrors): string | null => {
    const firstKey = Object.keys(errors)[0]
    if (!firstKey) return null
    return getFormErrorMessage(errors, firstKey)
  }

  // ============================================================================
  // PAGINATION HELPERS
  // ============================================================================

  /**
   * Type-safe pagination metadata extractor
   */
  const getPaginationMeta = <T>(data: PaginatedData<T>) => {
    return {
      currentPage: data.current_page,
      lastPage: data.last_page,
      perPage: data.per_page,
      total: data.total,
      from: data.from,
      to: data.to,
      hasMorePages: data.has_more_pages,
      isFirstPage: data.current_page === 1,
      isLastPage: data.current_page === data.last_page,
    }
  }

  /**
   * Check if paginated data has items
   */
  const hasPaginatedItems = <T>(data: PaginatedData<T>): boolean => {
    return data.data.length > 0
  }

  // ============================================================================
  // FILTER HELPERS
  // ============================================================================

  /**
   * Create type-safe filters object
   */
  const createFilters = (filters: Partial<Filters> = {}): Filters => {
    return {
      search: filters.search || '',
      page: filters.page || 1,
      per_page: filters.per_page || 10,
      sort_by: filters.sort_by || 'created_at',
      sort_direction: filters.sort_direction || 'desc',
      status: filters.status || '',
      date_from: filters.date_from || '',
      date_to: filters.date_to || '',
    }
  }

  /**
   * Convert filters to query string
   */
  const filtersToQueryString = (filters: Filters): string => {
    const params = new URLSearchParams()
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value && value !== '') {
        params.append(key, value.toString())
      }
    })
    
    return params.toString()
  }

  // ============================================================================
  // ENTITY HELPERS
  // ============================================================================

  /**
   * Get company display name safely
   */
  const getCompanyDisplayName = (company: Company | null | undefined): string => {
    if (!company) return 'Unknown Company'
    return company.legal_name || company.name || 'Unnamed Company'
  }

  /**
   * Get user display name safely
   */
  const getUserDisplayName = (user: User | null | undefined): string => {
    if (!user) return 'Unknown User'
    return user.name || user.username || 'Unnamed User'
  }

  /**
   * Check if entity is active
   */
  const isEntityActive = <T extends { is_active: boolean }>(entity: T | null | undefined): boolean => {
    return entity?.is_active ?? false
  }

  // ============================================================================
  // DATE HELPERS
  // ============================================================================

  /**
   * Format date safely with type checking
   */
  const formatDate = (date: string | Date | null | undefined, format: 'short' | 'long' | 'time' = 'short'): string => {
    if (!date) return 'N/A'
    
    const dateObj = typeof date === 'string' ? new Date(date) : date
    
    if (isNaN(dateObj.getTime())) return 'Invalid Date'
    
    switch (format) {
      case 'short':
        return dateObj.toLocaleDateString()
      case 'long':
        return dateObj.toLocaleDateString('en-US', { 
          year: 'numeric', 
          month: 'long', 
          day: 'numeric' 
        })
      case 'time':
        return dateObj.toLocaleString()
      default:
        return dateObj.toLocaleDateString()
    }
  }

  /**
   * Check if date is in the past
   */
  const isDateInPast = (date: string | Date): boolean => {
    const dateObj = typeof date === 'string' ? new Date(date) : date
    return dateObj.getTime() < Date.now()
  }

  /**
   * Check if date is overdue (past and should be completed)
   */
  const isOverdue = (dueDate: string | Date): boolean => {
    return isDateInPast(dueDate)
  }

  // ============================================================================
  // STATUS HELPERS
  // ============================================================================

  /**
   * Get status CSS classes for common statuses
   */
  const getStatusClasses = (status: string): string => {
    const statusMap: Record<string, string> = {
      active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
      inactive: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
      pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
      completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
      failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
      draft: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
      sent: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
      paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
      overdue: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
      cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    }
    
    return statusMap[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
  }

  /**
   * Get invoice status display text
   */
  const getInvoiceStatusText = (status: Invoice['status']): string => {
    const statusMap: Record<Invoice['status'], string> = {
      draft: 'Draft',
      sent: 'Sent',
      paid: 'Paid',
      overdue: 'Overdue',
      cancelled: 'Cancelled',
    }
    
    return statusMap[status] || status
  }

  // ============================================================================
  // VALIDATION HELPERS
  // ============================================================================

  /**
   * Validate email format
   */
  const isValidEmail = (email: string): boolean => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return emailRegex.test(email)
  }

  /**
   * Validate phone number format (basic)
   */
  const isValidPhone = (phone: string): boolean => {
    const phoneRegex = /^[\d\s\-\+\(\)]+$/
    return phoneRegex.test(phone) && phone.replace(/\D/g, '').length >= 10
  }

  /**
   * Validate URL format
   */
  const isValidUrl = (url: string): boolean => {
    try {
      new URL(url)
      return true
    } catch {
      return false
    }
  }

  return {
    // Type Guards
    isCompany,
    isUser,
    isInvoice,
    
    // Currency
    isValidCurrency,
    formatCurrency,
    
    // Form Helpers
    getFormErrorMessage,
    hasFormErrors,
    getFirstFormError,
    
    // Pagination
    getPaginationMeta,
    hasPaginatedItems,
    
    // Filters
    createFilters,
    filtersToQueryString,
    
    // Entity Helpers
    getCompanyDisplayName,
    getUserDisplayName,
    isEntityActive,
    
    // Date Helpers
    formatDate,
    isDateInPast,
    isOverdue,
    
    // Status Helpers
    getStatusClasses,
    getInvoiceStatusText,
    
    // Validation
    isValidEmail,
    isValidPhone,
    isValidUrl,
  }
}

// Export types for external use
export type {
  Company,
  User,
  Customer,
  Invoice,
  Payment,
  PaginatedData,
  Filters,
  FormErrors,
  Currency
}