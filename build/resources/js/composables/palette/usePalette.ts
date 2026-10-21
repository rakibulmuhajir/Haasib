import { computed, getCurrentInstance, onBeforeUnmount, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useNavGroups } from '@/composables/useNavGroups'
import { getFrecencyScores, recordCommandUse } from '@/palette/frecency'
import { matchCommand, suggestCommands } from '@/palette/v2/sentence'
import {
  applyResolve, enterOutcome, flag, fromMatch, nextRequired, pick, tab,
  type BlankSet,
} from '@/palette/v2/blanks'
import type { CatalogCommand, Card, Option } from '@/palette/v2/types'

const RESOLVE_MS = 150
const FIND_MS = 150

/** Commands per company slug: a fuel company's commands must never reach a general company. */
const catalogs = new Map<string, CatalogCommand[]>()
const loading = new Map<string, Promise<CatalogCommand[]>>()

/** For tests. */
export function clearPaletteCatalogCache(): void {
  catalogs.clear()
  loading.clear()
}

type Status = 'idle' | 'working' | 'ready' | 'missing' | 'error' | 'posting'
interface FindResult { group: string; label: string; meta?: string; url: string }
interface Result { message: string; openUrl: string | null; canUndo: boolean; undone: boolean }
interface Ready { key: string; params: Record<string, unknown>; summary: string }
interface Posted { undoKey: string | null; id: string | null; undone: boolean }

function csrf(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

const uuid = () => (typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`)
const normalise = (text: string) => text.trim().toLowerCase().replace(/\s+/g, ' ')

/** What went wrong, in the server's words: the first field error for a refused entry, else its message. */
function failureMessage(data: any, fallback: string): string {
  if (data?.code === 'VALIDATION') {
    const first = Object.values((data?.errors ?? {}) as Record<string, string[]>).flat()[0]
    if (first) return first
  }
  return data?.message || fallback
}

function describeProblem(data: any): string {
  const message: string = data?.message || "Couldn't check this"
  const fields = Object.values((data?.errors ?? {}) as Record<string, string[]>).flat()
  const extra = fields.filter((m) => m !== message)
  return extra.length > 0 && data?.code === 'VALIDATION' ? [message, ...extra].join(' ') : message
}

/**
 * State and flow for the sentence palette: match the typed line to a command, resolve its blanks
 * on the server, preview, and post on Enter. Nothing is posted until the entry on screen is ready.
 */
export function usePalette() {
  const { currentCompany, navGroups } = useNavGroups()
  const slug = () => (currentCompany.value?.slug as string | undefined) ?? ''

  const isOpen = ref(false)
  const text = ref('')
  const blanks = ref<BlankSet | null>(null)
  const suggestions = ref<CatalogCommand[]>([])
  const choiceIndex = ref(0)
  const choicesClosed = ref(false)
  const card = ref<Card | null>(null)
  const balances = ref<Array<{ account_name: string; before: number; after: number }>>([])
  const status = ref<Status>('idle')
  const message = ref<string | null>(null)
  const result = ref<Result | null>(null)
  const findResults = ref<FindResult[]>([])

  const catalog = ref<CatalogCommand[]>([])
  let ready: Ready | null = null
  let idempotencyKey = ''
  let lastPosted: Posted | null = null
  let lastPostedText = ''
  let seq = 0
  let findSeq = 0
  let resolveTimer: ReturnType<typeof setTimeout> | null = null
  let findTimer: ReturnType<typeof setTimeout> | null = null
  let inflight: Promise<void> | null = null
  let remote: FindResult[] = []
  let pages: FindResult[] = []

  /** The current blank's choices, unless the user closed the list. */
  const choices = computed<Option[]>(() => {
    const b = blanks.value
    const state = b?.blanks[b.current]?.state
    return state?.state === 'choices' && !choicesClosed.value ? state.choices : []
  })

  // ---- network ---------------------------------------------------------

  function headers(extra: Record<string, string> = {}): Record<string, string> {
    const h: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrf(),
      ...extra,
    }
    if (slug()) h['X-Company-Slug'] = slug()
    return h
  }

  async function loadCatalog(): Promise<void> {
    const wanted = slug()
    const cached = catalogs.get(wanted)
    if (cached) {
      catalog.value = cached
      refresh()
      return
    }
    catalog.value = []
    let pending = loading.get(wanted)
    if (!pending) {
      pending = fetch('/api/palette/catalog', { credentials: 'same-origin', headers: headers() })
        .then((res) => {
          if (!res.ok) throw new Error(`catalog ${res.status}`)
          return res.json()
        })
        .then((data) => (data.sentences ?? []) as CatalogCommand[])
        .finally(() => loading.delete(wanted))
      loading.set(wanted, pending)
    }
    try {
      const list = await pending
      catalogs.set(wanted, list)
      if (slug() !== wanted) return
      catalog.value = list
      refresh()
    } catch {
      if (slug() !== wanted) return
      status.value = 'error'
      message.value = "Couldn't load commands"
    }
  }

  function forgetReady() {
    ready = null
    idempotencyKey = ''
    card.value = null
    balances.value = []
  }

  async function resolveNow(): Promise<void> {
    if (resolveTimer) clearTimeout(resolveTimer)
    resolveTimer = null
    const set = blanks.value
    if (!set) return
    const id = ++seq
    status.value = 'working'
    message.value = null
    const slots: Record<string, { text?: string; value?: string }> = {}
    for (const b of set.blanks) {
      if (b.picked) slots[b.name] = { value: b.picked.value }
      else if (b.text !== '') slots[b.name] = { text: b.text }
    }
    try {
      const res = await fetch('/api/palette/resolve', {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers(),
        body: JSON.stringify({ command: set.command.name, slots }),
      })
      const data = await res.json().catch(() => ({}))
      if (id !== seq) return // typed more since: this answer is for old text
      if (!res.ok || !data?.status) throw new Error('resolve failed')

      const next = applyResolve(set, data)
      blanks.value = next
      choicesClosed.value = false
      choiceIndex.value = 0

      if (data.status === 'error') {
        forgetReady()
        status.value = 'error'
        message.value = data.message
      } else if (data.status === 'pending') {
        forgetReady()
        const bad = next.blanks.find((b) => b.state?.state === 'not_found')
        message.value = bad?.state?.state === 'not_found' ? bad.state.message : null
        status.value = next.flagMissing ? 'missing' : 'idle'
      } else {
        ready = { key: data.key, params: data.params, summary: data.summary }
        idempotencyKey = uuid()
        card.value = data.card
        message.value = data.summary
        if (set.command.previewable && !set.command.query) {
          const preview = await fetch('/api/palette/preview', {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers(),
            body: JSON.stringify({ key: data.key, params: data.params }),
          })
          const body = await preview.json().catch(() => ({}))
          if (id !== seq) return
          if (!body.ok) {
            forgetReady()
            status.value = 'error'
            message.value = describeProblem(body)
            return
          }
          balances.value = body.balances ?? []
        }
        status.value = 'ready'
      }
    } catch {
      if (id !== seq) return
      forgetReady()
      status.value = 'error'
      message.value = "Couldn't check this. Try again."
    }
  }

  function scheduleResolve() {
    if (resolveTimer) clearTimeout(resolveTimer)
    resolveTimer = setTimeout(() => {
      inflight = resolveNow().finally(() => { inflight = null })
    }, RESOLVE_MS)
  }

  // ---- typing ----------------------------------------------------------

  function pageMatches(query: string): FindResult[] {
    const words = query.split(' ').filter(Boolean)
    if (words.length === 0) return []
    const out: FindResult[] = []
    const visit = (items: any[], group: string) => {
      for (const item of items) {
        const href = typeof item.href === 'string' ? item.href : item.href?.url
        if (href && !item.external && words.every((w) => String(item.title).toLowerCase().includes(w))) {
          out.push({ group: 'Pages', label: item.title, meta: group, url: href })
        }
        if (item.children) visit(item.children, group)
      }
    }
    for (const g of navGroups.value) visit(g.items, g.label)
    return out.slice(0, 8)
  }

  function showFind() {
    findResults.value = [...pages, ...remote]
  }

  function scheduleFind(query: string) {
    if (findTimer) clearTimeout(findTimer)
    const id = ++findSeq
    findTimer = setTimeout(async () => {
      try {
        const res = await fetch(`/api/palette/find?q=${encodeURIComponent(query)}`, { credentials: 'same-origin', headers: headers() })
        if (!res.ok) return
        const data = await res.json()
        if (id !== findSeq) return
        remote = ((data.results ?? []) as any[]).map((r) => ({ group: r.group, label: r.label, meta: r.meta, url: r.url }))
        showFind()
      } catch {
        /* find is a convenience; stay quiet */
      }
    }, FIND_MS)
  }

  /** Re-reads the line: which command, which blanks, what to ask the server. */
  function refresh() {
    if (resolveTimer) clearTimeout(resolveTimer)
    if (findTimer) clearTimeout(findTimer)
    resolveTimer = findTimer = null
    seq++
    findSeq++
    forgetReady()
    message.value = null
    choiceIndex.value = 0
    choicesClosed.value = false
    remote = []
    pages = []
    findResults.value = []

    const line = normalise(text.value)
    if (line === '') {
      blanks.value = null
      suggestions.value = suggestCommands('', catalog.value, getFrecencyScores())
      status.value = 'idle'
      return
    }
    result.value = null

    const found = matchCommand(line, catalog.value)
    if (found) {
      const next = fromMatch(found)
      const old = blanks.value
      if (old && old.command.name === next.command.name) {
        // A blank whose text did not change keeps its pick and its answer.
        next.blanks.forEach((b, i) => {
          if (old.blanks[i]?.text === b.text) { b.picked = old.blanks[i].picked; b.state = old.blanks[i].state }
        })
      }
      blanks.value = next
      suggestions.value = []
      status.value = 'working'
      scheduleResolve()
      return
    }

    blanks.value = null
    status.value = 'idle'
    const go = line === 'go' || line.startsWith('go ')
    suggestions.value = go ? [] : suggestCommands(line, catalog.value, getFrecencyScores())
    const query = go ? line.slice(2).trim() : line
    if (!go && query.length < 2) return
    pages = pageMatches(query)
    showFind()
    if (!go) scheduleFind(query)
  }

  watch(text, refresh, { flush: 'sync' })
  watch(slug, () => {
    if (!isOpen.value) return
    forgetReady()
    void loadCatalog()
  }, { flush: 'sync' })

  // ---- Enter -----------------------------------------------------------

  function visit(url: string) {
    close()
    router.visit(url)
  }

  function expand(template: string): string {
    return template.replace('{company}', slug())
  }

  function formUrl(set: BlankSet): string {
    const base = expand(set.command.form_url ?? '')
    const param = set.command.name === 'invoice' ? 'customer_id' : set.command.name === 'bill' ? 'vendor_id' : null
    if (!param) return base
    const wanted = param === 'customer_id' ? 'customer' : 'vendor'
    const blank = set.blanks.find((b) => b.name === wanted) ?? set.blanks.find((b) => b.spec.kind === 'search')
    const id = blank?.picked?.value ?? (blank?.state?.state === 'resolved' ? blank.state.value : null)
    return id ? `${base}${base.includes('?') ? '&' : '?'}${param}=${encodeURIComponent(id)}` : base
  }

  function openUrlFor(command: CatalogCommand, data: any): string | null {
    const id = data?.data?.id
    if (command.opens === 'redirect') {
      return data?.redirect ?? (data?.data?.invoice_id && slug() ? `/${slug()}/invoices/${data.data.invoice_id}` : null)
    }
    if (!command.opens || !slug() || !id) return null
    return expand(command.opens).replace('{id}', String(id))
  }

  /** Where "Open" goes after an undo: the reversal entry, or the sale's invoice. */
  function undoUrlFor(key: string, data: any): string | null {
    if (!slug()) return null
    if (key === 'sale.void') return data?.redirect ?? (data?.data?.id ? `/${slug()}/invoices/${data.data.id}` : null)
    // Voiding a payment answers with the reversal's id; reversing a journal's own id is the reversal.
    const id = key.endsWith('.reverse') ? data?.data?.id : data?.data?.reversal_id
    return id ? `/${slug()}/journals/${id}` : null
  }

  async function post() {
    const set = blanks.value
    if (!ready || !set || status.value === 'posting') return
    const sent = ready
    const typed = text.value.trim()
    const [entity, ...rest] = sent.key.split('.')
    status.value = 'posting'
    try {
      const res = await fetch('/api/palette/execute', {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers({ 'X-Action': sent.key, 'X-Idempotency-Key': idempotencyKey }),
        body: JSON.stringify({ entity, verb: rest.join('.'), params: sent.params }),
      })
      const data = await res.json().catch(() => ({}))
      if (data.ok) {
        recordCommandUse(set.command.name)
        const id = data?.data?.id ? String(data.data.id) : null
        lastPosted = { undoKey: set.command.undo, id, undone: false }
        lastPostedText = typed
        const shown: Result = {
          message: data.message || sent.summary,
          openUrl: openUrlFor(set.command, data),
          canUndo: Boolean(set.command.undo && id),
          undone: false,
        }
        text.value = '' // refresh() resets the rest; the result is set after it
        result.value = shown
        status.value = 'idle'
      } else {
        forgetReady()
        status.value = 'error'
        message.value = failureMessage(data, 'Could not post')
      }
    } catch (e) {
      forgetReady()
      status.value = 'error'
      message.value = `Network error: ${e instanceof Error ? e.message : 'Unknown'}`
    }
  }

  async function undo(): Promise<void> {
    const last = lastPosted
    if (status.value === 'posting') return
    if (!last || !last.undoKey || !last.id || last.undone) {
      message.value = 'Nothing to undo'
      return
    }
    const [entity, ...rest] = last.undoKey.split('.')
    status.value = 'posting'
    try {
      const res = await fetch('/api/palette/execute', {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers({ 'X-Action': last.undoKey }),
        body: JSON.stringify({ entity, verb: rest.join('.'), params: { id: last.id } }),
      })
      const data = await res.json().catch(() => ({}))
      if (data.ok) {
        last.undone = true
        result.value = { message: data.message || 'Undone', openUrl: undoUrlFor(last.undoKey, data), canUndo: false, undone: true }
        message.value = null
        status.value = 'idle'
      } else {
        status.value = 'error'
        message.value = failureMessage(data, 'Could not undo')
      }
    } catch (e) {
      status.value = 'error'
      message.value = `Network error: ${e instanceof Error ? e.message : 'Unknown'}`
    }
  }

  async function onEnter() {
    if (status.value === 'posting') return
    const line = normalise(text.value)
    if (line === 'undo') {
      text.value = ''
      await undo()
      return
    }

    let set = blanks.value
    if (!set) {
      const hit = findResults.value[choiceIndex.value]
      if (hit) return visit(hit.url)
      const suggested = suggestions.value[choiceIndex.value]
      if (line !== '' && suggested) text.value = `${suggested.name} `
      return
    }

    // Enter skips the wait, but a preview still has to appear before anything is posted.
    if (resolveTimer) {
      inflight = resolveNow().finally(() => { inflight = null })
      if (!set.command.form_url) return
    }
    if (inflight) {
      await inflight
      if (!set.command.form_url) return
      set = blanks.value ?? set
    }
    if (status.value === 'error' && !set.command.form_url) {
      inflight = resolveNow().finally(() => { inflight = null })
      return
    }

    const outcome = enterOutcome(set, status.value === 'ready' && ready !== null, choices.value.length > 0)
    switch (outcome) {
      case 'pick': {
        const chosen = choices.value[choiceIndex.value]
        if (!chosen) return
        blanks.value = pick(set, set.current, chosen)
        inflight = resolveNow().finally(() => { inflight = null })
        return
      }
      case 'advance': {
        const next = nextRequired(set)
        if (next !== null) blanks.value = { ...set, current: next }
        return
      }
      case 'post':
        return post()
      case 'open': {
        if (set.command.form_url) return visit(formUrl(set))
        const url = ready?.params.url
        if (set.command.query && typeof url === 'string') visit(url)
        return
      }
      case 'flag': {
        const flagged = flag(set)
        blanks.value = flagged
        const bad = flagged.blanks[flagged.current]
        status.value = 'missing'
        const label = bad.name.charAt(0).toUpperCase() + bad.name.slice(1)
        message.value = bad.state?.state === 'not_found' ? bad.state.message : `${label} needed`
      }
    }
  }

  // ---- keys ------------------------------------------------------------

  function onKeydown(e: KeyboardEvent): void {
    const key = e.key
    if (key === 'Enter') {
      e.preventDefault()
      void onEnter()
    } else if (key === 'Tab') {
      if (blanks.value) {
        e.preventDefault()
        blanks.value = tab(blanks.value, e.shiftKey ? -1 : 1)
      } else if (suggestions.value.length > 0 && text.value.trim() !== '') {
        e.preventDefault()
        text.value = `${suggestions.value[choiceIndex.value]?.name ?? suggestions.value[0].name} `
      }
    } else if (key === 'Escape') {
      e.preventDefault()
      if (choices.value.length > 0) choicesClosed.value = true
      else if (text.value !== '') text.value = ''
      else close()
    } else if (key === 'ArrowDown' || key === 'ArrowUp') {
      const step = key === 'ArrowDown' ? 1 : -1
      const size = choices.value.length || (blanks.value ? 0 : findResults.value.length || suggestions.value.length)
      if (key === 'ArrowUp' && text.value === '' && lastPostedText) {
        e.preventDefault()
        text.value = lastPostedText
      } else if (size > 0) {
        e.preventDefault()
        choiceIndex.value = Math.max(0, Math.min(size - 1, choiceIndex.value + step))
      }
    } else if (key.toLowerCase() === 'z' && (e.ctrlKey || e.metaKey) && text.value === '' && result.value?.canUndo) {
      e.preventDefault()
      void undo()
    }
  }

  // ---- open / close ----------------------------------------------------

  function open() {
    isOpen.value = true
    void loadCatalog()
  }

  function close() {
    isOpen.value = false
    if (resolveTimer) clearTimeout(resolveTimer)
    if (findTimer) clearTimeout(findTimer)
    resolveTimer = findTimer = null
    seq++
    findSeq++
    forgetReady()
    text.value = ''
    blanks.value = null
    result.value = null
    message.value = null
    status.value = 'idle'
    findResults.value = []
  }

  if (getCurrentInstance()) {
    onBeforeUnmount(() => {
      if (resolveTimer) clearTimeout(resolveTimer)
      if (findTimer) clearTimeout(findTimer)
    })
  }

  return {
    text, blanks, suggestions, choices, choiceIndex, card, balances, status, message, result, findResults,
    isOpen, onKeydown, open, close, undo,
  }
}
