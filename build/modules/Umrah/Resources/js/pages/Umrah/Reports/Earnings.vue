<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { BreadcrumbItem } from '@/types'
import { BarChart3 } from 'lucide-vue-next'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  filters: { start: string; end: string }
  summary: Record<string, number>
  groups: any[]
}>()

const start = ref(props.filters.start)
const end = ref(props.filters.end)

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Earnings', href: `/${props.company.slug}/umrah/reports/earnings` },
]

const applyFilters = () => router.get(`/${props.company.slug}/umrah/reports/earnings`, { start: start.value, end: end.value }, { preserveState: true })
</script>

<template>
  <Head title="Umrah Earnings" />
  <PageShell title="Earnings Report" description="Visa revenue, costs, collections, balances, and group profit." :breadcrumbs="breadcrumbs" :icon="BarChart3">
    <div class="grid gap-3 md:grid-cols-[180px_180px_auto]">
      <div class="space-y-2"><Label>Start</Label><Input v-model="start" type="date" /></div>
      <div class="space-y-2"><Label>End</Label><Input v-model="end" type="date" /></div>
      <div class="flex items-end"><Button @click="applyFilters">Apply</Button></div>
    </div>

    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
      <Card><CardHeader><CardTitle>Groups</CardTitle></CardHeader><CardContent class="text-2xl font-semibold">{{ summary.groups }}</CardContent></Card>
      <Card><CardHeader><CardTitle>Revenue</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="summary.receivable" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Cost</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="summary.cost" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Profit</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="summary.profit" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Collected</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="summary.payments" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Balance</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="summary.agent_balance" :currency="company.base_currency" /></CardContent></Card>
    </div>

    <Card>
      <CardHeader><CardTitle>Groups</CardTitle></CardHeader>
      <CardContent class="space-y-3">
        <div v-if="!groups.length" class="text-sm text-muted-foreground">No groups in this period.</div>
        <div v-for="group in groups" :key="group.id" class="grid gap-2 rounded-md border p-3 md:grid-cols-[1fr_140px_140px_140px_140px]">
          <div>
            <div class="font-medium">{{ group.group_number }} · {{ group.name }}</div>
            <div class="text-sm text-muted-foreground">{{ group.agent?.name || 'No agent' }} · {{ group.status }}</div>
          </div>
          <div><MoneyText :amount="group.total_receivable" :currency="company.base_currency" /></div>
          <div><MoneyText :amount="Number(group.visa_cost_amount || 0) + Number(group.transport_cost_amount || 0)" :currency="company.base_currency" /></div>
          <div><MoneyText :amount="group.profit" :currency="company.base_currency" /></div>
          <div class="text-right"><MoneyText :amount="group.balance" :currency="company.base_currency" /></div>
        </div>
      </CardContent>
    </Card>
  </PageShell>
</template>
