import { computed, onBeforeUnmount, ref, watch, type Ref } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  parseQuickEntry,
  quickEntryHint,
  resolveQuickEntry,
  type Lookups,
  type OpenBill,
  type Option,
  type Picks,
  type QuickDefaults,
  type QuickEntry,
  type ReadyEntry,
} from '@/palette/quick-entry'

export interface PreviewLine {
  side: 'debit' | 'credit'
  account_code: string
  account_name: string
  amount: number
}

export interface PreviewBalance {
  account_name: string
  before: number
  after: number
}

export interface PostedResult {
  message: string
  openUrl: string | null
  undo: { key: string; params: Record<string, unknown> } | null
  undone: boolean
}

export type QuickPhase = 'idle' | 'working' | 'choices' | 'error' | 'preview'

interface Options {
  /** The palette's text input. */
  input: Ref<string>
  slug: () => string | undefined
  /** Adds a line to the palette's output log. */
  log: (type: 'input' | 'success' | 'error' | 'output', text: string) => void
  /** Records the typed line in the palette's history. */
  remember: (text: string) => void
  /** Called after a successful post: clear the input for the next command. */
  afterPost: () => void
  closePalette: () => void
}

const DEBOUNCE_MS = 300

function csrf(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

/**
 * State and flow for a typed one-line transaction: parse as the user types, debounce, resolve
 * names, ask the server for a preview, and post on Enter. Nothing is posted until the preview
 * for exactly the line on screen has been shown.
 */
export function useQuickEntry(opts: Options) {
  const entry = ref<QuickEntry | null>(null)
  const hint = ref<string | null>(null)
  const phase = ref<QuickPhase>('idle')
  const resolved = ref<ReadyEntry | null>(null)
  const preview = ref<{ lines: PreviewLine[]; balances: PreviewBalance[] } | null>(null)
  const problem = ref<string | null>(null)
  const choicePrompt = ref('')
  const choices = ref<Option[]>([])
  const choiceField = ref('')
  const choiceIndex = ref(0)
  const posting = ref(false)
  const lastResult = ref<PostedResult | null>(null)

  let picks: Picks = {}
  let timer: ReturnType<typeof setTimeout> | null = null
  let runId = 0
  let idempotencyKey = ''
  let defaultsCache: { slug: string; at: number; value: QuickDefaults } | null = null

  const active = computed(() => entry.value !== null)
  /** Enter would post: the preview on screen belongs to the line on screen. */
  const canPost = computed(() => phase.value === 'preview' && resolved.value !== null && !posting.value)

  function headers(extra: Record<string, string> = {}): Record<string, string> {
    const h: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrf(),
      ...extra,
    }
    const slug = opts.slug()
    if (slug) h['X-Company-Slug'] = slug
    return h
  }

  async function getJson(path: string): Promise<any> {
    const res = await fetch(path, { credentials: 'same-origin', headers: headers() })
    if (!res.ok) throw new Error(`${path} ${res.status}`)
    return res.json()
  }

  async function loadDefaults(): Promise<QuickDefaults> {
    const slug = opts.slug() ?? ''
    if (defaultsCache && defaultsCache.slug === slug && Date.now() - defaultsCache.at < 60_000) {
      return defaultsCache.value
    }
    const value = (await getJson('/api/palette/defaults')) as QuickDefaults
    defaultsCache = { slug, at: Date.now(), value }
    return value
  }

  async function lookups(): Promise<Lookups> {
    const defaults = await loadDefaults()
    return {
      defaults,
      search: async (entity, query) => {
        const data = await getJson(`/api/palette/flag-values?search_entity=${entity}&q=${encodeURIComponent(query)}`)
        return (data.values ?? []) as Option[]
      },
      openBills: async (vendorId) => {
        const data = await getJson(`/api/palette/open-bills?vendor_id=${encodeURIComponent(vendorId)}`)
        return (data.bills ?? []) as OpenBill[]
      },
    }
  }

  function clearOutcome() {
    resolved.value = null
    preview.value = null
    problem.value = null
    choices.value = []
    choiceIndex.value = 0
    choicePrompt.value = ''
  }

  function onInput(text: string) {
    if (timer) clearTimeout(timer)
    runId++
    picks = {}
    clearOutcome()
    entry.value = parseQuickEntry(text)
    hint.value = entry.value ? null : quickEntryHint(text)
    if (!entry.value) {
      phase.value = 'idle'
      return
    }
    phase.value = 'working'
    timer = setTimeout(run, DEBOUNCE_MS)
  }

  async function run() {
    const current = entry.value
    if (!current) return
    const id = ++runId
    phase.value = 'working'
    try {
      const result = await resolveQuickEntry(current, await lookups(), picks)
      if (id !== runId) return

      if (result.status === 'choices') {
        clearOutcome()
        choices.value = result.choices
        choicePrompt.value = result.prompt
        choiceField.value = result.field
        phase.value = 'choices'
        return
      }
      if (result.status === 'error') {
        clearOutcome()
        problem.value = result.message
        phase.value = 'error'
        return
      }

      resolved.value = result
      const res = await fetch('/api/palette/preview', {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers(),
        body: JSON.stringify({ key: result.key, params: result.params }),
      })
      const data = await res.json().catch(() => ({}))
      if (id !== runId) return

      if (data.ok) {
        preview.value = { lines: data.lines ?? [], balances: data.balances ?? [] }
        idempotencyKey = typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`
        phase.value = 'preview'
      } else {
        resolved.value = null
        problem.value = describeProblem(data)
        phase.value = 'error'
      }
    } catch {
      if (id !== runId) return
      clearOutcome()
      problem.value = "Couldn't check this. Try again."
      phase.value = 'error'
    }
  }

  function describeProblem(data: any): string {
    const message: string = data?.message || "Couldn't check this"
    const fields = Object.values((data?.errors ?? {}) as Record<string, string[]>).flat()
    const extra = fields.filter((m) => m !== message)
    return extra.length > 0 && data?.code === 'VALIDATION' ? [message, ...extra].join(' ') : message
  }

  function pickChoice(index: number) {
    const chosen = choices.value[index]
    if (!chosen) return
    picks = { ...picks, [choiceField.value]: chosen }
    if (timer) clearTimeout(timer)
    void run()
  }

  function moveChoice(delta: number) {
    if (phase.value !== 'choices' || choices.value.length === 0) return false
    choiceIndex.value = Math.max(0, Math.min(choices.value.length - 1, choiceIndex.value + delta))
    return true
  }

  /** Enter. True when the quick entry consumed the key. */
  function handleEnter(): boolean {
    if (!entry.value) return false
    if (posting.value) return true
    if (phase.value === 'choices') {
      pickChoice(choiceIndex.value)
      return true
    }
    if (phase.value === 'preview') {
      void post()
      return true
    }
    if (phase.value === 'working') {
      // Skip the wait; the preview still has to appear before anything is posted.
      if (timer) clearTimeout(timer)
      void run()
    }
    return true
  }

  /** What went wrong, in the server's words: the first field error for a refused entry, else its message. */
  function failureMessage(data: any, fallback: string): string {
    if (data?.code === 'VALIDATION') {
      const first = Object.values((data?.errors ?? {}) as Record<string, string[]>).flat()[0]
      if (first) return first
    }
    return data?.message || fallback
  }

  /** Where "Open" goes after an undo: the reversal entry, or the sale's invoice. */
  function undoUrlFor(key: string, data: any): string | null {
    const slug = opts.slug()
    if (!slug) return null
    if (key === 'sale.void') return data?.redirect ?? (data?.data?.id ? `/${slug}/invoices/${data.data.id}` : null)
    // Voiding a payment answers with the reversal's id; reversing a journal's own id is the reversal.
    const id = key.endsWith('.reverse') ? data?.data?.id : data?.data?.reversal_id
    return id ? `/${slug}/journals/${id}` : null
  }

  function openUrlFor(r: ReadyEntry, data: any): string | null {
    const slug = opts.slug()
    const id = data?.data?.id
    if (r.open === 'redirect') return data?.redirect ?? (data?.data?.invoice_id && slug ? `/${slug}/invoices/${data.data.invoice_id}` : null)
    if (!slug || !id) return null
    switch (r.open) {
      case 'payment': return `/${slug}/payments/${id}`
      case 'bill_payment': return `/${slug}/bill-payments/${id}`
      case 'journal': return `/${slug}/journals/${id}`
    }
    return null
  }

  async function post() {
    const r = resolved.value
    if (!r || posting.value) return
    const typed = opts.input.value.trim()
    const [entity, ...rest] = r.key.split('.')
    const verb = rest.join('.')

    posting.value = true
    opts.log('input', `❯ ${typed}`)
    opts.remember(typed)
    try {
      const res = await fetch('/api/palette/execute', {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers({ 'X-Action': r.key, 'X-Idempotency-Key': idempotencyKey }),
        body: JSON.stringify({ entity, verb, params: r.params }),
      })
      const data = await res.json().catch(() => ({}))

      if (data.ok) {
        const id = data?.data?.id
        lastResult.value = {
          message: data.message || 'Done',
          openUrl: openUrlFor(r, data),
          undo: r.undoKey && id ? { key: r.undoKey, params: { id } } : null,
          undone: false,
        }
        opts.log('success', `✓ ${lastResult.value.message}`)
        opts.afterPost()
      } else {
        opts.log('error', `✗ ${failureMessage(data, 'Could not post')}`)
        // The data may have changed under us (a date locked since the preview): look again.
        void run()
      }
    } catch (e) {
      opts.log('error', `✗ Network error: ${e instanceof Error ? e.message : 'Unknown'}`)
    } finally {
      posting.value = false
    }
  }

  function open() {
    const url = lastResult.value?.openUrl
    if (!url) return
    opts.closePalette()
    router.visit(url)
  }

  async function undo() {
    const result = lastResult.value
    if (!result?.undo || result.undone || posting.value) return
    const [entity, ...rest] = result.undo.key.split('.')
    posting.value = true
    try {
      const res = await fetch('/api/palette/execute', {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers({ 'X-Action': result.undo.key }),
        body: JSON.stringify({ entity, verb: rest.join('.'), params: result.undo.params }),
      })
      const data = await res.json().catch(() => ({}))
      if (data.ok) {
        result.undone = true
        result.message = data.message || 'Undone'
        result.openUrl = undoUrlFor(result.undo.key, data)
        opts.log('success', `✓ ${result.message}`)
      } else {
        opts.log('error', `✗ ${failureMessage(data, 'Could not undo')}`)
      }
    } catch (e) {
      opts.log('error', `✗ Network error: ${e instanceof Error ? e.message : 'Unknown'}`)
    } finally {
      posting.value = false
    }
  }

  function reset() {
    if (timer) clearTimeout(timer)
    runId++
    picks = {}
    entry.value = null
    hint.value = null
    phase.value = 'idle'
    clearOutcome()
    lastResult.value = null
    defaultsCache = null
  }

  watch(opts.input, onInput)
  onBeforeUnmount(() => {
    if (timer) clearTimeout(timer)
  })

  return {
    active,
    canPost,
    choiceIndex,
    choicePrompt,
    choices,
    handleEnter,
    hint,
    lastResult,
    moveChoice,
    open,
    phase,
    pickChoice,
    posting,
    preview,
    problem,
    reset,
    resolved,
    undo,
  }
}
