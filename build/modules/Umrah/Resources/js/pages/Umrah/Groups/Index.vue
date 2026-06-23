<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import type { BreadcrumbItem } from '@/types'
import { Plane, Plus, Search } from 'lucide-vue-next'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  groups: { data: any[] }
  statuses: Record<string, string>
  filters: { search?: string; status?: string }
}>()

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || 'all')

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Visa Groups', href: `/${props.company.slug}/umrah/groups` },
]

const applyFilters = () => router.get(`/${props.company.slug}/umrah/groups`, {
  search: search.value,
  status: status.value === 'all' ? '' : status.value,
}, { preserveState: true })
</script>

<template>
  <Head title="Visa Groups" />
  <PageShell title="Visa Groups" description="One group keeps agent, passports, visa cost, payment, travel, hotel, and transport together." :breadcrumbs="breadcrumbs" :icon="Plane">
    <template #actions>
      <Button @click="router.get(`/${company.slug}/umrah/groups/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Visa Group
      </Button>
    </template>

    <div class="grid gap-3 md:grid-cols-[1fr_240px]">
      <div class="relative">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input v-model="search" class="pl-10" placeholder="Search groups or agents..." @keyup.enter="applyFilters" />
      </div>
      <Select v-model="status" @update:modelValue="applyFilters">
        <SelectTrigger><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All statuses</SelectItem>
          <SelectItem v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <Card>
      <CardContent class="p-0">
        <div v-if="!groups.data.length" class="p-8 text-center text-sm text-muted-foreground">No visa groups yet.</div>
        <div v-for="group in groups.data" :key="group.id" class="grid cursor-pointer gap-3 border-b p-4 last:border-b-0 md:grid-cols-[1fr_160px_160px_160px]" @click="router.get(`/${company.slug}/umrah/groups/${group.id}`)">
          <div>
            <div class="font-medium">{{ group.group_number }} · {{ group.name }}</div>
            <div class="text-sm text-muted-foreground">{{ group.agent?.name || 'No agent' }} · {{ group.passenger_count }} passengers · {{ group.travel_date || 'No travel date' }}</div>
          </div>
          <div><Badge variant="secondary">{{ statuses[group.status] || group.status }}</Badge></div>
          <div class="font-medium"><MoneyText :amount="group.total_receivable" :currency="company.base_currency" /></div>
          <div class="font-medium text-right"><MoneyText :amount="group.balance" :currency="company.base_currency" /></div>
        </div>
      </CardContent>
    </Card>
  </PageShell>
</template>
