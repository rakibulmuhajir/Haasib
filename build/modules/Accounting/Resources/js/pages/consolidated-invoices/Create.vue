<script setup lang="ts">
/** New consolidated invoice: choose the customer and dates, then pick the unpaid lines to bill. */
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import ConsolidatedInvoiceForm from '../../components/ConsolidatedInvoiceForm.vue'
import type { BillToDefaults, BilledByDefaults, InvoiceRow } from '../../components/ConsolidatedInvoiceForm.vue'
import type { BreadcrumbItem } from '@/types'
import type { DocumentIssuer } from '@/components/LedgerDocument.vue'
import type { DocumentStampData } from '@/components/DocumentStamp.vue'

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  customers: Array<{ id: string; name: string; customer_number: string | null }>
  filters: { customer_id: string | null; from: string; to: string }
  rows: InvoiceRow[]
  billTo: BillToDefaults | null
  billedBy: BilledByDefaults | null
  labels: Record<string, string>
  issuer: DocumentIssuer
  stamp?: DocumentStampData | null
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Consolidated invoices', href: `/${props.company.slug}/consolidated-invoices` },
  { title: 'New', href: `/${props.company.slug}/consolidated-invoices/create` },
]

const customerId = ref(props.filters.customer_id ?? '')
const from = ref(props.filters.from)
const to = ref(props.filters.to)

// A new customer starts from their oldest unpaid invoice (the server's default); new dates keep the customer.
const load = (keepDates: boolean) => router.get(`/${props.company.slug}/consolidated-invoices/create`, {
  customer_id: customerId.value || undefined,
  ...(keepDates ? { from: from.value, to: to.value } : {}),
}, { preserveScroll: true })
</script>

<template>
  <Head title="New consolidated invoice" />

  <PageShell title="New consolidated invoice" :breadcrumbs="breadcrumbs">
    <div class="space-y-6">
      <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_10rem_10rem_auto] md:items-end print:hidden">
        <div class="space-y-1.5">
          <Label for="ci-customer">Customer</Label>
          <Select :model-value="customerId" @update:model-value="(v) => { customerId = String(v); load(false) }">
            <SelectTrigger id="ci-customer"><SelectValue placeholder="Choose a customer" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="space-y-1.5">
          <Label for="ci-from">From</Label>
          <Input id="ci-from" v-model="from" type="date" />
        </div>
        <div class="space-y-1.5">
          <Label for="ci-to">To</Label>
          <Input id="ci-to" v-model="to" type="date" />
        </div>
        <Button variant="outline" :disabled="!customerId" @click="load(true)">Show</Button>
      </div>

      <ConsolidatedInvoiceForm
        v-if="filters.customer_id"
        :key="`${filters.customer_id}-${filters.from}-${filters.to}`"
        :rows="rows"
        :bill-to="billTo"
        :billed-by="billedBy"
        :labels="labels"
        :currency="company.base_currency || 'PKR'"
        :from="filters.from"
        :to="filters.to"
        :company-slug="company.slug"
        :customer-id="filters.customer_id"
        :issuer="issuer"
        :stamp="stamp"
      />
      <p v-else class="text-sm text-muted-foreground">Choose a customer to see their unpaid invoices.</p>
    </div>
  </PageShell>
</template>
