// resources/js/palette/composables/useSuggestions.ts
import { Ref } from 'vue'
import { http, ensureCsrf } from '@/lib/http'
import type { FieldDef } from '@/palette/entities'

export type SuggestItem = { value: string; label: string; meta?: any }

export interface SuggestContext {
  isSuperAdmin: Ref<boolean>
  currentCompanyId: Ref<string | null>
  userSource: Ref<'all' | 'company'>
  companySource: Ref<'all' | 'me' | 'byUser'>
  q: Ref<string>
  params: Ref<Record<string, any>>
}

export function useSuggestions(ctx: SuggestContext) {
  const cache = new Map<string, { ts: number; items: SuggestItem[] }>()
  const TTL_MS = 60 * 1000

  const templ = (tpl: string, row: any) => tpl.replace(/\{(\w+)\}/g, (_, k) => (row?.[k] ?? ''))

  async function fromField(field: FieldDef, qstr: string, params: Record<string, any>): Promise<SuggestItem[]> {
    const src: any = (field as any).source || null
    if (!src || src.kind !== 'remote' || !src.endpoint) return []

    const depends = (src.dependsOn || []).reduce((acc: any, key: string) => { acc[key] = params?.[key]; return acc }, {})
    const qkey = src.queryKey || 'q'
    const limit = typeof src.limit === 'number' ? src.limit : 12
    const cacheKey = JSON.stringify({ ep: src.endpoint, q: qstr || '', depends })
    const now = Date.now()
    const hit = cache.get(cacheKey)
    if (hit && now - hit.ts < TTL_MS) return hit.items

    const reqParams: any = { [qkey]: qstr, limit, ...depends }
    await ensureCsrf()
    const { data } = await http.get(src.endpoint, { params: reqParams })
    const list = data?.data || []
    const items: SuggestItem[] = list.map((row: any) => {
      const value = row?.[src.valueKey]
      const label = src.labelTemplate ? templ(src.labelTemplate, row) : (src.labelKey ? row?.[src.labelKey] : String(value))
      return { value, label, meta: row }
    })
    cache.set(cacheKey, { ts: now, items })
    return items
  }
  async function users(qstr: string): Promise<SuggestItem[]> {
    const paramsAny: any = { q: qstr }
    if (!ctx.isSuperAdmin.value || ctx.userSource.value === 'company') {
      paramsAny.company_id = ctx.currentCompanyId.value
    }
    await ensureCsrf()
    const { data } = await http.get('/web/users/suggest', { params: paramsAny })
    const list = data?.data || []
    return list.map((u: any) => ({ value: u.email, label: u.name, meta: { email: u.email, id: u.id, name: u.name } }))
  }

  async function companies(): Promise<SuggestItem[]> {
    const qobj: any = {}
    if (ctx.companySource.value === 'byUser' && ctx.isSuperAdmin.value && ctx.params.value.email) {
      qobj.user_email = ctx.params.value.email
    }
    if (ctx.q.value && ctx.q.value.length > 0) {
      qobj.q = ctx.q.value
    }
    await ensureCsrf()
    const { data } = await http.get('/web/companies', { params: qobj })
    const list = data?.data || []
    return list.map((c: any) => ({ value: c.id, label: c.name, meta: { id: c.id } }))
  }

  async function currencies(qstr: string): Promise<SuggestItem[]> {
    await ensureCsrf()
    const { data } = await http.get('/web/currencies/suggest', { params: { q: qstr, limit: 12 } })
    const list = data?.data || []
    return list.map((c: any) => ({ value: c.code, label: `${c.code} — ${c.name}${c.symbol ? ` (${c.symbol})` : ''}`, meta: c }))
  }

  async function languages(qstr: string): Promise<SuggestItem[]> {
    await ensureCsrf()
    const { data } = await http.get('/web/languages/suggest', { params: { q: qstr, limit: 12 } })
    const list = data?.data || []
    return list.map((l: any) => ({ value: l.code, label: `${l.code} — ${l.native_name || l.name}${l.rtl ? ' (RTL)' : ''}`, meta: l }))
  }

  async function locales(qstr: string): Promise<SuggestItem[]> {
    await ensureCsrf()
    const paramsAny: any = { q: qstr, limit: 12 }
    if (ctx.params.value?.language) paramsAny.language = ctx.params.value.language
    if (ctx.params.value?.country) paramsAny.country = ctx.params.value.country
    const { data } = await http.get('/web/locales/suggest', { params: paramsAny })
    const list = data?.data || []
    return list.map((l: any) => ({ value: l.tag, label: `${l.tag} — ${l.native_name || l.name || ''}`.trim(), meta: l }))
  }

  async function countries(qstr: string): Promise<SuggestItem[]> {
    await ensureCsrf()
    const { data } = await http.get('/web/countries/suggest', { params: { q: qstr, limit: 12 } })
    const list = data?.data || []
    return list.map((c: any) => ({ value: c.code, label: `${c.emoji ? c.emoji + ' ' : ''}${c.name} — ${c.code}`, meta: c }))
  }

  return { users, companies, currencies, languages, locales, countries, fromField }
}
