<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import InlineEditable from '@/components/InlineEditable.vue'
import MoneyText from '@/components/MoneyText.vue'
import { useInlineEdit } from '@/composables/useInlineEdit'
import { useLexicon } from '@/composables/useLexicon'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import {
  Building2,
  Users,
  UserPlus,
  Mail,
  Calendar,
  Shield,
  MoreVertical,
  Trash2,
  UserCog,
  CheckCircle2,
  XCircle,
  Receipt,
  AlertTriangle,
  Wallet,
  Ban,
  BarChart3,
  Settings,
  Globe,
  Languages,
  TrendingUp,
  TrendingDown,
} from 'lucide-vue-next'
import { toast } from 'vue-sonner'

interface Company {
  id: string
  name: string
  slug: string
  base_currency: string
  is_active: boolean
  created_at: string
  industry?: string
  country?: string
  language?: string
  locale?: string
  fiscal_year_start_month?: number
}

interface Stats {
  total_users: number
  active_users: number
  admins: number
}

interface Financials {
  ar_outstanding: number
  ar_outstanding_count: number
  ar_overdue: number
  ar_overdue_count: number
  payments_mtd: number
  expenses_mtd_placeholder: string
  aging: {
    current: number
    bucket_1_30: number
    bucket_31_60: number
    bucket_31_60: number
    bucket_61_90: number
    bucket_90_plus: number
  }
  quick_stats: {
    invoices_sent_this_month: number
    payments_received_this_month: number
    new_customers_this_month: number
  }
  recent_activity: Array<{
    type: string
    label: string
    amount?: number
    currency?: string
    status?: string
    occurred_at: string
  }>
}

interface PendingInvitation {
  id: string
  email: string
  role: string
  expires_at: string
  created_at: string
  inviter_name: string | null
  inviter_email: string | null
  token: string
}

interface User {
  id: string
  name: string | null
  email: string
  role: string
  is_active: boolean
  joined_at: string | null
}

interface DashboardData {
  cash_position: {
    total: number
    accounts: Array<{ name: string, balance: number, currency: string }>
  }
  money_in_out: {
    money_in: { current: number, last: number, growth: number }
    money_out: { current: number, last: number, growth: number }
  }
  needs_attention: {
    overdue_invoices: number
    bills_due_soon: number
    bills_due_soon_amount?: number
    unreconciled_transactions: number
  }
  profit_loss: {
    income: number
    expenses: number
    profit: number
    last_month_profit: number
    profit_growth: number
    period: string
  }
}

const props = defineProps<{
  company: Company
  stats: Stats
  users: User[]
  currentUserRole: string
  pendingInvitations: PendingInvitation[]
  financials: Financials
  dashboard: DashboardData
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Home', href: '/dashboard' },
  { title: 'Companies', href: '/companies' },
  { title: props.company.name },
])

// Tab state
const activeTab = ref('overview')

// Setup inline editing
const inlineEdit = useInlineEdit({
  endpoint: `/${props.company.slug}/settings`,
  successMessage: 'Setting updated successfully',
  errorMessage: 'Failed to update setting',
})

const { t, tpl } = useLexicon()

// Register editable fields
const nameField = inlineEdit.registerField('name', props.company.name)
const languageField = inlineEdit.registerField('language', props.company.language || 'en')
const localeField = inlineEdit.registerField('locale', props.company.locale || 'en_US')
const fiscalYearField = inlineEdit.registerField('fiscal_year_start_month', props.company.fiscal_year_start_month || 1)

// Invite dialog
const inviteDialogOpen = ref(false)
const roleDialogOpen = ref(false)
const removeDialogOpen = ref(false)
const selectedUser = ref<User | null>(null)

const inviteForm = useForm({
  email: '',
  role: 'member',
})

const roleForm = useForm({
  userId: '',
  role: '',
})

const removeForm = useForm({})

const canManage = computed(() => ['owner', 'admin'].includes(props.currentUserRole))

const availableRoles = ['owner', 'admin', 'accountant', 'viewer', 'member']

const languageOptions = [
  { value: 'en', label: 'English' },
  { value: 'ar', label: 'Arabic' },
  { value: 'fr', label: 'French' },
  { value: 'de', label: 'German' },
  { value: 'es', label: 'Spanish' },
]

const localeOptions = [
  { value: 'en_US', label: 'English (US)' },
  { value: 'en_GB', label: 'English (UK)' },
  { value: 'ar_SA', label: 'Arabic (Saudi Arabia)' },
  { value: 'ar_AE', label: 'Arabic (UAE)' },
  { value: 'fr_FR', label: 'French (France)' },
  { value: 'de_DE', label: 'German (Germany)' },
  { value: 'es_ES', label: 'Spanish (Spain)' },
]

const monthOptions = [
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

const getRoleBadgeVariant = (role: string) => {
  const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    owner: 'default',
    admin: 'default',
    accountant: 'secondary',
    viewer: 'outline',
    member: 'outline',
  }
  return variants[role.toLowerCase()] || 'outline'
}

const handleInvite = () => {
  inviteForm.post(`/${props.company.slug}/users/invite`, {
    onSuccess: () => {
      inviteForm.reset()
      inviteDialogOpen.value = false
      toast.success('Invitation sent successfully')
    },
    onError: () => {
      toast.error('Failed to send invitation')
    },
  })
}

const openRoleDialog = (user: User) => {
  selectedUser.value = user
  roleForm.userId = user.id
  roleForm.role = user.role
  roleDialogOpen.value = true
}

const handleRoleUpdate = () => {
  roleForm.put(`/${props.company.slug}/users/${roleForm.userId}/role`, {
    onSuccess: () => {
      roleDialogOpen.value = false
      selectedUser.value = null
      toast.success('Role updated successfully')
    },
    onError: () => {
      toast.error('Failed to update role')
    },
  })
}

const openRemoveDialog = (user: User) => {
  selectedUser.value = user
  removeDialogOpen.value = true
}

const handleRemoveUser = () => {
  if (!selectedUser.value) return

  removeForm.delete(`/${props.company.slug}/users/${selectedUser.value.id}`, {
    onSuccess: () => {
      removeDialogOpen.value = false
      selectedUser.value = null
      toast.success('User removed successfully')
    },
    onError: () => {
      toast.error('Failed to remove user')
    },
  })
}

const tableColumns = [
  { key: 'name', label: 'User', sortable: true },
  { key: 'role', label: 'Role', sortable: true },
  { key: 'is_active', label: 'Status', sortable: true },
  { key: 'joined_at', label: 'Joined', sortable: true },
  { key: 'actions', label: '', class: 'text-right' },
]

const moneyLocale = (currencyCode?: string) => {
  const code = currencyCode || props.company.base_currency || 'USD'
  if (code === 'PKR') return 'en-PK'
  return 'en-US'
}

const currencySymbol = (currencyCode: string) => {
  const locale = moneyLocale(currencyCode)
  const formatter = new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: currencyCode,
    currencyDisplay: 'narrowSymbol',
  })
  return formatter.formatToParts(0).find((p) => p.type === 'currency')?.value ?? currencyCode
}
</script>

<template>
  <Head :title="company.name" />
  <Tabs v-model="activeTab" class="w-full">
    <PageShell
      :title="company.name"
      :icon="Building2"
      :breadcrumbs="breadcrumbs"
      :badge="{ text: company.is_active ? 'Active' : 'Inactive', variant: company.is_active ? 'default' : 'secondary' }"
      compact
    >
      <template #description>
        <span class="font-mono text-zinc-400">{{ company.slug }}</span>
        <span class="mx-2 text-zinc-300">•</span>
        <span class="text-zinc-600">{{ currencySymbol(company.base_currency) }}</span>
      </template>

      <template #actions>
        <TabsList class="bg-zinc-100">
        <TabsTrigger value="overview" class="gap-2">
          <BarChart3 class="h-4 w-4" />
          Dashboard
        </TabsTrigger>
        <TabsTrigger value="settings" class="gap-2">
          <Settings class="h-4 w-4" />
          Settings
        </TabsTrigger>
        <TabsTrigger value="users" class="gap-2">
          <Users class="h-4 w-4" />
          Users
        </TabsTrigger>
      </TabsList>
      </template>

      <!-- Overview Tab (Dashboard) -->
      <TabsContent value="overview" class="space-y-6">
        
        <!-- Cash Position -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader class="pb-2">
            <CardTitle class="text-sm font-medium text-zinc-500 uppercase tracking-wider">Cash Position</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="mb-4">
              <div class="text-3xl font-bold text-zinc-900">
                <MoneyText :amount="dashboard.cash_position.total" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
              </div>
              <p class="text-xs text-zinc-500 mt-1">Total across all accounts</p>
            </div>
            <div v-if="dashboard.cash_position.accounts.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
              <div v-for="(account, idx) in dashboard.cash_position.accounts" :key="idx" 
                class="p-3 bg-zinc-50 rounded-lg border border-zinc-100"
              >
                <div class="text-xs text-zinc-500 mb-1 truncate">{{ account.name }}</div>
                <div class="font-semibold text-zinc-800">
                  <MoneyText :amount="account.balance" :currency="account.currency" :locale="moneyLocale(account.currency)" />
                </div>
              </div>
            </div>
            <div v-else class="text-sm text-zinc-500 italic">No bank accounts connected.</div>
          </CardContent>
        </Card>

        <!-- P&L Summary Widget -->
        <Card class="border-zinc-200/80 bg-gradient-to-br from-white to-zinc-50">
          <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
              <CardTitle class="text-sm font-medium text-zinc-500 uppercase tracking-wider">{{ t('profit') }} - {{ dashboard.profit_loss.period }}</CardTitle>
              <Button variant="ghost" size="sm" class="text-xs text-zinc-500 hover:text-zinc-700" @click="router.visit(`/${company.slug}/reports/profit-loss`)">
                View Report →
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div class="flex items-center gap-4 mb-4">
              <div class="text-4xl font-bold" :class="dashboard.profit_loss.profit >= 0 ? 'text-emerald-600' : 'text-red-600'">
                <MoneyText :amount="dashboard.profit_loss.profit" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
              </div>
              <div v-if="dashboard.profit_loss.profit_growth !== 0" class="flex items-center gap-1 px-2 py-1 rounded-full text-sm"
                :class="dashboard.profit_loss.profit_growth >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                <component :is="dashboard.profit_loss.profit_growth >= 0 ? TrendingUp : TrendingDown" class="h-4 w-4" />
                {{ dashboard.profit_loss.profit_growth >= 0 ? '+' : '' }}{{ dashboard.profit_loss.profit_growth }}%
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4 pt-4 border-t border-zinc-100">
              <div>
                <div class="text-xs text-zinc-500 uppercase tracking-wide mb-1">{{ t('moneyIn') }}</div>
                <div class="text-lg font-semibold text-zinc-800">
                  <MoneyText :amount="dashboard.profit_loss.income" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                </div>
              </div>
              <div>
                <div class="text-xs text-zinc-500 uppercase tracking-wide mb-1">{{ t('moneyOut') }}</div>
                <div class="text-lg font-semibold text-zinc-800">
                  <MoneyText :amount="dashboard.profit_loss.expenses" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Money In / Money Out -->
        <div class="grid gap-6 md:grid-cols-2">
          <!-- Money In -->
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500 uppercase tracking-wider">{{ t('moneyIn') }}</CardTitle>
              <Badge :variant="dashboard.money_in_out.money_in.growth >= 0 ? 'default' : 'destructive'" class="text-xs">
                {{ dashboard.money_in_out.money_in.growth >= 0 ? '+' : '' }}{{ dashboard.money_in_out.money_in.growth.toFixed(1) }}%
              </Badge>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold text-zinc-900">
                <MoneyText :amount="dashboard.money_in_out.money_in.current" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
              </div>
              <p class="text-xs text-zinc-500 mt-1">
                This month vs
                <MoneyText :amount="dashboard.money_in_out.money_in.last" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                last month
              </p>
              
              <div class="mt-4 pt-4 border-t flex justify-between items-center">
                <div class="text-sm text-zinc-600">
                  <span class="font-medium">{{ financials.ar_overdue_count }}</span> invoices overdue
                </div>
                <div class="text-sm font-semibold text-amber-600">
                  <MoneyText :amount="financials.ar_overdue" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                  overdue
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Money Out -->
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500 uppercase tracking-wider">{{ t('moneyOut') }}</CardTitle>
              <Badge :variant="dashboard.money_in_out.money_out.growth <= 0 ? 'default' : 'outline'" class="text-xs">
                {{ dashboard.money_in_out.money_out.growth >= 0 ? '+' : '' }}{{ dashboard.money_in_out.money_out.growth.toFixed(1) }}%
              </Badge>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold text-zinc-900">
                <MoneyText :amount="dashboard.money_in_out.money_out.current" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
              </div>
              <p class="text-xs text-zinc-500 mt-1">
                This month vs
                <MoneyText :amount="dashboard.money_in_out.money_out.last" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                last month
              </p>
              
            <div class="mt-4 pt-4 border-t flex justify-between items-center">
              <div class="text-sm text-zinc-600">
                <span class="font-medium">{{ dashboard.needs_attention.bills_due_soon }}</span> bills due soon
              </div>
              <div class="text-sm font-semibold text-amber-600">
                <MoneyText
                  :amount="dashboard.needs_attention.bills_due_soon_amount || 0"
                  :currency="company.base_currency"
                  :locale="moneyLocale(company.base_currency)"
                />
                due soon
              </div>
            </div>
          </CardContent>
          </Card>
        </div>

        <!-- Quick Actions & Needs Attention -->
        <div class="grid gap-6 md:grid-cols-3">
          
          <!-- Quick Actions -->
          <Card class="md:col-span-2 border-zinc-200/80 bg-white">
            <CardHeader>
              <CardTitle class="text-base font-semibold text-zinc-900">Quick Actions</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <Button variant="outline" class="h-auto py-4 flex flex-col items-center gap-2 hover:border-primary/50 hover:bg-primary/5" @click="router.visit(`/${company.slug}/invoices/create`)">
                  <Receipt class="h-6 w-6 text-primary" />
                  <span>{{ t('createInvoice') }}</span>
                </Button>
                <Button variant="outline" class="h-auto py-4 flex flex-col items-center gap-2 hover:border-primary/50 hover:bg-primary/5" @click="router.visit(`/${company.slug}/payments/create`)">
                  <Wallet class="h-6 w-6 text-emerald-600" />
                  <span>{{ t('recordPayment') }}</span>
                </Button>
                <Button variant="outline" class="h-auto py-4 flex flex-col items-center gap-2 hover:border-primary/50 hover:bg-primary/5" @click="router.visit(`/${company.slug}/bills/create`)">
                  <Ban class="h-6 w-6 text-red-500" />
                  <span>{{ t('enterBill') }}</span>
                </Button>
                <Button variant="outline" class="h-auto py-4 flex flex-col items-center gap-2 hover:border-primary/50 hover:bg-primary/5" @click="router.visit(`/${company.slug}/banking/feed`)">
                  <BarChart3 class="h-6 w-6 text-indigo-600" />
                  <span>{{ t('reviewTransactionsAction') }}</span>
                </Button>
                <Button variant="outline" class="h-auto py-4 flex flex-col items-center gap-2 hover:border-primary/50 hover:bg-primary/5" @click="router.visit(`/${company.slug}/sales/create`)">
                  <Receipt class="h-6 w-6 text-primary" />
                  <span>{{ t('recordSale') }}</span>
                </Button>
                <Button variant="outline" class="h-auto py-4 flex flex-col items-center gap-2 hover:border-primary/50 hover:bg-primary/5" @click="router.visit(`/${company.slug}/reports/profit-loss`)">
                  <BarChart3 class="h-6 w-6 text-indigo-600" />
                  <span>{{ t('profitAndLoss') }}</span>
                </Button>
              </div>
            </CardContent>
          </Card>

          <!-- Needs Attention -->
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader>
              <CardTitle class="text-base font-semibold text-zinc-900">Needs Attention</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-3">
                <div v-if="dashboard.needs_attention.overdue_invoices > 0" class="flex items-center gap-3 p-2 rounded-md bg-red-50 border border-red-100">
                  <AlertTriangle class="h-5 w-5 text-red-600" />
                  <div class="text-sm">
                    <span class="font-bold text-red-800">{{ dashboard.needs_attention.overdue_invoices }}</span> invoices overdue
                  </div>
                </div>
                
                <div v-if="dashboard.needs_attention.bills_due_soon > 0" class="flex items-center gap-3 p-2 rounded-md bg-amber-50 border border-amber-100">
                  <Calendar class="h-5 w-5 text-amber-600" />
                  <div class="text-sm">
                    <span class="font-bold text-amber-800">{{ dashboard.needs_attention.bills_due_soon }}</span> bills due this week
                  </div>
                </div>

                <div v-if="dashboard.needs_attention.unreconciled_transactions > 0" class="flex items-center gap-3 p-2 rounded-md bg-indigo-50 border border-indigo-100 cursor-pointer hover:bg-indigo-100 transition-colors" @click="router.visit(`/${company.slug}/banking/feed`)">
                  <Wallet class="h-5 w-5 text-indigo-600" />
                  <div class="text-sm">
                    {{ tpl('transactionsToReviewCount', { count: dashboard.needs_attention.unreconciled_transactions }) }}
                  </div>
                </div>

                <div v-if="dashboard.needs_attention.overdue_invoices === 0 && dashboard.needs_attention.bills_due_soon === 0 && dashboard.needs_attention.unreconciled_transactions === 0" class="text-center py-4 text-zinc-500 text-sm">
                  <CheckCircle2 class="h-8 w-8 text-emerald-500 mx-auto mb-2" />
                  All caught up!
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-sm font-medium text-zinc-500">Recent Activity</CardTitle>
            <CardDescription>Last few accounting events</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3 text-sm text-zinc-700">
            <div v-for="(item, idx) in financials.recent_activity" :key="idx" class="flex justify-between border-b border-zinc-200 pb-2 last:border-b-0 last:pb-0">
              <div>
                <p class="font-medium text-zinc-900">{{ item.label }}</p>
                <p class="text-xs text-zinc-500">{{ new Date(item.occurred_at).toLocaleDateString() }}</p>
              </div>
              <div v-if="item.amount" class="font-mono text-sm text-zinc-800">
                <MoneyText :amount="item.amount" :currency="item.currency || company.base_currency" :locale="moneyLocale(item.currency || company.base_currency)" />
              </div>
            </div>
            <div v-if="financials.recent_activity.length === 0" class="text-sm text-zinc-500">No recent activity.</div>
          </CardContent>
        </Card>
      </TabsContent>

      <!-- Settings Tab -->
      <TabsContent value="settings" class="space-y-6">
        <!-- Editable Settings -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-zinc-900">Company Settings</CardTitle>
            <CardDescription class="text-zinc-500">
              {{ canManage ? 'Click on the pencil icon to edit a setting' : 'Contact an owner or admin to make changes' }}
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-6 md:grid-cols-2">
              <!-- Company Name (Editable) -->
              <InlineEditable
                v-model="nameField.value.value"
                label="Company Name"
                :editing="nameField.isEditing.value"
                :saving="nameField.isSaving.value"
                :can-edit="canManage"
                type="text"
                @start-edit="nameField.startEditing()"
                @save="nameField.save()"
                @cancel="nameField.cancelEditing()"
              />

              <!-- Slug (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Slug</Label>
                <div class="font-mono text-base text-zinc-900">{{ company.slug }}</div>
                <p class="text-xs text-zinc-400">Cannot be changed</p>
              </div>

              <!-- Base Currency (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Base Currency</Label>
                <div class="text-base text-zinc-900">
                  <span class="font-mono">{{ currencySymbol(company.base_currency) }}</span>
                  <span class="ml-2 text-sm text-zinc-500">({{ company.base_currency }})</span>
                </div>
                <p class="text-xs text-zinc-400">Cannot be changed after creation</p>
              </div>

              <!-- Country (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Country</Label>
                <div class="text-base text-zinc-900">{{ company.country || '—' }}</div>
                <p class="text-xs text-zinc-400">Cannot be changed</p>
              </div>

              <!-- Industry (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Industry</Label>
                <div class="text-base text-zinc-900 capitalize">{{ company.industry || '—' }}</div>
              </div>

              <!-- Created Date (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Created</Label>
                <div class="flex items-center gap-1.5 text-base text-zinc-900">
                  <Calendar class="h-3.5 w-3.5 text-zinc-400" />
                  {{ new Date(company.created_at).toLocaleDateString() }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Regional Settings -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-zinc-900 flex items-center gap-2">
              <Globe class="h-4 w-4" />
              Regional Settings
            </CardTitle>
            <CardDescription class="text-zinc-500">
              Language, locale, and fiscal year preferences
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-6 md:grid-cols-2">
              <!-- Language (Editable) -->
              <InlineEditable
                v-model="languageField.value.value"
                label="Language"
                :editing="languageField.isEditing.value"
                :saving="languageField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="languageOptions"
                :icon="Languages"
                @start-edit="languageField.startEditing()"
                @save="languageField.save()"
                @cancel="languageField.cancelEditing()"
              />

              <!-- Locale (Editable) -->
              <InlineEditable
                v-model="localeField.value.value"
                label="Locale"
                :editing="localeField.isEditing.value"
                :saving="localeField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="localeOptions"
                @start-edit="localeField.startEditing()"
                @save="localeField.save()"
                @cancel="localeField.cancelEditing()"
              />

              <!-- Fiscal Year Start Month (Editable) -->
              <InlineEditable
                v-model="fiscalYearField.value.value"
                label="Fiscal Year Start"
                :editing="fiscalYearField.isEditing.value"
                :saving="fiscalYearField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="monthOptions"
                :icon="Calendar"
                helper-text="Month when your fiscal year begins"
                @start-edit="fiscalYearField.startEditing()"
                @save="fiscalYearField.save()"
                @cancel="fiscalYearField.cancelEditing()"
              />
            </div>
          </CardContent>
        </Card>

        <!-- Pending Invitations (visible to owners) -->
        <Card v-if="currentUserRole === 'owner' && pendingInvitations.length > 0" class="border-amber-100 bg-amber-50">
          <CardHeader class="pb-3">
            <CardTitle class="text-amber-900">Pending Invitations</CardTitle>
            <CardDescription class="text-amber-700">
              Invites sent from {{ company.name }}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-3">
              <div
                v-for="invite in pendingInvitations"
                :key="invite.id"
                class="flex flex-col gap-1 rounded-lg border border-amber-200/80 bg-white px-4 py-3 shadow-xs"
              >
                <div class="flex items-center justify-between">
                  <div class="font-medium text-amber-900">{{ invite.email }}</div>
                  <Badge variant="outline" class="capitalize text-amber-800">
                    {{ invite.role }}
                  </Badge>
                </div>
                <div class="text-xs text-amber-700">
                  Expires {{ new Date(invite.expires_at).toLocaleDateString() }}
                  <span v-if="invite.inviter_name"> • Invited by {{ invite.inviter_name }}</span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </TabsContent>

      <!-- Users Tab -->
      <TabsContent value="users" class="space-y-6">
        <DataTable
          :data="users"
          :columns="tableColumns"
          title="Team Members"
          :description="`${users.length} ${users.length === 1 ? 'member' : 'members'} in this company`"
          key-field="id"
          hoverable
        >
          <template #header>
            <Button v-if="canManage" size="sm" @click="inviteDialogOpen = true">
              <UserPlus class="mr-2 h-4 w-4" />
              Invite User
            </Button>
          </template>

          <template #cell-name="{ row }">
            <div class="flex flex-col">
              <span class="font-medium text-zinc-900">{{ row.name || 'Unknown' }}</span>
              <div class="flex items-center gap-1 text-zinc-500">
                <Mail class="h-3 w-3" />
                <span class="text-xs">{{ row.email }}</span>
              </div>
            </div>
          </template>

          <template #cell-role="{ row }">
            <Badge :variant="getRoleBadgeVariant(row.role)" class="capitalize">
              <Shield class="mr-1 h-3 w-3" />
              {{ row.role }}
            </Badge>
          </template>

          <template #cell-is_active="{ row }">
            <Badge :variant="row.is_active ? 'default' : 'secondary'">
              <component :is="row.is_active ? CheckCircle2 : XCircle" class="mr-1 h-3 w-3" />
              {{ row.is_active ? 'Active' : 'Inactive' }}
            </Badge>
          </template>

          <template #cell-joined_at="{ row }">
            <div v-if="row.joined_at" class="flex items-center gap-1 text-zinc-700">
              <Calendar class="h-3 w-3 text-zinc-400" />
              <span>{{ new Date(row.joined_at).toLocaleDateString() }}</span>
            </div>
            <span v-else class="text-zinc-400">—</span>
          </template>

          <template #cell-actions="{ row }">
            <div class="flex justify-end">
              <DropdownMenu v-if="canManage">
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" size="sm">
                    <MoreVertical class="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem @click="openRoleDialog(row)">
                    <UserCog class="mr-2 h-4 w-4" />
                    Change Role
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="openRemoveDialog(row)" class="text-red-600 focus:text-red-600">
                    <Trash2 class="mr-2 h-4 w-4" />
                    Remove User
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </template>
        </DataTable>
      </TabsContent>

    <!-- Invite User Dialog -->
    <Dialog v-model:open="inviteDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-zinc-900">Invite User</DialogTitle>
          <DialogDescription class="text-zinc-500">
            Send an invitation to join {{ company.name }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label for="email" class="text-zinc-700">Email</Label>
            <Input
              id="email"
              v-model="inviteForm.email"
              type="email"
              placeholder="user@example.com"
              class="border-zinc-200"
            />
            <p v-if="inviteForm.errors.email" class="text-xs text-red-600">
              {{ inviteForm.errors.email }}
            </p>
          </div>
          <div class="space-y-2">
            <Label class="text-zinc-700">Role</Label>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="w-full justify-between border-zinc-200">
                  <span class="capitalize">{{ inviteForm.role }}</span>
                  <span class="ml-2">▼</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-full">
                <DropdownMenuItem
                  v-for="role in availableRoles"
                  :key="role"
                  @click="inviteForm.role = role"
                  class="capitalize"
                >
                  {{ role }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="inviteDialogOpen = false" :disabled="inviteForm.processing">
            Cancel
          </Button>
          <Button @click="handleInvite" :disabled="inviteForm.processing">
            <span v-if="inviteForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
            Send Invitation
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Change Role Dialog -->
    <Dialog v-model:open="roleDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-zinc-900">Change User Role</DialogTitle>
          <DialogDescription class="text-zinc-500">
            Update the role for {{ selectedUser?.name || selectedUser?.email }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label class="text-zinc-700">Role</Label>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="w-full justify-between border-zinc-200">
                  <span class="capitalize">{{ roleForm.role }}</span>
                  <span class="ml-2">▼</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-full">
                <DropdownMenuItem
                  v-for="role in availableRoles"
                  :key="role"
                  @click="roleForm.role = role"
                  class="capitalize"
                >
                  {{ role }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="roleDialogOpen = false" :disabled="roleForm.processing">
            Cancel
          </Button>
          <Button @click="handleRoleUpdate" :disabled="roleForm.processing">
            <span v-if="roleForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
            Update Role
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Remove User Confirmation -->
    <ConfirmDialog
      v-model:open="removeDialogOpen"
      variant="destructive"
      title="Remove User"
      :description="`Are you sure you want to remove ${selectedUser?.name || selectedUser?.email} from ${company.name}? This action cannot be undone.`"
      confirm-text="Remove User"
      :loading="removeForm.processing"
      @confirm="handleRemoveUser"
    />
  </PageShell>
  </Tabs>
</template>
