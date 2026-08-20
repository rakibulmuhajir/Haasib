<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Search, X } from 'lucide-vue-next'

interface Props {
  modelValue: string
  placeholder?: string
  clearable?: boolean
  loading?: boolean
  /** Debounce delay in milliseconds (0 = no debounce) */
  debounceMs?: number
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Search...',
  clearable: true,
  loading: false,
  debounceMs: 0,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'clear': []
  'search': [value: string]
}>()

const inputRef = ref<HTMLInputElement>()
const isFocused = ref(false)
let debounceTimer: ReturnType<typeof setTimeout> | null = null

const hasValue = computed(() => props.modelValue.length > 0)

const handleInput = (event: Event) => {
  const value = (event.target as HTMLInputElement).value
  emit('update:modelValue', value)
  
  // Clear existing timer
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
  
  // Emit search with optional debounce
  if (props.debounceMs > 0) {
    debounceTimer = setTimeout(() => {
      emit('search', value)
    }, props.debounceMs)
  } else {
    emit('search', value)
  }
}

const handleClear = () => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
  emit('update:modelValue', '')
  emit('clear')
  emit('search', '')
  inputRef.value?.focus()
}

const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape' && hasValue.value) {
    handleClear()
  }
}
</script>

<template>
  <div
    class="group relative"
    :class="{ 'z-10': isFocused }"
  >
    <!-- Search Icon -->
    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
      <Search
        class="h-[18px] w-[18px] transition-colors"
        :class="[
          loading ? 'animate-pulse text-primary' : '',
          isFocused ? 'text-primary' : 'text-text-tertiary group-hover:text-text-secondary',
        ]"
      />
    </div>

    <!-- Input. No shadow and no soft radius: this is a field on a page, not a
         surface that floats above one. -->
    <input
      ref="inputRef"
      type="text"
      :value="modelValue"
      :placeholder="placeholder"
      class="block w-full rounded-md border border-rule-default bg-surface-raised py-2.5 pl-10 pr-10
             text-sm text-text-primary placeholder:text-text-tertiary
             transition-colors duration-200
             hover:border-rule-emphasis
             focus:border-primary focus:outline-none focus:ring-2 focus:ring-focus-ring/40"
      :class="{ 'pr-10': clearable && hasValue }"
      @input="handleInput"
      @focus="isFocused = true"
      @blur="isFocused = false"
      @keydown="handleKeydown"
    >

    <!-- Clear Button -->
    <button
      v-if="clearable && hasValue"
      type="button"
      class="absolute inset-y-0 right-0 flex items-center pr-3
             text-text-tertiary transition-colors hover:text-text-primary"
      @click="handleClear"
    >
      <X class="h-4 w-4" />
      <span class="sr-only">Clear search</span>
    </button>

    <!-- Loading Indicator -->
    <div
      v-if="loading && !hasValue"
      class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3"
    >
      <div class="h-4 w-4 animate-spin rounded-full border-2 border-rule-default border-t-primary" />
    </div>
  </div>
</template>
