<script setup lang="ts">
/**
 * Correct a posted invoice or payment: pick who it really belongs to, or (invoices) split it
 * between customers, and say why. It reads as editing the record; the server posts the change as
 * its own correction and keeps the original (CorrectionService).
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

const props = defineProps<{
  kind: 'invoice' | 'payment'
  url: string
  number: string
  total: number
  customerId: string | null
  customers: { id: string; name: string }[]
}>()
const open = defineModel<boolean>('open', { default: false })

const form = useForm({
  action: 'change_customer' as 'change_customer' | 'split',
  customer_id: '',
  shares: [] as { customer_id: string; amount: number | null }[],
  apply_oldest_first: true,
  reason: '',
})

const reset = () => {
  form.reset()
  form.clearErrors()
  form.shares = [
    { customer_id: props.customerId ?? '', amount: props.total },
    { customer_id: '', amount: null },
  ]
}
watch(open, (value) => { if (value) reset() })

const options = computed(() => props.customers.map((c) => ({ value: c.id, label: c.name })))
const others = computed(() => options.value.filter((o) => o.value !== props.customerId))
const shareTotal = computed(() => form.shares.reduce((sum, s) => sum + Number(s.amount || 0), 0))
const remaining = computed(() => Math.round((props.total - shareTotal.value) * 100) / 100)

const addShare = () => form.shares.push({ customer_id: '', amount: remaining.value > 0 ? remaining.value : null })
const removeShare = (i: number) => form.shares.splice(i, 1)

const canSave = computed(() => form.reason.trim().length >= 3 && (form.action === 'change_customer'
  ? !!form.customer_id
  : form.shares.length >= 2 && form.shares.every((s) => s.customer_id && Number(s.amount) > 0) && Math.abs(remaining.value) < 0.01))

const save = () => form.post(props.url, { preserveScroll: true, onSuccess: () => { open.value = false } })
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Correct {{ number }}</DialogTitle>
        <DialogDescription>The original stays in the records.</DialogDescription>
      </DialogHeader>

      <div class="space-y-4">
        <Tabs v-if="kind === 'invoice'" v-model="form.action">
          <TabsList>
            <TabsTrigger value="change_customer">Other customer</TabsTrigger>
            <TabsTrigger value="split">Split</TabsTrigger>
          </TabsList>
        </Tabs>

        <div v-if="form.action === 'change_customer'" class="space-y-2">
          <Label>Belongs to</Label>
          <SearchableSelect v-model="form.customer_id" :options="others" placeholder="Choose customer" />
          <InputError :message="form.errors.customer_id" />
          <label v-if="kind === 'payment'" class="flex items-center gap-2 text-sm">
            <Checkbox v-model="form.apply_oldest_first" />
            Pay their oldest unpaid invoices
          </label>
        </div>

        <div v-else class="space-y-2">
          <div v-for="(share, i) in form.shares" :key="i" class="flex items-center gap-2">
            <div class="min-w-0 flex-1">
              <SearchableSelect v-model="share.customer_id" :options="options" placeholder="Customer" />
            </div>
            <Input v-model.number="share.amount" type="number" step="0.01" min="0" class="w-32 text-right tabular-nums" />
            <Button v-if="form.shares.length > 2" type="button" variant="ghost" size="icon" aria-label="Remove" @click="removeShare(i)">
              <Trash2 class="h-4 w-4" />
            </Button>
          </div>
          <div class="flex items-center justify-between text-sm">
            <Button type="button" variant="outline" size="sm" @click="addShare"><Plus class="mr-1 h-4 w-4" />Add</Button>
            <span :class="Math.abs(remaining) < 0.01 ? 'text-muted-foreground' : 'text-status-critical'">
              {{ Math.abs(remaining) < 0.01 ? 'Adds up' : `${remaining.toLocaleString()} left` }}
            </span>
          </div>
          <InputError :message="form.errors.shares" />
        </div>

        <div class="space-y-2">
          <Label for="correction-reason">Reason</Label>
          <Textarea id="correction-reason" v-model="form.reason" rows="2" placeholder="What was wrong" />
          <InputError :message="form.errors.reason" />
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="open = false">Cancel</Button>
        <Button :disabled="!canSave || form.processing" @click="save">Save correction</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
