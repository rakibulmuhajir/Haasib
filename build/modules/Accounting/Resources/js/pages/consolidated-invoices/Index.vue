<script setup lang="ts">
/** Every consolidated invoice sent, newest first. New ones are made from a customer's statement. */
import { Head, Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import type { RegisterColumn } from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import type { BreadcrumbItem } from '@/types'

interface Row {
  id: string
  number: string
  title: string
  customer_name: string
  period_from: string
  period_to: string
  total: number | string
  created_at: string
  line_count: number
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  documents: { data: Row[]; current_page: number; last_page: number; per_page: number; total: number }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Consolidated invoices', href: `/${props.company.slug}/consolidated-invoices` },
]

const columns: RegisterColumn<Row>[] = [
  { key: 'number', label: 'Number', kind: 'ref' },
  { key: 'created_at', label: 'Sent', kind: 'date' },
  { key: 'customer_name', label: 'Customer', kind: 'text' },
  { key: 'title', label: 'Title', kind: 'text' },
  { key: 'period_from', label: 'Period', kind: 'text' },
  { key: 'line_count', label: 'Lines', kind: 'amount' },
  { key: 'total', label: 'Total', kind: 'amount' },
]

const goToPage = (page: number) => router.get(`/${props.company.slug}/consolidated-invoices`, { page }, { preserveScroll: true })
</script>

<template>
  <Head title="Consolidated invoices" />

  <PageShell title="Consolidated invoices" description="Invoices that bill many sales at once." :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button as-child><Link :href="`/${company.slug}/consolidated-invoices/create`">New</Link></Button>
    </template>
    <LedgerRegister
      :data="documents.data"
      :columns="columns"
      :pagination="documents"
      :clickable="true"
      key-field="id"
      @row-click="(row: Row) => router.get(`/${company.slug}/consolidated-invoices/${row.id}`)"
      @page-change="goToPage"
    >
      <template #empty>None yet. Press New to make one.</template>
      <template #cell-created_at="{ row }">{{ String(row.created_at).slice(0, 10) }}</template>
      <template #cell-period_from="{ row }">{{ String(row.period_from).slice(0, 10) }} to {{ String(row.period_to).slice(0, 10) }}</template>
      <template #cell-total="{ row }"><MoneyText :amount="Number(row.total)" :currency="company.base_currency || 'PKR'" :show-currency="false" :fraction-digits="0" /></template>
    </LedgerRegister>
  </PageShell>
</template>
