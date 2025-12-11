<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { ArrowRight, ArrowLeft, Calendar, Info } from 'lucide-vue-next'
import { computed } from 'vue'

interface Props {
  company: {
    id: string
    name: string
    slug: string
    fiscal_year_start_month?: number
    period_frequency?: string
  }
  months: Array<{
    value: number
    label: string
  }>
}

const props = defineProps<Props>()

const form = useForm({
  fiscal_year_start_month: props.company.fiscal_year_start_month || 1,
  period_frequency: props.company.period_frequency || 'monthly',
})

const submit = () => {
  form.post(`/${props.company.slug}/onboarding/fiscal-year`)
}

const goBack = () => {
  router.visit(`/${props.company.slug}/onboarding/company-identity`)
}

const selectedMonthName = computed(() => {
  const month = props.months.find(m => m.value === form.fiscal_year_start_month)
  return month?.label || 'January'
})

const fiscalYearExample = computed(() => {
  const month = form.fiscal_year_start_month
  const now = new Date()
  const currentYear = now.getFullYear()
  const currentMonth = now.getMonth() + 1

  if (currentMonth >= month) {
    return `FY ${currentYear}-${currentYear + 1}`
  } else {
    return `FY ${currentYear - 1}-${currentYear}`
  }
})
</script>

<template>
  <Head title="Fiscal Year Setup" />

  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
      <!-- Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900 mb-4">
          <Calendar class="w-8 h-8 text-blue-600 dark:text-blue-400" />
        </div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-2">
          Fiscal Year Setup
        </h1>
        <p class="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
          Define when your financial year starts and how often you want to close accounting periods
        </p>
      </div>

      <!-- Progress Indicator -->
      <div class="mb-8">
        <div class="flex items-center justify-between max-w-2xl mx-auto">
          <div v-for="(step, index) in 7" :key="index" class="flex items-center">
            <div
              :class="[
                'w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-colors',
                index === 0 ? 'bg-green-600 text-white' :
                index === 1 ? 'bg-blue-600 text-white' :
                'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
              ]"
            >
              {{ index + 1 }}
            </div>
            <div
              v-if="index < 6"
              :class="[
                'w-12 h-0.5 mx-2',
                index < 1 ? 'bg-green-600' : 'bg-slate-200 dark:bg-slate-700',
              ]"
            />
          </div>
        </div>
        <div class="flex justify-between max-w-2xl mx-auto mt-2 text-xs text-slate-600 dark:text-slate-400">
          <span class="text-green-600 dark:text-green-400">Identity</span>
          <span class="font-semibold text-blue-600 dark:text-blue-400">Fiscal Year</span>
          <span>Bank Accounts</span>
          <span>Defaults</span>
          <span>Tax</span>
          <span>Numbering</span>
          <span>Terms</span>
        </div>
      </div>

      <!-- Form Card -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Calendar class="w-5 h-5" />
            Fiscal Year & Accounting Periods
          </CardTitle>
          <CardDescription>
            Your fiscal year defines the time period for financial reporting and tax purposes
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form @submit.prevent="submit" class="space-y-8">
            <!-- Fiscal Year Start Month -->
            <div class="space-y-4">
              <div>
                <Label for="start_month" class="text-base font-semibold">
                  When does your fiscal year start? <span class="text-red-500">*</span>
                </Label>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                  Most businesses use January (calendar year). Some use July or April (tax year).
                </p>
              </div>

              <Select v-model="form.fiscal_year_start_month" required>
                <SelectTrigger id="start_month" class="max-w-xs">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="month in months"
                    :key="month.value"
                    :value="month.value"
                  >
                    {{ month.label }}
                  </SelectItem>
                </SelectContent>
              </Select>

              <!-- Example Display -->
              <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-start gap-3">
                  <Info class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                  <div>
                    <p class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-1">
                      Your current fiscal year
                    </p>
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                      With {{ selectedMonthName }} as start month, your fiscal year is: <span class="font-semibold">{{ fiscalYearExample }}</span>
                    </p>
                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                      Starts: {{ selectedMonthName }} 1 • Ends: {{ months[(form.fiscal_year_start_month === 1 ? 11 : form.fiscal_year_start_month - 2)].label }} 31
                    </p>
                  </div>
                </div>
              </div>

              <p v-if="form.errors.fiscal_year_start_month" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.fiscal_year_start_month }}
              </p>
            </div>

            <!-- Period Frequency -->
            <div class="space-y-4 pt-6 border-t">
              <div>
                <Label class="text-base font-semibold">
                  How often do you want to close periods? <span class="text-red-500">*</span>
                </Label>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                  Accounting periods control when you can post transactions. Most businesses close monthly.
                </p>
              </div>

              <RadioGroup v-model="form.period_frequency" class="space-y-3">
                <div class="flex items-start space-x-3 border rounded-lg p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                  <RadioGroupItem value="monthly" id="monthly" class="mt-1" />
                  <div class="flex-1">
                    <Label for="monthly" class="text-base font-medium cursor-pointer">
                      Monthly (Recommended)
                    </Label>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                      Close books every month. 12 periods per year. Best for detailed reporting.
                    </p>
                  </div>
                </div>

                <div class="flex items-start space-x-3 border rounded-lg p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                  <RadioGroupItem value="quarterly" id="quarterly" class="mt-1" />
                  <div class="flex-1">
                    <Label for="quarterly" class="text-base font-medium cursor-pointer">
                      Quarterly
                    </Label>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                      Close books every 3 months. 4 periods per year. Simpler but less granular.
                    </p>
                  </div>
                </div>

                <div class="flex items-start space-x-3 border rounded-lg p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                  <RadioGroupItem value="yearly" id="yearly" class="mt-1" />
                  <div class="flex-1">
                    <Label for="yearly" class="text-base font-medium cursor-pointer">
                      Yearly
                    </Label>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                      Close books once per year. 1 period. Only for very small businesses.
                    </p>
                  </div>
                </div>
              </RadioGroup>

              <p v-if="form.errors.period_frequency" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.period_frequency }}
              </p>
            </div>

            <!-- Actions -->
            <div class="flex justify-between pt-6 border-t">
              <Button type="button" variant="outline" @click="goBack" :disabled="form.processing">
                <ArrowLeft class="w-4 h-4 mr-2" />
                Back
              </Button>
              <Button type="submit" :disabled="form.processing">
                Continue to Bank Accounts
                <ArrowRight class="w-4 h-4 ml-2" />
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <!-- Help Text -->
      <div class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">
        <p>💡 You can change these settings later in company settings</p>
      </div>
    </div>
  </div>
</template>
