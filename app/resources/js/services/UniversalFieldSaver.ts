// src/services/UniversalFieldSaver.ts
import { ref } from 'vue'
import { http } from '@/lib/http'
import { mapFieldName, isAddressField, fieldToAddressPath } from '@/utils/fieldMap'
import router from '@inertiajs/vue3'
import type { ToastServiceMethods } from 'primevue/toastservice'

type SaveOpts = {
  model: string          // 'customer' | 'invoice' | 'vendor' | 'user' ...
  id: string | number,
  fieldPath: string,      // 'city' or 'address.city' etc.
  verify?: boolean,       // default true: check server after PATCH
  maxRetries?: number,    // default 2
  headers?: Record<string, any>,
  optimisticUpdate?: boolean, // default true
  toast?: ToastServiceMethods, // Toast instance from Vue component
  onSuccess?: (data: any) => void,
  onError?: (error: any) => void
}

type SaveResult = {
  ok: boolean
  fieldPath: string
  value?: any
  error?: any
  data?: any
}

class UniversalFieldSaver {
  public savingState = ref<Record<string, boolean>>({}) // keyed by `${model}:${id}:${fieldPath}`
  private inflight: Record<string, Promise<SaveResult> | null> = {}

  private backoffMs(attempt: number) {
    return Math.min(300 * 2 ** attempt, 2000)
  }

  private keyFor(opts: SaveOpts) {
    return `${opts.model}:${opts.id}:${opts.fieldPath}`
  }

  private humanize(fieldPath: string) {
    return fieldPath.replace(/\./g, ' ').replace(/_/g, ' ')
  }

  private equals(a: any, b: any) {
    try { 
      return JSON.stringify(a) === JSON.stringify(b) 
    } catch { 
      return a === b 
    }
  }

  // Construct payload based on field path
  private buildPayload(opts: SaveOpts, value: any) {
    const mappedField = mapFieldName(opts.fieldPath)
    
    // Handle address fields
    if (isAddressField(mappedField)) {
      if (mappedField.startsWith('address.')) {
        // Already in nested format
        const addressKey = mappedField.substring(8)
        return {
          model: opts.model,
          id: String(opts.id),
          fields: {
            address: {
              [addressKey]: value
            }
          }
        }
      } else {
        // Convert to nested format
        return {
          model: opts.model,
          id: String(opts.id),
          fields: {
            address: {
              [mappedField]: value
            }
          }
        }
      }
    }
    
    // Handle special fields that need conversion
    if (mappedField === 'status') {
      return {
        model: opts.model,
        id: String(opts.id),
        fields: {
          status: value
        }
      }
    }
    
    // Regular fields
    return {
      model: opts.model,
      id: String(opts.id),
      fields: {
        [mappedField]: value
      }
    }
  }

  // Public API: save single field
  public async save(opts: SaveOpts, value: any, originalValue?: any): Promise<SaveResult> {
    const key = this.keyFor(opts)
    if (this.inflight[key]) return this.inflight[key] as Promise<SaveResult>

    const promise = (async (): Promise<SaveResult> => {
      const verify = opts.verify ?? true
      const maxRetries = opts.maxRetries ?? 2
      const optimisticUpdate = opts.optimisticUpdate ?? true
      this.setSaving(key, true)

      let attempt = 0
      let lastErr: any = null

      while (attempt <= maxRetries) {
        try {
          // Use single canonical endpoint: PATCH /api/inline-edit
          const response = await http.patch('/api/inline-edit', this.buildPayload(opts, value), {
            headers: opts.headers ?? {}
          })

          if (response.data.success) {
            // Server returned success
            opts.toast?.add({ 
              severity: 'success', 
              summary: 'Saved', 
              detail: `${this.humanize(opts.fieldPath)} saved`, 
              life: 2000 
            })
            
            opts.onSuccess?.(response.data)
            
            return { 
              ok: true, 
              fieldPath: opts.fieldPath, 
              value,
              data: response.data
            }
          } else {
            throw new Error(response.data.message || 'Save failed')
          }
        } catch (err: any) {
          lastErr = err
          attempt++
          
          if (attempt > maxRetries) break
          
          await new Promise(r => setTimeout(r, this.backoffMs(attempt)))
        }
      }

      // All retries failed
      const errorMessage = lastErr.response?.data?.message || lastErr.message || 'Unknown error'
      const fieldErrors = lastErr.response?.data?.errors
      
      if (fieldErrors) {
        // Field-specific validation errors
        const firstError = Object.values(fieldErrors)[0] as string[]
        opts.toast?.add({ 
          severity: 'error', 
          summary: 'Validation Error', 
          detail: firstError?.[0] || 'Invalid value', 
          life: 5000 
        })
      } else {
        opts.toast?.add({ 
          severity: 'error', 
          summary: 'Save failed', 
          detail: `Could not save ${this.humanize(opts.fieldPath)}: ${errorMessage}`, 
          life: 5000 
        })
      }

      opts.onError?.(lastErr)
      
      return { 
        ok: false, 
        fieldPath: opts.fieldPath, 
        error: lastErr 
      }
    })()

    this.inflight[key] = promise
    try {
      return await promise
    } finally {
      this.inflight[key] = null
      this.setSaving(key, false)
    }
  }

  public isSaving(opts: SaveOpts) {
    return !!this.savingState.value[this.keyFor(opts)]
  }

  private setSaving(key: string, val: boolean) {
    this.savingState.value = { ...this.savingState.value, [key]: val }
  }

  // Helper to update local data optimistically
  public updateOptimistically<T extends Record<string, any>>(
    localData: T,
    fieldPath: string,
    newValue: any,
    originalValue?: any
  ): T {
    if (originalValue !== undefined) {
      // Store original value for rollback
      ;(localData as any)._originalValues = (localData as any)._originalValues || {}
      ;(localData as any)._originalValues[fieldPath] = originalValue
    }
    
    // Handle nested field paths
    const parts = fieldPath.split('.')
    let current: any = localData
    
    for (let i = 0; i < parts.length - 1; i++) {
      const part = parts[i]
      if (!(part in current)) {
        current[part] = {}
      }
      current = current[part]
    }
    
    current[parts[parts.length - 1]] = newValue
    
    return localData
  }

  // Helper to rollback optimistic update
  public rollbackOptimisticUpdate<T extends Record<string, any>>(
    localData: T,
    fieldPath: string
  ): T {
    const originalValues = (localData as any)._originalValues
    if (!originalValues || !(fieldPath in originalValues)) {
      return localData
    }
    
    const parts = fieldPath.split('.')
    let current: any = localData
    
    for (let i = 0; i < parts.length - 1; i++) {
      const part = parts[i]
      if (!(part in current)) {
        return localData // Path doesn't exist, nothing to rollback
      }
      current = current[part]
    }
    
    current[parts[parts.length - 1]] = originalValues[fieldPath]
    
    // Clean up stored original value
    delete originalValues[fieldPath]
    
    return localData
  }
}

export default new UniversalFieldSaver()