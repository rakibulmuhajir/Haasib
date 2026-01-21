<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
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
  Edit,
  GitBranch,
  CalendarDays,
} from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

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
  is_amendable?: boolean
  has_amendments?: boolean
}

const props = defineProps<{
  company: { id: string; name: string; slug: string }
  closes: DailyClose[]
  permissions: {
    canAmend: boolean
    canLock: boolean
    canUnlock: boolean
  }
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Fuel', href: `/${props.company.slug}/fuel/dashboard` },
  { title: 'Daily Close History', href: `/${props.company.slug}/fuel/daily-close/history` },
])

const page = usePage()
const currency = computed(() => currencySymbol(((page.props as any)?.auth?.currentCompany?.base_currency as string) || 'PKR'))

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PK', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-PK', {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const getStatusConfig = (close: DailyClose) => {
  // Check is_locked first as it's a direct property
  if (close.is_locked || close.status === 'locked') {
    return {
      label: 'Locked',
      variant: 'secondary' as const,
      icon: Lock,
      class: 'text-amber-600',
    }
  }

  const configs: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline'; icon: typeof CheckCircle; class: string }> = {
    posted: { label: 'Posted', variant: 'default', icon: CheckCircle, class: 'text-green-600' },
    reversed: { label: 'Reversed', variant: 'destructive', icon: XCircle, class: 'text-red-600' },
    reversal: { label: 'Reversal', variant: 'outline', icon: RotateCcw, class: 'text-amber-600' },
    correction: { label: 'Correction', variant: 'default', icon: CheckCircle, class: 'text-blue-600' },
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

const unlockSingle = (closeId: string) => {
  router.post(`/${props.company.slug}/fuel/daily-close/${closeId}/unlock`, {}, {
    preserveScroll: true,
    onError: () => toast.error('Failed to unlock'),
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
        <CardTitle>Recent Daily Closes</CardTitle>
        <CardDescription>Last 30 days of daily close records</CardDescription>
      </CardHeader>
      <CardContent>
        <div v-if="closes.length === 0" class="text-center py-12 text-muted-foreground">
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
                  <Badge v-if="close.has_amendments" variant="outline" class="text-xs">
                    <GitBranch class="h-3 w-3 mr-1" />
                    Amended
                  </Badge>
                </div>
                <div class="text-sm text-muted-foreground font-mono">
                  {{ close.transaction_number }}
                </div>
              </div>
            </div>

            <div class="flex items-center gap-6">
              <div class="text-right">
                <div class="text-sm text-muted-foreground">Revenue</div>
                <div class="font-semibold">{{ currency }} {{ formatCurrency(close.total_revenue) }}</div>
              </div>

              <div class="text-right">
                <div class="text-sm text-muted-foreground">Closing Cash</div>
                <div class="font-semibold">{{ currency }} {{ formatCurrency(close.closing_cash) }}</div>
              </div>

              <div class="text-right min-w-24">
                <div class="text-sm text-muted-foreground">Variance</div>
                <div
                  :class="[
                    'font-semibold',
                    close.variance === 0 ? 'text-green-600' : close.variance > 0 ? 'text-blue-600' : 'text-red-600',
                  ]"
                >
                  {{ close.variance >= 0 ? '+' : '' }}{{ currency }} {{ formatCurrency(close.variance) }}
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

                  <template v-if="permissions.canAmend && close.is_amendable && close.status === 'posted' && !close.is_locked">
                    <DropdownMenuItem as-child>
                      <Link :href="`/${company.slug}/fuel/daily-close/${close.id}/amend`" class="flex items-center">
                        <Edit class="h-4 w-4 mr-2" />
                        Amend
                      </Link>
                    </DropdownMenuItem>
                  </template>

                  <DropdownMenuSeparator />

                  <template v-if="permissions.canLock && !close.is_locked && close.status === 'posted'">
                    <DropdownMenuItem @click="lockSingle(close.id)" class="flex items-center">
                      <Lock class="h-4 w-4 mr-2" />
                      Lock
                    </DropdownMenuItem>
                  </template>

                  <template v-if="permissions.canUnlock && close.is_locked">
                    <DropdownMenuItem @click="unlockSingle(close.id)" class="flex items-center">
                      <Lock class="h-4 w-4 mr-2" />
                      Unlock
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
            Lock all daily closes for a specific month. This will prevent any amendments to those entries.
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
  </PageShell>
</template>
