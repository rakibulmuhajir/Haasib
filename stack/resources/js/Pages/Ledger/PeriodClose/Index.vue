<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'
import { usePageActions } from '@/composables/usePageActions'
import PrimeButton from 'primevue/button'
import PrimeCard from 'primevue/card'
import PrimeDataTable from 'primevue/datatable'
import PrimeColumn from 'primevue/column'
import PrimeTag from 'primevue/tag'
import PrimeProgress from 'primevue/progressbar'
import PrimeMessage from 'primevue/message'
import PrimeDialog from 'primevue/dialog'
import PrimeTextarea from 'primevue/textarea'
import PrimeBadge from 'primevue/badge'
import { usePeriodCloseForms } from '@/composables/usePeriodCloseForms'
import { DeadlinesAlert } from './components'

// Types
interface Period {
  id: string
  name: string
  status: string
  start_date: string
  end_date: string
  fiscal_year: {
    id: string
    name: string
  }
  period_close?: {
    id: string
    status: string
    started_at?: string
    started_by?: string
    tasks_count: number
    completed_tasks_count: number
    required_tasks_count: number
    required_completed_count: number
  }
  can_close: boolean
  is_overdue: boolean
}

interface Statistics {
  total_periods: number
  closed_periods: number
  active_closes: number
  open_periods: number
  overdue_periods: number
  periods_with_tasks: number
  average_completion_time?: number
}

interface Deadline {
  period_id: string
  period_name: string
  deadline: string
  days_until_deadline: number
  status: string
  priority: 'high' | 'medium' | 'low'
}

interface Permission {
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

// Page props
const page = usePage()
const props = computed(() => page.props as any)

// Reactive state
const periods = computed(() => props.value.periods as Period[])
const statistics = computed(() => props.value.statistics as Statistics)
const deadlines = computed(() => props.value.deadlines as Deadline[])
const permissions = computed(() => props.value.permissions as Permission)
const currentCompany = computed(() => props.value.current_company as { id: string; name: string })

// Composition
const { actions } = usePageActions()

// Form state
const { useStartPeriodCloseForm } = usePeriodCloseForms()
const { form: startForm, startPeriodClose, reset: resetStartForm } = useStartPeriodCloseForm()

// Define page actions
const pageActions = [
  {
    key: 'new-period-close',
    label: 'Start Period Close',
    icon: 'pi pi-play',
    severity: 'primary',
    action: () => showStartDialog.value = true
  },
  {
    key: 'refresh',
    label: 'Refresh',
    icon: 'pi pi-refresh',
    severity: 'secondary',
    action: () => window.location.reload()
  }
]

// Define quick links for the period close page
const quickLinks = [
  {
    label: 'Start Period Close',
    url: '#',
    icon: 'pi pi-play',
    action: () => showStartDialog.value = true
  },
  {
    label: 'Bank Reconciliation',
    url: '/ledger/bank-reconciliation',
    icon: 'pi pi-bank'
  },
  {
    label: 'Journal Entries',
    url: '/accounting/journal-entries',
    icon: 'pi pi-book'
  },
  {
    label: 'Trial Balance',
    url: '/ledger/trial-balance',
    icon: 'pi pi-calculator'
  }
]

// Set page actions
actions.value = pageActions

// Dialog state
const showStartDialog = ref(false)
const selectedPeriod = ref<Period | null>(null)
const loading = ref(false)

// Computed properties
const activePeriods = computed(() => 
  periods.value.filter(p => p.status === 'closing' || (p.period_close && p.period_close.status === 'in_review'))
)

const completedPercentage = computed(() => {
  const total = statistics.value.total_periods
  const completed = statistics.value.closed_periods
  return total > 0 ? Math.round((completed / total) * 100) : 0
})

const completionRate = computed(() => {
  const withTasks = statistics.value.periods_with_tasks
  const total = statistics.value.total_periods
  return total > 0 ? Math.round((withTasks / total) * 100) : 0
})

// Methods
function getStatusColor(status: string): string {
  switch (status) {
    case 'closed': return 'success'
    case 'closing': return 'warning'
    case 'open': return 'info'
    default: return 'secondary'
  }
}

function getStatusIcon(status: string): string {
  switch (status) {
    case 'closed': return 'pi pi-check-circle'
    case 'closing': return 'pi pi-clock'
    case 'open': return 'pi pi-play'
    default: return 'pi pi-question-circle'
  }
}

function getPeriodCloseStatusColor(status: string): string {
  switch (status) {
    case 'closed': return 'success'
    case 'locked': return 'info'
    case 'awaiting_approval': return 'warning'
    case 'in_review': return 'primary'
    default: return 'secondary'
  }
}

function getTaskCompletionPercentage(period: Period): number {
  if (!period.period_close) return 0
  const total = period.period_close.tasks_count
  const completed = period.period_close.completed_tasks_count
  return total > 0 ? Math.round((completed / total) * 100) : 0
}

function getRequiredTaskCompletionPercentage(period: Period): number {
  if (!period.period_close) return 0
  const total = period.period_close.required_tasks_count
  const completed = period.period_close.required_completed_count
  return total > 0 ? Math.round((completed / total) * 100) : 0
}

function getPriorityColor(priority: string): string {
  switch (priority) {
    case 'high': return 'danger'
    case 'medium': return 'warning'
    case 'low': return 'info'
    default: return 'secondary'
  }
}

function getDaysUntilDeadlineText(days: number): string {
  if (days === 0) return 'Due today'
  if (days === 1) return 'Due tomorrow'
  if (days < 0) return `${Math.abs(days)} days overdue`
  return `Due in ${days} days`
}

async function openStartPeriodClose(period: Period) {
  if (!permissions.value.can_start) return
  
  selectedPeriod.value = period
  startForm.notes = ''
  resetStartForm()
  showStartDialog.value = true
}

async function handleStartPeriodClose() {
  if (!selectedPeriod.value) return
  
  loading.value = true
  try {
    const success = await startPeriodClose(selectedPeriod.value.id)
    
    if (success) {
      showStartDialog.value = false
      selectedPeriod.value = null
      // Refresh the page to show updated data
      router.reload()
    }
  } catch (error) {
    console.error('Failed to start period close:', error)
  } finally {
    loading.value = false
  }
}

function navigateToPeriodClose(period: Period) {
  router.get(`/ledger/periods/${period.id}`)
}

function navigateToPeriodStart(period: Period) {
  router.get(`/ledger/periods/${period.id}/start`)
}

function formatDuration(startDate: string, endDate: string): string {
  const start = new Date(startDate)
  const end = new Date(endDate)
  const days = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1
  return `${days} days`
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString()
}

function formatDateTime(dateString?: string): string {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString()
}

function getCompletionTime(time?: number): string {
  if (!time) return 'N/A'
  return `${time}h`
}
</script>

<template>
  <Head title="Period Close Management" />

  <LayoutShell>
    <!-- Universal Page Header -->
    <UniversalPageHeader
      title="Period Close"
      description="Manage monthly closing workflows"
      subDescription="Complete accounting period closing procedures"
      :show-search="true"
      search-placeholder="Search periods..."
    />

    <!-- Main Content Grid -->
    <div class="content-grid-5-6">
      <div class="main-content">

      <!-- Statistics Overview -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <PrimeCard>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ statistics.total_periods }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400">
                Total Periods
              </div>
            </div>
          </template>
        </PrimeCard>

        <PrimeCard>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-green-600">
                {{ statistics.closed_periods }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400">
                Closed Periods
              </div>
              <PrimeProgress 
                :value="completedPercentage" 
                class="w-full mt-2"
                :showValue="false"
              />
            </div>
          </template>
        </PrimeCard>

        <PrimeCard>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-amber-600">
                {{ statistics.active_closes }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400">
                Active Closes
              </div>
            </div>
          </template>
        </PrimeCard>

        <PrimeCard>
          <template #content>
            <div class="text-center">
              <div class="text-2xl font-bold text-blue-600">
                {{ getCompletionTime(statistics.average_completion_time) }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400">
                Avg. Completion
              </div>
            </div>
          </template>
        </PrimeCard>
      </div>

      <!-- Alert Messages -->
      <div v-if="statistics.overdue_periods > 0" class="mb-6">
        <PrimeMessage severity="error" :closable="false">
          <i class="pi pi-exclamation-triangle mr-2"></i>
          {{ statistics.overdue_periods }} period(s) are overdue for closing
        </PrimeMessage>
      </div>

      <!-- Deadlines Alert -->
      <DeadlinesAlert 
        v-if="deadlines.length > 0"
        :deadlines="deadlines"
        :max-items="3"
        class="mb-6"
      />

      <!-- Periods Table -->
      <PrimeCard>
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">Accounting Periods</h3>
            <div class="text-sm text-gray-600">
              {{ periods.length }} periods total
            </div>
          </div>
        </template>
        <template #content>
          <PrimeDataTable 
            :value="periods" 
            :paginator="periods.length > 10"
            stripedRows
            responsiveLayout="scroll"
          >
            <PrimeColumn field="name" header="Period">
              <template #body="{ data }">
                <div class="flex items-center space-x-2">
                  <i :class="getStatusIcon(data.status)" class="text-gray-500"></i>
                  <div>
                    <div class="font-medium">{{ data.name }}</div>
                    <div class="text-sm text-gray-600">
                      {{ formatDate(data.start_date) }} - {{ formatDate(data.end_date) }}
                      <span class="text-xs text-gray-500">({{ formatDuration(data.start_date, data.end_date) }})</span>
                    </div>
                  </div>
                </div>
              </template>
            </PrimeColumn>

            <PrimeColumn field="status" header="Status" style="width: 120px">
              <template #body="{ data }">
                <PrimeTag 
                  :value="data.status.replace('_', ' ')" 
                  :severity="getStatusColor(data.status)"
                />
              </template>
            </PrimeColumn>

            <PrimeColumn field="fiscal_year" header="Fiscal Year">
              <template #body="{ data }">
                <span class="text-sm">{{ data.fiscal_year.name }}</span>
              </template>
            </PrimeColumn>

            <PrimeColumn field="period_close" header="Close Status" style="width: 140px">
              <template #body="{ data }">
                <div v-if="data.period_close">
                  <div class="space-y-1">
                    <PrimeTag 
                      :value="data.period_close.status.replace('_', ' ')" 
                      :severity="getPeriodCloseStatusColor(data.period_close.status)"
                      size="small"
                    />
                    <div class="text-xs text-gray-600">
                      {{ data.period_close.completed_tasks_count }}/{{ data.period_close.tasks_count }} tasks
                    </div>
                  </div>
                </div>
                <span v-else class="text-gray-500 text-sm">Not started</span>
              </template>
            </PrimeColumn>

            <PrimeColumn field="completion" header="Progress">
              <template #body="{ data }">
                <div v-if="data.period_close" class="w-full">
                  <PrimeProgress 
                    :value="getTaskCompletionPercentage(data)" 
                    class="mb-1"
                    :showValue="false"
                  />
                  <div class="flex justify-between text-xs text-gray-600">
                    <span>{{ getTaskCompletionPercentage(data) }}%</span>
                    <span>{{ getRequiredTaskCompletionPercentage(data) }}% required</span>
                  </div>
                </div>
                <span v-else class="text-gray-500 text-sm">-</span>
              </template>
            </PrimeColumn>

            <PrimeColumn field="period_close.started_at" header="Started">
              <template #body="{ data }">
                <div v-if="data.period_close?.started_at" class="text-sm">
                  <div>{{ formatDate(data.period_close.started_at) }}</div>
                  <div class="text-xs text-gray-600">by {{ data.period_close.started_by }}</div>
                </div>
                <span v-else class="text-gray-500 text-sm">-</span>
              </template>
            </PrimeColumn>

            <PrimeColumn header="Actions" style="width: 200px">
              <template #body="{ data }">
                <div class="flex space-x-1">
                  <PrimeButton
                    v-if="data.can_close && permissions.can_start"
                    icon="pi pi-play"
                    size="small"
                    severity="primary"
                    @click="openStartPeriodClose(data)"
                    v-tooltip="'Start period close'"
                  />
                  
                  <PrimeButton
                    v-if="data.period_close && permissions.can_view"
                    icon="pi pi-eye"
                    size="small"
                    severity="secondary"
                    @click="navigateToPeriodClose(data)"
                    v-tooltip="'View details'"
                  />
                  
                  <PrimeButton
                    v-if="data.can_close && !data.period_close && permissions.can_start"
                    icon="pi pi-plus"
                    size="small"
                    severity="info"
                    @click="navigateToPeriodStart(data)"
                    v-tooltip="'Start workflow'"
                  />
                </div>
              </template>
            </PrimeColumn>
          </PrimeDataTable>
        </template>
      </PrimeCard>

      <!-- Start Period Close Dialog -->
      <PrimeDialog 
        v-model:visible="showStartDialog" 
        modal 
        :header="`Start Period Close - ${selectedPeriod?.name}`" 
        style="width: 600px"
      >
        <div class="space-y-4">
          <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
            <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">
              <i class="pi pi-info-circle mr-2"></i>
              Period Information
            </h4>
            <div v-if="selectedPeriod" class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
              <div><strong>Period:</strong> {{ selectedPeriod.name }}</div>
              <div><strong>Duration:</strong> {{ formatDate(selectedPeriod.start_date) }} - {{ formatDate(selectedPeriod.end_date) }}</div>
              <div><strong>Fiscal Year:</strong> {{ selectedPeriod.fiscal_year.name }}</div>
            </div>
          </div>
          
          <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Starting Notes (Optional)
            </label>
            <PrimeTextarea 
              id="notes" 
              v-model="startForm.notes" 
              rows="4" 
              placeholder="Add any notes about this period close..." 
              class="w-full"
              :class="{ 'p-invalid': startForm.errors.notes }"
            />
            <small v-if="startForm.errors.notes" class="text-red-600">
              {{ startForm.errors.notes }}
            </small>
            <small v-if="startForm.errors.general" class="text-red-600 block mt-2">
              {{ startForm.errors.general }}
            </small>
          </div>
          
          <div class="bg-amber-50 dark:bg-amber-900/20 p-4 rounded-lg">
            <h4 class="font-semibold text-amber-900 dark:text-amber-100 mb-2">
              <i class="pi pi-exclamation-triangle mr-2"></i>
              Before You Start
            </h4>
            <ul class="text-sm text-amber-800 dark:text-amber-200 space-y-1 list-disc list-inside">
              <li>Ensure all journal entries for the period are posted</li>
              <li>Verify trial balance is accurate</li>
              <li>Complete any required reconciliations</li>
              <li>Review and approve pending transactions</li>
            </ul>
          </div>
        </div>
        
        <template #footer>
          <PrimeButton 
            label="Cancel" 
            @click="showStartDialog = false" 
            severity="secondary" 
          />
          <PrimeButton 
            label="Start Period Close" 
            @click="handleStartPeriodClose" 
            :loading="loading || startForm.processing"
            severity="primary"
          />
        </template>
      </PrimeDialog>
      </div>

      <!-- Right Column - Quick Links -->
      <div class="sidebar-content">
        <QuickLinks 
          :links="quickLinks" 
          title="Period Actions"
        />
      </div>
    </div>
  </LayoutShell>
</template>

