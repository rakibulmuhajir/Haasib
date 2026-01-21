<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import UniversalDashboardLayout from '@/layouts/UniversalDashboardLayout.vue'
import TransactionCard from './components/TransactionCard.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { useLexicon } from '@/composables/useLexicon'

interface Props {
  transactions: any[]
  expenseAccounts: any[]
  incomeAccounts: any[]
  bankAccounts: any[]
  balanceExplainer?: {
    feed_balance: number
    ledger_balance: number
    difference: number
    is_balanced: boolean
    explanations: Array<{ label: string, amount: number }>
    currency: string
  }
}

const props = defineProps<Props>()

const { t } = useLexicon()

const formatCurrency = (val: number, currency = 'USD') => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: currency
  }).format(val)
}
</script>

<template>
  <Head :title="t('bankFeed')" />
  
  <UniversalDashboardLayout :title="t('bankFeed')" :subtitle="t('bankFeedSubtitle')">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- Left Column: The Stream -->
      <div class="lg:col-span-2 space-y-6">
        <div v-if="props.transactions.length === 0" class="text-center py-12 text-muted-foreground bg-muted/20 rounded-lg border border-dashed">
          <p class="text-lg font-medium">{{ t('noTransactions') }}</p>
          <p class="text-sm">{{ t('noTransactionsDesc') }}</p>
        </div>

        <div v-else>
          <TransactionCard 
            v-for="transaction in props.transactions" 
            :key="transaction.id"
            :transaction="transaction"
            :expense-accounts="props.expenseAccounts"
            :income-accounts="props.incomeAccounts"
            :bank-accounts="props.bankAccounts"
          />
        </div>
      </div>

      <!-- Right Column: Safety Nets -->
      <div class="space-y-6">
        <!-- Balance Explainer Widget -->
        <Card v-if="props.balanceExplainer">
          <CardHeader>
            <CardTitle class="text-sm font-medium">Balance Overview</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="space-y-4">
              <div v-if="props.balanceExplainer.is_balanced" class="bg-green-50 dark:bg-green-900/20 p-3 rounded text-sm text-green-800 dark:text-green-200 flex items-center gap-2">
                <span class="text-lg">✓</span>
                <div>
                  <div class="font-semibold">Balances Match</div>
                  System and Bank are in sync.
                </div>
              </div>

              <div class="flex justify-between items-center pb-2 border-b">
                <span class="text-sm text-muted-foreground">{{ t('bankFeedBalanceFeed') }}</span>
                <span class="font-bold">{{ formatCurrency(props.balanceExplainer.feed_balance, props.balanceExplainer.currency) }}</span>
              </div>
              <div class="flex justify-between items-center pb-2 border-b">
                <span class="text-sm text-muted-foreground">{{ t('bankFeedBalanceBooks') }}</span>
                <span class="font-bold">{{ formatCurrency(props.balanceExplainer.ledger_balance, props.balanceExplainer.currency) }}</span>
              </div>

              <div v-if="!props.balanceExplainer.is_balanced">
                <div class="flex justify-between items-center pb-2 border-b text-red-600">
                  <span class="text-sm font-medium">Difference</span>
                  <span class="font-bold">{{ formatCurrency(props.balanceExplainer.difference, props.balanceExplainer.currency) }}</span>
                </div>

                <div class="mt-4 bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded text-sm text-yellow-800 dark:text-yellow-200">
                  <div class="font-semibold mb-2">Why the difference?</div>
                  <ul class="space-y-1 list-disc list-inside">
                    <li v-for="(explanation, idx) in props.balanceExplainer.explanations" :key="idx">
                      {{ explanation.label }} ({{ formatCurrency(explanation.amount, props.balanceExplainer.currency) }})
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="text-sm font-medium">Review Queue</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground">0 items parked for review.</p>
          </CardContent>
        </Card>
      </div>

    </div>
  </UniversalDashboardLayout>
</template>
