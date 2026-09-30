<script setup lang="ts">
/**
 * Correct a posted invoice, payment, bill or bill payment: pick who it really belongs to, or
 * (invoices/bills) split it between parties, and say why. It reads as editing the record; the
 * server posts the change as its own correction and keeps the original (CorrectionService /
 * BillCorrectionService).
 */
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import SearchableSelect from '@/components/SearchableSelect.vue'
import InputError from '@/components/InputError.vue'
import { Plus, Trash2 } from 'lucide-vue-next'

const props = withDefaults(defineProps<{
  kind: 'invoice' | 'payment' | 'bill' | 'bill_payment'
  party?: 'customer' | 'supplier'
  url: string
  number: string
  total: number
  customerId: string | null
  parties: { id: string; name: string }[]
  // Payments already applied to this invoice or bill, shown before a split.
  appliedPayments?: { number: string; party: string | null; amount: number | string }[]
}>(), { party: 'customer', appliedPayments: () => [] })
const open = defineModel<boolean>('open', { default: false })

const form = useForm({
  action: 'change_customer' as 'change_customer' | 'split',
  customer_id: '',
  shares: [] as { customer_id: string; amount: number | null }[],
  // Money freed by a correction stays on account; applying it is left to the payment's page.
  apply_oldest_first: false,
  unapply_payments: true,
  reason: '',
})

const reset = () => {
  form.reset()
  form.clearErrors()
  form.shares = [
    { customer_id: '', amount: props.total },
    { customer_id: '', amount: null },
  ]
}
watch(open, (value) => { if (value) reset() })

const canSplit = computed(() => true)
const canApplyOldest = computed(() => props.kind === 'payment' || props.kind === 'bill_payment')
const options = computed(() => props.parties.map((c) => ({ value: c.id, label: c.name })))
const others = computed(() => options.value.filter((o) => o.value !== props.customerId))
const shareTotal = computed(() => form.shares.reduce((sum, s) => sum + Number(s.amount || 0), 0))
const remaining = computed(() => Math.round((props.total - shareTotal.value) * 100) / 100)

const addShare = () => form.shares.push({ customer_id: '', amount: remaining.value > 0 ? remaining.value : null })
const removeShare = (i: number) => form.shares.splice(i, 1)

const canSave = computed(() => form.reason.trim().length >= 3 && (form.action === 'change_customer'
  ? !!form.customer_id
  : form.shares.length >= 2 && form.shares.every((s) => s.customer_id && Number(s.amount) > 0) && Math.abs(remaining.value) < 0.01))

const money = (n: number | string) => Number(n).toLocaleString(undefined, { maximumFractionDigits: 2 })
const hasPayments = computed(() => props.appliedPayments.length > 0)

// Only what the chosen correction needs goes to the server.
const save = () => form
  .transform((data) => (data.action === 'split'
    ? { action: 'split', shares: data.shares, unapply_payments: data.unapply_payments, reason: data.reason }
    : { action: 'change_customer', customer_id: data.customer_id, apply_oldest_first: data.apply_oldest_first, reason: data.reason }))
  .post(props.url, { preserveScroll: true, onSuccess: () => { open.value = false } })

// Any error the fields above do not show still shows, so a refused save never looks like nothing.
const otherErrors = computed(() => Object.entries(form.errors)
  .filter(([key]) => !['customer_id', 'shares', 'reason'].includes(key))
  .map(([, message]) => message))
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="sm:max-w-xl">
      <DialogHeader>
        <DialogTitle>Correct {{ number }}</DialogTitle>
        <DialogDescription>The original stays in the records.</DialogDescription>
      </DialogHeader>

      <div class="min-w-0 space-y-4">
        <Tabs v-if="canSplit" v-model="form.action">
          <TabsList>
            <TabsTrigger value="change_customer">Other {{ party }}</TabsTrigger>
            <TabsTrigger value="split">Split</TabsTrigger>
          </TabsList>
        </Tabs>

        <div v-if="hasPayments && canSplit" class="rounded-md border border-status-attention/40 bg-status-attention/10 p-3 text-sm">
          <p class="font-medium">Payments applied</p>
          <p v-for="p in appliedPayments" :key="p.number" class="flex justify-between gap-3 tabular-nums">
            <span class="truncate">{{ p.number }}<template v-if="p.party"> · {{ p.party }}</template></span>
            <span>{{ money(p.amount) }}</span>
          </p>
          <label v-if="form.action === 'split'" class="mt-2 flex items-center gap-2">
            <Checkbox v-model="form.unapply_payments" />
            Take payments off first
          </label>
        </div>

        <div v-if="form.action === 'change_customer'" class="min-w-0 space-y-2">
          <Label>Belongs to</Label>
          <SearchableSelect v-model="form.customer_id" :options="others" :show-value="false" :placeholder="`Choose ${party}`" />
          <InputError :message="form.errors.customer_id" />
          <label v-if="canApplyOldest" class="flex items-center gap-2 text-sm">
            <Checkbox v-model="form.apply_oldest_first" />
            Pay their oldest unpaid {{ party === 'supplier' ? 'bills' : 'invoices' }}
          </label>
        </div>

        <div v-else class="min-w-0 space-y-2">
          <div class="grid grid-cols-[minmax(0,1fr)_8rem_2.25rem] gap-2 text-xs text-muted-foreground">
            <span class="capitalize">{{ party }}</span>
            <span class="text-right">Amount</span>
            <span />
          </div>
          <div v-for="(share, i) in form.shares" :key="i" class="grid grid-cols-[minmax(0,1fr)_8rem_2.25rem] items-center gap-2">
            <div class="min-w-0">
              <SearchableSelect v-model="share.customer_id" :options="options" :show-value="false" :placeholder="i === 0 ? `Keeps ${number}` : `Choose ${party}`" />
            </div>
            <Input v-model.number="share.amount" type="number" step="0.01" min="0" class="w-full text-right tabular-nums" />
            <Button v-if="form.shares.length > 2" type="button" variant="ghost" size="icon" aria-label="Remove" @click="removeShare(i)">
              <Trash2 class="h-4 w-4" />
            </Button>
            <span v-else />
          </div>
          <div class="flex items-center justify-between text-sm">
            <Button type="button" variant="outline" size="sm" @click="addShare"><Plus class="mr-1 h-4 w-4" />Add</Button>
            <span class="tabular-nums" :class="Math.abs(remaining) < 0.01 ? 'text-muted-foreground' : 'text-status-critical'">
              {{ Math.abs(remaining) < 0.01 ? 'Adds up' : `${money(remaining)} left` }}
            </span>
          </div>
          <InputError :message="form.errors.shares" />
        </div>

        <div class="space-y-2">
          <Label for="correction-reason">Reason</Label>
          <Textarea id="correction-reason" v-model="form.reason" rows="2" class="w-full" placeholder="What was wrong" />
          <InputError :message="form.errors.reason" />
        </div>
      </div>

      <p v-for="(message, i) in otherErrors" :key="i" class="text-sm text-destructive">{{ message }}</p>

      <DialogFooter>
        <Button variant="outline" @click="open = false">Cancel</Button>
        <Button :disabled="!canSave || form.processing" @click="save">Save correction</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
