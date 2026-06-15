<script setup lang="ts">
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import type { BreadcrumbItem } from '@/types'
import {
  BookOpen,
  Building,
  Calendar,
  Landmark,
  Settings,
  Fuel,
  Warehouse,
  Gauge,
  TrendingUp,
  UserPlus,
  HandCoins,
  CreditCard,
  Receipt,
  Droplet,
  Package,
  CircleDollarSign,
  CheckCircle,
  FileText,
  AlertTriangle,
  Lightbulb,
  ArrowRight,
} from 'lucide-vue-next'

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/dashboard` },
  { title: 'Setup & Help Guide', href: `/${companySlug.value}/fuel/guide` },
])

const stepGroups = [
  {
    title: '1. Before You Begin',
    icon: FileText,
    content: [
      { label: 'What You Need', detail: 'Company name, NTN, bank details, fuel products, tank/pump info, current rates, employee list, opening cash' },
      { label: 'Setup Time', detail: '30-45 minutes for full setup, 5-10 minutes per day for operations' },
    ],
  },
  {
    title: '2. Account & Company Creation',
    icon: Building,
    content: [
      { label: 'Register', detail: 'Create your account with email, phone, and password. Verify your email via the link sent to your inbox.' },
      { label: 'Create Company', detail: 'Choose "Guided Setup" (recommended) or "Manual Setup". Enter station name, select "Fuel Station" as industry, and provide NTN number.' },
      { label: 'Result', detail: 'Your company is registered and the system knows you are a fuel station business.' },
    ],
  },
  {
    title: '3. Station Settings & Identity',
    icon: Settings,
    content: [
      { label: 'Station Info', detail: 'Set currency to PKR, select timezone (Asia/Karachi), and choose fiscal year start month.' },
      { label: 'Shifts', detail: 'Configure 2-3 shifts per day (Day/Night or Morning/Evening/Night) with timings.' },
      { label: 'Payment Methods', detail: 'Cash, Credit/Debit Card, Mobile Wallets (Easypaisa/JazzCash), Vendor Fleet Cards, Bank Transfer, Credit Accounts.' },
    ],
  },
  {
    title: '4. Chart of Accounts (Auto-Created)',
    icon: Landmark,
    content: [
      { label: 'Assets (1000-1210)', detail: 'Operating Bank Account, Vendor Card Clearing, Card Receipts Clearing, Cash on Hand, Accounts Receivable, Fuel Inventory, Lubricants Inventory' },
      { label: 'Liabilities (2100-2210)', detail: 'Accounts Payable - Fuel Supplier, Amanat Deposits, Investor Deposits' },
      { label: 'Revenue & COGS (4100-5900)', detail: 'Fuel Sales, Shop Sales, Lubricant Sales, Cost of Goods - Fuel, Fuel Shrinkage Loss' },
      { label: 'Expenses (6100-6500)', detail: 'Investor Commission, Salaries & Wages, Cash Short/Over, Card Processing Fees, Utilities, Pump Maintenance, General Expenses' },
    ],
  },
  {
    title: '5. Define Your Fuel Products',
    icon: Fuel,
    content: [
      { label: 'Fuel Types', detail: 'Select from Petrol, Hi-Octane, Diesel, and CNG. Each gets an auto-generated SKU (e.g., FUEL-PET, FUEL-DSL).' },
      { label: 'Non-Fuel Products', detail: 'Add lubricants (engine oils, greases), coolants, and shop items for convenience store sales.' },
    ],
  },
  {
    title: '6. Configure Storage Tanks',
    icon: Warehouse,
    content: [
      { label: 'Tank Setup', detail: 'For each physical tank: enter name, code, fuel product link, capacity in liters, and current stock level.' },
      { label: 'Key Tips', detail: 'One entry per tank. If you have two petrol tanks, create two entries. Use manufacturer rated capacity. Enter current stock from your dip stick.' },
      { label: 'Dip Chart', detail: 'Optionally add a dip stick calibration chart for accurate volume conversion from physical measurements.' },
    ],
  },
  {
    title: '7. Set Up Fuel Pumps',
    icon: Gauge,
    content: [
      { label: 'Pump Setup', detail: 'For each dispenser: enter name, link to its tank, and record the current meter reading (totalizer/odometer).' },
      { label: 'Meter Reading', detail: 'This is the cumulative total liters dispensed. Find it on the pump display labeled "Total" or "Grand Total" - not the trip reset reading.' },
      { label: 'Nozzles', detail: 'For multi-nozzle pumps, configure each nozzle with its fuel product and sort order (left to right).' },
    ],
  },
  {
    title: '8. Enter Current Fuel Rates',
    icon: TrendingUp,
    content: [
      { label: 'Rate Entry', detail: 'For each fuel type: enter purchase rate (what you pay supplier), sale rate (what customers pay), and effective date.' },
      { label: 'Margin', detail: 'Calculated automatically: Sale Rate - Purchase Rate. Example: PKR 252.10 - PKR 248.50 = PKR 3.60/liter margin.' },
      { label: 'Rate History', detail: 'All rate changes are tracked. Each investment lot locks at the rate when created, preventing disputes.' },
      { label: 'OGRA Updates', detail: 'Update rates whenever OGRA announces price changes. The effective date ensures accuracy in reporting.' },
    ],
  },
  {
    title: '9. Initial Tank Readings',
    icon: Droplet,
    content: [
      { label: 'Take Dip Readings', detail: 'Use your dip stick or automatic tank gauge to measure each tank. Convert to liters using your calibration chart.' },
      { label: 'Workflow', detail: 'Draft → Confirm (manager approval) → Post. The first reading establishes your baseline for variance tracking.' },
      { label: 'Variance Detection', detail: 'Future readings are compared to expected levels. Variances help detect evaporation, theft, leaks, or meter drift.' },
    ],
  },
  {
    title: '10. Optional: Lubricants & Shop Products',
    icon: Package,
    content: [
      { label: 'Lubricants', detail: 'Add packaged oils and lubricants with SKUs (e.g., "Shell Helix HX3 20W-50"). Track inventory, cost, and sale prices.' },
      { label: 'Shop Items', detail: 'Add convenience store items like snacks, beverages, cigarettes, and spare parts.' },
    ],
  },
  {
    title: '11. Optional: Employees & Attendants',
    icon: UserPlus,
    content: [
      { label: 'Add Staff', detail: 'Add station manager, pump attendants, cashiers, accountants, and shift supervisors with names, phone, roles, and salaries.' },
      { label: 'Purpose', detail: 'Enables shift handovers, cash collection tracking per attendant, and payroll processing.' },
    ],
  },
  {
    title: '12. Optional: Investors & Commission',
    icon: HandCoins,
    content: [
      { label: 'Lot Model', detail: 'Each investment creates a "lot" with locked entitlement rate. Entitled Units = Investment ÷ Purchase Rate at time of investment.' },
      { label: 'Commission', detail: 'Commission = liters consumed × (Sale Rate - Purchase Rate). Rate changes do not affect existing lots (FIFO consumption).' },
      { label: 'Payment', detail: 'Pay commission periodically. The system tracks outstanding commissions per investor.' },
    ],
  },
  {
    title: '13. Optional: Amanat (Trust Deposits)',
    icon: HandCoins,
    content: [
      { label: 'How It Works', detail: 'Customer deposits money → Balance is tracked → Customer buys fuel, balance decreases → Can withdraw remaining balance.' },
      { label: 'Common Use', detail: 'Fleet operators pre-pay for fuel. Creates a liability until fuel is consumed.' },
    ],
  },
  {
    title: '14. Optional: Credit Customers',
    icon: CreditCard,
    content: [
      { label: 'Setup', detail: 'Add customers with credit limits and payment terms (e.g., Net 30 days). Record sales as "Credit Sale" type.' },
      { label: 'Tracking', detail: 'Monitor outstanding balances, block customers who exceed limits or default, and record collections when they pay.' },
    ],
  },
  {
    title: '15. Optional: Vendor Card Settlement',
    icon: Receipt,
    content: [
      { label: 'Fleet Cards', detail: 'Accept PSO, Shell, Total Parco fleet cards. Sales recorded as "Vendor Card" flow to Vendor Card Clearing (receivable).' },
      { label: 'Settlement', detail: 'Weekly/monthly: vendor pays minus fees. Record the settlement and mark invoices as paid. Fees go to Card Processing Fees expense.' },
    ],
  },
  {
    title: '16. Record Opening Cash & First Daily Close',
    icon: CircleDollarSign,
    content: [
      { label: 'Opening Cash', detail: 'Record physical cash in drawer/safe and bank balance as of today.' },
      { label: 'Daily Close Steps', detail: 'Enter closing pump readings → Tank dip measurements → Sales summary by type & payment method → Cash movements → Review & submit.' },
      { label: 'Locking', detail: 'Manager locks daily close after review. Changes require an amendment (preserving audit trail). Lock month to prevent edits to past periods.' },
    ],
  },
]

const dailyOps = [
  {
    time: 'Morning',
    tasks: [
      'Record opening pump readings (2 min)',
      'Check tank levels via dip readings (5 min)',
      'Verify cash in drawer (2 min)',
    ],
  },
  {
    time: 'During Day',
    tasks: [
      'Record fuel sales (can batch at end of day)',
      'Record Amanat deposits when customers deposit',
      'Record credit sales for credit customers',
      'Record handovers when attendants change shifts',
    ],
  },
  {
    time: 'End of Day',
    tasks: [
      'Record closing pump readings (2 min)',
      'Take tank dip readings (5 min)',
      'Count cash in drawer (5 min)',
      'Perform daily close (10 min)',
      'Lock daily close (1 min)',
    ],
  },
  {
    time: 'Weekly',
    tasks: [
      'Process vendor card settlements',
      'Pay investor commission as needed',
      'Review AR aging report',
      'Reconcile bank accounts',
    ],
  },
  {
    time: 'Monthly',
    tasks: [
      'Generate sales report',
      'Generate shrinkage report',
      'Review profit & loss',
      'Lock month (prevent edits to closed periods)',
      'Process payroll',
    ],
  },
]

const troubleshooting = [
  {
    issue: 'Pump reading seems wrong',
    causes: 'Wrong number entered, pump was reset or replaced, multiple nozzles on one pump',
    solution: 'Verify reading on pump\'s physical display. Check if pump was serviced (meter may have been reset). For multi-nozzle pumps, ensure correct totalizer.',
  },
  {
    issue: 'Tank variance is too high',
    causes: 'Inaccurate dip reading, temperature variation, actual theft or leak, pump meter inaccuracy',
    solution: 'Take another dip reading. Check for visible leaks. Compare with pump readings (total dispensed should match tank reduction). Consider pump calibration.',
  },
  {
    issue: 'Cash doesn\'t match expected',
    causes: 'Incorrectly recorded sale, misplaced cash, wrong change given, unrecorded expense',
    solution: 'Double-check all sales entries. Verify handover amounts. Check if cash was used for expenses. Record difference as "Cash Short/Over".',
  },
  {
    issue: 'Can\'t submit daily close',
    causes: 'Missing required field, previous daily close not locked, period is closed',
    solution: 'Check all fields marked with *. Ensure previous day\'s close is locked. Contact manager if period is closed.',
  },
  {
    issue: 'Investor commission seems wrong',
    causes: 'Rate entered incorrectly for the lot, fuel consumption not tracked properly, multiple lots being consumed',
    solution: 'Check the lot\'s locked purchase rate. Verify fuel consumption records. Review the FIFO consumption order.',
  },
]

const glossary = [
  { term: 'Amanat', meaning: 'Trust deposit - customer pre-pays for fuel' },
  { term: 'AR', meaning: 'Accounts Receivable - money owed to you' },
  { term: 'AP', meaning: 'Accounts Payable - money you owe' },
  { term: 'COGS', meaning: 'Cost of Goods Sold - what you paid for the fuel you sold' },
  { term: 'Daily Close', meaning: 'End-of-day reconciliation of all transactions' },
  { term: 'Dip Reading', meaning: 'Physical measurement of fuel in a tank using a dip stick' },
  { term: 'FIFO', meaning: 'First In, First Out - method of consuming investor lots' },
  { term: 'Handover', meaning: 'Transfer of cash responsibility between attendants' },
  { term: 'Investor Lot', meaning: 'A specific investment with a locked rate' },
  { term: 'Margin', meaning: 'Difference between sale price and purchase price' },
  { term: 'NTN', meaning: 'National Tax Number (Pakistan)' },
  { term: 'OGRA', meaning: 'Oil and Gas Regulatory Authority - sets fuel prices in Pakistan' },
  { term: 'Shrinkage', meaning: 'Loss of fuel due to evaporation, theft, or measurement error' },
  { term: 'Totalizer', meaning: 'The cumulative meter reading on a pump' },
  { term: 'Variance', meaning: 'Difference between expected and actual (tank levels, cash)' },
  { term: 'Vendor Card', meaning: 'Fleet card (e.g., PSO, Shell, Total Parco)' },
]

const bestPractices = [
  { text: 'Do daily close every day', detail: 'Skipping days makes it much harder to catch up and increases error risk.' },
  { text: 'Take dip readings seriously', detail: 'They are your best defense against theft and leakage.' },
  { text: 'Lock daily closes', detail: 'Prevents unauthorized changes and maintains audit integrity.' },
  { text: 'Record handovers promptly', detail: 'When an attendant finishes their shift, record the handover immediately.' },
  { text: 'Reconcile bank accounts weekly', detail: 'Catch discrepancies between your records and the bank early.' },
]

const commonMistakes = [
  'Entering pump readings in the wrong order (opening vs closing)',
  'Forgetting to record cash expenses before daily close',
  'Not linking tanks to the correct fuel product',
  'Entering rates without the correct effective date',
]
</script>

<template>
  <Head title="Fuel Station Setup & Help Guide" />

  <PageShell
    title="Setup & Help Guide"
    description="Complete guide for setting up and operating your fuel station in Haasib."
    :icon="BookOpen"
    :breadcrumbs="breadcrumbs"
  >
    <!-- Prerequisites -->
    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="flex items-center gap-2 text-base">
          <FileText class="h-4 w-4 text-sky-600" />
          Before You Begin
        </CardTitle>
        <CardDescription>Estimated setup time: 30-45 minutes. Daily operations: 5-10 minutes.</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="rounded-xl border border-border/70 bg-surface-1 p-4">
            <p class="text-sm font-medium text-text-primary">What You Need</p>
            <ul class="mt-2 space-y-1 text-sm text-text-secondary">
              <li>• Company name and NTN number</li>
              <li>• Bank account details</li>
              <li>• Fuel products you sell (Petrol, Diesel, Hi-Octane)</li>
              <li>• Tank capacities & current levels</li>
              <li>• Pump meter readings (totalizer)</li>
              <li>• Current purchase and sale rates</li>
              <li>• Employee/attendant names</li>
              <li>• Opening cash balance</li>
            </ul>
          </div>
          <div class="rounded-xl border border-border/70 bg-gradient-to-br from-emerald-500/10 to-sky-500/10 p-4">
            <p class="text-sm font-medium text-text-primary">Quick Links</p>
            <div class="mt-2 space-y-2 text-sm">
              <a :href="`/${companySlug}/fuel/onboarding`" class="flex items-center gap-2 text-sky-600 hover:underline">
                <ArrowRight class="h-3.5 w-3.5" /> Setup Wizard (step-by-step)
              </a>
              <a :href="`/${companySlug}/fuel/dashboard`" class="flex items-center gap-2 text-sky-600 hover:underline">
                <ArrowRight class="h-3.5 w-3.5" /> Fuel Dashboard (daily operations)
              </a>
              <a :href="`/${companySlug}/fuel/daily-close/create`" class="flex items-center gap-2 text-sky-600 hover:underline">
                <ArrowRight class="h-3.5 w-3.5" /> Daily Close (end of day)
              </a>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Setup Steps -->
    <div class="grid gap-4 lg:grid-cols-2">
      <Card
        v-for="group in stepGroups"
        :key="group.title"
        class="border-border/80"
      >
        <CardHeader class="pb-2">
          <CardTitle class="flex items-center gap-2 text-sm">
            <component :is="group.icon" class="h-4 w-4 text-sky-600" />
            {{ group.title }}
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <div
            v-for="item in group.content"
            :key="item.label"
            class="rounded-lg border border-border/60 bg-muted/20 p-3"
          >
            <p class="text-xs font-semibold text-text-primary">{{ item.label }}</p>
            <p class="mt-1 text-xs text-text-secondary">{{ item.detail }}</p>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Daily Operations -->
    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="flex items-center gap-2 text-base">
          <Calendar class="h-4 w-4 text-sky-600" />
          Daily Operations Quick Reference
        </CardTitle>
        <CardDescription>What to do and when to do it.</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
          <div
            v-for="group in dailyOps"
            :key="group.time"
            class="rounded-xl border border-border/70 bg-surface-1 p-4"
          >
            <Badge class="mb-3 bg-sky-600 text-white hover:bg-sky-600">{{ group.time }}</Badge>
            <ul class="space-y-2">
              <li v-for="task in group.tasks" :key="task" class="flex items-start gap-2 text-sm text-text-secondary">
                <CheckCircle class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-emerald-500" />
                {{ task }}
              </li>
            </ul>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Troubleshooting -->
    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="flex items-center gap-2 text-base">
          <AlertTriangle class="h-4 w-4 text-amber-600" />
          Troubleshooting Common Issues
        </CardTitle>
        <CardDescription>Solutions for the most common problems.</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="grid gap-4 lg:grid-cols-2">
          <div
            v-for="item in troubleshooting"
            :key="item.issue"
            class="rounded-xl border border-border/70 bg-surface-1 p-4"
          >
            <p class="font-semibold text-text-primary">{{ item.issue }}</p>
            <p class="mt-2 text-xs text-text-tertiary"><span class="font-medium">Causes:</span> {{ item.causes }}</p>
            <p class="mt-2 text-sm text-text-secondary"><span class="font-medium text-emerald-600">Solution:</span> {{ item.solution }}</p>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Best Practices & Mistakes -->
    <div class="grid gap-4 lg:grid-cols-2">
      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="flex items-center gap-2 text-base">
            <Lightbulb class="h-4 w-4 text-amber-500" />
            Best Practices
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <div
            v-for="item in bestPractices"
            :key="item.text"
            class="flex items-start gap-3 rounded-lg border border-border/60 bg-muted/20 p-3"
          >
            <CheckCircle class="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-500" />
            <div>
              <p class="text-sm font-semibold text-text-primary">{{ item.text }}</p>
              <p class="mt-0.5 text-xs text-text-secondary">{{ item.detail }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="flex items-center gap-2 text-base">
            <AlertTriangle class="h-4 w-4 text-rose-500" />
            Common Mistakes to Avoid
          </CardTitle>
        </CardHeader>
        <CardContent>
          <ul class="space-y-3">
            <li v-for="mistake in commonMistakes" :key="mistake" class="flex items-start gap-3 text-sm text-text-secondary">
              <span class="mt-1 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-rose-100 text-xs font-bold text-rose-600">!</span>
              {{ mistake }}
            </li>
          </ul>
        </CardContent>
      </Card>
    </div>

    <!-- Glossary -->
    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="flex items-center gap-2 text-base">
          <BookOpen class="h-4 w-4 text-sky-600" />
          Glossary of Terms
        </CardTitle>
        <CardDescription>Key terms used in fuel station management.</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <div
            v-for="item in glossary"
            :key="item.term"
            class="rounded-lg border border-border/60 bg-muted/20 p-3"
          >
            <p class="text-sm font-semibold text-text-primary">{{ item.term }}</p>
            <p class="mt-1 text-xs text-text-secondary">{{ item.meaning }}</p>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Also Available -->
    <Card class="border-border/80">
      <CardHeader>
        <CardTitle class="flex items-center gap-2 text-base">
          <FileText class="h-4 w-4 text-sky-600" />
          Full Documentation
        </CardTitle>
        <CardDescription>A complete step-by-step guide is also available as a printable document.</CardDescription>
      </CardHeader>
      <CardContent>
        <p class="text-sm text-text-secondary">
          The full onboarding guide with detailed explanations, examples, and checklists is available in the project documentation at
          <code class="rounded bg-muted px-1.5 py-0.5 text-xs font-mono">docs/fuel-station-onboarding-guide.md</code>.
          This in-app guide provides all the essential information you need for daily use.
        </p>
      </CardContent>
    </Card>
  </PageShell>
</template>
