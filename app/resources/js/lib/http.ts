// resources/js/lib/http.ts
import axios, { AxiosInstance, AxiosRequestConfig } from 'axios'

let csrfReady = false
let csrfPromise: Promise<void> | null = null

export async function ensureCsrf(): Promise<void> {
  if (csrfReady) return
  if (!csrfPromise) {
    csrfPromise = axios.get('/sanctum/csrf-cookie').catch(() => {
      // ignore, backend may not require it for GETs
    }).then(() => { csrfReady = true })
  }
  return csrfPromise
}

// Create a shared axios instance
export const http: AxiosInstance = axios.create()

// Ensure CSRF for mutating requests automatically
http.interceptors.request.use(async (config: AxiosRequestConfig) => {
  const method = (config.method || 'get').toLowerCase()
  const needsCsrf = method === 'post' || method === 'put' || method === 'patch' || method === 'delete'
  if (needsCsrf && !csrfReady) {
    await ensureCsrf()
  }
  return config
})

// Helper to attach an idempotency key header
export function withIdempotency(headers: Record<string, string> = {}): Record<string, string> {
  return {
    'X-Idempotency-Key': (globalThis.crypto && 'randomUUID' in globalThis.crypto)
      ? globalThis.crypto.randomUUID()
      : Math.random().toString(36).slice(2),
    ...headers,
  }
}

