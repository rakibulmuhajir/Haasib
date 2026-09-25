<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import InputError from '@/components/InputError.vue'
import EntitySearch from '@/components/forms/EntitySearch.vue'
import { useBaseCurrency } from '@/composables/useBaseCurrency'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogClose } from '@/components/ui/dialog'
import { ChevronDown, Landmark, Lock, Plus, Trash2 } from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

interface Opt { id: string; name: string; code?: string }
interface Opening {
  as_of_date: string | null
  locked_at: string | null
  locked_by: string | null
  earliest_transaction_date: string | null
  rows: {
    cash: { amount: number }
    banks: { account_id: string; account_name: string; amount: number }[]
    credit_customers: { customer_id: string; customer_name: string; amount: number }[]
    employees: { employee_id: string; employee_name: string; amount: number }[]
    salaries_owed: { employee_id: string; employee_name: string; amount: number; payslip_id?: string; paid?: boolean }[]
    amanat: { customer_id: string; customer_name: string; amount: number }[]
    suppliers: { vendor_id: string; vendor_name: string; amount: number }[]
    partners: { partner_id: string; partner_name: string; amount: number }[]
  }
  totals: { assets: number; liabilities: number; equity: number }
  options: { bank_accounts: Opt[]; customers: Opt[]; vendors: Opt[]; employees: Opt[]; partners: Opt[] }
}

const props = defineProps<{
  company: { id: string; name: string; slug: string }
  opening: Opening
  canManage: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Opening balances', href: `/${props.company.slug}/accounting/opening-balances` },
]

const locked = computed(() => !!props.opening.locked_at)
const editable = computed(() => props.canManage && !locked.value)

const baseCurrency = useBaseCurrency()
const currency = computed(() => baseCurrency.value ?? 'PKR')

/**
 * EntitySearch is given only an id via v-model; left alone it fetches
 * `/{company}/customers/{id}` (an Inertia page response, not JSON) to resolve a display
 * name, so a saved row shows blank until the user searches again. This map lets every
 * picker resolve its saved id to a name up front, from data already in `opening`.
 */
const entityNames = computed<Record<string, string>>(() => {
  const names: Record<string, string> = {}
  for (const c of props.opening.options.customers) names[c.id] = c.name
  for (const v of props.opening.options.vendors) names[v.id] = v.name
  for (const row of props.opening.rows.credit_customers) if (row.customer_id) names[row.customer_id] = row.customer_name
  for (const row of props.opening.rows.amanat) if (row.customer_id) names[row.customer_id] = row.customer_name
  for (const row of props.opening.rows.suppliers) if (row.vendor_id) names[row.vendor_id] = row.vendor_name
  return names
})

function fieldsFromOpening(o: Opening) {
  return {
    as_of_date: o.as_of_date ?? '',
    cash: { amount: o.rows.cash.amount ?? 0 },
    banks: o.rows.banks.map(r => ({ account_id: r.account_id, amount: r.amount })),
    credit_customers: o.rows.credit_customers.map(r => ({ customer_id: r.customer_id, amount: r.amount })),
    employees: o.rows.employees.map(r => ({ employee_id: r.employee_id, amount: r.amount })),
    salaries_owed: o.rows.salaries_owed.map(r => ({ employee_id: r.employee_id, amount: r.amount })),
    amanat: o.rows.amanat.map(r => ({ customer_id: r.customer_id, amount: r.amount })),
    suppliers: o.rows.suppliers.map(r => ({ vendor_id: r.vendor_id, amount: r.amount })),
    partners: o.rows.partners.map(r => ({ partner_id: r.partner_id, amount: r.amount })),
  }
}

// `loaded` travels with every save: the rows this page started from, so the server can keep
// lines changed elsewhere (a bank account, Quick Add) that this page never touched.
const form = useForm({ ...fieldsFromOpening(props.opening), loaded: fieldsFromOpening(props.opening) })

/**
 * Re-seeds the form from fresh `opening` props — needed because `SaveAction`
 * drops zero-amount bank rows and rounds amounts, and `LockAction`/`SaveAction`
 * redirects reload `opening` from what the server actually persisted. Without
 * this the page re-renders with new props but `useForm`'s local state keeps
 * the stale, pre-save rows.
 */
function seedFromOpening(o: Opening) {
  const fields = fieldsFromOpening(o)
  form.as_of_date = fields.as_of_date
  // A separate copy: the form's rows are edited in place, and `loaded` must stay as loaded.
  form.loaded = fieldsFromOpening(o)
  form.cash = fields.cash
  form.banks = fields.banks
  form.credit_customers = fields.credit_customers
  form.employees = fields.employees
  form.salaries_owed = fields.salaries_owed
  form.amanat = fields.amanat
  form.suppliers = fields.suppliers
  form.partners = fields.partners
}

/** Only the parts of `opening` that feed the form — options (dropdown lists)
 * can change (e.g. a customer created elsewhere) without the form needing to
 * reseed, so they're deliberately excluded from this comparison. */
const openingFormSnapshot = (o: Opening) => JSON.stringify({ as_of_date: o.as_of_date, locked_at: o.locked_at, rows: o.rows })

watch(
  () => props.opening,
  (next, prev) => {
    if (prev && openingFormSnapshot(next) === openingFormSnapshot(prev)) return
    seedFromOpening(next)
  },
  { deep: true },
)

const sum = (rows: { amount: number }[]) => rows.reduce((s, r) => s + Number(r.amount || 0), 0)
const assets = computed(() => Number(form.cash.amount || 0) + sum(form.banks) + sum(form.credit_customers) + sum(form.employees))
const liabilities = computed(() => sum(form.amanat) + sum(form.suppliers) + sum(form.partners) + sum(form.salaries_owed))
const equity = computed(() => assets.value - liabilities.value)
const rowCount = computed(() => (Number(form.cash.amount) > 0 ? 1 : 0) + form.banks.length + form.credit_customers.length + form.employees.length + form.salaries_owed.length + form.amanat.length + form.suppliers.length + form.partners.length)

const dateGuardMessage = computed(() => {
  const earliest = props.opening.earliest_transaction_date
  if (!earliest || !form.as_of_date) return null
  return form.as_of_date >= earliest ? `Must be before the first posted transaction (${earliest}).` : null
})
const canSave = computed(() => editable.value && rowCount.value > 0 && !!form.as_of_date && !dateGuardMessage.value && !form.processing)
const canLock = computed(() => props.canManage && !locked.value && !!props.opening.as_of_date)

const open = reactive({ cash: true, credit: true, employees: true, salariesOwed: true, amanat: true, suppliers: true, partners: false })

const err = (key: string) => (form.errors as Record<string, string>)[key]

function submit() {
  form.post(`/${props.company.slug}/accounting/opening-balances`, {
    preserveScroll: true,
    onSuccess: () => toast.success('Opening balances saved'),
    onError: (errors) => {
      const first = Object.values(errors)[0]
      if (first && !Object.keys(errors).some(k => k.includes('.'))) toast.error(first as string)
    },
  })
}

const showLockDialog = ref(false)
const locking = ref(false)

function lock() {
  locking.value = true
  router.post(`/${props.company.slug}/accounting/opening-balances/lock`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      showLockDialog.value = false
      toast.success('Opening balances locked')
    },
    onError: (errors) => toast.error((Object.values(errors)[0] as string) || 'Could not lock'),
    onFinish: () => {
      locking.value = false
    },
  })
}
</script>

<template>
  <Head title="Opening Balances" />
  <PageShell title="Opening balances" :breadcrumbs="breadcrumbs" :icon="Landmark">
    <div class="space-y-6 pb-32">
      <Card>
        <CardHeader>
          <CardTitle>Opening balances</CardTitle>
          <CardDescription>
            What the business held and owed the day before entries start in Haasib. Every line is posted against Opening Balance Equity (3080).
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div v-if="locked" class="rounded-md border border-status-attention/40 bg-status-attention/10 p-3 text-sm">
            Locked on {{ opening.locked_at?.slice(0, 10) }}<span v-if="opening.locked_by"> by {{ opening.locked_by }}</span>. Balances are read-only.
          </div>
          <div class="grid gap-2 md:max-w-xs">
            <Label for="as_of_date">As of date</Label>
            <Input id="as_of_date" v-model="form.as_of_date" type="date" :disabled="!editable" />
            <InputError :message="err('as_of_date') || dateGuardMessage || undefined" />
          </div>
        </CardContent>
      </Card>

      <!-- Cash & banks -->
      <Card>
        <Collapsible v-model:open="open.cash">
          <CardHeader>
            <CollapsibleTrigger class="flex w-full items-center justify-between text-left">
              <div>
                <CardTitle>Cash &amp; banks</CardTitle>
                <CardDescription>Drawer cash and each bank or card settlement account</CardDescription>
              </div>
              <span class="flex items-center gap-3 text-sm">
                <MoneyText :amount="Number(form.cash.amount || 0) + sum(form.banks)" :currency="currency" :fraction-digits="0" />
                <ChevronDown class="h-4 w-4" />
              </span>
            </CollapsibleTrigger>
          </CardHeader>
          <CollapsibleContent>
            <CardContent class="space-y-3">
              <div class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label for="cash-amount">Cash on hand</Label>
                  <p class="text-sm text-muted-foreground">Account 1050</p>
                </div>
                <Input id="cash-amount" v-model.number="form.cash.amount" type="number" min="0" step="1" :disabled="!editable" />
                <span />
              </div>
              <InputError :message="err('cash.amount')" />
              <div v-for="(row, i) in form.banks" :key="'bank-' + i" class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label :for="`banks-${i}-account_id`" :class="i === 0 ? undefined : 'sr-only'">Bank account</Label>
                  <Select v-model="row.account_id" :disabled="!editable">
                    <SelectTrigger :id="`banks-${i}-account_id`"><SelectValue placeholder="Choose bank account" /></SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="a in opening.options.bank_accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</SelectItem>
                    </SelectContent>
                  </Select>
                  <InputError :message="err(`banks.${i}.account_id`)" />
                </div>
                <div class="grid gap-1">
                  <Label :for="`banks-${i}-amount`" :class="i === 0 ? undefined : 'sr-only'">Balance</Label>
                  <Input :id="`banks-${i}-amount`" v-model.number="row.amount" type="number" min="0" step="1" :disabled="!editable" />
                  <InputError :message="err(`banks.${i}.amount`)" />
                </div>
                <Button v-if="editable" variant="ghost" size="icon" @click="form.banks.splice(i, 1)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button v-if="editable" variant="outline" size="sm" @click="form.banks.push({ account_id: '', amount: 0 })"><Plus class="mr-2 h-4 w-4" />Add bank account</Button>
            </CardContent>
          </CollapsibleContent>
        </Collapsible>
      </Card>

      <!-- Credit customers -->
      <Card>
        <Collapsible v-model:open="open.credit">
          <CardHeader>
            <CollapsibleTrigger class="flex w-full items-center justify-between text-left">
              <div>
                <CardTitle>Credit customers</CardTitle>
                <CardDescription>What each customer owes the business (udhaar). Becomes an opening invoice.</CardDescription>
                <p class="text-xs text-muted-foreground">Create missing customers/suppliers on their own pages first.</p>
              </div>
              <span class="flex items-center gap-3 text-sm">
                <MoneyText :amount="sum(form.credit_customers)" :currency="currency" :fraction-digits="0" />
                <ChevronDown class="h-4 w-4" />
              </span>
            </CollapsibleTrigger>
          </CardHeader>
          <CollapsibleContent>
            <CardContent class="space-y-3">
              <InputError :message="err('credit_customers')" />
              <div v-for="(row, i) in form.credit_customers" :key="'cc-' + i" class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label :id="`credit_customers-${i}-customer_id-label`" :class="i === 0 ? undefined : 'sr-only'">Customer</Label>
                  <EntitySearch
                    v-model="row.customer_id"
                    entity-type="customer"
                    :disabled="!editable"
                    :allow-quick-add="false"
                    :initial-entity="row.customer_id ? { id: row.customer_id, name: entityNames[row.customer_id] ?? '' } : null"
                    :aria-labelledby="`credit_customers-${i}-customer_id-label`"
                  />
                  <InputError :message="err(`credit_customers.${i}.customer_id`)" />
                </div>
                <div class="grid gap-1">
                  <Label :for="`credit_customers-${i}-amount`" :class="i === 0 ? undefined : 'sr-only'">Owes</Label>
                  <Input :id="`credit_customers-${i}-amount`" v-model.number="row.amount" type="number" min="0" step="1" :disabled="!editable" />
                  <InputError :message="err(`credit_customers.${i}.amount`)" />
                </div>
                <Button v-if="editable" variant="ghost" size="icon" @click="form.credit_customers.splice(i, 1)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button v-if="editable" variant="outline" size="sm" @click="form.credit_customers.push({ customer_id: '', amount: 0 })"><Plus class="mr-2 h-4 w-4" />Add customer</Button>
            </CardContent>
          </CollapsibleContent>
        </Collapsible>
      </Card>

      <!-- Employee advances -->
      <Card>
        <Collapsible v-model:open="open.employees">
          <CardHeader>
            <CollapsibleTrigger class="flex w-full items-center justify-between text-left">
              <div>
                <CardTitle>Employee advances</CardTitle>
                <CardDescription>Salary advances not yet recovered</CardDescription>
              </div>
              <span class="flex items-center gap-3 text-sm">
                <MoneyText :amount="sum(form.employees)" :currency="currency" :fraction-digits="0" />
                <ChevronDown class="h-4 w-4" />
              </span>
            </CollapsibleTrigger>
          </CardHeader>
          <CollapsibleContent>
            <CardContent class="space-y-3">
              <InputError :message="err('employees')" />
              <div v-for="(row, i) in form.employees" :key="'emp-' + i" class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label :for="`employees-${i}-employee_id`" :class="i === 0 ? undefined : 'sr-only'">Employee</Label>
                  <Select v-model="row.employee_id" :disabled="!editable">
                    <SelectTrigger :id="`employees-${i}-employee_id`"><SelectValue placeholder="Choose employee" /></SelectTrigger>
                    <SelectContent><SelectItem v-for="e in opening.options.employees" :key="e.id" :value="e.id">{{ e.name }}</SelectItem></SelectContent>
                  </Select>
                  <InputError :message="err(`employees.${i}.employee_id`)" />
                </div>
                <div class="grid gap-1">
                  <Label :for="`employees-${i}-amount`" :class="i === 0 ? undefined : 'sr-only'">Outstanding</Label>
                  <Input :id="`employees-${i}-amount`" v-model.number="row.amount" type="number" min="0" step="1" :disabled="!editable" />
                  <InputError :message="err(`employees.${i}.amount`)" />
                </div>
                <Button v-if="editable" variant="ghost" size="icon" @click="form.employees.splice(i, 1)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button v-if="editable" variant="outline" size="sm" @click="form.employees.push({ employee_id: '', amount: 0 })"><Plus class="mr-2 h-4 w-4" />Add employee</Button>
            </CardContent>
          </CollapsibleContent>
        </Collapsible>
      </Card>

      <!-- Salaries owed -->
      <Card>
        <Collapsible v-model:open="open.salariesOwed">
          <CardHeader>
            <CollapsibleTrigger class="flex w-full items-center justify-between text-left">
              <div>
                <CardTitle>Salaries owed</CardTitle>
                <CardDescription>
                  Salaries earned before the opening date and not yet paid. Each becomes a payslip due on the opening date.
                </CardDescription>
              </div>
              <span class="flex items-center gap-3 text-sm">
                <MoneyText :amount="sum(form.salaries_owed)" :currency="currency" :fraction-digits="0" />
                <ChevronDown class="h-4 w-4" />
              </span>
            </CollapsibleTrigger>
          </CardHeader>
          <CollapsibleContent>
            <CardContent class="space-y-3">
              <InputError :message="err('salaries_owed')" />
              <div v-for="(row, i) in form.salaries_owed" :key="'sal-' + i" class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label :for="`salaries_owed-${i}-employee_id`" :class="i === 0 ? undefined : 'sr-only'">Employee</Label>
                  <Select v-model="row.employee_id" :disabled="!editable">
                    <SelectTrigger :id="`salaries_owed-${i}-employee_id`"><SelectValue placeholder="Choose employee" /></SelectTrigger>
                    <SelectContent><SelectItem v-for="e in opening.options.employees" :key="e.id" :value="e.id">{{ e.name }}</SelectItem></SelectContent>
                  </Select>
                  <InputError :message="err(`salaries_owed.${i}.employee_id`)" />
                </div>
                <div class="grid gap-1">
                  <Label :for="`salaries_owed-${i}-amount`" :class="i === 0 ? undefined : 'sr-only'">Owed</Label>
                  <Input :id="`salaries_owed-${i}-amount`" v-model.number="row.amount" type="number" min="0" step="1" :disabled="!editable" />
                  <InputError :message="err(`salaries_owed.${i}.amount`)" />
                </div>
                <Button v-if="editable" variant="ghost" size="icon" @click="form.salaries_owed.splice(i, 1)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button v-if="editable" variant="outline" size="sm" @click="form.salaries_owed.push({ employee_id: '', amount: 0 })"><Plus class="mr-2 h-4 w-4" />Add employee</Button>
            </CardContent>
          </CollapsibleContent>
        </Collapsible>
      </Card>

      <!-- Amanat depositors -->
      <Card>
        <Collapsible v-model:open="open.amanat">
          <CardHeader>
            <CollapsibleTrigger class="flex w-full items-center justify-between text-left">
              <div>
                <CardTitle>Amanat depositors</CardTitle>
                <CardDescription>Money customers have left with the station</CardDescription>
              </div>
              <span class="flex items-center gap-3 text-sm">
                <MoneyText :amount="sum(form.amanat)" :currency="currency" :fraction-digits="0" />
                <ChevronDown class="h-4 w-4" />
              </span>
            </CollapsibleTrigger>
          </CardHeader>
          <CollapsibleContent>
            <CardContent class="space-y-3">
              <InputError :message="err('amanat')" />
              <div v-for="(row, i) in form.amanat" :key="'am-' + i" class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label :id="`amanat-${i}-customer_id-label`" :class="i === 0 ? undefined : 'sr-only'">Depositor</Label>
                  <EntitySearch
                    v-model="row.customer_id"
                    entity-type="customer"
                    :disabled="!editable"
                    :allow-quick-add="false"
                    :initial-entity="row.customer_id ? { id: row.customer_id, name: entityNames[row.customer_id] ?? '' } : null"
                    :aria-labelledby="`amanat-${i}-customer_id-label`"
                  />
                  <InputError :message="err(`amanat.${i}.customer_id`)" />
                </div>
                <div class="grid gap-1">
                  <Label :for="`amanat-${i}-amount`" :class="i === 0 ? undefined : 'sr-only'">Held</Label>
                  <Input :id="`amanat-${i}-amount`" v-model.number="row.amount" type="number" min="0" step="1" :disabled="!editable" />
                  <InputError :message="err(`amanat.${i}.amount`)" />
                </div>
                <Button v-if="editable" variant="ghost" size="icon" @click="form.amanat.splice(i, 1)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button v-if="editable" variant="outline" size="sm" @click="form.amanat.push({ customer_id: '', amount: 0 })"><Plus class="mr-2 h-4 w-4" />Add depositor</Button>
            </CardContent>
          </CollapsibleContent>
        </Collapsible>
      </Card>

      <!-- Suppliers -->
      <Card>
        <Collapsible v-model:open="open.suppliers">
          <CardHeader>
            <CollapsibleTrigger class="flex w-full items-center justify-between text-left">
              <div>
                <CardTitle>Suppliers</CardTitle>
                <CardDescription>Unpaid supplier balances. Becomes an opening bill.</CardDescription>
                <p class="text-xs text-muted-foreground">Create missing customers/suppliers on their own pages first.</p>
              </div>
              <span class="flex items-center gap-3 text-sm">
                <MoneyText :amount="sum(form.suppliers)" :currency="currency" :fraction-digits="0" />
                <ChevronDown class="h-4 w-4" />
              </span>
            </CollapsibleTrigger>
          </CardHeader>
          <CollapsibleContent>
            <CardContent class="space-y-3">
              <InputError :message="err('suppliers')" />
              <div v-for="(row, i) in form.suppliers" :key="'sup-' + i" class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label :id="`suppliers-${i}-vendor_id-label`" :class="i === 0 ? undefined : 'sr-only'">Supplier</Label>
                  <EntitySearch
                    v-model="row.vendor_id"
                    entity-type="vendor"
                    :disabled="!editable"
                    :allow-quick-add="false"
                    :initial-entity="row.vendor_id ? { id: row.vendor_id, name: entityNames[row.vendor_id] ?? '' } : null"
                    :aria-labelledby="`suppliers-${i}-vendor_id-label`"
                  />
                  <InputError :message="err(`suppliers.${i}.vendor_id`)" />
                </div>
                <div class="grid gap-1">
                  <Label :for="`suppliers-${i}-amount`" :class="i === 0 ? undefined : 'sr-only'">Owed</Label>
                  <Input :id="`suppliers-${i}-amount`" v-model.number="row.amount" type="number" min="0" step="1" :disabled="!editable" />
                  <InputError :message="err(`suppliers.${i}.amount`)" />
                </div>
                <Button v-if="editable" variant="ghost" size="icon" @click="form.suppliers.splice(i, 1)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button v-if="editable" variant="outline" size="sm" @click="form.suppliers.push({ vendor_id: '', amount: 0 })"><Plus class="mr-2 h-4 w-4" />Add supplier</Button>
            </CardContent>
          </CollapsibleContent>
        </Collapsible>
      </Card>

      <!-- Partner capital (optional) -->
      <Card>
        <Collapsible v-model:open="open.partners">
          <CardHeader>
            <CollapsibleTrigger class="flex w-full items-center justify-between text-left">
              <div>
                <CardTitle>Partner capital <span class="text-sm font-normal text-muted-foreground">(optional)</span></CardTitle>
                <CardDescription>Capital each partner has put in</CardDescription>
              </div>
              <span class="flex items-center gap-3 text-sm">
                <MoneyText :amount="sum(form.partners)" :currency="currency" :fraction-digits="0" />
                <ChevronDown class="h-4 w-4" />
              </span>
            </CollapsibleTrigger>
          </CardHeader>
          <CollapsibleContent>
            <CardContent class="space-y-3">
              <InputError :message="err('partners')" />
              <div v-for="(row, i) in form.partners" :key="'pt-' + i" class="grid grid-cols-[1fr_12rem_2.5rem] items-end gap-3">
                <div class="grid gap-1">
                  <Label :for="`partners-${i}-partner_id`" :class="i === 0 ? undefined : 'sr-only'">Partner</Label>
                  <Select v-model="row.partner_id" :disabled="!editable">
                    <SelectTrigger :id="`partners-${i}-partner_id`"><SelectValue placeholder="Choose partner" /></SelectTrigger>
                    <SelectContent><SelectItem v-for="p in opening.options.partners" :key="p.id" :value="p.id">{{ p.name }}</SelectItem></SelectContent>
                  </Select>
                  <InputError :message="err(`partners.${i}.partner_id`)" />
                </div>
                <div class="grid gap-1">
                  <Label :for="`partners-${i}-amount`" :class="i === 0 ? undefined : 'sr-only'">Capital</Label>
                  <Input :id="`partners-${i}-amount`" v-model.number="row.amount" type="number" min="0" step="1" :disabled="!editable" />
                  <InputError :message="err(`partners.${i}.amount`)" />
                </div>
                <Button v-if="editable" variant="ghost" size="icon" @click="form.partners.splice(i, 1)"><Trash2 class="h-4 w-4" /></Button>
              </div>
              <Button v-if="editable" variant="outline" size="sm" @click="form.partners.push({ partner_id: '', amount: 0 })"><Plus class="mr-2 h-4 w-4" />Add partner</Button>
            </CardContent>
          </CollapsibleContent>
        </Collapsible>
      </Card>
    </div>

    <!-- Sticky totals footer -->
    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-rule-default bg-background/95 px-4 backdrop-blur sm:px-6 lg:px-8">
      <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 py-3 text-sm">
        <div class="flex flex-wrap gap-6">
          <div>
            <div class="text-xs text-muted-foreground">Assets</div>
            <MoneyText :amount="assets" :currency="currency" :fraction-digits="0" class="font-medium" />
          </div>
          <div>
            <div class="text-xs text-muted-foreground">Liabilities</div>
            <MoneyText :amount="liabilities" :currency="currency" :fraction-digits="0" class="font-medium" />
          </div>
          <div>
            <div class="text-xs text-muted-foreground">&rarr; Opening Balance Equity</div>
            <MoneyText :amount="equity" :currency="currency" :fraction-digits="0" class="font-semibold" />
          </div>
        </div>
        <div class="flex items-center gap-2">
          <Button v-if="canLock" variant="outline" @click="showLockDialog = true">
            <Lock class="mr-2 h-4 w-4" />Lock
          </Button>
          <Button v-if="editable" :disabled="!canSave" @click="submit">Save opening balances</Button>
        </div>
      </div>
    </div>

    <Dialog :open="showLockDialog" @update:open="showLockDialog = $event">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Lock opening balances?</DialogTitle>
          <DialogDescription>Once locked, opening balances cannot be changed from this page.</DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <DialogClose as-child>
            <Button variant="outline">Cancel</Button>
          </DialogClose>
          <Button :disabled="locking" @click="lock">Lock</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
