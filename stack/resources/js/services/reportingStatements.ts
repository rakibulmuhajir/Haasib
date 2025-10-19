import axios from 'axios'

export interface ReportParams {
  report_type: 'income_statement' | 'balance_sheet' | 'cash_flow' | 'trial_balance'
  date_range: {
    start: string
    end: string
  }
  comparison?: 'prior_period' | 'prior_year' | 'none'
  currency?: string
  include_zero_balances?: boolean
  export_format?: 'json' | 'pdf' | 'csv'
  async?: boolean
  priority?: 'low' | 'normal' | 'high'
}

export interface ReportPreviewParams {
  report_type: 'income_statement' | 'balance_sheet' | 'cash_flow' | 'trial_balance'
  date_range: {
    start: string
    end: string
  }
  comparison?: 'prior_period' | 'prior_year' | 'none'
  currency?: string
}

export interface GetReportsParams {
  page?: number
  per_page?: number
  report_type?: string
  status?: 'queued' | 'running' | 'generated' | 'failed'
  date_from?: string
  date_to?: string
}

export interface TransactionDrilldownParams {
  account_id: string
  account_code?: string
  date_from: string
  date_to: string
  counterparty_id?: string
  include_running_balances?: boolean
  limit?: number
  offset?: number
}

export interface Report {
  report_id: string
  report_type: string
  status: 'queued' | 'running' | 'generated' | 'failed'
  created_at: string
  updated_at: string
  file_size?: number
  file_path?: string
  file_name?: string
  parameters: ReportParams
  error_message?: string
}

export interface ReportGenerationResponse {
  report_id: string
  status: string
  message: string
  job_id?: string
  estimated_completion_seconds?: number
}

export interface ReportPreviewResponse {
  preview: any
  metadata: {
    report_type: string
    date_range: {
      start: string
      end: string
    }
    currency: string
    generated_at: string
  }
}

export interface TransactionDrilldownResponse {
  transactions: any[]
  summary: {
    total_transactions: number
    total_amount: number
    total_debits: number
    total_credits: number
    net_amount: number
  }
  running_balances?: any[]
}

class ReportingStatementsService {
  private baseUrl = '/api/reporting'

  /**
   * Generate a new financial report
   */
  async generateReport(params: ReportParams): Promise<ReportGenerationResponse> {
    try {
      const response = await axios.post(`${this.baseUrl}/reports`, params)
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to generate report')
    }
  }

  /**
   * Generate a preview of a financial report
   */
  async previewReport(params: ReportPreviewParams): Promise<ReportPreviewResponse> {
    try {
      const response = await axios.post(`${this.baseUrl}/reports/preview`, params)
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to generate report preview')
    }
  }

  /**
   * Get list of reports
   */
  async getReports(params: GetReportsParams = {}): Promise<{ data: { data: Report[]; meta: any } }> {
    try {
      const response = await axios.get(`${this.baseUrl}/reports`, { params })
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to fetch reports')
    }
  }

  /**
   * Get specific report details
   */
  async getReport(reportId: string): Promise<Report> {
    try {
      const response = await axios.get(`${this.baseUrl}/reports/${reportId}`)
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to fetch report')
    }
  }

  /**
   * Get report generation status
   */
  async getReportStatus(reportId: string): Promise<any> {
    try {
      const response = await axios.get(`${this.baseUrl}/reports/${reportId}/status`)
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to fetch report status')
    }
  }

  /**
   * Delete a report
   */
  async deleteReport(reportId: string): Promise<void> {
    try {
      await axios.delete(`${this.baseUrl}/reports/${reportId}`)
    } catch (error) {
      this.handleError(error, 'Failed to delete report')
    }
  }

  /**
   * Download a report file
   */
  async downloadReport(reportId: string): Promise<void> {
    try {
      const response = await axios.get(`${this.baseUrl}/reports/${reportId}/download`, {
        responseType: 'blob'
      })
      
      // Get filename from response headers or use default
      const contentDisposition = response.headers['content-disposition']
      let filename = `report-${reportId}`
      
      if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename="(.+)"/)
        if (filenameMatch) {
          filename = filenameMatch[1]
        }
      }
      
      // Create download link
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', filename)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    } catch (error) {
      this.handleError(error, 'Failed to download report')
    }
  }

  /**
   * Get transaction drilldown data
   */
  async getTransactionDrilldown(params: TransactionDrilldownParams): Promise<TransactionDrilldownResponse> {
    try {
      const response = await axios.get(`${this.baseUrl}/transactions/drilldown`, { params })
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to fetch transaction drilldown')
    }
  }

  /**
   * Search transactions
   */
  async searchTransactions(params: {
    search_term: string
    account_id?: string
    date_from?: string
    date_to?: string
    limit?: number
  }): Promise<any[]> {
    try {
      const response = await axios.get(`${this.baseUrl}/transactions/search`, { params })
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to search transactions')
    }
  }

  /**
   * Get available report types
   */
  async getReportTypes(): Promise<any[]> {
    try {
      const response = await axios.get(`${this.baseUrl}/reports/types`)
      return response.data.data
    } catch (error) {
      this.handleError(error, 'Failed to fetch report types')
    }
  }

  /**
   * Get report statistics
   */
  async getReportStatistics(): Promise<any> {
    try {
      const response = await axios.get(`${this.baseUrl}/reports/statistics`)
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to fetch report statistics')
    }
  }

  /**
   * Watch report generation progress
   */
  async watchReportProgress(
    reportId: string,
    onUpdate: (status: any) => void,
    interval = 2000,
    maxAttempts = 30
  ): Promise<void> {
    let attempts = 0

    const poll = async () => {
      attempts++

      try {
        const status = await this.getReportStatus(reportId)
        onUpdate(status)

        // Continue polling if report is still being generated
        if (['queued', 'running'].includes(status.status) && attempts < maxAttempts) {
          setTimeout(poll, interval)
        }
      } catch (error) {
        console.error('Error polling report status:', error)
      }
    }

    await poll()
  }

  /**
   * Get default report parameters
   */
  getDefaultParams(reportType: string): Partial<ReportParams> {
    const now = new Date()
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
    const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0)

    return {
      report_type: reportType as any,
      date_range: {
        start: startOfMonth.toISOString().split('T')[0],
        end: endOfMonth.toISOString().split('T')[0],
      },
      comparison: 'prior_period',
      currency: 'USD',
      include_zero_balances: false,
      export_format: 'json',
      async: true,
      priority: 'normal',
    }
  }

  /**
   * Validate report parameters
   */
  validateReportParams(params: Partial<ReportParams>): { isValid: boolean; errors: string[] } {
    const errors: string[] = []

    if (!params.report_type) {
      errors.push('Report type is required')
    }

    const validTypes = ['income_statement', 'balance_sheet', 'cash_flow', 'trial_balance']
    if (params.report_type && !validTypes.includes(params.report_type)) {
      errors.push('Invalid report type')
    }

    if (params.date_range) {
      const { start, end } = params.date_range
      if (!start || !end) {
        errors.push('Both start and end dates are required for date range')
      } else if (new Date(start) > new Date(end)) {
        errors.push('Start date must be before or equal to end date')
      }
    }

    if (params.comparison && !['prior_period', 'prior_year', 'none'].includes(params.comparison)) {
      errors.push('Comparison must be one of: prior_period, prior_year, none')
    }

    if (params.currency && params.currency.length !== 3) {
      errors.push('Currency must be a 3-character ISO code')
    }

    if (params.export_format && !['json', 'pdf', 'csv'].includes(params.export_format)) {
      errors.push('Export format must be one of: json, pdf, csv')
    }

    if (params.priority && !['low', 'normal', 'high'].includes(params.priority)) {
      errors.push('Priority must be one of: low, normal, high')
    }

    return {
      isValid: errors.length === 0,
      errors,
    }
  }

  /**
   * Format report parameters for API calls
   */
  formatReportParams(params: Partial<ReportParams>): any {
    const formatted: any = {}

    if (params.report_type) formatted.report_type = params.report_type
    if (params.date_range) {
      formatted['date_range[start]'] = params.date_range.start
      formatted['date_range[end]'] = params.date_range.end
    }
    if (params.comparison) formatted.comparison = params.comparison
    if (params.currency) formatted.currency = params.currency
    if (params.include_zero_balances !== undefined) formatted.include_zero_balances = params.include_zero_balances
    if (params.export_format) formatted.export_format = params.export_format
    if (params.async !== undefined) formatted.async = params.async
    if (params.priority) formatted.priority = params.priority

    return formatted
  }

  /**
   * Error handling helper
   */
  private handleError(error: any, defaultMessage: string): never {
    if (error.response) {
      const message = error.response.data?.message || error.response.statusText || defaultMessage
      const status = error.response.status
      
      throw new Error(`[${status}] ${message}`)
    } else if (error.request) {
      throw new Error('Network error - no response received from server')
    } else {
      throw new Error(`${defaultMessage}: ${error.message}`)
    }
  }
}

// Create and export singleton instance
export const reportingStatementsService = new ReportingStatementsService()

// Export composables for Vue 3
export function useReportingStatements() {
  return {
    generateReport: reportingStatementsService.generateReport.bind(reportingStatementsService),
    previewReport: reportingStatementsService.previewReport.bind(reportingStatementsService),
    getReports: reportingStatementsService.getReports.bind(reportingStatementsService),
    getReport: reportingStatementsService.getReport.bind(reportingStatementsService),
    getReportStatus: reportingStatementsService.getReportStatus.bind(reportingStatementsService),
    deleteReport: reportingStatementsService.deleteReport.bind(reportingStatementsService),
    downloadReport: reportingStatementsService.downloadReport.bind(reportingStatementsService),
    getTransactionDrilldown: reportingStatementsService.getTransactionDrilldown.bind(reportingStatementsService),
    searchTransactions: reportingStatementsService.searchTransactions.bind(reportingStatementsService),
    getReportTypes: reportingStatementsService.getReportTypes.bind(reportingStatementsService),
    getReportStatistics: reportingStatementsService.getReportStatistics.bind(reportingStatementsService),
    watchReportProgress: reportingStatementsService.watchReportProgress.bind(reportingStatementsService),
    getDefaultParams: reportingStatementsService.getDefaultParams.bind(reportingStatementsService),
    validateReportParams: reportingStatementsService.validateReportParams.bind(reportingStatementsService),
    formatReportParams: reportingStatementsService.formatReportParams.bind(reportingStatementsService),
  }
}

export default reportingStatementsService