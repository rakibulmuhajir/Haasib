<script setup lang="ts">
import DailyCloseNav from '../../../components/DailyCloseNav.vue'
import { computed, ref } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import {
  Calculator,
  Plus,
  Calendar,
  CheckCircle,
  Lock,
  XCircle,
  RotateCcw,
  MoreHorizontal,
  Eye,
  CalendarDays,
} from 'lucide-vue-next'
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime'
import MoneyText from '@/components/MoneyText.vue'

interface DailyClose {
  id: string
  transaction_number: string
  date: string
  opening_cash: number
  closing_cash: number
  total_revenue: number
  variance: number
  status: 'posted' | 'locked' | 'reversed' | 'reversal' | 'correction'
  is_locked?: boolean
  has_post_close_activity?: boolean
  /** True when any nozzle on this close returned pump-test litres to the tank. */
  has_pump_test?: boolean
  /** Present only when a rate changed during this close. */
  rate_change?: { changed: boolean; split?: boolean; fallback_liters: number } | null
  readings_taken_at?: string | null
  /** Hours since the previous day's readings; null when either close has no recorded time. */
  hours_covered?: number | null
}

const props = defineProps<{
  company: { id: string; name: string; slug: string }
  closes: DailyClose[]
  /** The window currently applied. Mirrors the `range` query parameter. */
  range?: '30' | '90' | '365' | 'all'
  /** Every close on record, ignoring the window, so the empty state can tell the difference. */
  totalCloses?: number
  parkedCloses?: Array<{ business_date: string; updated_at: string }>
  permissions: {
    canLock: boolean
    canUnlock: boolean
    canEditDay: boolean
  }
}>()

// "Edit day" is only offered for the single latest posted, unlocked close: reopening any
// earlier day is refused server-side too (its openings feed every later day), and the
// server is the source of truth for that -- this only avoids offering an action that would
// just bounce back with an error for the common case. `closes` is already ordered newest
// first (see DailyCloseService::getRecentCloses), so the first non-reversed row is it.
const latestPostedDate = computed(() => props.closes.find((c) => c.status !== 'reversed')?.date ?? null)
const canEditClose = (close: DailyClose) =>
  props.permissions.canEditDay && !close.is_locked && close.status !== 'reversed' && close.date === latestPostedDate.value

const RANGES = [
  { value: '30', label: 'Last 30 days' },
  { value: '90', label: 'Last 90 days' },
  { value: '365', label: 'Last year' },
  { value: 'all', label: 'All time' },
] as const

const activeRange = computed(() => props.range ?? '30')
const activeRangeLabel = computed(
  () => RANGES.find((r) => r.value === activeRange.value)?.label ?? 'Last 30 days',
)

/**
 * True when closes exist but none fall inside the window. This is the case that used to
 * render as "No daily close records found" under a "Create First Daily Close" button, which
 * was simply false - and gave no hint that a window was being applied at all.
 */
const hiddenByRange = computed(
  () => props.closes.length === 0 && (props.totalCloses ?? 0) > 0,
)

const setRange = (value: string) => {
  router.get(
    `/${props.company.slug}/fuel/daily-close/history`,
    { range: value },
    { preserveScroll: true, preserveState: true, replace: true },
  )
}

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Fuel', href: `/${props.company.slug}/fuel/dashboard` },
  { title: 'Daily Close History', href: `/${props.company.slug}/fuel/daily-close/history` },
])

const page = usePage()
const currency = computed(() => ((page.props as any)?.auth?.currentCompany?.base_currency as string) || 'PKR')

const formatDate = (date: string) => {
  return formatSharedDateTime(date, { mode: 'date', locale: 'en-PK' })
}

const getStatusConfig = (close: DailyClose) => {
  // Check is_locked first as it's a direct property
  if (close.is_locked || close.status === 'locked') {
    return {
      label: 'Locked',
      variant: 'secondary' as const,
      icon: Lock,
      class: 'text-status-attention',
    }
  }

  const configs: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline'; icon: typeof CheckCircle; class: string }> = {
    posted: { label: 'Posted', variant: 'default', icon: CheckCircle, class: 'text-status-success' },
    reversed: { label: 'Reversed', variant: 'destructive', icon: XCircle, class: 'text-status-critical' },
    reversal: { label: 'Reversal', variant: 'outline', icon: RotateCcw, class: 'text-status-attention' },
    correction: { label: 'Correction', variant: 'default', icon: CheckCircle, class: 'text-status-info' },
  }
  return configs[close.status] || configs.posted
}

// Lock month dialog - default to previous month
const lockMonthOpen = ref(false)
const now = new Date()
const prevMonth = now.getMonth() === 0 ? 12 : now.getMonth() // If January, previous is December (getMonth is 0-indexed)
const prevMonthYear = now.getMonth() === 0 ? now.getFullYear() - 1 : now.getFullYear()
const selectedYear = ref(prevMonthYear)
const selectedMonth = ref(prevMonth)

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return [currentYear - 1, currentYear, currentYear + 1]
})

const months = [
  { value: 1, label: 'January' },
  { value: 2, label: 'February' },
  { value: 3, label: 'March' },
  { value: 4, label: 'April' },
  { value: 5, label: 'May' },
  { value: 6, label: 'June' },
  { value: 7, label: 'July' },
  { value: 8, label: 'August' },
  { value: 9, label: 'September' },
  { value: 10, label: 'October' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' },
]

const lockMonth = () => {
  router.post(`/${props.company.slug}/fuel/daily-close/lock-month`, {
    year: selectedYear.value,
    month: selectedMonth.value,
  }, {
    preserveScroll: true,
    onSuccess: (page) => {
      const flash = (page.props as any).flash
      if (flash?.success) {
        lockMonthOpen.value = false
      }
    },
    onError: () => {
      toast.error('Failed to lock month')
    },
  })
}

const lockSingle = (closeId: string) => {
  router.post(`/${props.company.slug}/fuel/daily-close/${closeId}/lock`, {}, {
    preserveScroll: true,
    onError: () => toast.error('Failed to lock'),
  })
}

// Reopening a settled day always costs a reason — it is kept permanently against the day.
const unlockTarget = ref<{ id: string; label: string } | null>(null)
const unlockForm = useForm({ reason: '' })

const promptUnlock = (close: { id: string; transaction_number: string }) => {
  unlockForm.reset()
  unlockForm.clearErrors()
  unlockTarget.value = { id: close.id, label: close.transaction_number }
}

const confirmUnlock = () => {
  if (!unlockTarget.value) return
  unlockForm.post(`/${props.company.slug}/fuel/daily-close/${unlockTarget.value.id}/unlock`, {
    preserveScroll: true,
    onSuccess: () => { unlockTarget.value = null; unlockForm.reset() },
  })
}

// "Edit day": undoes everything the close posted and reopens the Create page as a parked
// draft for the same date. Always costs a reason, kept permanently in
// fuel.daily_close_revisions. See DailyCloseReopenService.
const editDayTarget = ref<{ id: string; label: string } | null>(null)
const editDayForm = useForm({ reason: '' })

const promptEditDay = (close: { id: string; transaction_number: string }) => {
  editDayForm.reset()
  editDayForm.clearErrors()
  editDayTarget.value = { id: close.id, label: close.transaction_number }
}

const confirmEditDay = () => {
  if (!editDayTarget.value) return
  editDayForm.post(`/${props.company.slug}/fuel/daily-close/${editDayTarget.value.id}/reopen`, {
    preserveScroll: true,
    onSuccess: () => { editDayTarget.value = null; editDayForm.reset() },
  })
}
</script>

<template>
  <Head title="Daily Close History" />

  <PageShell
    title="Daily Close History"
    description="View past daily close records"
    :icon="Calendar"
    :breadcrumbs="breadcrumbs"
  >
    <DailyCloseNav :company="company" history />
    <div v-if="parkedCloses?.length" class="my-4 rounded-lg border p-4">
      <h2 class="font-semibold">Parked Daily Closes</h2>
      <Link v-for="draft in parkedCloses" :key="draft.business_date" :href="`/${company.slug}/fuel/daily-close?date=${draft.business_date}`" class="mr-4 inline-block py-2 underline">Resume {{ draft.business_date }}</Link>
    </div>
    <template #actions>
      <div class="flex items-center gap-2">
        <Button v-if="permissions.canLock" variant="outline" @click="lockMonthOpen = true">
          <CalendarDays class="h-4 w-4 mr-2" />
          Lock Month
        </Button>
        <Button as-child>
          <Link :href="`/${company.slug}/fuel/daily-close`">
            <Plus class="h-4 w-4 mr-2" />
            New Daily Close
          </Link>
        </Button>
      </div>
    </template>

    <Card>
      <CardHeader>
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <CardTitle>Daily Closes</CardTitle>
            <CardDescription>{{ activeRangeLabel }}</CardDescription>
          </div>
          <div class="flex flex-wrap gap-1" role="group" aria-label="Date range">
            <Button
              v-for="option in RANGES"
              :key="option.value"
              type="button"
              size="sm"
              :variant="activeRange === option.value ? 'default' : 'outline'"
              :aria-pressed="activeRange === option.value"
              @click="setRange(option.value)"
            >
              {{ option.label }}
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <!-- Closes exist, just not in this window. Saying "none found" here was a lie. -->
        <div v-if="hiddenByRange" class="text-center py-12 text-muted-foreground">
          <Calculator class="h-12 w-12 mx-auto mb-4 opacity-50" />
          <p>
            No daily closes in this range.
            <span class="text-foreground">{{ totalCloses }}</span>
            on record in total.
          </p>
          <Button type="button" variant="outline" class="mt-4" @click="setRange('all')">
            Show all time
          </Button>
        </div>

        <div v-else-if="closes.length === 0" class="text-center py-12 text-muted-foreground">
          <Calculator class="h-12 w-12 mx-auto mb-4 opacity-50" />
          <p>No daily close records found.</p>
          <Button as-child class="mt-4">
            <Link :href="`/${company.slug}/fuel/daily-close`">
              Create First Daily Close
            </Link>
          </Button>
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="close in closes"
            :key="close.id"
            class="flex items-center justify-between p-4 rounded-lg border hover:bg-muted/50 transition-colors"
          >
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center">
                <component
                  :is="getStatusConfig(close).icon"
                  :class="['h-5 w-5', getStatusConfig(close).class]"
                />
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <Link
                    :href="`/${company.slug}/fuel/daily-close/${close.id}`"
                    class="font-medium hover:underline"
                  >
                    {{ formatDate(close.date) }}
                  </Link>
                </div>
                <div class="text-sm text-muted-foreground font-mono">
                  {{ close.transaction_number }} <Badge v-if="close.has_post_close_activity" variant="destructive">Post-close activity</Badge>
                  <!-- A rate change is the usual reason a day's revenue or margin looks
                       unlike its neighbours; saying so here saves the hunt. -->
                  <Badge
                    v-if="close.rate_change"
                    variant="secondary"
                    :title="close.rate_change.split
                      ? 'A new fuel rate took effect during this day; litres were split between the two rates at the meter reading taken at the change'
                      : 'A new fuel rate took effect on this day'"
                  >Rate change</Badge>
                  <!-- Litres priced without a reading at the moment of the change, so the
                       whole day went on one rate. An approximation, and it should say so. -->
                  <Badge
                    v-if="close.rate_change && close.rate_change.fallback_liters > 0"
                    variant="destructive"
                    :title="`${close.rate_change.fallback_liters} L priced at a single rate because no reading was taken when the rate changed`"
                  >Rate not split</Badge>
                  <!-- A day that ran longer or shorter than 24 hours - a midnight reading on a
                       rate-change night, usually - so its totals are not compared blind. -->
                  <Badge
                    v-if="close.hours_covered != null && Math.abs(close.hours_covered - 24) >= 1"
                    variant="outline"
                    :title="`Readings ${close.hours_covered} hours after the previous day's, not the usual 24`"
                  >{{ close.hours_covered }} h</Badge>
                  <!-- Fuel run through a pump for calibration and poured back into the tank -
                       not a sale, but worth flagging since it lowered this day's litres sold. -->
                  <Badge
                    v-if="close.has_pump_test"
                    variant="outline"
                    title="Litres were run through a pump for calibration and returned to the tank on this day"
                  >Pump test</Badge>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-6">
              <div class="text-right">
                <div class="text-sm text-muted-foreground">Revenue</div>
                <div class="font-semibold"><MoneyText :amount="close.total_revenue" :currency="currency" :fraction-digits="0" /></div>
              </div>

              <div class="text-right">
                <div class="text-sm text-muted-foreground">Closing Cash</div>
                <div class="font-semibold"><MoneyText :amount="close.closing_cash" :currency="currency" :fraction-digits="0" /></div>
              </div>

              <div class="text-right min-w-24">
                <div class="text-sm text-muted-foreground">Variance</div>
                <!-- A till that is over is as much a discrepancy as one that
                     is short, so the sign carries the direction and the colour
                     only says whether the drawer balanced. -->
                <div
                  :class="[
                    'font-mono font-semibold tabular-nums',
                    Math.round(close.variance) === 0 ? 'text-text-primary' : 'text-status-attention',
                  ]"
                >
                  <!-- Whole rupees: leftover paisa from litres x rate are not a variance anyone counts. -->
                  <template v-if="Math.round(close.variance) >= 0">+</template><MoneyText :amount="Math.round(close.variance)" :currency="currency" :fraction-digits="0" />
                </div>
              </div>

              <Badge :variant="getStatusConfig(close).variant">
                <component :is="getStatusConfig(close).icon" class="h-3 w-3 mr-1" />
                {{ getStatusConfig(close).label }}
              </Badge>

              <!-- Actions Dropdown -->
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" size="icon">
                    <MoreHorizontal class="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem as-child>
                    <Link :href="`/${company.slug}/fuel/daily-close/${close.id}`" class="flex items-center">
                      <Eye class="h-4 w-4 mr-2" />
                      View Details
                    </Link>
                  </DropdownMenuItem>

                  <DropdownMenuSeparator />

                  <template v-if="permissions.canLock && !close.is_locked && close.status === 'posted'">
                    <DropdownMenuItem @click="lockSingle(close.id)" class="flex items-center">
                      <Lock class="h-4 w-4 mr-2" />
                      Lock
                    </DropdownMenuItem>
                  </template>

                  <template v-if="permissions.canUnlock && close.is_locked">
                    <DropdownMenuItem @click="promptUnlock(close)" class="flex items-center">
                      <Lock class="h-4 w-4 mr-2" />
                      Reopen day
                    </DropdownMenuItem>
                  </template>

                  <template v-if="permissions.canEditDay && close.status !== 'reversed'">
                    <DropdownMenuItem
                      :disabled="!canEditClose(close)"
                      :title="close.is_locked ? 'Unlock the day first' : (close.date !== latestPostedDate ? 'Reopen the later posted day first' : undefined)"
                      @click="canEditClose(close) && promptEditDay(close)"
                      class="flex items-center"
                    >
                      <RotateCcw class="h-4 w-4 mr-2" />
                      Edit day
                    </DropdownMenuItem>
                  </template>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Lock Month Dialog -->
    <Dialog v-model:open="lockMonthOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Lock Month</DialogTitle>
          <DialogDescription>
            Lock all daily closes for a specific month. This will prevent post-close corrections to those entries.
          </DialogDescription>
        </DialogHeader>

        <div class="grid grid-cols-2 gap-4 py-4">
          <div class="space-y-2">
            <Label>Year</Label>
            <Select v-model="selectedYear">
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="year in years" :key="year" :value="year">
                  {{ year }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2">
            <Label>Month</Label>
            <Select v-model="selectedMonth">
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="month in months" :key="month.value" :value="month.value">
                  {{ month.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="lockMonthOpen = false">Cancel</Button>
          <Button @click="lockMonth">Lock Month</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Reopen Day Dialog -->
    <Dialog :open="unlockTarget !== null" @update:open="(open) => { if (!open) unlockTarget = null }">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Reopen {{ unlockTarget?.label }}?</DialogTitle>
          <DialogDescription>
            Post-close corrections become possible again. This reopening is recorded permanently
            against the day, with your name and the reason below.
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-2">
          <Label for="reopen-reason">Reason for reopening</Label>
          <Textarea
            id="reopen-reason"
            v-model="unlockForm.reason"
            rows="3"
            placeholder="e.g. Attendant reported nozzle 1 closing reading was transposed."
          />
          <p v-if="unlockForm.errors.reason" class="text-sm text-status-critical">
            {{ unlockForm.errors.reason }}
          </p>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="unlockTarget = null">Cancel</Button>
          <Button :disabled="unlockForm.processing" @click="confirmUnlock">Reopen day</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Edit Day Dialog -->
    <Dialog :open="editDayTarget !== null" @update:open="(open) => { if (!open) editDayTarget = null }">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Edit {{ editDayTarget?.label }}?</DialogTitle>
          <DialogDescription>
            Everything this close posted (journal, invoices, stock movements, payments) is removed and
            the day becomes a draft in the same form used to create it. Re-post when ready. This is
            recorded permanently against the day, with your name and the reason below.
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-2">
          <Label for="edit-day-reason">Reason for editing</Label>
          <Textarea
            id="edit-day-reason"
            v-model="editDayForm.reason"
            rows="3"
            placeholder="e.g. Forgot to record a customer payment during posting."
          />
          <p v-if="editDayForm.errors.reason" class="text-sm text-status-critical">
            {{ editDayForm.errors.reason }}
          </p>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="editDayTarget = null">Cancel</Button>
          <Button :disabled="editDayForm.processing" @click="confirmEditDay">Edit day</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
