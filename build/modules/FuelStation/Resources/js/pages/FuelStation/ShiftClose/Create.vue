<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { Calculator, Fuel, Send, Moon, SunMedium } from 'lucide-vue-next'

type Shift = 'day' | 'night'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface FuelItemRow {
  id: string
  name: string
  fuel_category: string | null
  avg_cost: number | null
}

interface RateRow {
  purchase_rate: number
  sale_rate: number
  effective_date?: string | null
}

const props = defineProps<{
  company: CompanyRef
  date: string
  shift: Shift
  items: FuelItemRow[]
  rates: Record<string, RateRow>
  suggested: {
    liters_by_item_id: Record<string, number>
    cash_amount: number
    easypaisa_amount: number
    jazzcash_amount: number
    bank_transfer_amount: number
    card_swipe_amount: number
    parco_card_amount: number
  }
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Fuel', href: `/${props.company.slug}/fuel/dashboard` },
  { title: 'Shift Close', href: `/${props.company.slug}/fuel/shift-close` },
])

const money = (n: number) =>
  new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)
const qty = (n: number) =>
  new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(n ?? 0)

const form = useForm({
  date: props.date,
  shift: props.shift,
  notes: '',
  lines: props.items.map((item) => ({
    item_id: item.id,
    liters_sold: Number(props.suggested.liters_by_item_id[item.id] ?? 0),
    sale_rate: Number(props.rates[item.id]?.sale_rate ?? 0),
  })),
  cash_amount: Number(props.suggested.cash_amount ?? 0),
  easypaisa_amount: Number(props.suggested.easypaisa_amount ?? 0),
  jazzcash_amount: Number(props.suggested.jazzcash_amount ?? 0),
  bank_transfer_amount: Number(props.suggested.bank_transfer_amount ?? 0),
  card_swipe_amount: Number(props.suggested.card_swipe_amount ?? 0),
  parco_card_amount: Number(props.suggested.parco_card_amount ?? 0),
})

const linesWithMeta = computed(() => {
  return form.lines.map((line) => {
    const item = props.items.find((i) => i.id === line.item_id)
    const avgCost = Number(item?.avg_cost ?? 0)
    const liters = Number(line.liters_sold ?? 0)
    const saleRate = Number(line.sale_rate ?? 0)
    const revenue = liters * saleRate
    const cogs = liters * avgCost
    return { line, item, avgCost, liters, saleRate, revenue, cogs }
  })
})

const totals = computed(() => {
  const totalRevenue = linesWithMeta.value.reduce((sum, r) => sum + (isFinite(r.revenue) ? r.revenue : 0), 0)
  const totalCogs = linesWithMeta.value.reduce((sum, r) => sum + (isFinite(r.cogs) ? r.cogs : 0), 0)
  const grossProfit = totalRevenue - totalCogs
  const collections =
    Number(form.cash_amount ?? 0) +
    Number(form.easypaisa_amount ?? 0) +
    Number(form.jazzcash_amount ?? 0) +
    Number(form.bank_transfer_amount ?? 0) +
    Number(form.card_swipe_amount ?? 0) +
    Number(form.parco_card_amount ?? 0)
  const overShort = collections - totalRevenue
  return { totalRevenue, totalCogs, grossProfit, collections, overShort }
})

const reload = () => {
  router.get(`/${props.company.slug}/fuel/shift-close`, { date: form.date, shift: form.shift }, { preserveScroll: true })
}

const submit = () => {
  form.post(`/${props.company.slug}/fuel/shift-close`, { preserveScroll: true })
}
</script>

<template>
  <Head title="Shift Close" />

  <PageShell :breadcrumbs="breadcrumbs">
    <template #title>
      <div class="flex items-center gap-2">
        <Fuel class="h-5 w-5 text-amber-600" />
        <span>Shift Close</span>
      </div>
    </template>

    <template #subtitle>
      Post sales + COGS to the ledger for daily profit (weighted average cost).
    </template>

    <template #actions>
      <Button variant="secondary" @click="reload">
        <Calculator class="mr-2 h-4 w-4" />
        Refresh Suggestions
      </Button>
      <Button :disabled="form.processing" @click="submit">
        <Send class="mr-2 h-4 w-4" />
        Post to Ledger
      </Button>
    </template>

    <div class="grid gap-4 lg:grid-cols-3">
      <Card class="lg:col-span-2">
        <CardHeader>
          <CardTitle>Sales Input</CardTitle>
          <CardDescription>Enter liters from pump meters; rates are pulled from OGRA rate changes.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="date">Date</Label>
              <Input id="date" v-model="form.date" type="date" @change="reload" />
              <p v-if="form.errors.date" class="text-sm text-destructive">{{ form.errors.date }}</p>
            </div>
            <div class="space-y-2">
              <Label>Shift</Label>
              <Select v-model="form.shift" @update:model-value="reload">
                <SelectTrigger>
                  <SelectValue placeholder="Select shift" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="day">
                    <div class="flex items-center gap-2">
                      <SunMedium class="h-4 w-4" />
                      Day
                    </div>
                  </SelectItem>
                  <SelectItem value="night">
                    <div class="flex items-center gap-2">
                      <Moon class="h-4 w-4" />
                      Night
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.shift" class="text-sm text-destructive">{{ form.errors.shift }}</p>
            </div>
          </div>

          <div class="space-y-3">
            <div class="grid grid-cols-12 gap-2 text-xs font-medium text-text-tertiary">
              <div class="col-span-4">Fuel</div>
              <div class="col-span-2 text-right">Liters</div>
              <div class="col-span-2 text-right">Sale rate</div>
              <div class="col-span-2 text-right">Revenue</div>
              <div class="col-span-2 text-right">COGS</div>
            </div>

            <div
              v-for="(row, index) in linesWithMeta"
              :key="row.line.item_id"
              class="grid grid-cols-12 items-center gap-2 rounded-lg border border-border/70 bg-surface-1 p-3"
            >
              <div class="col-span-4">
                <div class="flex items-center gap-2">
                  <div class="font-medium text-text-primary">{{ row.item?.name ?? 'Fuel' }}</div>
                  <Badge variant="outline" class="text-xs">{{ row.item?.fuel_category ?? '-' }}</Badge>
                </div>
                <div class="text-xs text-text-tertiary">
                  Avg cost: {{ money(row.avgCost) }} • Rate date: {{ props.rates[row.line.item_id]?.effective_date ?? '—' }}
                </div>
              </div>

              <div class="col-span-2">
                <Input v-model.number="form.lines[index].liters_sold" type="number" min="0" step="0.01" class="text-right" />
                <p v-if="form.errors[`lines.${index}.liters_sold`]" class="text-xs text-destructive mt-1">
                  {{ form.errors[`lines.${index}.liters_sold`] }}
                </p>
              </div>

              <div class="col-span-2">
                <Input v-model.number="form.lines[index].sale_rate" type="number" min="0" step="0.01" class="text-right" />
                <p v-if="form.errors[`lines.${index}.sale_rate`]" class="text-xs text-destructive mt-1">
                  {{ form.errors[`lines.${index}.sale_rate`] }}
                </p>
              </div>

              <div class="col-span-2 text-right text-sm font-medium text-text-primary">
                {{ money(row.revenue) }}
              </div>
              <div class="col-span-2 text-right text-sm text-text-secondary">
                {{ money(row.cogs) }}
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <div class="space-y-4">
        <Card>
          <CardHeader>
            <CardTitle>Collections</CardTitle>
            <CardDescription>Enter what was collected (from manager’s book).</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="space-y-2">
              <Label>Cash</Label>
              <Input v-model.number="form.cash_amount" type="number" min="0" step="0.01" class="text-right" />
            </div>
            <div class="space-y-2">
              <Label>Easypaisa</Label>
              <Input v-model.number="form.easypaisa_amount" type="number" min="0" step="0.01" class="text-right" />
            </div>
            <div class="space-y-2">
              <Label>JazzCash</Label>
              <Input v-model.number="form.jazzcash_amount" type="number" min="0" step="0.01" class="text-right" />
            </div>
            <div class="space-y-2">
              <Label>Bank transfer</Label>
              <Input v-model.number="form.bank_transfer_amount" type="number" min="0" step="0.01" class="text-right" />
            </div>
            <div class="space-y-2">
              <Label>Card swipes</Label>
              <Input v-model.number="form.card_swipe_amount" type="number" min="0" step="0.01" class="text-right" />
            </div>
            <div class="space-y-2">
              <Label>Parco card</Label>
              <Input v-model.number="form.parco_card_amount" type="number" min="0" step="0.01" class="text-right" />
            </div>

            <div class="space-y-2">
              <Label>Notes</Label>
              <Input v-model="form.notes" type="text" placeholder="Optional" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Preview</CardTitle>
            <CardDescription>{{ props.company.base_currency }}</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="flex items-center justify-between text-sm">
              <span class="text-text-tertiary">Total liters</span>
              <span class="font-medium text-text-primary">
                {{ qty(linesWithMeta.reduce((s, r) => s + Number(r.liters ?? 0), 0)) }} L
              </span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-text-tertiary">Revenue</span>
              <span class="font-medium text-text-primary">{{ money(totals.totalRevenue) }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-text-tertiary">COGS</span>
              <span class="font-medium text-text-primary">{{ money(totals.totalCogs) }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-text-tertiary">Gross profit</span>
              <span class="font-semibold" :class="totals.grossProfit >= 0 ? 'text-emerald-700' : 'text-destructive'">
                {{ money(totals.grossProfit) }}
              </span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-text-tertiary">Collections</span>
              <span class="font-medium text-text-primary">{{ money(totals.collections) }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-text-tertiary">Over/short vs expected</span>
              <span :class="Math.abs(totals.overShort) < 0.01 ? 'text-emerald-700' : 'text-amber-700'" class="font-medium">
                {{ money(totals.overShort) }}
              </span>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>

