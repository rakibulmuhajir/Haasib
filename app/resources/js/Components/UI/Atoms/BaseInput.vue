<template>
  <div class="base-input-wrapper">
    <!-- Label -->
    <BaseLabel
      v-if="label || $slots.label"
      :for="inputId"
      :required="required"
      :helpText="helpText"
      :class="labelClass"
    >
      <slot name="label">{{ label }}</slot>
    </BaseLabel>

    <!-- Input Container -->
    <div class="relative">
      <!-- Prefix -->
      <div v-if="$slots.prefix || prefix" :class="prefixClasses">
        <slot name="prefix">{{ prefix }}</slot>
      </div>

      <!-- Main Input -->
      <component
        :is="inputComponent"
        :id="inputId"
        ref="inputRef"
        v-model="internalValue"
        :class="inputClasses"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :invalid="invalid"
        :type="type"
        :autocomplete="autocomplete"
        :maxlength="maxlength"
        :minlength="minlength"
        :pattern="pattern"
        :inputmode="inputmode"
        :aria-describedby="ariaDescribedBy"
        v-bind="$attrs"
        @input="onInput"
        @change="onChange"
        @blur="onBlur"
        @focus="onFocus"
        @keydown="onKeydown"
        @keyup="onKeyup"
        @paste="onPaste"
      >
        <template v-if="$slots.default" #default>
          <slot />
        </template>
      </component>

      <!-- Suffix -->
      <div v-if="$slots.suffix || suffix || clearable" :class="suffixClasses">
        <slot name="suffix">
          <!-- Clear button -->
          <BaseButton
            v-if="clearable && value"
            icon="pi pi-times"
            variant="text"
            size="small"
            class="!p-1 hover:bg-gray-100"
            @click="clearValue"
            :disabled="disabled"
          />
          <span v-else-if="suffix">{{ suffix }}</span>
        </slot>
      </div>

      <!-- Icon -->
      <i v-if="icon && !$slots.icon" :class="iconClasses" />
      <slot v-else-if="$slots.icon" name="icon" />
    </div>

    <!-- Helper Text -->
    <div v-if="helperText && !error" :class="helperTextClasses">
      {{ helperText }}
    </div>

    <!-- Character Counter -->
    <div v-if="showCharCounter && maxlength" :class="charCounterClasses">
      {{ charCount }}/{{ maxlength }}
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
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Password from 'primevue/password'
import BaseButton from './BaseButton.vue'
import BaseLabel from './BaseLabel.vue'
import BaseError from './BaseError.vue'

type InputType = 'text' | 'password' | 'email' | 'number' | 'tel' | 'url' | 'search'
type InputComponent = typeof InputText | typeof Textarea | typeof Password

interface Props {
  modelValue?: string | number | null
  type?: InputType
  label?: string
  placeholder?: string
  disabled?: boolean
  readonly?: boolean
  invalid?: boolean
  required?: boolean
  clearable?: boolean
  multiline?: boolean
  rows?: number
  autoResize?: boolean
  prefix?: string
  suffix?: string
  icon?: string
  iconPosition?: 'left' | 'right'
  helpText?: string
  error?: string
  inputId?: string
  inputClass?: string | object | any[]
  labelClass?: string | object | any[]
  helperTextClass?: string | object | any[]
  errorClass?: string | object | any[]
  maxlength?: number
  minlength?: number
  showCharCounter?: boolean
  autocomplete?: string
  pattern?: string
  inputmode?: 'none' | 'text' | 'decimal' | 'numeric' | 'tel' | 'search' | 'email' | 'url'
  toggleMask?: boolean
  feedback?: boolean
  promptLabel?: string
  weakLabel?: string
  mediumLabel?: string
  strongLabel?: string
}

interface Emits {
  (e: 'update:modelValue', value: string | number | null): void
  (e: 'input', event: Event): void
  (e: 'change', event: Event): void
  (e: 'blur', event: Event): void
  (e: 'focus', event: Event): void
  (e: 'keydown', event: Event): void
  (e: 'keyup', event: Event): void
  (e: 'paste', event: Event): void
  (e: 'clear'): void
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  iconPosition: 'left',
  rows: 3,
  showCharCounter: false,
  promptLabel: 'Enter a password',
  weakLabel: 'Weak',
  mediumLabel: 'Medium',
  strongLabel: 'Strong'
})

const emit = defineEmits<Emits>()

const inputRef = ref()

// Determine which component to use
const inputComponent = computed<InputComponent>(() => {
  if (props.multiline) return Textarea
  if (props.type === 'password') return Password
  return InputText
})

// Internal value
const internalValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

// Computed properties
const charCount = computed(() => {
  return props.modelValue ? String(props.modelValue).length : 0
})

const inputClasses = computed(() => {
  const classes = ['w-full', 'transition-colors', 'duration-200']
  
  if (props.inputClass) {
    if (typeof props.inputClass === 'string') {
      classes.push(props.inputClass)
    } else {
      // Handle object/array classes
      Object.entries(props.inputClass).forEach(([key, value]) => {
        if (value) classes.push(key)
      })
    }
  }
  
  // Add icon padding
  if (props.icon || props.$slots.icon) {
    classes.push(props.iconPosition === 'left' ? 'pl-10' : 'pr-10')
  }
  
  // Add prefix/suffix padding
  if (props.prefix || props.$slots.prefix) {
    classes.push('pl-10')
  }
  if (props.suffix || props.$slots.suffix || props.clearable) {
    classes.push('pr-10')
  }
  
  return classes
})

const prefixClasses = computed(() => {
  return [
    'absolute left-3 top-1/2 -translate-y-1/2 text-gray-500',
    'pointer-events-none flex items-center',
    props.disabled && 'opacity-50'
  ]
})

const suffixClasses = computed(() => {
  return [
    'absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1',
    props.disabled && 'opacity-50'
  ]
})

const iconClasses = computed(() => {
  return [
    'absolute text-gray-400',
    props.iconPosition === 'left' ? 'left-3 top-1/2 -translate-y-1/2' : 'right-3 top-1/2 -translate-y-1/2',
    props.disabled && 'opacity-50'
  ]
})

const helperTextClasses = computed(() => {
  return [
    'mt-1 text-sm text-gray-500',
    props.helperTextClass
  ]
})

const charCounterClasses = computed(() => {
  return [
    'mt-1 text-xs text-gray-400 text-right',
    charCount.value > maxlength * 0.9 && 'text-orange-500',
    charCount.value >= maxlength && 'text-red-500'
  ]
})

// Generate IDs for accessibility
const inputId = computed(() => props.inputId || `input-${Math.random().toString(36).substr(2, 9)}`)
const errorId = computed(() => `${inputId.value}-error`)
const ariaDescribedBy = computed(() => {
  const ids = []
  if (props.helpText) ids.push(`${inputId.value}-help`)
  if (props.error) ids.push(errorId.value)
  return ids.length > 0 ? ids.join(' ') : undefined
})

// Event handlers
const onInput = (event: Event) => {
  emit('input', event)
}

const onChange = (event: Event) => {
  emit('change', event)
}

const onBlur = (event: Event) => {
  emit('blur', event)
}

const onFocus = (event: Event) => {
  emit('focus', event)
}

const onKeydown = (event: Event) => {
  emit('keydown', event)
}

const onKeyup = (event: Event) => {
  emit('keyup', event)
}

const onPaste = (event: Event) => {
  emit('paste', event)
}

const clearValue = () => {
  emit('update:modelValue', null)
  emit('clear')
  nextTick(() => {
    inputRef.value?.$el?.focus()
  })
}

// Exposed methods
const focus = () => {
  nextTick(() => {
    inputRef.value?.$el?.focus()
  })
}

const blur = () => {
  inputRef.value?.$el?.blur()
}

const select = () => {
  nextTick(() => {
    inputRef.value?.$el?.select()
  })
}

defineExpose({
  focus,
  blur,
  select,
  $el: inputRef
})
</script>