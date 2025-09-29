<template>
  <div class="entity-picker">
    <Select
      ref="selectRef"
      v-model="selectedEntityId"
      :options="entities"
      :optionLabel="optionLabel"
      :optionValue="optionValue"
      :optionDisabled="optionDisabled"
      :placeholder="placeholder"
      :filter="true"
      :filterFields="filterFields"
      :filterPlaceholder="filterPlaceholder"
      :showClear="showClear"
      :disabled="disabled"
      :class="['w-full', { 'p-invalid': error }]"
      :loading="loading"
      @filter="onFilter"
      @change="onChange"
      @show="onShow"
      @hide="onHide"
    >
      <template #option="{ option }">
        <div class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded">
          <!-- Avatar/Icon -->
          <div class="flex-shrink-0">
            <div
              v-if="option[avatarField] && option[avatarTypeField] !== 'icon'"
              class="w-8 h-8 rounded-full overflow-hidden bg-gray-100"
            >
              <img
                v-if="option[avatarTypeField] === 'image'"
                :src="option[avatarField]"
                :alt="option[optionLabel]"
                class="w-full h-full object-cover"
              />
              <div
                v-else
                class="w-8 h-8 rounded-full flex items-center justify-center"
                :style="{ backgroundColor: getAvatarColor(option[optionLabel]) }"
              >
                <span class="text-white font-medium text-sm">
                  {{ getInitials(option[optionLabel]) }}
                </span>
              </div>
            </div>
            <div
              v-else-if="option[iconField]"
              class="w-8 h-8 rounded-full flex items-center justify-center bg-gray-100"
            >
              <i :class="[option[iconField], 'text-gray-600 text-sm']" />
            </div>
            <div
              v-else
              class="w-8 h-8 rounded-full flex items-center justify-center bg-gray-200"
            >
              <i :class="defaultIcon" class="text-gray-500" />
            </div>
          </div>

          <!-- Entity Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center space-x-2">
              <span class="font-medium text-gray-900 truncate">
                {{ option[optionLabel] }}
              </span>
              <StatusBadge
                v-if="showStatus && option[statusField]"
                :value="option[statusField]"
                size="sm"
                variant="dot"
              />
            </div>
            
            <!-- Subtitle -->
            <div v-if="showSubtitle && subtitleFields.length" class="text-xs text-gray-500 truncate mt-1">
              <template v-for="(field, index) in subtitleFields" :key="field">
                <span v-if="option[field]">
                  {{ option[field] }}
                  <span v-if="index < subtitleFields.length - 1 && option[subtitleFields[index + 1]]"> • </span>
                </span>
              </template>
            </div>

            <!-- Extra Info -->
            <div v-if="showExtraInfo && extraInfoFields.length" class="text-xs text-gray-400 mt-1">
              <template v-for="(field, index) in extraInfoFields" :key="field">
                <span v-if="option[field]">
                  {{ field }}: {{ option[field] }}
                  <span v-if="index < extraInfoFields.length - 1 && option[extraInfoFields[index + 1]]"> • </span>
                </span>
              </template>
            </div>
          </div>

          <!-- Additional Info -->
          <div v-if="showBalance && option[balanceField] !== undefined" class="text-right flex-shrink-0">
            <BalanceDisplay
              :amount="option[balanceField]"
              :currency="option[currencyField] || defaultCurrency"
              size="sm"
              variant="inline"
            />
          </div>

          <!-- Badge/Tag -->
          <div v-if="showBadge && option[badgeField]" class="text-right flex-shrink-0">
            <Tag
              :value="option[badgeField]"
              :severity="badgeSeverity"
              size="small"
            />
          </div>
        </div>
      </template>

      <template #value="{ value, placeholder }">
        <div v-if="value" class="flex items-center space-x-3">
          <!-- Selected Entity Avatar/Icon -->
          <div class="flex-shrink-0">
            <div
              v-if="selectedEntity?.[avatarField] && selectedEntity?.[avatarTypeField] !== 'icon'"
              class="w-6 h-6 rounded-full overflow-hidden bg-gray-100"
            >
              <img
                v-if="selectedEntity?.[avatarTypeField] === 'image'"
                :src="selectedEntity[avatarField]"
                :alt="selectedEntity[optionLabel]"
                class="w-full h-full object-cover"
              />
              <div
                v-else
                class="w-6 h-6 rounded-full flex items-center justify-center"
                :style="{ backgroundColor: getAvatarColor(selectedEntity?.[optionLabel]) }"
              >
                <span class="text-white text-xs font-medium">
                  {{ getInitials(selectedEntity?.[optionLabel]) }}
                </span>
              </div>
            </div>
            <div
              v-else-if="selectedEntity?.[iconField]"
              class="w-6 h-6 rounded-full flex items-center justify-center bg-gray-100"
            >
              <i :class="[selectedEntity[iconField], 'text-gray-600 text-xs']" />
            </div>
            <div
              v-else
              class="w-6 h-6 rounded-full flex items-center justify-center bg-gray-200"
            >
              <i :class="defaultIcon" class="text-gray-500 text-xs" />
            </div>
          </div>

          <!-- Selected Entity Info -->
          <div class="flex-1 min-w-0">
            <span class="text-gray-900">{{ selectedEntity?.[optionLabel] }}</span>
            <span v-if="selectedEntity?.[subtitleFields[0]]" class="text-xs text-gray-500 ml-2">
              {{ selectedEntity[subtitleFields[0]] }}
            </span>
          </div>

          <!-- Actions -->
          <div v-if="showActions" class="flex-shrink-0">
            <Button
              :icon="actionIcon"
              class="p-button-text p-button-xs p-button-rounded"
              @click.stop="viewEntity"
              :title="actionTitle"
            />
          </div>
        </div>
        <span v-else class="text-gray-400">{{ placeholder }}</span>
      </template>

      <!-- Header with create button -->
      <template #header>
        <div class="flex items-center justify-between p-3 border-b border-gray-200">
          <span class="text-sm font-medium text-gray-700">{{ headerTitle }}</span>
          <Button
            v-if="allowCreate"
            :label="createButtonLabel"
            icon="pi pi-plus"
            class="p-button-text p-button-sm"
            @click.stop="createEntity"
          />
        </div>
      </template>

      <!-- Empty template -->
      <template #emptyfilter>
        <div class="p-4 text-center text-gray-500">
          <i :class="emptyIcon" class="text-2xl mb-2 block text-gray-300"></i>
          <p class="text-sm">No {{ entityNamePlural }} found matching "{{ currentFilter }}"</p>
          <Button
            v-if="allowCreate"
            :label="createButtonLabel"
            class="p-button-outlined p-button-sm mt-2"
            @click="createEntity"
          />
        </div>
      </template>

      <template #empty>
        <div class="p-4 text-center text-gray-500">
          <i :class="emptyIcon" class="text-2xl mb-2 block text-gray-300"></i>
          <p class="text-sm mb-2">No {{ entityNamePlural }} available</p>
          <Button
            v-if="allowCreate"
            :label="createFirstButtonLabel"
            class="p-button-outlined p-button-sm"
            @click="createEntity"
          />
        </div>
      </template>
    </Select>

    <!-- Error message -->
    <div v-if="error" class="mt-1 text-sm text-red-600 flex items-center">
      <i class="pi pi-exclamation-triangle mr-1"></i>
      {{ error }}
    </div>

    <!-- Quick stats for selected entity -->
    <div
      v-if="showStats && selectedEntity && statsFields.length > 0"
      class="mt-2 p-3 bg-gray-50 rounded-lg border border-gray-200"
    >
      <div class="grid grid-cols-2 gap-3 text-xs">
        <div v-for="field in statsFields" :key="field.key">
          <span class="text-gray-500">{{ field.label }}:</span>
          <span class="font-medium ml-1">
            <template v-if="field.type === 'currency'">
              <BalanceDisplay
                :amount="selectedEntity[field.key] || 0"
                :currency="selectedEntity[currencyField] || defaultCurrency"
                size="xs"
                variant="inline"
              />
            </template>
            <template v-else>
              {{ selectedEntity[field.key] || 0 }}
            </template>
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import StatusBadge from '@/Components/StatusBadge.vue'
import BalanceDisplay from '@/Components/BalanceDisplay.vue'

interface Entity {
  [key: string]: any
}

interface StatsField {
  key: string
  label: string
  type?: 'number' | 'currency'
}

interface Props {
  modelValue?: number | string | null
  entities: Entity[]
  entityType?: 'customer' | 'company' | 'user' | 'vendor' | 'custom'
  optionLabel?: string
  optionValue?: string
  optionDisabled?: (entity: Entity) => boolean
  placeholder?: string
  filterPlaceholder?: string
  filterFields?: string[]
  showClear?: boolean
  disabled?: boolean
  loading?: boolean
  error?: string
  
  // Display options
  avatarField?: string
  avatarTypeField?: string
  iconField?: string
  defaultIcon?: string
  statusField?: string
  showStatus?: boolean
  
  // Subtitle
  subtitleFields?: string[]
  showSubtitle?: boolean
  
  // Extra info
  extraInfoFields?: string[]
  showExtraInfo?: boolean
  
  // Balance display
  balanceField?: string
  currencyField?: string
  defaultCurrency?: string
  showBalance?: boolean
  
  // Badge display
  badgeField?: string
  badgeSeverity?: 'success' | 'info' | 'warn' | 'danger' | 'secondary'
  showBadge?: boolean
  
  // Actions
  showActions?: boolean
  actionIcon?: string
  actionTitle?: string
  
  // Header
  headerTitle?: string
  
  // Create options
  allowCreate?: boolean
  createButtonLabel?: string
  createFirstButtonLabel?: string
  
  // Empty state
  emptyIcon?: string
  entityNamePlural?: string
  
  // Stats
  statsFields?: StatsField[]
  showStats?: boolean
}

interface Emits {
  (e: 'update:modelValue', value: number | string | null): void
  (e: 'change', entity: Entity | null): void
  (e: 'filter', event: Event): void
  (e: 'show'): void
  (e: 'hide'): void
  (e: 'create-entity'): void
  (e: 'view-entity', entity: Entity): void
}

const props = withDefaults(defineProps<Props>(), {
  entityType: 'custom',
  optionLabel: 'name',
  optionValue: 'id',
  placeholder: 'Select an item...',
  filterPlaceholder: 'Search items...',
  filterFields: () => ['name', 'email', 'code'],
  showClear: true,
  disabled: false,
  loading: false,
  showStatus: true,
  showSubtitle: true,
  showExtraInfo: false,
  showBalance: true,
  showBadge: false,
  showActions: true,
  defaultIcon: 'pi pi-user',
  actionIcon: 'pi pi-external-link',
  actionTitle: 'View details',
  headerTitle: 'Select Item',
  allowCreate: true,
  createButtonLabel: 'New Item',
  createFirstButtonLabel: 'Create Your First Item',
  emptyIcon: 'pi pi-search',
  badgeSeverity: 'secondary',
  defaultCurrency: 'USD',
  showStats: false,
  currencyField: 'currency_code'
})

const emit = defineEmits<Emits>()

const selectRef = ref()
const currentFilter = ref('')

const selectedEntityId = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const selectedEntity = computed(() => {
  if (!selectedEntityId.value) return null
  
  return props.entities.find(entity => {
    const entityId = entity[props.optionValue]
    return entityId === selectedEntityId.value
  }) || null
})

// Default configurations for different entity types
const entityConfigs = {
  customer: {
    subtitleFields: ['email', 'phone'],
    extraInfoFields: ['customer_type'],
    statsFields: [
      { key: 'invoice_count', label: 'Invoices', type: 'number' },
      { key: 'outstanding_balance', label: 'Outstanding', type: 'currency' }
    ],
    emptyIcon: 'pi pi-users',
    entityNamePlural: 'customers',
    headerTitle: 'Select Customer',
    createButtonLabel: 'New Customer',
    createFirstButtonLabel: 'Create Your First Customer'
  },
  company: {
    subtitleFields: ['email', 'phone'],
    extraInfoFields: ['industry', 'website'],
    statsFields: [
      { key: 'employee_count', label: 'Employees', type: 'number' },
      { key: 'annual_revenue', label: 'Revenue', type: 'currency' }
    ],
    emptyIcon: 'pi pi-building',
    entityNamePlural: 'companies',
    headerTitle: 'Select Company',
    createButtonLabel: 'New Company',
    createFirstButtonLabel: 'Create Your First Company'
  },
  user: {
    subtitleFields: ['email', 'role'],
    extraInfoFields: ['department'],
    statsFields: [
      { key: 'task_count', label: 'Tasks', type: 'number' },
      { key: 'project_count', label: 'Projects', type: 'number' }
    ],
    emptyIcon: 'pi pi-users',
    entityNamePlural: 'users',
    headerTitle: 'Select User',
    createButtonLabel: 'New User',
    createFirstButtonLabel: 'Create Your First User'
  },
  vendor: {
    subtitleFields: ['email', 'phone'],
    extraInfoFields: ['category', 'website'],
    statsFields: [
      { key: 'bill_count', label: 'Bills', type: 'number' },
      { key: 'total_paid', label: 'Total Paid', type: 'currency' }
    ],
    emptyIcon: 'pi pi-truck',
    entityNamePlural: 'vendors',
    headerTitle: 'Select Vendor',
    createButtonLabel: 'New Vendor',
    createFirstButtonLabel: 'Create Your First Vendor'
  }
}

// Get configuration based on entity type
const config = computed(() => {
  return props.entityType !== 'custom' 
    ? entityConfigs[props.entityType] 
    : {
        subtitleFields: props.subtitleFields || [],
        extraInfoFields: props.extraInfoFields || [],
        statsFields: props.statsFields || [],
        emptyIcon: props.emptyIcon,
        entityNamePlural: props.entityNamePlural || 'items',
        headerTitle: props.headerTitle,
        createButtonLabel: props.createButtonLabel,
        createFirstButtonLabel: props.createFirstButtonLabel
      }
})

// Get initials from name
const getInitials = (name?: string) => {
  if (!name) return ''
  return name
    .split(' ')
    .map(word => word.charAt(0))
    .join('')
    .substring(0, 2)
    .toUpperCase()
}

// Generate consistent color for avatar
const getAvatarColor = (name?: string) => {
  if (!name) return '#6B7280'
  
  const colors = [
    '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
    '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6366F1'
  ]
  
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  
  return colors[Math.abs(hash) % colors.length]
}

const onChange = (event: any) => {
  emit('change', selectedEntity.value)
}

const onFilter = (event: any) => {
  currentFilter.value = event.target?.value || ''
  emit('filter', event)
}

const onShow = () => {
  emit('show')
}

const onHide = () => {
  currentFilter.value = ''
  emit('hide')
}

const createEntity = () => {
  emit('create-entity')
}

const viewEntity = () => {
  if (selectedEntity.value) {
    emit('view-entity', selectedEntity.value)
  }
}

// Exposed methods
const show = () => {
  selectRef.value?.show()
}

const hide = () => {
  selectRef.value?.hide()
}

const focus = () => {
  nextTick(() => {
    selectRef.value?.$el?.querySelector('input')?.focus()
  })
}

defineExpose({
  show,
  hide,
  focus
})
</script>

<style scoped>
.entity-picker :deep(.p-select) {
  border-radius: 0.375rem;
}

.entity-picker :deep(.p-select-overlay) {
  max-height: 300px;
}

.entity-picker :deep(.p-select-option) {
  padding: 0;
}

.entity-picker :deep(.p-select-label) {
  padding: 0.5rem 0.75rem;
}
</style>