import { ref } from 'vue'
import { http } from '@/lib/http'
import { isAddressField, mapFieldName } from '@/utils/fieldMap'
import type { ToastServiceMethods } from 'primevue/toastservice'

type SaveOptions = {
    model: string
    id: string | number
    fieldPath: string
    verify?: boolean
    maxRetries?: number
    headers?: Record<string, any>
    optimisticUpdate?: boolean
    toast?: ToastServiceMethods
    onSuccess?: (data: any) => void
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
    public savingState = ref<Record<string, boolean>>({})
    private inflight: Record<string, Promise<SaveResult> | null> = {}

    private backoffMs(attempt: number) {
        return Math.min(300 * 2 ** attempt, 2000)
    }

    private keyFor(opts: SaveOptions) {
        return `${opts.model}:${opts.id}:${opts.fieldPath}`
    }

    private humanize(fieldPath: string) {
        return fieldPath.replace(/\./g, ' ').replace(/_/g, ' ')
    }

    private buildPayload(opts: SaveOptions, value: any) {
        const mappedField = mapFieldName(opts.fieldPath)

        if (isAddressField(mappedField)) {
            const key = mappedField.startsWith('address.') ? mappedField.substring(8) : mappedField

            return {
                model: opts.model,
                id: String(opts.id),
                fields: {
                    address: {
                        [key]: value,
                    },
                },
            }
        }

        return {
            model: opts.model,
            id: String(opts.id),
            fields: {
                [mappedField]: value,
            },
        }
    }

    public async save(opts: SaveOptions, value: any, originalValue?: any): Promise<SaveResult> {
        console.log('🔍 [UniversalFieldSaver DEBUG] Starting save operation', {
            model: opts.model,
            id: opts.id,
            fieldPath: opts.fieldPath,
            value: value,
            originalValue: originalValue,
            timestamp: new Date().toISOString()
        })

        const key = this.keyFor(opts)
        if (this.inflight[key]) {
            console.log('⚠️ [UniversalFieldSaver DEBUG] Request already in flight, returning existing promise')
            return this.inflight[key] as Promise<SaveResult>
        }

        const promise = (async (): Promise<SaveResult> => {
            const maxRetries = opts.maxRetries ?? 2
            this.setSaving(key, true)

            let attempt = 0
            let lastErr: any = null

            while (attempt <= maxRetries) {
                try {
                    const payload = this.buildPayload(opts, value)
                    console.log('📤 [UniversalFieldSaver DEBUG] Attempting HTTP request', {
                        attempt: attempt + 1,
                        maxRetries: maxRetries + 1,
                        url: '/api/inline-edit',
                        method: 'PATCH',
                        payload: payload,
                        headers: opts.headers ?? {},
                    })

                    const response = await http.patch('/api/inline-edit', payload, {
                        headers: opts.headers ?? {},
                    })

                    console.log('✅ [UniversalFieldSaver DEBUG] HTTP Response received', {
                        status: response.status,
                        statusText: response.statusText,
                        data: response.data,
                        headers: response.headers,
                        attempt: attempt + 1
                    })

                    if (response.data.success) {
                        console.log('🎉 [UniversalFieldSaver DEBUG] Save successful!', {
                            fieldPath: opts.fieldPath,
                            value: value,
                            responseData: response.data
                        })

                        opts.toast?.add({
                            severity: 'success',
                            summary: 'Saved',
                            detail: `${this.humanize(opts.fieldPath)} saved`,
                            life: 2000,
                        })

                        opts.onSuccess?.(response.data)

                        return {
                            ok: true,
                            fieldPath: opts.fieldPath,
                            value,
                            data: response.data,
                        }
                    }

                    console.log('❌ [UniversalFieldSaver DEBUG] Save failed', {
                        message: response.data.message,
                        responseData: response.data,
                        status: response.status
                    })
                    throw new Error(response.data.message || 'Save failed')
                } catch (err: any) {
                    console.log('💥 [UniversalFieldSaver DEBUG] Exception occurred', {
                        attempt: attempt + 1,
                        maxRetries: maxRetries + 1,
                        error: err,
                        message: err.message,
                        stack: err.stack,
                        response: err.response,
                        status: err.response?.status,
                        statusText: err.response?.statusText
                    })
                    
                    lastErr = err
                    attempt++

                    if (attempt > maxRetries) break

                    console.log('🔄 [UniversalFieldSaver DEBUG] Retrying after backoff', {
                        attempt: attempt + 1,
                        backoffMs: this.backoffMs(attempt)
                    })
                    await new Promise((resolve) => setTimeout(resolve, this.backoffMs(attempt)))
                }
            }

            const status = lastErr?.response?.status
            const errorMessage = lastErr?.response?.data?.message || lastErr?.message || 'Unknown error'
            const fieldErrors = lastErr?.response?.data?.errors

            console.log('🚨 [UniversalFieldSaver DEBUG] Final error handling', {
                status: status,
                errorMessage: errorMessage,
                fieldErrors: fieldErrors,
                lastErr: lastErr,
                fieldPath: opts.fieldPath,
                totalAttempts: attempt
            })

            // Handle specific error cases with better user feedback
            const showError = (summary: string, detail: string, life: number = 5000) => {
                if (opts.toast) {
                    opts.toast.add({
                        severity: 'error',
                        summary,
                        detail,
                        life,
                    })
                } else {
                    // Fallback to console for debugging
                    console.error(`🚨 [UniversalFieldSaver] ${summary}: ${detail}`)
                    alert(`${summary}: ${detail}`)
                }
            }

            if (status === 403) {
                showError(
                    'Permission Denied', 
                    `You don't have permission to edit ${this.humanize(opts.fieldPath)}. Please contact your administrator.`,
                    8000
                )
            } else if (status === 404) {
                showError(
                    'Not Found', 
                    `The record you're trying to edit could not be found. It may have been deleted.`
                )
            } else if (status === 422 && fieldErrors) {
                const firstError = Object.values(fieldErrors)[0] as string[]
                showError(
                    'Validation Error', 
                    firstError?.[0] || 'Invalid value'
                )
            } else {
                showError(
                    'Save failed', 
                    `Could not save ${this.humanize(opts.fieldPath)}: ${errorMessage}`
                )
            }

            opts.onError?.(lastErr)

            return {
                ok: false,
                fieldPath: opts.fieldPath,
                error: lastErr,
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

    public isSaving(opts: SaveOptions) {
        return !!this.savingState.value[this.keyFor(opts)]
    }

    private setSaving(key: string, value: boolean) {
        this.savingState.value = { ...this.savingState.value, [key]: value }
    }

    public updateOptimistically<T extends Record<string, any>>(localData: T, fieldPath: string, newValue: any, originalValue?: any): T {
        if (originalValue !== undefined) {
            ;(localData as any)._originalValues = (localData as any)._originalValues || {}
            ;(localData as any)._originalValues[fieldPath] = originalValue
        }

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

    public rollbackOptimisticUpdate<T extends Record<string, any>>(localData: T, fieldPath: string): T {
        const originalValues = (localData as any)._originalValues

        if (!originalValues || !(fieldPath in originalValues)) {
            return localData
        }

        const parts = fieldPath.split('.')
        let current: any = localData

        for (let i = 0; i < parts.length - 1; i++) {
            const part = parts[i]
            if (!(part in current)) {
                return localData
            }
            current = current[part]
        }

        current[parts[parts.length - 1]] = originalValues[fieldPath]

        delete originalValues[fieldPath]

        return localData
    }
}

export default new UniversalFieldSaver()
