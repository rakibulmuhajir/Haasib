<script setup lang="ts">
import { computed } from 'vue'
import { format } from 'date-fns'
import Card from 'primevue/card'
import Badge from 'primevue/badge'
import { useFormatting } from '@/composables/useFormatting'

interface JournalLine {
  id: number
  line_number: number
  description: string
  debit_amount: number
  credit_amount: number
  ledger_account: {
    id: string
    code: string
    name: string
    type: string
  }
}

interface Props {
  lines: JournalLine[]
  showAccountType?: boolean
  showLineNumbers?: boolean
  currency?: string
}

const props = withDefaults(defineProps<Props>(), {
  showAccountType: true,
  showLineNumbers: true,
  currency: 'USD'
})

const emit = defineEmits<{
  lineClick: [line: JournalLine]
}>()

// Get formatting utilities
const { formatMoney } = useFormatting()

// Get account name with code
const getAccountName = (account: JournalLine['ledger_account']) => {
  return account ? `${account.code} - ${account.name}` : 'Unknown'
}

// Totals
const totalDebit = computed(() => 
  props.lines.reduce((sum, line) => sum + Number(line.debit_amount), 0)
)

const totalCredit = computed(() => 
  props.lines.reduce((sum, line) => sum + Number(line.credit_amount), 0)
)

const isBalanced = computed(() => 
  Math.abs(totalDebit.value - totalCredit.value) < 0.01
)

// Handle line click
const handleLineClick = (line: JournalLine) => {
  emit('lineClick', line)
}
</script>

<template>
  <Card>
    <template #title>Journal Lines ({{ lines.length }})</template>
    <template #content>
      <div class="space-y-3">
        <!-- Header Row -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 px-4 pb-2 text-xs font-medium text-gray-500 uppercase tracking-wider">
          <div v-if="showLineNumbers" class="md:col-span-1">#</div>
          <div :class="showLineNumbers ? 'md:col-span-4' : 'md:col-span-5'">Account</div>
          <div class="md:col-span-3">Description</div>
          <div class="md:col-span-2 text-right">Debit</div>
          <div class="md:col-span-2 text-right">Credit</div>
        </div>

        <!-- Journal Lines -->
        <div 
          v-for="line in lines" 
          :key="line.id"
          class="grid grid-cols-1 md:grid-cols-12 gap-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer"
          @click="handleLineClick(line)"
        >
          <!-- Line Number -->
          <div v-if="showLineNumbers" class="md:col-span-1 flex items-center">
            <span class="text-sm font-medium text-gray-500">
              #{{ line.line_number }}
            </span>
          </div>
          
          <!-- Account -->
          <div :class="showLineNumbers ? 'md:col-span-4' : 'md:col-span-5'">
            <div class="text-sm font-medium text-gray-900 dark:text-white">
              {{ getAccountName(line.ledger_account) }}
            </div>
            <div v-if="showAccountType && line.ledger_account?.type" class="text-xs text-gray-500">
              {{ line.ledger_account.type }}
            </div>
          </div>
          
          <!-- Description -->
          <div class="md:col-span-3">
            <div class="text-sm text-gray-900 dark:text-white">
              {{ line.description || 'No description' }}
            </div>
          </div>
          
          <!-- Debit -->
          <div class="md:col-span-2 text-right">
            <div v-if="line.debit_amount > 0" class="text-sm font-medium text-green-600">
              {{ formatMoney(line.debit_amount) }}
            </div>
            <div v-else class="text-sm text-gray-400">
              —
            </div>
          </div>
          
          <!-- Credit -->
          <div class="md:col-span-2 text-right">
            <div v-if="line.credit_amount > 0" class="text-sm font-medium text-red-600">
              {{ formatMoney(line.credit_amount) }}
            </div>
            <div v-else class="text-sm text-gray-400">
              —
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="lines.length === 0" class="text-center py-8">
          <i class="fas fa-receipt text-4xl text-gray-300 mb-3"></i>
          <p class="text-gray-500">No journal lines found</p>
        </div>
      </div>
    </template>
    
    <!-- Footer with totals -->
    <template v-if="lines.length > 0" #footer>
      <div class="space-y-3">
        <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
          <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div :class="showLineNumbers ? 'md:col-span-8' : 'md:col-span-8'"></div>
            <div class="md:col-span-2 text-right">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Debit:</span>
              <div class="text-lg font-semibold text-green-600">
                {{ formatMoney(totalDebit) }}
              </div>
            </div>
            <div class="md:col-span-2 text-right">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Credit:</span>
              <div class="text-lg font-semibold text-red-600">
                {{ formatMoney(totalCredit) }}
              </div>
            </div>
          </div>
          
          <div class="flex justify-center mt-3">
            <Badge 
              :severity="isBalanced ? 'success' : 'danger'"
              :value="isBalanced ? 'Balanced' : `Unbalanced by ${formatMoney(Math.abs(totalDebit - totalCredit))}`"
              size="large"
            />
          </div>
        </div>
      </div>
    </template>
  </Card>
</template>

<style scoped>
:deep(.p-card) {
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

:deep(.p-card-footer) {
  background-color: transparent;
}
</style>