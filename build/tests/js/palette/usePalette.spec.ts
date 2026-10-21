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
  it('sends both the typed text and the pick for a picked blank', async () => {
    const p = usePalette()
    p.open()
    await tick()
    let resolves = 0
    fetchMock.mockImplementation((path: string) => {
      if (path !== '/api/palette/resolve') return json({})
      resolves++
      if (resolves > 1) return json(readyBody('Pay'))
      return json({
        status: 'pending',
        slots: {
          vendor: { state: 'choices', prompt: 'Which?', choices: [{ value: 'v1', label: 'Ali Traders' }] },
          amount: { state: 'resolved', value: '100', label: '100' },
        },
      })
    })
    p.text.value = 'pay ali 100'
    await vi.advanceTimersByTimeAsync(150)
    await tick()
    expect(p.choices.value).toHaveLength(1)

    p.onKeydown(enter())
    await tick()
    const body = JSON.parse(calls('/api/palette/resolve')[1][1].body)
    expect(body.slots.vendor).toEqual({ text: 'ali', value: 'v1' })
    expect(p.status.value).toBe('ready')
  })

  it('retries a post after a network error with the same idempotency key', async () => {
    const p = usePalette()
    p.open()
    await tick()
    let executes = 0
    fetchMock.mockImplementation((path: string) => {
      if (path === '/api/palette/resolve') return json(readyBody('Pay'))
      if (path === '/api/palette/execute') {
        executes++
        return executes === 1 ? Promise.reject(new Error('offline')) : new Promise(() => {})
      }
      return json({})
    })
    p.text.value = 'pay ali 100'
    await vi.advanceTimersByTimeAsync(150)
    await tick()

    p.onKeydown(enter())
    await tick()
    expect(p.status.value).toBe('ready')
    p.onKeydown(enter())
    await tick()

    const keys = calls('/api/palette/execute').map((c) => c[1].headers['X-Idempotency-Key'])
    expect(keys).toHaveLength(2)
    expect(keys[0]).toBeTruthy()
    expect(keys[1]).toBe(keys[0])
  })

  it('flags the missing blank on Enter', async () => {
    const p = usePalette()
    p.open()
    await tick()
    fetchMock.mockImplementation((path: string) => {
      if (path !== '/api/palette/resolve') return json({})
      return json({
        status: 'pending',
        slots: { vendor: { state: 'resolved', value: 'v1', label: 'Ali' }, amount: { state: 'missing' } },
      })
    })
    p.text.value = 'pay ali'
    await vi.advanceTimersByTimeAsync(150)
    await tick()

    p.onKeydown(enter())
    await tick()
    expect(p.status.value).toBe('missing')
    expect(p.message.value).toBe('Amount needed')
    expect(p.blanks.value?.flagMissing).toBe(true)
    expect(p.blanks.value?.current).toBe(1)
  })
})

describe('usePalette line and blank modes', () => {
  const fullPay: CatalogCommand = {
    ...pay,
    sentence: 'Pay {vendor} {amount} from {account?}',
    slots: [...pay.slots, { name: 'account', kind: 'account', optional: true }],
  }
  const opened = async () => {
    fetchMock.mockImplementation((path: string) => (path === '/api/palette/catalog' ? json({ sentences: [fullPay], commands: [] }) : json({})))
    const p = usePalette()
    p.open()
    await tick()
    return p
  }
  const texts = (p: ReturnType<typeof usePalette>) => Object.fromEntries((p.blanks.value?.blanks ?? []).map((b) => [b.name, b.text]))

  it('carries the resolve notes out and clears them with the entry', async () => {
    const p = await opened()
    fetchMock.mockImplementation((path: string) =>
      path === '/api/palette/resolve' ? json({ ...readyBody('Pay'), notes: ['Settles BILL-7'] }) : json({}))
    p.text.value = 'pay ali 100'
    await vi.advanceTimersByTimeAsync(150)
    await tick()
    expect(p.notes.value).toEqual(['Settles BILL-7'])
    p.text.value = 'pay ali 101'
    expect(p.notes.value).toEqual([])
  })

  it('splits a line typed straight through', async () => {
    const p = await opened()
    for (const line of ['pay ', 'pay a', 'pay ali', 'pay ali 1', 'pay ali 1000', 'pay ali 1000 from', 'pay ali 1000 from cash']) p.text.value = line
    expect(texts(p)).toEqual({ vendor: 'ali', amount: '1000', account: 'cash' })
    expect(p.mode.value).toBe('line')
  })

  it('keeps the current blank while the command is unchanged', async () => {
    const p = await opened()
    p.text.value = 'pay ali'
    p.enterBlank(0)
    p.mode.value = 'line'
    p.text.value = 'pay ali 5'
    expect(p.blanks.value?.current).toBe(0)
  })

  it('edits one blank without moving text into another', async () => {
    const p = await opened()
    p.text.value = 'pay ali 1000 from cash'
    p.editBlank(0, '7')
    expect(texts(p)).toEqual({ vendor: '7', amount: '1000', account: 'cash' })
    p.editBlank(0, '7 star from')
    expect(texts(p)).toEqual({ vendor: '7 star from', amount: '1000', account: 'cash' })
    expect(p.mode.value).toBe('blank')
    expect(p.text.value).toBe('Pay 7 star from 1000 from cash')
  })

  it('goes back to the line on Esc from a blank, then clears, then closes', async () => {
    const p = await opened()
    const esc = { key: 'Escape', preventDefault: vi.fn() } as unknown as KeyboardEvent
    p.text.value = 'pay ali 1000'
    p.editBlank(0, 'ali')
    p.onKeydown(esc)
    expect(p.mode.value).toBe('line')
    expect(p.text.value).toBe('Pay ali 1000 ')
    p.onKeydown(esc)
    expect(p.text.value).toBe('')
    p.onKeydown(esc)
    expect(p.isOpen.value).toBe(false)
  })

  it('fills the line from a suggestion on Enter with an empty line', async () => {
    const p = await opened()
    expect(p.suggestions.value.length).toBeGreaterThan(0)
    p.onKeydown(enter())
    await tick()
    expect(p.text.value).toBe('pay ')
  })
})

describe('usePalette round 2', () => {
  const full: CatalogCommand = {
    ...pay,
    sentence: 'Pay {vendor} {amount} from {account?}',
    slots: [...pay.slots, { name: 'account', kind: 'account', optional: true }],
  }
  const opened = async () => {
    fetchMock.mockImplementation((path: string) => (path === '/api/palette/catalog' ? json({ sentences: [full], commands: [] }) : json({})))
    const p = usePalette()
    p.open()
    await tick()
    return p
  }

  it('keeps every blank and pick when Esc leaves blank mode', async () => {
    const p = await opened()
    p.text.value = 'pay ali 1000 from cash'
    p.editBlank(0, 'Seven Star from')
    const set = p.blanks.value!
    set.blanks[0].picked = { value: 'v1', label: 'Seven Star' }
    p.onKeydown({ key: 'Escape', preventDefault: vi.fn() } as unknown as KeyboardEvent)
    expect(p.mode.value).toBe('line')
    expect(p.blanks.value?.blanks.map((b) => b.text)).toEqual(['Seven Star from', '1000', 'cash'])
    expect(p.blanks.value?.blanks[0].picked?.value).toBe('v1')
  })

  it('treats a bare verb as a search: suggestions stay, nothing is resolved', async () => {
    const p = await opened()
    p.text.value = 'pay'
    await vi.advanceTimersByTimeAsync(200)
    expect(p.blanks.value).toBeNull()
    expect(p.suggestions.value.length).toBeGreaterThan(0)
    expect(calls('/api/palette/resolve')).toHaveLength(0)
  })

  it('lets the current blank follow the typing in line mode', async () => {
    const p = await opened()
    p.text.value = 'pay ali'
    expect(p.blanks.value?.current).toBe(1)
    p.text.value = 'pay ali 5'
    expect(p.blanks.value?.current).toBe(2)
  })
})
