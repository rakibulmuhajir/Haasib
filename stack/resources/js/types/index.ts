/**
 * Common TypeScript type definitions for Haasib Application
 * 
 * This file contains shared interfaces and types used across the frontend application.
 * It helps ensure type safety and provides IntelliSense support for common data structures.
 */

// ============================================================================
// CORE ENTITIES
// ============================================================================

export interface Company {
  id: string
  name: string
  legal_name?: string
  slug: string
  email: string
  phone?: string
  website?: string
  industry: string
  base_currency: string
  fiscal_year_start: string
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface User {
  id: string
  name: string
  email: string
  username: string
  system_role: 'system_owner' | 'super_admin' | 'admin' | 'manager' | 'employee'
  current_company_id?: string
  is_active: boolean
  email_verified_at?: string
  created_at: string
  updated_at: string
}

export interface Customer {
  id: string
  company_id: string
  name: string
  email?: string
  phone?: string
  address?: string
  currency?: string
  tax_number?: string
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface Vendor {
  id: string
  company_id: string
  legal_name: string
  display_name?: string
  email?: string
  phone?: string
  address?: string
  tax_number?: string
  is_active: boolean
  created_at: string
  updated_at: string
}

// ============================================================================
// ACCOUNTING ENTITIES
// ============================================================================

export interface Invoice {
  id: string
  company_id: string
  customer_id: string
  invoice_number: string
  issue_date: string
  due_date: string
  currency: string
  exchange_rate: number
  subtotal: number
  tax_amount: number
  total_amount: number
  balance_due: number
  status: 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled'
  notes?: string
  terms?: string
  created_at: string
  updated_at: string
}

export interface InvoiceLineItem {
  id: string
  invoice_id: string
  description: string
  quantity: number
  unit_price: number
  discount_percentage: number
  tax_rate: number
  total: number
  created_at: string
}

export interface Payment {
  id: string
  company_id: string
  customer_id: string
  amount: number
  currency: string
  exchange_rate: number
  payment_date: string
  payment_method: string
  reference_number?: string
  notes?: string
  status: 'pending' | 'completed' | 'failed'
  remaining_amount: number
  created_at: string
  updated_at: string
}

// ============================================================================
// API & PAGINATION
// ============================================================================

export interface PaginatedData<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
  has_more_pages: boolean
}

export interface ApiResponse<T = any> {
  success: boolean
  data?: T
  message?: string
  errors?: Record<string, string[]>
}

export interface Filters {
  search?: string
  page?: number
  per_page?: number
  sort_by?: string
  sort_direction?: 'asc' | 'desc'
  status?: string
  date_from?: string
  date_to?: string
}

// ============================================================================
// FORMS & VALIDATION
// ============================================================================

export interface FormErrors {
  [key: string]: string | string[]
}

export interface FormState<T> {
  data: T
  errors: FormErrors
  processing: boolean
  recentlySuccessful: boolean
  dirty: boolean
}

// ============================================================================
// VUE COMPONENT PROPS & EMITS
// ============================================================================

export interface BaseComponentProps {
  id?: string
  class?: string
  style?: string | Record<string, any>
}

export interface ModalProps extends BaseComponentProps {
  visible: boolean
  header?: string
  dismissableMask?: boolean
  closable?: boolean
}

export interface TableProps<T = any> extends BaseComponentProps {
  data: T[]
  loading?: boolean
  totalRecords?: number
  rows?: number
  first?: number
  sortField?: string
  sortOrder?: number
  filters?: Record<string, any>
}

// ============================================================================
// REPORTING & ANALYTICS
// ============================================================================

export interface FinancialSummary {
  total_revenue: number
  total_expenses: number
  net_income: number
  cash_flow: number
  period_start: string
  period_end: string
  currency: string
}

export interface TrialBalanceAccount {
  id: string
  account_number: string
  account_name: string
  account_type: 'Asset' | 'Liability' | 'Equity' | 'Revenue' | 'Expense'
  debit: number
  credit: number
  balance: number
}

export interface TrialBalance {
  company_name: string
  currency: string
  period: {
    date_from: string
    date_to: string
  }
  generated_at: string
  summary: {
    account_count: number
    total_debits: number
    total_credits: number
    total_difference: number
    is_balanced: boolean
  }
  accounts: TrialBalanceAccount[]
}

// ============================================================================
// SYSTEM & ADMINISTRATION
// ============================================================================

export interface Module {
  id: string
  key: string
  name: string
  version: string
  description?: string
  is_enabled: boolean
  is_active: boolean
  category: string
  settings?: Record<string, any>
}

export interface Permission {
  id: string
  name: string
  description?: string
  group: string
}

export interface Role {
  id: string
  name: string
  description?: string
  permissions: Permission[]
}

// ============================================================================
// UTILITY TYPES
// ============================================================================

export type Optional<T, K extends keyof T> = Omit<T, K> & Partial<Pick<T, K>>
export type RequiredFields<T, K extends keyof T> = T & Required<Pick<T, K>>
export type ID = string
export type Timestamp = string
export type Currency = 'USD' | 'EUR' | 'GBP' | 'CAD' | 'AUD' | 'JPY' | 'CNY'
export type SortDirection = 'asc' | 'desc'
export type Status = 'active' | 'inactive' | 'pending' | 'completed' | 'failed'

// ============================================================================
// EVENT TYPES
// ============================================================================

export interface CompanySwitchEvent {
  type: 'company-switched'
  companyId: string
  previousCompanyId?: string
  timestamp: string
}

export interface UserLoginEvent {
  type: 'user-login'
  userId: string
  companyId?: string
  timestamp: string
  ipAddress: string
}

// ============================================================================
// CHART & DATA VISUALIZATION
// ============================================================================

export interface ChartData {
  labels: string[]
  datasets: ChartDataset[]
}

export interface ChartDataset {
  label: string
  data: number[]
  backgroundColor?: string | string[]
  borderColor?: string | string[]
  borderWidth?: number
  fill?: boolean
}

export interface ChartOptions {
  responsive?: boolean
  maintainAspectRatio?: boolean
  plugins?: {
    legend?: {
      position?: 'top' | 'bottom' | 'left' | 'right'
      display?: boolean
    }
    tooltip?: {
      enabled?: boolean
      mode?: 'index' | 'dataset' | 'point' | 'nearest'
    }
  }
  scales?: {
    x?: {
      display?: boolean
      beginAtZero?: boolean
    }
    y?: {
      display?: boolean
      beginAtZero?: boolean
    }
  }
}

export default {
  Company,
  User,
  Customer,
  Invoice,
  Payment,
  PaginatedData,
  ApiResponse,
  FinancialSummary,
}