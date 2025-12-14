<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Loader2, ArrowRightLeft } from 'lucide-vue-next'
import { computed } from 'vue'

interface Props {
  transaction: any
  accounts: any[]
}

const props = defineProps<Props>()
const emit = defineEmits(['success'])
const page = usePage()
const company = page.props.company as any

const isSpend = computed(() => Number(props.transaction.amount) < 0)

const form = useForm({
  bank_transaction_id: props.transaction.id,
  target_bank_account_id: '',
})

// Filter out current bank account
const availableAccounts = computed(() => {
  return props.accounts.filter(a => a.id !== props.transaction.bank_account_id)
})

const submit = () => {
  form.post(`/${company.slug}/banking/resolve/transfer`, {
    preserveScroll: true,
    onSuccess: () => emit('success'),
  })
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center gap-4 p-4 border rounded-md bg-background/50">
      <div class="flex-1 text-center">
        <div class="text-xs text-muted-foreground uppercase font-bold">{{ isSpend ? 'From' : 'To' }}</div>
        <div class="font-medium">{{ transaction.bank_account?.account_name || 'Current Account' }}</div>
      </div>
      <ArrowRightLeft class="h-5 w-5 text-muted-foreground" />
      <div class="flex-1 text-center">
        <div class="text-xs text-muted-foreground uppercase font-bold">{{ isSpend ? 'To' : 'From' }}</div>
        <div class="font-medium text-primary">Target Account</div>
      </div>
    </div>

    <div class="space-y-2">
      <Label>Select Account</Label>
      <Select v-model="form.target_bank_account_id">
        <SelectTrigger>
          <SelectValue placeholder="Choose bank account..." />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="acc in availableAccounts" :key="acc.id" :value="acc.id">
            {{ acc.account_name }} ({{ acc.account_number }})
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div class="flex justify-end pt-2">
      <Button @click="submit" :disabled="form.processing || !form.target_bank_account_id">
        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
        Record Transfer
      </Button>
    </div>
  </div>
</template>
