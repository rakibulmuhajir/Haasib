<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { CheckCircle2, Rocket, FileText, Users, Building2, TrendingUp } from 'lucide-vue-next'

interface Props {
  company: {
    id: string
    name: string
    slug: string
    industry_code?: string
  }
  summary: {
    accounts_created: number
    periods_created: number
    bank_accounts_created: number
    defaults_configured: boolean
    tax_configured: boolean
  }
}

const props = defineProps<Props>()

const form = useForm({})

const completeSetup = () => {
  form.post(`/${props.company.slug}/onboarding/complete`)
}

const nextSteps = [
  {
    icon: Users,
    title: 'Add Your First Customer',
    description: 'Start by adding customers you do business with',
    action: 'Go to Customers',
    url: `/${props.company.slug}/customers/create`,
  },
  {
    icon: FileText,
    title: 'Create Your First Invoice',
    description: 'Send professional invoices and get paid faster',
    action: 'Create Invoice',
    url: `/${props.company.slug}/invoices/create`,
  },
  {
    icon: Building2,
    title: 'Add Vendors',
    description: 'Track vendors and bills you need to pay',
    action: 'Go to Vendors',
    url: `/${props.company.slug}/vendors/create`,
  },
  {
    icon: TrendingUp,
    title: 'Explore Reports',
    description: 'View profit & loss, balance sheet, and more',
    action: 'View Reports',
    url: `/${props.company.slug}/reports`,
  },
]

const navigateToStep = (url: string) => {
  router.visit(url)
}
</script>

<template>
  <Head title="Setup Complete!" />

  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
      <!-- Success Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 dark:bg-green-900 mb-4 animate-bounce">
          <CheckCircle2 class="w-12 h-12 text-green-600 dark:text-green-400" />
        </div>
        <h1 class="text-4xl font-bold text-slate-900 dark:text-slate-100 mb-2">
          All Set! 🎉
        </h1>
        <p class="text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
          {{ company.name }} is ready to use. Your accounting system has been fully configured.
        </p>
      </div>

      <!-- Progress Indicator - All Complete -->
      <div class="mb-8">
        <div class="flex items-center justify-between max-w-2xl mx-auto">
          <div v-for="(step, index) in 7" :key="index" class="flex items-center">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold bg-green-600 text-white">
              <CheckCircle2 class="w-5 h-5" />
            </div>
            <div
              v-if="index < 6"
              class="w-12 h-0.5 mx-2 bg-green-600"
            />
          </div>
        </div>
        <div class="flex justify-between max-w-2xl mx-auto mt-2 text-xs text-green-600">
          <span>Identity</span>
          <span>Fiscal Year</span>
          <span>Bank Accounts</span>
          <span>Defaults</span>
          <span>Tax</span>
          <span>Numbering</span>
          <span>Terms</span>
        </div>
      </div>

      <!-- Summary Card -->
      <Card class="mb-8">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <CheckCircle2 class="w-5 h-5 text-green-600" />
            What We've Set Up
          </CardTitle>
          <CardDescription>
            Here's a summary of your configured accounting system
          </CardDescription>
        </CardHeader>

        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Chart of Accounts -->
            <div class="flex items-start gap-3 p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
              <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center flex-shrink-0">
                <FileText class="w-5 h-5 text-blue-600 dark:text-blue-400" />
              </div>
              <div>
                <p class="font-semibold text-slate-900 dark:text-slate-100">
                  {{ summary.accounts_created }} Accounts Created
                </p>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                  Industry-specific chart of accounts
                </p>
              </div>
            </div>

            <!-- Fiscal Periods -->
            <div class="flex items-start gap-3 p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
              <div class="w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center flex-shrink-0">
                <TrendingUp class="w-5 h-5 text-purple-600 dark:text-purple-400" />
              </div>
              <div>
                <p class="font-semibold text-slate-900 dark:text-slate-100">
                  {{ summary.periods_created }} Accounting Periods
                </p>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                  First fiscal year configured
                </p>
              </div>
            </div>

            <!-- Bank Accounts -->
            <div class="flex items-start gap-3 p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
              <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center flex-shrink-0">
                <Building2 class="w-5 h-5 text-green-600 dark:text-green-400" />
              </div>
              <div>
                <p class="font-semibold text-slate-900 dark:text-slate-100">
                  {{ summary.bank_accounts_created }} Bank Accounts
                </p>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                  Ready for payments and reconciliation
                </p>
              </div>
            </div>

            <!-- System Defaults -->
            <div class="flex items-start gap-3 p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
              <div class="w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900 flex items-center justify-center flex-shrink-0">
                <CheckCircle2 class="w-5 h-5 text-orange-600 dark:text-orange-400" />
              </div>
              <div>
                <p class="font-semibold text-slate-900 dark:text-slate-100">
                  All Defaults Configured
                </p>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                  AR, AP, revenue, expense accounts
                </p>
              </div>
            </div>
          </div>

          <!-- Additional Settings -->
          <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <div class="flex items-start gap-3">
              <CheckCircle2 class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
              <div class="text-sm">
                <p class="text-blue-900 dark:text-blue-100 font-medium mb-1">
                  Additional Configuration Complete
                </p>
                <ul class="text-blue-700 dark:text-blue-300 space-y-1">
                  <li v-if="summary.tax_configured">✓ Tax settings configured</li>
                  <li>✓ Invoice and bill numbering set up</li>
                  <li>✓ Default payment terms configured</li>
                  <li>✓ Company identity and timezone set</li>
                </ul>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Next Steps Card -->
      <Card class="mb-8">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Rocket class="w-5 h-5 text-blue-600" />
            Ready to Get Started?
          </CardTitle>
          <CardDescription>
            Here are some suggested next steps to start using your accounting system
          </CardDescription>
        </CardHeader>

        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="step in nextSteps"
              :key="step.title"
              class="border rounded-lg p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
              @click="navigateToStep(step.url)"
            >
              <div class="flex items-start gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center flex-shrink-0">
                  <component :is="step.icon" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div class="flex-1">
                  <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-1">
                    {{ step.title }}
                  </h3>
                  <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ step.description }}
                  </p>
                </div>
              </div>
              <Button variant="outline" size="sm" class="w-full">
                {{ step.action }}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Main Action -->
      <div class="text-center">
        <Button @click="completeSetup" size="lg" class="px-8" :disabled="form.processing">
          <Rocket class="w-5 h-5 mr-2" />
          {{ form.processing ? 'Completing Setup...' : 'Complete Setup & Go to Dashboard' }}
        </Button>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-4">
          You can always change these settings later in company settings
        </p>
      </div>
    </div>
  </div>
</template>
