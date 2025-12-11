<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Building2, Rocket, Loader2 } from 'lucide-vue-next'

interface Props {
  currencies: Array<{
    code: string
    name: string
    symbol: string
  }>
  guided?: boolean
}

const props = defineProps<Props>()

const form = useForm({
  name: '',
  base_currency: 'USD',
  country: '',
})

const submit = () => {
  form.post('/companies')
}
</script>

<template>
  <Head title="Create Company" />

  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="container mx-auto px-4 py-16 max-w-2xl">
      <!-- Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900 mb-4">
          <Building2 class="w-8 h-8 text-blue-600 dark:text-blue-400" />
        </div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-2">
          Create Your Company
        </h1>
        <p class="text-slate-600 dark:text-slate-400">
          <template v-if="guided">
            Let's start by creating your company. After this, we'll guide you through setting everything up.
          </template>
          <template v-else>
            Set up your company to get started with Haasib accounting
          </template>
        </p>
      </div>

      <!-- Guided Setup Badge -->
      <div v-if="guided" class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
        <div class="flex items-center gap-3">
          <Rocket class="w-5 h-5 text-blue-600 dark:text-blue-400" />
          <div class="text-sm">
            <p class="text-blue-900 dark:text-blue-100 font-medium">Guided Setup Mode</p>
            <p class="text-blue-700 dark:text-blue-300">After creating your company, we'll help you set up everything step by step.</p>
          </div>
        </div>
      </div>

      <!-- Form Card -->
      <Card>
        <CardHeader>
          <CardTitle>Company Information</CardTitle>
          <CardDescription>
            Enter your basic company details. You can add more information later.
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form @submit.prevent="submit" class="space-y-6">
            <!-- Company Name -->
            <div class="space-y-2">
              <Label for="name" class="font-medium">
                Company Name <span class="text-red-500">*</span>
              </Label>
              <Input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="e.g., Acme Corporation"
                required
                autofocus
              />
              <p class="text-xs text-slate-500 dark:text-slate-400">
                This is your legal business name
              </p>
              <p v-if="form.errors.name" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.name }}
              </p>
            </div>

            <!-- Base Currency -->
            <div class="space-y-2">
              <Label for="currency" class="font-medium">
                Base Currency <span class="text-red-500">*</span>
              </Label>
              <Select v-model="form.base_currency" required>
                <SelectTrigger id="currency">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="currency in currencies"
                    :key="currency.code"
                    :value="currency.code"
                  >
                    {{ currency.code }} - {{ currency.name }} ({{ currency.symbol }})
                  </SelectItem>
                </SelectContent>
              </Select>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                This is your primary accounting currency. You can work with multiple currencies later.
              </p>
              <p v-if="form.errors.base_currency" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.base_currency }}
              </p>
            </div>

            <!-- Country (Optional) -->
            <div class="space-y-2">
              <Label for="country" class="font-medium">
                Country (Optional)
              </Label>
              <Input
                id="country"
                v-model="form.country"
                type="text"
                placeholder="e.g., United States, Pakistan"
              />
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Where is your business primarily located?
              </p>
              <p v-if="form.errors.country" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.country }}
              </p>
            </div>

            <!-- Submit Button -->
            <div class="pt-4">
              <Button type="submit" :disabled="form.processing" class="w-full" size="lg">
                <Loader2 v-if="form.processing" class="w-4 h-4 mr-2 animate-spin" />
                <template v-if="guided">
                  Create Company & Continue to Setup
                </template>
                <template v-else>
                  Create Company
                </template>
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <!-- Help Text -->
      <div class="text-center mt-6 text-sm text-slate-600 dark:text-slate-400">
        <p>Need help? <a href="#" class="text-blue-600 hover:underline">Contact support</a></p>
      </div>
    </div>
  </div>
</template>
