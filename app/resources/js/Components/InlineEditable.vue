<template>
  <div class="inline-editable">
    <label v-if="label" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
      {{ label }}
    </label>

    <!-- READ MODE -->
    <div v-if="!isEditing" class="flex items-center gap-2 group">
      <slot name="display">
        <div 
          class="flex-1 cursor-pointer" 
          :class="{
            'hover:bg-gray-50 dark:hover:bg-gray-800 p-2 rounded': displayValue === '',
            'group-hover:bg-gray-50 dark:group-hover:bg-gray-800 p-2 rounded': displayValue !== ''
          }" 
          @click="startEditing" 
          tabindex="0" 
          @keydown.enter.prevent="startEditing" 
          role="button" 
          :aria-label="displayValue === '' ? `Add ${label || 'field'}` : `Edit ${label || 'field'}`"
        >
          <span v-if="displayValue !== ''">{{ displayValue }}</span>
          <span v-else class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">+ Click to add {{ label || 'field' }}</span>
        </div>
      </slot>

      <button
        v-if="displayValue !== ''"
        type="button"
        @click="startEditing"
        class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 opacity-0 group-hover:opacity-100 transition-opacity"
        :aria-label="`Edit ${label || 'field'}`"
      >
        <i class="fas fa-pen text-xs mr-1" aria-hidden="true"></i> edit
      </button>
    </div>

    <!-- EDIT MODE -->
    <div v-else class="flex items-start gap-2">
      <div class="flex-1">
        <input
          v-if="type === 'text'"
          v-model="localValue"
          class="w-full p-2 border rounded"
          :placeholder="placeholder"
          ref="inputRef"
          @keyup.enter="onEnterKey"
          @keyup.esc="onEscapeKey"
          :aria-label="`Edit ${label || 'field'} input`"
        />
        <textarea
          v-else-if="type === 'textarea'"
          v-model="localValue"
          class="w-full p-2 border rounded"
          :placeholder="placeholder"
          ref="inputRef"
          @keyup.enter="onEnterKey"
          @keyup.esc="onEscapeKey"
          :aria-label="`Edit ${label || 'field'} input`"
        />
        <select
          v-else-if="type === 'select'"
          v-model="localValue"
          class="w-full p-2 border rounded"
          ref="inputRef"
          @keyup.enter="onEnterKey"
          @keyup.esc="onEscapeKey"
          :aria-label="`Edit ${label || 'field'} input`"
        >
          <option v-for="option in options" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <div v-if="error" class="text-xs text-red-600 mt-1" role="alert">{{ error }}</div>
      </div>

      <div class="flex-shrink-0 flex items-center gap-2 mt-1 edit-buttons">
        <button
          type="button"
          class="text-xs text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300"
          @click="onSave"
          :disabled="saving"
          :aria-disabled="saving"
          :aria-label="`Save ${label || 'field'}`"
        >
          <i class="fas fa-check text-xs mr-1" aria-hidden="true"></i>
          <span v-if="saving">saving…</span>
          <span v-else>save</span>
        </button>

        <button
          type="button"
          class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
          @click="onCancel"
          :disabled="saving"
          :aria-label="`Cancel editing ${label || 'field'}`"
        >
          <i class="fas fa-times text-xs mr-1" aria-hidden="true"></i> cancel
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed, nextTick } from 'vue'
import type { PropType } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number, Object] as PropType<any>, default: '' },
  label: { type: String, default: '' },
  type: { type: String as PropType<'text'|'textarea'|'select'>, default: 'text' },
  options: { type: Array as PropType<Array<{ label: string; value: any }>>, default: () => [] },
  placeholder: { type: String, default: '' },
  saving: { type: Boolean, default: false },
  editing: { type: Boolean, default: false },
  validate: { type: Function as PropType<(val: any) => string | null>, default: null }
})

const emit = defineEmits(['update:modelValue', 'save', 'cancel', 'update:editing'])

const isEditing = ref(false)
const localValue = ref(props.modelValue)
const error = ref<string | null>(null)
const inputRef = ref<HTMLElement | null>(null)

watch(() => props.modelValue, (v) => {
  localValue.value = v
})

// Watch the editing prop to control edit mode from parent
watch(() => props.editing, (newValue) => {
  isEditing.value = newValue
  if (newValue) {
    localValue.value = props.modelValue
    nextTick(() => {
      if (inputRef.value && 'focus' in inputRef.value) {
        (inputRef.value as HTMLElement).focus()
      }
    })
  }
})


const displayValue = computed(() => {
  if (props.type === 'select') {
    const found = props.options.find(o => o.value === props.modelValue)
    return found ? found.label : ''
  }
  return props.modelValue ?? ''
})

const startEditing = async () => {
  emit('update:editing', true)
}

const onSave = async () => {
  error.value = props.validate ? props.validate(localValue.value) : null
  if (error.value) return
  emit('save', localValue.value)
  // Don't close editing here - let parent handle it after successful save
}

const onCancel = () => {
  emit('update:editing', false)
  localValue.value = props.modelValue
  error.value = null
  emit('cancel')
}

const onEnterKey = () => {
  if (props.type !== 'textarea') {
    onSave()
  }
}

const onEscapeKey = () => {
  onCancel()
}
</script>

<style scoped>
.inline-editable input,
.inline-editable textarea,
.inline-editable select {
  background: transparent;
  border: 1px solid #e5e7eb;
  padding: 0.375rem 0.5rem;
  border-radius: 0.375rem;
}
.inline-editable textarea {
  min-height: 3rem;
}

/* Ensure smooth transitions for opacity changes */
.group button {
  transition: opacity 0.2s ease-in-out;
}

/* Ensure edit buttons are always visible in edit mode */
.inline-editable .edit-buttons {
  opacity: 1 !important;
  visibility: visible !important;
}
</style>
