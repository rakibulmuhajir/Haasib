<script setup lang="ts">
import { computed } from 'vue'
import { format } from 'date-fns'
import Card from 'primevue/card'
import Badge from 'primevue/badge'
import Divider from 'primevue/divider'
import SvgIcon from '@/Components/SvgIcon.vue'
import Button from 'primevue/button'
import { useFormatting } from '@/composables/useFormatting'

interface JournalEntry {
  id: number
  reference?: string
  date: string
  description: string
  status: 'draft' | 'posted' | 'void'
  total_debit: number
  total_credit: number
  posted_at?: string
  posted_by?: any
  created_at: string
  created_by?: any
  metadata?: {
    void_reason?: string
  }
}

interface Props {
  entry: JournalEntry
  permissions?: {
    post?: boolean
    void?: boolean
  }
  showActions?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  permissions: () => ({ post: true, void: true }),
  showActions: true
})

const emit = defineEmits<{
  post: []
  void: []
}>()

// Get formatting utilities
const { formatMoney } = useFormatting()

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

// Permissions
const canPost = computed(() => props.permissions?.post ?? true)
const canVoid = computed(() => props.permissions?.void ?? true)

// Format date
const formatDate = (dateString: string) => {
  return format(new Date(dateString), 'MMMM dd, yyyy')
}

// Post entry
const postEntry = () => {
  emit('post')
}

// Void entry
const voidEntry = () => {
  emit('void')
}
</script>

<template>
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
              {{ formatMoney(entry?.total_debit) }}
            </span>
          </div>
          
          <div class="flex justify-between items-center">
            <span class="text-gray-500">Total Credit</span>
            <span class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ formatMoney(entry?.total_credit) }}
            </span>
          </div>
          
          <Divider />
          
          <div class="flex justify-between items-center">
            <span class="text-gray-700 dark:text-gray-300 font-medium">Balance</span>
            <span 
              class="text-lg font-semibold"
              :class="entry?.total_debit === entry?.total_credit ? 'text-green-600' : 'text-red-600'"
            >
              {{ formatMoney(Math.abs((entry?.total_debit || 0) - (entry?.total_credit || 0))) }}
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
    <Card v-if="showActions">
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
              @click="voidEntry"
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
</template>

<style scoped>
:deep(.p-card) {
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

:deep(.p-divider) {
  margin: 1rem 0;
}
</style>