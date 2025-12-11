<script setup lang="ts">
import { computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Label } from '@/components/ui/label'
import { Pencil, Check, X, Loader2 } from 'lucide-vue-next'

export interface SelectOption {
  value: string | number
  label: string
}

const props = withDefaults(
  defineProps<{
    /** The field label */
    label: string
    /** Current display value (when not editing) */
    displayValue?: string
    /** Whether the field is currently being edited */
    editing: boolean
    /** Whether the field is currently saving */
    saving: boolean
    /** Whether editing is allowed */
    canEdit?: boolean
    /** Input type: 'text', 'email', 'number', 'select' */
    type?: 'text' | 'email' | 'number' | 'select' | 'textarea'
    /** Options for select type */
    options?: SelectOption[]
    /** Placeholder text for input */
    placeholder?: string
    /** Helper text shown below the value */
    helperText?: string
    /** Icon component to show before the value */
    icon?: object
    /** Whether the field is read-only (shows without edit button) */
    readonly?: boolean
    /** Additional class for the value display */
    valueClass?: string
  }>(),
  {
    canEdit: true,
    type: 'text',
    readonly: false,
    valueClass: '',
  }
)

const emit = defineEmits<{
  startEdit: []
  save: []
  cancel: []
}>()

const model = defineModel<string | number>()

const displayText = computed(() => {
  if (props.displayValue !== undefined) {
    return props.displayValue
  }
  if (props.type === 'select' && props.options) {
    const option = props.options.find((o) => o.value === model.value)
    return option?.label || String(model.value || '—')
  }
  return String(model.value || '—')
})

const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Enter' && props.type !== 'textarea') {
    emit('save')
  } else if (e.key === 'Escape') {
    emit('cancel')
  }
}
</script>

<template>
  <div class="space-y-1.5">
    <!-- Label row with edit button -->
    <div class="flex items-center justify-between">
      <Label class="text-sm font-medium text-zinc-500">{{ label }}</Label>
      <Button
        v-if="canEdit && !readonly && !editing"
        variant="ghost"
        size="sm"
        class="h-6 px-2"
        @click="emit('startEdit')"
      >
        <Pencil class="h-3 w-3" />
      </Button>
    </div>

    <!-- Editing mode -->
    <div v-if="editing" class="flex items-center gap-2">
      <!-- Text/Email/Number Input -->
      <Input
        v-if="type === 'text' || type === 'email' || type === 'number'"
        v-model="model"
        :type="type"
        :placeholder="placeholder"
        class="h-8"
        @keydown="handleKeydown"
      />

      <!-- Select -->
      <Select
        v-else-if="type === 'select'"
        v-model="model"
        @update:modelValue="(v) => (model = typeof options?.[0]?.value === 'number' ? Number(v) : v)"
      >
        <SelectTrigger class="h-8 w-full">
          <SelectValue :placeholder="placeholder" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="opt in options" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </SelectItem>
        </SelectContent>
      </Select>

      <!-- Textarea -->
      <textarea
        v-else-if="type === 'textarea'"
        v-model="model"
        :placeholder="placeholder"
        class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
        @keydown="handleKeydown"
      />

      <!-- Save button -->
      <Button
        size="sm"
        variant="ghost"
        class="h-8 w-8 p-0 shrink-0"
        :disabled="saving"
        @click="emit('save')"
      >
        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
        <Check v-else class="h-4 w-4 text-green-600" />
      </Button>

      <!-- Cancel button -->
      <Button
        size="sm"
        variant="ghost"
        class="h-8 w-8 p-0 shrink-0"
        :disabled="saving"
        @click="emit('cancel')"
      >
        <X class="h-4 w-4 text-red-600" />
      </Button>
    </div>

    <!-- Display mode -->
    <div v-else :class="['text-base text-zinc-900', valueClass]">
      <div class="flex items-center gap-2">
        <component :is="icon" v-if="icon" class="h-4 w-4 text-zinc-400" />
        <span>{{ displayText }}</span>
      </div>
    </div>

    <!-- Helper text -->
    <p v-if="helperText" class="text-xs text-zinc-400">{{ helperText }}</p>
  </div>
</template>
