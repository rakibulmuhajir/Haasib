<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { format } from 'date-fns'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Badge from 'primevue/badge'
import Divider from 'primevue/divider'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'

const page = usePage()

// Get entry from props
const entry = computed(() => page.props.entry as any)

// Permissions
const canView = computed(() => 
  page.props.auth.permissions?.['ledger.view'] ?? false
)
const canPost = computed(() => 
  page.props.auth.permissions?.['ledger.post'] ?? false
)
const canVoid = computed(() => 
  page.props.auth.permissions?.['ledger.void'] ?? false
)

// Void dialog
const showVoidDialog = ref(false)
const voidReason = ref('')
const voiding = ref(false)

// Format currency
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount)
}

// Format date
const formatDate = (dateString: string) => {
  return format(new Date(dateString), 'MMMM dd, yyyy')
}

const formatDateShort = (dateString: string) => {
  return format(new Date(dateString), 'MMM dd, yyyy')
}

// Get status badge
const getStatusBadge = (status: string) => {
  const variants = {
    draft: 'info',
    posted: 'success',
    void: 'danger'
  }
  
  return {
    severity: variants[status] || 'secondary',
    value: status.charAt(0).toUpperCase() + status.slice(1)
  }
}

// Post entry
const postEntry = async () => {
  if (!canPost.value) return
  
  try {
    await router.post(route('ledger.post', entry.value.id))
  } catch (error) {
    console.error('Failed to post entry:', error)
  }
}

// Void entry
const voidEntry = async () => {
  if (!canVoid.value || !voidReason.value.trim()) return
  
  voiding.value = true
  
  try {
    await router.post(route('ledger.void', entry.value.id), {
      reason: voidReason.value
    })
    showVoidDialog.value = false
    voidReason.value = ''
  } catch (error) {
    console.error('Failed to void entry:', error)
  } finally {
    voiding.value = false
  }
}

// Get account name
const getAccountName = (account: any) => {
  return account ? `${account.code} - ${account.name}` : 'Unknown'
}
</script>

<template>
  <LayoutShell>
    <template #sidebar>
      <!-- Sidebar content will be handled by the layout -->
    </template>
    
    <template #topbar>
      <div class="flex items-center justify-between">
        <Breadcrumb 
          :items="[
            { label: 'Ledger', url: route('ledger.index') },
            { label: entry?.reference || `Entry ${entry?.id?.slice(0, 8)}` }
          ]" 
        />
        
        <div class="flex items-center gap-3">
          <Link :href="route('ledger.index')">
            <Button
              label="Back to Entries"
              icon="arrow-left"
              size="small"
              severity="secondary"
              outlined
            />
          </Link>
          
          <Button
            v-if="canPost && entry?.status === 'draft'"
            label="Post Entry"
            icon="check"
            size="small"
            @click="postEntry"
          />
          
          <Button
            v-if="canVoid && entry?.status === 'posted'"
            label="Void Entry"
            icon="ban"
            size="small"
            severity="danger"
            @click="showVoidDialog = true"
          />
        </div>
      </div>
    </template>

    <div class="max-w-6xl mx-auto space-y-6">
      <!-- Header -->
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Journal Entry
          </h1>
          <div class="flex items-center gap-3 mt-2">
            <Badge 
              :severity="getStatusBadge(entry?.status).severity"
              :value="getStatusBadge(entry?.status).value"
            />
            <span class="text-gray-500">
              {{ formatDate(entry?.date) }}
            </span>
            <span v-if="entry?.reference" class="text-gray-500">
              • {{ entry.reference }}
            </span>
          </div>
        </div>
      </div>

      <!-- Entry Summary -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Entry Details -->
        <Card>
          <template #title>Entry Details</template>
          <template #content>
            <div class="space-y-3">
              <div>
                <label class="text-sm font-medium text-gray-500">Status</label>
                <div class="mt-1">
                  <Badge 
                    :severity="getStatusBadge(entry?.status).severity"
                    :value="getStatusBadge(entry?.status).value"
                  />
                </div>
              </div>
              
              <div>
                <label class="text-sm font-medium text-gray-500">Date</label>
                <div class="mt-1 text-gray-900 dark:text-white">
                  {{ formatDate(entry?.date) }}
                </div>
              </div>
              
              <div v-if="entry?.reference">
                <label class="text-sm font-medium text-gray-500">Reference</label>
                <div class="mt-1 text-gray-900 dark:text-white font-mono">
                  {{ entry.reference }}
                </div>
              </div>
              
              <div>
                <label class="text-sm font-medium text-gray-500">Description</label>
                <div class="mt-1 text-gray-900 dark:text-white">
                  {{ entry?.description }}
                </div>
              </div>
              
              <div v-if="entry?.posted_at">
                <label class="text-sm font-medium text-gray-500">Posted</label>
                <div class="mt-1 text-gray-900 dark:text-white">
                  {{ formatDate(entry.posted_at) }}
                  <span v-if="entry?.posted_by" class="text-gray-500">
                    by {{ entry.posted_by.name }}
                  </span>
                </div>
              </div>
              
              <div>
                <label class="text-sm font-medium text-gray-500">Created</label>
                <div class="mt-1 text-gray-900 dark:text-white">
                  {{ formatDate(entry?.created_at) }}
                  <span v-if="entry?.created_by" class="text-gray-500">
                    by {{ entry.created_by.name }}
                  </span>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Totals -->
        <Card>
          <template #title>Totals</template>
          <template #content>
            <div class="space-y-4">
              <div class="flex justify-between items-center">
                <span class="text-gray-500">Total Debit</span>
                <span class="text-lg font-semibold text-gray-900 dark:text-white">
                  {{ formatCurrency(entry?.total_debit) }}
                </span>
              </div>
              
              <div class="flex justify-between items-center">
                <span class="text-gray-500">Total Credit</span>
                <span class="text-lg font-semibold text-gray-900 dark:text-white">
                  {{ formatCurrency(entry?.total_credit) }}
                </span>
              </div>
              
              <Divider />
              
              <div class="flex justify-between items-center">
                <span class="text-gray-700 dark:text-gray-300 font-medium">Balance</span>
                <span 
                  class="text-lg font-semibold"
                  :class="entry?.total_debit === entry?.total_credit ? 'text-green-600' : 'text-red-600'"
                >
                  {{ formatCurrency(Math.abs((entry?.total_debit || 0) - (entry?.total_credit || 0))) }}
                </span>
              </div>
              
              <div class="text-center">
                <Badge 
                  :severity="entry?.total_debit === entry?.total_credit ? 'success' : 'danger'"
                  :value="entry?.total_debit === entry?.total_credit ? 'Balanced' : 'Unbalanced'"
                />
              </div>
            </div>
          </template>
        </Card>

        <!-- Actions -->
        <Card>
          <template #title>Actions</template>
          <template #content>
            <div class="space-y-3">
              <div v-if="entry?.status === 'draft'" class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                  <SvgIcon name="info" set="line" class="w-4 h-4 text-blue-600" />
                  <span class="text-sm font-medium text-blue-800 dark:text-blue-200">
                    Draft Entry
                  </span>
                </div>
                <p class="text-sm text-blue-700 dark:text-blue-300">
                  This entry is still in draft mode and can be edited or posted.
                </p>
                <Button
                  v-if="canPost"
                  label="Post Entry"
                  icon="check"
                  size="small"
                  class="mt-3 w-full"
                  @click="postEntry"
                />
              </div>
              
              <div v-if="entry?.status === 'posted'" class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                  <SvgIcon name="check-circle" set="line" class="w-4 h-4 text-green-600" />
                  <span class="text-sm font-medium text-green-800 dark:text-green-200">
                    Posted Entry
                  </span>
                </div>
                <p class="text-sm text-green-700 dark:text-green-300">
                  This entry has been posted to the ledger and affects account balances.
                </p>
                <Button
                  v-if="canVoid"
                  label="Void Entry"
                  icon="ban"
                  size="small"
                  severity="danger"
                  class="mt-3 w-full"
                  @click="showVoidDialog = true"
                />
              </div>
              
              <div v-if="entry?.status === 'void'" class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                  <SvgIcon name="x-circle" set="line" class="w-4 h-4 text-red-600" />
                  <span class="text-sm font-medium text-red-800 dark:text-red-200">
                    Voided Entry
                  </span>
                </div>
                <p class="text-sm text-red-700 dark:text-red-300">
                  This entry has been voided and no longer affects account balances.
                </p>
                <div v-if="entry?.metadata?.void_reason" class="mt-2">
                  <label class="text-xs font-medium text-red-600">Reason:</label>
                  <p class="text-sm text-red-800 dark:text-red-200">
                    {{ entry.metadata.void_reason }}
                  </p>
                </div>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Journal Lines -->
      <Card>
        <template #title>Journal Lines ({{ entry?.journal_lines?.length || 0 }})</template>
        <template #content>
          <div class="space-y-3">
            <div 
              v-for="(line, index) in entry?.journal_lines || []" 
              :key="line.id"
              class="grid grid-cols-1 md:grid-cols-12 gap-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
            >
              <!-- Line Number -->
              <div class="md:col-span-1 flex items-center">
                <span class="text-sm font-medium text-gray-500">
                  #{{ line.line_number }}
                </span>
              </div>
              
              <!-- Account -->
              <div class="md:col-span-4">
                <div class="text-sm font-medium text-gray-900 dark:text-white">
                  {{ getAccountName(line.ledger_account) }}
                </div>
                <div class="text-xs text-gray-500">
                  {{ line.ledger_account?.type }}
                </div>
              </div>
              
              <!-- Description -->
              <div class="md:col-span-3">
                <div class="text-sm text-gray-900 dark:text-white">
                  {{ line.description || 'No description' }}
                </div>
              </div>
              
              <!-- Debit -->
              <div class="md:col-span-2 text-right">
                <div v-if="line.debit_amount > 0" class="text-sm font-medium text-green-600">
                  {{ formatCurrency(line.debit_amount) }}
                </div>
                <div v-else class="text-sm text-gray-400">
                  —
                </div>
              </div>
              
              <!-- Credit -->
              <div class="md:col-span-2 text-right">
                <div v-if="line.credit_amount > 0" class="text-sm font-medium text-red-600">
                  {{ formatCurrency(line.credit_amount) }}
                </div>
                <div v-else class="text-sm text-gray-400">
                  —
                </div>
              </div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Void Dialog -->
    <Dialog 
      v-model:visible="showVoidDialog" 
      modal 
      header="Void Journal Entry"
      :style="{ width: '450px' }"
    >
      <div class="space-y-4">
        <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
          <div class="flex items-center gap-2">
            <SvgIcon name="alert-triangle" set="line" class="w-4 h-4 text-red-600" />
            <span class="text-sm font-medium text-red-800 dark:text-red-200">
              This action cannot be undone
            </span>
          </div>
          <p class="text-sm text-red-700 dark:text-red-300 mt-2">
            Voiding this entry will reverse its effect on all account balances.
          </p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Reason for voiding <span class="text-red-500">*</span>
          </label>
          <Textarea
            v-model="voidReason"
            rows="3"
            class="w-full"
            placeholder="Please provide a detailed reason for voiding this entry..."
          />
        </div>
      </div>
      
      <template #footer>
        <div class="flex justify-end gap-3">
          <Button
            label="Cancel"
            severity="secondary"
            outlined
            @click="showVoidDialog = false"
          />
          <Button
            label="Void Entry"
            severity="danger"
            :loading="voiding"
            :disabled="!voidReason.trim()"
            @click="voidEntry"
          />
        </div>
      </template>
    </Dialog>
  </LayoutShell>
</template>

<style scoped>
:deep(.p-card) {
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

:deep(.p-divider) {
  margin: 1rem 0;
}

:deep(.p-dialog) {
  border-radius: 0.75rem;
}
</style>