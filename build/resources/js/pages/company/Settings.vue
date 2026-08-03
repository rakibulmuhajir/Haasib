<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import CompanyCurrencies from '@/components/company/CompanyCurrencies.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { formatDateTime } from '@/lib/datetime'
import {
  Building2,
  CalendarRange,
  Coins,
  ExternalLink,
  KeyRound,
  PackageCheck,
  Settings2,
  UserPlus,
  Users,
} from 'lucide-vue-next'

interface Company {
  id: string
  name: string
  slug: string
  industry?: string
  industry_code?: string
  industry_name?: string | null
  country?: string
  base_currency: string
  logo_url?: string | null
  language?: string | null
  locale?: string | null
  is_active: boolean
  created_at: string
  current_user_role: string | null
  can_manage_company: boolean
  can_manage_users: boolean
  settings?: {
    fiscal_year_start_month?: number
    auto_create_fiscal_year?: boolean
    default_period_type?: string
    modules?: Record<string, boolean>
    contact_email?: string | null
    contact_phone?: string | null
    website?: string | null
  }
}

interface CompanyUser {
  id: string
  name: string
  email: string
  role: string
  joined_at: string | null
}

interface PendingInvitation {
  id: string
  email: string
  role: string
  expires_at: string
}

const page = usePage()
const props = page.props as any
const company = ref<Company>(props.company)
const companyCurrencies = props.companyCurrencies || []
const availableCurrencies = props.availableCurrencies || []
const users = (props.users || []) as CompanyUser[]
const pendingInvitations = (props.pendingInvitations || []) as PendingInvitation[]
const logoPreview = ref(company.value.logo_url || '')

const formatDate = (value: string | null) => value ? formatDateTime(value, { mode: 'date' }) : '—'
const roleLabel = (role: string | null) => ({
  owner: 'Owner',
  manager: 'Manager',
  accountant: 'Accountant',
  operations: 'Operations Clerk',
  agent: 'Agent',
}[String(role)] || role || 'System administrator')

const roleDescription = (role: string) => ({
  owner: 'Full company access, including ownership and team controls.',
  manager: 'Runs the business and manages team members, but cannot remove the Owner.',
  accountant: 'Handles accounts, vouchers, payments, expenses, reports, and payroll.',
  operations: 'Creates groups and vouchers without seeing prices, costs, or accounting.',
  agent: 'Works only with their own groups, vouchers, payments, and reports.',
}[role] || 'Company access')

const generalForm = useForm({
  name: company.value.name,
  logo: null as File | null,
  contact_email: company.value.settings?.contact_email || '',
  contact_phone: company.value.settings?.contact_phone || '',
  website: company.value.settings?.website || '',
  language: company.value.language || 'en',
  locale: company.value.locale || 'en_US',
})

const fiscalYearForm = useForm({
  fiscal_year_start_month: company.value.settings?.fiscal_year_start_month ?? 1,
  auto_create_fiscal_year: company.value.settings?.auto_create_fiscal_year ?? true,
  default_period_type: company.value.settings?.default_period_type ?? 'monthly',
})

const moduleSettingsForm = useForm({
  inventory: company.value.settings?.modules?.inventory !== false,
  payroll: company.value.settings?.modules?.payroll !== false,
})

const months = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
].map((label, index) => ({ value: index + 1, label }))

const periodTypes = [
  { value: 'monthly', label: 'Monthly' },
  { value: 'quarterly', label: 'Quarterly' },
  { value: 'yearly', label: 'Yearly' },
]

const selectLogo = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0] || null
  generalForm.logo = file
  if (file) logoPreview.value = URL.createObjectURL(file)
}

const saveGeneralSettings = () => generalForm
  .transform((data) => ({ ...data, _method: 'patch' }))
  .post(`/${company.value.slug}/settings`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => { generalForm.logo = null },
  })

const saveModuleSettings = () => moduleSettingsForm.patch(
  `/${company.value.slug}/settings/modules`,
  { preserveScroll: true },
)

const saveFiscalYearSettings = () => fiscalYearForm.patch(
  `/${company.value.slug}/settings`,
  { preserveScroll: true },
)
</script>

<template>
  <Head title="Settings" />

  <PageShell title="Settings">
    <div class="mx-auto w-full max-w-6xl space-y-6 pb-12">
      <section class="flex flex-col gap-5 border-b border-border pb-6 md:flex-row md:items-end md:justify-between">
        <div class="space-y-2">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ company.name }}</h1>
            <Badge :variant="company.is_active ? 'default' : 'secondary'">
              {{ company.is_active ? 'Active' : 'Inactive' }}
            </Badge>
            <Badge variant="outline">{{ roleLabel(company.current_user_role) }}</Badge>
          </div>
          <p class="max-w-2xl text-sm leading-6 text-muted-foreground">
            Company details, team access, currencies, modules, and accounting defaults.
          </p>
          <p class="text-xs text-muted-foreground">
            {{ company.industry_name || company.industry || company.industry_code || 'General business' }}
            · {{ company.country || 'Country not set' }}
            · {{ company.base_currency }} base currency
            · Created {{ formatDate(company.created_at) }}
          </p>
        </div>

        <div class="flex flex-wrap gap-2">
          <Button variant="outline" size="sm" @click="router.get(`/${company.slug}/onboarding`)">
            Setup guide
            <ExternalLink class="ml-2 h-3.5 w-3.5" />
          </Button>
          <Button variant="outline" size="sm" @click="router.get(`/${company.slug}/tax/settings`)">
            Tax settings
            <ExternalLink class="ml-2 h-3.5 w-3.5" />
          </Button>
        </div>
      </section>

      <Tabs default-value="general" class="space-y-6">
        <TabsList class="grid h-auto w-full grid-cols-2 gap-1 bg-muted/60 p-1 md:grid-cols-5">
          <TabsTrigger value="general" class="gap-2 py-2.5">
            <Building2 class="h-4 w-4" /> General
          </TabsTrigger>
          <TabsTrigger value="users" class="gap-2 py-2.5">
            <Users class="h-4 w-4" /> Users
          </TabsTrigger>
          <TabsTrigger value="currencies" class="gap-2 py-2.5">
            <Coins class="h-4 w-4" /> Currencies
          </TabsTrigger>
          <TabsTrigger value="modules" class="gap-2 py-2.5">
            <PackageCheck class="h-4 w-4" /> Modules
          </TabsTrigger>
          <TabsTrigger value="accounting" class="col-span-2 gap-2 py-2.5 md:col-span-1">
            <CalendarRange class="h-4 w-4" /> Accounting
          </TabsTrigger>
        </TabsList>

        <TabsContent value="general">
          <Card>
            <CardHeader>
              <CardTitle>Company details</CardTitle>
              <CardDescription>Identity and contact information shown across Haasib.</CardDescription>
            </CardHeader>
            <CardContent>
              <form class="space-y-6" @submit.prevent="saveGeneralSettings">
                <div class="grid gap-5 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label for="company-name">Company name</Label>
                    <Input id="company-name" v-model="generalForm.name" :disabled="!company.can_manage_company" />
                    <p v-if="generalForm.errors.name" class="text-xs text-destructive">{{ generalForm.errors.name }}</p>
                  </div>
                  <div class="space-y-2">
                    <Label for="base-currency">Base currency</Label>
                    <Input id="base-currency" :model-value="company.base_currency" disabled />
                    <p class="text-xs text-muted-foreground">Fixed after the company is created.</p>
                  </div>
                  <div class="space-y-2">
                    <Label for="contact-email">Contact email</Label>
                    <Input id="contact-email" v-model="generalForm.contact_email" type="email" :disabled="!company.can_manage_company" />
                    <p v-if="generalForm.errors.contact_email" class="text-xs text-destructive">{{ generalForm.errors.contact_email }}</p>
                  </div>
                  <div class="space-y-2">
                    <Label for="contact-phone">Contact phone</Label>
                    <Input id="contact-phone" v-model="generalForm.contact_phone" :disabled="!company.can_manage_company" />
                    <p v-if="generalForm.errors.contact_phone" class="text-xs text-destructive">{{ generalForm.errors.contact_phone }}</p>
                  </div>
                  <div class="space-y-2 md:col-span-2">
                    <Label for="website">Website</Label>
                    <Input id="website" v-model="generalForm.website" type="url" :disabled="!company.can_manage_company" />
                    <p v-if="generalForm.errors.website" class="text-xs text-destructive">{{ generalForm.errors.website }}</p>
                  </div>
                  <div class="space-y-2">
                    <Label for="language">Language</Label>
                    <Input id="language" v-model="generalForm.language" :disabled="!company.can_manage_company" />
                  </div>
                  <div class="space-y-2">
                    <Label for="locale">Locale</Label>
                    <Input id="locale" v-model="generalForm.locale" :disabled="!company.can_manage_company" />
                  </div>
                  <div class="space-y-2 md:col-span-2">
                    <Label for="company-logo">Company logo</Label>
                    <div class="flex items-center gap-4 rounded-lg bg-muted/40 p-3">
                      <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-background ring-1 ring-border">
                        <img v-if="logoPreview" :src="logoPreview" :alt="`${company.name} logo preview`" class="h-full w-full object-contain" />
                        <Building2 v-else class="h-6 w-6 text-muted-foreground" />
                      </div>
                      <Input id="company-logo" type="file" accept="image/png,image/jpeg,image/webp" :disabled="!company.can_manage_company" @change="selectLogo" />
                    </div>
                    <p v-if="generalForm.errors.logo" class="text-xs text-destructive">{{ generalForm.errors.logo }}</p>
                  </div>
                </div>
                <div v-if="company.can_manage_company" class="flex justify-end border-t border-border pt-5">
                  <Button type="submit" :disabled="generalForm.processing">
                    {{ generalForm.processing ? 'Saving…' : 'Save company details' }}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="users" class="space-y-5">
          <Card>
            <CardHeader class="flex flex-row items-start justify-between gap-4">
              <div class="space-y-1.5">
                <CardTitle>Users & permissions</CardTitle>
                <CardDescription>{{ users.length }} active {{ users.length === 1 ? 'user' : 'users' }} · {{ pendingInvitations.length }} pending</CardDescription>
              </div>
              <Button v-if="company.can_manage_users" @click="router.get(`/${company.slug}/users`)">
                <UserPlus class="mr-2 h-4 w-4" /> Invite or manage
              </Button>
            </CardHeader>
            <CardContent class="space-y-6">
              <div class="overflow-hidden rounded-lg border border-border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>User</TableHead>
                      <TableHead>Role</TableHead>
                      <TableHead class="hidden md:table-cell">Access</TableHead>
                      <TableHead class="hidden text-right sm:table-cell">Joined</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-for="user in users" :key="user.id">
                      <TableCell>
                        <div class="font-medium">{{ user.name }}</div>
                        <div class="text-xs text-muted-foreground">{{ user.email }}</div>
                      </TableCell>
                      <TableCell><Badge variant="outline">{{ roleLabel(user.role) }}</Badge></TableCell>
                      <TableCell class="hidden max-w-md text-sm text-muted-foreground md:table-cell">{{ roleDescription(user.role) }}</TableCell>
                      <TableCell class="hidden text-right text-sm text-muted-foreground sm:table-cell">{{ formatDate(user.joined_at) }}</TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </div>

              <div v-if="pendingInvitations.length" class="space-y-3">
                <h3 class="text-sm font-medium">Pending invitations</h3>
                <div class="grid gap-2 md:grid-cols-2">
                  <div v-for="invitation in pendingInvitations" :key="invitation.id" class="flex items-center justify-between rounded-lg bg-muted/40 px-4 py-3">
                    <div>
                      <p class="text-sm font-medium">{{ invitation.email }}</p>
                      <p class="text-xs text-muted-foreground">Expires {{ formatDate(invitation.expires_at) }}</p>
                    </div>
                    <Badge variant="secondary">{{ roleLabel(invitation.role) }}</Badge>
                  </div>
                </div>
              </div>

              <div class="rounded-lg bg-muted/40 p-4">
                <div class="flex gap-3">
                  <KeyRound class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground" />
                  <div>
                    <p class="text-sm font-medium">Role rules are enforced server-side</p>
                    <p class="mt-1 text-sm leading-6 text-muted-foreground">Owners and Managers can manage the team. Managers cannot change or remove an Owner. Financial data stays hidden from Operations Clerks and Agents.</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="currencies">
          <Card>
            <CardHeader>
              <CardTitle>Currencies</CardTitle>
              <CardDescription>Manual rates use 1 secondary currency = X {{ company.base_currency }}.</CardDescription>
            </CardHeader>
            <CardContent>
              <CompanyCurrencies :company="company" :enabled="companyCurrencies" :available="availableCurrencies" />
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="modules">
          <Card>
            <CardHeader>
              <CardTitle>Optional modules</CardTitle>
              <CardDescription>Turn operational areas on or off for this company.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-5">
              <div class="divide-y divide-border rounded-lg border border-border">
                <div class="flex items-center justify-between gap-5 p-5">
                  <div>
                    <p class="font-medium">Inventory</p>
                    <p class="mt-1 text-sm text-muted-foreground">Items, warehouses, stock levels, and stock movements.</p>
                  </div>
                  <Switch v-model:checked="moduleSettingsForm.inventory" :disabled="!company.can_manage_company || moduleSettingsForm.processing" />
                </div>
                <div class="flex items-center justify-between gap-5 p-5">
                  <div>
                    <p class="font-medium">Payroll</p>
                    <p class="mt-1 text-sm text-muted-foreground">Employees, payroll periods, payslips, payments, and advances.</p>
                  </div>
                  <Switch v-model:checked="moduleSettingsForm.payroll" :disabled="!company.can_manage_company || moduleSettingsForm.processing" />
                </div>
              </div>
              <p v-if="moduleSettingsForm.errors.inventory || moduleSettingsForm.errors.payroll" class="text-sm text-destructive">
                {{ moduleSettingsForm.errors.inventory || moduleSettingsForm.errors.payroll }}
              </p>
              <div v-if="company.can_manage_company" class="flex justify-end">
                <Button :disabled="moduleSettingsForm.processing" @click="saveModuleSettings">
                  <Settings2 class="mr-2 h-4 w-4" />
                  {{ moduleSettingsForm.processing ? 'Saving…' : 'Save module settings' }}
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="accounting">
          <Card>
            <CardHeader>
              <CardTitle>Fiscal year & periods</CardTitle>
              <CardDescription>Choose how transactions are grouped into financial reporting periods.</CardDescription>
            </CardHeader>
            <CardContent>
              <form class="space-y-6" @submit.prevent="saveFiscalYearSettings">
                <div class="grid gap-5 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label for="fiscal-month">Fiscal year starts</Label>
                    <Select v-model="fiscalYearForm.fiscal_year_start_month" :disabled="!company.can_manage_company">
                      <SelectTrigger id="fiscal-month"><SelectValue placeholder="Select month" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="space-y-2">
                    <Label for="period-type">Reporting periods</Label>
                    <Select v-model="fiscalYearForm.default_period_type" :disabled="!company.can_manage_company">
                      <SelectTrigger id="period-type"><SelectValue placeholder="Select period type" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="period in periodTypes" :key="period.value" :value="period.value">{{ period.label }}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div class="flex items-center justify-between gap-5 rounded-lg bg-muted/40 p-4">
                  <div>
                    <p class="text-sm font-medium">Create fiscal years automatically</p>
                    <p class="mt-1 text-sm text-muted-foreground">Create the required year and periods when a transaction is posted.</p>
                  </div>
                  <Switch v-model:checked="fiscalYearForm.auto_create_fiscal_year" :disabled="!company.can_manage_company" />
                </div>

                <div v-if="company.can_manage_company" class="flex justify-end border-t border-border pt-5">
                  <Button type="submit" :disabled="fiscalYearForm.processing">
                    {{ fiscalYearForm.processing ? 'Saving…' : 'Save accounting settings' }}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  </PageShell>
</template>
