<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { HandCoins, ArrowLeft, Check, Clock, CheckCircle, Banknote, CreditCard } from 'lucide-vue-next'

interface Handover {
  id: string
  attendant_id: string
  attendant_name: string
  handover_date: string
  pump_id: string
  pump_name: string
  shift: 'day' | 'night'
  cash_amount: number
  easypaisa_amount: number
  jazzcash_amount: number
  bank_transfer_amount: number
  card_swipe_amount: number
  parco_card_amount: number
  total_amount: number
  status: 'pending' | 'received' | 'reconciled'
  received_at?: string | null
  received_by?: string | null
  notes?: string | null
}

const props = defineProps<{
  handover: Handover
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
  { title: 'Handovers', href: `/${companySlug.value}/fuel/handovers` },
  { title: props.handover.attendant_name, href: `/${companySlug.value}/fuel/handovers/${props.handover.id}` },
])

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('en-PK', {
    style: 'currency',
    currency: 'PKR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

const formatDateTime = (date: string) => {
  return new Date(date).toLocaleString('en-PK', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const receiveHandover = () => {
  const slug = companySlug.value
  if (!slug) return

  router.post(`/${slug}/fuel/handovers/${props.handover.id}/receive`, {}, {
    preserveScroll: true,
  })
}

const paymentBreakdown = computed(() => [
  { label: 'Cash', amount: props.handover.cash_amount, icon: Banknote, color: 'text-green-600' },
  { label: 'EasyPaisa', amount: props.handover.easypaisa_amount, icon: CreditCard, color: 'text-blue-600' },
  { label: 'JazzCash', amount: props.handover.jazzcash_amount, icon: CreditCard, color: 'text-purple-600' },
  { label: 'Bank Transfer', amount: props.handover.bank_transfer_amount, icon: Banknote, color: 'text-indigo-600' },
  { label: 'Card Swipe', amount: props.handover.card_swipe_amount, icon: CreditCard, color: 'text-red-600' },
  { label: 'Parco Card', amount: props.handover.parco_card_amount, icon: CreditCard, color: 'text-orange-600' },
])

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'pending':
      return { class: 'bg-amber-100 text-amber-800', icon: Clock, label: 'Pending' }
    case 'received':
      return { class: 'bg-emerald-100 text-emerald-800', icon: CheckCircle, label: 'Received' }
    case 'reconciled':
      return { class: 'bg-sky-100 text-sky-800', icon: Check, label: 'Reconciled' }
    default:
      return { class: 'bg-zinc-100 text-zinc-700', icon: Clock, label: status }
  }
}
</script>

<template>
  <Head :title="`Handover: ${handover.attendant_name}`" />

  <PageShell
    :title="`Handover from ${handover.attendant_name}`"
    :description="`${handover.pump_name} • ${handover.shift} shift • ${formatDateTime(handover.handover_date)}`"
    :icon="HandCoins"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${companySlug}/fuel/handovers`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button
        v-if="handover.status === 'pending'"
        class="bg-emerald-600 hover:bg-emerald-700"
        @click="receiveHandover"
      >
        <Check class="mr-2 h-4 w-4" />
        Mark as Received
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-3">
      <Card class="relative overflow-hidden border-border/80 bg-gradient-to-br from-amber-500/10 via-orange-500/5 to-red-500/10">
        <CardHeader class="pb-2">
          <CardDescription>Total Amount</CardDescription>
          <CardTitle class="text-3xl">{{ formatCurrency(handover.total_amount) }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Banknote class="h-4 w-4 text-amber-600" />
            <span>Collected in shift</span>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Status</CardDescription>
          <CardTitle class="text-lg capitalize">
            <Badge :class="getStatusBadge(handover.status).class" class="text-sm">
              <component :is="getStatusBadge(handover.status).icon" class="mr-1 h-3 w-3" />
              {{ getStatusBadge(handover.status).label }}
            </Badge>
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0 space-y-1">
          <p class="text-sm text-text-secondary">Submitted: {{ formatDateTime(handover.handover_date) }}</p>
          <p v-if="handover.received_at" class="text-sm text-text-secondary">
            Received: {{ formatDateTime(handover.received_at) }}
          </p>
          <p v-if="handover.received_by" class="text-sm text-text-secondary">
            By: {{ handover.received_by }}
          </p>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader class="pb-2">
          <CardDescription>Details</CardDescription>
          <CardTitle class="text-lg">{{ handover.pump_name }}</CardTitle>
        </CardHeader>
        <CardContent class="pt-0 space-y-1">
          <Badge variant="outline" :class="handover.shift === 'day' ? 'border-amber-200 text-amber-700' : 'border-indigo-200 text-indigo-700'">
            {{ handover.shift.charAt(0).toUpperCase() + handover.shift.slice(1) }} Shift
          </Badge>
          <p class="text-sm text-text-secondary">{{ handover.attendant_name }}</p>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="text-base">Payment Breakdown</CardTitle>
        <CardDescription>Collections by payment method</CardDescription>
      </CardHeader>

      <CardContent class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="payment in paymentBreakdown"
            :key="payment.label"
            class="flex items-center justify-between p-3 rounded-lg border border-border/70 bg-muted/30"
          >
            <div class="flex items-center gap-3">
              <component :is="payment.icon" :class="payment.color" class="h-5 w-5" />
              <span class="font-medium">{{ payment.label }}</span>
            </div>
            <span class="font-semibold" :class="payment.amount > 0 ? payment.color : 'text-text-secondary'">
              {{ formatCurrency(payment.amount) }}
            </span>
          </div>
        </div>

        <div class="pt-4 border-t border-border/50 flex justify-between items-center">
          <span class="text-lg font-medium">Total</span>
          <span class="text-xl font-bold">{{ formatCurrency(handover.total_amount) }}</span>
        </div>
      </CardContent>
    </Card>

    <Card v-if="handover.notes" class="border-border/80">
      <CardHeader>
        <CardTitle class="text-base">Notes</CardTitle>
      </CardHeader>
      <CardContent>
        <p class="text-text-secondary">{{ handover.notes }}</p>
      </CardContent>
    </Card>
  </PageShell>
</template>