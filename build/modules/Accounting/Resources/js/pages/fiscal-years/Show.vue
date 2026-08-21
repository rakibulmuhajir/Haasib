<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import LedgerRegister from '@/components/LedgerRegister.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import InputError from '@/components/InputError.vue'
import type { BreadcrumbItem } from '@/types'
import { formatDateTime } from '@/lib/datetime'
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

const formatDate = (value: string) => formatDateTime(value, { mode: 'date' })

/**
 * A period's number is its place in the fiscal year, not a figure to sum, so
 * it stays text rather than amount even though it is a digit. Open and
 * closed are the same two states a fiscal year itself carries, defined once
 * in status.ts -- a period does not get its own vocabulary for the same
 * fact.
 */
const periodColumns = [
  { key: 'period_number', label: '#', kind: 'text' as const },
  { key: 'name', label: 'Name', kind: 'text' as const },
  { key: 'dates', label: 'Dates', kind: 'text' as const },
  { key: 'status', label: 'Status', kind: 'status' as const },
  { key: 'actions', label: '', kind: 'text' as const, class: 'text-right', headerClass: 'text-right' },
]
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
      <Card class="lg:col-span-1" variant="detail">
        <CardHeader>
          <CardTitle>Summary</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3 text-sm">
          <div class="flex items-center justify-between">
            <span>Dates</span>
            <span class="font-medium">
              {{ formatDate(fiscalYear.start_date) }} → {{ formatDate(fiscalYear.end_date) }}
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

      <Card class="lg:col-span-2" variant="register">
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
                <InputError :message="createPeriodsForm.errors.period_type" />
              </div>
              <Button :disabled="createPeriodsForm.processing" @click="createPeriods">
                Generate periods
              </Button>
            </div>
          </div>

          <LedgerRegister v-else :data="periods" :columns="periodColumns">
            <template #cell-dates="{ row }">
              {{ formatDate(row.start_date) }} → {{ formatDate(row.end_date) }}
            </template>

            <template #cell-status="{ row }">
              <StatusBadge :status="row.is_closed ? 'closed' : 'open'" />
            </template>

            <template #cell-actions="{ row }">
              <div class="flex justify-end gap-2">
                <Button
                  v-if="!row.is_closed"
                  size="sm"
                  variant="outline"
                  @click="closePeriod(row.id)"
                >
                  <Lock class="mr-2 h-4 w-4" />
                  Close
                </Button>
                <Button
                  v-else
                  size="sm"
                  variant="secondary"
                  @click="reopenPeriod(row.id)"
                >
                  <Unlock class="mr-2 h-4 w-4" />
                  Reopen
                </Button>
              </div>
            </template>
          </LedgerRegister>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
