<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Loader2 } from 'lucide-vue-next'

interface Props {
  transaction: any
  accounts: any[]
  suggestion?: { account_id: string, description: string }
}

const props = defineProps<Props>()
const emit = defineEmits(['success'])
const page = usePage()
const company = page.props.company as any

const form = useForm({
  bank_transaction_id: props.transaction.id,
  allocations: [
    {
      account_id: '',
      amount: Math.abs(Number(props.transaction.amount)),
      description: props.transaction.description,
    }
  ]
})

// Readable labels for account types
const typeLabels: Record<string, string> = {
  expense: 'Expenses',
  cogs: 'Cost of Goods Sold',
  asset: 'Assets',
  liability: 'Liabilities',
  equity: 'Equity',
  revenue: 'Revenue',
  other_income: 'Other Income',
  other_expense: 'Other Expenses',
}

const groupedAccounts = computed(() => {
  const groups: Record<string, any[]> = {}
  
  props.accounts.forEach(acc => {
    const type = acc.type || 'other'
    if (!groups[type]) {
      groups[type] = []
    }
    groups[type].push(acc)
  })

  // Sort groups order if needed, or just return as is
  return groups
})

onMounted(() => {
  if (props.suggestion) {
    form.allocations[0].account_id = props.suggestion.account_id
    form.allocations[0].description = props.suggestion.description
  }
})

const submit = () => {
  form.post(`/${company.slug}/banking/resolve/create`, {
    preserveScroll: true,
    onSuccess: () => emit('success'),
  })
}
</script>

<template>
  <div class="space-y-4">
    <div class="grid gap-4">
      <div class="space-y-2">
        <Label>Category</Label>
        <Select v-model="form.allocations[0].account_id">
          <SelectTrigger>
            <SelectValue placeholder="Select account..." />
          </SelectTrigger>
          <SelectContent class="max-h-[300px]">
            <template v-for="(accounts, type) in groupedAccounts" :key="type">
              <SelectGroup>
                <SelectLabel class="px-2 py-1.5 text-xs font-semibold text-muted-foreground uppercase bg-muted/20">
                  {{ typeLabels[type] || type }}
                </SelectLabel>
                <SelectItem v-for="acc in accounts" :key="acc.id" :value="acc.id">
                  <span class="font-mono text-xs text-muted-foreground mr-2">{{ acc.code }}</span>
                  {{ acc.name }}
                </SelectItem>
              </SelectGroup>
            </template>
          </SelectContent>
        </Select>
      </div>

      <div class="space-y-2">
        <Label>Description</Label>
        <Input v-model="form.allocations[0].description" />
      </div>

      <!-- Advanced: Add splitting later -->
    </div>

    <div class="flex justify-end pt-2">
      <Button @click="submit" :disabled="form.processing || !form.allocations[0].account_id">
        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
        Create Transaction
      </Button>
    </div>
  </div>
</template>
