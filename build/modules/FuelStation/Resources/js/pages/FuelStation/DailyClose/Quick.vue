<script setup lang="ts">
/**
 * Daily Close, quick entry: pick what you are entering (grouped as cash in / cash out), then who
 * or which account, then fill one small form. It works on the same day and the same parked draft
 * as the full form (DailyClose/Create): it loads that draft, changes only the entry lists it
 * knows about, and parks it back, so readings and everything else typed in the full form are
 * left alone.
 */
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select'
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
  paymentAccounts: Named[]
  cashAccountIds?: string[]
  creditCustomers: Array<{ id: string; name: string; is_credit_blocked: boolean }>
  purchaseSuppliers?: Named[]
  purchaseItems?: Array<{ id: string; name: string; is_fuel: boolean; unit: string }>
  tanks: Array<{ id: string; name: string; linked_item_id: string }>
  canEnterPurchases?: boolean
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
for (const list of ['credit_sales', 'expenses', 'bank_deposits', 'bank_withdrawals', 'payments_received', 'purchases']) {
  payload[list] ??= []
}

type Kind = 'payment_received' | 'bank_withdrawal' | 'credit_sale' | 'expense' | 'bank_deposit' | 'delivery'
type Direction = 'in' | 'out' | 'stock'
interface KindMeta { value: Kind; label: string; secondary: string; direction: Direction; list: string }
const kinds: KindMeta[] = [
  { value: 'payment_received', label: 'Payment received', secondary: 'Customer', direction: 'in', list: 'payments_received' },
  { value: 'bank_withdrawal', label: 'Cash withdrawn from bank', secondary: 'Bank', direction: 'in', list: 'bank_withdrawals' },
  { value: 'credit_sale', label: 'Credit sale', secondary: 'Customer', direction: 'out', list: 'credit_sales' },
  { value: 'expense', label: 'Expense', secondary: 'Expense account', direction: 'out', list: 'expenses' },
  { value: 'bank_deposit', label: 'Bank deposit', secondary: 'Bank', direction: 'out', list: 'bank_deposits' },
  // A supplier bill for a delivery, created (and received into the tank) when the day posts.
  { value: 'delivery', label: 'Delivery (supplier bill)', secondary: 'Supplier', direction: 'stock', list: 'purchases' },
]
const kindGroups = [
  { label: 'Cash in', kinds: kinds.filter((k) => k.direction === 'in') },
  { label: 'Cash out', kinds: kinds.filter((k) => k.direction === 'out') },
  { label: 'Purchases', kinds: props.canEnterPurchases ? kinds.filter((k) => k.direction === 'stock') : [] },
].filter((g) => g.kinds.length)

const kind = ref<Kind | ''>('')
const targetId = ref('')
const kindMeta = computed(() => kinds.find((k) => k.value === kind.value))
// Only what fits the chosen entry: customers for a sale or payment, banks for deposits and
// withdrawals, expense accounts for an expense.
const secondaryOptions = computed<Array<Named & { balance?: number }>>(() => {
  switch (kind.value) {
    case 'credit_sale': return props.creditCustomers.filter((c) => !c.is_credit_blocked)
    case 'payment_received': return props.creditCustomers
    case 'expense': return props.expenseAccounts
    case 'bank_deposit':
    case 'bank_withdrawal': return props.bankAccounts
    case 'delivery': return props.purchaseSuppliers ?? []
    default: return []
  }
})
const target = computed(() => secondaryOptions.value.find((o) => o.id === targetId.value))

const cashAccountId = computed(() => props.paymentAccounts.find((a) => (props.cashAccountIds ?? []).includes(a.id))?.id ?? props.paymentAccounts[0]?.id ?? '')

// One small form per entry. Changing the entry type clears the choice; changing who clears the
// figures. After Add, both stay so the next one of the same kind needs only its figures.
const entry = reactive({
  item_id: '', litres: null as number | null, amount: null as number | null, reference: '', description: '', payment_account_id: '',
  tank_id: '', direct: null as number | null, show_direct: false, paid_now: false,
})
const resetEntry = () => Object.assign(entry, {
  item_id: '', litres: null, amount: null, reference: '', description: '', payment_account_id: cashAccountId.value,
  tank_id: '', direct: null, show_direct: false, paid_now: false,
})
resetEntry()
let keepTarget = false
watch(kind, () => {
  if (!keepTarget) targetId.value = ''
  keepTarget = false
  resetEntry()
})
watch(targetId, resetEntry)

// Credit sale: litres drive the amount at the day's rate; typing an amount works back to litres.
const rateFor = (itemId: string) => Number(props.rates?.[itemId]?.sale_rate ?? 0)
const round2 = (n: number) => Math.round(n * 100) / 100
const onLitres = (v: unknown) => {
  entry.litres = v === '' || v === null ? null : Number(v)
  if (kind.value === 'delivery') return
  const rate = rateFor(entry.item_id)
  if (rate > 0 && entry.litres) entry.amount = round2(entry.litres * rate)
}
const onAmount = (v: unknown) => {
  entry.amount = v === '' || v === null ? null : Number(v)
  if (kind.value === 'delivery') return
  const rate = rateFor(entry.item_id)
  if (kind.value === 'credit_sale' && rate > 0 && entry.amount) entry.litres = round2(entry.amount / rate)
}
watch(() => entry.item_id, (id) => {
  if (kind.value === 'delivery') {
    // A fuel's tank is picked for it when only one tank holds that fuel.
    const tanksOfItem = tanksForItem(id)
    entry.tank_id = tanksOfItem.length === 1 ? tanksOfItem[0].id : ''
    return
  }
  const rate = rateFor(id)
  if (rate > 0 && entry.litres) entry.amount = round2(entry.litres * rate)
})

// Delivery: litres + the total billed; the rate falls out of them (4 decimals, like the Bills form).
const purchaseItem = computed(() => (props.purchaseItems ?? []).find((i) => i.id === entry.item_id))
const tanksForItem = (itemId: string) => props.tanks.filter((t) => t.linked_item_id === itemId)
const deliveryRate = computed(() => (Number(entry.litres) > 0 && Number(entry.amount) > 0 ? Math.round((Number(entry.amount) / Number(entry.litres)) * 10000) / 10000 : null))
const deliveryNeedsTank = computed(() => !!purchaseItem.value?.is_fuel && Number(entry.litres) - Number(entry.direct || 0) > 0)

const canAdd = computed(() =>
  !!target.value && Number(entry.amount) > 0
  && (kind.value !== 'credit_sale' || !!entry.item_id)
  && (kind.value !== 'payment_received' || !!entry.payment_account_id)
  && (kind.value !== 'delivery' || (!!entry.item_id && Number(entry.litres) > 0
    && Number(entry.direct || 0) <= Number(entry.litres) && (!deliveryNeedsTank.value || !!entry.tank_id))),
)

const addEntry = () => {
  if (!canAdd.value || !target.value || !kindMeta.value) return
  const amount = round2(Number(entry.amount))
  const t = target.value
  const rowFor: Record<Kind, () => Record<string, any>> = {
    credit_sale: () => ({ customer_id: t.id, customer_name: t.name, item_id: entry.item_id, litres: entry.litres ?? undefined, amount, reference: entry.reference }),
    payment_received: () => ({ customer_id: t.id, customer_name: t.name, invoice_ids: [], amount, payment_account_id: entry.payment_account_id, reference: entry.reference }),
    expense: () => ({ account_id: t.id, account_name: t.name, description: entry.description, amount }),
    bank_deposit: () => ({ bank_account_id: t.id, amount, reference: entry.reference, purpose: '' }),
    bank_withdrawal: () => ({ bank_account_id: t.id, amount, reference: entry.reference, purpose: '' }),
    // Same row shape as the full form's Purchases (bill.create on post, amount-driven rate).
    delivery: () => ({
      supplier_id: t.id, item_id: entry.item_id, description: '', quantity: Number(entry.litres),
      unit_cost: deliveryRate.value, line_total: amount, amount_driven: true,
      tank_id: deliveryNeedsTank.value ? entry.tank_id : '', supplier_invoice_number: '', notes: '',
      paid_now: entry.paid_now, direct_quantity: Number(entry.direct || 0) || null,
    }),
  }
  payload[kindMeta.value.list].push(rowFor[kindMeta.value.value]())
  dirty.value = true
  resetEntry()
}

// "+" on a row: open the form again for the same entry type and the same customer/account.
const formEl = ref<HTMLElement | null>(null)
const addAnotherLike = (row: { kind: Kind; targetId: string }) => {
  if (kind.value !== row.kind) keepTarget = true
  kind.value = row.kind
  targetId.value = row.targetId
  resetEntry()
  formEl.value?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

// Today's entries of the kinds this page handles, as one register with money in and money out
// in their own columns. Rows the full form locks (credit sales pre-loaded from an invoice) are
// shown but not removable.
const fuelName = (id?: string) => props.fuelItems.find((f) => f.id === id)?.name
const bankName = (id: string) => props.bankAccounts.find((b) => b.id === id)?.name ?? 'Bank'
const rowTarget: Record<Kind, (r: any) => string> = {
  credit_sale: (r) => r.customer_id, payment_received: (r) => r.customer_id, expense: (r) => r.account_id,
  bank_deposit: (r) => r.bank_account_id, bank_withdrawal: (r) => r.bank_account_id,
  delivery: (r) => r.supplier_id,
}
const rowDetails: Record<Kind, (r: any) => string> = {
  credit_sale: (r) => [r.customer_name, r.litres ? `${r.litres} L ${fuelName(r.item_id) ?? ''}`.trim() : null, r.reference || r.invoice_number].filter(Boolean).join(' · '),
  payment_received: (r) => [r.customer_name, r.reference].filter(Boolean).join(' · '),
  expense: (r) => [r.account_name, r.description].filter(Boolean).join(' · '),
  bank_deposit: (r) => [bankName(r.bank_account_id), r.reference].filter(Boolean).join(' · '),
  bank_withdrawal: (r) => [bankName(r.bank_account_id), r.reference].filter(Boolean).join(' · '),
  delivery: (r) => [
    (props.purchaseSuppliers ?? []).find((v) => v.id === r.supplier_id)?.name,
    `${r.quantity} ${(props.purchaseItems ?? []).find((i) => i.id === r.item_id)?.name ?? ''}`.trim(),
    Number(r.direct_quantity) > 0 ? `${r.direct_quantity} sold directly` : null,
    r.paid_now ? 'paid in cash' : `on account ${Math.round(Number(r.line_total || 0)).toLocaleString()}`,
  ].filter(Boolean).join(' · '),
}
const rows = computed(() => kinds.flatMap((k) => (payload[k.list] as any[]).map((r, i) => ({
  id: `${k.list}:${i}`, list: k.list, index: i, kind: k.value, targetId: rowTarget[k.value](r),
  locked: !!(r.pending_fuel_invoice || r.pending_accounting_invoice),
  type: k.label, details: rowDetails[k.value](r),
  in: k.direction === 'in' ? Number(r.amount || 0) : null,
  out: k.direction === 'out' ? Number(r.amount || 0) : (k.value === 'delivery' && r.paid_now ? Number(r.line_total || 0) : null),
}))))
const columns = [
  { key: 'type', label: 'Entry', kind: 'text' as const },
  { key: 'details', label: 'Details', kind: 'text' as const },
  { key: 'in', label: 'In', kind: 'in' as const, align: 'right' as const },
  { key: 'out', label: 'Out', kind: 'out' as const, align: 'right' as const },
  { key: 'actions', label: '', kind: 'text' as const },
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
      <section ref="formEl" class="grid gap-4 border-y border-rule-default py-4 md:grid-cols-[14rem_18rem_1fr]">
        <div class="space-y-1">
          <Label for="quick-kind">Entry</Label>
          <Select v-model="kind">
            <SelectTrigger id="quick-kind"><SelectValue placeholder="What are you entering?" /></SelectTrigger>
            <SelectContent>
              <SelectGroup v-for="group in kindGroups" :key="group.label">
                <SelectLabel>{{ group.label }}</SelectLabel>
                <SelectItem v-for="k in group.kinds" :key="k.value" :value="k.value">{{ k.label }}</SelectItem>
              </SelectGroup>
            </SelectContent>
          </Select>
          <p v-if="kindMeta" class="text-xs text-text-secondary">{{ kindMeta.direction === 'in' ? 'Cash in' : kindMeta.direction === 'out' ? 'Cash out' : 'Cash out only if paid now' }}</p>
        </div>

        <div class="space-y-1">
          <Label for="quick-target">{{ kindMeta?.secondary ?? 'Choose' }}</Label>
          <Select v-model="targetId" :disabled="!kind">
            <SelectTrigger id="quick-target"><SelectValue :placeholder="kind ? 'Select…' : 'Pick an entry first'" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="o in secondaryOptions" :key="o.id" :value="o.id">{{ o.name }}</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="(kind === 'bank_deposit' || kind === 'bank_withdrawal') && target" class="text-xs text-text-secondary">
            Balance <MoneyText :amount="target.balance ?? 0" :currency="currency" :fraction-digits="0" />
          </p>
        </div>

        <div v-if="target" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
          <template v-if="kind === 'delivery'">
            <div class="space-y-1">
              <Label for="quick-item">Item</Label>
              <Select v-model="entry.item_id">
                <SelectTrigger id="quick-item"><SelectValue placeholder="Item" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="i in purchaseItems ?? []" :key="i.id" :value="i.id">{{ i.name }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="deliveryNeedsTank && tanksForItem(entry.item_id).length === 1" class="text-xs text-text-secondary">Into {{ tanksForItem(entry.item_id)[0].name }}</p>
            </div>
            <div class="space-y-1">
              <Label for="quick-qty">{{ purchaseItem?.is_fuel ? 'Litres' : 'Quantity' }}</Label>
              <Input id="quick-qty" type="number" min="0" step="0.01" :model-value="entry.litres ?? ''" @update:model-value="onLitres" />
            </div>
            <div v-if="deliveryNeedsTank && tanksForItem(entry.item_id).length !== 1" class="space-y-1">
              <Label for="quick-tank">Tank</Label>
              <Select v-model="entry.tank_id">
                <SelectTrigger id="quick-tank"><SelectValue placeholder="Tank" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="t in (tanksForItem(entry.item_id).length ? tanksForItem(entry.item_id) : tanks)" :key="t.id" :value="t.id">{{ t.name }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </template>
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
          <div v-if="kind === 'payment_received'" class="space-y-1">
            <Label for="quick-into">Into</Label>
            <Select v-model="entry.payment_account_id">
              <SelectTrigger id="quick-into"><SelectValue placeholder="Account" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="a in paymentAccounts" :key="a.id" :value="a.id">{{ a.name }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-1">
            <Label for="quick-amount">{{ kind === 'delivery' ? 'Total' : 'Amount' }}</Label>
            <Input id="quick-amount" type="number" min="0" step="0.01" :model-value="entry.amount ?? ''" @update:model-value="onAmount" />
            <p v-if="kind === 'delivery' && deliveryRate" class="text-xs text-text-secondary">@ {{ deliveryRate }} / L</p>
          </div>
          <template v-if="kind === 'delivery'">
            <div v-if="entry.show_direct" class="space-y-1">
              <Label for="quick-direct">Sold directly (L)</Label>
              <Input id="quick-direct" v-model.number="entry.direct" type="number" min="0" step="0.01" />
            </div>
            <div class="flex flex-col gap-2 pb-1 text-sm">
              <button v-if="!entry.show_direct && purchaseItem?.is_fuel" type="button" class="text-left text-xs text-primary underline-offset-2 hover:underline" @click="entry.show_direct = true">+ Sold directly</button>
              <label class="flex items-center gap-2 text-xs"><input v-model="entry.paid_now" type="checkbox" class="h-4 w-4" /> Paid now from cash</label>
            </div>
          </template>
          <div v-else-if="kind === 'expense'" class="space-y-1">
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
        <template #cell-in="{ row }">
          <MoneyText v-if="row.in !== null" :amount="row.in" :currency="currency" :fraction-digits="0" />
        </template>
        <template #cell-out="{ row }">
          <MoneyText v-if="row.out !== null" :amount="row.out" :currency="currency" :fraction-digits="0" />
        </template>
        <template #cell-actions="{ row }">
          <div class="flex justify-end gap-1">
            <Button variant="ghost" size="icon" :aria-label="`Add another ${row.type}`" title="Add another like this" @click="addAnotherLike(row)">
              <Plus class="h-4 w-4" />
            </Button>
            <Button v-if="!row.locked" variant="ghost" size="icon" :aria-label="`Remove ${row.type}`" @click="removeRow(row)">
              <Trash2 class="h-4 w-4" />
            </Button>
            <span v-else class="self-center text-xs text-text-secondary">from invoice</span>
          </div>
        </template>
        <template #empty>Nothing entered yet for this day.</template>
      </LedgerRegister>

      <p v-if="dirty" class="text-sm text-status-attention">Unsaved changes: press Save draft before leaving this page.</p>
    </div>
  </PageShell>
</template>
