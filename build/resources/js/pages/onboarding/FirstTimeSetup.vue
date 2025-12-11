<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Rocket, Settings, CheckCircle2, TrendingUp, Shield, Users } from 'lucide-vue-next'

interface Props {
  user: {
    name: string
    email: string
  }
}

const props = defineProps<Props>()

const startGuidedSetup = () => {
  router.visit('/companies/create?guided=true')
}

const setupManually = () => {
  router.visit('/companies/create')
}

const features = [
  {
    icon: CheckCircle2,
    title: 'Industry-Specific Setup',
    description: 'Get a pre-configured chart of accounts tailored to your business type',
  },
  {
    icon: TrendingUp,
    title: 'Automated Accounting',
    description: 'Automatic invoice numbering, payment tracking, and financial reporting',
  },
  {
    icon: Shield,
    title: 'Tax Compliance',
    description: 'Built-in tax calculations and compliance features',
  },
  {
    icon: Users,
    title: 'Team Collaboration',
    description: 'Invite team members and control access with role-based permissions',
  },
]
</script>

<template>
  <Head title="Welcome to Haasib" />

  <div class="min-h-screen bg-gradient-to-br from-blue-50 via-slate-50 to-purple-50 dark:from-slate-900 dark:via-slate-800 dark:to-slate-900">
    <div class="container mx-auto px-4 py-16 max-w-6xl">
      <!-- Hero Section -->
      <div class="text-center mb-12">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-br from-blue-600 to-purple-600 mb-6 shadow-lg">
          <Rocket class="w-10 h-10 text-white" />
        </div>
        <h1 class="text-5xl font-bold text-slate-900 dark:text-slate-100 mb-4">
          Welcome to Haasib, {{ user.name }}!
        </h1>
        <p class="text-xl text-slate-600 dark:text-slate-400 max-w-3xl mx-auto">
          Let's get your accounting system set up in just a few minutes. Choose how you'd like to proceed.
        </p>
      </div>

      <!-- Main Options -->
      <div class="grid md:grid-cols-2 gap-8 mb-12">
        <!-- Guided Setup -->
        <Card class="border-2 border-blue-200 dark:border-blue-800 hover:border-blue-400 dark:hover:border-blue-600 transition-all hover:shadow-xl relative overflow-hidden">
          <div class="absolute top-0 right-0 bg-blue-600 text-white text-xs font-semibold px-3 py-1 rounded-bl-lg">
            RECOMMENDED
          </div>
          <CardHeader>
            <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center mb-4">
              <Rocket class="w-6 h-6 text-blue-600 dark:text-blue-400" />
            </div>
            <CardTitle class="text-2xl">Guided Setup</CardTitle>
            <CardDescription class="text-base">
              Step-by-step wizard that configures everything for you
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-3">
              <div class="flex items-start gap-3">
                <CheckCircle2 class="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                <div>
                  <p class="font-medium text-slate-900 dark:text-slate-100">Industry-Specific Accounts</p>
                  <p class="text-sm text-slate-600 dark:text-slate-400">Get 20-30 pre-configured accounts for your business</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <CheckCircle2 class="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                <div>
                  <p class="font-medium text-slate-900 dark:text-slate-100">Automatic Configuration</p>
                  <p class="text-sm text-slate-600 dark:text-slate-400">Fiscal year, bank accounts, tax settings, and more</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <CheckCircle2 class="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                <div>
                  <p class="font-medium text-slate-900 dark:text-slate-100">Best Practices Built-In</p>
                  <p class="text-sm text-slate-600 dark:text-slate-400">Follow accounting standards automatically</p>
                </div>
              </div>
            </div>

            <div class="pt-4">
              <Button @click="startGuidedSetup" size="lg" class="w-full">
                <Rocket class="w-5 h-5 mr-2" />
                Start Guided Setup (7 steps)
              </Button>
              <p class="text-xs text-center text-slate-500 dark:text-slate-400 mt-2">
                Takes about 5-10 minutes
              </p>
            </div>
          </CardContent>
        </Card>

        <!-- Manual Setup -->
        <Card class="hover:shadow-lg transition-all">
          <CardHeader>
            <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
              <Settings class="w-6 h-6 text-slate-600 dark:text-slate-400" />
            </div>
            <CardTitle class="text-2xl">Manual Setup</CardTitle>
            <CardDescription class="text-base">
              Set up your company yourself with full control
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-3">
              <div class="flex items-start gap-3">
                <div class="w-5 h-5 flex-shrink-0 mt-0.5">
                  <div class="w-2 h-2 rounded-full bg-slate-400 mx-auto mt-1.5"></div>
                </div>
                <div>
                  <p class="font-medium text-slate-900 dark:text-slate-100">Full Control</p>
                  <p class="text-sm text-slate-600 dark:text-slate-400">Create and configure everything manually</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-5 h-5 flex-shrink-0 mt-0.5">
                  <div class="w-2 h-2 rounded-full bg-slate-400 mx-auto mt-1.5"></div>
                </div>
                <div>
                  <p class="font-medium text-slate-900 dark:text-slate-100">Custom Configuration</p>
                  <p class="text-sm text-slate-600 dark:text-slate-400">Build your chart of accounts from scratch</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-5 h-5 flex-shrink-0 mt-0.5">
                  <div class="w-2 h-2 rounded-full bg-slate-400 mx-auto mt-1.5"></div>
                </div>
                <div>
                  <p class="font-medium text-slate-900 dark:text-slate-100">For Advanced Users</p>
                  <p class="text-sm text-slate-600 dark:text-slate-400">Recommended if you have accounting experience</p>
                </div>
              </div>
            </div>

            <div class="pt-4">
              <Button @click="setupManually" variant="outline" size="lg" class="w-full">
                <Settings class="w-5 h-5 mr-2" />
                Set Up Manually
              </Button>
              <p class="text-xs text-center text-slate-500 dark:text-slate-400 mt-2">
                For experienced users
              </p>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Features Section -->
      <Card class="bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-800 dark:to-slate-900 border-slate-200 dark:border-slate-700">
        <CardHeader>
          <CardTitle class="text-center text-2xl">What You'll Get</CardTitle>
          <CardDescription class="text-center text-base">
            Powerful accounting features designed for modern businesses
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div v-for="feature in features" :key="feature.title" class="text-center">
              <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center mx-auto mb-3">
                <component :is="feature.icon" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
              </div>
              <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-2">
                {{ feature.title }}
              </h3>
              <p class="text-sm text-slate-600 dark:text-slate-400">
                {{ feature.description }}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Help Text -->
      <div class="text-center mt-8 text-sm text-slate-600 dark:text-slate-400">
        <p>Need help deciding? <a href="#" class="text-blue-600 hover:underline">Read our setup guide</a> or <a href="#" class="text-blue-600 hover:underline">contact support</a></p>
      </div>
    </div>
  </div>
</template>
