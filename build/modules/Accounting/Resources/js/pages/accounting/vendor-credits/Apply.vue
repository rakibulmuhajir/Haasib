<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { BreadcrumbItem } from '@/types'
import { ReceiptRefund, Save } from 'lucide-vue-next'

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

const totalApplied = computed(() => form.applications.reduce((sum, a) => sum + (Number(a.amount_applied) || 0), 0))

const updateAmount = (billId: string, amount: number) => {
  const existing = form.applications.find((a) => a.bill_id === billId)
  if (existing) {
    existing.amount_applied = amount
    return
  }
  form.applications.push({ bill_id: billId, amount_applied: amount })
}

const currentAmount = (billId: string) =>
  form.applications.find((a) => a.bill_id === billId)?.amount_applied ?? 0

const handleSubmit = () => {
  if (totalApplied.value - props.credit.amount > 0.000001) {
    alert('Applications exceed credit amount')
    return
  }
  form.post(`/${props.company.slug}/vendor-credits/${props.credit.id}/apply`, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head :title="`Apply ${credit.credit_number}`" />
  <PageShell
    :title="`Apply ${credit.credit_number}`"
    :breadcrumbs="breadcrumbs"
    :icon="ReceiptRefund"
  >
    <div class="mb-4 text-sm text-muted-foreground">
      Credit amount: {{ credit.amount }} {{ credit.currency }} · Total applying: {{ totalApplied.toFixed(2) }}
    </div>

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div
        v-for="bill in unpaidBills"
        :key="bill.id"
        class="flex items-center justify-between rounded border p-3"
      >
        <div>
          <div class="font-medium">{{ bill.bill_number }}</div>
          <div class="text-xs text-muted-foreground">Balance {{ bill.balance }} {{ bill.currency }}</div>
        </div>
        <Input
          class="w-32"
          type="number"
          min="0"
          step="0.01"
          placeholder="Amount"
          :value="currentAmount(bill.id)"
          @input="(e: any) => updateAmount(bill.id, parseFloat(e.target.value) || 0)"
        />
      </div>

      <div class="flex justify-end gap-3">
        <Button type="submit">
          <Save class="mr-2 h-4 w-4" />
          Apply Credit
        </Button>
      </div>
    </form>
  </PageShell>
</template>
