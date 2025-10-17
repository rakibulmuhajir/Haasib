import axios from 'axios'
import router from '@inertiajs/vue3/router'

// API base configuration
const API_BASE_URL = '/api/v1/ledger'

// Create axios instance for period close API
const periodCloseApi = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  },
  withCredentials: true
})

// Add response interceptor for error handling
periodCloseApi.interceptors.response.use(
  (response) => response,
  (error) => {
    // Handle common error scenarios
    if (error.response?.status === 401) {
      // Unauthorized - redirect to login
      router.visit('/login')
    } else if (error.response?.status === 403) {
      // Forbidden - show permission error
      console.error('Permission denied:', error.response.data?.error || 'Insufficient permissions')
    } else if (error.response?.status === 404) {
      // Not found
      console.error('Resource not found:', error.response.data?.error || 'Resource not found')
    } else if (error.response?.status >= 500) {
      // Server error
      console.error('Server error:', error.response.data?.error || 'Internal server error')
    }
    
    return Promise.reject(error)
  }
)

// Types for API requests and responses
export interface StartPeriodCloseRequest {
  notes?: string
}

export interface UpdateTaskRequest {
  status: 'pending' | 'in_progress' | 'completed' | 'blocked' | 'waived'
  notes?: string
  attachments?: string[]
}

export interface PeriodCloseResponse {
  id: string
  accounting_period_id: string
  status: string
  started_at?: string
  started_by?: string
  closing_summary?: string
  tasks_count: number
  tasks: Array<{
    id: string
    code: string
    title: string
    category: string
    sequence: number
    status: string
    is_required: boolean
    notes?: string
  }>
}

export interface ValidationResponse {
  status: string
  score: number
  trial_balance: {
    is_balanced: boolean
    total_debits: number
    total_credits: number
    difference: number
  }
  unposted_documents: {
    count: number
    total_amount: number
    document_types: Record<string, number>
  }
  issues: Array<{
    type: 'error' | 'warning' | 'info'
    code: string
    message: string
    category: string
    affected_accounts?: string[]
    suggested_action?: string
    priority: 'high' | 'medium' | 'low'
  }>
  recommendations: string[]
  validation_metadata: {
    validation_timestamp: string
    validated_by: string
    validation_version: string
    checks_performed: string[]
  }
}

export interface PeriodCloseSnapshot {
  period: {
    id: string
    name: string
    start_date: string
    end_date: string
    status: string
    fiscal_year: {
      id: string
      name: string
    }
    can_be_closed: boolean
    duration_days: number
  }
  period_close: {
    id: string
    status: string
    started_at?: string
    started_by?: string
    closing_summary?: string
    tasks_count: number
    completed_tasks_count: number
    required_tasks_count: number
    required_completed_count: number
    completion_percentage: number
    required_completion_percentage: number
    tasks: Array<{
      id: string
      code: string
      title: string
      category: string
      sequence: number
      status: string
      is_required: boolean
      notes?: string
      completed_by?: string
      completed_at?: string
      started_by?: string
      started_at?: string
    }>
  }
  permissions: {
    can_view: boolean
    can_start: boolean
    can_validate: boolean
    can_lock: boolean
    can_complete: boolean
    can_reopen: boolean
    can_adjust: boolean
    can_manage_tasks: boolean
    can_view_reports: boolean
  }
  period_metadata: {
    is_overdue: boolean
    days_since_end: number
    deadline_date?: string
    working_days_used?: number
    working_days_remaining?: number
  }
  task_statistics: {
    total_tasks: number
    completed_tasks: number
    in_progress_tasks: number
    blocked_tasks: number
    waived_tasks: number
    required_tasks: number
    required_completed: number
    overdue_tasks: number
    average_task_duration?: number
    estimated_completion?: string
  }
  recent_activity: Array<{
    type: string
    description: string
    timestamp: string
    user?: string
    details?: Record<string, any>
  }>
  available_actions: Array<{
    action: string
    label: string
    description: string
    method: string
    endpoint: string
    primary?: boolean
    warning?: string
  }>
}

// API Service Class
export class PeriodCloseApiService {
  /**
   * Get period close index with all periods and their statuses
   */
  static async getPeriodCloseIndex(): Promise<{
    data: any[]
    meta: {
      total: number
      current_company_id: string
    }
  }> {
    const response = await periodCloseApi.get('/periods/close')
    return response.data
  }

  /**
   * Get detailed period close snapshot for a specific period
   */
  static async getPeriodCloseSnapshot(periodId: string): Promise<PeriodCloseSnapshot> {
    const response = await periodCloseApi.get(`/periods/${periodId}/close`)
    return response.data
  }

  /**
   * Start a period close workflow
   */
  static async startPeriodClose(
    periodId: string, 
    data: StartPeriodCloseRequest = {}
  ): Promise<PeriodCloseResponse> {
    const response = await periodCloseApi.post(`/periods/${periodId}/close/start`, data)
    return response.data.data
  }

  /**
   * Run period close validations
   */
  static async validatePeriodClose(periodId: string): Promise<ValidationResponse> {
    const response = await periodCloseApi.post(`/periods/${periodId}/close/validate`)
    return response.data.data
  }

  /**
   * Update a period close task
   */
  static async updateTask(
    periodId: string, 
    taskId: string, 
    data: UpdateTaskRequest
  ): Promise<{
    id: string
    status: string
    completed_by?: string
    completed_at?: string
    notes?: string
    attachment_manifest?: string[]
  }> {
    const response = await periodCloseApi.patch(`/periods/${periodId}/close/tasks/${taskId}`, data)
    return response.data.data
  }

  /**
   * Complete a task (shortcut method)
   */
  static async completeTask(
    periodId: string, 
    taskId: string, 
    notes?: string, 
    attachments?: string[]
  ): Promise<any> {
    return this.updateTask(periodId, taskId, {
      status: 'completed',
      notes,
      attachments
    })
  }

  /**
   * Get available actions for a period
   */
  static async getPeriodActions(periodId: string): Promise<{
    actions: Array<{
      action: string
      label: string
      description: string
      method: string
      endpoint: string
      primary?: boolean
      warning?: string
    }>
  }> {
    const response = await periodCloseApi.get(`/periods/${periodId}/close/actions`)
    return response.data
  }

  /**
   * Get period close statistics for dashboard
   */
  static async getPeriodCloseStatistics(): Promise<{
    total_periods: number
    closed_periods: number
    active_closes: number
    open_periods: number
    periods_with_tasks: number
    recent_activity: any[]
    upcoming_deadlines: any[]
  }> {
    const response = await periodCloseApi.get('/periods/close/statistics')
    return response.data
  }

  /**
   * Get all periods with their close status (alternative endpoint)
   */
  static async getPeriodsWithCloseStatus(): Promise<any[]> {
    const response = await periodCloseApi.get('/periods')
    return response.data.data
  }

  /**
   * Create a period close adjustment entry
   */
  static async createAdjustment(
    periodId: string,
    adjustmentData: {
      description: string
      reference: string
      entry_date?: string
      lines: Array<{
        account_id: string
        description: string
        debit_amount?: number
        credit_amount?: number
      }>
      notes?: string
    }
  ): Promise<{
    id: string
    type: string
    status: string
    total_debits: number
    total_credits: number
    description: string
    reference: string
    entry_date: string
    metadata: Record<string, any>
  }> {
    const response = await periodCloseApi.post(`/periods/${periodId}/close/adjustments`, adjustmentData)
    return response.data.data
  }

  /**
   * Get period close adjustments for a period
   */
  static async getAdjustments(periodId: string): Promise<{
    adjustments: Array<{
      id: string
      type: string
      status: string
      total_debits: number
      total_credits: number
      description: string
      reference: string
      entry_date: string
      created_by: string
      created_at: string
      metadata: Record<string, any>
      lines: Array<{
        account_id: string
        account_code: string
        account_name: string
        description: string
        debit_amount: number
        credit_amount: number
      }>
    }>
    summary: {
      total_count: number
      total_debits: number
      total_credits: number
      net_change: number
    }
  }> {
    const response = await periodCloseApi.get(`/periods/${periodId}/close/adjustments`)
    return response.data.data
  }

  /**
   * Delete a period close adjustment entry
   */
  static async deleteAdjustment(
    periodId: string,
    journalEntryId: string
  ): Promise<{
    id: string
    status: string
    deleted_at: string
    deleted_by: string
    audit_trail: {
      deleted_reason: string
      approval_required: boolean
      approved_by?: string
      approved_at?: string
    }
  }> {
    const response = await periodCloseApi.delete(`/periods/${periodId}/close/adjustments/${journalEntryId}`)
    return response.data.data
  }

  /**
   * Lock a period close
   */
  static async lockPeriodClose(
    periodId: string,
    reason: string
  ): Promise<{
    id: string
    status: string
    locked_at: string
    locked_by: string
    lock_reason: string
  }> {
    const response = await periodCloseApi.post(`/periods/${periodId}/close/lock`, {
      reason
    })
    return response.data.data
  }

  /**
   * Complete a period close
   */
  static async completePeriodClose(
    periodId: string,
    notes?: string
  ): Promise<{
    id: string
    status: string
    completed_at: string
    completed_by: string
    completion_notes?: string
    accounting_period_status: string
  }> {
    const response = await periodCloseApi.post(`/periods/${periodId}/close/complete`, {
      notes
    })
    return response.data.data
  }

  /**
   * Check if a period close can be locked
   */
  static async canLockPeriodClose(periodId: string): Promise<{
    can_lock: boolean
    blocking_issues: string[]
    period_close_status: string
    accounting_period_status: string
    task_statistics: {
      total_tasks: number
      required_tasks: number
      completed_tasks: number
      required_completed: number
      completion_rate: number
      required_completion_rate: number
    }
    last_validation_score?: number
    pending_adjustments_count: number
  }> {
    const response = await periodCloseApi.get(`/periods/${periodId}/close/can-lock`)
    return response.data.data
  }

  /**
   * Check if a period close can be completed
   */
  static async canCompletePeriodClose(periodId: string): Promise<{
    can_complete: boolean
    blocking_issues: string[]
    period_close_status: string
    accounting_period_status: string
    lock_information: {
      locked_at?: string
      locked_by?: string
      lock_age_hours?: number
      max_lock_age_hours: number
      lock_expired: boolean
      lock_reason?: string
    }
    task_statistics: {
      total_tasks: number
      completed_tasks: number
      completion_rate: number
    }
    final_validation_score?: number
    pending_journal_entries: number
    total_adjustments: number
    completion_metadata: {
      estimated_minutes: number
      system_health: {
        database_connection: boolean
        disk_space: boolean
        memory_usage: boolean
      }
    }
  }> {
    const response = await periodCloseApi.get(`/periods/${periodId}/close/can-complete`)
    return response.data.data
  }

  /**
   * Handle API errors consistently
   */
  static handleApiError(error: any, defaultMessage: string = 'An error occurred'): string {
    if (error.response?.data?.error) {
      return error.response.data.error
    } else if (error.response?.data?.message) {
      return error.response.data.message
    } else if (error.message) {
      return error.message
    } else {
      return defaultMessage
    }
  }

  /**
   * Check if a period can be closed based on API response
   */
  static canPeriodBeClosed(period: any): boolean {
    return period.can_close === true && period.status !== 'closed'
  }

  /**
   * Format API error for user display
   */
  static formatApiError(error: any): {
    message: string
    type: 'error' | 'warning' | 'info'
    details?: any
  } {
    const message = this.handleApiError(error)
    
    // Determine error type based on status code and message
    if (error.response?.status === 403) {
      return { message, type: 'warning', details: 'permission_denied' }
    } else if (error.response?.status === 404) {
      return { message, type: 'error', details: 'not_found' }
    } else if (error.response?.status === 409) {
      return { message, type: 'warning', details: 'conflict' }
    } else if (error.response?.status >= 500) {
      return { message, type: 'error', details: 'server_error' }
    } else {
      return { message, type: 'error', details: 'unknown' }
    }
  }
}

export default PeriodCloseApiService