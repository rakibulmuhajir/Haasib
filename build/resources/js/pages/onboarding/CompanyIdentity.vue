<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { Building, ArrowRight, Briefcase } from 'lucide-vue-next'

interface Props {
  company: {
    id: string
    name: string
    slug: string
    base_currency: string
    industry_code?: string
    registration_number?: string
    trade_name?: string
    timezone?: string
  }
  industries: Array<{
    code: string
    name: string
    description?: string
  }>
  timezones: Record<string, string>
}

const props = defineProps<Props>()

const form = useForm({
  industry_code: props.company.industry_code || '',
  registration_number: props.company.registration_number || '',
  trade_name: props.company.trade_name || '',
  timezone: props.company.timezone || 'Asia/Karachi',
})

const submit = () => {
  form.post(`/${props.company.slug}/onboarding/company-identity`)
}
</script>

<template>
  <Head :title="`Setup ${company.name}`" />

  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
      <!-- Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900 mb-4">
          <Building class="w-8 h-8 text-blue-600 dark:text-blue-400" />
        </div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-2">
          Welcome to {{ company.name }}!
        </h1>
        <p class="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
          Let's set up your company in just a few steps. We'll customize your accounting system based on your industry.
        </p>
      </div>

      <!-- Progress Indicator -->
      <div class="mb-8">
        <div class="flex items-center justify-between max-w-2xl mx-auto">
          <div v-for="(step, index) in 7" :key="index" class="flex items-center">
            <div
              :class="[
                'w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-colors',
                index === 0
                  ? 'bg-blue-600 text-white'
                  : 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
              ]"
            >
              {{ index + 1 }}
            </div>
            <div
              v-if="index < 6"
              :class="[
                'w-12 h-0.5 mx-2',
                'bg-slate-200 dark:bg-slate-700',
              ]"
            />
          </div>
        </div>
        <div class="flex justify-between max-w-2xl mx-auto mt-2 text-xs text-slate-600 dark:text-slate-400">
          <span class="font-semibold text-blue-600 dark:text-blue-400">Identity</span>
          <span>Fiscal Year</span>
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
            <Briefcase class="w-5 h-5" />
            Company Identity & Industry
          </CardTitle>
          <CardDescription>
            Select your industry to get a pre-configured chart of accounts tailored to your business
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form @submit.prevent="submit" class="space-y-6">
            <!-- Industry Selection -->
            <div class="space-y-2">
              <Label for="industry" class="text-base font-semibold">
                Industry <span class="text-red-500">*</span>
              </Label>
              <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">
                Your chart of accounts will be customized based on your industry
              </p>
              <Select v-model="form.industry_code" required>
                <SelectTrigger id="industry">
                  <SelectValue placeholder="Select your industry..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="industry in industries"
                    :key="industry.code"
                    :value="industry.code"
                  >
                    <div class="py-1">
                      <div class="font-medium">{{ industry.name }}</div>
                      <div v-if="industry.description" class="text-xs text-slate-500 dark:text-slate-400">
                        {{ industry.description }}
                      </div>
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.industry_code" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.industry_code }}
              </p>
            </div>

            <!-- Registration Number -->
            <div class="space-y-2">
              <Label for="registration_number">
                Registration / Tax Number
              </Label>
              <Input
                id="registration_number"
                v-model="form.registration_number"
                type="text"
                placeholder="e.g., NTN-1234567"
                class="max-w-md"
              />
              <p class="text-sm text-slate-500 dark:text-slate-400">
                Your business registration or tax identification number
              </p>
              <p v-if="form.errors.registration_number" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.registration_number }}
              </p>
            </div>

            <!-- Trade Name -->
            <div class="space-y-2">
              <Label for="trade_name">
                Trading Name (Optional)
              </Label>
              <Input
                id="trade_name"
                v-model="form.trade_name"
                type="text"
                placeholder="If different from legal name"
                class="max-w-md"
              />
              <p class="text-sm text-slate-500 dark:text-slate-400">
                The name you use for business operations (if different from legal name)
              </p>
              <p v-if="form.errors.trade_name" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.trade_name }}
              </p>
            </div>

            <!-- Timezone -->
            <div class="space-y-2">
              <Label for="timezone" class="text-base font-semibold">
                Timezone <span class="text-red-500">*</span>
              </Label>
              <Select v-model="form.timezone" required>
                <SelectTrigger id="timezone" class="max-w-md">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="(label, tz) in timezones"
                    :key="tz"
                    :value="tz"
                  >
                    {{ label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p class="text-sm text-slate-500 dark:text-slate-400">
                Used for reports and transaction timestamps
              </p>
              <p v-if="form.errors.timezone" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.timezone }}
              </p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end pt-6 border-t">
              <Button
                type="submit"
                :disabled="form.processing || !form.industry_code"
                class="gap-2"
              >
                Continue to Fiscal Year
                <ArrowRight class="w-4 h-4" />
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <!-- Help Text -->
      <div class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">
        <p>Need help? Check our <a href="#" class="text-blue-600 hover:underline">setup guide</a> or <a href="#" class="text-blue-600 hover:underline">contact support</a></p>
      </div>
    </div>
  </div>
</template>
