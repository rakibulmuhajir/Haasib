<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import PrimeDrawer from 'primevue/drawer'
import PrimeButton from 'primevue/button'
import PrimeCard from 'primevue/card'
import PrimeInputText from 'primevue/inputtext'
import PrimeTextarea from 'primevue/textarea'
import PrimeDropdown from 'primevue/dropdown'
import PrimeCheckbox from 'primevue/checkbox'
import PrimeDataTable from 'primevue/datatable'
import PrimeColumn from 'primevue/column'
import PrimeDialog from 'primevue/dialog'
import PrimeMessages from 'primevue/messages'
import PrimeMessage from 'primevue/message'
import PrimeBadge from 'primevue/badge'
import PrimeTag from 'primevue/tag'
import PrimeIcon from 'primevue/icon'
import PrimeScrollPanel from 'primevue/scrollpanel'
import PrimeToolbar from 'primevue/toolbar'
import PrimeTooltip from 'primevue/tooltip'
import PrimeDivider from 'primevue/divider'

interface TemplateTask {
  id?: number
  code: string
  title: string
  category: string
  sequence: number
  is_required: boolean
  default_notes?: string
}

interface Template {
  id: string
  name: string
  description?: string
  frequency: string
  is_default: boolean
  active: boolean
  templateTasks: TemplateTask[]
}

interface Props {
  visible: boolean
  templateId?: string | null
  mode?: 'create' | 'edit' | 'view'
}

const props = withDefaults(defineProps<Props>(), {
  templateId: null,
  mode: 'create'
})

const emit = defineEmits<{
  'update:visible': [value: boolean]
  'created': [template: Template]
  'updated': [template: Template]
  'archived': [template: Template]
  'synced': [templateId: string, periodCloseId: string]
}>()

const page = usePage()
const templates = ref<Template[]>([])
const loading = ref(false)
const saving = ref(false)
const showDeleteDialog = ref(false)
const showSyncDialog = ref(false)
const selectedPeriodClose = ref<string>('')
const periodCloses = ref<any[]>([])
const messages = ref<any[]>([])

const frequencies = [
  { label: 'Monthly', value: 'monthly' },
  { label: 'Quarterly', value: 'quarterly' },
  { label: 'Yearly', value: 'yearly' },
  { label: 'Custom', value: 'custom' }
]

const categories = [
  { label: 'Trial Balance', value: 'trial_balance' },
  { label: 'Reconciliations', value: 'reconciliations' },
  { label: 'Compliance', value: 'compliance' },
  { label: 'Reporting', value: 'reporting' },
  { label: 'Adjustments', value: 'adjustments' },
  { label: 'Other', value: 'other' }
]

const form = useForm({
  name: '',
  description: '',
  frequency: 'monthly',
  is_default: false,
  active: true,
  tasks: [] as TemplateTask[]
})

const editingTask = ref<TemplateTask | null>(null)
const taskDialogVisible = ref(false)
const taskForm = useForm({
  code: '',
  title: '',
  category: 'trial_balance',
  sequence: 1,
  is_required: true,
  default_notes: ''
})

// Computed properties
const isEditing = computed(() => props.mode === 'edit' && props.templateId)
const isViewing = computed(() => props.mode === 'view')
const canSave = computed(() => !isViewing.value && !saving.value)
const canArchive = computed(() => isEditing.value && !saving.value)
const canSync = computed(() => isEditing.value && !saving.value)

const formValid = computed(() => {
  return form.name.trim() !== '' && 
         form.frequency !== '' && 
         form.tasks.length > 0 &&
         form.tasks.every(task => task.code.trim() !== '' && task.title.trim() !== '')
})

// Methods
const resetForm = () => {
  form.reset()
  editingTask.value = null
  taskDialogVisible.value = false
  selectedPeriodClose.value = ''
  messages.value = []
}

const loadTemplates = async () => {
  try {
    loading.value = true
    const response = await fetch('/api/v1/ledger/period-close/templates', {
      headers: {
        'X-Company-Id': page.props.currentCompany?.id,
        'Accept': 'application/json'
      }
    })
    
    if (response.ok) {
      const data = await response.json()
      templates.value = data.data.templates || []
    }
  } catch (error) {
    console.error('Failed to load templates:', error)
    messages.value = [{
      severity: 'error',
      text: 'Failed to load templates'
    }]
  } finally {
    loading.value = false
  }
}

const loadTemplate = async (templateId: string) => {
  try {
    loading.value = true
    const response = await fetch(`/api/v1/ledger/period-close/templates/${templateId}`, {
      headers: {
        'X-Company-Id': page.props.currentCompany?.id,
        'Accept': 'application/json'
      }
    })
    
    if (response.ok) {
      const data = await response.json()
      const template = data.data
      form.name = template.name
      form.description = template.description || ''
      form.frequency = template.frequency
      form.is_default = template.is_default
      form.active = template.active
      form.tasks = template.templateTasks || []
    }
  } catch (error) {
    console.error('Failed to load template:', error)
    messages.value = [{
      severity: 'error',
      text: 'Failed to load template'
    }]
  } finally {
    loading.value = false
  }
}

const loadPeriodCloses = async () => {
  try {
    const response = await fetch('/api/v1/ledger/periods', {
      headers: {
        'X-Company-Id': page.props.currentCompany?.id,
        'Accept': 'application/json'
      }
    })
    
    if (response.ok) {
      const data = await response.json()
      periodCloses.value = data.data || []
    }
  } catch (error) {
    console.error('Failed to load period closes:', error)
  }
}

const saveTemplate = async () => {
  if (!formValid.value) return
  
  saving.value = true
  messages.value = []
  
  try {
    const url = isEditing.value 
      ? `/api/v1/ledger/period-close/templates/${props.templateId}`
      : '/api/v1/ledger/period-close/templates'
    
    const method = isEditing.value ? 'PUT' : 'POST'
    
    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-Company-Id': page.props.currentCompany?.id,
        'Accept': 'application/json'
      },
      body: JSON.stringify(form.data())
    })
    
    const data = await response.json()
    
    if (response.ok) {
      messages.value = [{
        severity: 'success',
        text: data.message
      }]
      
      if (isEditing.value) {
        emit('updated', data.data)
      } else {
        emit('created', data.data)
      }
      
      await loadTemplates()
      
      if (!isEditing.value) {
        resetForm()
      }
    } else {
      messages.value = data.errors 
        ? Object.entries(data.errors).map(([field, messages]: [string, any]) => ({
            severity: 'error',
            text: Array.isArray(messages) ? messages[0] : messages
          }))
        : [{ severity: 'error', text: data.message }]
    }
  } catch (error) {
    console.error('Failed to save template:', error)
    messages.value = [{
      severity: 'error',
      text: 'Failed to save template'
    }]
  } finally {
    saving.value = false
  }
}

const archiveTemplate = async () => {
  if (!props.templateId) return
  
  try {
    const response = await fetch(`/api/v1/ledger/period-close/templates/${props.templateId}/archive`, {
      method: 'POST',
      headers: {
        'X-Company-Id': page.props.currentCompany?.id,
        'Accept': 'application/json'
      }
    })
    
    const data = await response.json()
    
    if (response.ok) {
      messages.value = [{
        severity: 'success',
        text: data.message
      }]
      
      emit('archived', props.templateId)
      await loadTemplates()
      closeDrawer()
    } else {
      messages.value = [{
        severity: 'error',
        text: data.message
      }]
    }
  } catch (error) {
    console.error('Failed to archive template:', error)
    messages.value = [{
      severity: 'error',
      text: 'Failed to archive template'
    }]
  }
  
  showDeleteDialog.value = false
}

const syncTemplate = async () => {
  if (!props.templateId || !selectedPeriodClose.value) return
  
  try {
    const response = await fetch(`/api/v1/ledger/period-close/templates/${props.templateId}/sync`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Company-Id': page.props.currentCompany?.id,
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        period_close_id: selectedPeriodClose.value
      })
    })
    
    const data = await response.json()
    
    if (response.ok) {
      messages.value = [{
        severity: 'success',
        text: data.message
      }]
      
      emit('synced', props.templateId, selectedPeriodClose.value)
      showSyncDialog.value = false
      selectedPeriodClose.value = ''
    } else {
      messages.value = [{
        severity: 'error',
        text: data.message
      }]
    }
  } catch (error) {
    console.error('Failed to sync template:', error)
    messages.value = [{
      severity: 'error',
      text: 'Failed to sync template'
    }]
  }
}

const closeDrawer = () => {
  emit('update:visible', false)
  resetForm()
}

// Task management
const openTaskDialog = (task?: TemplateTask) => {
  editingTask.value = task || null
  taskDialogVisible.value = true
  
  if (task) {
    taskForm.code = task.code
    taskForm.title = task.title
    taskForm.category = task.category
    taskForm.sequence = task.sequence
    taskForm.is_required = task.is_required
    taskForm.default_notes = task.default_notes || ''
  } else {
    taskForm.reset()
    taskForm.sequence = (form.tasks.length + 1)
  }
}

const saveTask = () => {
  if (!taskForm.code.trim() || !taskForm.title.trim()) return
  
  const taskData = {
    code: taskForm.code,
    title: taskForm.title,
    category: taskForm.category,
    sequence: taskForm.sequence,
    is_required: taskForm.is_required,
    default_notes: taskForm.default_notes
  }
  
  if (editingTask.value) {
    const index = form.tasks.findIndex(t => t.id === editingTask.value?.id)
    if (index !== -1) {
      form.tasks[index] = { ...taskData, id: editingTask.value.id }
    }
  } else {
    form.tasks.push(taskData)
  }
  
  taskDialogVisible.value = false
  editingTask.value = null
  taskForm.reset()
}

const removeTask = (index: number) => {
  form.tasks.splice(index, 1)
  // Reorder tasks
  form.tasks.forEach((task, i) => {
    task.sequence = i + 1
  })
}

const moveTask = (index: number, direction: 'up' | 'down') => {
  const newIndex = direction === 'up' ? index - 1 : index + 1
  if (newIndex >= 0 && newIndex < form.tasks.length) {
    [form.tasks[index], form.tasks[newIndex]] = [form.tasks[newIndex], form.tasks[index]]
    // Update sequences
    form.tasks.forEach((task, i) => {
      task.sequence = i + 1
    })
  }
}

const getCategoryLabel = (category: string) => {
  return categories.find(c => c.value === category)?.label || category
}

const getFrequencyLabel = (frequency: string) => {
  return frequencies.find(f => f.value === frequency)?.label || frequency
}

// Lifecycle
watch(() => props.visible, (visible) => {
  if (visible) {
    loadTemplates()
    if (isEditing.value && props.templateId) {
      loadTemplate(props.templateId)
    } else if (props.mode === 'create') {
      resetForm()
    }
  }
})

watch(() => props.templateId, (templateId) => {
  if (templateId && isEditing.value) {
    loadTemplate(templateId)
  }
})

onMounted(() => {
  if (props.visible) {
    loadTemplates()
    if (isEditing.value && props.templateId) {
      loadTemplate(props.templateId)
    }
  }
})
</script>

<template>
  <PrimeDrawer
    :visible="visible"
    @update:visible="closeDrawer"
    position="right"
    :style="{ width: '50rem' }"
    header="Template Management"
    :modal="true"
    :block-scroll="true"
  >
    <template #header>
      <div class="flex items-center justify-between w-full">
        <div class="flex items-center gap-2">
          <PrimeIcon name="pi pi-list" />
          <h2 class="text-xl font-semibold">
            {{ mode === 'create' ? 'Create Template' : mode === 'edit' ? 'Edit Template' : 'View Template' }}
          </h2>
        </div>
        
        <div v-if="isEditing" class="flex items-center gap-2">
          <PrimeButton
            icon="pi pi-sync"
            label="Sync"
            severity="info"
            size="small"
            @click="showSyncDialog = true"
            v-tooltip="'Sync template to period close'"
          />
          <PrimeButton
            icon="pi pi-trash"
            label="Archive"
            severity="danger"
            size="small"
            @click="showDeleteDialog = true"
            v-tooltip="'Archive template'"
          />
        </div>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Messages -->
      <PrimeMessages v-if="messages.length > 0" :messages="messages" />

      <!-- Template Details -->
      <PrimeCard>
        <template #header>
          <h3 class="text-lg font-medium">Template Details</h3>
        </template>
        
        <template #content>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                  Template Name *
                </label>
                <PrimeInputText
                  id="name"
                  v-model="form.name"
                  :disabled="isViewing"
                  placeholder="Enter template name"
                  class="w-full"
                />
              </div>
              
              <div>
                <label for="frequency" class="block text-sm font-medium text-gray-700 mb-1">
                  Frequency *
                </label>
                <PrimeDropdown
                  id="frequency"
                  v-model="form.frequency"
                  :options="frequencies"
                  option-label="label"
                  option-value="value"
                  :disabled="isViewing"
                  placeholder="Select frequency"
                  class="w-full"
                />
              </div>
            </div>
            
            <div>
              <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                Description
              </label>
              <PrimeTextarea
                id="description"
                v-model="form.description"
                :disabled="isViewing"
                placeholder="Enter template description"
                rows="3"
                class="w-full"
              />
            </div>
            
            <div class="flex items-center gap-4">
              <PrimeCheckbox
                v-model="form.is_default"
                :disabled="isViewing"
                input-id="is_default"
              />
              <label for="is_default" class="text-sm font-medium text-gray-700">
                Set as default template
              </label>
              
              <PrimeCheckbox
                v-model="form.active"
                :disabled="isViewing"
                input-id="active"
              />
              <label for="active" class="text-sm font-medium text-gray-700">
                Active
              </label>
            </div>
          </div>
        </template>
      </PrimeCard>

      <!-- Tasks -->
      <PrimeCard>
        <template #header>
          <div class="flex items-center justify-between w-full">
            <h3 class="text-lg font-medium">Tasks</h3>
            <PrimeButton
              icon="pi pi-plus"
              label="Add Task"
              size="small"
              @click="openTaskDialog()"
              :disabled="isViewing"
            />
          </div>
        </template>
        
        <template #content>
          <div v-if="form.tasks.length === 0" class="text-center py-8 text-gray-500">
            <PrimeIcon name="pi pi-list" class="text-4xl mb-2" />
            <p>No tasks added yet. Click "Add Task" to get started.</p>
          </div>
          
          <PrimeDataTable
            v-else
            :value="form.tasks"
            :paginator="false"
            class="p-datatable-sm"
          >
            <PrimeColumn field="sequence" header="#" style="width: 4rem">
              <template #body="{ data, index }">
                <div class="flex items-center gap-1">
                  <PrimeButton
                    icon="pi pi-angle-up"
                    size="small"
                    text
                    @click="moveTask(index, 'up')"
                    :disabled="index === 0 || isViewing"
                  />
                  <PrimeButton
                    icon="pi pi-angle-down"
                    size="small"
                    text
                    @click="moveTask(index, 'down')"
                    :disabled="index === form.tasks.length - 1 || isViewing"
                  />
                  <span class="text-sm">{{ data.sequence }}</span>
                </div>
              </template>
            </PrimeColumn>
            
            <PrimeColumn field="code" header="Code">
              <template #body="{ data }">
                <PrimeBadge :value="data.code" severity="secondary" />
              </template>
            </PrimeColumn>
            
            <PrimeColumn field="title" header="Title">
              <template #body="{ data }">
                <span class="font-medium">{{ data.title }}</span>
              </template>
            </PrimeColumn>
            
            <PrimeColumn field="category" header="Category">
              <template #body="{ data }">
                <PrimeTag :value="getCategoryLabel(data.category)" severity="info" />
              </template>
            </PrimeColumn>
            
            <PrimeColumn field="is_required" header="Required">
              <template #body="{ data }">
                <PrimeTag 
                  :value="data.is_required ? 'Yes' : 'No'" 
                  :severity="data.is_required ? 'success' : 'secondary'"
                />
              </template>
            </PrimeColumn>
            
            <PrimeColumn field="default_notes" header="Notes">
              <template #body="{ data }">
                <span class="text-sm text-gray-600">
                  {{ data.default_notes?.substring(0, 50) || '-' }}
                  {{ data.default_notes && data.default_notes.length > 50 ? '...' : '' }}
                </span>
              </template>
            </PrimeColumn>
            
            <PrimeColumn style="width: 8rem">
              <template #body="{ index }">
                <div class="flex items-center gap-2">
                  <PrimeButton
                    icon="pi pi-pencil"
                    size="small"
                    text
                    @click="openTaskDialog(form.tasks[index])"
                    :disabled="isViewing"
                    v-tooltip="'Edit task'"
                  />
                  <PrimeButton
                    icon="pi pi-trash"
                    size="small"
                    text
                    severity="danger"
                    @click="removeTask(index)"
                    :disabled="isViewing"
                    v-tooltip="'Remove task'"
                  />
                </div>
              </template>
            </PrimeColumn>
          </PrimeDataTable>
        </template>
      </PrimeCard>
    </div>

    <!-- Footer Actions -->
    <template #footer>
      <div class="flex items-center justify-between">
        <PrimeButton
          label="Cancel"
          severity="secondary"
          @click="closeDrawer"
        />
        
        <div class="flex items-center gap-2">
          <PrimeButton
            label="Save"
            icon="pi pi-check"
            @click="saveTemplate"
            :loading="saving"
            :disabled="!formValid || !canSave"
          />
        </div>
      </div>
    </template>
  </PrimeDrawer>

  <!-- Task Dialog -->
  <PrimeDialog
    v-model:visible="taskDialogVisible"
    :header="editingTask ? 'Edit Task' : 'Add Task'"
    :style="{ width: '40rem' }"
    :modal="true"
  >
    <div class="space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label for="task_code" class="block text-sm font-medium text-gray-700 mb-1">
            Task Code *
          </label>
          <PrimeInputText
            id="task_code"
            v-model="taskForm.code"
            placeholder="e.g., tb_validate"
            class="w-full"
          />
        </div>
        
        <div>
          <label for="task_sequence" class="block text-sm font-medium text-gray-700 mb-1">
            Sequence *
          </label>
          <PrimeInputText
            id="task_sequence"
            v-model.number="taskForm.sequence"
            type="number"
            min="1"
            class="w-full"
          />
        </div>
      </div>
      
      <div>
        <label for="task_title" class="block text-sm font-medium text-gray-700 mb-1">
          Task Title *
        </label>
        <PrimeInputText
          id="task_title"
          v-model="taskForm.title"
          placeholder="e.g., Validate Trial Balance"
          class="w-full"
        />
      </div>
      
      <div>
        <label for="task_category" class="block text-sm font-medium text-gray-700 mb-1">
          Category *
        </label>
        <PrimeDropdown
          id="task_category"
          v-model="taskForm.category"
          :options="categories"
          option-label="label"
          option-value="value"
          placeholder="Select category"
          class="w-full"
        />
      </div>
      
      <div>
        <label for="task_notes" class="block text-sm font-medium text-gray-700 mb-1">
          Default Notes
        </label>
        <PrimeTextarea
          id="task_notes"
          v-model="taskForm.default_notes"
          placeholder="Default notes for this task"
          rows="3"
          class="w-full"
        />
      </div>
      
      <div class="flex items-center gap-2">
        <PrimeCheckbox
          v-model="taskForm.is_required"
          input-id="task_required"
        />
        <label for="task_required" class="text-sm font-medium text-gray-700">
          This task is required
        </label>
      </div>
    </div>
    
    <template #footer>
      <div class="flex items-center justify-end gap-2">
        <PrimeButton
          label="Cancel"
          severity="secondary"
          @click="taskDialogVisible = false"
        />
        <PrimeButton
          label="Save Task"
          @click="saveTask"
          :disabled="!taskForm.code.trim() || !taskForm.title.trim()"
        />
      </div>
    </template>
  </PrimeDialog>

  <!-- Archive Confirmation Dialog -->
  <PrimeDialog
    v-model:visible="showDeleteDialog"
    header="Archive Template"
    :style="{ width: '30rem' }"
    :modal="true"
  >
    <div class="space-y-4">
      <PrimeMessage
        severity="warn"
        text="Are you sure you want to archive this template? This action cannot be undone."
      />
      <p class="text-sm text-gray-600">
        Archiving this template will make it inactive and it will no longer appear in the list of available templates.
      </p>
    </div>
    
    <template #footer>
      <div class="flex items-center justify-end gap-2">
        <PrimeButton
          label="Cancel"
          severity="secondary"
          @click="showDeleteDialog = false"
        />
        <PrimeButton
          label="Archive"
          severity="danger"
          @click="archiveTemplate"
        />
      </div>
    </template>
  </PrimeDialog>

  <!-- Sync Dialog -->
  <PrimeDialog
    v-model:visible="showSyncDialog"
    header="Sync Template to Period Close"
    :style="{ width: '30rem' }"
    :modal="true"
  >
    <div class="space-y-4">
      <div>
        <label for="period_close" class="block text-sm font-medium text-gray-700 mb-1">
          Select Period Close
        </label>
        <PrimeDropdown
          id="period_close"
          v-model="selectedPeriodClose"
          :options="periodCloses"
          option-label="name"
          option-value="id"
          placeholder="Select period close"
          class="w-full"
          filter
        />
      </div>
      
      <PrimeMessage
        severity="info"
        text="This will sync all tasks from this template to the selected period close, updating existing tasks and adding new ones."
      />
    </div>
    
    <template #footer>
      <div class="flex items-center justify-end gap-2">
        <PrimeButton
          label="Cancel"
          severity="secondary"
          @click="showSyncDialog = false"
        />
        <PrimeButton
          label="Sync"
          @click="syncTemplate"
          :disabled="!selectedPeriodClose"
        />
      </div>
    </template>
  </PrimeDialog>
</template>

<style scoped>
:deep(.p-drawer-content) {
  padding: 1.5rem;
}

:deep(.p-card-content) {
  padding: 1rem;
}

:deep(.p-datatable .p-datatable-tbody > tr > td) {
  padding: 0.5rem;
}

:deep(.p-dialog-content) {
  padding: 1.5rem;
}
</style>