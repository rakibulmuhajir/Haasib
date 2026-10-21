<script setup lang="ts">
/**
 * The typed command as a sentence: the verb, then a blank for each slot with the template's own
 * anchor words between them. Editing a blank rewrites the whole line, so the matcher stays the
 * only thing that decides which text belongs to which blank.
 */
import { reactive, watch } from 'vue'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import { parseTemplate } from '@/palette/v2/sentence'
import type { Blank, BlankSet } from '@/palette/v2/blanks'

const props = defineProps<{ set: BlankSet }>()
const emit = defineEmits<{ 'update:text': [line: string]; 'focus-blank': [index: number] }>()

const tokens = () => parseTemplate(props.set.command.sentence)
const byName = (name: string) => props.set.blanks.findIndex((b) => b.name === name)

// What the user has typed in each blank, kept as typed: the matcher trims, and a name has spaces in it.
const local = reactive<Record<string, string>>({})
watch(
  () => props.set.blanks.map((b) => b.text),
  () => {
    for (const b of props.set.blanks) if ((local[b.name] ?? '').trim() !== b.text) local[b.name] = b.text
  },
  { immediate: true },
)

const RELATIVE = /^(today|yesterday|tomorrow)$/i

/** The line these blanks spell, leaving out the anchor words of a blank with nothing in it. */
function line(): string {
  const out: string[] = []
  let anchors: string[] = []
  let seenSlot = false
  for (const t of tokens()) {
    if (t.type === 'word') {
      if (seenSlot) anchors.push(t.text)
      else out.push(t.text)
      continue
    }
    seenSlot = true
    const value = (local[t.name] ?? '').trim()
    const kind = props.set.blanks[byName(t.name)]?.spec.kind
    const bare = kind === 'date' && RELATIVE.test(value)
    if (value !== '') out.push(...(bare ? [] : anchors), value)
    anchors = []
  }
  return out.join(' ')
}

function edit(blank: Blank, value: string | number) {
  local[blank.name] = String(value)
  emit('update:text', line())
}

type BlankKind = 'missing' | 'current' | 'filled' | 'empty'

function kindOf(index: number): BlankKind {
  const blank = props.set.blanks[index]
  const unresolved = !blank.spec.optional && blank.state?.state !== 'resolved'
  if (blank.state?.state === 'not_found' || (props.set.flagMissing && unresolved)) return 'missing'
  if (index === props.set.current) return 'current'
  return blank.text !== '' ? 'filled' : 'empty'
}

const KIND_CLASS: Record<BlankKind, string> = {
  empty: 'border-dashed border-border bg-transparent',
  current: 'border-solid border-ring ring-2 ring-ring/40 bg-surface-raised',
  filled: 'border-solid border-transparent bg-surface-band',
  missing: 'border-solid border-status-critical focus-visible:border-status-critical bg-transparent',
}

function width(blank: Blank): string {
  const chars = Math.max((local[blank.name] ?? '').length, blank.name.length, 3)
  return `${chars + 3}ch`
}

const inputs: Record<number, { $el: HTMLInputElement } | null> = {}
function setRef(index: number, el: unknown) {
  inputs[index] = (el as { $el: HTMLInputElement } | null) ?? null
}

function focus(index: number) {
  const el = inputs[index]?.$el
  if (!el) return
  el.focus()
  el.setSelectionRange(el.value.length, el.value.length)
}

defineExpose({ focus })
</script>

<template>
  <div class="flex flex-wrap items-center gap-2 text-sm text-text-primary" data-testid="palette-sentence">
    <template v-for="(t, n) in tokens()" :key="n">
      <span v-if="t.type === 'word'" :class="n === 0 ? 'font-semibold' : 'text-text-secondary'">{{ t.text }}</span>
      <Input
        v-else-if="byName(t.name) >= 0"
        :ref="(el: unknown) => setRef(byName(t.name), el)"
        :model-value="local[t.name] ?? ''"
        :placeholder="t.name.replace(/_/g, ' ')"
        :aria-label="t.name.replace(/_/g, ' ')"
        :aria-invalid="kindOf(byName(t.name)) === 'missing' || undefined"
        autocomplete="off"
        spellcheck="false"
        :style="{ width: width(set.blanks[byName(t.name)]) }"
        :class="cn(
          'h-7 min-w-0 shrink-0 px-2 py-0 text-sm shadow-none md:text-sm',
          ['amount', 'date'].includes(set.blanks[byName(t.name)].spec.kind) && 'font-mono',
          KIND_CLASS[kindOf(byName(t.name))],
        )"
        @update:model-value="edit(set.blanks[byName(t.name)], $event)"
        @focus="emit('focus-blank', byName(t.name))"
      />
    </template>
  </div>
</template>
