<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { ArrowRight, ArrowLeft, FileText, Info } from 'lucide-vue-next'
import { computed } from 'vue'

interface Props {
  company: {
    id: string
    name: string
    slug: string
    tax_registered?: boolean
    tax_rate?: number
    tax_inclusive?: boolean
  }
}

const props = defineProps<Props>()

const form = useForm({
  tax_registered: props.company.tax_registered ?? false,
  tax_rate: props.company.tax_rate || 0,
  tax_inclusive: props.company.tax_inclusive ?? false,
})

const submit = () => {
  form.post(`/${props.company.slug}/onboarding/tax-settings`)
}

const goBack = () => {
  router.visit(`/${props.company.slug}/onboarding/default-accounts`)
}

const showTaxFields = computed(() => form.tax_registered)

const taxRegisteredValue = computed({
  get: () => (form.tax_registered ? 'true' : 'false'),
  set: (value: string) => {
    form.tax_registered = value === 'true'
  },
})

const taxInclusiveValue = computed({
  get: () => (form.tax_inclusive ? 'true' : 'false'),
  set: (value: string) => {
    form.tax_inclusive = value === 'true'
  },
})
</script>

<template>
  <Head title="Tax Settings" />

  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
      <!-- Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900 mb-4">
          <FileText class="w-8 h-8 text-blue-600 dark:text-blue-400" />
        </div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-2">
          Tax Settings
        </h1>
        <p class="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
          Configure your sales tax or VAT settings. You can skip this step if you're not registered for tax.
        </p>
      </div>

      <!-- Progress Indicator -->
      <div class="mb-8">
        <div class="flex items-center justify-between max-w-2xl mx-auto">
          <div v-for="(step, index) in 7" :key="index" class="flex items-center">
            <div
              :class="[
                'w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-colors',
                index < 4 ? 'bg-green-600 text-white' :
                index === 4 ? 'bg-blue-600 text-white' :
                'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
              ]"
            >
              {{ index + 1 }}
            </div>
            <div
              v-if="index < 6"
              :class="[
                'w-12 h-0.5 mx-2',
                index < 4 ? 'bg-green-600' : 'bg-slate-200 dark:bg-slate-700',
              ]"
            />
          </div>
        </div>
        <div class="flex justify-between max-w-2xl mx-auto mt-2 text-xs text-slate-600 dark:text-slate-400">
          <span class="text-green-600">Identity</span>
          <span class="text-green-600">Fiscal Year</span>
          <span class="text-green-600">Bank Accounts</span>
          <span class="text-green-600">Defaults</span>
          <span class="font-semibold text-blue-600">Tax</span>
          <span>Numbering</span>
          <span>Terms</span>
        </div>
      </div>

      <!-- Form Card -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <FileText class="w-5 h-5" />
            Sales Tax & VAT Configuration
          </CardTitle>
          <CardDescription>
            Set up your tax settings for invoices and bills
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form @submit.prevent="submit" class="space-y-8">
            <!-- Tax Registration Status -->
            <div class="space-y-4">
              <div>
                <Label class="text-base font-semibold">
                  Are you registered for sales tax or VAT? <span class="text-red-500">*</span>
                </Label>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                  If you collect tax from customers or reclaim tax from vendors, select Yes
                </p>
              </div>

              <RadioGroup v-model="taxRegisteredValue" class="space-y-3">
                <div class="flex items-start space-x-3 border rounded-lg p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                  <RadioGroupItem value="true" id="tax_yes" class="mt-1" />
                  <div class="flex-1">
                    <Label for="tax_yes" class="text-base font-medium cursor-pointer">
                      Yes, I'm registered
                    </Label>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                      I collect sales tax/VAT from customers and need to track it
                    </p>
                  </div>
                </div>

                <div class="flex items-start space-x-3 border rounded-lg p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                  <RadioGroupItem value="false" id="tax_no" class="mt-1" />
                  <div class="flex-1">
                    <Label for="tax_no" class="text-base font-medium cursor-pointer">
                      No, I'm not registered
                    </Label>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                      I don't charge tax or I'm below the registration threshold
                    </p>
                  </div>
                </div>
              </RadioGroup>

              <p v-if="form.errors.tax_registered" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.tax_registered }}
              </p>
            </div>

            <!-- Tax Rate (Conditional) -->
            <div v-if="showTaxFields" class="space-y-4 pt-6 border-t">
              <div class="space-y-2">
                <Label for="tax_rate" class="text-base font-semibold">
                  Default Tax Rate (%) <span class="text-red-500">*</span>
                </Label>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                  Enter your standard sales tax or VAT rate (e.g., 18 for 18%)
                </p>
                <Input
                  id="tax_rate"
                  v-model.number="form.tax_rate"
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  placeholder="e.g., 18.00"
                  class="max-w-xs"
                  required
                />
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  You can override this rate on individual invoices if needed
                </p>
                <p v-if="form.errors.tax_rate" class="text-sm text-red-600 dark:text-red-400">
                  {{ form.errors.tax_rate }}
                </p>
              </div>

              <!-- Tax Inclusive (Conditional) -->
              <div class="space-y-4 pt-4">
                <div>
                  <Label class="text-base font-semibold">
                    Tax calculation method <span class="text-red-500">*</span>
                  </Label>
                  <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                    Choose how tax is calculated on your invoices
                  </p>
                </div>

                <RadioGroup v-model="taxInclusiveValue" class="space-y-3">
                  <div class="flex items-start space-x-3 border rounded-lg p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <RadioGroupItem value="false" id="tax_exclusive" class="mt-1" />
                    <div class="flex-1">
                      <Label for="tax_exclusive" class="text-base font-medium cursor-pointer">
                        Tax Exclusive (Recommended)
                      </Label>
                      <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                        Tax is added on top of the item price
                      </p>
                      <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 font-mono">
                        Example: $100 item + 18% tax = $118 total
                      </p>
                    </div>
                  </div>

                  <div class="flex items-start space-x-3 border rounded-lg p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <RadioGroupItem value="true" id="tax_inclusive" class="mt-1" />
                    <div class="flex-1">
                      <Label for="tax_inclusive" class="text-base font-medium cursor-pointer">
                        Tax Inclusive
                      </Label>
                      <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                        Tax is included within the item price
                      </p>
                      <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 font-mono">
                        Example: $118 total includes $18 tax (18%)
                      </p>
                    </div>
                  </div>
                </RadioGroup>

                <p v-if="form.errors.tax_inclusive" class="text-sm text-red-600 dark:text-red-400">
                  {{ form.errors.tax_inclusive }}
                </p>
              </div>
            </div>

            <!-- Info Box -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
              <div class="flex items-start gap-3">
                <Info class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                <div class="text-sm">
                  <p class="text-blue-900 dark:text-blue-100 font-medium mb-1">
                    Don't worry if you're unsure
                  </p>
                  <p class="text-blue-700 dark:text-blue-300">
                    You can always change these settings later in company settings. Most businesses use tax-exclusive pricing.
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
                Continue to Numbering
                <ArrowRight class="w-4 h-4 ml-2" />
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
