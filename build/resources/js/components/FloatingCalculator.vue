<script setup lang="ts">
/**
 * A floating adding machine: type an amount, Enter, repeat; the list stays visible so a
 * mistyped figure can be spotted, and "Use total" puts the sum into the number field that was
 * last focused on the page (dispatching an input event so v-model sees it). Nothing is saved.
 */
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { Calculator, X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

const open = ref(false)
const entry = ref('')
const amounts = ref<number[]>([])
const panel = ref<HTMLElement | null>(null)
const entryInput = ref<HTMLInputElement | null>(null)
const target = ref<HTMLInputElement | null>(null)

const total = computed(() => Math.round(amounts.value.reduce((s, a) => s + a, 0) * 100) / 100)
const fmt = (n: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(n)

// Remember the last number field focused outside this panel: that is where the total goes.
const onFocusIn = (e: FocusEvent) => {
  const el = e.target as HTMLElement | null
  if (el instanceof HTMLInputElement && el.type === 'number' && !panel.value?.contains(el)) target.value = el
}
onMounted(() => document.addEventListener('focusin', onFocusIn))
onUnmounted(() => document.removeEventListener('focusin', onFocusIn))

const add = () => {
  const value = Number(entry.value)
  if (entry.value.trim() !== '' && Number.isFinite(value) && value !== 0) amounts.value.push(value)
  entry.value = ''
}
const toggle = async () => {
  open.value = !open.value
  if (open.value) {
    await nextTick()
    entryInput.value?.focus()
  }
}
const useTotal = () => {
  const el = target.value
  if (!el || !document.body.contains(el)) return
  el.value = String(total.value)
  el.dispatchEvent(new Event('input', { bubbles: true }))
  el.focus()
}
const targetLabel = computed(() => target.value?.getAttribute('aria-label') || target.value?.id || 'the last field')
</script>

<template>
  <div class="fixed right-4 z-40" :style="{ bottom: 'calc(1rem + env(safe-area-inset-bottom, 0px))' }">
    <div v-if="open" ref="panel" class="mb-2 w-64 rounded-md border border-rule-default bg-background p-3 shadow-lg">
      <div class="mb-2 flex items-center justify-between">
        <span class="text-sm font-medium">Calculator</span>
        <button type="button" class="text-muted-foreground" aria-label="Close calculator" @click="open = false"><X class="h-4 w-4" /></button>
      </div>
      <input
        ref="entryInput"
        v-model="entry"
        type="text"
        inputmode="decimal"
        aria-label="Amount to add"
        placeholder="Amount, Enter"
        class="h-8 w-full rounded-md border border-input bg-background px-2 text-right tabular-nums"
        @keydown.enter.prevent="add"
      />
      <ol v-if="amounts.length" class="mt-2 max-h-48 space-y-0.5 overflow-y-auto text-sm tabular-nums">
        <li v-for="(a, i) in amounts" :key="i" class="flex items-center justify-between">
          <span class="text-muted-foreground">{{ i + 1 }}.</span>
          <span class="flex items-center gap-2">
            {{ fmt(a) }}
            <button type="button" class="text-muted-foreground" :aria-label="`Remove ${fmt(a)}`" @click="amounts.splice(i, 1)"><X class="h-3 w-3" /></button>
          </span>
        </li>
      </ol>
      <div class="mt-2 flex justify-between border-t pt-2 font-semibold tabular-nums">
        <span>{{ amounts.length }} · Total</span>
        <span>{{ fmt(total) }}</span>
      </div>
      <div class="mt-2 flex gap-2">
        <Button size="sm" class="flex-1" :disabled="!target || !amounts.length" :title="`Into ${targetLabel}`" @click="useTotal">Use total</Button>
        <Button size="sm" variant="outline" :disabled="!amounts.length" @click="amounts = []">Clear</Button>
      </div>
    </div>
    <Button size="icon" class="ml-auto flex h-10 w-10 rounded-full shadow-md" aria-label="Calculator" @click="toggle">
      <Calculator class="h-5 w-5" />
    </Button>
  </div>
</template>
