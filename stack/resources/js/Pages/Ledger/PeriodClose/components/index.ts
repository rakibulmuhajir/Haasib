// Export all period close components for easy importing
export { default as TaskList } from './TaskList.vue'
export { default as ValidationSummary } from './ValidationSummary.vue'
export { default as ProgressCard } from './ProgressCard.vue'
export { default as ChecklistActions } from './ChecklistActions.vue'
export { default as DeadlinesAlert } from './DeadlinesAlert.vue'

// Export types for external use
export type Task = {
  id: string
  code: string
  title: string
  category: string
  sequence: number
  status: 'pending' | 'in_progress' | 'completed' | 'blocked' | 'waived'
  is_required: boolean
  notes?: string
  completed_by?: string
  completed_at?: string
  started_at?: string
  started_by?: string
}

export type ValidationIssue = {
  type: 'error' | 'warning' | 'info'
  code: string
  message: string
  category: string
  affected_accounts?: string[]
  suggested_action?: string
  priority: 'high' | 'medium' | 'low'
}

export type ValidationResults = {
  status: 'passed' | 'failed' | 'warning'
  score: number
  issues: ValidationIssue[]
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
  recommendations: string[]
  validation_metadata: {
    validation_timestamp: string
    validated_by: string
    validation_version: string
    checks_performed: string[]
  }
}

export type PeriodCloseData = {
  id: string
  status: string
  started_at?: string
  started_by?: string
  completed_at?: string
  tasks: Task[]
  tasks_count?: number
  completed_tasks_count?: number
  required_tasks_count?: number
  required_completed_count?: number
  completion_percentage?: number
  required_completion_percentage?: number
}

export type Period = {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
  fiscal_year: {
    id: string
    name: string
  }
  period_close?: PeriodCloseData
  can_close: boolean
  is_overdue: boolean
}

export type Deadline = {
  period_id: string
  period_name: string
  deadline: string
  days_until_deadline: number
  status: string
  priority: 'high' | 'medium' | 'low'
}

export type Permission = {
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