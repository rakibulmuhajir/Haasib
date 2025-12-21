<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { BreadcrumbItem } from '@/types'
import { CalendarDays, Lock, Unlock, Pencil } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface AccountingPeriod {
  id: string
  period_number: number
  name: string
  start_date: string
  end_date: string
  is_closed: boolean
  closed_at: string | null
}

interface FiscalYear {
  id: string
  name: string
  start_date: string
  end_date: string
  is_current: boolean
  is_closed: boolean
  periods: AccountingPeriod[]
}

const props = defineProps<{
  company: CompanyRef
  fiscalYear: FiscalYear
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Fiscal Years', href: `/${props.company.slug}/fiscal-years` },
  { title: props.fiscalYear.name, href: `/${props.company.slug}/fiscal-years/${props.fiscalYear.id}` },
]

const periods = computed(() => props.fiscalYear.periods ?? [])

const periodType = ref('monthly')
const createPeriodsForm = useForm({ period_type: periodType.value })

const createPeriods = () => {
  createPeriodsForm
    .transform((d) => ({ period_type: periodType.value }))
    .post(`/${props.company.slug}/fiscal-years/${props.fiscalYear.id}/periods`, { preserveScroll: true })
}

const closePeriod = (id: string) => {
  router.post(`/${props.company.slug}/accounting-periods/${id}/close`, {}, { preserveScroll: true })
}

const reopenPeriod = (id: string) => {
  router.post(`/${props.company.slug}/accounting-periods/${id}/reopen`, {}, { preserveScroll: true })
}

const badgeVariant = (val: boolean) => (val ? 'outline' : 'success')
const badgeLabel = (val: boolean) => (val ? 'Closed' : 'Open')
</script>

<template>
  <Head :title="`Fiscal Year ${fiscalYear.name}`" />
  <PageShell
    :title="`Fiscal Year: ${fiscalYear.name}`"
    :breadcrumbs="breadcrumbs"
    :icon="CalendarDays"
  >
    <template #actions>
      <Button variant="secondary" @click="router.get(`/${company.slug}/fiscal-years/${fiscalYear.id}/edit`)">
        <Pencil class="mr-2 h-4 w-4" />
        Edit
      </Button>
    </template>

    <div class="grid gap-6 lg:grid-cols-3">
      <Card class="lg:col-span-1">
        <CardHeader>
          <CardTitle>Summary</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3 text-sm">
          <div class="flex items-center justify-between">
            <span>Dates</span>
            <span class="font-medium">
              {{ new Date(fiscalYear.start_date).toLocaleDateString() }} → {{ new Date(fiscalYear.end_date).toLocaleDateString() }}
            </span>
          </div>
          <div class="flex items-center justify-between">
            <span>Current</span>
            <Badge :variant="fiscalYear.is_current ? 'success' : 'secondary'">
              {{ fiscalYear.is_current ? 'Yes' : 'No' }}
            </Badge>
          </div>
        </CardContent>
      </Card>

      <Card class="lg:col-span-2">
        <CardHeader>
          <CardTitle>Accounting periods</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div v-if="periods.length === 0" class="rounded-lg border p-4 space-y-3">
            <div class="text-sm text-muted-foreground">
              No periods exist for this fiscal year yet.
            </div>
            <div class="flex flex-col gap-3 md:flex-row md:items-center">
              <div class="flex-1">
                <Select v-model="periodType">
                  <SelectTrigger>
                    <SelectValue placeholder="Select period type" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="monthly">Monthly</SelectItem>
                    <SelectItem value="quarterly">Quarterly</SelectItem>
                    <SelectItem value="yearly">Yearly</SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="createPeriodsForm.errors.period_type" class="text-sm text-red-600 mt-1">
                  {{ createPeriodsForm.errors.period_type }}
                </p>
              </div>
              <Button :disabled="createPeriodsForm.processing" @click="createPeriods">
                Generate periods
              </Button>
            </div>
          </div>

          <Table v-else>
            <TableHeader>
              <TableRow>
                <TableHead>#</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Dates</TableHead>
                <TableHead>Status</TableHead>
                <TableHead class="text-right">Action</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="p in periods" :key="p.id">
                <TableCell class="font-medium">{{ p.period_number }}</TableCell>
                <TableCell>{{ p.name }}</TableCell>
                <TableCell>
                  {{ new Date(p.start_date).toLocaleDateString() }} → {{ new Date(p.end_date).toLocaleDateString() }}
                </TableCell>
                <TableCell>
                  <Badge :variant="badgeVariant(p.is_closed)">{{ badgeLabel(p.is_closed) }}</Badge>
                </TableCell>
                <TableCell class="text-right">
                  <Button
                    v-if="!p.is_closed"
                    size="sm"
                    variant="outline"
                    @click="closePeriod(p.id)"
                  >
                    <Lock class="mr-2 h-4 w-4" />
                    Close
                  </Button>
                  <Button
                    v-else
                    size="sm"
                    variant="secondary"
                    @click="reopenPeriod(p.id)"
                  >
                    <Unlock class="mr-2 h-4 w-4" />
                    Reopen
                  </Button>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>

