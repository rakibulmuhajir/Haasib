<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Field } from '@/components/ui/field'
import { useForm as useInertiaForm } from '@inertiajs/vue3'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import CurrencySelector from '@/components/currency/CurrencySelector.vue'

// Define validation schema
const customerSchema = toTypedSchema(z.object({
  name: z.string().min(1, 'Customer name is required'),
  email: z.string().email('Invalid email').optional().or(z.literal('')),
  preferred_currency_code: z.string().length(3).optional(),
}))

// Props
interface CustomerFormProps {
  initialData?: {
    name?: string
    email?: string
    preferred_currency_code?: string
  }
  submitUrl?: string
  onSuccess?: () => void
  isMultiCurrencyEnabled?: boolean
  currencies?: Array<{
    code: string
    name: string
    symbol: string
    display_name: string
    is_base?: boolean
  }>
  baseCurrency?: {
    code: string
    name: string
    symbol: string
  }
}

const props = withDefaults(defineProps<CustomerFormProps>(), {
  submitUrl: '/accounting/customers',
  initialData: () => ({ name: '', email: '' })
})

// VeeValidate form
const { defineField, handleSubmit, errors, resetForm } = useForm({
  validationSchema: customerSchema,
  initialValues: props.initialData
})

// Define form fields
const [name, nameAttrs] = defineField('name')
const [email, emailAttrs] = defineField('email')
const [preferred_currency_code, preferredCurrencyAttrs] = defineField('preferred_currency_code')

// Inertia form for submission
const inertiaForm = useInertiaForm({
  name: '',
  email: '',
  preferred_currency_code: ''
})

// Handle form submission
const onSubmit = handleSubmit((values) => {
  // Sync VeeValidate values with Inertia form
  inertiaForm.name = values.name
  inertiaForm.email = values.email || ''
  inertiaForm.preferred_currency_code = values.preferred_currency_code || ''

  // Submit with Inertia
  inertiaForm.post(props.submitUrl, {
    onSuccess: () => {
      resetForm()
      inertiaForm.reset()
      props.onSuccess?.()
    }
  })
})
</script>

<template>
  <form @submit="onSubmit" class="flex items-center gap-2">
    <Field name="name">
      <Input
        v-model="name"
        v-bind="nameAttrs"
        type="text"
        placeholder="Customer name"
        class="w-48"
        :class="{ 'border-red-500': errors.name }"
      />
      <div v-if="errors.name" class="text-sm text-red-500 mt-1">
        {{ errors.name }}
      </div>
    </Field>

    <Field name="email">
      <Input
        v-model="email"
        v-bind="emailAttrs"
        type="email"
        placeholder="Email (optional)"
        class="w-48"
        :class="{ 'border-red-500': errors.email }"
      />
      <div v-if="errors.email" class="text-sm text-red-500 mt-1">
        {{ errors.email }}
      </div>
    </Field>

    <!-- Preferred Currency - Only show when multi-currency is enabled -->
    <Field v-if="props.isMultiCurrencyEnabled" name="preferred_currency_code">
      <CurrencySelector
        v-model="preferred_currency_code"
        v-bind="preferredCurrencyAttrs"
        :currencies="props.currencies || []"
        placeholder="Select preferred currency"
        class="w-48"
      />
      <div v-if="errors.preferred_currency_code" class="text-sm text-red-500 mt-1">
        {{ errors.preferred_currency_code }}
      </div>
    </Field>

    <Button type="submit" :disabled="inertiaForm.processing">
      {{ inertiaForm.processing ? 'Saving...' : 'Add Customer' }}
    </Button>
  </form>
</template>