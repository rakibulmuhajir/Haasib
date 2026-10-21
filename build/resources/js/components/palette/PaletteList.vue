<script setup lang="ts">
/** One list for everything the palette offers: suggestions, a blank's choices, or find results. */
import { nextTick, ref, watch } from 'vue'
import { cn } from '@/lib/utils'

export interface PaletteListItem { label: string; meta?: string; group?: string }

const props = defineProps<{ items: PaletteListItem[]; index: number; heading?: string }>()
const emit = defineEmits<{ hover: [index: number]; choose: [index: number] }>()

const rows = ref<HTMLElement[]>([])

watch(
  () => props.index,
  () => nextTick(() => rows.value[props.index]?.scrollIntoView?.({ block: 'nearest' })),
)

const startsGroup = (i: number) => props.items[i].group !== undefined && props.items[i].group !== props.items[i - 1]?.group
</script>

<template>
  <div v-if="items.length > 0" role="listbox" class="max-h-[320px] overflow-y-auto border-t border-rule-subtle py-1">
    <p v-if="heading" class="px-4 pb-1 pt-2 text-xs text-text-metadata">{{ heading }}</p>
    <template v-for="(item, i) in items" :key="i">
      <p v-if="startsGroup(i)" class="px-4 pb-1 pt-2 font-mono text-[10px] uppercase tracking-wider text-text-metadata">
        {{ item.group }}
      </p>
      <div
        :ref="(el) => { if (el) rows[i] = el as HTMLElement }"
        role="option"
        :aria-selected="i === index"
        :class="cn(
          'flex cursor-pointer items-baseline justify-between gap-4 px-4 py-1.5 text-sm text-text-primary',
          i === index && 'bg-surface-band',
        )"
        @mouseenter="emit('hover', i)"
        @click="emit('choose', i)"
      >
        <span class="min-w-0 truncate">{{ item.label }}</span>
        <span v-if="item.meta" class="shrink-0 text-xs text-text-metadata">{{ item.meta }}</span>
      </div>
    </template>
  </div>
</template>
