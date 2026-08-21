<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { useCompanyRoute } from '@/composables/useCompanyRoute'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime } from '@/lib/datetime'
import { User, ArrowLeft, Wallet, TrendingUp, TrendingDown, Ban, Edit, Unlock } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'
import MoneyText from '@/components/MoneyText.vue'
import InputError from '@/components/InputError.vue'

interface Customer {
  id: string
  name: string
  code: string | null
  phone: string | null
  email: string | null
  address: string | null
  credit_limit: number
  current_balance: number
  is_credit_blocked: boolean
}

interface Transaction {
  id: string
  date: string
  type: 'sale' | 'collection'
  description: string
  amount: number
  liters?: number
  reference?: string
}

const props = defineProps<{
  customer: Customer
  transactions: Transaction[]
  currency: string
}>()

const { companySlug } = useCompanyRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Credit Customers', href: `/${companySlug.value}/fuel/credit-customers` },
  { title: props.customer.name, href: `/${companySlug.value}/fuel/credit-customers/${props.customer.id}` },
])

const currency = computed(() => currencySymbol(props.currency))

const formatDate = (dateStr: string) => {
  return formatDateTime(dateStr, { mode: 'date' })
}

// Credit limit dialog
const limitDialogOpen = ref(false)
const limitForm = useForm({
  credit_limit: props.customer.credit_limit,
})

const submitLimit = () => {
  limitForm.post(`/${companySlug.value}/fuel/credit-customers/${props.customer.id}/limit`, {
    preserveScroll: true,
    onSuccess: () => {
      limitDialogOpen.value = false
    },
  })
}

const toggleBlock = () => {
  router.post(`/${companySlug.value}/fuel/credit-customers/${props.customer.id}/toggle-block`, {}, {
    preserveScroll: true,
  })
}

const columns = [
  { key: 'date', label: 'Date', kind: 'date' as const },
  { key: 'type', label: 'Type', kind: 'status' as const },
  { key: 'description', label: 'Description', kind: 'text' as const },
  { key: 'amount', label: 'Amount', kind: 'amount' as const },
]

const tableData = computed(() => {
  return props.transactions.map((t) => ({
    id: t.id,
    date: formatDate(t.date),
    type: t.type,
    description: t.description,
    amount: t.amount,
    _raw: t,
  }))
})

const goBack = () => {
  router.get(`/${companySlug.value}/fuel/credit-customers`)
}
</script>

<template>
  <Head :title="customer.name" />

  <PageShell
    :title="customer.name"
    :description="customer.phone || customer.email || 'Credit customer details'"
    :icon="User"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="goBack">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button variant="outline" @click="limitDialogOpen = true">
        <Edit class="mr-2 h-4 w-4" />
        Set Limit
      </Button>
      <Button
        :variant="customer.is_credit_blocked ? 'default' : 'destructive'"
        @click="toggleBlock"
      >
        <component :is="customer.is_credit_blocked ? Unlock : Ban" class="mr-2 h-4 w-4" />
        {{ customer.is_credit_blocked ? 'Unblock' : 'Block Credit' }}
      </Button>
    </template>

    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-3">
      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Current Balance</CardDescription>
          <CardTitle class="text-2xl" :class="customer.current_balance > 0 ? 'text-status-attention' : 'text-status-success'">
            <MoneyText :amount="customer.current_balance" :currency="props.currency" />
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Wallet class="h-4 w-4" />
            <span>Outstanding receivable</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Credit Limit</CardDescription>
          <CardTitle class="text-2xl">
            <template v-if="customer.credit_limit > 0"><MoneyText :amount="customer.credit_limit" :currency="props.currency" /></template>
            <template v-else>No Limit</template>
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div v-if="customer.credit_limit > 0" class="text-sm text-text-secondary">
            <MoneyText :amount="Math.max(0, customer.credit_limit - customer.current_balance)" :currency="props.currency" /> available
          </div>
          <div v-else class="text-sm text-text-secondary">Unlimited credit</div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Status</CardDescription>
          <CardTitle class="text-2xl">
            <Badge
              :class="{
                'bg-status-critical/10 text-status-critical': customer.is_credit_blocked,
                'bg-status-attention/10 text-status-attention': !customer.is_credit_blocked && customer.credit_limit > 0 && customer.current_balance > customer.credit_limit,
                'bg-status-success/10 text-status-success': !customer.is_credit_blocked && (customer.credit_limit === 0 || customer.current_balance <= customer.credit_limit),
              }"
            >
              {{ customer.is_credit_blocked ? 'Blocked' : (customer.credit_limit > 0 && customer.current_balance > customer.credit_limit ? 'Over Limit' : 'Active') }}
            </Badge>
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="text-sm text-text-secondary">
            {{ customer.is_credit_blocked ? 'Cannot make credit purchases' : 'Credit enabled' }}
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Customer Details & Transactions -->
    <div class="grid gap-6 lg:grid-cols-3">
      <Card class="lg:col-span-1">
        <CardHeader>
          <CardTitle class="text-base">Customer Details</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div v-if="customer.code">
            <div class="text-sm text-muted-foreground">Code</div>
            <div class="font-medium">{{ customer.code }}</div>
          </div>
          <div v-if="customer.phone">
            <div class="text-sm text-muted-foreground">Phone</div>
            <div class="font-medium">{{ customer.phone }}</div>
          </div>
          <div v-if="customer.email">
            <div class="text-sm text-muted-foreground">Email</div>
            <div class="font-medium">{{ customer.email }}</div>
          </div>
          <div v-if="customer.address">
            <div class="text-sm text-muted-foreground">Address</div>
            <div class="font-medium">{{ customer.address }}</div>
          </div>
        </CardContent>
      </Card>

      <Card class="lg:col-span-2">
        <CardHeader>
          <CardTitle class="text-base">Transaction History</CardTitle>
          <CardDescription>Recent credit sales and collections.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <LedgerRegister :data="tableData" :columns="columns">
            <template #empty>
              <div class="py-8 text-center text-muted-foreground">
                No transactions yet
              </div>
            </template>

            <template #cell-type="{ row }">
              <Badge
                :class="row._raw.type === 'sale' ? 'bg-status-attention/10 text-status-attention' : 'bg-status-success/10 text-status-success'"
              >
                {{ row._raw.type === 'sale' ? 'Sale' : 'Collection' }}
              </Badge>
            </template>

            <template #cell-amount="{ row }">
              <span :class="row._raw.type === 'sale' ? 'text-status-attention' : 'text-status-success'" class="font-medium">
                <MoneyText
                  :amount="row._raw.amount"
                  :currency="props.currency"
                  :direction="row._raw.type === 'sale' ? 'inflow' : 'outflow'"
                />
              </span>
            </template>
          </LedgerRegister>
        </CardContent>
      </Card>
    </div>

    <!-- Credit Limit Dialog -->
    <Dialog v-model:open="limitDialogOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Set Credit Limit</DialogTitle>
          <DialogDescription>Set the maximum credit allowed for {{ customer.name }}.</DialogDescription>
        </DialogHeader>

        <form novalidate @submit.prevent="submitLimit" class="space-y-4">
          <div class="space-y-2">
            <Label for="credit_limit">Credit Limit</Label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">{{ currency }}</span>
              <Input
                id="credit_limit"
                v-model.number="limitForm.credit_limit"
                type="number"
                min="0"
                step="1"
                class="pl-14"
                :class="{ 'border-destructive': limitForm.errors.credit_limit }"
              />
            </div>
            <p class="text-sm text-muted-foreground">Set to 0 for unlimited credit.</p>
            <InputError :message="limitForm.errors.credit_limit" />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="limitDialogOpen = false" :disabled="limitForm.processing">
              Cancel
            </Button>
            <Button type="submit" :disabled="limitForm.processing">
              <span v-if="limitForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
              Save Limit
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
