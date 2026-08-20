<script setup lang="ts">
/**
 * Ledger design system — Tier 0 character test.
 *
 * This page renders the REAL shadcn components, not hand-written specimens.
 * That is the whole point: the risk being tested is whether the component
 * library can be made native to the ledger grammar, and a fake specimen
 * cannot answer that question.
 *
 * Tier 0 is the go/no-go gate. If something here cannot be made to look
 * native, we find out now rather than after 111 files have been migrated.
 *
 * Controls at the top of the page let you flip the skin off, so every
 * specimen can be compared against the current theme side by side in time.
 */
import { onBeforeUnmount, onMounted, ref, watchEffect } from 'vue'
import { Head } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Skeleton } from '@/components/ui/skeleton'
import { Separator } from '@/components/ui/separator'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import {
  Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter,
  DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel,
  DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import {
  Tooltip, TooltipContent, TooltipProvider, TooltipTrigger,
} from '@/components/ui/tooltip'
import { Toaster } from '@/components/ui/sonner'

import LedgerRegister from '@/components/LedgerRegister.vue'
import MoneyText from '@/components/MoneyText.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import TotalRow from '@/components/TotalRow.vue'
import DefinitionList from '@/components/DefinitionList.vue'
import Explain from '@/components/Explain.vue'
import { statusKeys } from '@/lib/status'
import type { MoneyDirection, MoneyTone } from '@/lib/money'

/* ── Page controls ─────────────────────────────────────────────────────── */
const skinOn = ref(true)
const dark = ref(false)
const density = ref<'comfortable' | 'compact' | 'print'>('comfortable')

/**
 * The skin goes on <html>, not on a wrapper div, and that is a Tier 0 finding
 * rather than a convenience. Dialog, popover, dropdown, sheet and toast all
 * portal to document.body — a scoped wrapper would leave every floating
 * surface unskinned, which is precisely where a retheme is most likely to
 * break character. Putting it on the root here also matches how it ships.
 */
let prior: { skin: string | null, density: string | null, dark: boolean } | null = null

onMounted(() => {
  const root = document.documentElement
  prior = {
    skin: root.getAttribute('data-skin'),
    density: root.getAttribute('data-density'),
    dark: root.classList.contains('dark'),
  }

  watchEffect(() => {
    if (skinOn.value) root.setAttribute('data-skin', 'ledger')
    else root.removeAttribute('data-skin')
    root.setAttribute('data-density', density.value)
    root.classList.toggle('dark', dark.value)
  })
})

// Leave the app exactly as it was found — the playground is a visitor here.
onBeforeUnmount(() => {
  if (!prior) return
  const root = document.documentElement
  if (prior.skin) root.setAttribute('data-skin', prior.skin)
  else root.removeAttribute('data-skin')
  if (prior.density) root.setAttribute('data-density', prior.density)
  else root.removeAttribute('data-density')
  root.classList.toggle('dark', prior.dark)
})

/* ── Specimen state ────────────────────────────────────────────────────── */
const checked = ref(true)
const switched = ref(true)
const radio = ref('cash')
const selected = ref('meezan')
const text = ref('Northstar Retail')

const DENSITIES = ['comfortable', 'compact', 'print'] as const

/**
 * The 20 statuses actually present in the codebase. Tone is deliberate and
 * restrained: most states are carried by the label, not by colour. Only
 * genuinely adverse states get critical tone.
 */
const STATUSES = [
  { key: 'draft', label: 'Draft', tone: 'neutral' },
  { key: 'sent', label: 'Sent', tone: 'info' },
  { key: 'pending', label: 'Pending', tone: 'neutral' },
  { key: 'submitted', label: 'Submitted', tone: 'info' },
  { key: 'approved', label: 'Approved', tone: 'success' },
  { key: 'confirmed', label: 'Confirmed', tone: 'success' },
  { key: 'received', label: 'Received', tone: 'success' },
  { key: 'paid', label: 'Paid', tone: 'success' },
  { key: 'partially_paid', label: 'Part paid', tone: 'attention' },
  { key: 'overdue', label: 'Overdue', tone: 'critical' },
  { key: 'rejected', label: 'Rejected', tone: 'critical' },
  { key: 'posted', label: 'Posted', tone: 'neutral' },
  { key: 'reversed', label: 'Reversed', tone: 'attention' },
  { key: 'reconciled', label: 'Reconciled', tone: 'success' },
  { key: 'void', label: 'Void', tone: 'muted' },
  { key: 'cancelled', label: 'Cancelled', tone: 'muted' },
  { key: 'archived', label: 'Archived', tone: 'muted' },
  { key: 'closed', label: 'Closed', tone: 'muted' },
  { key: 'locked', label: 'Locked', tone: 'neutral' },
  { key: 'active', label: 'Active', tone: 'success' },
] as const

/**
 * Register rows. Note the outflows are NOT red — direction is carried by the
 * column and the minus sign. Red is reserved for adverse states only.
 */
const ROWS = [
  { date: '18 AUG', what: 'Northstar Retail', note: 'paid invoice INV-1018', ref: 'SL-204', in: 85000, out: null, detail: 'Settled INV-1018 in full. Bank transfer, cleared same day.' },
  { date: '17 AUG', what: 'Pak Logistics', note: 'freight for August', ref: 'PL-118', in: null, out: 24500, detail: null },
  { date: '16 AUG', what: 'Zamzam Traders', note: 'part payment, INV-1015', ref: 'SL-197', in: 60000, out: null, detail: null },
  { date: '15 AUG', what: 'K-Electric', note: 'July electricity', ref: 'PL-121', in: null, out: 31200, detail: null },
  { date: '14 AUG', what: 'Salaries', note: 'August, 11 staff', ref: 'PL-109', in: null, out: 186000, detail: null },
]

/*
 * The register's own column vocabulary, declared the way a real page declares
 * it. `in` and `out` are two columns of one register and both are ink -- the
 * headings say which way the money went, so colour would only repeat them.
 */
const REGISTER_COLUMNS = [
  { key: 'date', label: 'Date', kind: 'date' as const },
  { key: 'what', label: 'What happened', kind: 'text' as const },
  { key: 'ref', label: 'Reference', kind: 'ref' as const },
  { key: 'in', label: 'Money in', kind: 'in' as const },
  { key: 'out', label: 'Money out', kind: 'out' as const },
]

/* Which row is opened. One row carries a detail so the slot can be seen. */
const openRef = ref<string | null>('SL-204')

const fmt = (n: number) => new Intl.NumberFormat('en-PK').format(n)

/* Text-expansion and RTL checks. The ledger composition is unusually
   dependent on precise text lengths, so these are specimens, not asides. */
/* Rule 3 argued in one column. Every row below the first is an ordinary
   movement of money, and not one of them is red. */
const MONEY_CASES: { label: string; amount: number; direction: MoneyDirection; tone: MoneyTone }[] = [
  { label: 'Sale recorded', amount: 1284500, direction: 'inflow', tone: 'default' },
  { label: 'Rent paid', amount: 450000, direction: 'outflow', tone: 'default' },
  { label: 'Salaries paid', amount: 1875000, direction: 'outflow', tone: 'default' },
  { label: 'Refund issued', amount: 32000, direction: 'outflow', tone: 'default' },
  { label: 'Next month, projected', amount: 1400000, direction: 'inflow', tone: 'estimated' },
  { label: 'Invoice 41 days late', amount: 623925, direction: 'inflow', tone: 'overdue' },
  { label: 'Voided receipt', amount: 12000, direction: 'inflow', tone: 'muted' },
]

const LONG_LABEL = 'Provision for doubtful debts and expected credit losses'
const URDU_LABEL = 'وصول شدہ رقم'
</script>

<template>
  <Head title="Ledger design system" />

  <div class="min-h-screen bg-background text-foreground">
    <Toaster position="bottom-right" />

    <!-- ── Controls ──────────────────────────────────────────────────── -->
    <header class="sticky top-0 z-50 border-b-2 border-rule-emphasis bg-background">
      <div class="mx-auto flex max-w-[1400px] flex-wrap items-center gap-4 px-6 py-3">
        <div>
          <h1 class="font-display text-lg font-bold leading-none">Ledger design system</h1>
          <p class="mt-1 text-xs text-text-metadata">Tier 0 — real components, full state matrix</p>
        </div>

        <div class="ml-auto flex flex-wrap items-center gap-2">
          <Button size="sm" :variant="skinOn ? 'default' : 'outline'" @click="skinOn = !skinOn">
            Skin {{ skinOn ? 'on' : 'off' }}
          </Button>
          <Button size="sm" variant="outline" @click="dark = !dark">
            {{ dark ? 'Dark' : 'Light' }}
          </Button>
          <div class="flex border border-border">
            <button
              v-for="d in DENSITIES"
              :key="d"
              class="px-3 py-1.5 text-xs font-semibold capitalize transition-colors"
              :class="density === d ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
              @click="density = d"
            >
              {{ d }}
            </button>
          </div>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-[1400px] px-6 pb-24">
      <!-- ── Tokens ──────────────────────────────────────────────────── -->
      <section class="pt-10">
        <h2 class="spec-h">Tokens</h2>
        <p class="spec-note">
          Layer 1 is semantic and ours. Layer 2 is shadcn's, and it references layer 1.
          Accounting meaning never lives in the component library's vocabulary.
        </p>

        <div class="mt-5 grid gap-px border border-border bg-border sm:grid-cols-2 lg:grid-cols-4">
          <div v-for="s in [
            { n: 'surface-canvas', c: 'bg-surface-canvas' },
            { n: 'surface-raised', c: 'bg-surface-raised' },
            { n: 'surface-sunken', c: 'bg-surface-sunken' },
            { n: 'surface-band', c: 'bg-surface-band' },
            { n: 'rule-subtle', c: 'bg-rule-subtle' },
            { n: 'rule-default', c: 'bg-rule-default' },
            { n: 'rule-emphasis', c: 'bg-rule-emphasis' },
            { n: 'focus-ring', c: 'bg-focus-ring' },
            { n: 'status-info', c: 'bg-status-info' },
            { n: 'status-attention', c: 'bg-status-attention' },
            { n: 'status-critical', c: 'bg-status-critical' },
            { n: 'status-success', c: 'bg-status-success' },
            { n: 'amount-inflow', c: 'bg-amount-inflow' },
            { n: 'amount-outflow', c: 'bg-amount-outflow' },
            { n: 'amount-estimated', c: 'bg-amount-estimated' },
            { n: 'amount-overdue', c: 'bg-amount-overdue' },
          ]" :key="s.n" class="bg-card p-3">
            <div class="h-10 border border-border" :class="s.c" />
            <div class="mt-2 font-mono text-[11px] text-text-metadata">{{ s.n }}</div>
          </div>
        </div>

        <Alert class="mt-5">
          <AlertTitle>Direction is not severity</AlertTitle>
          <AlertDescription>
            <code class="font-mono text-xs">amount-inflow</code> and
            <code class="font-mono text-xs">amount-outflow</code> are the same ink, on purpose.
            Paying a bill is not an error. Red belongs to
            <code class="font-mono text-xs">status-critical</code> and
            <code class="font-mono text-xs">amount-overdue</code>, and nothing else.
          </AlertDescription>
        </Alert>
      </section>

      <!-- ── Type ────────────────────────────────────────────────────── -->
      <section class="spec-section">
        <h2 class="spec-h">Type roles</h2>
        <p class="spec-note">
          Three roles, strictly assigned. The serif never appears on a button, a field label,
          or a table cell — that is the line between confident and costume.
        </p>

        <div class="mt-5 space-y-5 border border-border bg-card p-6">
          <div>
            <div class="spec-cap">Display — page titles and major conclusions only</div>
            <p class="font-display text-4xl font-bold tracking-tight">Where you stand</p>
          </div>
          <Separator />
          <div>
            <div class="spec-cap">Sans — navigation, controls, forms, table content, body copy</div>
            <p class="text-base">
              This is the figure to spend against, not your bank balance. It already sets aside
              what you owe and counts only what customers are likely to pay.
            </p>
          </div>
          <Separator />
          <div>
            <div class="spec-cap">Mono — tables, references, IDs, metadata</div>
            <p class="font-mono text-sm">INV-1018 · SL-204 · 18 AUG 2026 · 08:52</p>
          </div>
          <Separator />
          <div>
            <div class="spec-cap">Figures — tabular everywhere alignment matters</div>
            <div class="flex flex-wrap items-baseline gap-8">
              <div>
                <div class="text-[11px] text-text-metadata">Conclusion — sans, tabular</div>
                <div class="amount text-4xl font-semibold tracking-tight">1,270,567</div>
              </div>
              <div>
                <div class="text-[11px] text-text-metadata">Register — mono, tabular</div>
                <div class="amount font-mono text-xl">1,270,567</div>
              </div>
              <div>
                <div class="text-[11px] text-text-metadata">Alignment proof</div>
                <div class="amount font-mono text-sm leading-relaxed text-right">
                  1,234,567<br>&minus;&nbsp;&nbsp;&nbsp;89,000<br>+&nbsp;&nbsp;125,000
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Buttons ─────────────────────────────────────────────────── -->
      <section class="spec-section">
        <h2 class="spec-h">Actions</h2>
        <p class="spec-note">A strict hierarchy. Controls printed onto the interface, not floating candy.</p>

        <div class="mt-5 border border-border bg-card">
          <div class="grid grid-cols-[130px_1fr] items-center gap-4 border-b border-rule-subtle p-4">
            <div class="spec-cap !mb-0">State</div>
            <div class="flex flex-wrap gap-2 text-xs text-text-metadata">
              default · secondary · outline · ghost · link · destructive
            </div>
          </div>
          <div
            v-for="state in ['default', 'disabled', 'loading']"
            :key="state"
            class="grid grid-cols-[130px_1fr] items-center gap-4 border-b border-rule-subtle p-4 last:border-0"
          >
            <div class="font-mono text-[11px] uppercase tracking-wider text-text-metadata">{{ state }}</div>
            <div class="flex flex-wrap items-center gap-2">
              <Button
                v-for="v in (['default', 'secondary', 'outline', 'ghost', 'link', 'destructive'] as const)"
                :key="v"
                :variant="v"
                :disabled="state !== 'default'"
              >
                <span v-if="state === 'loading'" class="spec-spinner" aria-hidden="true" />
                {{ state === 'loading' ? 'Saving' : 'Record payment' }}
              </Button>
            </div>
          </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3 border border-border bg-card p-4">
          <span class="spec-cap !mb-0">Sizes</span>
          <Button size="sm">Small</Button>
          <Button>Default</Button>
          <Button size="lg">Large</Button>
          <Button size="icon" aria-label="More actions">···</Button>
        </div>
      </section>

      <!-- ── Fields ──────────────────────────────────────────────────── -->
      <section class="spec-section">
        <h2 class="spec-h">Fields</h2>
        <p class="spec-note">
          Financial forms need visibly distinct grammar for values the user owns versus values
          the system owns. Disabled and read-only are mandatory specimens — an accounting app is
          full of posted, immutable values, and that is exactly where a visual system fails.
        </p>

        <div class="mt-5 grid gap-6 border border-border bg-card p-6 lg:grid-cols-2">
          <div class="space-y-2">
            <Label for="f1">Customer</Label>
            <Input id="f1" v-model="text" />
            <p class="spec-help">User-entered. Editable, and it looks it.</p>
          </div>

          <div class="space-y-2">
            <Label for="f2">Amount</Label>
            <Input id="f2" model-value="45,000" class="amount font-mono" />
            <p class="spec-help">Tabular, non-wrapping, right-alignable.</p>
          </div>

          <div class="space-y-2">
            <Label for="f3">Sales tax at 18%</Label>
            <Input id="f3" model-value="8,100" readonly class="amount font-mono spec-calculated" />
            <p class="spec-help">System-calculated. Sunken, no border emphasis, not focusable as an edit.</p>
          </div>

          <div class="space-y-2">
            <Label for="f4">Journal reference</Label>
            <Input id="f4" model-value="SL-204" disabled class="font-mono" />
            <p class="spec-help">Posted and immutable. Disabled, and clearly inert.</p>
          </div>

          <div class="space-y-2">
            <Label for="f5">Expected collection</Label>
            <Input id="f5" model-value="125,000" class="amount font-mono spec-estimated" />
            <p class="spec-help">Estimated. Muted ink signals a figure that is not yet fact.</p>
          </div>

          <div class="space-y-2">
            <Label for="f6" class="spec-invalid-label">Invoice date</Label>
            <Input id="f6" model-value="32 August 2026" aria-invalid="true" />
            <p class="spec-error">
              <span aria-hidden="true">▲</span>
              That date does not exist. Use a date in the open period.
            </p>
          </div>

          <div class="space-y-2 lg:col-span-2">
            <Label for="f7">Notes</Label>
            <Textarea id="f7" rows="3" placeholder="What was this for?" />
          </div>
        </div>

        <div class="mt-4 grid gap-6 border border-border bg-card p-6 lg:grid-cols-3">
          <div class="space-y-3">
            <div class="spec-cap">Selection</div>
            <label class="flex items-center gap-2 text-sm">
              <Checkbox v-model="checked" /> Include drafts
            </label>
            <label class="flex items-center gap-2 text-sm text-muted-foreground">
              <Checkbox disabled /> Locked period (disabled)
            </label>
            <label class="flex items-center gap-2 text-sm">
              <Switch v-model="switched" /> Send a copy to the customer
            </label>
          </div>

          <div class="space-y-3">
            <div class="spec-cap">Radio group</div>
            <RadioGroup v-model="radio" class="space-y-2">
              <label class="flex items-center gap-2 text-sm"><RadioGroupItem value="cash" /> Cash</label>
              <label class="flex items-center gap-2 text-sm"><RadioGroupItem value="bank" /> Bank transfer</label>
              <label class="flex items-center gap-2 text-sm"><RadioGroupItem value="cheque" /> Cheque</label>
            </RadioGroup>
          </div>

          <div class="space-y-3">
            <div class="spec-cap">Select</div>
            <Select v-model="selected">
              <SelectTrigger><SelectValue placeholder="Choose an account" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="meezan">Meezan Bank — current</SelectItem>
                <SelectItem value="hbl">HBL — savings</SelectItem>
                <SelectItem value="cash">Cash in hand</SelectItem>
              </SelectContent>
            </Select>
            <div class="spec-cap pt-2">Tabs</div>
            <Tabs default-value="all">
              <TabsList>
                <TabsTrigger value="all">All</TabsTrigger>
                <TabsTrigger value="unpaid">Unpaid</TabsTrigger>
                <TabsTrigger value="draft">Draft</TabsTrigger>
              </TabsList>
              <TabsContent value="all" class="pt-2 text-sm text-muted-foreground">64 entries</TabsContent>
              <TabsContent value="unpaid" class="pt-2 text-sm text-muted-foreground">7 entries</TabsContent>
              <TabsContent value="draft" class="pt-2 text-sm text-muted-foreground">2 entries</TabsContent>
            </Tabs>
          </div>
        </div>
      </section>

      <!-- ── Register ────────────────────────────────────────────────── -->
      <section class="spec-section">
        <h2 class="spec-h">The register</h2>
        <p class="spec-note">
          Green-bar banding, struck totals, and money in / money out as two columns of one
          register rather than two cards. Change the density control above — this table is the
          reason the three contracts exist.
        </p>

        <div class="mt-5 border border-border bg-card">
          <div class="flex items-baseline justify-between gap-4 border-b-2 border-rule-emphasis px-5 py-4">
            <h3 class="font-display text-xl font-bold">What's been happening</h3>
            <span class="spec-cap !mb-0">August 2026</span>
          </div>

          <LedgerRegister
            :data="ROWS"
            :columns="REGISTER_COLUMNS"
            key-field="ref"
            :density="density"
            :expanded="(row) => openRef === row.ref"
            :totals="{}"
            totals-label="Total this month"
            clickable
            @row-click="(row) => (openRef = openRef === row.ref ? null : row.ref)"
          >
            <template #cell-what="{ row }">
              <span class="font-semibold">{{ row.what }}</span>
              <span class="text-text-secondary"> · {{ row.note }}</span>
            </template>

            <template #cell-in="{ row }">
              <MoneyText v-if="row.in" :amount="row.in" currency="PKR" direction="inflow" />
            </template>

            <template #cell-out="{ row }">
              <MoneyText v-if="row.out" :amount="row.out" currency="PKR" direction="outflow" />
            </template>

            <template #row-detail="{ row }">
              <p v-if="row.detail" class="text-sm text-text-secondary">{{ row.detail }}</p>
              <p v-else class="text-sm text-text-secondary">Nothing further recorded against this entry.</p>
            </template>

            <template #total-in><MoneyText :amount="145000" currency="PKR" direction="inflow" /></template>
            <template #total-out><MoneyText :amount="241700" currency="PKR" direction="outflow" /></template>
          </LedgerRegister>
        </div>

        <p class="spec-note mt-4">
          Click a row to open it. The detail is a slot on the register rather than a second
          table underneath one, which is how a page used to end up with its own alignment and
          its own banding beneath a row it did not own.
        </p>
      </section>

      <!-- ── Financial primitives (Tier 1) ───────────────────────────── -->
      <section class="spec-section">
        <h2 class="spec-h">Financial primitives</h2>
        <p class="spec-note">
          The components that carry accounting meaning. Each exists because the meaning could not
          survive being expressed as a utility class on a span.
        </p>

        <div class="mt-5 grid gap-px border border-border bg-border lg:grid-cols-2">
          <!-- Direction vs severity — the correction to the mockup. -->
          <div class="space-y-4 bg-card p-5">
            <div class="spec-cap">Direction is not severity</div>
            <div class="space-y-2">
              <div v-for="m in MONEY_CASES" :key="m.label" class="flex items-baseline justify-between gap-4">
                <span class="text-sm">{{ m.label }}</span>
                <MoneyText
                  :amount="m.amount"
                  currency="PKR"
                  locale="en-PK"
                  :direction="m.direction"
                  :tone="m.tone"
                />
              </div>
            </div>
            <p class="spec-help">
              Rent leaving the account is ink with a minus sign. Only the overdue receivable is red,
              because only it is adverse.
            </p>
          </div>

          <!-- Edge cases, which is where money components usually fail. -->
          <div class="space-y-4 bg-card p-5">
            <div class="spec-cap">Zero, nothing, and other currencies</div>
            <DefinitionList>
              <dt class="spec-dt">Zero</dt>
              <dd><MoneyText :amount="0" currency="PKR" locale="en-PK" /></dd>
              <dt class="spec-dt">Zero, dashed for a register</dt>
              <dd><MoneyText :amount="0" currency="PKR" locale="en-PK" dash-zero /></dd>
              <dt class="spec-dt">Null</dt>
              <dd><MoneyText :amount="null" currency="PKR" locale="en-PK" /></dd>
              <dt class="spec-dt">Unparseable</dt>
              <dd><MoneyText amount="n/a" currency="PKR" locale="en-PK" /></dd>
              <dt class="spec-dt">Foreign, with base equivalent</dt>
              <dd>
                <MoneyText
                  :amount="1250"
                  currency="USD"
                  locale="en-US"
                  :base-amount="348750"
                  base-currency="PKR"
                />
              </dd>
              <dt class="spec-dt">Report convention</dt>
              <dd><MoneyText :amount="-45000" currency="PKR" locale="en-PK" negative="parens" /></dd>
              <dt class="spec-dt">Whole rupees</dt>
              <dd><MoneyText :amount="1284500" currency="PKR" locale="en-PK" :fraction-digits="0" /></dd>
            </DefinitionList>
          </div>

          <!-- The struck balance. -->
          <div class="space-y-3 bg-card p-5">
            <div class="spec-cap">Totals — the rules carry the weight</div>
            <div>
              <TotalRow level="line" label="Goods and services" :amount="1284500" currency="PKR" locale="en-PK" />
              <TotalRow level="line" label="Freight" :amount="18000" currency="PKR" locale="en-PK" />
              <TotalRow level="subtotal" label="Subtotal" :amount="1302500" currency="PKR" locale="en-PK" />
              <TotalRow level="line" label="Sales tax" note="at 17%" :amount="221425" currency="PKR" locale="en-PK" />
              <TotalRow level="total" label="Invoice total" :amount="1523925" currency="PKR" locale="en-PK" />
              <TotalRow level="line" label="Paid" direction="outflow" :amount="900000" currency="PKR" locale="en-PK" />
              <TotalRow level="grand" label="Still owed" :amount="623925" currency="PKR" locale="en-PK" tone="overdue" />
            </div>
            <p class="spec-help">
              Ruled above, double-ruled below. The rules alone say which line is the answer, so
              the lines above it need no grey and no smaller type to get out of its way.
            </p>
          </div>

          <!-- Help replaces the mode toggle. -->
          <div class="space-y-4 bg-card p-5">
            <div class="spec-cap">Explain — what replaced the mode toggle</div>
            <p class="text-sm leading-relaxed">
              Your <Explain term="cashPosition" /> is 4.2m, but your <Explain term="profit" /> for the
              month reads 6.8m. Entries reach the <Explain term="journal" /> once they are
              <Explain term="posted" />, and the period is <Explain term="locked" /> after that.
            </p>
            <p class="spec-help">
              Keyboard-reachable, not hover-only. The dotted underline is the affordance; a column of
              question-mark icons would read as an apology for the form.
            </p>

            <div class="pt-2">
              <div class="spec-cap mb-2">On a status</div>
              <div class="flex flex-wrap gap-2">
                <StatusBadge status="posted" explain />
                <StatusBadge status="reconciled" explain />
                <StatusBadge status="reversed" explain />
              </div>
            </div>
          </div>

          <!-- StatusBadge across every real state. -->
          <div class="space-y-3 bg-card p-5 lg:col-span-2">
            <div class="spec-cap">StatusBadge — all twenty states</div>
            <div class="flex flex-wrap gap-2">
              <StatusBadge v-for="k in statusKeys" :key="k" :status="k" />
            </div>
            <div class="flex flex-wrap items-center gap-2 pt-2">
              <span class="spec-help">Off the wire, unnormalised, and unknown:</span>
              <StatusBadge status="PartiallyPaid" />
              <StatusBadge status="partially-paid" />
              <StatusBadge status="awaiting_dispatch" />
              <StatusBadge :status="null" />
            </div>
            <p class="spec-help">
              An unrecognised state is titlecased and shown neutral. Hiding a state the server
              considers real is worse than showing one this file has not caught up with.
            </p>
          </div>

          <!-- DefinitionList. -->
          <div class="space-y-3 bg-card p-5">
            <div class="spec-cap">DefinitionList — columns</div>
            <DefinitionList
              :items="[
                { term: 'Invoice number', value: 'INV-2026-00841' },
                { term: 'Customer', value: 'Northstar Retail (Pvt) Ltd' },
                { term: 'Financial year', value: '2025-26', explain: 'fiscalYear' },
                { term: 'Reference', value: null },
                { term: 'Recorded by', value: 'Yasir Khan' },
              ]"
            />
          </div>

          <div class="space-y-3 bg-card p-5">
            <div class="spec-cap">DefinitionList — stacked, long and RTL</div>
            <DefinitionList
              layout="stacked"
              :items="[
                { term: LONG_LABEL, value: 'Reviewed and carried forward to the next period' },
                { term: URDU_LABEL, value: '1,284,500' },
              ]"
            />
          </div>
        </div>
      </section>

      <!-- ── Status ──────────────────────────────────────────────────── -->
      <section class="spec-section">
        <h2 class="spec-h">Status</h2>
        <p class="spec-note">
          All twenty states found in the codebase. Most are carried by the label and a rule,
          not by colour — colour only reinforces, and every state stays legible in greyscale.
        </p>

        <div class="mt-5 flex flex-wrap gap-2 border border-border bg-card p-5">
          <span v-for="s in STATUSES" :key="s.key" class="spec-status" :data-tone="s.tone">
            {{ s.label }}
          </span>
        </div>

        <div class="mt-4 flex flex-wrap gap-2 border border-border bg-card p-5">
          <span class="spec-cap !mb-0 w-full">shadcn Badge, for comparison</span>
          <Badge>Default</Badge>
          <Badge variant="secondary">Secondary</Badge>
          <Badge variant="outline">Outline</Badge>
          <Badge variant="destructive">Destructive</Badge>
          <Badge variant="success">Success</Badge>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
          <Alert>
            <AlertTitle>Sales tax is due 25 August</AlertTitle>
            <AlertDescription>The draft return for 1–31 July is ready to review.</AlertDescription>
          </Alert>
          <Alert variant="destructive">
            <AlertTitle>This period is locked</AlertTitle>
            <AlertDescription>Reopen the period before posting entries dated in July.</AlertDescription>
          </Alert>
        </div>
      </section>

      <!-- ── Overlays ────────────────────────────────────────────────── -->
      <section class="spec-section">
        <h2 class="spec-h">Overlays</h2>
        <p class="spec-note">
          Where design systems break character. A ledger page with a generic rounded dialog
          floating over it ruins the illusion instantly — so these are the ones that matter most.
          Shadow is permitted here and nowhere else, because these genuinely float.
        </p>

        <div class="mt-5 flex flex-wrap gap-3 border border-border bg-card p-5">
          <Dialog>
            <DialogTrigger as-child><Button variant="outline">Dialog</Button></DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle class="font-display text-xl">Void this invoice?</DialogTitle>
                <DialogDescription>
                  INV-1018 has a payment of 85,000 recorded against it. Voiding the invoice
                  leaves that payment unallocated.
                </DialogDescription>
              </DialogHeader>
              <DialogFooter>
                <DialogClose as-child>
                  <Button variant="ghost">Keep it</Button>
                </DialogClose>
                <Button variant="destructive">Void invoice</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          <DropdownMenu>
            <DropdownMenuTrigger as-child><Button variant="outline">Dropdown</Button></DropdownMenuTrigger>
            <DropdownMenuContent align="start">
              <DropdownMenuLabel>Position</DropdownMenuLabel>
              <DropdownMenuItem>Profit and loss</DropdownMenuItem>
              <DropdownMenuItem>Balance sheet</DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuLabel>Who owes what</DropdownMenuLabel>
              <DropdownMenuItem>Aged receivables</DropdownMenuItem>
              <DropdownMenuItem>Aged payables</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <Popover>
            <PopoverTrigger as-child><Button variant="outline">Popover</Button></PopoverTrigger>
            <PopoverContent class="w-80">
              <p class="font-display text-base font-semibold">Free to commit</p>
              <p class="mt-2 text-sm text-muted-foreground">
                Your bank balance minus what you owe in the next 14 days, plus invoices likely
                to clear. This is the figure to spend against.
              </p>
            </PopoverContent>
          </Popover>

          <Sheet>
            <SheetTrigger as-child><Button variant="outline">Drawer</Button></SheetTrigger>
            <SheetContent>
              <SheetHeader>
                <SheetTitle class="font-display text-xl">INV-1018</SheetTitle>
                <SheetDescription>Northstar Retail · issued 3 August</SheetDescription>
              </SheetHeader>
            </SheetContent>
          </Sheet>

          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger as-child><Button variant="outline">Tooltip</Button></TooltipTrigger>
              <TooltipContent>Posted entries cannot be edited</TooltipContent>
            </Tooltip>
          </TooltipProvider>

          <Button variant="outline" @click="toast.success('Payment recorded', { description: '85,000 from Northstar Retail' })">
            Toast
          </Button>
        </div>
      </section>

      <!-- ── Empty, loading, error ───────────────────────────────────── -->
      <section class="spec-section">
        <h2 class="spec-h">Empty, loading, error</h2>
        <p class="spec-note">
          The design is defined as much by its failure states as by its resting screenshot.
          An empty screen is an invitation to act; an error says what happened and how to fix it.
        </p>

        <div class="mt-5 grid gap-4 lg:grid-cols-3">
          <div class="border border-border bg-card p-8 text-center">
            <p class="font-display text-lg font-semibold">No entries yet</p>
            <p class="mx-auto mt-2 max-w-[30ch] text-sm text-muted-foreground">
              Once you record a payment or import a statement, it shows up here.
            </p>
            <Button class="mt-4" size="sm">Import a statement</Button>
          </div>

          <div class="space-y-3 border border-border bg-card p-5">
            <div class="spec-cap">Loading</div>
            <Skeleton class="h-4 w-3/4" />
            <Skeleton class="h-4 w-full" />
            <Skeleton class="h-4 w-5/6" />
            <Skeleton class="h-4 w-2/3" />
          </div>

          <div class="border border-border bg-card p-5">
            <div class="spec-cap">Error</div>
            <p class="font-display text-lg font-semibold">That statement didn't import</p>
            <p class="mt-2 text-sm text-muted-foreground">
              Rows 12 and 19 have no date. Add a date to those rows and upload the file again.
            </p>
            <Button class="mt-4" size="sm" variant="outline">Try again</Button>
          </div>
        </div>
      </section>

      <!-- ── Robustness ──────────────────────────────────────────────── -->
      <section class="spec-section">
        <h2 class="spec-h">Robustness</h2>
        <p class="spec-note">
          This composition depends on precise text lengths, so long labels, text expansion,
          RTL, and zero/null amounts are specimens rather than afterthoughts.
        </p>

        <div class="mt-5 grid gap-px border border-border bg-border md:grid-cols-2">
          <div class="space-y-2 bg-card p-5">
            <div class="spec-cap">Long label, narrow column</div>
            <div class="grid grid-cols-[1fr_auto] items-baseline gap-4 border-b border-rule-subtle py-2">
              <span class="text-sm">{{ LONG_LABEL }}</span>
              <span class="amount font-mono text-sm">−1,284,000</span>
            </div>
            <div class="grid grid-cols-[1fr_auto] items-baseline gap-4 py-2">
              <span class="text-sm">Cash</span>
              <span class="amount font-mono text-sm">12,999,999,999</span>
            </div>
          </div>

          <div class="space-y-2 bg-card p-5">
            <div class="spec-cap">RTL — Urdu</div>
            <div dir="rtl" class="space-y-2">
              <div class="grid grid-cols-[1fr_auto] items-baseline gap-4 border-b border-rule-subtle py-2">
                <span class="text-sm">{{ URDU_LABEL }}</span>
                <span class="amount font-mono text-sm">85,000</span>
              </div>
              <Input :model-value="URDU_LABEL" />
            </div>
          </div>

          <div class="space-y-2 bg-card p-5">
            <div class="spec-cap">Zero, null, negative</div>
            <div class="amount space-y-1 text-right font-mono text-sm">
              <div>0</div>
              <div class="text-text-metadata">—</div>
              <div>−45,000</div>
              <div class="text-amount-overdue">−45,000 <span class="text-[11px]">overdue</span></div>
              <div class="text-amount-estimated">125,000 <span class="text-[11px]">estimated</span></div>
            </div>
          </div>

          <div class="space-y-2 bg-card p-5">
            <div class="spec-cap">Focus ring — tab through these</div>
            <div class="flex flex-wrap gap-2">
              <Button variant="outline" size="sm">One</Button>
              <Input class="w-32" model-value="Two" />
              <Button size="sm">Three</Button>
            </div>
            <p class="spec-help">Every control must show a visible ring, not just a colour shift.</p>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
/* Slot-driven DefinitionList specimen: scoped styles do not cross into a
   child component, so the page restates the term style for its own <dt>. */
.spec-dt {
  font-size: 12px;
  font-weight: 500;
  color: var(--text-metadata);
}

.spec-section {
  padding-top: var(--space-6, 40px);
}

.spec-h {
  font-family: var(--font-display);
  font-size: 26px;
  font-weight: 700;
  letter-spacing: -0.02em;
  padding-bottom: var(--space-2, 8px);
  border-bottom: 2.5px solid var(--rule-emphasis, currentColor);
}

.spec-note {
  margin-top: var(--space-3, 12px);
  max-width: 78ch;
  font-size: 13.5px;
  line-height: 1.6;
  color: var(--text-secondary);
}

.spec-cap {
  margin-bottom: var(--space-2, 8px);
  font-family: var(--font-mono);
  font-size: 11px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--text-metadata);
}

.spec-help {
  font-size: 12px;
  color: var(--text-metadata);
}

.spec-error {
  display: flex;
  align-items: baseline;
  gap: 6px;
  font-size: 12px;
  font-weight: 500;
  color: var(--status-critical);
}

.spec-invalid-label {
  color: var(--status-critical);
}

/* System-owned values read as sunken and settled, not as fields awaiting input. */
.spec-calculated {
  background: var(--surface-sunken);
  border-color: var(--rule-subtle);
  color: var(--text-secondary);
}

/* Not yet fact. */
.spec-estimated {
  color: var(--amount-estimated);
  font-style: italic;
}

.spec-spinner {
  width: 12px;
  height: 12px;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: spec-spin 0.7s linear infinite;
}

@keyframes spec-spin {
  to { transform: rotate(360deg); }
}

/* ── Status chips ──────────────────────────────────────────────────────
   Tone is a rule and a weight before it is a colour. Strip the colour and
   every one of these is still distinguishable — that is the requirement. */
.spec-status {
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  border: 1px solid var(--rule-default);
  border-left-width: 3px;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.spec-status[data-tone='info']      { border-left-color: var(--status-info); }
.spec-status[data-tone='success']   { border-left-color: var(--status-success); }
.spec-status[data-tone='attention'] { border-left-color: var(--status-attention); }
.spec-status[data-tone='critical']  { border-left-color: var(--status-critical); color: var(--status-critical); }
.spec-status[data-tone='neutral']   { border-left-color: var(--rule-emphasis); }
.spec-status[data-tone='muted']     { border-left-color: var(--rule-default); color: var(--text-metadata); text-decoration: line-through; }

/* ── Register ──────────────────────────────────────────────────────────
   The banding, the density contract, the mono headers and the double-ruled
   total used to be restated here, in a scoped block, against a table this
   page had hand-written. They are the component's decisions and they belong
   in the component -- a spec page that reimplements the thing it documents
   is a spec page that will drift away from it.                             */

@media (prefers-reduced-motion: reduce) {
  .spec-spinner { animation: none; }
}
</style>
