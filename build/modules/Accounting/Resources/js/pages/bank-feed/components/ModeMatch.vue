<script setup lang="ts">
import { ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Label } from '@/components/ui/label'
import { Loader2 } from 'lucide-vue-next'

interface Match {
  id: string
  type: 'payment' | 'bill_payment'
  description: string
  amount: number
}

interface Props {
  transaction: any
  matches: Match[]
}

const props = defineProps<Props>()
const emit = defineEmits(['success'])
const page = usePage()
const company = page.props.company as any

const selectedMatchId = ref(props.matches?.[0]?.id)

const form = useForm({
  bank_transaction_id: props.transaction.id,
  target_type: '',
  target_id: '',
})

const submit = () => {
  const match = props.matches.find(m => m.id === selectedMatchId.value)
  if (!match) return

  form.target_type = match.type
  form.target_id = match.id

  form.post(`/${company.slug}/banking/resolve/match`, {
    preserveScroll: true,
    onSuccess: () => emit('success'),
  })
}
</script>

<template>
  <div class="space-y-4">
    <div class="text-sm text-muted-foreground">
      We found {{ matches?.length || 0 }} existing record(s) that match this transaction.
    </div>

    <RadioGroup v-model="selectedMatchId" class="space-y-3">
      <div v-for="match in matches" :key="match.id" 
        class="flex items-center space-x-3 space-y-0 rounded-md border p-3 bg-background hover:bg-accent cursor-pointer"
        :class="{ 'border-primary ring-1 ring-primary': selectedMatchId === match.id }"
        @click="selectedMatchId = match.id"
      >
        <RadioGroupItem :value="match.id" :id="match.id" />
        <Label :for="match.id" class="flex-1 cursor-pointer">
          <div class="flex justify-between items-center font-normal">
            <span>{{ match.description }}</span>
            <span class="font-medium font-mono">${{ Math.abs(Number(match.amount)).toFixed(2) }}</span>
          </div>
        </Label>
      </div>
    </RadioGroup>

    <div class="flex justify-end pt-2">
      <Button @click="submit" :disabled="form.processing">
        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
        Confirm Match
      </Button>
    </div>
  </div>
</template>
