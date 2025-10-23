import axios, { type AxiosInstance, type AxiosRequestConfig } from 'axios'

let csrfReady = false
let csrfPromise: Promise<void> | null = null

export async function ensureCsrf(): Promise<void> {
    if (csrfReady) return
    if (!csrfPromise) {
        csrfPromise = axios
            .get('/sanctum/csrf-cookie')
            .catch(() => {
                // Backend may not require this for simple GETs
            })
            .then(() => {
                csrfReady = true
            })
    }
    return csrfPromise
}

export const http: AxiosInstance = axios.create()

http.defaults.withCredentials = true
http.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

try {
    const meta: HTMLMetaElement | null = document
        ?.head
        ?.querySelector('meta[name="csrf-token"]') as any

    if (meta?.content) {
        http.defaults.headers.common['X-CSRF-TOKEN'] = meta.content
    }
} catch {
    // No DOM available (SSR) or missing meta tag
}

http.interceptors.request.use(async (config: AxiosRequestConfig) => {
    const method = (config.method || 'get').toLowerCase()
    const needsCsrf = ['post', 'put', 'patch', 'delete'].includes(method)

    if (needsCsrf && !csrfReady) {
        await ensureCsrf()
    }

    try {
        const companyId = (globalThis as any)?.localStorage?.getItem('currentCompanyId')
        if (companyId) {
            if (!config.headers) {
                config.headers = {}
            }
            ;(config.headers as any)['X-Company-Id'] = companyId
        }
    } catch {
        // Ignore storage access issues (e.g., private mode)
    }

    return config
})

http.interceptors.response.use(
    (response) => response,
    (error) => Promise.reject(error)
)

export function withIdempotency(headers: Record<string, string> = {}): Record<string, string> {
    return {
        'X-Idempotency-Key':
            globalThis.crypto && 'randomUUID' in globalThis.crypto
                ? globalThis.crypto.randomUUID()
                : Math.random().toString(36).slice(2),
        ...headers,
    }
}
