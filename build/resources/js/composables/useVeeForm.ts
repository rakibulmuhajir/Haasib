import { useForm } from 'vee-validate'
import { useForm as useInertiaForm } from '@inertiajs/vue3'
import type { TypedSchema } from 'vee-validate'

export interface UseVeeFormOptions<T> {
  schema: TypedSchema<T>
  initialValues?: Partial<T>
  submitUrl: string
  onSuccess?: () => void
  onError?: (errors: any) => void
}

export function useVeeForm<T extends Record<string, any>>(options: UseVeeFormOptions<T>) {
  const { schema, initialValues, submitUrl, onSuccess, onError } = options

  // VeeValidate form setup
  const veeForm = useForm({
    validationSchema: schema,
    initialValues: initialValues || {}
  })

  // Inertia form for submission
  const inertiaForm = useInertiaForm(initialValues || {} as T)

  // Submit handler
  const onSubmit = veeForm.handleSubmit((values) => {
    // Sync VeeValidate values with Inertia form
    Object.keys(values).forEach(key => {
      if (key in inertiaForm) {
        (inertiaForm as any)[key] = values[key]
      }
    })

    // Submit with Inertia
    inertiaForm.post(submitUrl, {
      onSuccess: () => {
        veeForm.resetForm()
        inertiaForm.reset()
        onSuccess?.()
      },
      onError: (errors) => {
        onError?.(errors)
      }
    })
  })

  return {
    ...veeForm,
    inertiaForm,
    onSubmit,
    isSubmitting: computed(() => inertiaForm.processing)
  }
}