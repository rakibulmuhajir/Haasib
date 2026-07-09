<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { BreadcrumbItem } from '@/types'
import { Plane, Plus, Users } from 'lucide-vue-next'

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  summary: Record<string, number>
  upcomingGroups: any[]
  recentGroups: any[]
}>()

const page = usePage()
const currentRole = (page.props.auth as any)?.currentCompanyRole || null
const canViewAccounting = ['super_admin', 'owner', 'accountant'].includes(String(currentRole))

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
]

const openGroup = (id: string) => router.get(`/${props.company.slug}/umrah/groups/${id}`)
</script>

<template>
  <Head title="Umrah Dashboard" />
  <PageShell title="Umrah Dashboard" description="Visa groups, passports, travel dates, payments, and balances." :breadcrumbs="breadcrumbs" :icon="Plane">
    <template #actions>
      <Button @click="router.get(`/${company.slug}/umrah/groups/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Visa Group
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
      <Card>
        <CardHeader><CardTitle>Active Groups</CardTitle></CardHeader>
        <CardContent class="text-2xl font-semibold">{{ summary.active_groups }}</CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle>Passports</CardTitle></CardHeader>
        <CardContent class="text-2xl font-semibold">{{ summary.passports_in_process }}</CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle>Unpaid Balance</CardTitle></CardHeader>
        <CardContent class="text-2xl font-semibold"><MoneyText :amount="summary.agent_balance" :currency="company.base_currency" /></CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle>To Collect This Month</CardTitle></CardHeader>
        <CardContent class="text-2xl font-semibold"><MoneyText :amount="summary.month_revenue" :currency="company.base_currency" /></CardContent>
      </Card>
      <Card v-if="canViewAccounting">
        <CardHeader><CardTitle>Month Profit</CardTitle></CardHeader>
        <CardContent class="text-2xl font-semibold"><MoneyText :amount="summary.month_profit" :currency="company.base_currency" /></CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle>Collected</CardTitle></CardHeader>
        <CardContent class="text-2xl font-semibold"><MoneyText :amount="summary.payments_this_month" :currency="company.base_currency" /></CardContent>
      </Card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle>Upcoming Travel</CardTitle>
          <CardDescription>Groups with travel dates coming up.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <div v-if="!upcomingGroups.length" class="text-sm text-muted-foreground">No upcoming groups yet.</div>
          <div v-for="group in upcomingGroups" :key="group.id" class="flex items-center justify-between rounded-md border p-3">
            <div>
              <div class="font-medium">{{ group.group_number }} · {{ group.name }}</div>
              <div class="text-sm text-muted-foreground">{{ group.agent?.name || 'No agent' }} · {{ group.travel_date || 'No date' }}</div>
            </div>
            <Button variant="outline" size="sm" @click="openGroup(group.id)">Open</Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Recent Groups</CardTitle>
          <CardDescription>Latest work added to the system.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <div v-if="!recentGroups.length" class="text-sm text-muted-foreground">Create your first visa group to begin.</div>
          <div v-for="group in recentGroups" :key="group.id" class="flex items-center justify-between rounded-md border p-3">
            <div>
              <div class="font-medium">{{ group.group_number }} · {{ group.name }}</div>
              <div class="text-sm text-muted-foreground">{{ group.agent?.name || 'No agent' }} · <MoneyText :amount="group.balance" :currency="company.base_currency" /> balance</div>
            </div>
            <Badge variant="secondary">{{ group.status }}</Badge>
          </div>
        </CardContent>
      </Card>
    </div>

    <div class="grid gap-3 md:grid-cols-3">
      <Button variant="outline" @click="router.get(`/${company.slug}/umrah/agents`)">
        <Users class="mr-2 h-4 w-4" />
        Agents
      </Button>
      <Button variant="outline" @click="router.get(`/${company.slug}/umrah/vendors`)">Visa Vendors</Button>
      <Button variant="outline" @click="router.get(`/${company.slug}/umrah/reports/earnings`)">Earnings Report</Button>
    </div>
  </PageShell>
</template>
