<template>
  <div class="base-select-wrapper">
    <!-- Label -->
    <BaseLabel
      v-if="label || $slots.label"
      :for="selectId"
      :required="required"
      :helpText="helpText"
      :class="labelClass"
    >
      <slot name="label">{{ label }}</slot>
    </BaseLabel>

    <!-- Select Container -->
    <div class="relative">
      <!-- Clear button (outside dropdown) -->
      <div v-if="showClear && value && !disabled" class="absolute right-8 top-1/2 -translate-y-1/2 z-10">
        <BaseButton
          icon="pi pi-times"
          variant="text"
          size="small"
          class="!p-1 hover:bg-gray-100"
          @click="clearValue"
        />
      </div>

      <!-- Main Select Component -->
      <component
        :is="selectComponent"
        ref="selectRef"
        v-model="internalValue"
        :options="options"
        :optionLabel="optionLabel"
        :optionValue="optionValue"
        :optionDisabled="optionDisabled"
        :optionGroupLabel="optionGroupLabel"
        :optionGroupChildren="optionGroupChildren"
        :placeholder="placeholder"
        :disabled="disabled"
        :loading="loading"
        :invalid="invalid"
        :filter="filter"
        :filterValue="filterValue"
        :filterFields="filterFields"
        :filterPlaceholder="filterPlaceholder"
        :filterLocale="filterLocale"
        :editable="editable"
        :resetFilterOnHide="resetFilterOnHide"
        :virtualScrollerOptions="virtualScrollerOptions"
        :autoOptionFocus="autoOptionFocus"
        :selectOnFocus="selectOnFocus"
        :filterMatchMode="filterMatchMode"
        :showClear="false" // We handle clear button separately
        :panelStyle="panelStyle"
        :panelClass="panelClass"
        :appendTo="appendTo"
        :loadingIcon="loadingIcon"
        :ariaLabel="ariaLabel"
        :ariaLabelledBy="ariaLabelledBy"
        :inputId="selectId"
        :inputClass="inputClasses"
        :dataKey="dataKey"
        :filterInputProps="filterInputProps"
        :clearIconProps="clearIconProps"
        v-bind="$attrs"
        @change="onChange"
        @focus="onFocus"
        @blur="onBlur"
        @before-show="onBeforeShow"
        @before-hide="onBeforeHide"
        @show="onShow"
        @hide="onHide"
        @filter="onFilter"
        @click="onClick"
      >
        <!-- Option Template -->
        <template v-if="$slots.option" #option="slotProps">
          <slot name="option" v-bind="slotProps" />
        </template>

        <!-- Value Template -->
        <template v-if="$slots.value" #value="slotProps">
          <slot name="value" v-bind="slotProps" />
        </template>

        <!-- Header Template -->
        <template v-if="$slots.header" #header>
          <slot name="header" />
        </template>

        <!-- Footer Template -->
        <template v-if="$slots.footer" #footer>
          <slot name="footer" />
        </template>

        <!-- Empty Filter Template -->
        <template v-if="$slots.emptyfilter" #emptyfilter="slotProps">
          <slot name="emptyfilter" v-bind="slotProps" />
        </template>

        <!-- Empty Template -->
        <template v-if="$slots.empty" #empty="slotProps">
          <slot name="empty" v-bind="slotProps" />
        </template>

        <!-- Group Template -->
        <template v-if="$slots.optiongroup" #optiongroup="slotProps">
          <slot name="optiongroup" v-bind="slotProps" />
        </template>

        <!-- Loading Template -->
        <template v-if="$slots.loading" #loading="slotProps">
          <slot name="loading" v-bind="slotProps" />
        </template>

        <!-- Content Template (for Select) -->
        <template v-if="$slots.content && selectComponent === Select" #content="slotProps">
          <slot name="content" v-bind="slotProps" />
        </template>

        <!-- Trigger Icon (for Select) -->
        <template v-if="$slots.triggericon && selectComponent === Select" #triggericon>
          <slot name="triggericon" />
        </template>

        <!-- Indicator (for SelectButton) -->
        <template v-if="$slots.indicator && selectComponent === SelectButton" #indicator="slotProps">
          <slot name="indicator" v-bind="slotProps" />
        </template>
      </component>
    </div>

    <!-- Helper Text -->
    <div v-if="helpText && !error" :class="helperTextClasses">
      {{ helpText }}
    </div>

    <!-- Selected Options Count (for multi-select) -->
    <div v-if="multiple && selectedCount > 0 && showSelectedCount" :class="selectedCountClasses">
      {{ selectedCountText }}
    </div>

    <!-- Error Message -->
    <BaseError
      v-if="error"
      :error="error"
      :errorId="errorId"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, nextTick } from 'vue'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import MultiSelect from 'primevue/multiselect'
import Listbox from 'primevue/listbox'
import BaseButton from './BaseButton.vue'
import BaseLabel from './BaseLabel.vue'
import BaseError from './BaseError.vue'

type SelectComponent = typeof Select | typeof SelectButton | typeof MultiSelect | typeof Listbox

interface Option {
  [key: string]: any
}

interface Props {
  modelValue?: any
  options?: Option[]
  optionLabel?: string
  optionValue?: string
  optionDisabled?: string | ((option: Option) => boolean)
  optionGroupLabel?: string
  optionGroupChildren?: string
  placeholder?: string
  disabled?: boolean
  loading?: boolean
  invalid?: boolean
  required?: boolean
  clearable?: boolean
  filter?: boolean
  filterValue?: string
  filterFields?: string[]
  filterPlaceholder?: string
  filterLocale?: string
  editable?: boolean
  resetFilterOnHide?: boolean
  virtualScrollerOptions?: any
  autoOptionFocus?: boolean
  selectOnFocus?: boolean
  filterMatchMode?: string
  panelStyle?: any
  panelClass?: string | object | any[]
  appendTo?: string
  loadingIcon?: string
  ariaLabel?: string
  ariaLabelledBy?: string
  inputId?: string
  inputClass?: string | object | any[]
  dataKey?: string
  filterInputProps?: any
  clearIconProps?: any
  multiple?: boolean
  showClear?: boolean
  showSelectedCount?: boolean
  selectedCountText?: string | ((count: number) => string)
  helpText?: string
  error?: string
  label?: string
  labelClass?: string | object | any[]
  helperTextClass?: string | object | any[]
  component?: 'select' | 'selectbutton' | 'multiselect' | 'listbox'
}

interface Emits {
  (e: 'update:modelValue', value: any): void
  (e: 'change', event: any): void
  (e: 'focus', event: Event): void
  (e: 'blur', event: Event): void
  (e: 'before-show', event: Event): void
  (e: 'before-hide', event: Event): void
  (e: 'show', event: Event): void
  (e: 'hide', event: Event): void
  (e: 'filter', event: any): void
  (e: 'click', event: Event): void
  (e: 'clear'): void
}

const props = withDefaults(defineProps<Props>(), {
  optionLabel: 'label',
  optionValue: 'value',
  resetFilterOnHide: true,
  autoOptionFocus: true,
  showClear: true,
  showSelectedCount: true,
  component: 'select',
  selectedCountText: (count: number) => `${count} item${count > 1 ? 's' : ''} selected`
})

const emit = defineEmits<Emits>()

const selectRef = ref()

// Determine which component to use
const selectComponent = computed<SelectComponent>(() => {
  switch (props.component) {
    case 'selectbutton':
      return SelectButton
    case 'multiselect':
      return MultiSelect
    case 'listbox':
      return Listbox
    default:
      return Select
  }
})

// Internal value
const internalValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

// Computed properties
const selectedCount = computed(() => {
  if (!props.multiple || !props.modelValue) return 0
  return Array.isArray(props.modelValue) ? props.modelValue.length : 1
})

const selectedCountText = computed(() => {
  if (typeof props.selectedCountText === 'function') {
    return props.selectedCountText(selectedCount.value)
  }
  return props.selectedCountText
})

const inputClasses = computed(() => {
  const classes = ['w-full', 'transition-colors', 'duration-200']
  
  if (props.inputClass) {
    if (typeof props.inputClass === 'string') {
      classes.push(props.inputClass)
    } else {
      Object.entries(props.inputClass).forEach(([key, value]) => {
        if (value) classes.push(key)
      })
    }
  }
  
  return classes
})

const helperTextClasses = computed(() => {
  return [
    'mt-1 text-sm text-gray-500',
    props.helperTextClass
  ]
})

const selectedCountClasses = computed(() => {
  return [
    'mt-1 text-xs text-gray-600',
    props.helperTextClass
  ]
})

// Generate IDs for accessibility
const selectId = computed(() => props.inputId || `select-${Math.random().toString(36).substr(2, 9)}`)
const errorId = computed(() => `${selectId.value}-error`)

// Event handlers
const onChange = (event: any) => {
  emit('change', event)
}

const onFocus = (event: Event) => {
  emit('focus', event)
}

const onBlur = (event: Event) => {
  emit('blur', event)
}

const onBeforeShow = (event: Event) => {
  emit('before-show', event)
}

const onBeforeHide = (event: Event) => {
  emit('before-hide', event)
}

const onShow = (event: Event) => {
  emit('show', event)
}

const onHide = (event: Event) => {
  emit('hide', event)
}

const onFilter = (event: any) => {
  emit('filter', event)
}

const onClick = (event: Event) => {
  emit('click', event)
}

const clearValue = () => {
  emit('update:modelValue', props.multiple ? [] : null)
  emit('clear')
}

// Exposed methods
const focus = () => {
  nextTick(() => {
    if (selectComponent.value === Select) {
      selectRef.value?.$el?.querySelector('input')?.focus()
    } else {
      selectRef.value?.$el?.focus()
    }
  })
}

const blur = () => {
  nextTick(() => {
    if (selectComponent.value === Select) {
      selectRef.value?.$el?.querySelector('input')?.blur()
    } else {
      selectRef.value?.$el?.blur()
    }
  })
}

const show = () => {
  selectRef.value?.show?.()
}

const hide = () => {
  selectRef.value?.hide?.()
}

defineExpose({
  focus,
  blur,
  show,
  hide,
  $el: selectRef
})
</script>