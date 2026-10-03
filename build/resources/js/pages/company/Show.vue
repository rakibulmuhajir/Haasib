<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import InlineEditable from '@/components/InlineEditable.vue'
import MoneyText from '@/components/MoneyText.vue'
import FinancialPosition from '@/components/FinancialPosition.vue'
import Derivation from '@/components/Derivation.vue'
import type { DerivationLine } from '@/components/Derivation.vue'
import MetaChip from '@/components/MetaChip.vue'
import type { RegisterColumn } from '@/components/LedgerRegister.vue'
import { useInlineEdit } from '@/composables/useInlineEdit'
import { useLexicon } from '@/composables/useLexicon'
import { formatDateTime } from '@/lib/datetime'
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
import { Building2, Users, UserPlus, Mail, Calendar, Shield, MoreVertical, Trash2, UserCog, CheckCircle2, XCircle, BarChart3, Settings, Globe, Languages } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { currencySymbol as sharedCurrencySymbol } from '@/lib/utils'

const formatDate = (value: string) => formatDateTime(value, { mode: 'date' })

interface Company {
  id: string
  name: string
  slug: string
  base_currency: string
  is_active: boolean
  created_at: string
  industry?: string
  industry_code?: string | null
  industry_name?: string | null
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
    /** Which column the figure belongs in. `null` for entries with no money. */
    direction?: 'in' | 'out' | null
  }>
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
  financials: Financials
  dashboard: DashboardData
  /** Present only for users permitted to see the company's position; null otherwise. */
  financialPosition?: {
    cash: number
    bank: number
    receivable: number
    payable: number
    net: number
    breakdown: Record<'cash' | 'bank' | 'receivable' | 'payable', Array<{ label: string; amount: number }>>
  } | null
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
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

// User management dialogs
const createUserDialogOpen = ref(false)
const roleDialogOpen = ref(false)
const removeDialogOpen = ref(false)
const selectedUser = ref<User | null>(null)

const createUserForm = useForm({
  name: '',
  email: '',
  role: 'operations',
  password: '',
  password_confirmation: '',
})

const roleForm = useForm({
  userId: '',
  role: '',
})

const removeForm = useForm({})

const canManage = computed(() => ['owner', 'manager'].includes(props.currentUserRole))
const pageTitle = computed(() => props.company.name)
const pageIcon = computed(() => Building2)
const pageBreadcrumbs = computed<BreadcrumbItem[]>(() => breadcrumbs.value)

const availableRoles = ['manager', 'accountant', 'operations']

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
    manager: 'default',
    accountant: 'secondary',
    viewer: 'outline',
    member: 'outline',
  }
  return variants[role.toLowerCase()] || 'outline'
}

const handleCreateUser = () => {
  createUserForm.post(`/${props.company.slug}/users`, {
    onSuccess: () => {
      createUserForm.reset()
      createUserForm.role = 'operations'
      createUserDialogOpen.value = false
      toast.success('User created successfully')
    },
    onError: () => {
      toast.error('Failed to create user')
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
  { key: 'name', label: 'User', sortable: true, kind: 'text' as const },
  { key: 'role', label: 'Role', sortable: true, kind: 'text' as const },
  { key: 'is_active', label: 'Status', sortable: true, kind: 'status' as const },
  { key: 'joined_at', label: 'Joined', sortable: true, kind: 'date' as const },
  { key: 'actions', label: '', class: 'text-right' },
]

const moneyLocale = (currencyCode?: string) => {
  const code = currencyCode || props.company.base_currency || 'USD'
  if (code === 'PKR') return 'en-PK'
  return 'en-US'
}

/* The symbol lookup lives in lib/utils; this page only supplies the locale,
   because Rs and ₨ differ by locale for the same PKR code. */
const currencySymbol = (currencyCode: string) => sharedCurrencySymbol(currencyCode, moneyLocale(currencyCode))

/* ── The dashboard, read as a reckoning ─────────────────────────────────────
   Eight cards each announcing a number is a list of facts. The page below is
   an argument: this is what you hold, this is what is already spoken for,
   therefore this is what is free. Everything else on the page answers to it. */

const baseCurrency = computed(() => props.company.base_currency)
const baseLocale = computed(() => moneyLocale(props.company.base_currency))

/** Money already promised to someone else in the next few days. */
const committedSoon = computed(() => props.dashboard.needs_attention.bills_due_soon_amount ?? 0)

const standLines = computed<DerivationLine[]>(() => {
  const lines: DerivationLine[] = [
    { label: 'In your accounts', amount: props.dashboard.cash_position.total, sign: null },
  ]
  if (committedSoon.value > 0) {
    lines.push({ label: 'Bills falling due', amount: committedSoon.value, sign: '−' })
  }
  return lines
})

const freeToCommit = computed(() => props.dashboard.cash_position.total - committedSoon.value)

const accountCount = computed(() => props.dashboard.cash_position.accounts.length)

/* Profit and loss, stated the same way — earned, less spent, therefore kept. */
const periodLines = computed<DerivationLine[]>(() => [
  { label: 'Money earned', amount: props.dashboard.profit_loss.income, sign: null },
  { label: 'Money spent', amount: props.dashboard.profit_loss.expenses, sign: '−' },
])

const periodResultLabel = computed(() =>
  props.dashboard.profit_loss.profit < 0 ? 'Loss for the period' : 'Kept',
)

interface AttentionItem {
  key: string
  label: string
  why: string
  chip: string
  tone: 'late' | 'attention' | 'info'
  href: string
}

/* Ordered by how badly it wants you: money already late, then money about to
   leave, then bookkeeping. Rows with a count of zero never appear — an item
   that says "0 overdue invoices" is asking to be read and then ignored. */
const attentionItems = computed<AttentionItem[]>(() => {
  const n = props.dashboard.needs_attention
  const slug = props.company.slug
  const items: AttentionItem[] = []

  if (n.overdue_invoices > 0) {
    items.push({
      key: 'overdue',
      label: n.overdue_invoices === 1 ? 'One invoice is overdue' : `${n.overdue_invoices} invoices are overdue`,
      why: 'Work you have already done that has not been paid for.',
      chip: 'Past due',
      tone: 'late',
      href: `/${slug}/invoices?status=overdue`,
    })
  }

  if (n.bills_due_soon > 0) {
    items.push({
      key: 'bills',
      label: n.bills_due_soon === 1 ? 'One bill is due this week' : `${n.bills_due_soon} bills are due this week`,
      why: 'Money that leaves the account whether or not you look.',
      chip: '7 days',
      tone: 'attention',
      href: `/${slug}/bills`,
    })
  }

  if (n.unreconciled_transactions > 0) {
    items.push({
      key: 'unreconciled',
      label: `${n.unreconciled_transactions} bank ${n.unreconciled_transactions === 1 ? 'line has' : 'lines have'} not been matched`,
      why: 'Until these are matched, the figures above are an estimate.',
      chip: 'To match',
      tone: 'info',
      href: `/${slug}/bank-reconciliation`,
    })
  }

  return items
})

/* The register. IN and OUT are separate columns because a signed single column
   makes the reader do the sorting; two columns let the eye do it. */
interface ActivityRow {
  key: string
  occurred_at: string
  label: string
  inAmount: number | null
  outAmount: number | null
  currency: string
}

const activityRows = computed<ActivityRow[]>(() =>
  props.financials.recent_activity.map((item, index) => ({
    key: `${item.type}-${index}`,
    occurred_at: item.occurred_at,
    label: item.label,
    inAmount: item.direction === 'in' ? (item.amount ?? null) : null,
    outAmount: item.direction === 'out' ? (item.amount ?? null) : null,
    currency: item.currency || props.company.base_currency,
  })),
)

const activityColumns: RegisterColumn<ActivityRow>[] = [
  { key: 'occurred_at', label: 'Date', kind: 'date' },
  { key: 'label', label: 'Entry', kind: 'text' },
  { key: 'inAmount', label: 'In', kind: 'in' },
  { key: 'outAmount', label: 'Out', kind: 'out' },
]

/* Where the page lets you start something rather than only read. */
const startActions = computed(() => {
  const slug = props.company.slug
  return [
    { key: 'invoice', label: 'Write an invoice', href: `/${slug}/invoices/create` },
    { key: 'payment', label: 'Record a payment', href: `/${slug}/payments/create` },
    { key: 'bill', label: 'Enter a bill', href: `/${slug}/bills/create` },
    { key: 'customer', label: 'Add a customer', href: `/${slug}/customers/create` },
  ]
})
</script>

<template>
  <Head :title="pageTitle" />
  <Tabs v-model="activeTab" class="w-full">
    <PageShell
      :title="pageTitle"
      :icon="pageIcon"
      :breadcrumbs="pageBreadcrumbs"
      :badge="{ text: company.is_active ? 'Active' : 'Inactive', variant: company.is_active ? 'default' : 'secondary' }"
      compact
    >
      <template #description>
        <span class="font-mono text-text-tertiary">{{ company.slug }}</span>
        <span class="mx-2 text-text-quaternary">•</span>
        <span class="text-text-secondary">{{ currencySymbol(company.base_currency) }}</span>
      </template>

      <template #actions>
        <TabsList class="bg-surface-sunken">
        <TabsTrigger value="overview" class="gap-2">
          <BarChart3 class="h-4 w-4" />
          Dashboard
        </TabsTrigger>
        <TabsTrigger v-if="canManage" value="settings" class="gap-2">
          <Settings class="h-4 w-4" />
          Settings
        </TabsTrigger>
        <TabsTrigger v-if="canManage" value="users" class="gap-2">
          <Users class="h-4 w-4" />
          Users
        </TabsTrigger>
      </TabsList>
      </template>

      <!-- Overview Tab (Dashboard) -->
      <TabsContent value="overview" class="space-y-6">
        <!-- First thing on the page, because it is the question a partner or manager opens
             the dashboard to answer. Absent entirely for anyone without the permission. -->
        <FinancialPosition
          v-if="financialPosition"
          :position="financialPosition"
          :currency="company.base_currency"
        />


        <div class="ledger-home">
          <!-- Where you stand ------------------------------------------------
               The one conclusion the page exists to state. Everything under it
               is either the working that produced it or the work that changes
               it. -->
          <section class="reckon">
            <h2 class="reckon__title">Where you stand</h2>
            <Derivation
              :lines="standLines"
              total-label="Free to commit"
              :total-amount="freeToCommit"
              :currency="baseCurrency"
              :locale="baseLocale"
            >
              <template #footnote>
                Across {{ accountCount }} {{ accountCount === 1 ? 'account' : 'accounts' }}.
                Money customers still owe you is not counted here — it is not yours until it arrives.
              </template>
            </Derivation>
          </section>

          <!-- What needs you -------------------------------------------------
               A queue, not a scoreboard. Each row is one click from the thing
               that clears it. -->
          <section class="needs">
            <h2 class="needs__title">What needs you</h2>

            <ul v-if="attentionItems.length" class="needs__list">
              <li v-for="item in attentionItems" :key="item.key">
                <button type="button" class="need" @click="router.visit(item.href)">
                  <span class="need__body">
                    <span class="need__label">{{ item.label }}</span>
                    <span class="need__why">{{ item.why }}</span>
                  </span>
                  <MetaChip :tone="item.tone">{{ item.chip }}</MetaChip>
                </button>
              </li>
            </ul>

            <p v-else class="needs__clear">Nothing is waiting on you.</p>
          </section>

          <!-- What's been happening -------------------------------------------
               In and out kept apart so the direction is read, not computed. -->
          <section class="happening">
            <LedgerRegister
              title="What's been happening"
              :data="activityRows"
              :columns="activityColumns"
              key-field="key"
              :clickable="false"
              sprockets
            >
              <template #cell-label="{ row }">
                <span class="entry">{{ row.label }}</span>
              </template>
              <template #cell-inAmount="{ row }">
                <MoneyText
                  v-if="row.inAmount !== null"
                  :amount="row.inAmount"
                  :currency="row.currency"
                  :locale="moneyLocale(row.currency)"
                  :show-currency="false"
                />
                <span v-else class="void" aria-hidden="true">—</span>
              </template>
              <template #cell-outAmount="{ row }">
                <MoneyText
                  v-if="row.outAmount !== null"
                  :amount="row.outAmount"
                  :currency="row.currency"
                  :locale="moneyLocale(row.currency)"
                  :show-currency="false"
                />
                <span v-else class="void" aria-hidden="true">—</span>
              </template>
              <template #empty>Nothing has been recorded yet.</template>
            </LedgerRegister>
          </section>

          <!-- The period ------------------------------------------------------ -->
          <section class="reckon reckon--period">
            <h2 class="reckon__title">{{ dashboard.profit_loss.period }}</h2>
            <Derivation
              :lines="periodLines"
              :total-label="periodResultLabel"
              :total-amount="dashboard.profit_loss.profit"
              :currency="baseCurrency"
              :locale="baseLocale"
            />
          </section>

          <!-- Start something -------------------------------------------------- -->
          <section class="start">
            <h2 class="start__title">Start something</h2>
            <div class="start__strip">
              <button
                v-for="action in startActions"
                :key="action.key"
                type="button"
                class="start__action"
                @click="router.visit(action.href)"
              >
                {{ action.label }}
              </button>
            </div>
          </section>
        </div>
      </TabsContent>

      <!-- Settings Tab -->
      <TabsContent v-if="canManage" value="settings" class="space-y-6">
        <!-- Editable Settings -->
        <Card variant="form" class="border-rule-subtle bg-surface-raised">
          <CardHeader>
            <CardTitle class="text-foreground">Company Settings</CardTitle>
            <CardDescription class="text-text-secondary">
              {{ canManage ? 'Click on the pencil icon to edit a setting' : 'Contact an owner or manager to make changes' }}
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
                <Label class="text-sm font-medium text-text-secondary">Slug</Label>
                <div class="font-mono text-base text-foreground">{{ company.slug }}</div>
                <p class="text-xs text-text-tertiary">Cannot be changed</p>
              </div>

              <!-- Base Currency (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-text-secondary">Base Currency</Label>
                <div class="text-base text-foreground">
                  <span class="font-mono">{{ currencySymbol(company.base_currency) }}</span>
                  <span class="ml-2 text-sm text-text-secondary">({{ company.base_currency }})</span>
                </div>
                <p class="text-xs text-text-tertiary">Cannot be changed after creation</p>
              </div>

              <!-- Country (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-text-secondary">Country</Label>
                <div class="text-base text-foreground">{{ company.country || '—' }}</div>
                <p class="text-xs text-text-tertiary">Cannot be changed</p>
              </div>

              <!-- Industry (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-text-secondary">Industry</Label>
                <div class="text-base text-foreground capitalize">
                  {{ company.industry_name || company.industry || company.industry_code || '—' }}
                </div>
              </div>

              <!-- Created Date (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-text-secondary">Created</Label>
                <div class="flex items-center gap-1.5 text-base text-foreground">
                  <Calendar class="h-3.5 w-3.5 text-text-tertiary" />
                  {{ formatDate(company.created_at) }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Regional Settings -->
        <Card variant="form" class="border-rule-subtle bg-surface-raised">
          <CardHeader>
            <CardTitle class="text-foreground flex items-center gap-2">
              <Globe class="h-4 w-4" />
              Regional Settings
            </CardTitle>
            <CardDescription class="text-text-secondary">
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

      </TabsContent>

      <!-- Users Tab -->
      <TabsContent v-if="canManage" value="users" class="space-y-6">
        <LedgerRegister
          :data="users"
          :columns="tableColumns"
          title="Team Members"
          :description="`${users.length} ${users.length === 1 ? 'member' : 'members'} in this company`"
          key-field="id"
          hoverable
        >
          <template #header>
            <Button v-if="canManage" size="sm" @click="createUserDialogOpen = true">
              <UserPlus class="mr-2 h-4 w-4" />
              Add User
            </Button>
          </template>

          <template #cell-name="{ row }">
            <div class="flex flex-col">
              <span class="font-medium text-foreground">{{ row.name || 'Unknown' }}</span>
              <div class="flex items-center gap-1 text-text-secondary">
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
            <div v-if="row.joined_at" class="flex items-center gap-1 text-text-secondary">
              <Calendar class="h-3 w-3 text-text-tertiary" />
              <span>{{ formatDate(row.joined_at) }}</span>
            </div>
            <span v-else class="text-text-tertiary">—</span>
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
                  <DropdownMenuItem @click="openRemoveDialog(row)" class="text-status-critical focus:text-status-critical">
                    <Trash2 class="mr-2 h-4 w-4" />
                    Remove User
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </template>
        </LedgerRegister>
      </TabsContent>

    <!-- Create User Dialog -->
    <Dialog v-model:open="createUserDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-foreground">Add User</DialogTitle>
          <DialogDescription class="text-text-secondary">
            Create a login for {{ company.name }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label for="company-user-name" class="text-text-secondary">Full name</Label>
            <Input id="company-user-name" v-model="createUserForm.name" class="border-rule-default" />
            <p v-if="createUserForm.errors.name" class="text-xs text-status-critical">{{ createUserForm.errors.name }}</p>
          </div>
          <div class="space-y-2">
            <Label for="company-user-email" class="text-text-secondary">Email</Label>
            <Input
              id="company-user-email"
              v-model="createUserForm.email"
              type="email"
              placeholder="user@example.com"
              class="border-rule-default"
            />
            <p v-if="createUserForm.errors.email" class="text-xs text-status-critical">
              {{ createUserForm.errors.email }}
            </p>
          </div>
          <div class="space-y-2">
            <Label class="text-text-secondary">Role</Label>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="w-full justify-between border-rule-default">
                  <span class="capitalize">{{ createUserForm.role }}</span>
                  <span class="ml-2">▼</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-full">
                <DropdownMenuItem
                  v-for="role in availableRoles"
                  :key="role"
                  @click="createUserForm.role = role"
                  class="capitalize"
                >
                  {{ role }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
          <div class="space-y-2">
            <Label for="company-user-password" class="text-text-secondary">Password</Label>
            <Input id="company-user-password" v-model="createUserForm.password" type="password" autocomplete="new-password" class="border-rule-default" />
            <p v-if="createUserForm.errors.password" class="text-xs text-status-critical">{{ createUserForm.errors.password }}</p>
          </div>
          <div class="space-y-2">
            <Label for="company-user-password-confirmation" class="text-text-secondary">Confirm password</Label>
            <Input id="company-user-password-confirmation" v-model="createUserForm.password_confirmation" type="password" autocomplete="new-password" class="border-rule-default" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="createUserDialogOpen = false" :disabled="createUserForm.processing">
            Cancel
          </Button>
          <Button @click="handleCreateUser" :disabled="createUserForm.processing">
            <span v-if="createUserForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
            Create User
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Change Role Dialog -->
    <Dialog v-model:open="roleDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-foreground">Change User Role</DialogTitle>
          <DialogDescription class="text-text-secondary">
            Update the role for {{ selectedUser?.name || selectedUser?.email }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label class="text-text-secondary">Role</Label>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="w-full justify-between border-rule-default">
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

<style scoped>
/* The generic dashboard, set as one continuous sheet rather than a grid of
   cards. Cards break a page into equally-loud boxes; rules and space let one
   thing be the conclusion and the rest be support. */
.ledger-home {
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.reckon__title,
.needs__title,
.start__title {
    font-family: var(--display-family);
    font-size: 19px;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--text-primary);
    padding-bottom: 8px;
    border-bottom: var(--rule-w-base, 1.5px) solid var(--rule-emphasis);
}

/* The period reckoning is the same device at a quieter volume — it reports a
   month, not a standing. */
.reckon--period :deep(.total__what),
.reckon--period :deep(.money--conclusion) {
    font-size: 26px;
}

.needs__list {
    display: flex;
    flex-direction: column;
    margin-top: 4px;
}

.need {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    width: 100%;
    padding: 12px 8px 12px 0;
    text-align: left;
    background: none;
    border: 0;
    border-bottom: var(--rule-w-hair, 1px) solid var(--rule-default);
    cursor: pointer;
}

.needs__list li:last-child .need {
    border-bottom: 0;
}

/* Hover is an outline, not a fill — the same move the register makes, so a
   row behaves the same whichever part of the page it is in. */
.need:hover {
    outline: var(--rule-w-base, 1.5px) solid var(--rule-emphasis);
    outline-offset: calc(var(--rule-w-base, 1.5px) * -1);
}

.need__body {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.need__label {
    font-size: 15px;
    font-weight: 500;
    color: var(--text-primary);
}

.need__why {
    font-size: 13px;
    color: var(--text-secondary);
}

.needs__clear {
    margin-top: 16px;
    font-size: 15px;
    color: var(--text-secondary);
}

/* The register carries its own card padding for the pages that give it a
   border. Here it sits bare on the sheet, so its heading has to share the left
   edge with every other section heading or the page reads as two documents. */
.happening :deep(.reghead) {
    padding-left: 0;
    padding-right: 0;
    padding-top: 0;
}

.happening :deep(.reghead__title) {
    font-size: 19px;
}

.entry {
    color: var(--text-primary);
}

/* An empty column reads as "nothing moved that way", which is information.
   Blank would read as a rendering fault. */
.void {
    font-family: var(--mono-family);
    color: var(--text-tertiary);
}

.start__strip {
    display: flex;
    flex-wrap: wrap;
    gap: 0;
    margin-top: 16px;
    border: var(--rule-w-hair, 1px) solid var(--rule-default);
}

.start__action {
    flex: 1 1 180px;
    padding: 14px 16px;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
    background: var(--surface-raised);
    border: 0;
    border-right: var(--rule-w-hair, 1px) solid var(--rule-default);
    cursor: pointer;
}

.start__action:last-child {
    border-right: 0;
}

.start__action:hover {
    background: var(--surface-band);
}

@media (max-width: 640px) {
    .ledger-home {
        gap: 32px;
    }

    .start__action {
        flex-basis: 100%;
        border-right: 0;
        border-bottom: var(--rule-w-hair, 1px) solid var(--rule-default);
    }
}
</style>
