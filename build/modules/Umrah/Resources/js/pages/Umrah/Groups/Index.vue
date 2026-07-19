<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import DateTimeText from '@/components/DateTimeText.vue'
import RecordPagination from '@/components/RecordPagination.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { BreadcrumbItem } from '@/types'
import { Calculator, LoaderCircle, Plane, Plus, Search, X } from 'lucide-vue-next'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  groups: { data: any[]; total: number; current_page: number; last_page: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null }
  filters: { search?: string }
  canViewAccounting: boolean
}>()

const search = ref(props.filters.search || '')
const searching = ref(false)

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Visa Groups', href: `/${props.company.slug}/umrah/groups` },
]

const applyFilters = () => {
  search.value = search.value.trim()
  router.get(`/${props.company.slug}/umrah/groups`, {
  search: search.value,
  }, {
    preserveState: true,
    onStart: () => { searching.value = true },
    onFinish: () => { searching.value = false },
  })
}

const clearFilters = () => {
  search.value = ''
  applyFilters()
}
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

    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto]">
      <div class="relative">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input v-model="search" class="pl-10" placeholder="Group number, name, passenger, or passport" @keyup.enter="applyFilters" />
      </div>
      <Button variant="secondary" :disabled="searching" @click="applyFilters">
        <LoaderCircle v-if="searching" class="mr-2 h-4 w-4 animate-spin" />
        <Search v-else class="mr-2 h-4 w-4" />Search
      </Button>
      <Button v-if="search" variant="ghost" :disabled="searching" @click="clearFilters">
        <X class="mr-2 h-4 w-4" />Clear
      </Button>
    </div>

    <Card>
      <CardContent class="p-0">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Group</TableHead>
              <TableHead>Agent</TableHead>
              <TableHead>Travel</TableHead>
              <TableHead class="text-center">Pax</TableHead>
              <TableHead class="text-right">Receivable</TableHead>
              <TableHead class="text-right">Balance</TableHead>
              <TableHead class="text-right">Payment</TableHead>
              <TableHead v-if="canViewAccounting" class="w-12"><span class="sr-only">Accounting</span></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableEmpty v-if="!groups.data.length" :colspan="canViewAccounting ? 8 : 7">
              {{ search ? 'No visa groups match your search.' : 'No visa groups yet.' }}
            </TableEmpty>
            <TableRow v-for="group in groups.data" :key="group.id" class="cursor-pointer" @click="router.get(`/${company.slug}/umrah/groups/${group.id}`)">
              <TableCell class="font-medium">{{ group.group_number }}</TableCell>
              <TableCell>{{ group.agent?.name || '-' }}</TableCell>
              <TableCell><DateTimeText :value="group.travel_date" mode="date" /></TableCell>
              <TableCell class="text-center">{{ group.passenger_count }}</TableCell>
              <TableCell class="text-right font-medium"><MoneyText :amount="group.total_receivable" :currency="company.base_currency" /></TableCell>
              <TableCell class="text-right font-medium"><MoneyText :amount="group.balance" :currency="company.base_currency" /></TableCell>
              <TableCell class="text-right">
                <Badge :variant="Number(group.balance || 0) <= 0 ? 'default' : 'secondary'">{{ Number(group.balance || 0) <= 0 ? 'Paid' : 'Unpaid' }}</Badge>
              </TableCell>
              <TableCell v-if="canViewAccounting" class="text-right">
                <Button type="button" variant="ghost" size="icon" title="Open group accounting" @click.stop="router.get(`/${company.slug}/umrah/groups/${group.id}/accounting`)">
                  <Calculator class="h-4 w-4" />
                </Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
        <RecordPagination :current-page="groups.current_page" :last-page="groups.last_page" :from="groups.from" :to="groups.to" :total="groups.total" :previous-url="groups.prev_page_url" :next-url="groups.next_page_url" />
      </CardContent>
    </Card>
  </PageShell>
</template>
