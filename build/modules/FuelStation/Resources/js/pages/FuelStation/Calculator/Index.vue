<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import Hint from '@/components/Hint.vue'
import MoneyText from '@/components/MoneyText.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import InputError from '@/components/InputError.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import type { BreadcrumbItem } from '@/types'
import { Calculator, Plus, X } from 'lucide-vue-next'
import {
  astFromTokens, isoDate, nextKey, tokensFromAst, whenLabel,
  type FormulaNode, type Op, type Token, type ValueNode, type When,
} from './formula'

interface Metric {
  key: string
  label: string
  group: string
  unit: string
  collections: string[]
  takes_day: boolean
}
interface Option { id: string; name: string }
interface Options {
  products: (Option & { is_fuel: boolean })[]
  expense_accounts: Option[]
  income_accounts: Option[]
  customers: Option[]
  channels: Option[]
}
interface Saved { id: string; name: string; formula: FormulaNode; is_shared: boolean; mine: boolean; owner: string | null }
interface Part { label: string; value: number | null; unit: string | null; source_href: string | null; note: string | null; from: string; to: string }
interface Result { result: number | null; unit: string | null; message: string | null; warnings: string[]; parts: Part[] }

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  result: Result | null
  metrics: Metric[]
  options: Options
  saved: Saved[]
  example: FormulaNode
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Reports', href: `/${props.company.slug}/fuel/reports/performance` },
  { title: 'Calculator', href: `/${props.company.slug}/fuel/calculator` },
])
const base = computed(() => `/${props.company.slug}/fuel/calculator`)

// ---- the formula, as chips ----------------------------------------------------------------------

const tokens = ref<Token[]>(tokensFromAst(props.example))
const loaded = ref<Saved | null>(null)
const localError = ref('')
const serverError = ref('')
const busy = ref(false)

const metricOf = (key: string) => props.metrics.find((m) => m.key === key)
const groups = computed(() => {
  const out: { label: string; metrics: Metric[] }[] = []
  for (const metric of props.metrics) {
    let group = out.find((g) => g.label === metric.group)
    if (!group) out.push((group = { label: metric.group, metrics: [] }))
    group.metrics.push(metric)
  }
  return out
})

const nameOf = (list: Option[], id?: string) => list.find((o) => o.id === id)?.name ?? 'Unknown'
const collectionName = (node: ValueNode): string => {
  const c = node.collection
  switch (c.type) {
    case 'product': case 'fuel': return nameOf(props.options.products, c.id)
    case 'all_fuels': return 'All fuels'
    case 'all_products': return 'All products'
    case 'account': return nameOf([...props.options.expense_accounts, ...props.options.income_accounts], c.id)
    case 'customer': return nameOf(props.options.customers, c.id)
    case 'channel': return nameOf(props.options.channels, c.id)
    default: return ''
  }
}
const valueLabel = (node: ValueNode) =>
  [metricOf(node.metric)?.label ?? node.metric, collectionName(node), whenLabel(node.when)].filter(Boolean).join(' · ')

const opSymbol: Record<Op, string> = { '+': '+', '-': '−', '*': '×', '/': '÷' }
const operators: Op[] = ['+', '-', '*', '/']

const addOp = (op: Op) => tokens.value.push({ key: nextKey(), kind: 'op', op })
const addParen = (paren: '(' | ')') => tokens.value.push({ key: nextKey(), kind: 'paren', paren })
const removeToken = (key: number) => { tokens.value = tokens.value.filter((t) => t.key !== key) }
const clearAll = () => { tokens.value = []; loaded.value = null; localError.value = '' }

const numberDraft = ref<number | string>('')
const addNumber = () => {
  const value = Number(numberDraft.value)
  if (numberDraft.value === '' || !Number.isFinite(value)) return
  tokens.value.push({ key: nextKey(), kind: 'number', value })
  numberDraft.value = ''
}

// ---- the value editor ---------------------------------------------------------------------------

const editorOpen = ref(false)
const editingKey = ref<number | null>(null)
const draft = ref<ValueNode>({ type: 'value', metric: 'sales', collection: { type: 'all_fuels' }, when: { preset: 'this_month' } })

const draftMetric = computed(() => metricOf(draft.value.metric))
const collectionTypes = computed(() => (draftMetric.value?.collections ?? []).filter((t) => t !== 'fuel'))
const collectionTypeLabel = (type: string): string => ({
  product: 'One product', all_fuels: 'All fuels', all_products: 'All products', account: 'One account',
  customer: 'One customer', channel: 'One channel',
  none: draftMetric.value?.collections.includes('channel') ? 'All channels' : 'Whole station',
} as Record<string, string>)[type] ?? type

const idOptions = computed(() => {
  const o = props.options
  switch (draft.value.collection.type) {
    case 'product': case 'fuel': return [...o.products].sort((a, b) => Number(b.is_fuel) - Number(a.is_fuel))
    case 'account': return draft.value.metric === 'income_account' ? o.income_accounts : o.expense_accounts
    case 'customer': return o.customers
    case 'channel': return o.channels
    default: return []
  }
})
const idChoices = computed(() => idOptions.value.map((o) => ({ value: o.id, label: o.name })))
const needsId = computed(() => ['product', 'fuel', 'account', 'customer', 'channel'].includes(draft.value.collection.type))
const draftReady = computed(() => !!draftMetric.value && (!needsId.value || !!draft.value.collection.id))

const defaultWhen = (metric: Metric | undefined): When => (metric?.takes_day ? { preset: 'today' } : { preset: 'this_month' })
const pickCollectionType = (type: string) => {
  draft.value.collection = { type }
  if (['product', 'fuel', 'account', 'customer', 'channel'].includes(type)) draft.value.collection.id = idOptions.value[0]?.id
}
const pickMetric = (key: string) => {
  const metric = metricOf(key)
  draft.value.metric = key
  draft.value.when = defaultWhen(metric)
  pickCollectionType(metric?.collections.find((t) => t !== 'fuel') ?? 'none')
}

const rangeChoices = [
  ['this_month', 'This month'], ['last_month', 'Last month'], ['today', 'Today'], ['yesterday', 'Yesterday'],
  ['this_year', 'This year'], ['last_n_days', 'Last N days'], ['dates', 'Pick dates'],
]
const dayChoices = [['today', 'Today'], ['yesterday', 'Yesterday'], ['month_end_last', 'Last month end'], ['on', 'Pick a date']]
const whenChoices = computed(() => (draftMetric.value?.takes_day ? dayChoices : rangeChoices))
const whenChoice = computed({
  get: () => draft.value.when.preset ?? (draft.value.when.on ? 'on' : 'dates'),
  set: (v: string) => {
    const today = isoDate(new Date())
    if (v === 'dates') draft.value.when = { from: today.slice(0, 8) + '01', to: today }
    else if (v === 'on') draft.value.when = { on: today }
    else if (v === 'last_n_days') draft.value.when = { preset: v, n: 7 }
    else draft.value.when = { preset: v }
  },
})

const openEditor = (token?: Extract<Token, { kind: 'value' }>) => {
  editingKey.value = token?.key ?? null
  draft.value = token
    ? (JSON.parse(JSON.stringify(token.node)) as ValueNode)
    : { type: 'value', metric: 'sales', collection: { type: 'all_fuels' }, when: { preset: 'this_month' } }
  editorOpen.value = true
}
const applyEditor = () => {
  const node = JSON.parse(JSON.stringify(draft.value)) as ValueNode
  const at = tokens.value.findIndex((t) => t.key === editingKey.value)
  if (at >= 0) tokens.value[at] = { key: editingKey.value as number, kind: 'value', node }
  else tokens.value.push({ key: nextKey(), kind: 'value', node })
  editorOpen.value = false
}

// ---- calculate ----------------------------------------------------------------------------------

const calculate = () => {
  localError.value = ''
  serverError.value = ''
  const { ast, error } = astFromTokens(tokens.value)
  if (!ast) {
    localError.value = error ?? 'Check the formula.'
    return
  }
  router.post(`${base.value}/evaluate`, { formula: ast } as never, {
    preserveState: true,
    preserveScroll: true,
    only: ['result'],
    onStart: () => { busy.value = true },
    onFinish: () => { busy.value = false },
    onError: (errors) => { serverError.value = String(errors.formula ?? Object.values(errors)[0] ?? 'Could not calculate.') },
  })
}

const fmt = (v: number | null): string => {
  if (v === null) return '—'
  const digits = v !== 0 && Math.abs(v) < 1 ? 4 : 2
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: digits }).format(v)
}
const unitText = (unit: string | null) => (unit === 'L' ? 'L' : unit ?? '')

const partColumns = [
  { key: 'label', label: 'Value', kind: 'text' as const },
  { key: 'value', label: 'Figure', kind: 'amount' as const },
  { key: 'unit', label: 'Unit', kind: 'text' as const },
  { key: 'source', label: 'Source', kind: 'text' as const },
]

// ---- saving -------------------------------------------------------------------------------------

const saveOpen = ref(false)
const saveName = ref('')
const saveShared = ref(false)
const saveError = ref('')

const openSave = () => {
  saveError.value = ''
  const { ast, error } = astFromTokens(tokens.value)
  if (!ast) {
    localError.value = error ?? 'Check the formula.'
    return
  }
  saveName.value = loaded.value?.name ?? ''
  saveShared.value = loaded.value?.is_shared ?? false
  saveOpen.value = true
}
const save = (update: boolean) => {
  const { ast } = astFromTokens(tokens.value)
  if (!ast || !saveName.value.trim()) {
    saveError.value = 'Give it a name.'
    return
  }
  const payload = { name: saveName.value.trim(), formula: ast, is_shared: saveShared.value } as never
  const url = update && loaded.value ? `${base.value}/formulas/${loaded.value.id}` : `${base.value}/formulas`
  const options = {
    preserveScroll: true,
    preserveState: true,
    only: ['saved', 'flash', 'errors'],
    onSuccess: () => { saveOpen.value = false },
    onError: (errors: Record<string, string>) => { saveError.value = String(Object.values(errors)[0] ?? 'Could not save.') },
  }
  if (update && loaded.value) router.put(url, payload, options)
  else router.post(url, payload, options)
}

const load = (item: Saved) => {
  tokens.value = tokensFromAst(JSON.parse(JSON.stringify(item.formula)) as FormulaNode)
  loaded.value = item
  localError.value = ''
}

const deleting = ref<Saved | null>(null)
const deleteOpen = computed({ get: () => deleting.value !== null, set: (v: boolean) => { if (!v) deleting.value = null } })
const confirmDelete = () => {
  const item = deleting.value
  if (!item) return
  router.delete(`${base.value}/formulas/${item.id}`, {
    preserveScroll: true,
    preserveState: true,
    only: ['saved', 'flash'],
    onSuccess: () => { if (loaded.value?.id === item.id) loaded.value = null },
    onFinish: () => { deleting.value = null },
  })
}
</script>

<template>
  <Head title="Calculator" />

  <PageShell
    title="Calculator"
    description="Build a formula from the books."
    :icon="Calculator"
    :breadcrumbs="breadcrumbs"
  >
    <div class="space-y-5">
      <Card>
        <CardHeader class="pb-3">
          <CardTitle class="text-base">Formula</CardTitle>
          <CardDescription>
            Add values, join them with + − × ÷.
            <Hint>
              Read only
              <template #content>
                Reads only. Each value is the figure of its report, and nothing is ever written to the books.
              </template>
            </Hint>
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="flex min-h-12 flex-wrap items-center gap-2 rounded-md border border-dashed border-rule-default bg-surface-sunken p-3">
            <p v-if="!tokens.length" class="text-sm text-text-secondary">Add a value to start.</p>
            <template v-for="token in tokens" :key="token.key">
              <span
                v-if="token.kind === 'value'"
                class="inline-flex max-w-full items-stretch overflow-hidden rounded-md border border-rule-default bg-surface-raised text-sm"
              >
                <button type="button" class="px-3 py-1.5 text-left hover:bg-surface-band" @click="openEditor(token)">
                  {{ valueLabel(token.node) }}
                </button>
                <button type="button" class="border-l border-rule-subtle px-2 text-text-secondary hover:bg-surface-band" aria-label="Remove" @click="removeToken(token.key)">
                  <X class="h-3.5 w-3.5" />
                </button>
              </span>
              <span v-else class="inline-flex items-center overflow-hidden rounded-md border border-rule-subtle bg-surface-raised font-mono text-sm">
                <span class="px-2.5 py-1.5">
                  {{ token.kind === 'op' ? opSymbol[token.op] : token.kind === 'paren' ? token.paren : fmt(token.value) }}
                </span>
                <button type="button" class="border-l border-rule-subtle px-1.5 py-1.5 text-text-secondary hover:bg-surface-band" aria-label="Remove" @click="removeToken(token.key)">
                  <X class="h-3.5 w-3.5" />
                </button>
              </span>
            </template>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <Button type="button" variant="outline" size="sm" @click="openEditor()">
              <Plus class="mr-1 h-4 w-4" />Value
            </Button>
            <Button v-for="op in operators" :key="op" type="button" variant="outline" size="sm" class="w-9 font-mono" :aria-label="`Add ${opSymbol[op]}`" @click="addOp(op)">
              {{ opSymbol[op] }}
            </Button>
            <Button type="button" variant="outline" size="sm" class="w-9 font-mono" aria-label="Add open bracket" @click="addParen('(')">(</Button>
            <Button type="button" variant="outline" size="sm" class="w-9 font-mono" aria-label="Add close bracket" @click="addParen(')')">)</Button>
            <div class="flex items-center gap-2">
              <Input v-model="numberDraft" type="number" step="any" placeholder="Number" class="h-8 w-28" aria-label="Number" @keydown.enter.prevent="addNumber" />
              <Button type="button" variant="outline" size="sm" @click="addNumber">Add</Button>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <Button type="button" :disabled="busy || !tokens.length" @click="calculate">Calculate</Button>
            <Button type="button" variant="outline" :disabled="!tokens.length" @click="openSave">Save</Button>
            <Button type="button" variant="ghost" :disabled="!tokens.length" @click="clearAll">Clear</Button>
          </div>
          <InputError :message="localError || serverError" />
        </CardContent>
      </Card>

      <Card v-if="result">
        <CardHeader class="pb-3">
          <CardTitle class="text-base">Result</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div v-if="result.result !== null" class="flex flex-wrap items-baseline gap-2">
            <MoneyText v-if="result.unit === 'Rs'" :amount="result.result" :currency="company.base_currency" scale="conclusion" />
            <template v-else>
              <span class="font-display text-4xl tabular-nums">{{ fmt(result.result) }}</span>
              <span v-if="result.unit" class="font-mono text-sm text-text-secondary">{{ unitText(result.unit) }}</span>
            </template>
          </div>
          <p v-else class="text-lg">No result. <span class="text-text-secondary">{{ result.message }}</span></p>
          <p v-for="warning in result.warnings" :key="warning" class="text-sm text-status-attention">{{ warning }}</p>

          <LedgerRegister :data="result.parts" :columns="partColumns" :key-field="(_row: Part, index: number) => String(index)">
            <template #empty>Numbers only.</template>
            <template #cell-label="{ row }">
              <span>{{ row.label }}</span>
              <span class="block text-xs text-text-metadata">{{ row.from }}<template v-if="row.to !== row.from"> to {{ row.to }}</template></span>
              <span v-if="row.note" class="block text-xs text-status-attention">{{ row.note }}</span>
            </template>
            <template #cell-value="{ row }">{{ fmt(row.value) }}</template>
            <template #cell-unit="{ row }">{{ unitText(row.unit) }}</template>
            <template #cell-source="{ row }">
              <a v-if="row.source_href" :href="row.source_href" target="_blank" rel="noopener" class="underline underline-offset-2">Source</a>
            </template>
          </LedgerRegister>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-3">
          <CardTitle class="text-base">Saved</CardTitle>
        </CardHeader>
        <CardContent>
          <p v-if="!saved.length" class="text-sm text-text-secondary">Nothing saved yet.</p>
          <ul v-else class="divide-y divide-rule-subtle">
            <li v-for="item in saved" :key="item.id" class="flex flex-wrap items-center gap-2 py-2">
              <span class="min-w-0 flex-1 truncate font-medium">{{ item.name }}</span>
              <Badge v-if="item.is_shared" variant="outline">Shared<template v-if="!item.mine && item.owner"> · {{ item.owner }}</template></Badge>
              <Button type="button" variant="outline" size="sm" @click="load(item)">Load</Button>
              <Button v-if="item.mine" type="button" variant="ghost" size="sm" @click="deleting = item">Delete</Button>
            </li>
          </ul>
        </CardContent>
      </Card>
    </div>

    <Dialog v-model:open="editorOpen">
      <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Value</DialogTitle>
          <DialogDescription>What to read, for what, and when.</DialogDescription>
        </DialogHeader>
        <div class="space-y-4">
          <div class="grid gap-1.5">
            <Label>Read</Label>
            <Select :model-value="draft.metric" @update:model-value="(v) => pickMetric(String(v))">
              <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectGroup v-for="group in groups" :key="group.label">
                  <SelectLabel>{{ group.label }}</SelectLabel>
                  <SelectItem v-for="metric in group.metrics" :key="metric.key" :value="metric.key">{{ metric.label }}</SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
          </div>

          <div v-if="collectionTypes.length > 1" class="grid gap-1.5">
            <Label>For</Label>
            <Select :model-value="draft.collection.type" @update:model-value="(v) => pickCollectionType(String(v))">
              <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="type in collectionTypes" :key="type" :value="type">{{ collectionTypeLabel(type) }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div v-if="needsId" class="grid gap-1.5">
            <Label>{{ collectionTypeLabel(draft.collection.type).replace('One ', '') }}</Label>
            <SearchableSelect
              :model-value="draft.collection.id ?? ''"
              :options="idChoices"
              :show-value="false"
              placeholder="Pick one"
              @update:model-value="(v: string) => (draft.collection.id = v)"
            />
          </div>

          <div class="grid gap-1.5">
            <Label>{{ draftMetric?.takes_day ? 'On' : 'When' }}</Label>
            <Select v-model="whenChoice">
              <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="[value, label] in whenChoices" :key="value" :value="value">{{ label }}</SelectItem>
              </SelectContent>
            </Select>
            <div v-if="draft.when.preset === 'last_n_days'" class="flex items-center gap-2">
              <Input v-model.number="draft.when.n" type="number" min="1" max="366" class="w-24" aria-label="Days" />
              <span class="text-sm text-text-secondary">days</span>
            </div>
            <Input v-if="draft.when.on !== undefined" v-model="draft.when.on" type="date" aria-label="Date" />
            <div v-if="draft.when.from !== undefined" class="grid grid-cols-2 gap-2">
              <Input v-model="draft.when.from" type="date" aria-label="From" />
              <Input v-model="draft.when.to" type="date" aria-label="To" />
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" @click="editorOpen = false">Cancel</Button>
          <Button type="button" :disabled="!draftReady" @click="applyEditor">Done</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="saveOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Save formula</DialogTitle>
          <DialogDescription>Yours only, unless shared.</DialogDescription>
        </DialogHeader>
        <div class="space-y-4">
          <div class="grid gap-1.5">
            <Label for="calc-name">Name</Label>
            <Input id="calc-name" v-model="saveName" maxlength="120" />
          </div>
          <div class="flex items-center justify-between gap-3">
            <Label for="calc-share">Share with the company</Label>
            <Switch id="calc-share" v-model:checked="saveShared" />
          </div>
          <InputError :message="saveError" />
        </div>
        <DialogFooter class="gap-2">
          <Button type="button" variant="outline" @click="saveOpen = false">Cancel</Button>
          <Button v-if="loaded?.mine" type="button" variant="outline" @click="save(true)">Update</Button>
          <Button type="button" @click="save(false)">{{ loaded?.mine ? 'Save as new' : 'Save' }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <ConfirmDialog
      v-model:open="deleteOpen"
      variant="destructive"
      title="Delete formula?"
      confirm-text="Delete"
      @confirm="confirmDelete"
    />
  </PageShell>
</template>
