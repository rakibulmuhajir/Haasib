<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import AppLayout from '@/Layouts/AppLayout.vue'
import { formatCurrency } from '@/Utils/format.js'

const props = defineProps({
  reconciliation: Object,
  statement_lines: Array,
  matches: Array,
  adjustments: Array,
  unmatched_transactions: Array,
  permissions: Object,
})

const toast = useToast()

const showCompleteDialog = ref(false)
const showLockDialog = ref(false)
const showReopenDialog = ref(false)
const reopenReason = ref('')
const isSubmitting = ref(false)

// Status helpers
const statusVariant = computed(() => {
  const variants = {
    draft: 'secondary',
    in_progress: 'info',
    completed: 'success',
    locked: 'danger',
    reopened: 'warning'
  }
  return variants[props.reconciliation.status] || 'secondary'
})

const varianceStatusVariant = computed(() => {
  const variants = {
    balanced: 'success',
    positive: 'warning',
    negative: 'danger'
  }
  return variants[props.reconciliation.variance_status] || 'secondary'
})

// Actions
const completeReconciliation = () => {
  if (!props.reconciliation.can_be_completed) {
    toast.add({
      severity: 'warn',
      summary: 'Cannot Complete',
      detail: 'Reconciliation must have zero variance to be completed.',
      life: 3000
    })
    return
  }

  isSubmitting.value = true
  
  router.post(
    `/ledger/bank-statements/reconciliations/${props.reconciliation.id}/complete`,
    {},
    {
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Completed',
          detail: 'Reconciliation completed successfully.',
          life: 3000
        })
        showCompleteDialog.value = false
      },
      onError: (errors) => {
        toast.add({
          severity: 'error',
          summary: 'Error',
          detail: errors.message || 'Failed to complete reconciliation.',
          life: 5000
        })
      },
      onFinish: () => {
        isSubmitting.value = false
      }
    }
  )
}

const lockReconciliation = () => {
  if (!props.reconciliation.can_be_locked) {
    toast.add({
      severity: 'warn',
      summary: 'Cannot Lock',
      detail: 'Reconciliation must be completed before it can be locked.',
      life: 3000
    })
    return
  }

  isSubmitting.value = true
  
  router.post(
    `/ledger/bank-statements/reconciliations/${props.reconciliation.id}/lock`,
    {},
    {
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Locked',
          detail: 'Reconciliation locked successfully.',
          life: 3000
        })
        showLockDialog.value = false
      },
      onError: (errors) => {
        toast.add({
          severity: 'error',
          summary: 'Error',
          detail: errors.message || 'Failed to lock reconciliation.',
          life: 5000
        })
      },
      onFinish: () => {
        isSubmitting.value = false
      }
    }
  )
}

const reopenReconciliation = () => {
  if (!reopenReason.value.trim()) {
    toast.add({
      severity: 'warn',
      summary: 'Reason Required',
      detail: 'Please provide a reason for reopening this reconciliation.',
      life: 3000
    })
    return
  }

  isSubmitting.value = true
  
  router.post(
    `/ledger/bank-statements/reconciliations/${props.reconciliation.id}/reopen`,
    { reason: reopenReason.value },
    {
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Reopened',
          detail: 'Reconciliation reopened successfully.',
          life: 3000
        })
        showReopenDialog.value = false
        reopenReason.value = ''
      },
      onError: (errors) => {
        toast.add({
          severity: 'error',
          summary: 'Error',
          detail: errors.message || 'Failed to reopen reconciliation.',
          life: 5000
        })
      },
      onFinish: () => {
        isSubmitting.value = false
      }
    }
  )
}

const autoMatch = () => {
  if (!props.permissions.can_auto_match) {
    toast.add({
      severity: 'warn',
      summary: 'Permission Required',
      detail: 'You do not have permission to run auto-match.',
      life: 3000
    })
    return
  }

  router.post(
    `/ledger/bank-statements/reconciliations/${props.reconciliation.id}/auto-match`,
    {},
    {
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Auto-Match Started',
          detail: 'Auto-matching job has been queued for processing.',
          life: 3000
        })
      },
      onError: (errors) => {
        toast.add({
          severity: 'error',
          summary: 'Error',
          detail: errors.message || 'Failed to start auto-matching.',
          life: 5000
        })
      }
    }
  )
}
</script>

<template>
  <Head title="Bank Reconciliation" />

  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">
            Bank Reconciliation
          </h1>
          <p class="mt-1 text-sm text-gray-500">
            {{ reconciliation.statement.name }} • {{ reconciliation.statement.period }}
          </p>
        </div>
        
        <div class="flex items-center gap-2">
          <Button
            label="Auto-Match"
            icon="pi pi-refresh"
            @click="autoMatch"
            :disabled="!reconciliation.can_be_edited || !permissions.can_auto_match"
            severity="secondary"
            size="small"
          />
          
          <Button
            label="Complete"
            icon="pi pi-check"
            @click="showCompleteDialog = true"
            :disabled="!reconciliation.can_be_completed || !permissions.can_complete"
            severity="success"
            size="small"
          />
          
          <Button
            label="Lock"
            icon="pi pi-lock"
            @click="showLockDialog = true"
            :disabled="!reconciliation.can_be_locked || !permissions.can_lock"
            severity="danger"
            size="small"
          />
          
          <Button
            label="Reopen"
            icon="pi pi-unlock"
            @click="showReopenDialog = true"
            :disabled="!reconciliation.can_be_reopened || !permissions.can_reopen"
            severity="warning"
            size="small"
          />
        </div>
      </div>

      <!-- Status Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Status Card -->
        <Card>
          <template #content>
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500">Status</p>
                <p class="text-lg font-semibold capitalize">
                  {{ reconciliation.status.replace('_', ' ') }}
                </p>
              </div>
              <Tag :value="reconciliation.status" :severity="statusVariant" />
            </div>
          </template>
        </Card>

        <!-- Variance Card -->
        <Card>
          <template #content>
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500">Variance</p>
                <p class="text-lg font-semibold">
                  {{ reconciliation.variance }}
                </p>
              </div>
              <Tag :value="reconciliation.variance_status" :severity="varianceStatusVariant" />
            </div>
          </template>
        </Card>

        <!-- Progress Card -->
        <Card>
          <template #content>
            <div>
              <p class="text-sm text-gray-500">Progress</p>
              <p class="text-lg font-semibold">{{ reconciliation.percent_complete }}%</p>
              <ProgressBar :value="reconciliation.percent_complete" class="mt-2" />
            </div>
          </template>
        </Card>

        <!-- Account Card -->
        <Card>
          <template #content>
            <div>
              <p class="text-sm text-gray-500">Bank Account</p>
              <p class="text-lg font-semibold truncate">{{ reconciliation.bank_account.name }}</p>
              <p class="text-sm text-gray-400">{{ reconciliation.bank_account.account_number }}</p>
            </div>
          </template>
        </Card>
      </div>

      <!-- Summary Info -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Statement Summary -->
        <Card>
          <template #title>
            Statement Summary
          </template>
          <template #content>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-gray-500">Opening Balance:</span>
                <span class="font-medium">{{ reconciliation.statement.opening_balance }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Closing Balance:</span>
                <span class="font-medium">{{ reconciliation.statement.closing_balance }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Currency:</span>
                <span class="font-medium">{{ reconciliation.statement.currency }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Statement Lines:</span>
                <span class="font-medium">{{ reconciliation.statement.lines_count }}</span>
              </div>
            </div>
          </template>
        </Card>

        <!-- Reconciliation Info -->
        <Card>
          <template #title>
            Reconciliation Details
          </template>
          <template #content>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-gray-500">Started:</span>
                <span class="font-medium">
                  {{ reconciliation.started_at ? new Date(reconciliation.started_at).toLocaleDateString() : 'Not started' }}
                </span>
              </div>
              <div class="flex justify-between" v-if="reconciliation.completed_at">
                <span class="text-gray-500">Completed:</span>
                <span class="font-medium">
                  {{ new Date(reconciliation.completed_at).toLocaleDateString() }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Active Duration:</span>
                <span class="font-medium">{{ reconciliation.active_duration || 'N/A' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Matches:</span>
                <span class="font-medium">{{ matches.length }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Adjustments:</span>
                <span class="font-medium">{{ adjustments.length }}</span>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Variance Warning -->
      <Message 
        v-if="reconciliation.variance_status !== 'balanced'"
        :severity="reconciliation.variance_status === 'positive' ? 'warn' : 'error'"
        :closable="false"
      >
        <strong>Variance Detected:</strong> {{ reconciliation.variance }} variance must be resolved before completion.
      </Message>

      <!-- Notes -->
      <Card v-if="reconciliation.notes">
        <template #title>
          Notes
        </template>
        <template #content>
          <p class="whitespace-pre-wrap">{{ reconciliation.notes }}</p>
        </template>
      </Card>
    </div>

    <!-- Complete Confirmation Dialog -->
    <Dialog 
      v-model:visible="showCompleteDialog" 
      modal 
      header="Complete Reconciliation"
      :style="{ width: '450px' }"
    >
      <p class="mb-4">
        Are you sure you want to complete this reconciliation? This will mark the reconciliation as finished and it can no longer be edited unless reopened.
      </p>
      
      <div class="flex justify-end gap-2">
        <Button 
          label="Cancel" 
          @click="showCompleteDialog = false" 
          severity="secondary"
          :disabled="isSubmitting"
        />
        <Button 
          label="Complete" 
          @click="completeReconciliation"
          :loading="isSubmitting"
          severity="success"
        />
      </div>
    </Dialog>

    <!-- Lock Confirmation Dialog -->
    <Dialog 
      v-model:visible="showLockDialog" 
      modal 
      header="Lock Reconciliation"
      :style="{ width: '450px' }"
    >
      <p class="mb-4">
        Are you sure you want to lock this reconciliation? Once locked, it cannot be edited or reopened without special permissions.
      </p>
      
      <div class="flex justify-end gap-2">
        <Button 
          label="Cancel" 
          @click="showLockDialog = false" 
          severity="secondary"
          :disabled="isSubmitting"
        />
        <Button 
          label="Lock" 
          @click="lockReconciliation"
          :loading="isSubmitting"
          severity="danger"
        />
      </div>
    </Dialog>

    <!-- Reopen Dialog -->
    <Dialog 
      v-model:visible="showReopenDialog" 
      modal 
      header="Reopen Reconciliation"
      :style="{ width: '450px' }"
    >
      <p class="mb-4">
        Please provide a reason for reopening this reconciliation. This will be recorded for audit purposes.
      </p>
      
      <div class="mb-4">
        <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">
          Reason
        </label>
        <Textarea 
          id="reason"
          v-model="reopenReason"
          rows="3"
          class="w-full"
          placeholder="Enter reason for reopening..."
        />
      </div>
      
      <div class="flex justify-end gap-2">
        <Button 
          label="Cancel" 
          @click="showReopenDialog = false; reopenReason = ''" 
          severity="secondary"
          :disabled="isSubmitting"
        />
        <Button 
          label="Reopen" 
          @click="reopenReconciliation"
          :loading="isSubmitting"
          severity="warning"
        />
      </div>
    </Dialog>
  </AppLayout>
</template>