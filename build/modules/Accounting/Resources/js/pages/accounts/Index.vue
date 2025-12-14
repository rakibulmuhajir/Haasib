<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { HelpCircle, PlusCircle, Pencil } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface AccountRow {
  id: string
  parent_id: string | null
  code: string
  name: string
  type: string
  subtype: string
  description: string | null
  is_active: boolean
}

const props = defineProps({
  company: {
    type: Object as () => CompanyRef,
    required: true
  },
  accounts: {
    type: Array as () => AccountRow[],
    required: true
  }
})

const activeTab = ref('assets')

// --- Configuration Maps ---

// 1. Tab Definitions
const tabs = [
  { key: 'assets', label: 'Assets', types: ['asset'] },
  { key: 'liabilities', label: 'Liabilities & Credit Cards', types: ['liability'] },
  { key: 'income', label: 'Income', types: ['revenue', 'other_income'] },
  { key: 'expenses', label: 'Expenses', types: ['expense', 'cogs', 'other_expense'] },
  { key: 'equity', label: 'Equity', types: ['equity'] },
]

// 2. Section Definitions (The "Bands")
const sectionConfig: Record<string, { label: string; subtypes: string[]; help?: string }> = {
  // Assets
  cash_bank: {
    label: 'Cash and Bank',
    subtypes: ['bank', 'cash'],
    help: 'Accounts for cash on hand and bank accounts. Use these to track money entering and leaving your business.'
  },
  money_in_transit: {
    label: 'Money in Transit',
    subtypes: ['money_in_transit'], // Assuming a subtype or mapping for this
    help: 'Funds that are moving between accounts, such as credit card settlements or bank transfers.'
  },
  receivables: {
    label: 'Expected Payments from Customers',
    subtypes: ['accounts_receivable'],
    help: 'Invoices you have sent but not yet been paid for. Tracks money owed to you.'
  },
  inventory: {
    label: 'Inventory',
    subtypes: ['inventory'],
    help: 'Value of goods you have available for sale.'
  },
  ppe: {
    label: 'Property, Plant, Equipment',
    subtypes: ['fixed_asset'],
    help: 'Long-term assets like buildings, vehicles, and computers that you use to run your business.'
  },
  depreciation: {
    label: 'Depreciation and Amortization',
    subtypes: ['accumulated_depreciation'],
    help: 'The loss of value of assets over time.'
  },
  other_assets: {
    label: 'Other Short-term Assets',
    subtypes: ['other_current_asset', 'other_asset'],
    help: 'Assets that do not fit into other categories, like prepaid expenses or security deposits.'
  },

  // Liabilities
  payables: {
    label: 'Expected Payments to Vendors',
    subtypes: ['accounts_payable'],
    help: 'Bills you have received but not yet paid. Tracks money you owe to suppliers.'
  },
  credit_cards: {
    label: 'Credit Cards',
    subtypes: ['credit_card'],
    help: 'Balances due on business credit cards.'
  },
  loans: {
    label: 'Loans and Line of Credit',
    subtypes: ['loan_payable', 'other_current_liability', 'other_liability'],
    help: 'Money borrowed that must be repaid, including bank loans and lines of credit.'
  },
  sales_tax: {
    label: 'Sales Taxes',
    subtypes: ['sales_tax_payable'], // Assuming explicit subtype, or mapped via other_current_liability + specific code logic in real app
    help: 'Taxes collected from customers that need to be remitted to the government.'
  },
  payroll_liabilities: {
    label: 'Payroll Liabilities',
    subtypes: ['payroll_liabilities'], // Placeholder for logic
    help: 'Wages withheld and taxes owed related to payroll.'
  },

  // Income
  revenue: { label: 'Income', subtypes: ['revenue'], help: 'Money earned from selling your goods or services.' },
  other_income: { label: 'Other Income', subtypes: ['other_income'], help: 'Income from sources other than regular sales, like interest or grants.' },

  // Expenses
  expense: { label: 'Operating Expenses', subtypes: ['expense'], help: 'Day-to-day costs of running your business, like rent and utilities.' },
  cogs: { label: 'Cost of Goods Sold', subtypes: ['cogs'], help: 'Direct costs to produce the goods you sold.' },
  other_expense: { label: 'Other Expenses', subtypes: ['other_expense'], help: 'Non-operating costs, like interest expense or penalties.' },

  // Equity
  equity: { label: 'Equity', subtypes: ['equity'], help: 'The owner\'s stake in the business, including capital contributions.' },
  retained_earnings: { label: 'Retained Earnings', subtypes: ['retained_earnings'], help: 'Profits reinvested in the business from previous years.' },
}

// Defines which sections appear in which tab and in what order
const tabSections: Record<string, string[]> = {
  assets: ['cash_bank', 'money_in_transit', 'receivables', 'inventory', 'ppe', 'depreciation', 'other_assets'],
  liabilities: ['credit_cards', 'loans', 'payables', 'sales_tax', 'payroll_liabilities'],
  income: ['revenue', 'other_income'],
  expenses: ['expense', 'cogs', 'other_expense'],
  equity: ['equity', 'retained_earnings']
}

// --- Computed Logic ---

const tabCounts = computed(() => {
  const counts: Record<string, number> = {}
  tabs.forEach(t => {
    counts[t.key] = props.accounts.filter(a => t.types.includes(a.type)).length
  })
  return counts
})

const groupedAccounts = computed(() => {
  const currentTab = tabs.find(t => t.key === activeTab.value)
  if (!currentTab) return []

  const sectionsForTab = tabSections[activeTab.value] || []
  
  return sectionsForTab.map(sectionKey => {
    const config = sectionConfig[sectionKey]
    
    // Find accounts matching the subtypes for this section
    const accounts = props.accounts.filter(a => 
      currentTab.types.includes(a.type) && 
      config.subtypes.includes(a.subtype)
    ).sort((a, b) => a.code.localeCompare(b.code))

    return {
      key: sectionKey,
      label: config.label,
      help: config.help,
      accounts: accounts
    }
  })
})

const handleEdit = (id: string) => {
  router.get(`/${props.company.slug}/accounts/${id}/edit`)
}

const handleCreate = () => {
  router.get(`/${props.company.slug}/accounts/create`)
}

</script>

<template>
  <Head title="Chart of Accounts" />

  <PageShell>
    <template #title>
      <div class="flex items-center gap-2">
        <span>Chart of Accounts</span>
        <Popover>
          <PopoverTrigger>
            <HelpCircle class="h-5 w-5 text-gray-400 hover:text-gray-600 cursor-pointer" />
          </PopoverTrigger>
          <PopoverContent class="w-80 p-4 text-sm text-gray-600 shadow-lg">
            The Chart of Accounts is a list of all the accounts used to record your financial transactions.
          </PopoverContent>
        </Popover>
      </div>
    </template>

    <template #actions>
      <Button variant="default" class="bg-blue-600 hover:bg-blue-700 font-semibold rounded-full px-6 shadow-sm" @click="handleCreate">
        Add a New Account
      </Button>
    </template>

    <!-- PageShell's default slot for the main content area -->
    <div class="py-8">
      <!-- 2. Category Tabs (Pills with stacked count) -->
      <div class="mb-8 flex flex-wrap gap-2">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          class="flex flex-col items-center justify-center rounded-full px-6 py-2 transition-all duration-200 border min-w-[120px]"
          :class="[
            activeTab === tab.key
              ? 'bg-[#1c2c52] text-white border-[#1c2c52] shadow-md'
              : 'bg-white text-gray-600 hover:bg-gray-100 border-gray-200'
          ]"
        >
          <span class="text-sm font-semibold">{{ tab.label }}</span>
          <span class="text-xs font-bold" :class="activeTab === tab.key ? 'text-blue-200' : 'text-gray-400'">
            {{ tabCounts[tab.key] }}
          </span>
        </button>
      </div>

      <!-- 3. Sections Group -->
      <div class="space-y-8 max-w-5xl">
        <div 
          v-for="group in groupedAccounts" 
          :key="group.key"
          class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden"
        >
          <!-- Section Header -->
          <div class="flex items-center gap-2 border-b border-gray-100 bg-gray-50/80 px-6 py-4">
            <h2 class="font-bold text-gray-800 text-base">{{ group.label }}</h2>
            <Popover>
              <PopoverTrigger>
                <HelpCircle class="h-4 w-4 text-gray-400 hover:text-gray-600 cursor-pointer" />
              </PopoverTrigger>
              <PopoverContent class="w-80 text-sm text-gray-600 p-3">
                {{ group.help }}
              </PopoverContent>
            </Popover>
          </div>

          <!-- 4. Accounts List -->
          <div class="divide-y divide-gray-100">
            <!-- Empty State -->
            <div v-if="group.accounts.length === 0" class="px-6 py-8 text-center bg-white">
              <p class="text-sm text-gray-500 italic mb-4">
                You haven't added any {{ group.label }} accounts yet.
              </p>
            </div>

            <!-- Account Rows -->
            <div 
              v-for="account in group.accounts" 
              :key="account.id"
              class="group relative flex items-start justify-between px-6 py-5 hover:bg-blue-50/30 transition-colors"
            >
              <div class="flex-1 pr-8">
                <div class="flex items-center gap-3">
                  <span class="font-bold text-gray-900">{{ account.name }}</span>
                </div>
                
                <!-- Status Line -->
                <div class="mt-1 text-xs text-gray-500">
                  <span v-if="!account.is_active" class="text-amber-600 font-medium mr-2">Archived</span>
                  <span>No transactions for this account</span> <!-- Placeholder for real transaction check -->
                </div>

                <!-- Description (Single Column Layout) -->
                <div v-if="account.description" class="mt-2 text-sm text-gray-600 leading-relaxed max-w-3xl">
                  {{ account.description }}
                </div>
              </div>

              <!-- Edit Action -->
              <button 
                @click="handleEdit(account.id)"
                class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-blue-600 p-2"
                title="Edit Account"
              >
                <Pencil class="h-4 w-4" />
              </button>
            </div>
          </div>

          <!-- 5. "Add a new account" footer -->
          <div class="border-t border-gray-100 bg-gray-50/30 px-6 py-3">
            <button 
              @click="handleCreate"
              class="flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-800 transition-colors"
            >
              <PlusCircle class="h-4 w-4" />
              Add a new account
            </button>
          </div>
        </div>
      </div>
    </div>
  </PageShell>
</template>