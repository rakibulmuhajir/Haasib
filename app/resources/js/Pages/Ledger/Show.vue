<script setup lang="ts">
import { computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import { format } from 'date-fns'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import SvgIcon from '@/Components/SvgIcon.vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { useDeleteConfirmation } from '@/composables/useDeleteConfirmation'
import JournalEntrySummary from '@/Components/Ledger/JournalEntrySummary.vue'
import LinesTable from '@/Components/Ledger/LinesTable.vue'

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

const toast = useToast()
const { confirmDelete } = useDeleteConfirmation()

// Format date
const formatDate = (dateString: string) => {
  return format(new Date(dateString), 'MMMM dd, yyyy')
}

// Post entry
const postEntry = async () => {
  if (!canPost.value) return
  
  try {
    await router.post(route('ledger.post', entry.value.id), {}, {
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Success',
          detail: 'Journal entry posted successfully',
          life: 3000
        })
      }
    })
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
    }, {
      onSuccess: () => {
        showVoidDialog.value = false
        voidReason.value = ''
        toast.add({
          severity: 'success',
          summary: 'Success',
          detail: 'Journal entry voided successfully',
          life: 3000
        })
      }
    })
  } catch (error) {
    console.error('Failed to void entry:', error)
  } finally {
    voiding.value = false
  }
}

// Handle actions from JournalEntrySummary component
const handlePost = () => {
  postEntry()
}

const handleVoid = () => {
  showVoidDialog.value = true
}

// Handle line click from LinesTable component
const handleLineClick = (line: any) => {
  // Future enhancement: show line details or navigate to account
  console.log('Line clicked:', line)
}
</script>

<template>
  <Head title="Journal Entry" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Ledger" />
    </template>
    
    <template #topbar>
      <div class="flex items-center justify-between">
        <Breadcrumb 
          :items="[
            { label: 'Ledger', url: route('ledger.index') },
            { label: entry?.reference || `Entry ${String(entry?.id).slice(0, 8)}` }
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
      <JournalEntrySummary
        :entry="entry"
        :permissions="{
          post: canPost,
          void: canVoid
        }"
        @post="handlePost"
        @void="handleVoid"
      />

      <!-- Journal Lines -->
      <LinesTable
        :lines="entry?.journal_lines || []"
        @lineClick="handleLineClick"
      />
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
:deep(.p-dialog) {
  border-radius: 0.75rem;
}
</style>