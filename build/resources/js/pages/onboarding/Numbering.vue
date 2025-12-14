<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ArrowRight, ArrowLeft, Hash, Info } from 'lucide-vue-next'
import { computed } from 'vue'

interface Props {
  company: {
    id: string
    name: string
    slug: string
    invoice_prefix?: string
    invoice_start_number?: number
    bill_prefix?: string
    bill_start_number?: number
  }
}

const props = defineProps<Props>()

const form = useForm({
  invoice_prefix: props.company.invoice_prefix || 'INV-',
  invoice_start_number: props.company.invoice_start_number || 1001,
  bill_prefix: props.company.bill_prefix || 'BILL-',
  bill_start_number: props.company.bill_start_number || 1001,
})

const submit = () => {
  form.post(`/${props.company.slug}/onboarding/numbering`)
}

const goBack = () => {
  router.visit(`/${props.company.slug}/onboarding/tax-settings`)
}

const invoicePreview = computed(() => {
  return `${form.invoice_prefix}${String(form.invoice_start_number).padStart(5, '0')}`
})

const billPreview = computed(() => {
  return `${form.bill_prefix}${String(form.bill_start_number).padStart(5, '0')}`
})
</script>

<template>
  <Head title="Document Numbering" />

  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
      <!-- Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900 mb-4">
          <Hash class="w-8 h-8 text-blue-600 dark:text-blue-400" />
        </div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-2">
          Document Numbering
        </h1>
        <p class="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
          Set up automatic numbering for invoices and bills. This ensures professional, sequential document numbers.
        </p>
      </div>

      <!-- Progress Indicator -->
      <div class="mb-8">
        <div class="flex items-center justify-between max-w-2xl mx-auto">
          <div v-for="(step, index) in 7" :key="index" class="flex items-center">
            <div
              :class="[
                'w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-colors',
                index < 5 ? 'bg-green-600 text-white' :
                index === 5 ? 'bg-blue-600 text-white' :
                'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
              ]"
            >
              {{ index + 1 }}
            </div>
            <div
              v-if="index < 6"
              :class="[
                'w-12 h-0.5 mx-2',
                index < 5 ? 'bg-green-600' : 'bg-slate-200 dark:bg-slate-700',
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
          <span class="font-semibold text-blue-600">Numbering</span>
          <span>Terms</span>
        </div>
      </div>

      <!-- Form Card -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Hash class="w-5 h-5" />
            Automatic Document Numbering
          </CardTitle>
          <CardDescription>
            Configure how your invoices and bills are numbered
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form @submit.prevent="submit" class="space-y-8">
            <!-- Invoice Numbering -->
            <div class="space-y-4">
              <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 border-b pb-2">
                Invoice Numbering
              </h3>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Invoice Prefix -->
                <div class="space-y-2">
                  <Label for="invoice_prefix" class="font-medium">
                    Prefix <span class="text-red-500">*</span>
                  </Label>
                  <Input
                    id="invoice_prefix"
                    v-model="form.invoice_prefix"
                    type="text"
                    placeholder="e.g., INV-"
                    required
                  />
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    Text that appears before the number (e.g., "INV-", "SI-", "2025-")
                  </p>
                  <p v-if="form.errors.invoice_prefix" class="text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.invoice_prefix }}
                  </p>
                </div>

                <!-- Invoice Start Number -->
                <div class="space-y-2">
                  <Label for="invoice_start_number" class="font-medium">
                    Starting Number <span class="text-red-500">*</span>
                  </Label>
                  <Input
                    id="invoice_start_number"
                    v-model.number="form.invoice_start_number"
                    type="number"
                    min="1"
                    placeholder="e.g., 1001"
                    required
                  />
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    First invoice number (usually 1001 or 1)
                  </p>
                  <p v-if="form.errors.invoice_start_number" class="text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.invoice_start_number }}
                  </p>
                </div>
              </div>

              <!-- Invoice Preview -->
              <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <p class="text-sm text-blue-900 dark:text-blue-100 mb-2">
                  <strong>Preview:</strong> Your first invoice will be numbered:
                </p>
                <p class="text-2xl font-mono font-bold text-blue-700 dark:text-blue-300">
                  {{ invoicePreview }}
                </p>
                <p class="text-xs text-blue-600 dark:text-blue-400 mt-2">
                  Numbers automatically increment: {{ invoicePreview }}, {{ form.invoice_prefix }}{{ String(form.invoice_start_number + 1).padStart(5, '0') }}, {{ form.invoice_prefix }}{{ String(form.invoice_start_number + 2).padStart(5, '0') }}, ...
                </p>
              </div>
            </div>

            <!-- Bill Numbering -->
            <div class="space-y-4 pt-6 border-t">
              <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 border-b pb-2">
                Bill Numbering
              </h3>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Bill Prefix -->
                <div class="space-y-2">
                  <Label for="bill_prefix" class="font-medium">
                    Prefix <span class="text-red-500">*</span>
                  </Label>
                  <Input
                    id="bill_prefix"
                    v-model="form.bill_prefix"
                    type="text"
                    placeholder="e.g., BILL-"
                    required
                  />
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    Text that appears before the number (e.g., "BILL-", "PO-", "PUR-")
                  </p>
                  <p v-if="form.errors.bill_prefix" class="text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.bill_prefix }}
                  </p>
                </div>

                <!-- Bill Start Number -->
                <div class="space-y-2">
                  <Label for="bill_start_number" class="font-medium">
                    Starting Number <span class="text-red-500">*</span>
                  </Label>
                  <Input
                    id="bill_start_number"
                    v-model.number="form.bill_start_number"
                    type="number"
                    min="1"
                    placeholder="e.g., 1001"
                    required
                  />
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    First bill number (usually 1001 or 1)
                  </p>
                  <p v-if="form.errors.bill_start_number" class="text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.bill_start_number }}
                  </p>
                </div>
              </div>

              <!-- Bill Preview -->
              <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <p class="text-sm text-blue-900 dark:text-blue-100 mb-2">
                  <strong>Preview:</strong> Your first bill will be numbered:
                </p>
                <p class="text-2xl font-mono font-bold text-blue-700 dark:text-blue-300">
                  {{ billPreview }}
                </p>
                <p class="text-xs text-blue-600 dark:text-blue-400 mt-2">
                  Numbers automatically increment: {{ billPreview }}, {{ form.bill_prefix }}{{ String(form.bill_start_number + 1).padStart(5, '0') }}, {{ form.bill_prefix }}{{ String(form.bill_start_number + 2).padStart(5, '0') }}, ...
                </p>
              </div>
            </div>

            <!-- Info Box -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
              <div class="flex items-start gap-3">
                <Info class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                <div class="text-sm">
                  <p class="text-blue-900 dark:text-blue-100 font-medium mb-1">
                    Sequential numbering is important
                  </p>
                  <p class="text-blue-700 dark:text-blue-300">
                    Tax authorities often require sequential invoice numbers. The system ensures no gaps or duplicates.
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
                Continue to Payment Terms
                <ArrowRight class="w-4 h-4 ml-2" />
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
