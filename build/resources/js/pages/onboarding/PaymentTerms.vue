<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ArrowRight, ArrowLeft, Calendar, Info } from 'lucide-vue-next'

interface Props {
  company: {
    id: string
    name: string
    slug: string
    default_customer_payment_terms?: number
    default_vendor_payment_terms?: number
  }
}

const props = defineProps<Props>()

const form = useForm({
  default_customer_payment_terms: props.company.default_customer_payment_terms || 30,
  default_vendor_payment_terms: props.company.default_vendor_payment_terms || 30,
})

const submit = () => {
  form.post(`/${props.company.slug}/onboarding/payment-terms`)
}

const goBack = () => {
  router.visit(`/${props.company.slug}/onboarding/numbering`)
}

const presetTerms = [
  { label: 'Due on receipt', value: 0 },
  { label: 'Net 15', value: 15 },
  { label: 'Net 30', value: 30 },
  { label: 'Net 45', value: 45 },
  { label: 'Net 60', value: 60 },
  { label: 'Net 90', value: 90 },
]

const setCustomerPreset = (days: number) => {
  form.default_customer_payment_terms = days
}

const setVendorPreset = (days: number) => {
  form.default_vendor_payment_terms = days
}
</script>

<template>
  <Head title="Payment Terms" />

  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
      <!-- Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900 mb-4">
          <Calendar class="w-8 h-8 text-blue-600 dark:text-blue-400" />
        </div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-2">
          Payment Terms
        </h1>
        <p class="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
          Set default payment terms for your customers and vendors. This determines when payments are due.
        </p>
      </div>

      <!-- Progress Indicator -->
      <div class="mb-8">
        <div class="flex items-center justify-between max-w-2xl mx-auto">
          <div v-for="(step, index) in 7" :key="index" class="flex items-center">
            <div
              :class="[
                'w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-colors',
                index < 6 ? 'bg-green-600 text-white' :
                index === 6 ? 'bg-blue-600 text-white' :
                'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
              ]"
            >
              {{ index + 1 }}
            </div>
            <div
              v-if="index < 6"
              :class="[
                'w-12 h-0.5 mx-2',
                index < 6 ? 'bg-green-600' : 'bg-slate-200 dark:bg-slate-700',
              ]"
            />
          </div>
        </div>
        <div class="flex justify-between max-w-2xl mx-auto mt-2 text-xs text-slate-600 dark:text-slate-400">
          <span class="text-green-600">Identity</span>
          <span class="text-green-600">Fiscal Year</span>
          <span class="text-green-600">Bank Accounts</span>
          <span class="text-green-600">Defaults</span>
          <span class="text-green-600">Tax</span>
          <span class="text-green-600">Numbering</span>
          <span class="font-semibold text-blue-600">Terms</span>
        </div>
      </div>

      <!-- Form Card -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Calendar class="w-5 h-5" />
            Default Payment Terms
          </CardTitle>
          <CardDescription>
            Configure when payments are typically due for your business
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form @submit.prevent="submit" class="space-y-8">
            <!-- Customer Payment Terms -->
            <div class="space-y-4">
              <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 border-b pb-2">
                Customer Payment Terms
              </h3>

              <div>
                <Label class="text-sm text-slate-600 dark:text-slate-400 mb-3 block">
                  How many days do you typically give customers to pay invoices?
                </Label>

                <!-- Quick Presets -->
                <div class="flex flex-wrap gap-2 mb-4">
                  <Button
                    v-for="preset in presetTerms"
                    :key="preset.value"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="setCustomerPreset(preset.value)"
                    :class="form.default_customer_payment_terms === preset.value ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20' : ''"
                  >
                    {{ preset.label }}
                  </Button>
                </div>

                <!-- Custom Input -->
                <div class="space-y-2">
                  <Label for="customer_terms" class="font-medium">
                    Days Until Payment Due <span class="text-red-500">*</span>
                  </Label>
                  <div class="flex items-center gap-2 max-w-xs">
                    <Input
                      id="customer_terms"
                      v-model.number="form.default_customer_payment_terms"
                      type="number"
                      min="0"
                      max="365"
                      required
                      class="w-24"
                    />
                    <span class="text-sm text-slate-600 dark:text-slate-400">days</span>
                  </div>
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    Due date will be automatically calculated when creating invoices
                  </p>
                  <p v-if="form.errors.default_customer_payment_terms" class="text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.default_customer_payment_terms }}
                  </p>
                </div>

                <!-- Example -->
                <div class="bg-slate-50 dark:bg-slate-800 border rounded-lg p-3 mt-3">
                  <p class="text-sm text-slate-700 dark:text-slate-300">
                    <strong>Example:</strong> Invoice created today will be due in <strong>{{ form.default_customer_payment_terms }}</strong> days
                    <template v-if="form.default_customer_payment_terms === 0">(immediate payment)</template>
                  </p>
                </div>
              </div>
            </div>

            <!-- Vendor Payment Terms -->
            <div class="space-y-4 pt-6 border-t">
              <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 border-b pb-2">
                Vendor Payment Terms
              </h3>

              <div>
                <Label class="text-sm text-slate-600 dark:text-slate-400 mb-3 block">
                  How many days do vendors typically give you to pay bills?
                </Label>

                <!-- Quick Presets -->
                <div class="flex flex-wrap gap-2 mb-4">
                  <Button
                    v-for="preset in presetTerms"
                    :key="preset.value"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="setVendorPreset(preset.value)"
                    :class="form.default_vendor_payment_terms === preset.value ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/20' : ''"
                  >
                    {{ preset.label }}
                  </Button>
                </div>

                <!-- Custom Input -->
                <div class="space-y-2">
                  <Label for="vendor_terms" class="font-medium">
                    Days Until Payment Due <span class="text-red-500">*</span>
                  </Label>
                  <div class="flex items-center gap-2 max-w-xs">
                    <Input
                      id="vendor_terms"
                      v-model.number="form.default_vendor_payment_terms"
                      type="number"
                      min="0"
                      max="365"
                      required
                      class="w-24"
                    />
                    <span class="text-sm text-slate-600 dark:text-slate-400">days</span>
                  </div>
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    Due date will be automatically calculated when recording bills
                  </p>
                  <p v-if="form.errors.default_vendor_payment_terms" class="text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.default_vendor_payment_terms }}
                  </p>
                </div>

                <!-- Example -->
                <div class="bg-slate-50 dark:bg-slate-800 border rounded-lg p-3 mt-3">
                  <p class="text-sm text-slate-700 dark:text-slate-300">
                    <strong>Example:</strong> Bill recorded today will be due in <strong>{{ form.default_vendor_payment_terms }}</strong> days
                    <template v-if="form.default_vendor_payment_terms === 0">(immediate payment)</template>
                  </p>
                </div>
              </div>
            </div>

            <!-- Info Box -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
              <div class="flex items-start gap-3">
                <Info class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                <div class="text-sm">
                  <p class="text-blue-900 dark:text-blue-100 font-medium mb-1">
                    Defaults vs. Custom Terms
                  </p>
                  <p class="text-blue-700 dark:text-blue-300">
                    These are defaults to save you time. You can always set specific payment terms for individual customers or vendors, and override on any invoice or bill.
                  </p>
                </div>
              </div>
            </div>

            <!-- Validation Errors -->
            <div v-if="Object.keys(form.errors).length > 0" class="text-sm text-red-600 dark:text-red-400">
              <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
            </div>

            <!-- Actions -->
            <div class="flex justify-between pt-6 border-t">
              <Button type="button" variant="outline" @click="goBack" :disabled="form.processing">
                <ArrowLeft class="w-4 h-4 mr-2" />
                Back
              </Button>
              <Button type="submit" :disabled="form.processing">
                Complete Setup
                <ArrowRight class="w-4 h-4 ml-2" />
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <!-- Help Text -->
      <div class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">
        <p>💡 Almost done! One more step to complete your setup</p>
      </div>
    </div>
  </div>
</template>
