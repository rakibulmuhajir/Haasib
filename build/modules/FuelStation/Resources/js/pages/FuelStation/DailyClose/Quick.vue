<script setup lang="ts">
/**
 * Daily Close, quick entry: pick what you are entering, then who or which account, then fill
 * one small form. It works on the same day and the same parked draft as the full form
 * (DailyClose/Create): it loads that draft, changes only the entry lists it knows about, and
 * parks it back, so readings and everything else typed in the full form are left alone.
 */
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ClipboardList, Plus, Trash2 } from 'lucide-vue-next'

// The controller hands over every Daily Close prop; only the ones declared below are used.
defineOptions({ inheritAttrs: false })

interface Named { id: string; name: string; code?: string }

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  date: string
  parkedDraft?: Record<string, any> | null
  previousClose: { closing_cash?: number | null }
  fuelItems: Array<{ id: string; name: string; fuel_category: string }>
  rates: Record<string, { purchase_rate: number; sale_rate: number }>
  expenseAccounts: Named[]
  bankAccounts: Array<Named & { balance?: number }>
  creditCustomers: Array<{ id: string; name: string; is_credit_blocked: boolean }>
}>()

const currency = computed(() => props.company.base_currency || 'PKR')

// The draft being edited: the parked one if the day has it, else a minimal one the full form
// fills in (it rebuilds nozzles/tanks from the station on restore).
const payload = reactive<Record<string, any>>({
  date: props.date,
  opening_cash: Number(props.previousClose?.closing_cash ?? 0),
  nozzle_readings: [],
  ...(props.parkedDraft ?? {}),
})
payload.credit_sales ??= []
payload.expenses ??= []
payload.bank_deposits ??= []

type Kind = 'credit_sale' | 'expense' | 'bank_deposit'
const kinds: Array<{ value: Kind; label: string; secondary: string }> = [
  { value: 'credit_sale', label: 'Credit sale', secondary: 'Customer' },
  { value: 'expense', label: 'Expense', secondary: 'Expense account' },
  { value: 'bank_deposit', label: 'Bank deposit', secondary: 'Bank' },
]

const kind = ref<Kind | ''>('')
const targetId = ref('')
const kindMeta = computed(() => kinds.find((k) => k.value === kind.value))
const secondaryOptions = computed<Named[]>(() => {
  if (kind.value === 'credit_sale') return props.creditCustomers.filter((c) => !c.is_credit_blocked)
  if (kind.value === 'expense') return props.expenseAccounts
  if (kind.value === 'bank_deposit') return props.bankAccounts
  return []
})
const target = computed(() => secondaryOptions.value.find((o) => o.id === targetId.value))

// One small form per kind; reset whenever the choice changes.
const entry = reactive({ item_id: '', litres: null as number | null, amount: null as number | null, reference: '', description: '' })
const resetEntry = () => Object.assign(entry, { item_id: '', litres: null, amount: null, reference: '', description: '' })
watch(kind, () => { targetId.value = ''; resetEntry() })
watch(targetId, resetEntry)

// Credit sale: litres drive the amount at the day's rate; typing an amount works back to litres.
const rateFor = (itemId: string) => Number(props.rates?.[itemId]?.sale_rate ?? 0)
const round2 = (n: number) => Math.round(n * 100) / 100
const onLitres = (v: unknown) => {
  entry.litres = v === '' || v === null ? null : Number(v)
  const rate = rateFor(entry.item_id)
  if (rate > 0 && entry.litres) entry.amount = round2(entry.litres * rate)
}
const onAmount = (v: unknown) => {
  entry.amount = v === '' || v === null ? null : Number(v)
  const rate = rateFor(entry.item_id)
  if (kind.value === 'credit_sale' && rate > 0 && entry.amount) entry.litres = round2(entry.amount / rate)
}
watch(() => entry.item_id, (id) => {
  const rate = rateFor(id)
  if (rate > 0 && entry.litres) entry.amount = round2(entry.litres * rate)
})

const canAdd = computed(() => !!target.value && Number(entry.amount) > 0 && (kind.value !== 'credit_sale' || !!entry.item_id))

const addEntry = () => {
  if (!canAdd.value || !target.value) return
  const amount = round2(Number(entry.amount))
  if (kind.value === 'credit_sale') {
    payload.credit_sales.push({
      customer_id: target.value.id, customer_name: target.value.name, item_id: entry.item_id,
      litres: entry.litres ?? undefined, amount, reference: entry.reference,
    })
  } else if (kind.value === 'expense') {
    payload.expenses.push({ account_id: target.value.id, account_name: target.value.name, description: entry.description, amount })
  } else if (kind.value === 'bank_deposit') {
    payload.bank_deposits.push({ bank_account_id: target.value.id, amount, reference: entry.reference, purpose: '' })
  }
  dirty.value = true
  resetEntry()
}

// Today's entries of the kinds this page handles, as one register. Rows the full form locks
// (credit sales pre-loaded from an invoice) are shown but not removable.
const fuelName = (id?: string) => props.fuelItems.find((f) => f.id === id)?.name
const bankName = (id: string) => props.bankAccounts.find((b) => b.id === id)?.name ?? 'Bank'
const rows = computed(() => [
  ...payload.credit_sales.map((r: any, i: number) => ({
    id: `credit_sales:${i}`, list: 'credit_sales', index: i, locked: !!(r.pending_fuel_invoice || r.pending_accounting_invoice),
    type: 'Credit sale',
    details: [r.customer_name, r.litres ? `${r.litres} L ${fuelName(r.item_id) ?? ''}`.trim() : null, r.reference || r.invoice_number].filter(Boolean).join(' · '),
    amount: Number(r.amount || 0),
  })),
  ...payload.expenses.map((r: any, i: number) => ({
    id: `expenses:${i}`, list: 'expenses', index: i, locked: false,
    type: 'Expense', details: [r.account_name, r.description].filter(Boolean).join(' · '), amount: Number(r.amount || 0),
  })),
  ...payload.bank_deposits.map((r: any, i: number) => ({
    id: `bank_deposits:${i}`, list: 'bank_deposits', index: i, locked: false,
    type: 'Bank deposit', details: [bankName(r.bank_account_id), r.reference].filter(Boolean).join(' · '), amount: Number(r.amount || 0),
  })),
])
const columns = [
  { key: 'type', label: 'Entry', kind: 'text' as const },
  { key: 'details', label: 'Details', kind: 'text' as const },
  { key: 'amount', label: 'Amount', kind: 'amount' as const, align: 'right' as const },
  { key: 'remove', label: '', kind: 'text' as const },
]
const removeRow = (row: { list: string; index: number }) => {
  payload[row.list].splice(row.index, 1)
  dirty.value = true
}

const dirty = ref(false)
const saving = ref(false)
const saveDraft = () => {
  saving.value = true
  router.post(`/${props.company.slug}/fuel/daily-close`, { ...payload, date: props.date, intent: 'park' }, {
    preserveScroll: true,
    onSuccess: () => { dirty.value = false },
    onFinish: () => { saving.value = false },
  })
}

const changeDate = (value: string | number) => {
  if (value) router.get(`/${props.company.slug}/fuel/daily-close/quick`, { date: String(value) })
}
</script>

<template>
  <Head title="Daily close · quick entry" />
  <PageShell
    title="Daily close · quick entry"
    description="Pick what you are entering, then who, then fill it in. Readings and posting stay on the full form."
    :icon="ClipboardList"
    :breadcrumbs="[
      { title: 'Daily close', href: `/${company.slug}/fuel/daily-close?date=${date}` },
      { title: 'Quick entry', href: `/${company.slug}/fuel/daily-close/quick?date=${date}` },
    ]"
  >
    <template #actions>
      <Button variant="outline" as-child>
        <Link :href="`/${company.slug}/fuel/daily-close?date=${date}`">Open full form</Link>
      </Button>
      <Button :disabled="saving || !dirty" @click="saveDraft">Save draft</Button>
    </template>

    <div class="space-y-6">
      <div class="flex flex-wrap items-end gap-4">
        <div class="space-y-1">
          <Label for="quick-date">Business date</Label>
          <Input id="quick-date" type="date" :model-value="date" class="w-44" @update:model-value="changeDate" />
        </div>
        <p v-if="parkedDraft" class="pb-2 text-sm text-text-secondary">Continuing this day's saved draft.</p>
      </div>

      <!-- What -> who -> the form -->
      <section class="grid gap-4 border-y border-rule-default py-4 md:grid-cols-[14rem_18rem_1fr]">
        <div class="space-y-1">
          <Label for="quick-kind">Entry</Label>
          <Select v-model="kind">
            <SelectTrigger id="quick-kind"><SelectValue placeholder="What are you entering?" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="k in kinds" :key="k.value" :value="k.value">{{ k.label }}</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-1">
          <Label for="quick-target">{{ kindMeta?.secondary ?? 'Choose' }}</Label>
          <Select v-model="targetId" :disabled="!kind">
            <SelectTrigger id="quick-target"><SelectValue :placeholder="kind ? 'Select…' : 'Pick an entry first'" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="o in secondaryOptions" :key="o.id" :value="o.id">{{ o.name }}</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="kind === 'bank_deposit' && target" class="text-xs text-text-secondary">
            Balance <MoneyText :amount="(target as any).balance ?? 0" :currency="currency" :fraction-digits="0" />
          </p>
        </div>

        <div v-if="target" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
          <template v-if="kind === 'credit_sale'">
            <div class="space-y-1">
              <Label for="quick-fuel">Fuel</Label>
              <Select v-model="entry.item_id">
                <SelectTrigger id="quick-fuel"><SelectValue placeholder="Fuel" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="f in fuelItems" :key="f.id" :value="f.id">{{ f.name }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-1">
              <Label for="quick-litres">Litres</Label>
              <Input id="quick-litres" type="number" min="0" step="0.01" :model-value="entry.litres ?? ''" @update:model-value="onLitres" />
              <p v-if="entry.item_id && rateFor(entry.item_id) > 0" class="text-xs text-text-secondary">@ {{ rateFor(entry.item_id) }} / L today</p>
            </div>
          </template>
          <div class="space-y-1">
            <Label for="quick-amount">Amount</Label>
            <Input id="quick-amount" type="number" min="0" step="0.01" :model-value="entry.amount ?? ''" @update:model-value="onAmount" />
          </div>
          <div v-if="kind === 'expense'" class="space-y-1">
            <Label for="quick-description">Description</Label>
            <Input id="quick-description" v-model="entry.description" maxlength="255" />
          </div>
          <div v-else class="space-y-1">
            <Label for="quick-reference">{{ kind === 'credit_sale' ? 'Slip / reference' : 'Reference' }}</Label>
            <Input id="quick-reference" v-model="entry.reference" maxlength="100" />
          </div>
          <Button :disabled="!canAdd" @click="addEntry"><Plus class="mr-1 h-4 w-4" />Add</Button>
        </div>
        <p v-else class="self-end pb-2 text-sm text-text-secondary">
          {{ kind ? `Choose the ${kindMeta?.secondary.toLowerCase()} to fill in the entry.` : 'Choose an entry type to begin.' }}
        </p>
      </section>

      <LedgerRegister :data="rows" :columns="columns" key-field="id" title="Today's entries">
        <template #cell-amount="{ row }">
          <MoneyText :amount="row.amount" :currency="currency" :fraction-digits="0" />
        </template>
        <template #cell-remove="{ row }">
          <Button v-if="!row.locked" variant="ghost" size="icon" :aria-label="`Remove ${row.type}`" @click="removeRow(row)">
            <Trash2 class="h-4 w-4" />
          </Button>
          <span v-else class="text-xs text-text-secondary">from invoice</span>
        </template>
        <template #empty>Nothing entered yet for this day.</template>
      </LedgerRegister>

      <p v-if="dirty" class="text-sm text-status-attention">Unsaved changes: press Save draft before leaving this page.</p>
    </div>
  </PageShell>
</template>
