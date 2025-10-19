<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Report Templates
          </h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Manage and customize report templates for different report types
          </p>
        </div>
        
        <div class="flex items-center space-x-3">
          <!-- Create Button -->
          <Button
            icon="pi pi-plus"
            label="Create Template"
            @click="showCreateDialog = true"
          />
          
          <!-- Reorder Button -->
          <Button
            icon="pi pi-sort"
            label="Reorder"
            severity="secondary"
            @click="enableReordering"
            :disabled="templates.length === 0"
          />
        </div>
      </div>

      <!-- Filters -->
      <Card>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Search
              </label>
              <InputText
                v-model="filters.search"
                placeholder="Search templates..."
                class="w-full"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Report Type
              </label>
              <Dropdown
                v-model="filters.reportType"
                :options="reportTypeOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="All Types"
                class="w-full"
                showClear
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Status
              </label>
              <Dropdown
                v-model="filters.status"
                :options="statusOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="All Status"
                class="w-full"
                showClear
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Visibility
              </label>
              <Dropdown
                v-model="filters.visibility"
                :options="visibilityOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="All Visibility"
                class="w-full"
                showClear
              />
            </div>
          </div>
        </template>
      </Card>

      <!-- Templates Grid -->
      <div v-if="loading" class="flex justify-center py-12">
        <ProgressSpinner />
      </div>
      
      <div v-else-if="error" class="text-center py-12">
        <i class="pi pi-exclamation-triangle text-4xl text-yellow-500 mb-4"></i>
        <p class="text-gray-600 dark:text-gray-400">{{ error }}</p>
        <Button
          label="Retry"
          icon="pi pi-refresh"
          @click="loadTemplates"
          class="mt-4"
        />
      </div>
      
      <div v-else-if="filteredTemplates.length === 0" class="text-center py-12">
        <i class="pi pi-file-text text-4xl text-gray-400 mb-4"></i>
        <p class="text-gray-600 dark:text-gray-400">
          {{ filters.search || filters.reportType || filters.status || filters.visibility ? 'No templates found matching your filters.' : 'No templates created yet.' }}
        </p>
        <Button
          label="Create First Template"
          icon="pi pi-plus"
          @click="showCreateDialog = true"
          class="mt-4"
          v-if="!filters.search && !filters.reportType && !filters.status && !filters.visibility"
        />
      </div>
      
      <!-- Templates Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <Card
          v-for="template in filteredTemplates"
          :key="template.template_id"
          class="relative transition-all duration-200 hover:shadow-lg"
          :class="{ 'ring-2 ring-blue-500': selectedTemplate?.template_id === template.template_id }"
        >
          <!-- Reorder Handle -->
          <div
            v-if="isReordering"
            class="absolute top-2 left-2 z-10 cursor-move"
            style="cursor: grab;"
            @mousedown="startDrag(template, $event)"
          >
            <i class="pi pi-bars text-gray-400"></i>
          </div>
          
          <template #header>
            <div class="flex items-center justify-between p-4">
              <div class="flex items-center space-x-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-full"
                     :class="getReportTypeColor(template.report_type)">
                  <i :class="getReportTypeIcon(template.report_type)" class="text-white"></i>
                </div>
                <div>
                  <h3 class="font-semibold text-gray-900 dark:text-white truncate">
                    {{ template.name }}
                  </h3>
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ formatReportType(template.report_type) }}
                  </p>
                </div>
              </div>
              
              <!-- Status Badge -->
              <Badge
                :value="template.is_active ? 'Active' : 'Inactive'"
                :severity="template.is_active ? 'success' : 'danger'"
              />
            </div>
          </template>
          
          <template #content>
            <div class="space-y-4">
              <!-- Description -->
              <p v-if="template.description" class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                {{ template.description }}
              </p>
              
              <!-- Template Details -->
              <div class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-500 dark:text-gray-400">Visibility:</span>
                  <Badge
                    :value="formatVisibility(template.visibility)"
                    :severity="getVisibilitySeverity(template.visibility)"
                    size="small"
                  />
                </div>
                
                <div class="flex justify-between text-sm">
                  <span class="text-gray-500 dark:text-gray-400">Position:</span>
                  <span class="font-medium">{{ template.position || 0 }}</span>
                </div>
                
                <div class="flex justify-between text-sm">
                  <span class="text-gray-500 dark:text-gray-400">Created:</span>
                  <span>{{ formatDate(template.created_at) }}</span>
                </div>
              </div>
              
              <!-- Quick Actions -->
              <div class="flex justify-between items-center pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex space-x-2">
                  <Button
                    icon="pi pi-eye"
                    size="small"
                    severity="secondary"
                    @click="previewTemplate(template)"
                    v-tooltip="'Preview'"
                  />
                  
                  <Button
                    icon="pi pi-copy"
                    size="small"
                    severity="secondary"
                    @click="duplicateTemplate(template)"
                    v-tooltip="'Duplicate'"
                  />
                  
                  <Button
                    icon="pi pi-pencil"
                    size="small"
                    @click="editTemplate(template)"
                    v-tooltip="'Edit'"
                  />
                </div>
                
                <Button
                  icon="pi pi-trash"
                  size="small"
                  severity="danger"
                  @click="confirmDelete(template)"
                  v-tooltip="'Delete'"
                  :disabled="!canDelete(template)"
                />
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Create/Edit Dialog -->
      <Dialog
        v-model:visible="showCreateDialog"
        :header="editingTemplate ? 'Edit Template' : 'Create Template'"
        :modal="true"
        :style="{ width: '80vw', maxWidth: '900px' }"
        :breakpoints="{ '960px': '100vw' }"
      >
        <TemplateForm
          :template="editingTemplate"
          :loading="saving"
          @save="handleSave"
          @cancel="handleCancel"
        />
      </Dialog>

      <!-- Preview Dialog -->
      <Dialog
        v-model:visible="showPreviewDialog"
        :header="`Preview: ${previewingTemplate?.name}`"
        :modal="true"
        :style="{ width: '90vw', maxWidth: '1200px' }"
        :breakpoints="{ '960px': '100vw' }"
      >
        <TemplatePreview
          :template="previewingTemplate"
          @close="showPreviewDialog = false"
        />
      </Dialog>

      <!-- Delete Confirmation -->
      <ConfirmDialog />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import AppLayout from '@/Layouts/AuthenticatedLayout.vue'
import TemplateForm from '@/Components/Reporting/Templates/TemplateForm.vue'
import TemplatePreview from '@/Components/Reporting/Templates/TemplatePreview.vue'

const toast = useToast()
const confirm = useConfirm()

// State
const loading = ref(false)
const saving = ref(false)
const error = ref(null)
const templates = ref([])
const isReordering = ref(false)
const draggedItem = ref(null)

// Dialogs
const showCreateDialog = ref(false)
const showPreviewDialog = ref(false)
const editingTemplate = ref(null)
const selectedTemplate = ref(null)
const previewingTemplate = ref(null)

// Filters
const filters = ref({
  search: '',
  reportType: null,
  status: null,
  visibility: null
})

// Options
const reportTypeOptions = ref([
  { label: 'Income Statement', value: 'income_statement' },
  { label: 'Balance Sheet', value: 'balance_sheet' },
  { label: 'Cash Flow', value: 'cash_flow' },
  { label: 'Trial Balance', value: 'trial_balance' },
  { label: 'KPI Dashboard', value: 'kpi_dashboard' }
])

const statusOptions = ref([
  { label: 'Active', value: true },
  { label: 'Inactive', value: false }
])

const visibilityOptions = ref([
  { label: 'Public', value: 'public' },
  { label: 'Private', value: 'private' },
  { label: 'Role-based', value: 'role_based' }
])

// Computed
const filteredTemplates = computed(() => {
  let filtered = templates.value

  if (filters.value.search) {
    const search = filters.value.search.toLowerCase()
    filtered = filtered.filter(template =>
      template.name.toLowerCase().includes(search) ||
      template.description?.toLowerCase().includes(search)
    )
  }

  if (filters.value.reportType) {
    filtered = filtered.filter(template => template.report_type === filters.value.reportType)
  }

  if (filters.value.status !== null) {
    filtered = filtered.filter(template => template.is_active === filters.value.status)
  }

  if (filters.value.visibility) {
    filtered = filtered.filter(template => template.visibility === filters.value.visibility)
  }

  return filtered.sort((a, b) => (a.position || 0) - (b.position || 0))
})

// Methods
const loadTemplates = async () => {
  loading.value = true
  error.value = null

  try {
    const response = await fetch('/api/reporting/templates')
    if (!response.ok) throw new Error('Failed to load templates')
    
    const data = await response.json()
    templates.value = data.data || data
  } catch (err) {
    error.value = err.message
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load templates',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const refreshTemplates = () => {
  loadTemplates()
}

const enableReordering = () => {
  isReordering.value = true
  toast.add({
    severity: 'info',
    summary: 'Reordering Mode',
    detail: 'Drag templates to reorder them. Click "Save Order" when done.',
    life: 5000
  })
}

const disableReordering = () => {
  isReordering.value = false
  draggedItem.value = null
}

const startDrag = (template, event) => {
  if (!isReordering.value) return
  
  draggedItem.value = template
  event.dataTransfer.effectAllowed = 'move'
}

const saveOrder = async () => {
  try {
    const orderedTemplateIds = templates.value.map(t => t.template_id)
    
    const response = await fetch('/api/reporting/templates/reorder', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        template_ids: orderedTemplateIds
      })
    })

    if (!response.ok) throw new Error('Failed to save order')

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Template order saved',
      life: 3000
    })
    
    disableReordering()
    await loadTemplates()
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to save template order',
      life: 3000
    })
  }
}

const editTemplate = (template) => {
  editingTemplate.value = { ...template }
  showCreateDialog.value = true
}

const duplicateTemplate = async (template) => {
  try {
    const response = await fetch(`/api/reporting/templates/${template.template_id}/duplicate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    })

    if (!response.ok) throw new Error('Failed to duplicate template')

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Template duplicated successfully',
      life: 3000
    })

    await loadTemplates()
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to duplicate template',
      life: 3000
    })
  }
}

const previewTemplate = (template) => {
  previewingTemplate.value = template
  showPreviewDialog.value = true
}

const confirmDelete = (template) => {
  if (!canDelete(template)) {
    toast.add({
      severity: 'warn',
      summary: 'Cannot Delete',
      detail: 'This template is being used by schedules or reports',
      life: 3000
    })
    return
  }

  confirm.require({
    message: `Are you sure you want to delete "${template.name}"?`,
    header: 'Delete Template',
    icon: 'pi pi-exclamation-triangle',
    rejectClass: 'p-button-secondary p-button-outlined',
    rejectLabel: 'Cancel',
    acceptLabel: 'Delete',
    acceptClass: 'p-button-danger',
    accept: () => deleteTemplate(template)
  })
}

const deleteTemplate = async (template) => {
  try {
    const response = await fetch(`/api/reporting/templates/${template.template_id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    })

    if (!response.ok) throw new Error('Failed to delete template')

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Template deleted successfully',
      life: 3000
    })

    await loadTemplates()
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to delete template',
      life: 3000
    })
  }
}

const handleSave = async (templateData) => {
  saving.value = true

  try {
    const url = editingTemplate.value 
      ? `/api/reporting/templates/${editingTemplate.value.template_id}`
      : '/api/reporting/templates'
    
    const method = editingTemplate.value ? 'PUT' : 'POST'

    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify(templateData)
    })

    if (!response.ok) {
      const errorData = await response.json()
      throw new Error(errorData.message || 'Failed to save template')
    }

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: `Template ${editingTemplate.value ? 'updated' : 'created'} successfully`,
      life: 3000
    })

    showCreateDialog.value = false
    editingTemplate.value = null
    await loadTemplates()
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: err.message || 'Failed to save template',
      life: 3000
    })
  } finally {
    saving.value = false
  }
}

const handleCancel = () => {
  showCreateDialog.value = false
  editingTemplate.value = null
}

// Utility Functions
const formatReportType = (type) => {
  const option = reportTypeOptions.value.find(opt => opt.value === type)
  return option ? option.label : type
}

const formatVisibility = (visibility) => {
  const option = visibilityOptions.value.find(opt => opt.value === visibility)
  return option ? option.label : visibility
}

const getVisibilitySeverity = (visibility) => {
  switch (visibility) {
    case 'public': return 'success'
    case 'private': return 'danger'
    case 'role_based': return 'info'
    default: return 'secondary'
  }
}

const getReportTypeIcon = (type) => {
  switch (type) {
    case 'income_statement': return 'pi pi-chart-line'
    case 'balance_sheet': return 'pi pi-chart-bar'
    case 'cash_flow': return 'pi pi-money-bill'
    case 'trial_balance': return 'pi pi-table'
    case 'kpi_dashboard': return 'pi pi-th-large'
    default: return 'pi pi-file'
  }
}

const getReportTypeColor = (type) => {
  switch (type) {
    case 'income_statement': return 'bg-blue-500'
    case 'balance_sheet': return 'bg-green-500'
    case 'cash_flow': return 'bg-purple-500'
    case 'trial_balance': return 'bg-orange-500'
    case 'kpi_dashboard': return 'bg-pink-500'
    default: return 'bg-gray-500'
  }
}

const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString()
}

const canDelete = (template) => {
  // Check if template is being used by any schedules or is a default template
  return !template.is_default && template.usage_count === 0
}

// Watch for filter changes
watch(filters, () => {
  // Filters are reactive through computed property
}, { deep: true })

// Lifecycle
onMounted(() => {
  loadTemplates()
})
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>