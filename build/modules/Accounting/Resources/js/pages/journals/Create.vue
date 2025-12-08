<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { FileText, Plus, Trash2 } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface AccountRef {
  id: string
  code: string
  name: string
  type: string
  subtype: string
}

const props = defineProps<{
  company: CompanyRef
  accounts: AccountRef[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Journals', href: `/${props.company.slug}/journals` },
  { title: 'Create', href: `/${props.company.slug}/journals/create` },
]

const form = useForm({
  transaction_date: new Date().toISOString().slice(0, 10),
  posting_date: new Date().toISOString().slice(0, 10),
  description: '',
  post: true,
  entries: [
    { account_id: '', type: 'debit', amount: '', description: '' },
    { account_id: '', type: 'credit', amount: '', description: '' },
  ],
})

const totals = computed(() => {
  let debit = 0
  let credit = 0
  form.entries.forEach((e) => {
    const amt = Number(e.amount) || 0
    if (e.type === 'debit') debit += amt
    else credit += amt
  })
  return { debit, credit, balanced: Math.abs(debit - credit) < 0.0001 }
})

const addLine = () => {
  form.entries.push({ account_id: '', type: 'debit', amount: '', description: '' })
}

const removeLine = (idx: number) => {
  if (form.entries.length > 2) {
    form.entries.splice(idx, 1)
  }
}

const submit = () => {
  form.post(`/${props.company.slug}/journals`, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head title="New Journal" />
  <PageShell
    title="New Journal"
    :breadcrumbs="breadcrumbs"
    :icon="FileText"
  >
    <form class="space-y-6" @submit.prevent="submit">
      <div class="grid gap-4 md:grid-cols-3">
        <div>
          <Label for="transaction_date">Transaction Date</Label>
          <Input id="transaction_date" v-model="form.transaction_date" type="date" required />
        </div>
        <div>
          <Label for="posting_date">Posting Date</Label>
          <Input id="posting_date" v-model="form.posting_date" type="date" required />
        </div>
        <div class="flex items-center gap-2 pt-6">
          <Checkbox id="post" v-model:checked="form.post" />
          <Label for="post">Post immediately</Label>
        </div>
        <div class="md:col-span-3">
          <Label for="description">Description</Label>
          <Input id="description" v-model="form.description" placeholder="Optional description" />
        </div>
      </div>

      <div class="flex items-center justify-between">
        <div class="text-lg font-semibold">Lines</div>
        <Button type="button" variant="outline" @click="addLine">
          <Plus class="mr-2 h-4 w-4" />
          Add Line
        </Button>
      </div>

      <div class="space-y-3">
        <div
          v-for="(entry, idx) in form.entries"
          :key="idx"
          class="grid gap-3 rounded border p-3 md:grid-cols-12"
        >
          <div class="md:col-span-4">
            <Label>Account</Label>
            <Select v-model="entry.account_id">
              <SelectTrigger>
                <SelectValue placeholder="Select account" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="acct in accounts"
                  :key="acct.id"
                  :value="acct.id"
                >
                  {{ acct.code }} — {{ acct.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="md:col-span-2">
            <Label>Type</Label>
            <Select v-model="entry.type">
              <SelectTrigger>
                <SelectValue placeholder="Type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="debit">Debit</SelectItem>
                <SelectItem value="credit">Credit</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="md:col-span-3">
            <Label>Amount</Label>
            <Input v-model="entry.amount" type="number" min="0" step="0.01" />
          </div>
          <div class="md:col-span-2">
            <Label>Description</Label>
            <Input v-model="entry.description" placeholder="Optional" />
          </div>
          <div class="md:col-span-1 flex items-end justify-end">
            <Button type="button" variant="destructive" size="icon" @click="removeLine(idx)">
              <Trash2 class="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>

      <div class="grid gap-2 md:w-1/3">
        <div class="flex justify-between text-sm">
          <span>Total Debit</span>
          <span>{{ totals.debit.toFixed(2) }}</span>
        </div>
        <div class="flex justify-between text-sm">
          <span>Total Credit</span>
          <span>{{ totals.credit.toFixed(2) }}</span>
        </div>
        <div class="flex justify-between text-sm font-semibold" :class="totals.balanced ? 'text-green-600' : 'text-red-600'">
          <span>Balanced?</span>
          <span>{{ totals.balanced ? 'Yes' : 'No' }}</span>
        </div>
      </div>

      <div class="flex justify-end gap-3">
        <Button type="submit" :disabled="!totals.balanced || form.processing">
          Save Journal
        </Button>
      </div>
    </form>
  </PageShell>
</template>
