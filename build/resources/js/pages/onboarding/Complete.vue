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

  <div class="min-h-screen bg-surface-canvas">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
      <!-- Success Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-status-success/15 mb-4 animate-bounce">
          <CheckCircle2 class="w-12 h-12 text-status-success" />
        </div>
        <h1 class="text-4xl font-bold text-text-primary mb-2">
          All Set! 🎉
        </h1>
        <p class="text-lg text-text-secondary max-w-2xl mx-auto">
          {{ company.name }} is ready to use. Your accounting system has been fully configured.
        </p>
      </div>

      <!-- Progress Indicator - All Complete -->
      <div class="mb-8">
        <div class="flex items-center justify-between max-w-2xl mx-auto">
          <div v-for="(step, index) in 7" :key="index" class="flex items-center">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold bg-status-success text-status-success-contrast">
              <CheckCircle2 class="w-5 h-5" />
            </div>
            <div
              v-if="index < 6"
              class="w-12 h-0.5 mx-2 bg-status-success"
            />
          </div>
        </div>
        <div class="flex justify-between max-w-2xl mx-auto mt-2 text-xs text-status-success">
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
      <Card variant="detail" class="mb-8">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <CheckCircle2 class="w-5 h-5 text-status-success" />
            What We've Set Up
          </CardTitle>
          <CardDescription>
            Here's a summary of your configured accounting system
          </CardDescription>
        </CardHeader>

        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Chart of Accounts -->
            <div class="flex items-start gap-3 p-4 bg-surface-sunken rounded-lg">
              <div class="w-10 h-10 rounded-full bg-status-info/15 flex items-center justify-center flex-shrink-0">
                <FileText class="w-5 h-5 text-status-info" />
              </div>
              <div>
                <p class="font-semibold text-text-primary">
                  {{ summary.accounts_created }} Accounts Created
                </p>
                <p class="text-sm text-text-secondary">
                  Industry-specific chart of accounts
                </p>
              </div>
            </div>

            <!-- Fiscal Periods -->
            <div class="flex items-start gap-3 p-4 bg-surface-sunken rounded-lg">
              <div class="w-10 h-10 rounded-full bg-status-info/15 flex items-center justify-center flex-shrink-0">
                <TrendingUp class="w-5 h-5 text-status-info" />
              </div>
              <div>
                <p class="font-semibold text-text-primary">
                  {{ summary.periods_created }} Accounting Periods
                </p>
                <p class="text-sm text-text-secondary">
                  First fiscal year configured
                </p>
              </div>
            </div>

            <!-- Bank Accounts -->
            <div class="flex items-start gap-3 p-4 bg-surface-sunken rounded-lg">
              <div class="w-10 h-10 rounded-full bg-status-success/15 flex items-center justify-center flex-shrink-0">
                <Building2 class="w-5 h-5 text-status-success" />
              </div>
              <div>
                <p class="font-semibold text-text-primary">
                  {{ summary.bank_accounts_created }} Bank Accounts
                </p>
                <p class="text-sm text-text-secondary">
                  Ready for payments and reconciliation
                </p>
              </div>
            </div>

            <!-- System Defaults -->
            <div class="flex items-start gap-3 p-4 bg-surface-sunken rounded-lg">
              <div class="w-10 h-10 rounded-full bg-status-attention/15 flex items-center justify-center flex-shrink-0">
                <CheckCircle2 class="w-5 h-5 text-status-attention" />
              </div>
              <div>
                <p class="font-semibold text-text-primary">
                  All Defaults Configured
                </p>
                <p class="text-sm text-text-secondary">
                  AR, AP, revenue, expense accounts
                </p>
              </div>
            </div>
          </div>

          <!-- Additional Settings -->
          <div class="mt-6 p-4 bg-status-info/10 border border-status-info/30 rounded-lg">
            <div class="flex items-start gap-3">
              <CheckCircle2 class="w-5 h-5 text-status-info mt-0.5 flex-shrink-0" />
              <div class="text-sm">
                <p class="text-text-primary font-medium mb-1">
                  Additional Configuration Complete
                </p>
                <ul class="text-text-secondary space-y-1">
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
      <Card variant="detail" class="mb-8">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Rocket class="w-5 h-5 text-status-info" />
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
              class="border rounded-lg p-4 hover:bg-surface-sunken transition-colors cursor-pointer"
              @click="navigateToStep(step.url)"
            >
              <div class="flex items-start gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-status-info/15 flex items-center justify-center flex-shrink-0">
                  <component :is="step.icon" class="w-5 h-5 text-status-info" />
                </div>
                <div class="flex-1">
                  <h3 class="font-semibold text-text-primary mb-1">
                    {{ step.title }}
                  </h3>
                  <p class="text-sm text-text-secondary">
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
        <p class="text-sm text-text-secondary mt-4">
          You can always change these settings later in company settings
        </p>
      </div>
    </div>
  </div>
</template>
