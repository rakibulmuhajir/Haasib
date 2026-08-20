<script setup lang="ts">
import { ref, computed } from 'vue'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import ModeMatch from './ModeMatch.vue'
import ModeCreate from './ModeCreate.vue'
import ModeTransfer from './ModeTransfer.vue'
import ModePark from './ModePark.vue'
import MoneyText from '@/components/MoneyText.vue'

interface Props {
  transaction: any // Typed properly in real app
  expenseAccounts: any[]
  incomeAccounts: any[]
  bankAccounts: any[]
}

const props = defineProps<Props>()

const emit = defineEmits(['resolve'])

const amount = computed(() => Number(props.transaction.amount))
const isSpend = computed(() => amount.value < 0)
const currency = computed(() => props.transaction.bank_account?.currency || 'USD')

// Determine default tab based on suggestions
const defaultTab = computed(() => {
  if (props.transaction.suggestions?.match?.length) return 'match'
  return 'create'
})

const activeTab = ref(defaultTab.value)

const formatDate = (dateString: string) => {
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  }).format(new Date(dateString))
}

</script>

<template>
  <Card class="mb-4 shadow-sm hover:shadow-md transition-shadow duration-200 border-l-4"
    :class="[isSpend ? 'border-l-red-500' : 'border-l-green-500']"
    variant="detail"
  >
    <CardHeader class="pb-2">
      <div class="flex justify-between items-start">
        <div class="space-y-1">
          <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <span>{{ formatDate(props.transaction.transaction_date) }}</span>
            <span>&bull;</span>
            <span class="font-medium text-foreground">{{ props.transaction.payee_name || 'Unknown Payee' }}</span>
          </div>
          <h3 class="font-semibold text-lg leading-tight">{{ props.transaction.description }}</h3>
        </div>
        <div class="text-right">
<!-- Money leaving a bank account is the business operating. Painting
               every debit red made the feed look like a list of problems, and
               left nothing to say with when one actually was. The sign carries
               the direction, as it does everywhere else. -->
          <div class="text-lg font-bold">
            {{ isSpend ? '-' : '+' }}<MoneyText :amount="Math.abs(amount)" :currency="currency" />
          </div>
          <Badge variant="outline" class="mt-1 text-xs" v-if="props.transaction.suggestions?.match?.length">
            Match Found
          </Badge>
        </div>
      </div>
    </CardHeader>

    <CardContent>
      <Tabs v-model="activeTab" class="w-full">
        <TabsList class="grid w-full grid-cols-4 mb-4">
          <TabsTrigger value="match" :disabled="!props.transaction.suggestions?.match?.length">Match</TabsTrigger>
          <TabsTrigger value="create">Create</TabsTrigger>
          <TabsTrigger value="transfer">Transfer</TabsTrigger>
          <TabsTrigger value="park">Park</TabsTrigger>
        </TabsList>

        <div class="bg-muted/30 p-4 rounded-lg border">
          <TabsContent value="match" class="mt-0">
            <ModeMatch 
              :transaction="transaction" 
              :matches="transaction.suggestions?.match" 
              @success="$emit('resolve')"
            />
          </TabsContent>

          <TabsContent value="create" class="mt-0">
            <ModeCreate 
              :transaction="transaction"
              :accounts="isSpend ? expenseAccounts : incomeAccounts"
              :suggestion="transaction.suggestions?.create"
              @success="$emit('resolve')"
            />
          </TabsContent>

          <TabsContent value="transfer" class="mt-0">
            <ModeTransfer 
              :transaction="transaction"
              :accounts="bankAccounts"
              @success="$emit('resolve')"
            />
          </TabsContent>

          <TabsContent value="park" class="mt-0">
            <ModePark 
              :transaction="transaction"
              @success="$emit('resolve')"
            />
          </TabsContent>
        </div>
      </Tabs>
    </CardContent>
  </Card>
</template>