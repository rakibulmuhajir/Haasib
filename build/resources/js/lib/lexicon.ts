/**
 * Lexicon (terminology dictionary)
 *
 * The single source of truth for the words the product uses. One vocabulary,
 * not two: every term used to carry an `owner` and an `accountant` spelling,
 * and the accountant half was never reachable — `getTerm` was called with
 * 'owner' from every path. Two vocabularies also meant every user-facing
 * string existed twice across 193 pages, which is a drift problem waiting to
 * happen and doubles the work when Urdu localization arrives.
 *
 * The house rule for choosing a word: plain language for descriptions, column
 * headers and actions; real accounting terms only where they name an artifact
 * the reader navigates to. A column header reads `Money out`, never `Credit`;
 * the page it links to is still called `Journals`. Anything a non-accountant
 * might not know gets an <Explain>, not a second dictionary.
 *
 * @see docs/frontend-experience-contract.md Section 14: Language & Terminology
 *
 * Usage:
 *   const { t, tpl } = useLexicon()
 *   t('moneyIn')                             // "Money In"
 *   tpl('transactionsToReview', { count: 5 }) // "5 transactions to review"
 */

// -----------------------------------------------------------------------------
// Type Definitions
// -----------------------------------------------------------------------------

export interface TermDictionary {
  [key: string]: string
}

// -----------------------------------------------------------------------------
// Core Financial Concepts
// -----------------------------------------------------------------------------

export const coreTerms: TermDictionary = {
  // Income/Revenue
  moneyIn: 'Money In',
  income: 'Income',
  sales: 'Sales',

  // Expenses
  moneyOut: 'Money Out',
  expenses: 'Spending',
  costs: 'Costs',

  // Profit
  profit: 'Profit',
  moneyMade: 'Money you made',
  grossProfit: 'Gross Profit',

  // Cash & Banks
  cash: 'Cash',
  cashOnHand: 'Cash on hand',
  bankBalance: 'Bank Balance',

  // Categories vs Accounts
  category: 'Category',
  categories: 'Categories',
  chartOfAccounts: 'Categories',

  // Common UI Terms
  back: 'Back',
  receiveStock: 'Receive stock',
  apply: 'Apply',
  dateRange: 'Date range',
  startDate: 'Start date',
  endDate: 'End date',
  cancel: 'Cancel',
  save: 'Save',
  saveDraft: 'Save Draft',
  optional: 'optional',
  name: 'Name',
  email: 'Email',
  createAndSelect: 'Create & Select',
  addDetailsLater: 'You can add more details later.',
  selectCategory: 'Select a category...',
  addMoreDetails: 'Add more details',
  reference: 'Reference',
  referencePlaceholder: 'PO#, Order#, etc.',

  // Tax & Amounts
  subtotal: 'Subtotal',
  tax: 'Tax',
  total: 'Total',
  netAmount: 'Net Amount',
  taxIncluded: 'Tax included',
  taxDeductible: 'Tax deductible',
}

// -----------------------------------------------------------------------------
// Receivables (AR)
// -----------------------------------------------------------------------------

export const receivablesTerms: TermDictionary = {
  // Invoices - List/Summary
  unpaidInvoices: 'Unpaid Invoices',
  whoOwesYou: 'People who owe you',
  arAging: 'Overdue Invoices',
  arBalance: 'Money owed to you',

  // Invoice Creation - Page Titles
  newInvoice: 'New Invoice',
  editInvoice: 'Edit Invoice',
  invoiceDetails: 'Invoice Details',

  // Invoice Creation - Form Labels
  whoIsThisFor: 'Who is this for?',
  whatDidYouSell: 'What did you sell?',
  howMuch: 'How much?',
  addTax: 'Add tax',
  dueIn: 'Due',
  addMoreDetails: 'Advanced',
  noTaxProfileConfigured: 'No tax profile configured',
  lineItems: 'Items',
  additionalInfo: 'Additional Info',

  // Invoice Creation - Placeholders
  searchCustomers: 'Search customers...',
  descriptionPlaceholder: 'e.g., Web design services',
  referencePlaceholder: 'PO number, project name...',

  // Invoice Creation - Actions
  saveDraft: 'Save Draft',
  sendInvoice: 'Send Invoice',
  approveInvoice: 'Approve',
  previewPdf: 'Preview',
  readyToSend: 'Ready to Send?',

  // Invoice - Quick Add
  quickAddCustomer: 'Quick Add Customer',
  quickAddCustomerDescription: 'Create a new customer with just a name. Add details later.',
  addNewCustomer: '+ New Customer',
  customerCreated: 'Customer created',
  vendorCreated: 'Vendor created',
  noCustomersFound: 'No customers found',

  // Invoice - Customer Notes
  customerNotes: 'Notes for customer',
  customerNotesPlaceholder: 'Notes that will appear on the invoice...',

  // Invoice - Due Date Options
  dueInDays: 'In {days} days',
  dueOnReceipt: 'Due on receipt',
  dueEndOfMonth: 'End of month',

  // Invoice - Status Messages
  invoiceSaved: 'Invoice saved',
  invoiceSent: 'Invoice sent!',
  invoiceApproved: 'Invoice approved',

  // Customers
  customers: 'Customers',
  customerBalance: 'Amount owed',
  recentCustomers: 'Recent',

  // Payments
  paymentReceived: 'Payment received',
  recordPayment: 'Record Payment',
}

// -----------------------------------------------------------------------------
// Payables (AP)
// -----------------------------------------------------------------------------

export const payablesTerms: TermDictionary = {
  // Bills - List/Summary
  unpaidBills: 'Bills to pay',
  whoYouOwe: 'People you owe',
  apAging: 'Bills due',
  apBalance: 'Money you owe',

  // Bill Creation - Page Titles
  newBill: 'Enter a Bill',
  editBill: 'Edit Bill',
  billDetails: 'Bill Details',

  // Bill Creation - Form Labels
  whoIsItFrom: 'Who is it from?',
  whatDidYouBuy: 'What did you buy?',
  howMuchBill: 'How much?',
  includesTax: 'Includes tax',
  expenseCategory: 'Category',
  billDate: 'Bill Date',

  // Bill Creation - Placeholders
  searchVendors: 'Search vendors...',
  billDescriptionPlaceholder: 'e.g., Office supplies',
  billReferencePlaceholder: 'Vendor invoice #...',

  // Bill Creation - Actions
  saveBillDraft: 'Save Draft',
  saveAndPayNow: 'Save & Pay Now',
  approveBill: 'Approve',

  // Bill - Quick Add
  quickAddVendor: 'Quick Add Vendor',
  quickAddVendorDescription: 'Create a new vendor with just a name. Add details later.',
  addNewVendor: '+ New Vendor',
  createVendorAndSelect: 'Create & Select',
  noVendorsFound: 'No vendors found',

  // Bill - Status Messages
  billSaved: 'Bill saved',
  billSavedAndPaid: 'Bill saved and paid!',
  billApproved: 'Bill recorded',
  billPaid: 'Bill paid!',

  // Bill - Additional Fields
  vendorInvoiceNumber: 'Invoice #',
  vendorInvoiceNumberPlaceholder: 'Vendor\'s invoice number',
  internalNotes: 'Notes',
  internalNotesPlaceholder: 'Notes for your records...',

  // Vendors
  vendors: 'Vendors',
  vendorBalance: 'Amount owed',
  recentVendors: 'Recent',

  // Payments
  payBill: 'Pay Bill',
  makePayment: 'Make Payment',

  // Receipt Capture (Mobile)
  snapReceipt: 'Snap Receipt',
  receiptCaptured: 'Receipt Captured',
  saveAsPending: 'Save as Pending',
  matchToBank: 'Match to Bank',
  detectedFromReceipt: 'We found:',
}

// -----------------------------------------------------------------------------
// Banking & Transactions
// -----------------------------------------------------------------------------

export const bankingTerms: TermDictionary = {
  // Bank Feed
  bankFeed: 'Bank Transactions',
  bankFeedSubtitle: 'Quickly confirm, categorize, or park transactions to keep cash up to date.',
  transactionsToReview: 'Transactions to review',
  unreconciledItems: 'Items to review',
  bankFeedBalanceFeed: 'Bank balance',
  bankFeedBalanceBooks: 'Books balance',
  reviewTransactionsAction: 'Review Transactions',

  // Reconciliation
  reconcile: 'Match transactions',
  reconciliation: 'Transaction matching',
  reconciled: 'Matched',

  // Transfers
  transfer: 'Transfer',
  internalTransfer: 'Move money between accounts',

  // Bank Accounts
  bankAccounts: 'Bank Accounts',

  // Bank Rules
  bankRules: 'Auto-Categorize Rules',
}

// -----------------------------------------------------------------------------
// Reports
// -----------------------------------------------------------------------------

export const reportTerms: TermDictionary = {
  // P&L
  profitAndLoss: 'How much did you make?',
  incomeStatement: 'Profit Report',

  // Balance Sheet
  balanceSheet: 'What you own & owe',
  financialPosition: 'Financial Snapshot',

  // Cash Flow
  cashFlow: 'Cash Forecast',
  cashFlowForecast: 'Can you pay your bills?',

  // Expense Report
  expenseReport: 'Where did your money go?',
  spendingByCategory: 'Spending breakdown',

  // Ledger
  generalLedger: 'Transaction History',
  trialBalance: 'Account Summary',
  journalReport: 'Entry Log',
}

// -----------------------------------------------------------------------------
// Navigation & Actions
// -----------------------------------------------------------------------------

export const navigationTerms: TermDictionary = {
  // Main Nav
  dashboard: 'Dashboard',
  accounting: 'Money',
  receivables: 'Money In',
  payables: 'Money Out',
  invoices: 'Invoices',
  bills: 'Bills',
  banking: 'Bank',
  reports: 'Reports',
  settings: 'Settings',

  // Actions
  createInvoice: 'Create Invoice',
  recordSale: 'Record a sale',
  enterBill: 'Enter a bill',
  recordExpense: 'Record expense',
  createJournalEntry: 'Add entry',
}

// -----------------------------------------------------------------------------
// Status & States
// -----------------------------------------------------------------------------

export const statusTerms: TermDictionary = {
  draft: 'Draft',
  pending: 'Pending',
  approved: 'Approved',
  posted: 'Recorded',
  paid: 'Paid',
  unpaid: 'Unpaid',
  partiallyPaid: 'Partially Paid',
  overdue: 'Overdue',
  voided: 'Cancelled',
}

// -----------------------------------------------------------------------------
// Inventory
// -----------------------------------------------------------------------------

export const inventoryTerms: TermDictionary = {
  inventory: 'Inventory',
  items: 'Products',
  warehouses: 'Locations',
  categories: 'Categories',
  stockLevels: 'Stock',
  stockReceipts: 'Stock Receipts',
  stockStatus: 'Stock status',
  expectedInbound: 'Expected inbound',
  stockPending: 'Pending receipt',
  stockReceived: 'Stock received',
  stockNotTracked: 'Not tracked',
  stockAwaitingPayment: 'Awaiting payment',
  stockMovements: 'Stock History',
  sku: 'SKU',
  unitCost: 'Cost',
  sellingPrice: 'Price',
  reorderPoint: 'Low Stock Alert',
  inStock: 'In Stock',
  outOfStock: 'Out of Stock',
  lowStock: 'Running Low',
  adjustment: 'Stock Adjustment',
  transfer: 'Move Stock',
}

// -----------------------------------------------------------------------------
// Payroll & HR
// -----------------------------------------------------------------------------

export const payrollTerms: TermDictionary = {
  payroll: 'Payroll',
  employees: 'Team',
  payrollPeriods: 'Pay Periods',
  payslips: 'Payslips',
  payrollSettings: 'Pay Settings',
  leaveRequests: 'Time Off',
  grossPay: 'Gross Pay',
  netPay: 'Take Home',
  deductions: 'Deductions',
  earnings: 'Earnings',
  baseSalary: 'Salary',
  payFrequency: 'Pay Schedule',
}

// -----------------------------------------------------------------------------
// Dashboard Widgets
// -----------------------------------------------------------------------------

export const dashboardTerms: TermDictionary = {
  // Headings
  cashPosition: 'Your Cash',
  revenueThisMonth: 'Money in this month',
  expensesThisMonth: 'Money out this month',
  needsAttention: 'Needs your attention',
  quickActions: 'Quick Actions',
  recentActivity: 'Recent Activity',

  // Balance Explainer
  balanceMatches: 'Your books match the bank',
  balanceDifference: 'Your books don\'t match the bank',
}

// -----------------------------------------------------------------------------
// Empty States & Messages
// -----------------------------------------------------------------------------

export const emptyStateTerms: TermDictionary = {
  noInvoices: 'No invoices yet',
  noInvoicesDesc: 'Create your first invoice to get paid faster',
  noBills: 'No bills yet',
  noBillsDesc: 'Enter bills as they come in to track what you owe',
  noTransactions: 'No transactions to review',
  noTransactionsDesc: 'Connect your bank to start tracking your money',
  noCustomers: 'No customers yet',
  noVendors: 'No vendors yet',
  noReportData: 'No activity in this period',
}

// -----------------------------------------------------------------------------
// Tooltips & Help Text
// -----------------------------------------------------------------------------

export const helpTerms: TermDictionary = {
  invoiceDateHelp: 'When did you make this sale?',
  dueDateHelp: 'When should they pay by?',
  categoryHelp: 'What type of income or expense is this?',
  taxCodeHelp: 'Is this taxable?',
  referenceHelp: 'Add a note for your records',
}

// -----------------------------------------------------------------------------
// Templated Messages (with interpolation)
// -----------------------------------------------------------------------------

export const templateTerms: TermDictionary = {
  // Bank Feed
  transactionsToReviewCount: '{count} transactions to review',
  transactionMatched: 'Matched to {document}',

  // Invoices
  invoiceCreated: 'Invoice sent to {customer}',
  invoicePaid: '{customer} paid {amount}',
  invoiceOverdue: '{customer} is {days} days overdue',

  // Bills
  billDueSoon: 'Bill due in {days} days',
  billPaid: 'Paid {amount} to {vendor}',

  // Dashboard
  profitThisMonth: 'You made {amount} this month',
  cashForecast: 'You\'ll have about {amount} in {days} days',

  // Errors
  periodClosed: 'Can\'t save to {month} - it\'s been closed',
  insufficientPermission: 'You don\'t have permission to do this',

  itemNumber: 'Item {number}',

  // Generic UI
  back: 'Back',
  cancel: 'Cancel',
  saveChanges: 'Save changes',
  edit: 'Edit',
  duplicate: 'Duplicate',
  void: 'Void',
  actions: 'Actions',
}

// -----------------------------------------------------------------------------
// Invoice/Bill UI and Actions
// -----------------------------------------------------------------------------

export const invoiceBillTerms: TermDictionary = {
  // Generic
  delete: 'Delete',
  discount: 'Discount',
  internalNotes: 'Internal Notes',
  currency: 'Currency',
  status: 'Status',
  details: 'Details',
  notes: 'Notes',
  vendor: 'Vendor',
  customer: 'Customer',
  date: 'Date',
  due: 'Due',
  balance: 'Balance',
  amount: 'Amount',
  price: 'Price',
  addLine: 'Add another item',
  selectVendor: 'Select vendor',
  selectAccount: 'Select account',
  useDefault: 'Use default',
  billDate: 'Bill Date',
  lineItems: 'Items',

  // Bill-specific
  billNumber: 'Bill #',
  billAmount: 'Bill Amount',
  amountPaid: 'Amount Paid',
  paymentSummary: 'Payment Summary',
  markAsReceived: 'Mark as Received',
  recordPayment: 'Record Payment',
  expenseAccount: 'Expense Account',
  apAccount: 'AP Account',
  taxPercent: 'Tax %',
  discountPercent: 'Discount %',
  confirmDeleteBill: 'Are you sure you want to delete this bill?',
  searchBillPlaceholder: 'Search bill # or vendor invoice #',
  allVendors: 'All vendors',
  allStatus: 'All status',
  received: 'Received',
  billReceived: 'Bill received',
  partial: 'Partial',
  cancelled: 'Cancelled',
  searchInvoicePlaceholder: 'Search invoices…',

  // Invoice-specific
  invoiceNumber: 'Invoice #',
  invoiceAmount: 'Invoice Amount',

  invoiceDetails: 'Invoice details',
  invoiceDetailsHelper: 'Basic information about the invoice',
  lineItemsHelper: 'Add what you sold',
  incomeAccount: 'Income account',
  amountSummary: 'Amount summary',
  addLineItem: 'Add item',
  invoiceLocked: 'This invoice cannot be edited in its current status.',
  customerInformation: 'Customer information',
  selectCustomerForInvoice: 'Select the customer for this invoice',
  arAccount: 'AR account',
  useCompanyDefault: 'Use company default',
  invoiceDateLabel: 'Invoice Date',
  paymentTerms: 'Payment terms',
  items: 'Items',
  description: 'Description',
  quantity: 'Qty',
  unitPrice: 'Price',
  total: 'Total',
  subtotal: 'Subtotal',
  tax: 'Tax',
  paid: 'Paid',
  balanceDue: 'Balance due',
  statusTimeline: 'Status timeline',
  created: 'Created',
  sent: 'Sent',
  viewed: 'Viewed',
  sendInvoice: 'Send to customer',
  downloadPdf: 'Download PDF',
  invoiceSummary: 'Invoice summary',
  additionalInformation: 'Additional information',
  customerNotes: 'Customer notes',
  markAsSent: 'Mark as sent',
  back: 'Back',
  cancel: 'Cancel',
  saveChanges: 'Save changes',
  edit: 'Edit',
  duplicate: 'Duplicate',
  void: 'Void',
  dueDate: 'Due date',
  reference: 'Reference',
  days: 'days',
}

// -----------------------------------------------------------------------------
// Combined Dictionary (all terms)
// -----------------------------------------------------------------------------

export const lexicon: TermDictionary = {
  ...coreTerms,
  ...receivablesTerms,
  ...payablesTerms,
  ...bankingTerms,
  ...reportTerms,
  ...navigationTerms,
  ...statusTerms,
  ...inventoryTerms,
  ...payrollTerms,
  ...dashboardTerms,
  ...emptyStateTerms,
  ...helpTerms,
  ...templateTerms,
  ...invoiceBillTerms,
}

// -----------------------------------------------------------------------------
// Type-safe key extraction
// -----------------------------------------------------------------------------

export type LexiconKey = keyof typeof lexicon

// -----------------------------------------------------------------------------
// Interpolation Helper
// -----------------------------------------------------------------------------

export function interpolate(template: string, params: Record<string, string | number>): string {
  return template.replace(/\{(\w+)\}/g, (_, key) => {
    return params[key]?.toString() ?? `{${key}}`
  })
}

// -----------------------------------------------------------------------------
// Term Getter
// -----------------------------------------------------------------------------

export function getTerm(
  key: LexiconKey | string,
  params?: Record<string, string | number>
): string {
  const term = lexicon[key]

  if (term === undefined) {
    console.warn(`[Lexicon] Unknown key: "${key}"`)
    return String(key)
  }

  return params ? interpolate(term, params) : term
}
