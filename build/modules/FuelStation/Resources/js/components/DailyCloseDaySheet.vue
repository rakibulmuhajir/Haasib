<script setup lang="ts">
/**
 * A posted Daily Close read as one sheet: cash, fuel sales, tanks, money in, money out --
 * every line itemised and every total the sum of the lines above it. Figures come from what
 * the close stored when it posted (metadata + posting_snapshot); things recorded on other
 * screens that day (a direct sale's cash, a supplier payment made from Bills) are listed from
 * the snapshot's sources, so the totals no longer contain amounts no line explains.
 */
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'

interface Line {
  label: string
  detail?: string
  amount: number
  href?: string
  sub?: Array<{ label: string; detail?: string; amount: number }>
  credit?: any
}

const props = defineProps<{
  metadata: Record<string, any>
  currency: string
  companySlug: string
  fuelItems?: Array<{ id: string; name: string }>
  expenseAccounts?: Array<{ id: string; name: string }>
  accountNames?: Record<string, string>
  nozzleNames?: Record<string, { name: string; tank_id: string | null }>
  previousTankDips?: Record<string, number>
  paymentSources?: Record<string, { direct: boolean; invoices: string }>
  creditRows: Array<{ invoice_id: string; invoice_number: string; customer_name: string | null; amount: number; discount_amount: number; balance: number; source?: string; split_from?: string }>
  canApplyDiscount?: boolean
}>()
const emit = defineEmits<{ applyDiscount: [credit: any] }>()

const m = computed(() => props.metadata || {})
const snap = computed(() => m.value.posting_snapshot || {})
const totals = computed(() => snap.value.totals || {})
const input = computed(() => m.value.form_input || {})
const n = (v: unknown) => Number(v || 0)
const round0 = (v: number) => Math.round(v)
const litres = (v: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(v)
const itemName = (id?: string) => props.fuelItems?.find((i) => i.id === id)?.name ?? 'Fuel'
const accountName = (id?: string | null) => (id && props.accountNames?.[id]) || 'Account'
const sum = (lines: Line[]) => lines.reduce((s, l) => s + n(l.amount), 0)

// Everything recorded on other screens that business day, as the close read it.
const sourceLabels: Record<string, string> = {
  invoice: 'Invoice', payment: 'Customer payment', bill: 'Bill', bill_payment: 'Supplier payment', expense: 'Expense',
}
// The close's own supplier payments and expense journals are in the snapshot's sources too, but
// they are already itemised above (Paid supplier, Expense) -- leave them out so nothing counts
// twice. Its inline purchases (close_purchase) are not itemised elsewhere, so they stay.
const otherScreens = computed(() => {
  const ownExpenses = new Set<string>((m.value.expense_transaction_ids || []) as string[])
  return Object.values(snap.value.sources || {}).filter((s: any) =>
    !String(s.type || '').startsWith('stock:')
    && !ownExpenses.has(s.id)
    && !(String(s.source || '').startsWith('close_') && s.source !== 'close_purchase'),
  ) as any[]
})
const sourceLabel = (s: any) => `${sourceLabels[s.type] ?? String(s.type).replace(/[_:]/g, ' ')} · ${s.reference ?? ''}`.trim()

/** Keeps a section honest: if its lines do not reach the posted total, say so on a line. */
const withRemainder = (lines: Line[], total: number, label: string): Line[] => {
  const diff = round0(total - sum(lines))
  return Math.abs(diff) >= 1 ? [...lines, { label, amount: diff }] : lines
}

// ---------- fuel sales ----------
const nozzleRows = computed(() =>
  ((input.value.nozzle_readings || []) as any[])
    .filter((r) => n(r.liters_sold) > 0)
    .map((r) => ({
      item_id: r.item_id,
      name: props.nozzleNames?.[r.nozzle_id]?.name ?? 'Nozzle',
      tank_id: props.nozzleNames?.[r.nozzle_id]?.tank_id ?? null,
      opening: n(r.opening_electronic),
      closing: n(r.closing_electronic),
      litres: n(r.liters_sold),
      rate: n(r.sale_rate),
    })),
)
const salesLines = computed<Line[]>(() => {
  const byFuel = new Map<string, Line & { litres: number }>()
  for (const r of nozzleRows.value) {
    const fuel = itemName(r.item_id)
    const line = byFuel.get(fuel) ?? { label: fuel, detail: '', amount: 0, litres: 0, sub: [] }
    line.amount += r.litres * r.rate
    line.litres += r.litres
    line.sub!.push({ label: r.name, detail: `${litres(r.opening)} → ${litres(r.closing)} = ${litres(r.litres)} L × ${r.rate}`, amount: r.litres * r.rate })
    byFuel.set(fuel, line)
  }
  const fuelLines: Line[] = [...byFuel.values()].map(({ litres: total, ...l }) => ({ ...l, detail: `${litres(total)} L` }))
  const fuelTotal = n(m.value.total_revenue)
  const lines: Line[] = withRemainder(fuelLines, fuelTotal, 'Rate-change split / rounding')
  for (const o of (m.value.other_sales_details || []) as any[]) {
    lines.push({ label: o.item_name ?? 'Other sale', detail: `${litres(n(o.quantity))} × ${n(o.unit_price)}`, amount: n(o.amount) })
  }
  for (const s of otherScreens.value.filter((x) => n(x.sales) !== 0)) {
    lines.push({ label: s.type === 'invoice' ? `Direct / invoiced sale · ${s.reference}` : sourceLabel(s), amount: n(s.sales) })
  }
  return withRemainder(lines, n(totals.value.total_revenue), 'Other sales')
})
const pumpTests = computed(() => (m.value.pump_tests || []) as any[])

// ---------- tanks ----------
const tankRows = computed(() =>
  ((snap.value.tanks || []) as any[]).map((t) => {
    const opening = props.previousTankDips?.[t.tank_id]
    const sold = nozzleRows.value.filter((r) => r.tank_id === t.tank_id).reduce((s, r) => s + r.litres, 0)
    const expected = n(t.expected_liters)
    return {
      name: t.tank_name,
      opening,
      delivered: opening === undefined ? null : Math.max(0, expected - opening + sold),
      sold,
      expected,
      dip: n(t.physical_liters),
      stick: t.stick_reading,
      variance: n(t.physical_liters) - expected,
    }
  }),
)

// ---------- money in ----------
const moneyIn = computed<Line[]>(() => {
  const lines: Line[] = [{ label: 'Opening cash', amount: n(totals.value.opening_cash ?? m.value.opening_cash) }]
  if (n(m.value.total_revenue)) lines.push({ label: 'Meter sales', amount: n(m.value.total_revenue) })
  if (n(m.value.other_sales)) lines.push({ label: 'Lubricants & other sales', amount: n(m.value.other_sales) })
  for (const [id, amt] of Object.entries(m.value.bank_withdrawals_by_account || {})) lines.push({ label: 'Cash withdrawn from bank', detail: accountName(id), amount: n(amt) })
  for (const p of (m.value.payments_received_details || []) as any[]) lines.push({ label: 'Payment received', detail: p.customer_name, amount: n(p.amount) })
  if (n(m.value.partner_deposits)) lines.push({ label: 'Partner deposits', amount: n(m.value.partner_deposits) })
  for (const a of (m.value.amanat_deposit_details || []) as any[]) lines.push({ label: 'Amanat deposit', detail: a.customer_name, amount: n(a.amount) })
  for (const o of (m.value.other_deposit_details || []) as any[]) lines.push({ label: 'Other cash in', detail: o.description || o.deposit_type, amount: n(o.amount) })
  for (const s of otherScreens.value.filter((x) => n(x.money_in) > 0)) {
    // A customer payment is named by what it paid: a direct sale's cash reads as such.
    const paid = s.type === 'payment' ? props.paymentSources?.[s.id] : undefined
    if (paid?.direct) lines.push({ label: 'Direct sale cash', detail: paid.invoices, amount: n(s.money_in) })
    else if (paid?.invoices) lines.push({ label: 'Customer payment', detail: `${s.reference} for ${paid.invoices}`, amount: n(s.money_in) })
    else lines.push({ label: sourceLabel(s), amount: n(s.money_in) })
  }
  return withRemainder(lines, n(totals.value.opening_cash ?? m.value.opening_cash) + n(totals.value.money_in), 'Other money in')
})

// ---------- money out ----------
const moneyOut = computed<Line[]>(() => {
  const lines: Line[] = []
  for (const c of props.creditRows) {
    lines.push({
      label: c.source === 'accounting_invoice' ? 'Invoiced in Accounting' : 'Credit sale',
      detail: [c.customer_name, c.invoice_number, c.split_from ? `split from ${c.split_from}` : null, c.discount_amount ? `discount ${round0(c.discount_amount).toLocaleString()}` : null, `owes ${round0(c.balance).toLocaleString()}`].filter(Boolean).join(' · '),
      amount: n(c.amount),
      href: `/${props.companySlug}/invoices/${c.invoice_id}`,
      // A share split off onto another invoice is not the close's own invoice: no close discount on it.
      credit: c.split_from ? undefined : c,
    })
  }
  for (const r of (m.value.payment_receipt_postings || []) as any[]) {
    if (n(r.amount)) lines.push({
      label: r.channel_label ?? 'Card / bank sale',
      detail: [`into ${accountName(r.account_id)}`, n(r.fee_amount) ? `charge ${r.fee_percent}% ${round0(n(r.fee_amount)).toLocaleString()}` : null].filter(Boolean).join(' · '),
      amount: n(r.amount),
    })
  }
  for (const [id, amt] of Object.entries(m.value.bank_deposits_by_account || {})) lines.push({ label: 'Bank deposit', detail: accountName(id), amount: n(amt) })
  for (const p of (m.value.pay_supplier_details || []) as any[]) {
    lines.push({ label: 'Paid supplier', detail: [p.vendor_name, n(p.advance_amount) ? `advance ${round0(n(p.advance_amount)).toLocaleString()}` : null].filter(Boolean).join(' · '), amount: n(p.amount) })
  }
  for (const b of (m.value.bill_payment_details || []) as any[]) lines.push({ label: 'Supplier bill payment', detail: b.vendor_name ?? b.payment_number, amount: n(b.amount) })
  for (const a of (m.value.amanat_disbursement_details || []) as any[]) lines.push({ label: 'Amanat withdrawal', detail: a.customer_name, amount: n(a.amount) })
  for (const e of (input.value.expenses || []) as any[]) {
    const account = props.expenseAccounts?.find((x) => x.id === e.account_id)?.name ?? accountName(e.account_id)
    lines.push({ label: 'Expense', detail: [account, e.description].filter(Boolean).join(' · '), amount: n(e.amount) })
  }
  if (n(m.value.partner_withdrawals)) lines.push({ label: 'Partner withdrawals', amount: n(m.value.partner_withdrawals) })
  if (n(m.value.employee_advances)) lines.push({ label: 'Salary advances', amount: n(m.value.employee_advances) })
  for (const p of (m.value.payroll_payout_details || []) as any[]) lines.push({ label: 'Salary paid', detail: p.employee_name, amount: n(p.amount) })
  for (const s of otherScreens.value.filter((x) => n(x.money_out) > 0)) lines.push({ label: sourceLabel(s), amount: n(s.money_out) })
  return withRemainder(lines, n(totals.value.money_out), 'Other money out')
})

const cash = computed(() => ({
  opening: n(totals.value.opening_cash ?? m.value.opening_cash),
  in: n(totals.value.money_in),
  out: n(totals.value.money_out),
  expected: n(totals.value.expected_closing ?? m.value.expected_closing),
  counted: n(totals.value.closing_cash ?? m.value.closing_cash),
  variance: n(totals.value.variance ?? m.value.variance),
}))
</script>

<template>
  <div class="space-y-6">
    <!-- Cash -->
    <section class="rounded-md border border-rule-default p-4">
      <h3 class="mb-3 font-semibold">Cash</h3>
      <dl class="grid gap-x-8 gap-y-1 text-sm tabular-nums sm:grid-cols-2 lg:grid-cols-3">
        <div class="flex justify-between"><dt>Total money in <span class="text-xs text-muted-foreground">(incl. opening)</span></dt><dd><MoneyText :amount="cash.opening + cash.in" :currency="currency" :fraction-digits="0" /></dd></div>
        <div class="flex justify-between"><dt>− Money out</dt><dd><MoneyText :amount="cash.out" :currency="currency" :fraction-digits="0" /></dd></div>
        <div class="flex justify-between font-medium"><dt>= Expected</dt><dd><MoneyText :amount="cash.expected" :currency="currency" :fraction-digits="0" /></dd></div>
        <div class="flex justify-between"><dt>Counted</dt><dd><MoneyText :amount="cash.counted" :currency="currency" :fraction-digits="0" /></dd></div>
        <div class="flex justify-between font-semibold">
          <dt>{{ Math.round(cash.variance) === 0 ? 'Balanced' : cash.variance < 0 ? 'Short' : 'Over' }}</dt>
          <dd :class="Math.round(cash.variance) !== 0 ? 'text-status-attention' : ''"><MoneyText :amount="Math.abs(cash.variance)" :currency="currency" :fraction-digits="0" /></dd>
        </div>
      </dl>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Fuel sales -->
      <section class="rounded-md border border-rule-default p-4">
        <h3 class="mb-3 font-semibold">Fuel sales</h3>
        <ul class="space-y-2 text-sm tabular-nums">
          <li v-for="(line, i) in salesLines" :key="'s' + i">
            <div class="flex justify-between gap-3">
              <span><span class="font-medium">{{ line.label }}</span> <span v-if="line.detail" class="text-muted-foreground">{{ line.detail }}</span></span>
              <MoneyText :amount="line.amount" :currency="currency" :fraction-digits="0" />
            </div>
            <ul v-if="line.sub?.length" class="mt-1 space-y-0.5 pl-4 text-xs text-muted-foreground">
              <li v-for="(sub, j) in line.sub" :key="j" class="flex justify-between gap-3">
                <span>{{ sub.label }} · {{ sub.detail }}</span>
                <MoneyText :amount="sub.amount" :currency="currency" :fraction-digits="0" />
              </li>
            </ul>
          </li>
          <li class="flex justify-between border-t border-rule-default pt-2 font-semibold">
            <span>Total sales</span>
            <MoneyText :amount="Number(totals.total_revenue || 0)" :currency="currency" :fraction-digits="0" />
          </li>
        </ul>
        <p v-if="pumpTests.length" class="mt-2 text-xs text-muted-foreground">
          Pump test (returned to tank, not a sale):
          <span v-for="(t, i) in pumpTests" :key="i">{{ i ? ' · ' : '' }}{{ litres(Number(t.liters)) }} L {{ t.fuel }}</span>
        </p>
      </section>

      <!-- Tanks -->
      <section class="rounded-md border border-rule-default p-4">
        <h3 class="mb-3 font-semibold">Tanks</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm tabular-nums">
            <thead class="text-xs text-muted-foreground">
              <tr>
                <th class="pb-1 text-left font-normal">Tank</th>
                <th class="pb-1 text-right font-normal">Opening</th>
                <th class="pb-1 text-right font-normal">+ Delivered</th>
                <th class="pb-1 text-right font-normal">− Sold</th>
                <th class="pb-1 text-right font-normal">= Expected</th>
                <th class="pb-1 text-right font-normal">Dip</th>
                <th class="pb-1 text-right font-normal">Variance</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in tankRows" :key="t.name" class="border-t border-rule-default">
                <td class="py-1">{{ t.name }}</td>
                <td class="py-1 text-right">{{ t.opening === undefined ? '—' : litres(t.opening) }}</td>
                <td class="py-1 text-right">{{ t.delivered === null ? '—' : litres(t.delivered) }}</td>
                <td class="py-1 text-right">{{ litres(t.sold) }}</td>
                <td class="py-1 text-right">{{ litres(t.expected) }}</td>
                <td class="py-1 text-right" :title="t.stick ? `Stick ${t.stick}` : undefined">{{ litres(t.dip) }}</td>
                <td class="py-1 text-right font-medium" :class="Math.abs(t.variance) >= 1 ? '' : 'text-muted-foreground'">
                  {{ t.variance > 0 ? '+' : '' }}{{ litres(t.variance) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="mt-2 text-xs text-muted-foreground">Litres · variance + gain, − loss</p>
      </section>

      <!-- Money in -->
      <section class="rounded-md border border-rule-default p-4">
        <h3 class="mb-3 font-semibold">Money in</h3>
        <ul class="space-y-1.5 text-sm tabular-nums">
          <li v-for="(line, i) in moneyIn" :key="'i' + i" class="flex justify-between gap-3">
            <span><span class="font-medium">{{ line.label }}</span> <span v-if="line.detail" class="text-muted-foreground">· {{ line.detail }}</span></span>
            <MoneyText :amount="line.amount" :currency="currency" :fraction-digits="0" />
          </li>
          <li class="flex justify-between border-t border-rule-default pt-2 font-semibold">
            <span>Total money in</span>
            <MoneyText :amount="cash.opening + cash.in" :currency="currency" :fraction-digits="0" />
          </li>
        </ul>
      </section>

      <!-- Money out -->
      <section class="rounded-md border border-rule-default p-4">
        <h3 class="mb-3 font-semibold">Money out</h3>
        <ul class="space-y-1.5 text-sm tabular-nums">
          <li v-for="(line, i) in moneyOut" :key="'o' + i" class="flex justify-between gap-3">
            <span>
              <Link v-if="line.href" :href="line.href" class="font-medium underline-offset-2 hover:underline">{{ line.label }}</Link>
              <span v-else class="font-medium">{{ line.label }}</span>
              <span v-if="line.detail" class="text-muted-foreground"> · {{ line.detail }}</span>
              <Button
                v-if="line.credit && canApplyDiscount"
                variant="link"
                size="sm"
                class="ml-1 h-auto p-0 text-xs"
                @click="emit('applyDiscount', line.credit)"
                >Apply discount</Button
              >
            </span>
            <MoneyText :amount="line.amount" :currency="currency" :fraction-digits="0" />
          </li>
          <li class="flex justify-between border-t border-rule-default pt-2 font-semibold">
            <span>Total money out</span>
            <MoneyText :amount="cash.out" :currency="currency" :fraction-digits="0" />
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
