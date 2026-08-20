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
  router.visit('/companies')
}

const setupManually = () => {
  router.visit('/companies')
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

  <div class="min-h-screen bg-surface-canvas">
    <div class="container mx-auto px-4 py-16 max-w-6xl">
      <!-- Hero Section -->
      <div class="text-center mb-12">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary mb-6">
          <Rocket class="w-10 h-10 text-primary-foreground" />
        </div>
        <h1 class="text-5xl font-bold text-text-primary mb-4">
          Welcome to Haasib, {{ user.name }}!
        </h1>
        <p class="text-xl text-text-secondary max-w-3xl mx-auto">
          Let's get your accounting system set up in just a few minutes. Choose how you'd like to proceed.
        </p>
      </div>

      <!-- Main Options -->
      <div class="grid md:grid-cols-2 gap-8 mb-12">
        <!-- Guided Setup -->
        <Card class="border-2 border-status-info/30 hover:border-status-info transition-colors relative overflow-hidden">
          <div class="absolute top-0 right-0 bg-primary text-primary-foreground text-xs font-semibold px-3 py-1 rounded-bl-lg">
            RECOMMENDED
          </div>
          <CardHeader>
            <div class="w-12 h-12 rounded-full bg-status-info/15 flex items-center justify-center mb-4">
              <Rocket class="w-6 h-6 text-status-info" />
            </div>
            <CardTitle class="text-2xl">Guided Setup</CardTitle>
            <CardDescription class="text-base">
              Step-by-step wizard that configures everything for you
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-3">
              <div class="flex items-start gap-3">
                <CheckCircle2 class="w-5 h-5 text-status-success mt-0.5 flex-shrink-0" />
                <div>
                  <p class="font-medium text-text-primary">Industry-Specific Accounts</p>
                  <p class="text-sm text-text-secondary">Get 20-30 pre-configured accounts for your business</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <CheckCircle2 class="w-5 h-5 text-status-success mt-0.5 flex-shrink-0" />
                <div>
                  <p class="font-medium text-text-primary">Automatic Configuration</p>
                  <p class="text-sm text-text-secondary">Fiscal year, bank accounts, tax settings, and more</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <CheckCircle2 class="w-5 h-5 text-status-success mt-0.5 flex-shrink-0" />
                <div>
                  <p class="font-medium text-text-primary">Best Practices Built-In</p>
                  <p class="text-sm text-text-secondary">Follow accounting standards automatically</p>
                </div>
              </div>
            </div>

            <div class="pt-4">
              <Button @click="startGuidedSetup" size="lg" class="w-full">
                <Rocket class="w-5 h-5 mr-2" />
                Start Guided Setup (7 steps)
              </Button>
              <p class="text-xs text-center text-text-secondary mt-2">
                Takes about 5-10 minutes
              </p>
            </div>
          </CardContent>
        </Card>

        <!-- Manual Setup -->
        <Card class="hover:shadow-lg transition-all">
          <CardHeader>
            <div class="w-12 h-12 rounded-full bg-surface-sunken flex items-center justify-center mb-4">
              <Settings class="w-6 h-6 text-text-secondary" />
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
                  <div class="w-2 h-2 rounded-full bg-text-tertiary mx-auto mt-1.5"></div>
                </div>
                <div>
                  <p class="font-medium text-text-primary">Full Control</p>
                  <p class="text-sm text-text-secondary">Create and configure everything manually</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-5 h-5 flex-shrink-0 mt-0.5">
                  <div class="w-2 h-2 rounded-full bg-text-tertiary mx-auto mt-1.5"></div>
                </div>
                <div>
                  <p class="font-medium text-text-primary">Custom Configuration</p>
                  <p class="text-sm text-text-secondary">Build your chart of accounts from scratch</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-5 h-5 flex-shrink-0 mt-0.5">
                  <div class="w-2 h-2 rounded-full bg-text-tertiary mx-auto mt-1.5"></div>
                </div>
                <div>
                  <p class="font-medium text-text-primary">For Advanced Users</p>
                  <p class="text-sm text-text-secondary">Recommended if you have accounting experience</p>
                </div>
              </div>
            </div>

            <div class="pt-4">
              <Button @click="setupManually" variant="outline" size="lg" class="w-full">
                <Settings class="w-5 h-5 mr-2" />
                Set Up Manually
              </Button>
              <p class="text-xs text-center text-text-secondary mt-2">
                For experienced users
              </p>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Features Section -->
      <Card class="bg-surface-sunken border-rule-default">
        <CardHeader>
          <CardTitle class="text-center text-2xl">What You'll Get</CardTitle>
          <CardDescription class="text-center text-base">
            Powerful accounting features designed for modern businesses
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div v-for="feature in features" :key="feature.title" class="text-center">
              <div class="w-12 h-12 rounded-full bg-status-info/15 flex items-center justify-center mx-auto mb-3">
                <component :is="feature.icon" class="w-6 h-6 text-status-info" />
              </div>
              <h3 class="font-semibold text-text-primary mb-2">
                {{ feature.title }}
              </h3>
              <p class="text-sm text-text-secondary">
                {{ feature.description }}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Help Text -->
      <div class="text-center mt-8 text-sm text-text-secondary">
        <p>Need help deciding? <a href="#" class="text-status-info hover:underline">Read our setup guide</a> or <a href="#" class="text-status-info hover:underline">contact support</a></p>
      </div>
    </div>
  </div>
</template>
