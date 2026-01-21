<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import type { BreadcrumbItem } from '@/types'
import { Receipt, ArrowLeft, User, Calendar, Banknote, FileText, Clock } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface Collection {
  id: string
  date: string
  reference: string | null
  customer_id: string | null
  customer_name: string
  payment_method: string
  amount: number
  notes: string | null
  status: string
  created_at: string
}

const props = defineProps<{
  collection: Collection
  currency: string
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
  { title: 'Collections', href: `/${companySlug.value}/fuel/collections` },
  { title: props.collection.reference || 'Collection', href: `/${companySlug.value}/fuel/collections/${props.collection.id}` },
])

const currency = computed(() => currencySymbol(props.currency))

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
}

const formatDateTime = (dateStr: string) => {
  return new Date(dateStr).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const paymentMethodLabel = (method: string) => {
  const labels: Record<string, string> = {
    cash: 'Cash',
    bank: 'Bank Deposit',
    transfer: 'Bank Transfer',
    cheque: 'Cheque',
  }
  return labels[method] || method
}

const goBack = () => {
  router.get(`/${companySlug.value}/fuel/collections`)
}

const goToCustomer = () => {
  if (props.collection.customer_id) {
    router.get(`/${companySlug.value}/fuel/credit-customers/${props.collection.customer_id}`)
  }
}
</script>

<template>
  <Head :title="collection.reference || 'Collection'" />

  <PageShell
    :title="collection.reference || 'Collection Details'"
    description="View collection payment details."
    :icon="Receipt"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="goBack">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back to Collections
      </Button>
    </template>

    <div class="mx-auto max-w-2xl space-y-6">
      <!-- Amount Card -->
      <Card class="border-border/80 bg-gradient-to-br from-emerald-500/10 via-teal-500/5 to-cyan-500/10">
        <CardContent class="pt-6">
          <div class="text-center">
            <p class="text-sm text-muted-foreground">Amount Collected</p>
            <p class="text-4xl font-bold text-emerald-600">
              {{ currency }} {{ formatCurrency(collection.amount) }}
            </p>
            <Badge class="mt-2" :class="{
              'bg-emerald-100 text-emerald-800': collection.status === 'posted',
              'bg-amber-100 text-amber-800': collection.status === 'pending',
            }">
              {{ collection.status === 'posted' ? 'Posted' : collection.status }}
            </Badge>
          </div>
        </CardContent>
      </Card>

      <!-- Details Card -->
      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Collection Details</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex items-start gap-3">
              <Calendar class="h-5 w-5 text-muted-foreground" />
              <div>
                <p class="text-sm text-muted-foreground">Collection Date</p>
                <p class="font-medium">{{ formatDate(collection.date) }}</p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <FileText class="h-5 w-5 text-muted-foreground" />
              <div>
                <p class="text-sm text-muted-foreground">Reference</p>
                <p class="font-medium">{{ collection.reference || '-' }}</p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <User class="h-5 w-5 text-muted-foreground" />
              <div>
                <p class="text-sm text-muted-foreground">Customer</p>
                <button
                  v-if="collection.customer_id"
                  class="font-medium hover:underline text-left"
                  @click="goToCustomer"
                >
                  {{ collection.customer_name }}
                </button>
                <p v-else class="font-medium">{{ collection.customer_name }}</p>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <Banknote class="h-5 w-5 text-muted-foreground" />
              <div>
                <p class="text-sm text-muted-foreground">Payment Method</p>
                <p class="font-medium">{{ paymentMethodLabel(collection.payment_method) }}</p>
              </div>
            </div>
          </div>

          <Separator v-if="collection.notes" />

          <div v-if="collection.notes" class="space-y-2">
            <p class="text-sm text-muted-foreground">Notes</p>
            <p class="text-sm">{{ collection.notes }}</p>
          </div>

          <Separator />

          <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Clock class="h-4 w-4" />
            <span>Created {{ formatDateTime(collection.created_at) }}</span>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
