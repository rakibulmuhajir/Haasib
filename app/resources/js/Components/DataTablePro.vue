<template>
  <DataTable
    :value="value"
    :loading="loading"
    :paginator="paginator"
    :rows="rows"
    :totalRecords="totalRecords"
    :lazy="lazy"
    :first="first"
    :sortField="sortField"
    :sortOrder="sortOrder"
    v-model:filters="internalFilters"
    :globalFilterFields="globalFilterFields"
    v-model:selection="internalSelection"
    :dataKey="dataKey"
    filterDisplay="menu"
    showFilterOperator
    showFilterMatchModes
    showClearButton
    showApplyButton
    :scrollable="virtualScroll"
    :virtualScrollerOptions="virtualScroll ? { itemSize: rowHeight } : undefined"
    :scrollHeight="virtualScroll ? scrollHeight : undefined"
    size="small"
    stripedRows
    responsiveLayout="scroll"
    class="w-full"
    @page="onPage"
    @sort="onSort"
    @filter="onFilter"
  >
    <template v-if="$slots.header" #header>
      <slot name="header" />
    </template>

    <Column v-if="showSelectionColumn && selectionMode"
            :selectionMode="selectionMode"
            headerStyle="width: 3rem"/>

    <Column
      v-for="col in columns"
      :key="col.field"
      :field="col.field"
      :header="col.header"
      :sortable="col.sortable ?? true"
      :style="col.style"
      :bodyClass="col.bodyClass"
      :headerClass="col.headerClass"
      :dataType="col.dataType || inferDataType(col)"
      :filterMatchMode="defaultMatchMode(col)"
      :filterMatchModeOptions="resolveMatchModeOptions(col)"
      :showFilterMenu="col.filterable !== false"
      :filterField="col.filterField || col.field"
      :showClearButton="true"
      :showApplyButton="true"
    >
      <template v-if="$slots[`cell-${col.field}`]" #body="slotProps">
        <slot :name="`cell-${col.field}`" v-bind="slotProps" />
      </template>

      <template v-if="col.filterable !== false" #filter="{ filterModel, filterCallback }">
        <!-- Custom BETWEEN for number: two inputs -->
        <template v-if="col.filter?.type === 'number' && (filterModel.matchMode === FilterMatchMode.BETWEEN || filterModel.matchMode === 'between')">
          <div class="flex gap-2">
            <InputNumber
              :modelValue="Array.isArray(filterModel.value) ? filterModel.value[0] : null"
              :placeholder="col.filter?.placeholder || 'Min'"
              class="w-full"
              @update:modelValue="(v:any) => { filterModel.value = [v, Array.isArray(filterModel.value) ? filterModel.value[1] : null]; filterCallback() }"
              @blur="filterCallback()"
            />
            <InputNumber
              :modelValue="Array.isArray(filterModel.value) ? filterModel.value[1] : null"
              :placeholder="col.filter?.placeholder || 'Max'"
              class="w-full"
              @update:modelValue="(v:any) => { filterModel.value = [Array.isArray(filterModel.value) ? filterModel.value[0] : null, v]; filterCallback() }"
              @blur="filterCallback()"
            />
          </div>
        </template>
        <!-- Date BETWEEN: range calendar -->
        <template v-else-if="col.filter?.type === 'date' && (filterModel.matchMode === FilterMatchMode.BETWEEN || filterModel.matchMode === 'between')">
          <Calendar
            selectionMode="range"
            :modelValue="filterModel.value"
            @update:modelValue="(v:any) => { filterModel.value = v; filterCallback() }"
            :dateFormat="col.filter?.dateFormat || 'yy-mm-dd'"
            :showIcon="true"
            class="w-full"
          />
        </template>
        <!-- Default editor based on type -->
        <component
          v-else
          :is="resolveFilterComponent(col)"
          v-model="filterModel.value"
          class="w-full"
          :options="col.filter?.options"
          :optionLabel="col.filter?.optionLabel || 'label'"
          :optionValue="col.filter?.optionValue || 'value'"
          :placeholder="col.filter?.placeholder || `Filter ${col.header}`"
          :dateFormat="col.filter?.dateFormat || 'yy-mm-dd'"
          :showClear="true"
          :useGrouping="false"
          :fluid="true"
          :mode="col.filter?.mode"
          @change="filterCallback()"
          @update:modelValue="filterCallback()"
        />
      </template>
    </Column>

    <template v-if="$slots.footer" #footer>
      <slot name="footer" />
    </template>
  </DataTable>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Calendar from 'primevue/calendar'
import Dropdown from 'primevue/dropdown'
import MultiSelect from 'primevue/multiselect'
import { FilterMatchMode, FilterOperator } from '@primevue/core/api'

type FilterType = 'text' | 'number' | 'date' | 'select' | 'multiselect'

interface ColumnFilterConfig {
  type?: FilterType
  options?: any[]
  optionLabel?: string
  optionValue?: string
  placeholder?: string
  dateFormat?: string
  mode?: string
  matchMode?: string
  modes?: string[]
}

interface ColumnDef {
  field: string
  header: string
  sortable?: boolean
  style?: string | Record<string, string>
  bodyClass?: string
  headerClass?: string
  filterable?: boolean
  filterField?: string
  filter?: ColumnFilterConfig
  dataType?: 'text' | 'numeric' | 'date'
}

const props = defineProps({
  value: { type: Array, required: true },
  columns: { type: Array as () => ColumnDef[], required: true },
  loading: { type: Boolean, default: false },
  paginator: { type: Boolean, default: true },
  rows: { type: Number, default: 15 },
  totalRecords: { type: Number, default: 0 },
  lazy: { type: Boolean, default: false },
  first: { type: Number, default: 0 },
  sortField: { type: String, default: '' },
  sortOrder: { type: Number as () => 1 | -1, default: -1 },
  filters: { type: Object as () => Record<string, any> | undefined, default: undefined },
  globalFilterFields: { type: Array as () => string[], default: () => [] },
  virtualScroll: { type: Boolean, default: false },
  scrollHeight: { type: String, default: '480px' },
  rowHeight: { type: Number, default: 40 },
  // Selection support
  selection: { type: [Object, Array] as () => any, default: undefined },
  selectionMode: { type: String as () => 'single' | 'multiple' | undefined, default: undefined },
  dataKey: { type: String, default: 'id' },
  showSelectionColumn: { type: Boolean, default: false },
})

const emit = defineEmits<{
  (e: 'update:filters', value: Record<string, any>): void
  (e: 'update:selection', value: any): void
  (e: 'page', event: any): void
  (e: 'sort', event: any): void
  (e: 'filter', event: any): void
}>()

const buildDefaultFilters = () => {
  const f: Record<string, any> = {
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
  }

  for (const col of props.columns) {
    if (col.filterable === false) continue
    // Sensible defaults: number -> GTE, date -> DATE_AFTER, text -> CONTAINS
    let mode = col.filter?.matchMode as any
    if (!mode) {
      if (col.filter?.type === 'number') mode = FilterMatchMode.GREATER_THAN_OR_EQUAL_TO
      else if (col.filter?.type === 'date') mode = FilterMatchMode.DATE_AFTER
      else mode = FilterMatchMode.CONTAINS
    }
    f[col.filterField || col.field] = {
      operator: FilterOperator.AND,
      constraints: [{ value: null, matchMode: mode }],
    }
  }
  return f
}

const internalFilters = ref<Record<string, any>>(props.filters || buildDefaultFilters())
const internalSelection = ref<any>(props.selection)

watch(
  () => props.filters,
  (val) => {
    if (val) internalFilters.value = val
  },
  { deep: true }
)

watch(
  internalFilters,
  (val) => emit('update:filters', val),
  { deep: true }
)

watch(
  () => props.selection,
  (val) => {
    internalSelection.value = val
  },
  { deep: true }
)

watch(
  internalSelection,
  (val) => emit('update:selection', val),
  { deep: true }
)

const resolveFilterComponent = (col: ColumnDef) => {
  const type = col.filter?.type || 'text'
  switch (type) {
    case 'number':
      return InputNumber
    case 'date':
      return Calendar
    case 'select':
      return Dropdown
    case 'multiselect':
      return MultiSelect
    default:
      return InputText
  }
}

const inferDataType = (col: ColumnDef): 'text' | 'numeric' | 'date' => {
  if (col.filter?.type === 'number') return 'numeric'
  if (col.filter?.type === 'date') return 'date'
  return 'text'
}

const resolveMatchModeOptions = (col: ColumnDef) => {
  if (col.filter?.modes && col.filter.modes.length) {
    // Allow passing either raw values or {label,value} objects
    return col.filter.modes
  }
  if (col.filter?.type === 'number') {
    return [
      { label: '≥', value: FilterMatchMode.GREATER_THAN_OR_EQUAL_TO },
      { label: '≤', value: FilterMatchMode.LESS_THAN_OR_EQUAL_TO },
      { label: '=', value: FilterMatchMode.EQUALS },
      { label: '<', value: FilterMatchMode.LESS_THAN },
      { label: '>', value: FilterMatchMode.GREATER_THAN },
      { label: 'Between', value: FilterMatchMode.BETWEEN },
    ]
  }
  if (col.filter?.type === 'select') {
    return [
      { label: 'Equals', value: FilterMatchMode.EQUALS },
    ]
  }
  if (col.filter?.type === 'multiselect') {
    return [
      { label: 'In', value: FilterMatchMode.IN },
    ]
  }
  if (col.filter?.type === 'date') {
    return [
      { label: 'After', value: FilterMatchMode.DATE_AFTER },
      { label: 'Before', value: FilterMatchMode.DATE_BEFORE },
      { label: 'On', value: FilterMatchMode.DATE_IS },
      { label: 'Between', value: FilterMatchMode.BETWEEN },
    ]
  }
  return [
    { label: 'Contains', value: FilterMatchMode.CONTAINS },
    { label: 'Starts with', value: FilterMatchMode.STARTS_WITH },
    { label: 'Equals', value: FilterMatchMode.EQUALS },
  ]
}

const defaultMatchMode = (col: ColumnDef) => {
  if (col.filter?.matchMode) return col.filter.matchMode
  if (col.filter?.type === 'number') return FilterMatchMode.GREATER_THAN_OR_EQUAL_TO
  if (col.filter?.type === 'date') return FilterMatchMode.DATE_AFTER
  if (col.filter?.type === 'select') return FilterMatchMode.EQUALS
  if (col.filter?.type === 'multiselect') return FilterMatchMode.IN
  return FilterMatchMode.CONTAINS
}

const onPage = (e: any) => emit('page', e)
const onSort = (e: any) => emit('sort', e)
const onFilter = (e: any) => emit('filter', { ...(e || {}), filters: internalFilters.value })
</script>

<style scoped>
/* Optional: tweak small row density */
:deep(.p-datatable .p-datatable-tbody > tr > td),
:deep(.p-datatable .p-datatable-thead > tr > th) {
  padding-top: 0.4rem;
  padding-bottom: 0.4rem;
}
</style>
