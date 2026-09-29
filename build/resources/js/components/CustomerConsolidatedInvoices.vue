<script setup lang="ts">
/** On a customer's page: the consolidated invoices sent to them, and a way to make the next one. */
import { Link } from '@inertiajs/vue3'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

export interface SentDocument {
  id: string
  number: string
  title: string
  date: string
  period_from: string
  period_to: string
  total: number
  line_count: number
}

defineProps<{
  companySlug: string
  customerId: string
  documents: SentDocument[]
  currency: string
}>()
</script>

<template>
  <Card class="border-border/80">
    <CardHeader class="flex flex-row items-center justify-between gap-3 space-y-0">
      <CardTitle class="text-base">Consolidated invoices</CardTitle>
      <Button size="sm" as-child>
        <Link :href="`/${companySlug}/reports/statements?kind=customer&id=${customerId}&consolidate=1`">New</Link>
      </Button>
    </CardHeader>
    <CardContent class="p-0">
      <table v-if="documents.length" class="w-full text-sm">
        <tbody>
          <tr v-for="doc in documents" :key="doc.id" class="border-t first:border-t-0">
            <td class="px-4 py-2">
              <Link :href="`/${companySlug}/consolidated-invoices/${doc.id}`" class="font-medium text-primary underline-offset-2 hover:underline">{{ doc.number }}</Link>
              <span class="ml-2 text-xs text-muted-foreground">{{ doc.title }}</span>
            </td>
            <td class="px-2 py-2 text-muted-foreground">Sent {{ doc.date }}</td>
            <td class="px-2 py-2 text-muted-foreground">{{ doc.period_from }} to {{ doc.period_to }} · {{ doc.line_count }} lines</td>
            <td class="px-4 py-2 text-right tabular-nums"><MoneyText :amount="doc.total" :currency="currency" :fraction-digits="0" /></td>
          </tr>
        </tbody>
      </table>
      <p v-else class="px-4 pb-4 text-sm text-muted-foreground">None sent yet.</p>
      <div v-if="documents.length >= 10" class="border-t px-4 py-2 text-right">
        <Link :href="`/${companySlug}/consolidated-invoices?customer_id=${customerId}`" class="text-xs text-primary underline-offset-2 hover:underline">All</Link>
      </div>
    </CardContent>
  </Card>
</template>
