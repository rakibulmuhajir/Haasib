<script setup lang="ts">
/**
 * The command palette: type a sentence, fill its blanks, check the plain preview, press Enter.
 * State and flow live in usePalette; this is the dialog around it.
 */
import { router } from '@inertiajs/vue3'
import { computed, nextTick, ref, watch } from 'vue'
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { parseTemplate } from '@/palette/sentence'
import { showsSentence } from '@/palette/blanks'
import { usePalette } from '@/composables/palette/usePalette'
import { usePaletteVisibility } from '@/composables/usePaletteVisibility'
import PaletteCard from './PaletteCard.vue'
import PaletteFooter from './PaletteFooter.vue'
import PaletteList, { type PaletteListItem } from './PaletteList.vue'
import PaletteResult from './PaletteResult.vue'
import PaletteSentence from './PaletteSentence.vue'

const visibility = usePaletteVisibility()
const palette = usePalette()
const { text, blanks, suggestions, choices, choiceIndex, card, balances, notes, mode, status, message, result, findResults } = palette

// The shared flag drives the palette; the palette closing itself (Esc, after a visit) clears the flag.
watch(
  visibility.visible,
  (visible) => {
    if (visible) {
      palette.open()
      void nextTick(() => focusInput(true))
    } else palette.close()
  },
  { immediate: true },
)
watch(palette.isOpen, (open) => {
  if (!open && visibility.visible.value) visibility.close()
})

const plain = ref<{ $el: HTMLInputElement } | null>(null)
const sentence = ref<InstanceType<typeof PaletteSentence> | null>(null)

// Typing a line happens in the one input; the sentence (verb and chips) shows once the verb is followed by a space.
const sentenceShown = computed(() => showsSentence(blanks.value, text.value, mode.value === 'blank'))

/** The caret goes to the end only when the line input is entered, so mid-line edits keep their place. */
function focusInput(caretToEnd = false) {
  if (mode.value === 'blank' && blanks.value) sentence.value?.focus(blanks.value.current)
  else {
    const el = plain.value?.$el
    el?.focus()
    if (caretToEnd) el?.setSelectionRange(el.value.length, el.value.length)
  }
}

// The focus follows the mode: blank inputs in blank mode, the line input otherwise.
watch(mode, () => nextTick(() => focusInput(true)), { flush: 'post' })
watch(() => blanks.value?.current, () => nextTick(() => focusInput()), { flush: 'post' })

function onKeydown(e: KeyboardEvent) {
  // Enter on the Open / Undo links is theirs.
  if (e.key === 'Enter' && (e.target as HTMLElement | null)?.closest?.('button')) return
  palette.onKeydown(e)
  if (e.key === 'Tab' || e.key === 'Enter') void nextTick(focusInput)
}

function focusBlank(index: number) {
  if (blanks.value && blanks.value.current !== index) blanks.value = { ...blanks.value, current: index }
}

const WHO_LABEL: Record<string, string> = { pay: 'Paying', receive: 'Receiving' }
const whoLabel = computed(() => WHO_LABEL[blanks.value?.command.name ?? ''] ?? 'Who')

// ---- list ---------------------------------------------------------------

/** What follows the verb: "Bank charge {amount} {account?}" -> "amount account". */
const hint = (sentence: string) => {
  const tokens = parseTemplate(sentence)
  const first = tokens.findIndex((t) => t.type === 'slot')
  return first < 0 ? '' : tokens.slice(first).map((t) => (t.type === 'slot' ? t.name : t.text)).join(' ')
}

const items = computed<PaletteListItem[]>(() => {
  if (choices.value.length > 0) return choices.value.map((c) => ({ label: c.label, meta: c.meta }))
  if (blanks.value && sentenceShown.value) return []
  if (findResults.value.length > 0) return findResults.value.map((r) => ({ label: r.label, meta: r.meta, group: r.group }))
  return suggestions.value.map((s) => ({ label: s.label, meta: hint(s.sentence) }))
})

const listHeading = computed(() => {
  const state = blanks.value?.blanks.find((b) => b.state?.state === 'choices')?.state
  return choices.value.length > 0 && state?.state === 'choices' ? state.prompt : undefined
})

function choose(index: number) {
  choiceIndex.value = index
  palette.onKeydown(new KeyboardEvent('keydown', { key: 'Enter', cancelable: true }))
  void nextTick(focusInput)
}

// ---- footer -------------------------------------------------------------

const required = computed(() => (blanks.value?.blanks ?? []).filter((b) => !b.spec.optional))
const notFound = computed(() => (blanks.value?.blanks ?? []).some((b) => b.state?.state === 'not_found'))

const footerStatus = computed(() => {
  if (status.value === 'posting') return 'Posting…'
  if (status.value === 'ready') return 'Ready'
  if (message.value && (status.value !== 'working' || notFound.value)) return message.value
  if (!blanks.value || !sentenceShown.value) return ''
  const done = required.value.filter((b) => b.state?.state === 'resolved').length
  return `${done} of ${required.value.length}`
})

const footerCritical = computed(() => status.value === 'missing' || notFound.value)

const enterLabel = computed(() => {
  if (choices.value.length > 0) return 'pick'
  const command = blanks.value?.command
  if (status.value === 'ready') return command?.query || command?.form_url ? 'open' : 'post'
  return 'next'
})

// ---- result -------------------------------------------------------------

function openResult() {
  const url = result.value?.openUrl
  if (!url) return
  palette.close()
  router.visit(url)
}
</script>

<template>
  <Dialog :open="visibility.visible.value" @update:open="(open: boolean) => !open && visibility.close()">
    <DialogContent
      class="top-[20%] max-w-[600px] translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-[600px] [&>button]:hidden"
      @keydown="onKeydown"
      @escape-key-down.prevent
      @open-auto-focus.prevent="focusInput(true)"
    >
      <DialogTitle class="sr-only">Command palette</DialogTitle>
      <DialogDescription class="sr-only">Type a command or search</DialogDescription>

      <div class="px-4 py-3">
        <Input
          v-if="mode === 'line'"
          ref="plain"
          v-model="text"
          placeholder="Type a command or search…"
          aria-label="Command"
          autocomplete="off"
          spellcheck="false"
          class="h-8 border-0 bg-transparent px-0 text-sm shadow-none focus-visible:ring-0 md:text-sm"
        />
        <PaletteSentence
          v-if="blanks && sentenceShown"
          ref="sentence"
          :set="blanks"
          :editing="mode === 'blank'"
          :class="mode === 'line' && 'mt-2'"
          @edit="palette.editBlank"
          @focus-blank="focusBlank"
          @pick-blank="palette.enterBlank"
        />
      </div>

      <PaletteList
        :items="items"
        :index="choiceIndex"
        :heading="listHeading"
        @hover="choiceIndex = $event"
        @choose="choose"
      />

      <PaletteCard
        v-if="card && blanks && sentenceShown && choices.length === 0"
        :card="card"
        :balances="balances"
        :who-label="whoLabel"
        :notes="notes"
      />

      <PaletteResult
        v-if="result && !blanks"
        :message="result.message"
        :can-open="Boolean(result.openUrl)"
        :can-undo="result.canUndo"
        :undone="result.undone"
        @open="openResult"
        @undo="palette.undo()"
      />

      <PaletteFooter :enter-label="enterLabel" :status="footerStatus" :critical="footerCritical" />
    </DialogContent>
  </Dialog>
</template>
