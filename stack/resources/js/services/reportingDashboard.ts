import axios from 'axios'

export interface DashboardParams {
  layout_id: string
  date_range?: {
    start: string
    end: string
  }
  comparison?: 'prior_period' | 'prior_year' | 'custom'
  filters?: {
    segment?: string
  }
  currency?: string
  force_refresh?: boolean
}

export interface RefreshDashboardParams {
  layout_id: string
  invalidate_cache?: boolean
  priority?: 'low' | 'normal' | 'high'
  async?: boolean
  date_range?: {
    start: string
    end: string
  }
  comparison?: 'prior_period' | 'prior_year' | 'custom'
  filters?: {
    segment?: string
  }
  currency?: string
}

export interface InvalidateCacheParams {
  layout_id?: string
}

export interface DashboardLayout {
  layout_id: string
  name: string
  visibility: 'private' | 'company' | 'role'
  is_default: boolean
  created_at: string
  updated_at: string
}

export interface DashboardResponse {
  layout: {
    layout_id: string
    name: string
    visibility: string
  }
  refreshed_at: string
  cards: DashboardCard[]
  totals: SummaryTotal[]
  parameters: any
}

export interface DashboardCard {
  card_id: string
  type: 'kpi' | 'chart' | 'table' | 'stat'
  title: string
  data: any
  comparison?: {
    previous_value: number
    variance_percent: number
    trend: 'up' | 'down' | 'flat'
  }
  drilldown_url?: string
}

export interface SummaryTotal {
  label: string
  value: number
  currency: string
  trend_percent?: number
  direction: 'up' | 'down' | 'flat'
}

export interface RefreshResponse {
  job_id: string
  status: string
  estimated_completion_seconds: number
  message: string
}

export interface DashboardStatus {
  company_id: string
  layout_id: string
  refresh_in_progress: boolean
  cache_exists: boolean
  last_refreshed_at?: string
  cache_ttl: number
  status: string
}

export interface DashboardStats {
  company_id: string
  total_layouts: number
  is_refreshing: boolean
  cache_stats: {
    dashboard_cache_keys: number
    kpi_cache_keys: number
    total_memory_usage: number
  }
  last_check_at: string
}

class ReportingDashboardService {
  private baseUrl = '/api/reporting/dashboard'

  /**
   * Fetch dashboard data for a specific layout
   */
  async fetchDashboard(params: DashboardParams): Promise<{ data: DashboardResponse; headers: Record<string, string> }> {
    try {
      const response = await axios.get(this.baseUrl, { params })
      
      return {
        data: response.data,
        headers: {
          'X-Cache-TTL': response.headers['x-cache-ttl'] || '5',
          'X-Content-Type-Options': response.headers['x-content-type-options'] || 'nosniff',
        },
      }
    } catch (error) {
      this.handleError(error, 'Failed to fetch dashboard data')
    }
  }

  /**
   * Refresh dashboard cache
   */
  async refreshDashboard(params: RefreshDashboardParams): Promise<RefreshResponse> {
    try {
      const response = await axios.post(`${this.baseUrl}/refresh`, params)
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to refresh dashboard')
    }
  }

  /**
   * Refresh all dashboards for the company
   */
  async refreshAllDashboards(params: { invalidate_cache?: boolean } = {}): Promise<any> {
    try {
      const response = await axios.post(`${this.baseUrl}/refresh-all`, params)
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to refresh all dashboards')
    }
  }

  /**
   * Invalidate dashboard cache
   */
  async invalidateCache(params: InvalidateCacheParams = {}): Promise<any> {
    try {
      const response = await axios.post(`${this.baseUrl}/invalidate-cache`, params)
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to invalidate cache')
    }
  }

  /**
   * Get dashboard refresh status
   */
  async getStatus(params: { layout_id: string }): Promise<DashboardStatus> {
    try {
      const response = await axios.get(`${this.baseUrl}/status`, { params })
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to get dashboard status')
    }
  }

  /**
   * Get dashboard statistics
   */
  async getStats(): Promise<DashboardStats> {
    try {
      const response = await axios.get(`${this.baseUrl}/stats`)
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to get dashboard statistics')
    }
  }

  /**
   * Get available dashboard layouts
   */
  async getDashboardLayouts(): Promise<{ data: DashboardLayout[]; total: number }> {
    try {
      const response = await axios.get(`${this.baseUrl}/layouts`)
      return response.data
    } catch (error) {
      this.handleError(error, 'Failed to get dashboard layouts')
    }
  }

  /**
   * Watch dashboard refresh job status
   */
  async watchRefreshJob(
    params: { layout_id: string },
    onUpdate: (status: DashboardStatus) => void,
    interval = 2000,
    maxAttempts = 30
  ): Promise<void> {
    let attempts = 0

    const poll = async () => {
      attempts++

      try {
        const status = await this.getStatus(params)
        onUpdate(status)

        // Continue polling if refresh is in progress
        if (status.refresh_in_progress && attempts < maxAttempts) {
          setTimeout(poll, interval)
        }
      } catch (error) {
        console.error('Error polling refresh status:', error)
      }
    }

    await poll()
  }

  /**
   * Get optimized dashboard data with caching
   */
  async getCachedDashboard(
    params: DashboardParams,
    cacheKey: string,
    cacheDuration = 5000 // 5 seconds
  ): Promise<DashboardResponse> {
    // Check browser cache first
    const cached = localStorage.getItem(cacheKey)
    if (cached) {
      const { data, timestamp } = JSON.parse(cached)
      if (Date.now() - timestamp < cacheDuration) {
        return data
      }
    }

    // Fetch fresh data
    const { data, headers } = await this.fetchDashboard(params)
    
    // Store in browser cache
    localStorage.setItem(cacheKey, JSON.stringify({
      data,
      timestamp: Date.now(),
      ttl: parseInt(headers['X-Cache-TTL']) * 1000, // Convert to milliseconds
    }))

    return data
  }

  /**
   * Clear browser cache for dashboard
   */
  clearBrowserCache(): void {
    const keys = Object.keys(localStorage).filter(key => key.startsWith('dashboard-'))
    keys.forEach(key => localStorage.removeItem(key))
  }

  /**
   * Generate cache key for dashboard parameters
   */
  generateCacheKey(params: DashboardParams): string {
    const keyData = {
      layout_id: params.layout_id,
      date_range: params.date_range,
      comparison: params.comparison,
      currency: params.currency,
    }
    return `dashboard-${btoa(JSON.stringify(keyData))}`
  }

  /**
   * Format dashboard parameters for API calls
   */
  formatParams(params: Partial<DashboardParams>): any {
    const formatted: any = {}

    if (params.layout_id) formatted.layout_id = params.layout_id
    if (params.date_range) {
      formatted['date_range[start]'] = params.date_range.start
      formatted['date_range[end]'] = params.date_range.end
    }
    if (params.comparison) formatted.comparison = params.comparison
    if (params.filters) {
      Object.keys(params.filters).forEach(key => {
        formatted[`filters[${key}]`] = params.filters[key]
      })
    }
    if (params.currency) formatted.currency = params.currency
    if (params.force_refresh) formatted.force_refresh = params.force_refresh

    return formatted
  }

  /**
   * Validate dashboard parameters
   */
  validateParams(params: Partial<DashboardParams>): { isValid: boolean; errors: string[] } {
    const errors: string[] = []

    if (!params.layout_id) {
      errors.push('Layout ID is required')
    }

    if (params.date_range) {
      const { start, end } = params.date_range
      if (!start || !end) {
        errors.push('Both start and end dates are required for date range')
      } else if (new Date(start) > new Date(end)) {
        errors.push('Start date must be before or equal to end date')
      }
    }

    if (params.comparison && !['prior_period', 'prior_year', 'custom'].includes(params.comparison)) {
      errors.push('Comparison must be one of: prior_period, prior_year, custom')
    }

    if (params.currency && params.currency.length !== 3) {
      errors.push('Currency must be a 3-character ISO code')
    }

    return {
      isValid: errors.length === 0,
      errors,
    }
  }

  /**
   * Get default dashboard parameters
   */
  getDefaultParams(): Partial<DashboardParams> {
    const now = new Date()
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
    const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0)

    return {
      date_range: {
        start: startOfMonth.toISOString().split('T')[0],
        end: endOfMonth.toISOString().split('T')[0],
      },
      comparison: 'prior_period',
      currency: 'USD',
    }
  }

  /**
   * Health check for dashboard service
   */
  async healthCheck(): Promise<{ status: string; latency: number }> {
    const start = Date.now()
    
    try {
      await axios.get('/api/health')
      return {
        status: 'healthy',
        latency: Date.now() - start,
      }
    } catch (error) {
      return {
        status: 'unhealthy',
        latency: Date.now() - start,
      }
    }
  }

  /**
   * Error handling helper
   */
  private handleError(error: any, defaultMessage: string): never {
    if (error.response) {
      // The request was made and the server responded with a status code
      // that falls out of the range of 2xx
      const message = error.response.data?.message || error.response.statusText || defaultMessage
      const status = error.response.status
      
      throw new Error(`[${status}] ${message}`)
    } else if (error.request) {
      // The request was made but no response was received
      throw new Error('Network error - no response received from server')
    } else {
      // Something happened in setting up the request that triggered an Error
      throw new Error(`${defaultMessage}: ${error.message}`)
    }
  }
}

// Create and export singleton instance
export const reportingDashboardService = new ReportingDashboardService()

// Export composables for Vue 3
export function useReportingDashboard() {
  return {
    fetchDashboard: reportingDashboardService.fetchDashboard.bind(reportingDashboardService),
    refreshDashboard: reportingDashboardService.refreshDashboard.bind(reportingDashboardService),
    refreshAllDashboards: reportingDashboardService.refreshAllDashboards.bind(reportingDashboardService),
    invalidateCache: reportingDashboardService.invalidateCache.bind(reportingDashboardService),
    getStatus: reportingDashboardService.getStatus.bind(reportingDashboardService),
    getStats: reportingDashboardService.getStats.bind(reportingDashboardService),
    getDashboardLayouts: reportingDashboardService.getDashboardLayouts.bind(reportingDashboardService),
    watchRefreshJob: reportingDashboardService.watchRefreshJob.bind(reportingDashboardService),
    getCachedDashboard: reportingDashboardService.getCachedDashboard.bind(reportingDashboardService),
    clearBrowserCache: reportingDashboardService.clearBrowserCache.bind(reportingDashboardService),
    generateCacheKey: reportingDashboardService.generateCacheKey.bind(reportingDashboardService),
    formatParams: reportingDashboardService.formatParams.bind(reportingDashboardService),
    validateParams: reportingDashboardService.validateParams.bind(reportingDashboardService),
    getDefaultParams: reportingDashboardService.getDefaultParams.bind(reportingDashboardService),
    healthCheck: reportingDashboardService.healthCheck.bind(reportingDashboardService),
  }
}

export default reportingDashboardService