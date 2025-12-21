type Primitive = string | number | boolean | null | undefined
type QueryValue = Primitive | Primitive[]

export type RouteQueryOptions = {
  query?: Record<string, QueryValue>
  mergeQuery?: Record<string, QueryValue>
}

// Support both 'method' (singular, for Inertia compatibility) and 'methods' (plural, from wayfinder)
// When Method is a string[], the definition uses 'methods'. When Method is a string, route calls use 'method'.
export type RouteDefinition<Method extends string | string[]> = {
  url: string
} & (Method extends string[]
  ? { methods: Method; method?: never }
  : { method: Method; methods?: never })

export type RouteFormDefinition<Method extends string> = {
  action: string
  method: Method
}

export function queryParams(options?: RouteQueryOptions): string {
  const query = options?.mergeQuery ?? options?.query
  if (!query) return ''

  const params = new URLSearchParams()

  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null) continue

    if (Array.isArray(value)) {
      value.forEach((v) => append(params, key, v))
    } else {
      append(params, key, value)
    }
  }

  const qs = params.toString()
  return qs ? `?${qs}` : ''
}

function append(params: URLSearchParams, key: string, value: Primitive) {
  if (value === undefined || value === null) return
  params.append(key, String(value))
}

export function applyUrlDefaults<T extends Record<string, unknown>>(args: T): T {
  return args
}
