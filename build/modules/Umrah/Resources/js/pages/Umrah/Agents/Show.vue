<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Users } from 'lucide-vue-next'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  agent: any
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Agents', href: `/${props.company.slug}/umrah/agents` },
  { title: props.agent.name, href: `/${props.company.slug}/umrah/agents/${props.agent.id}` },
]
</script>

<template>
  <Head :title="agent.name" />
  <PageShell :title="agent.name" :description="`${agent.agent_number} · ${agent.phone || 'No phone'}`" :breadcrumbs="breadcrumbs" :icon="Users">
    <div class="grid gap-4 md:grid-cols-3">
      <Card><CardHeader><CardTitle>Total Receivable</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="agent.total_receivable" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Paid</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="agent.total_paid" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Balance</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="agent.balance" :currency="company.base_currency" /></CardContent></Card>
    </div>

    <Card>
      <CardHeader><CardTitle>Groups</CardTitle></CardHeader>
      <CardContent class="space-y-3">
        <div v-if="!agent.groups?.length" class="text-sm text-muted-foreground">No groups for this agent yet.</div>
        <div v-for="group in agent.groups" :key="group.id" class="flex items-center justify-between rounded-md border p-3">
          <div>
            <div class="font-medium">{{ group.group_number }} · {{ group.name }}</div>
            <div class="text-sm text-muted-foreground">{{ group.status }} · {{ group.passenger_count }} passengers</div>
          </div>
          <Button variant="outline" size="sm" @click="router.get(`/${company.slug}/umrah/groups/${group.id}`)">Open</Button>
        </div>
      </CardContent>
    </Card>
  </PageShell>
</template>
