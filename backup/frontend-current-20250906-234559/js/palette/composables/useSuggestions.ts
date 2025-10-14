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

    const reqParams: any = { ...depends }
    if (qstr) {
      reqParams[qkey] = qstr
    }
    if (typeof src.limit === 'number') {
      reqParams.limit = src.limit
    }

    // Handle special contextual parameters for known endpoints
    if (src.endpoint.includes('/users/suggest')) {
      if (!ctx.isSuperAdmin.value || ctx.userSource.value === 'company') {
        reqParams.company_id = ctx.currentCompanyId.value
      }
    }
    if (src.endpoint === '/web/companies') {
      if (ctx.companySource.value === 'byUser' && ctx.isSuperAdmin.value && ctx.params.value.email) {
        reqParams.user_email = ctx.params.value.email
      }
    }

    const cacheKey = JSON.stringify({ ep: src.endpoint, params: reqParams })
    const now = Date.now()
    const hit = cache.get(cacheKey)
    if (hit && now - hit.ts < TTL_MS) return hit.items

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

  return { fromField }
}
