<script setup lang="ts">
import { computed } from 'vue'

type Item = { value: string; label: string; meta?: any }

const props = defineProps<{
  items: Item[]
  selectedIndex: number
  loading?: boolean
  error?: string | null
  showPreview?: boolean
}>()

const emit = defineEmits<{ (e: 'select', item: Item): void }>()

const highlighted = computed(() => {
  if (!props.items || props.items.length === 0) return null
  return props.items[Math.min(props.selectedIndex, props.items.length - 1)]
})
</script>

<template>
  <div class="border border-gray-700/50 rounded-xl bg-gray-900/50 backdrop-blur-sm overflow-hidden">
    <div class="text-gray-500 text-xs px-4 py-2.5 mb-1 bg-gray-800/30 flex items-center gap-2">
      <slot name="header" />
    </div>

    <div v-if="loading" class="px-4 py-3 text-gray-500 text-xs">Loading…</div>
    <div v-else-if="error" class="px-4 py-3 text-red-300 text-xs">{{ error }}</div>

    <div v-else class="max-h-40 overflow-auto">
      <button type="button"
        v-for="(it, index) in items"
        :key="it.value + ':' + index"
        @click="emit('select', it)"
        class="w-full text-left px-4 py-2.5 hover:bg-gray-800/30 text-gray-300 border-t border-gray-800/50"
        :class="index === selectedIndex ? 'bg-gray-800/50' : ''"
      >
        <div class="font-medium">{{ it.label }}</div>
        <div v-if="it.meta && (it.meta.description || it.meta.sub)" class="text-xs text-gray-500">
          {{ it.meta.description || it.meta.sub }}
        </div>
      </button>
    </div>

    <div v-if="(showPreview ?? true) && highlighted" class="px-4 py-3 text-xs text-gray-400 border-t border-gray-800/50 bg-gray-800/20">
      <slot name="preview" :item="highlighted" />
    </div>
  </div>
</template>
