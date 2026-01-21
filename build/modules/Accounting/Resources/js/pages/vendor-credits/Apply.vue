<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import {
  ReceiptText,
  Save,
  ArrowLeft,
  DollarSign,
  Zap,
  RotateCcw,
  AlertCircle,
  CheckCircle2,
  Calculator,
  FileText,
  TrendingUp
} from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface BillRef {
  id: string
  bill_number: string
  balance: number
  currency: string
  bill_date?: string
  due_date?: string
}

interface CreditRef {
  id: string
  credit_number: string
  amount: number
  currency: string
}

const props = defineProps<{
  company: CompanyRef
  credit: CreditRef
  unpaidBills: BillRef[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Vendor Credits', href: `/${props.company.slug}/vendor-credits` },
  { title: props.credit.credit_number, href: `/${props.company.slug}/vendor-credits/${props.credit.id}` },
  { title: 'Apply', href: `/${props.company.slug}/vendor-credits/${props.credit.id}/apply` },
]

const form = useForm({
  applications: props.unpaidBills.map((bill) => ({
    bill_id: bill.id,
    amount_applied: 0,
  })),
})

// State management
const showToast = ref(false)
const toastMessage = ref('')

// Calculated properties
const totalApplied = computed(() => form.applications.reduce((sum, a) => sum + (Number(a.amount_applied) || 0), 0))
const remainingCredit = computed(() => props.credit.amount - totalApplied.value)
const utilizationPercentage = computed(() => (totalApplied.value / props.credit.amount) * 100)
const hasExceeded = computed(() => totalApplied.value > props.credit.amount)
const hasUnusedCredit = computed(() => remainingCredit.value > 0.001)
const hasApplications = computed(() => totalApplied.value > 0)

// Validation states
const validationState = computed(() => {
  if (!hasApplications.value) return 'empty'
  if (hasExceeded.value) return 'exceeded'
  if (hasUnusedCredit.value) return 'partial'
  return 'perfect'
})

// Formatting functions
const formatMoney = (amount: number, currency: string) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
    currencyDisplay: 'narrowSymbol',
  }).format(amount)

const formatDate = (dateString?: string) => {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString()
}

// Helper functions
const updateAmount = (billId: string, amount: number) => {
  const maxAmount = Math.min(amount, props.unpaidBills.find(b => b.id === billId)?.balance || 0)
  form.applications.find((a) => a.bill_id === billId)!.amount_applied = maxAmount
}

const currentAmount = (billId: string) =>
  form.applications.find((a) => a.bill_id === billId)?.amount_applied ?? 0

const applyFullAmount = (billId: string) => {
  const bill = props.unpaidBills.find(b => b.id === billId)
  if (bill) {
    updateAmount(billId, bill.balance)
  }
}

const applyMaxPossible = (billId: string) => {
  const bill = props.unpaidBills.find(b => b.id === billId)
  if (bill) {
    const maxPossible = Math.min(bill.balance, remainingCredit.value)
    updateAmount(billId, maxPossible)
  }
}

const applyToOldestBills = () => {
  const sortedBills = [...props.unpaidBills].sort((a, b) => {
    const dateA = a.due_date ? new Date(a.due_date).getTime() : 0
    const dateB = b.due_date ? new Date(b.due_date).getTime() : 0
    return dateA - dateB
  })

  let remaining = remainingCredit.value
  sortedBills.forEach(bill => {
    if (remaining > 0) {
      const amount = Math.min(bill.balance, remaining)
      updateAmount(bill.id, amount)
      remaining -= amount
    }
  })
}

const applyToLargestBills = () => {
  const sortedBills = [...props.unpaidBills].sort((a, b) => b.balance - a.balance)

  let remaining = remainingCredit.value
  sortedBills.forEach(bill => {
    if (remaining > 0) {
      const amount = Math.min(bill.balance, remaining)
      updateAmount(bill.id, amount)
      remaining -= amount
    }
  })
}

const clearAll = () => {
  form.applications.forEach(app => {
    app.amount_applied = 0
  })
}

const optimizeApplications = () => {
  // Apply to bills with highest balance first to minimize number of bills
  applyToLargestBills()
}

const validateAndSubmit = () => {
  if (!hasApplications.value) {
    toastMessage.value = 'Please enter an amount to apply'
    showToast.value = true
    setTimeout(() => showToast.value = false, 3000)
    return
  }

  if (hasExceeded.value) {
    toastMessage.value = 'Applications exceed credit amount'
    showToast.value = true
    setTimeout(() => showToast.value = false, 3000)
    return
  }

  form.post(`/${props.company.slug}/vendor-credits/${props.credit.id}/apply`, {
    preserveScroll: true,
    onSuccess: () => {
      toastMessage.value = 'Credit applied successfully!'
      showToast.value = true
      setTimeout(() => {
        showToast.value = false
        // Navigate back to credit show page after a delay
        window.location.href = `/${props.company.slug}/vendor-credits/${props.credit.id}`
      }, 1500)
    },
    onError: (errors) => {
      toastMessage.value = 'Error applying credit'
      showToast.value = true
      setTimeout(() => showToast.value = false, 3000)
    }
  })
}

// Helper for getting bill status
const getBillStatus = (bill: BillRef, appliedAmount: number) => {
  if (appliedAmount >= bill.balance) return 'fully-paid'
  if (appliedAmount > 0) return 'partially-paid'
  return 'unpaid'
}

// Helper for aging calculation
const getDaysOverdue = (dueDate?: string) => {
  if (!dueDate) return null
  const today = new Date()
  const due = new Date(dueDate)
  const diff = Math.floor((today.getTime() - due.getTime()) / (1000 * 60 * 60 * 24))
  return diff > 0 ? diff : null
}

</script>

<template>
  <Head :title="`Apply ${credit.credit_number}`" />
  <PageShell
    :title="`Apply ${credit.credit_number}`"
    :breadcrumbs="breadcrumbs"
    :icon="ReceiptText"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/vendor-credits/${credit.id}`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back to Credit
      </Button>
      <Button @click="validateAndSubmit" :disabled="form.processing || !hasApplications || hasExceeded">
        <Save class="mr-2 h-4 w-4" />
        Apply Credit
      </Button>
    </template>

    <!-- Toast Notification -->
    <div
      v-if="showToast"
      :class="[
        'fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transition-all duration-300',
        validationState === 'exceeded' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'
      ]"
    >
      {{ toastMessage }}
    </div>

    <!-- Credit Summary Card -->
    <Card class="mb-6">
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <DollarSign class="h-5 w-5" />
          Credit Summary
        </CardTitle>
        <CardDescription>
          Apply credit to unpaid bills from {{ credit.vendor?.name || 'vendor' }}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <div class="grid gap-6 md:grid-cols-3">
          <div>
            <div class="text-sm font-medium text-muted-foreground">Available Credit</div>
            <div class="text-2xl font-bold text-blue-600">
              {{ formatMoney(credit.amount, credit.currency) }}
            </div>
          </div>
          <div>
            <div class="text-sm font-medium text-muted-foreground">Applied Amount</div>
            <div class="text-2xl font-bold" :class="hasExceeded ? 'text-red-600' : 'text-green-600'">
              {{ formatMoney(totalApplied, credit.currency) }}
            </div>
          </div>
          <div>
            <div class="text-sm font-medium text-muted-foreground">Remaining</div>
            <div class="text-2xl font-bold" :class="hasUnusedCredit ? 'text-orange-600' : 'text-gray-600'">
              {{ formatMoney(Math.max(0, remainingCredit), credit.currency) }}
            </div>
          </div>
        </div>

        <!-- Progress Bar -->
        <div class="mt-6 space-y-2">
          <div class="flex justify-between text-sm">
            <span>Credit Utilization</span>
            <span :class="hasExceeded ? 'text-red-600 font-semibold' : 'text-muted-foreground'">
              {{ utilizationPercentage.toFixed(1) }}%
            </span>
          </div>
          <Progress
            :value="Math.min(100, utilizationPercentage)"
            :class="hasExceeded ? 'bg-red-100' : ''"
          />
        </div>

        <!-- Status Indicator -->
        <div class="mt-4">
          <div class="flex items-center gap-2">
            <div
              :class="[
                'w-3 h-3 rounded-full',
                validationState === 'perfect' ? 'bg-green-500' :
                validationState === 'partial' ? 'bg-orange-500' :
                validationState === 'exceeded' ? 'bg-red-500' : 'bg-gray-500'
              ]"
            ></div>
            <span class="text-sm font-medium">
              {{
                validationState === 'perfect' ? 'Perfect match!' :
                validationState === 'partial' ? 'Credit remaining' :
                validationState === 'exceeded' ? 'Exceeded credit amount' :
                'No applications yet'
              }}
            </span>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Quick Actions -->
    <Card v-if="unpaidBills.length > 0" class="mb-6">
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <Zap class="h-5 w-5" />
          Quick Actions
        </CardTitle>
        <CardDescription>
          Smart tools to quickly apply your credit
        </CardDescription>
      </CardHeader>
      <CardContent>
        <div class="flex flex-wrap gap-3">
          <Button variant="outline" @click="optimizeApplications" class="flex items-center gap-2">
            <Calculator class="h-4 w-4" />
            Optimize (Largest Bills)
          </Button>
          <Button variant="outline" @click="applyToOldestBills" class="flex items-center gap-2">
            <TrendingUp class="h-4 w-4" />
            Apply to Oldest
          </Button>
          <Button variant="outline" @click="clearAll" class="flex items-center gap-2">
            <RotateCcw class="h-4 w-4" />
            Clear All
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- Bills to Apply -->
    <Card>
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <FileText class="h-5 w-5" />
          Unpaid Bills ({{ unpaidBills.length }})
        </CardTitle>
        <CardDescription>
          Select bills and amounts to apply your credit
        </CardDescription>
      </CardHeader>
      <CardContent>
        <div v-if="unpaidBills.length === 0" class="text-center py-8 text-muted-foreground">
          <CheckCircle2 class="h-12 w-12 mx-auto mb-4 text-green-500" />
          <div class="text-lg font-medium">No unpaid bills</div>
          <div class="text-sm">All bills have been paid or no bills are associated with this vendor</div>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="bill in unpaidBills"
            :key="bill.id"
            class="rounded-lg border p-4 transition-all hover:border-primary/50"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                  <div>
                    <div class="font-semibold text-lg">{{ bill.bill_number }}</div>
                    <div class="text-sm text-muted-foreground">
                      Due: {{ formatDate(bill.due_date) }}
                      <span
                        v-if="getDaysOverdue(bill.due_date)"
                        class="ml-2 text-xs font-medium px-2 py-1 rounded-full bg-orange-100 text-orange-800"
                      >
                        {{ getDaysOverdue(bill.due_date) }} days overdue
                      </span>
                    </div>
                  </div>
                  <Badge
                    :variant="getBillStatus(bill, currentAmount(bill.id)) === 'fully-paid' ? 'success' :
                               getBillStatus(bill, currentAmount(bill.id)) === 'partially-paid' ? 'warning' : 'secondary'"
                  >
                    {{ getBillStatus(bill, currentAmount(bill.id))?.replace('-', ' ').toUpperCase() }}
                  </Badge>
                </div>

                <!-- Amount Application Controls -->
                <div class="flex items-center gap-4">
                  <div class="flex-1">
                    <div class="flex items-center gap-3">
                      <div class="flex-1 max-w-xs">
                        <label class="text-xs font-medium text-muted-foreground">Amount to Apply</label>
                        <Input
                          type="number"
                          min="0"
                          step="0.01"
                          :max="bill.balance"
                          :value="currentAmount(bill.id)"
                          @input="(e: any) => updateAmount(bill.id, parseFloat(e.target.value) || 0)"
                          class="font-mono"
                        />
                      </div>
                      <div class="text-sm text-muted-foreground">
                        <div>of {{ formatMoney(bill.balance, bill.currency) }}</div>
                        <Progress
                          :value="(currentAmount(bill.id) / bill.balance) * 100"
                          class="w-24 h-2 mt-1"
                        />
                      </div>
                    </div>
                  </div>

                  <!-- Quick Apply Buttons -->
                  <div class="flex gap-2">
                    <Button
                      size="sm"
                      variant="outline"
                      @click="applyMaxPossible(bill.id)"
                      :disabled="remainingCredit <= 0"
                    >
                      Max Possible
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      @click="applyFullAmount(bill.id)"
                    >
                      Full Amount
                    </Button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Warning Alert -->
    <Alert v-if="hasExceeded" class="mt-6 border-red-200 bg-red-50">
      <AlertCircle class="h-4 w-4 text-red-600" />
      <AlertDescription class="text-red-800">
        Total applications exceed the available credit amount by {{ formatMoney(totalApplied - credit.amount, credit.currency) }}.
        Please reduce the amounts to continue.
      </AlertDescription>
    </Alert>

    <!-- Empty State -->
    <div v-if="!hasApplications && unpaidBills.length > 0" class="mt-6 text-center py-8 text-muted-foreground">
      <DollarSign class="h-12 w-12 mx-auto mb-4 opacity-50" />
      <div class="text-lg font-medium">No applications yet</div>
      <div class="text-sm mb-4">Enter amounts or use quick actions to apply your credit</div>
      <Button @click="optimizeApplications" variant="outline">
        <Calculator class="mr-2 h-4 w-4" />
        Smart Apply
      </Button>
    </div>
  </PageShell>
</template>
