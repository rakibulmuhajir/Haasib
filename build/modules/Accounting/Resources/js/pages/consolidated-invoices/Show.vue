<script setup lang="ts">
/**
 * A saved consolidated invoice: exactly what was sent, printed or downloaded again. It cannot be
 * changed -- a new one is made from the customer's statement. See ConsolidatedInvoiceService.
 */
import { Head, Link } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import ConsolidatedInvoiceDocument from '../../components/ConsolidatedInvoiceDocument.vue'
import type { ConsolidatedDocumentData } from '../../components/ConsolidatedInvoiceDocument.vue'
import { Button } from '@/components/ui/button'
import { Download, Printer } from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  document: ConsolidatedDocumentData & {
    id: string
    customer_id: string
    customer_name: string
    period_from: string
    period_to: string
    created_by_name: string | null
    file_name: string
  }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Consolidated invoices', href: `/${props.company.slug}/consolidated-invoices` },
  { title: props.document.number, href: `/${props.company.slug}/consolidated-invoices/${props.document.id}` },
]

const print = () => window.print()
</script>

<template>
  <!-- Print > Save as PDF suggests the page title as the file name. -->
  <Head :title="document.file_name" />

  <PageShell :title="`${document.title} ${document.number}`" :description="document.customer_name" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button variant="outline" @click="print"><Printer class="mr-2 h-4 w-4" />Print</Button>
      <Button as-child>
        <a :href="`/${company.slug}/consolidated-invoices/${document.id}/pdf`"><Download class="mr-2 h-4 w-4" />Download PDF</a>
      </Button>
    </template>

    <p class="mb-4 text-sm text-muted-foreground print:hidden">
      Saved {{ document.date }}<span v-if="document.created_by_name"> by {{ document.created_by_name }}</span> ·
      <Link :href="`/${company.slug}/reports/statements?kind=customer&id=${document.customer_id}`" class="text-primary underline-offset-2 hover:underline">{{ document.customer_name }} statement</Link>
    </p>

    <ConsolidatedInvoiceDocument :document="document" />
  </PageShell>
</template>
