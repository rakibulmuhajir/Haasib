<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Loader2 } from 'lucide-vue-next'

interface Props {
  transaction: any
}

const props = defineProps<Props>()
const emit = defineEmits(['success'])
const page = usePage()
const company = page.props.company as any

const form = useForm({
  bank_transaction_id: props.transaction.id,
  note: '',
})

const submit = () => {
  form.post(`/${company.slug}/banking/resolve/park`, {
    preserveScroll: true,
    onSuccess: () => emit('success'),
  })
}
</script>

<template>
  <div class="space-y-4">
    <div class="space-y-2">
      <Label>Why are you parking this?</Label>
      <Textarea 
        v-model="form.note" 
        placeholder="e.g. Not sure what this charge is, please check."
        class="min-h-[100px]"
      />
    </div>

    <div class="flex justify-end pt-2">
      <Button variant="secondary" @click="submit" :disabled="form.processing || !form.note">
        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
        Park for Review
      </Button>
    </div>
  </div>
</template>
