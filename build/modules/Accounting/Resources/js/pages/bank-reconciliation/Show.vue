<script setup lang="ts">
/**
 * Reconciling a bank account against its statement. The left-hand list is the books: every
 * entry on the bank's ledger account not cleared by an earlier reconciliation. Tick what is on
 * the statement until the difference is zero. An imported statement (CSV) is matched to the
 * books automatically; a statement line with no entry in the books (bank charge, profit,
 * returned cheque) can be booked from here. See BankReconciliationService.
 */
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { RefreshCcw, Upload, Check } from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'
import MoneyText from '@/components/MoneyText.vue'

interface BookLine {
  id: string
  date: string
  reference: string | null
  description: string
  amount: number
  cleared: boolean
  after_statement: boolean
  link: string | null
}

interface StatementLine {
  id: string
  date: string
  description: string | null
  reference: string | null
  amount: number
  balance: number | null
  journal_entry_id: string | null
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  reconciliation: {
    id: string
    status: 'in_progress' | 'completed' | 'cancelled'
    statement_date: string
    statement_ending_balance: number
    completed_at: string | null
    bank_account: { id: string; name: string; number: string | null; currency: string | null }
  }
  lines: BookLine[]
  statement: StatementLine[]
  summary: {
    statement_balance: number
    cleared_before: number
    cleared_now: number
    cleared_balance: number
    difference: number
    book_balance: number
    unmatched_statement: number
  }
  entryAccounts: Array<{ id: string; code: string; name: string; type: string }>
}>()

const base = computed(() => `/${props.company.slug}/banking/reconciliation/${props.reconciliation.id}`)
const currency = computed(() => props.reconciliation.bank_account.currency || props.company.base_currency || 'PKR')
const open = computed(() => props.reconciliation.status === 'in_progress')
const balanced = computed(() => Math.abs(props.summary.difference) < 0.01)

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Bank Reconciliation', href: `/${props.company.slug}/banking/reconciliation` },
  { title: props.reconciliation.bank_account.name, href: base.value },
]

// ── Books ────────────────────────────────────────────────────────────
const filter = ref<'open' | 'cleared' | 'all'>('open')
const shownLines = computed(() => props.lines.filter((l) =>
  filter.value === 'all' ? true : filter.value === 'cleared' ? l.cleared : !l.cleared))
const busy = ref<string | null>(null)
const toggle = (line: BookLine, cleared: boolean) => {
  busy.value = line.id
  router.post(`${base.value}/toggle`, { journal_entry_id: line.id, cleared }, {
    preserveScroll: true,
    preserveState: true,
    onFinish: () => { busy.value = null },
  })
}
const matchedTo = computed(() => {
  const byId: Record<string, BookLine> = {}
  props.lines.forEach((l) => { byId[l.id] = l })
  return byId
})

// ── Statement ────────────────────────────────────────────────────────
const importForm = useForm({ statement: null as File | null })
const fileInput = ref<HTMLInputElement | null>(null)
const pickFile = (e: Event) => {
  importForm.statement = (e.target as HTMLInputElement).files?.[0] ?? null
  if (!importForm.statement) return
  importForm.post(`${base.value}/import`, {
    forceFormData: true,
    preserveScroll: true,
    onFinish: () => { if (fileInput.value) fileInput.value.value = '' },
  })
}
const entryAccount = ref<Record<string, string>>({})
const addEntry = (line: StatementLine) => {
  const account = entryAccount.value[line.id]
  if (!account) return
  busy.value = line.id
  router.post(`${base.value}/entry`, { statement_line_id: line.id, account_id: account }, {
    preserveScroll: true,
    onFinish: () => { busy.value = null },
  })
}

const complete = () => router.post(`${base.value}/complete`)
const discard = () => router.post(`${base.value}/cancel`)
</script>

<template>
  <Head :title="`Reconcile ${reconciliation.bank_account.name}`" />

  <PageShell
    :title="`Reconcile ${reconciliation.bank_account.name}`"
    :description="`Statement to ${reconciliation.statement_date}`"
    :icon="RefreshCcw"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <template v-if="open">
        <input ref="fileInput" type="file" accept=".csv,text/csv" class="hidden" @change="pickFile" />
        <Button variant="outline" :disabled="importForm.processing" @click="fileInput?.click()">
          <Upload class="mr-2 h-4 w-4" />{{ statement.length ? 'Re-import statement' : 'Import statement' }}
        </Button>
        <Button variant="outline" @click="discard">Discard</Button>
        <Button :disabled="!balanced" @click="complete"><Check class="mr-2 h-4 w-4" />Complete</Button>
      </template>
      <Badge v-else variant="secondary">Completed</Badge>
    </template>

    <p v-if="importForm.errors.statement" class="mb-4 text-sm text-destructive">{{ importForm.errors.statement }}</p>

    <!-- Where it stands -->
    <div class="mb-6 grid gap-3 sm:grid-cols-4">
      <div class="rounded-lg border p-3">
        <div class="text-xs text-muted-foreground">Statement balance</div>
        <div class="text-lg font-semibold tabular-nums"><MoneyText :amount="summary.statement_balance" :currency="currency" /></div>
      </div>
      <div class="rounded-lg border p-3">
        <div class="text-xs text-muted-foreground">Cleared</div>
        <div class="text-lg font-semibold tabular-nums"><MoneyText :amount="summary.cleared_balance" :currency="currency" /></div>
      </div>
      <div class="rounded-lg border p-3" :class="balanced ? 'border-status-success/40 bg-status-success/10' : 'border-status-attention/40 bg-status-attention/10'">
        <div class="text-xs text-muted-foreground">Difference</div>
        <div class="text-lg font-semibold tabular-nums"><MoneyText :amount="summary.difference" :currency="currency" /></div>
      </div>
      <div class="rounded-lg border p-3">
        <div class="text-xs text-muted-foreground">Books on {{ reconciliation.statement_date }}</div>
        <div class="text-lg font-semibold tabular-nums"><MoneyText :amount="summary.book_balance" :currency="currency" /></div>
      </div>
    </div>

    <div class="grid gap-6" :class="statement.length ? 'xl:grid-cols-2' : ''">
      <!-- Books -->
      <Card>
        <CardHeader class="flex flex-row items-center justify-between gap-3 space-y-0">
          <CardTitle class="text-base">In the books</CardTitle>
          <div class="flex gap-1 text-xs">
            <Button v-for="f in (['open', 'cleared', 'all'] as const)" :key="f" size="sm" :variant="filter === f ? 'secondary' : 'ghost'" @click="filter = f">
              {{ f === 'open' ? 'Not cleared' : f === 'cleared' ? 'Cleared' : 'All' }}
            </Button>
          </div>
        </CardHeader>
        <CardContent class="overflow-x-auto p-0">
          <table class="w-full text-sm">
            <thead class="border-b text-left text-xs text-muted-foreground">
              <tr>
                <th class="w-10 px-3 py-2"></th>
                <th class="px-2 py-2">Date</th>
                <th class="px-2 py-2">Entry</th>
                <th class="px-2 py-2 text-right">In</th>
                <th class="px-3 py-2 text-right">Out</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in shownLines" :key="line.id" class="border-b last:border-0" :class="line.after_statement ? 'text-muted-foreground' : ''">
                <td class="px-3 py-2">
                  <Checkbox :model-value="line.cleared" :disabled="!open || busy === line.id" @update:model-value="(v) => toggle(line, v === true)" />
                </td>
                <td class="whitespace-nowrap px-2 py-2 tabular-nums">{{ line.date }}</td>
                <td class="px-2 py-2">
                  <Link v-if="line.link" :href="`/${company.slug}/${line.link}`" class="text-primary underline-offset-2 hover:underline">{{ line.reference }}</Link>
                  <span v-else>{{ line.reference }}</span>
                  <div class="text-xs text-muted-foreground">{{ line.description }}<span v-if="line.after_statement"> · after statement date</span></div>
                </td>
                <td class="px-2 py-2 text-right tabular-nums"><MoneyText v-if="line.amount > 0" :amount="line.amount" :currency="currency" :show-currency="false" :fraction-digits="0" /></td>
                <td class="px-3 py-2 text-right tabular-nums"><MoneyText v-if="line.amount < 0" :amount="-line.amount" :currency="currency" :show-currency="false" :fraction-digits="0" /></td>
              </tr>
              <tr v-if="!shownLines.length">
                <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">Nothing here.</td>
              </tr>
            </tbody>
          </table>
        </CardContent>
      </Card>

      <!-- Statement -->
      <Card v-if="statement.length">
        <CardHeader class="flex flex-row items-center justify-between gap-3 space-y-0">
          <CardTitle class="text-base">On the statement</CardTitle>
          <span class="text-xs text-muted-foreground">{{ summary.unmatched_statement }} not in the books</span>
        </CardHeader>
        <CardContent class="overflow-x-auto p-0">
          <table class="w-full text-sm">
            <thead class="border-b text-left text-xs text-muted-foreground">
              <tr>
                <th class="px-3 py-2">Date</th>
                <th class="px-2 py-2">Description</th>
                <th class="px-2 py-2 text-right">Amount</th>
                <th class="px-3 py-2">Books</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in statement" :key="line.id" class="border-b last:border-0 align-top">
                <td class="whitespace-nowrap px-3 py-2 tabular-nums">{{ line.date }}</td>
                <td class="px-2 py-2">
                  {{ line.description }}
                  <div v-if="line.reference" class="text-xs text-muted-foreground">{{ line.reference }}</div>
                </td>
                <td class="px-2 py-2 text-right tabular-nums" :class="line.amount < 0 ? 'text-status-critical' : ''">
                  <MoneyText :amount="line.amount" :currency="currency" :show-currency="false" :fraction-digits="0" />
                </td>
                <td class="px-3 py-2">
                  <span v-if="line.journal_entry_id" class="text-xs text-status-success">
                    ✓ {{ matchedTo[line.journal_entry_id]?.reference ?? 'Matched' }}
                    <span v-if="matchedTo[line.journal_entry_id]" class="text-muted-foreground">{{ matchedTo[line.journal_entry_id].date }}</span>
                  </span>
                  <div v-else-if="open" class="flex min-w-56 items-center gap-1">
                    <Select v-model="entryAccount[line.id]">
                      <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Book to…" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="a in entryAccounts" :key="a.id" :value="a.id">{{ a.code }} {{ a.name }}</SelectItem>
                      </SelectContent>
                    </Select>
                    <Button size="sm" class="h-8" :disabled="!entryAccount[line.id] || busy === line.id" @click="addEntry(line)">Add</Button>
                  </div>
                  <span v-else class="text-xs text-muted-foreground">Not in the books</span>
                </td>
              </tr>
            </tbody>
          </table>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>
