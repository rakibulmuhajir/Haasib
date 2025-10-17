<script setup lang="ts">
import { computed } from 'vue'
import PrimeCard from 'primevue/card'
import PrimeProgressBar from 'primevue/progressbar'
import PrimeTag from 'primevue/tag'
import PrimeBadge from 'primevue/badge'
import PrimeMessage from 'primevue/message'

interface ValidationIssue {
  type: 'error' | 'warning' | 'info'
  code: string
  message: string
  category: string
  affected_accounts?: string[]
  suggested_action?: string
  priority: 'high' | 'medium' | 'low'
}

interface ValidationResults {
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

interface ValidationSummaryProps {
  results: ValidationResults
  compact?: boolean
  showDetails?: boolean
}

const props = withDefaults(defineProps<ValidationSummaryProps>(), {
  compact: false,
  showDetails: true
})

const issuesByCategory = computed(() => {
  const grouped: Record<string, ValidationIssue[]> = {}
  props.results.issues.forEach(issue => {
    if (!grouped[issue.category]) {
      grouped[issue.category] = []
    }
    grouped[issue.category].push(issue)
  })
  return grouped
})

const criticalIssues = computed(() => 
  props.results.issues.filter(issue => issue.type === 'error' || issue.priority === 'high')
)

const warningIssues = computed(() => 
  props.results.issues.filter(issue => issue.type === 'warning' && issue.priority !== 'high')
)

const infoIssues = computed(() => 
  props.results.issues.filter(issue => issue.type === 'info')
)

function getValidationStatusColor(status: string): string {
  switch (status) {
    case 'passed': return 'success'
    case 'failed': return 'danger'
    case 'warning': return 'warning'
    default: return 'secondary'
  }
}

function getIssueIcon(type: string): string {
  switch (type) {
    case 'error': return 'pi pi-exclamation-circle'
    case 'warning': return 'pi pi-exclamation-triangle'
    case 'info': return 'pi pi-info-circle'
    default: return 'pi pi-question-circle'
  }
}

function getIssueColor(type: string): string {
  switch (type) {
    case 'error': return 'danger'
    case 'warning': return 'warning'
    case 'info': return 'info'
    default: return 'secondary'
  }
}

function getPriorityColor(priority: string): string {
  switch (priority) {
    case 'high': return 'danger'
    case 'medium': return 'warning'
    case 'low': return 'info'
    default: return 'secondary'
  }
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount)
}

function formatDateTime(dateString: string): string {
  return new Date(dateString).toLocaleString()
}

function getCategoryTitle(category: string): string {
  switch (category) {
    case 'trial_balance': return 'Trial Balance'
    case 'unposted_documents': return 'Unposted Documents'
    case 'account_reconciliation': return 'Account Reconciliation'
    case 'period_integrity': return 'Period Integrity'
    case 'compliance': return 'Compliance Check'
    default: return category.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
  }
}
</script>

<template>
  <PrimeCard>
    <template #header>
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold">Validation Results</h3>
        <div class="flex items-center space-x-2">
          <PrimeBadge 
            :value="`Score: ${results.score}/100`" 
            :severity="results.score >= 90 ? 'success' : results.score >= 70 ? 'warning' : 'danger'"
          />
          <PrimeTag 
            :value="results.status.toUpperCase()" 
            :severity="getValidationStatusColor(results.status)"
          />
        </div>
      </div>
    </template>

    <template #content>
      <!-- Status Overview -->
      <PrimeMessage 
        :severity="results.status === 'passed' ? 'success' : results.status === 'failed' ? 'error' : 'warn'"
        :closable="false"
        class="mb-4"
      >
        <i class="pi pi-shield mr-2"></i>
        Period validation {{ results.status }} with a score of {{ results.score }}/100
        <span v-if="criticalIssues.length > 0">
          • {{ criticalIssues.length }} critical issue{{ criticalIssues.length !== 1 ? 's' : '' }} require attention
        </span>
      </PrimeMessage>

      <!-- Quick Stats -->
      <div v-if="!compact" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
          <div class="text-2xl font-bold text-red-600">{{ criticalIssues.length }}</div>
          <div class="text-sm text-red-700 dark:text-red-300">Critical Issues</div>
        </div>
        
        <div class="text-center p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
          <div class="text-2xl font-bold text-amber-600">{{ warningIssues.length }}</div>
          <div class="text-sm text-amber-700 dark:text-amber-300">Warnings</div>
        </div>
        
        <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
          <div class="text-2xl font-bold text-blue-600">{{ infoIssues.length }}</div>
          <div class="text-sm text-blue-700 dark:text-blue-300">Info Items</div>
        </div>
      </div>

      <!-- Trial Balance Status -->
      <div v-if="!compact" class="mb-6 p-4 border rounded-lg">
        <h4 class="font-semibold mb-3 flex items-center">
          <i class="pi pi-calculator mr-2 text-blue-500"></i>
          Trial Balance
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Total Debits</div>
            <div class="font-mono font-semibold">{{ formatCurrency(results.trial_balance.total_debits) }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Total Credits</div>
            <div class="font-mono font-semibold">{{ formatCurrency(results.trial_balance.total_credits) }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Difference</div>
            <div class="font-mono font-semibold" :class="{
              'text-red-600': !results.trial_balance.is_balanced,
              'text-green-600': results.trial_balance.is_balanced
            }">
              {{ formatCurrency(Math.abs(results.trial_balance.difference)) }}
              <i 
                class="ml-1 text-sm"
                :class="results.trial_balance.is_balanced ? 'pi pi-check' : 'pi pi-times'"
              ></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Issues by Category -->
      <div v-if="showDetails && issuesByCategory.length > 0" class="space-y-6">
        <div v-for="(issues, category) in issuesByCategory" :key="category" class="space-y-3">
          <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 flex items-center">
            <i class="pi pi-folder mr-2 text-gray-500"></i>
            {{ getCategoryTitle(category) }}
            <PrimeBadge 
              :value="issues.length" 
              severity="secondary" 
              size="small"
              class="ml-2"
            />
          </h4>

          <div class="space-y-2">
            <div 
              v-for="issue in issues" 
              :key="issue.code"
              class="p-3 border rounded-lg"
              :class="{
                'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800': issue.type === 'error',
                'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800': issue.type === 'warning',
                'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800': issue.type === 'info'
              }"
            >
              <div class="flex items-start space-x-3">
                <i 
                  :class="[
                    getIssueIcon(issue.type),
                    'text-lg mt-0.5',
                    {
                      'text-red-600': issue.type === 'error',
                      'text-amber-600': issue.type === 'warning',
                      'text-blue-600': issue.type === 'info'
                    }
                  ]"
                ></i>
                
                <div class="flex-1">
                  <div class="flex items-center space-x-2 mb-1">
                    <span class="font-medium text-gray-900 dark:text-gray-100">
                      {{ issue.message }}
                    </span>
                    <PrimeTag 
                      :value="issue.code" 
                      severity="secondary" 
                      size="small"
                    />
                    <PrimeTag 
                      :value="issue.priority" 
                      :severity="getPriorityColor(issue.priority)" 
                      size="small"
                    />
                  </div>
                  
                  <p v-if="issue.suggested_action" class="text-sm text-gray-700 dark:text-gray-300 mb-2">
                    <strong>Suggested Action:</strong> {{ issue.suggested_action }}
                  </p>
                  
                  <div v-if="issue.affected_accounts && issue.affected_accounts.length > 0" class="text-xs text-gray-600 dark:text-gray-400">
                    <strong>Affected Accounts:</strong> {{ issue.affected_accounts.join(', ') }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recommendations -->
      <div v-if="showDetails && results.recommendations.length > 0" class="mt-6">
        <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
          <i class="pi pi-lightbulb mr-2 text-amber-500"></i>
          Recommendations
        </h4>
        <ul class="space-y-2">
          <li 
            v-for="recommendation in results.recommendations" 
            :key="recommendation"
            class="flex items-start space-x-2 text-sm text-gray-700 dark:text-gray-300"
          >
            <i class="pi pi-check-circle text-green-500 mt-0.5"></i>
            <span>{{ recommendation }}</span>
          </li>
        </ul>
      </div>

      <!-- Validation Metadata -->
      <div v-if="!compact" class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-500">
          <div>
            Validated by {{ results.validation_metadata.validated_by }}
          </div>
          <div>
            {{ formatDateTime(results.validation_metadata.validation_timestamp) }}
          </div>
          <div>
            Version {{ results.validation_metadata.validation_version }}
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="results.issues.length === 0 && results.status === 'passed'" class="text-center py-8">
        <i class="pi pi-check-circle text-4xl text-green-500 mb-3"></i>
        <p class="text-green-700 dark:text-green-300 font-medium">
          All validations passed successfully!
        </p>
        <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">
          No issues found in this period close.
        </p>
      </div>
    </template>
  </PrimeCard>
</template>

<style scoped>
:deep(.p-progressbar .p-progressbar-value) {
  transition: width 0.3s ease-in-out;
}

:deep(.p-tag) {
  font-size: 0.75rem;
}
</style>