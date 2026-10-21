import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import type { CatalogCommand } from '@/palette/v2/types'

const slug = ref('acme')
vi.mock('@/composables/useNavGroups', () => ({
  useNavGroups: () => ({ currentCompany: ref({ get slug() { return slug.value } }), navGroups: ref([]) }),
}))
vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn() } }))
vi.mock('@/palette/frecency', () => ({ recordCommandUse: vi.fn(), getFrecencyScores: () => ({}) }))

import { clearPaletteCatalogCache, usePalette } from '@/composables/palette/usePalette'

const pay: CatalogCommand = {
  name: 'pay', aliases: [], label: 'Pay', sentence: 'Pay {vendor} {amount}', key: 'money.pay',
  slots: [
    { name: 'vendor', kind: 'search', optional: false },
    { name: 'amount', kind: 'amount', optional: false },
  ],
  generic: false, form_url: null, query: false, opens: null, undo: null, previewable: false,
}

const json = (body: unknown) => Promise.resolve({ ok: true, json: () => Promise.resolve(body) })
const readyBody = (summary: string) => ({ status: 'ready', key: 'money.pay', params: {}, card: { currency: 'PKR' }, summary, notes: [] })
const tick = async () => { for (let i = 0; i < 25; i++) await Promise.resolve() }
const enter = () => ({ key: 'Enter', preventDefault: vi.fn() }) as unknown as KeyboardEvent

let fetchMock: ReturnType<typeof vi.fn>
const calls = (path: string) => fetchMock.mock.calls.filter((c) => String(c[0]).startsWith(path))

beforeEach(() => {
  vi.useFakeTimers()
  clearPaletteCatalogCache()
  slug.value = 'acme'
  fetchMock = vi.fn((path: string) => (path === '/api/palette/catalog' ? json({ sentences: [pay], commands: [] }) : json({})))
  vi.stubGlobal('fetch', fetchMock)
})
afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

describe('usePalette', () => {
  it('drops a resolve answer that arrives after a newer one', async () => {
    const p = usePalette()
    p.open()
    await tick()

    const held: Array<(v: unknown) => void> = []
    fetchMock.mockImplementation((path: string) => {
      if (path !== '/api/palette/resolve') return json({})
      return new Promise((resolve) => held.push(resolve))
    })

    p.text.value = 'pay ali 100'
    await vi.advanceTimersByTimeAsync(150)
    p.text.value = 'pay ali 200'
    await vi.advanceTimersByTimeAsync(150)
    expect(held).toHaveLength(2)

    const answer = (summary: string) => ({ ok: true, json: () => Promise.resolve(readyBody(summary)) })
    held[1](answer('second'))
    await tick()
    held[0](answer('first'))
    await tick()

    expect(p.status.value).toBe('ready')
    expect(p.message.value).toBe('second')
  })

  it('posts once when Enter is pressed twice', async () => {
    const p = usePalette()
    p.open()
    await tick()
    fetchMock.mockImplementation((path: string) => {
      if (path === '/api/palette/resolve') return json(readyBody('Pay'))
      if (path === '/api/palette/execute') return new Promise(() => {}) // never answers: stays posting
      return json({})
    })

    p.text.value = 'pay ali 100'
    await vi.advanceTimersByTimeAsync(150)
    await tick()
    expect(p.status.value).toBe('ready')

    p.onKeydown(enter())
    p.onKeydown(enter())
    await tick()
    expect(calls('/api/palette/execute')).toHaveLength(1)
    expect(p.status.value).toBe('posting')
  })

  it('refetches the catalog when the company changes', async () => {
    const p = usePalette()
    p.open()
    await tick()
    slug.value = 'beta'
    p.close()
    p.open()
    await tick()
    expect(calls('/api/palette/catalog')).toHaveLength(2)
    p.close()
    p.open() // same company again: cached
    await tick()
    expect(calls('/api/palette/catalog')).toHaveLength(2)
  })

  it('says there is nothing to undo when nothing was posted', async () => {
    const p = usePalette()
    await p.undo()
    expect(p.message.value).toBe('Nothing to undo')
    expect(calls('/api/palette/execute')).toHaveLength(0)
  })
})
