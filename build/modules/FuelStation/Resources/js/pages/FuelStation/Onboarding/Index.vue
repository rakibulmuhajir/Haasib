<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import type { BreadcrumbItem } from '@/types'
import { Fuel, CheckCircle, Circle, ArrowRight, ArrowLeft, Settings, Warehouse, CreditCard, Users } from 'lucide-vue-next'

interface OnboardingStatus {
  completed_steps: string[]
  current_step: string
  is_complete: boolean
  company_name: string
  industry: string
}

interface FuelItem {
  id: string
  name: string
  sku: string
  fuel_category: string
}

const props = defineProps<{
  status: OnboardingStatus
  tanks: any[]
  pumps: any[]
  fuelItems: FuelItem[]
}>()

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/dashboard` },
  { title: 'Onboarding', href: `/${companySlug.value}/fuel/onboarding` },
])

// Onboarding steps
const steps = [
  {
    id: 'welcome',
    title: 'Welcome',
    description: 'Getting started with fuel station setup',
    icon: Fuel,
  },
  {
    id: 'accounts',
    title: 'Chart of Accounts',
    description: 'Set up fuel-specific GL accounts',
    icon: Settings,
  },
  {
    id: 'fuel_items',
    title: 'Fuel Items',
    description: 'Configure petrol, diesel, and other fuels',
    icon: Fuel,
  },
  {
    id: 'tanks',
    title: 'Storage Tanks',
    description: 'Set up your fuel storage tanks',
    icon: Warehouse,
  },
  {
    id: 'pumps',
    title: 'Fuel Pumps',
    description: 'Configure pump points and nozzles',
    icon: Fuel,
  },
  {
    id: 'rates',
    title: 'Fuel Rates',
    description: 'Set current fuel prices and margins',
    icon: CreditCard,
  },
  {
    id: 'complete',
    title: 'Complete',
    description: 'Ready to start operations!',
    icon: CheckCircle,
  },
]

const currentStepIndex = ref(0)
const currentStep = computed(() => steps[currentStepIndex.value])

const progress = computed(() => {
  const completedCount = props.status.completed_steps.length
  return Math.round((completedCount / (steps.length - 1)) * 100) // Exclude complete step
})

const isStepCompleted = (stepId: string) => {
  return props.status.completed_steps.includes(stepId)
}

const isStepAccessible = (stepId: string) => {
  const stepIndex = steps.findIndex(s => s.id === stepId)
  const currentIndex = steps.findIndex(s => s.id === props.status.current_step)

  // Can access completed steps and the current step
  return stepIndex <= currentIndex || isStepCompleted(stepId)
}

const goToStep = (stepId: string) => {
  if (!isStepAccessible(stepId)) return

  const stepIndex = steps.findIndex(s => s.id === stepId)
  currentStepIndex.value = stepIndex
}

// Account setup form
const accountForm = useForm({
  setup_accounts: true,
})

// Fuel items setup form
const fuelItemForm = useForm({
  petrol_enabled: true,
  petrol_cost: 248.00,
  petrol_sale: 252.10,
  diesel_enabled: true,
  diesel_cost: 260.00,
  diesel_sale: 263.50,
  hi_octane_enabled: false,
  hi_octane_cost: 280.00,
  hi_octane_sale: 285.00,
})

// Tank setup form
const tankForm = useForm({
  petrol_tank_capacity: 25000,
  petrol_tank_name: 'Petrol Tank 1',
  diesel_tank_capacity: 20000,
  diesel_tank_name: 'Diesel Tank 1',
  hi_octane_tank_capacity: 10000,
  hi_octane_tank_name: 'Hi-Octane Tank 1',
})

// Pump setup form
const pumpForm = useForm({
  petrol_pumps: 3,
  diesel_pumps: 2,
  hi_octane_pumps: 1,
})

// Rate setup form
const rateForm = useForm({
  effective_date: new Date().toISOString().split('T')[0],
})

const nextStep = () => {
  const nextIndex = currentStepIndex.value + 1
  if (nextIndex < steps.length) {
    currentStepIndex.value = nextIndex
  }
}

const previousStep = () => {
  const prevIndex = currentStepIndex.value - 1
  if (prevIndex >= 0) {
    currentStepIndex.value = prevIndex
  }
}

const setupAccounts = () => {
  const slug = companySlug.value
  if (!slug) return

  accountForm.post(`/${slug}/fuel/onboarding/accounts`, {
    preserveScroll: true,
    onSuccess: () => nextStep(),
  })
}

const setupFuelItems = () => {
  const slug = companySlug.value
  if (!slug) return

  const fuelItems = []

  if (fuelItemForm.petrol_enabled) {
    fuelItems.push({
      name: 'Petrol',
      sku: 'FUEL-PETROL',
      fuel_category: 'petrol',
      avg_cost: fuelItemForm.petrol_cost,
      sale_price: fuelItemForm.petrol_sale,
    })
  }

  if (fuelItemForm.diesel_enabled) {
    fuelItems.push({
      name: 'Diesel',
      sku: 'FUEL-DIESEL',
      fuel_category: 'diesel',
      avg_cost: fuelItemForm.diesel_cost,
      sale_price: fuelItemForm.diesel_sale,
    })
  }

  if (fuelItemForm.hi_octane_enabled) {
    fuelItems.push({
      name: 'Hi-Octane',
      sku: 'FUEL-HIOCTANE',
      fuel_category: 'high_octane',
      avg_cost: fuelItemForm.hi_octane_cost,
      sale_price: fuelItemForm.hi_octane_sale,
    })
  }

  router.post(`/${slug}/fuel/onboarding/fuel-items`, {
    fuel_items: fuelItems,
  }, {
    preserveScroll: true,
    onSuccess: () => nextStep(),
  })
}

const completeSetup = () => {
  const slug = companySlug.value
  if (!slug) return

  router.post(`/${slug}/fuel/onboarding/complete`, {}, {
    preserveScroll: true,
    onSuccess: () => router.get(`/${slug}/fuel/dashboard`),
  })
}

onMounted(() => {
  // Set current step based on status
  const currentStepIndexFromStatus = steps.findIndex(s => s.id === props.status.current_step)
  if (currentStepIndexFromStatus >= 0) {
    currentStepIndex.value = currentStepIndexFromStatus
  }
})
</script>

<template>
  <Head title="Fuel Station Setup" />

  <PageShell
    title="Fuel Station Setup"
    description="Complete setup wizard for your fuel station operations"
    :icon="Fuel"
    :breadcrumbs="breadcrumbs"
  >
    <!-- Progress Overview -->
    <Card class="border-border/80 mb-6">
      <CardHeader>
        <div class="flex items-center justify-between">
          <div>
            <CardTitle class="text-base">Setup Progress</CardTitle>
            <CardDescription>
              {{ props.status.completed_steps.length }} of {{ steps.length - 1 }} steps completed
            </CardDescription>
          </div>
          <Badge :class="props.status.is_complete ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
            {{ props.status.is_complete ? 'Complete' : 'In Progress' }}
          </Badge>
        </div>
      </CardHeader>
      <CardContent>
        <Progress :model-value="progress" class="mb-4" />
        <div class="flex justify-between text-sm text-text-secondary">
          <span>0%</span>
          <span>{{ progress }}% Complete</span>
          <span>100%</span>
        </div>
      </CardContent>
    </Card>

    <div class="grid gap-6 lg:grid-cols-4">
      <!-- Step Navigation -->
      <div class="lg:col-span-1">
        <Card class="border-border/80">
          <CardHeader>
            <CardTitle class="text-base">Steps</CardTitle>
          </CardHeader>
          <CardContent class="p-0">
            <div class="space-y-1">
              <div
                v-for="(step, index) in steps"
                :key="step.id"
                :class="[
                  'flex items-center gap-3 p-3 cursor-pointer transition-colors',
                  currentStep.id === step.id ? 'bg-muted border-r-2 border-primary' : 'hover:bg-muted/50',
                  !isStepAccessible(step.id) ? 'opacity-50 cursor-not-allowed' : '',
                ]"
                @click="goToStep(step.id)"
              >
                <div class="flex-shrink-0">
                  <component
                    :is="isStepCompleted(step.id) ? CheckCircle : Circle"
                    :class="[
                      'h-5 w-5',
                      isStepCompleted(step.id) ? 'text-emerald-600' : 'text-text-tertiary',
                    ]"
                  />
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate">{{ step.title }}</p>
                  <p class="text-xs text-text-secondary truncate">{{ step.description }}</p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Step Content -->
      <div class="lg:col-span-3">
        <Card class="border-border/80">
          <CardHeader>
            <div class="flex items-center gap-3">
              <component :is="currentStep.icon" class="h-6 w-6 text-blue-600" />
              <div>
                <CardTitle class="text-lg">{{ currentStep.title }}</CardTitle>
                <CardDescription>{{ currentStep.description }}</CardDescription>
              </div>
            </div>
          </CardHeader>

          <CardContent class="min-h-[400px]">
            <!-- Welcome Step -->
            <div v-if="currentStep.id === 'welcome'" class="space-y-6">
              <div class="text-center space-y-4">
                <Fuel class="h-16 w-16 text-blue-600 mx-auto" />
                <div>
                  <h2 class="text-2xl font-bold text-text-primary">Welcome to Haasib Fuel Station</h2>
                  <p class="text-text-secondary mt-2">
                    Let's set up your fuel station for smooth operations. This wizard will guide you through
                    configuring accounts, inventory, and operational settings.
                  </p>
                </div>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <Card class="border-border/60">
                  <CardContent class="pt-6">
                    <div class="text-center space-y-2">
                      <Settings class="h-8 w-8 text-blue-600 mx-auto" />
                      <h3 class="font-semibold">What You'll Configure</h3>
                      <ul class="text-sm text-text-secondary space-y-1 text-left">
                        <li>• Chart of accounts for fuel operations</li>
                        <li>• Fuel inventory items and pricing</li>
                        <li>• Storage tanks and pump configuration</li>
                        <li>• Current fuel rates and margins</li>
                      </ul>
                    </div>
                  </CardContent>
                </Card>

                <Card class="border-border/60">
                  <CardContent class="pt-6">
                    <div class="text-center space-y-2">
                      <CheckCircle class="h-8 w-8 text-emerald-600 mx-auto" />
                      <h3 class="font-semibold">Time to Complete</h3>
                      <p class="text-sm text-text-secondary">
                        Approximately 15-20 minutes. You can save progress and return later.
                      </p>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>

            <!-- Accounts Setup -->
            <div v-if="currentStep.id === 'accounts'" class="space-y-6">
              <div class="space-y-4">
                <p class="text-text-secondary">
                  We'll create essential GL accounts for your fuel station operations including fuel inventory,
                  revenue accounts, commission expenses, and payment clearing accounts.
                </p>

                <div class="rounded-lg border border-border/70 bg-muted/30 p-4">
                  <h4 class="font-medium mb-2">Accounts to be created:</h4>
                  <div class="grid gap-2 text-sm">
                    <div>• Fuel Inventory (Asset)</div>
                    <div>• Fuel Sales Revenue</div>
                    <div>• Investor Commission Expense</div>
                    <div>• Parco Card Clearing</div>
                    <div>• Amanat Deposits (Liability)</div>
                    <div>• Fuel Shrinkage Loss</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Fuel Items Setup -->
            <div v-if="currentStep.id === 'fuel_items'" class="space-y-6">
              <div class="space-y-4">
                <p class="text-text-secondary">
                  Configure the fuel types you'll sell. Set current purchase costs and sale prices.
                  These will be used for inventory valuation and margin calculations.
                </p>

                <div class="space-y-4">
                  <!-- Petrol -->
                  <Card class="border-border/70">
                    <CardContent class="pt-6">
                      <div class="flex items-center justify-between mb-4">
                        <h4 class="font-medium">Petrol</h4>
                        <Switch v-model:checked="fuelItemForm.petrol_enabled" />
                      </div>
                      <div v-if="fuelItemForm.petrol_enabled" class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                          <Label>Purchase Cost (PKR/L)</Label>
                          <Input v-model.number="fuelItemForm.petrol_cost" type="number" step="0.01" />
                        </div>
                        <div class="space-y-2">
                          <Label>Sale Price (PKR/L)</Label>
                          <Input v-model.number="fuelItemForm.petrol_sale" type="number" step="0.01" />
                        </div>
                      </div>
                    </CardContent>
                  </Card>

                  <!-- Diesel -->
                  <Card class="border-border/70">
                    <CardContent class="pt-6">
                      <div class="flex items-center justify-between mb-4">
                        <h4 class="font-medium">Diesel</h4>
                        <Switch v-model:checked="fuelItemForm.diesel_enabled" />
                      </div>
                      <div v-if="fuelItemForm.diesel_enabled" class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                          <Label>Purchase Cost (PKR/L)</Label>
                          <Input v-model.number="fuelItemForm.diesel_cost" type="number" step="0.01" />
                        </div>
                        <div class="space-y-2">
                          <Label>Sale Price (PKR/L)</Label>
                          <Input v-model.number="fuelItemForm.diesel_sale" type="number" step="0.01" />
                        </div>
                      </div>
                    </CardContent>
                  </Card>

                  <!-- Hi-Octane -->
                  <Card class="border-border/70">
                    <CardContent class="pt-6">
                      <div class="flex items-center justify-between mb-4">
                        <h4 class="font-medium">Hi-Octane</h4>
                        <Switch v-model:checked="fuelItemForm.hi_octane_enabled" />
                      </div>
                      <div v-if="fuelItemForm.hi_octane_enabled" class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                          <Label>Purchase Cost (PKR/L)</Label>
                          <Input v-model.number="fuelItemForm.hi_octane_cost" type="number" step="0.01" />
                        </div>
                        <div class="space-y-2">
                          <Label>Sale Price (PKR/L)</Label>
                          <Input v-model.number="fuelItemForm.hi_octane_sale" type="number" step="0.01" />
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                </div>
              </div>
            </div>

            <!-- Tanks Setup -->
            <div v-if="currentStep.id === 'tanks'" class="space-y-6">
              <div class="space-y-4">
                <p class="text-text-secondary">
                  Configure your fuel storage tanks. Each tank will be linked to a fuel type and track inventory levels.
                </p>

                <div v-if="fuelItemForm.petrol_enabled" class="space-y-2">
                  <Label>Petrol Tank</Label>
                  <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="tankForm.petrol_tank_name" placeholder="Tank name" />
                    <div class="space-y-1">
                      <Input v-model.number="tankForm.petrol_tank_capacity" type="number" placeholder="Capacity (liters)" />
                      <p class="text-xs text-text-secondary">Liters</p>
                    </div>
                  </div>
                </div>

                <div v-if="fuelItemForm.diesel_enabled" class="space-y-2">
                  <Label>Diesel Tank</Label>
                  <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="tankForm.diesel_tank_name" placeholder="Tank name" />
                    <div class="space-y-1">
                      <Input v-model.number="tankForm.diesel_tank_capacity" type="number" placeholder="Capacity (liters)" />
                      <p class="text-xs text-text-secondary">Liters</p>
                    </div>
                  </div>
                </div>

                <div v-if="fuelItemForm.hi_octane_enabled" class="space-y-2">
                  <Label>Hi-Octane Tank</Label>
                  <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="tankForm.hi_octane_tank_name" placeholder="Tank name" />
                    <div class="space-y-1">
                      <Input v-model.number="tankForm.hi_octane_tank_capacity" type="number" placeholder="Capacity (liters)" />
                      <p class="text-xs text-text-secondary">Liters</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Pumps Setup -->
            <div v-if="currentStep.id === 'pumps'" class="space-y-6">
              <div class="space-y-4">
                <p class="text-text-secondary">
                  Configure your fuel pump points. Each pump will be linked to a tank and track meter readings.
                </p>

                <div class="grid gap-4 sm:grid-cols-3">
                  <div v-if="fuelItemForm.petrol_enabled" class="space-y-2">
                    <Label>Petrol Pumps</Label>
                    <Input v-model.number="pumpForm.petrol_pumps" type="number" min="1" max="10" />
                  </div>

                  <div v-if="fuelItemForm.diesel_enabled" class="space-y-2">
                    <Label>Diesel Pumps</Label>
                    <Input v-model.number="pumpForm.diesel_pumps" type="number" min="1" max="10" />
                  </div>

                  <div v-if="fuelItemForm.hi_octane_enabled" class="space-y-2">
                    <Label>Hi-Octane Pumps</Label>
                    <Input v-model.number="pumpForm.hi_octane_pumps" type="number" min="1" max="10" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Rates Setup -->
            <div v-if="currentStep.id === 'rates'" class="space-y-6">
              <div class="space-y-4">
                <p class="text-text-secondary">
                  Set the effective date for your fuel rates. The rates configured in the previous step will be
                  applied from this date.
                </p>

                <div class="space-y-2">
                  <Label>Effective Date</Label>
                  <Input v-model="rateForm.effective_date" type="date" />
                </div>
              </div>
            </div>

            <!-- Complete Step -->
            <div v-if="currentStep.id === 'complete'" class="space-y-6">
              <div class="text-center space-y-4">
                <CheckCircle class="h-16 w-16 text-emerald-600 mx-auto" />
                <div>
                  <h2 class="text-2xl font-bold text-text-primary">Setup Complete!</h2>
                  <p class="text-text-secondary mt-2">
                    Your fuel station is now configured and ready for operations. You can start recording sales,
                    managing inventory, and tracking financials.
                  </p>
                </div>
              </div>

              <Card class="border-emerald-200 bg-emerald-50">
                <CardContent class="pt-6">
                  <h3 class="font-semibold text-emerald-900 mb-2">What's Next?</h3>
                  <ul class="text-sm text-emerald-800 space-y-1">
                    <li>• Go to the dashboard to see your fuel station overview</li>
                    <li>• Add your first tank readings for inventory tracking</li>
                    <li>• Record your first fuel sale</li>
                    <li>• Set up investors and amanat customers</li>
                  </ul>
                </CardContent>
              </Card>
            </div>
          </CardContent>

          <!-- Step Navigation -->
          <div class="flex justify-between items-center pt-6 border-t border-border/50">
            <Button
              variant="outline"
              :disabled="currentStepIndex === 0"
              @click="previousStep"
            >
              <ArrowLeft class="mr-2 h-4 w-4" />
              Previous
            </Button>

            <div class="flex gap-2">
              <Button
                v-if="currentStep.id === 'accounts'"
                class="bg-blue-600 hover:bg-blue-700"
                :disabled="accountForm.processing"
                @click="setupAccounts"
              >
                <span v-if="accountForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                Setup Accounts
              </Button>

              <Button
                v-if="currentStep.id === 'fuel_items'"
                class="bg-blue-600 hover:bg-blue-700"
                @click="setupFuelItems"
              >
                Configure Fuel Items
              </Button>

              <Button
                v-if="currentStep.id === 'tanks'"
                class="bg-blue-600 hover:bg-blue-700"
                @click="nextStep"
              >
                Configure Tanks
              </Button>

              <Button
                v-if="currentStep.id === 'pumps'"
                class="bg-blue-600 hover:bg-blue-700"
                @click="nextStep"
              >
                Configure Pumps
              </Button>

              <Button
                v-if="currentStep.id === 'rates'"
                class="bg-blue-600 hover:bg-blue-700"
                @click="nextStep"
              >
                Set Rates
              </Button>

              <Button
                v-if="currentStep.id === 'complete'"
                class="bg-emerald-600 hover:bg-emerald-700"
                @click="completeSetup"
              >
                Go to Dashboard
              </Button>

              <Button
                v-if="!['accounts', 'fuel_items', 'complete'].includes(currentStep.id)"
                class="bg-blue-600 hover:bg-blue-700"
                @click="nextStep"
              >
                Next
                <ArrowRight class="ml-2 h-4 w-4" />
              </Button>
            </div>
          </div>
        </Card>
      </div>
    </div>
  </PageShell>
</template>