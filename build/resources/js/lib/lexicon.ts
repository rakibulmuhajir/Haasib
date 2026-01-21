/**
 * Mode-Aware Lexicon (Terminology Dictionary)
 *
 * Central source of truth for Owner/Accountant mode terminology.
 * All user-facing text that differs by mode should be defined here.
 *
 * @see docs/frontend-experience-contract.md Section 14: Language & Terminology
 *
 * Usage:
 *   const { t } = useLexicon()
 *   t('moneyIn') // Returns "Money In" or "Revenue" based on mode
 *   t('transactionsToReview', { count: 5 }) // "5 transactions to review"
 */

import type { UserMode } from '@/composables/useUserMode'

// -----------------------------------------------------------------------------
// Type Definitions
// -----------------------------------------------------------------------------

export interface TermEntry {
  owner: string
  accountant: string
}

export interface TermDictionary {
  [key: string]: TermEntry
}

// -----------------------------------------------------------------------------
// Core Financial Concepts
// -----------------------------------------------------------------------------

export const coreTerms: TermDictionary = {
  // Income/Revenue
  moneyIn: {
    owner: 'Money In',
    accountant: 'Revenue',
  },
  income: {
    owner: 'Income',
    accountant: 'Revenue',
  },
  sales: {
    owner: 'Sales',
    accountant: 'Sales Revenue',
  },

  // Expenses
  moneyOut: {
    owner: 'Money Out',
    accountant: 'Expenses',
  },
  expenses: {
    owner: 'Spending',
    accountant: 'Expenses',
  },
  costs: {
    owner: 'Costs',
    accountant: 'Cost of Goods Sold',
  },

  // Profit
  profit: {
    owner: 'Profit',
    accountant: 'Net Income',
  },
  moneyMade: {
    owner: 'Money you made',
    accountant: 'Net Income',
  },
  grossProfit: {
    owner: 'Gross Profit',
    accountant: 'Gross Profit',
  },

  // Cash & Banks
  cash: {
    owner: 'Cash',
    accountant: 'Cash & Equivalents',
  },
  cashOnHand: {
    owner: 'Cash on hand',
    accountant: 'Cash Position',
  },
  bankBalance: {
    owner: 'Bank Balance',
    accountant: 'Bank Account Balance',
  },

  // Categories vs Accounts
  category: {
    owner: 'Category',
    accountant: 'Account',
  },
  categories: {
    owner: 'Categories',
    accountant: 'GL Accounts',
  },
  chartOfAccounts: {
    owner: 'Categories',
    accountant: 'Chart of Accounts',
  },

  // Common UI Terms
  back: {
    owner: 'Back',
    accountant: 'Back',
  },
  receiveStock: {
    owner: 'Receive stock',
    accountant: 'Receive stock',
  },
  apply: {
    owner: 'Apply',
    accountant: 'Apply',
  },
  dateRange: {
    owner: 'Date range',
    accountant: 'Date range',
  },
  startDate: {
    owner: 'Start date',
    accountant: 'Start date',
  },
  endDate: {
    owner: 'End date',
    accountant: 'End date',
  },
  cancel: {
    owner: 'Cancel',
    accountant: 'Cancel',
  },
  save: {
    owner: 'Save',
    accountant: 'Save',
  },
  saveDraft: {
    owner: 'Save Draft',
    accountant: 'Save as Draft',
  },
  optional: {
    owner: 'optional',
    accountant: 'optional',
  },
  name: {
    owner: 'Name',
    accountant: 'Name',
  },
  email: {
    owner: 'Email',
    accountant: 'Email',
  },
  createAndSelect: {
    owner: 'Create & Select',
    accountant: 'Create',
  },
  addDetailsLater: {
    owner: 'You can add more details later.',
    accountant: 'Additional details can be added later.',
  },
  selectCategory: {
    owner: 'Select a category...',
    accountant: 'Select Account',
  },
  addMoreDetails: {
    owner: 'Add more details',
    accountant: 'Additional Fields',
  },
  reference: {
    owner: 'Reference',
    accountant: 'Reference',
  },
  referencePlaceholder: {
    owner: 'PO#, Order#, etc.',
    accountant: 'Reference Number',
  },

  // Tax & Amounts
  subtotal: {
    owner: 'Subtotal',
    accountant: 'Subtotal',
  },
  tax: {
    owner: 'Tax',
    accountant: 'Tax',
  },
  total: {
    owner: 'Total',
    accountant: 'Total',
  },
  netAmount: {
    owner: 'Net Amount',
    accountant: 'Net Amount',
  },
  taxIncluded: {
    owner: 'Tax included',
    accountant: 'Tax Included',
  },
  taxDeductible: {
    owner: 'Tax deductible',
    accountant: 'Input Tax Credit',
  },
}

// -----------------------------------------------------------------------------
// Receivables (AR)
// -----------------------------------------------------------------------------

export const receivablesTerms: TermDictionary = {
  // Invoices - List/Summary
  unpaidInvoices: {
    owner: 'Unpaid Invoices',
    accountant: 'AR Outstanding',
  },
  whoOwesYou: {
    owner: 'People who owe you',
    accountant: 'Accounts Receivable',
  },
  arAging: {
    owner: 'Overdue Invoices',
    accountant: 'AR Aging',
  },
  arBalance: {
    owner: 'Money owed to you',
    accountant: 'AR Balance',
  },

  // Invoice Creation - Page Titles
  newInvoice: {
    owner: 'New Invoice',
    accountant: 'Create Invoice',
  },
  editInvoice: {
    owner: 'Edit Invoice',
    accountant: 'Edit Invoice',
  },
  invoiceDetails: {
    owner: 'Invoice Details',
    accountant: 'Invoice Details',
  },

  // Invoice Creation - Form Labels
  whoIsThisFor: {
    owner: 'Who is this for?',
    accountant: 'Customer',
  },
  whatDidYouSell: {
    owner: 'What did you sell?',
    accountant: 'Description',
  },
  howMuch: {
    owner: 'How much?',
    accountant: 'Amount',
  },
  addTax: {
    owner: 'Add tax',
    accountant: 'Apply Tax',
  },
  dueIn: {
    owner: 'Due',
    accountant: 'Due Date',
  },
  addMoreDetails: {
    owner: 'Advanced',
    accountant: 'Advanced',
  },
  noTaxProfileConfigured: {
    owner: 'No tax profile configured',
    accountant: 'No tax profile configured',
  },
  lineItems: {
    owner: 'Items',
    accountant: 'Line Items',
  },
  additionalInfo: {
    owner: 'Additional Info',
    accountant: 'Additional Information',
  },

  // Invoice Creation - Placeholders
  searchCustomers: {
    owner: 'Search customers...',
    accountant: 'Search customers...',
  },
  descriptionPlaceholder: {
    owner: 'e.g., Web design services',
    accountant: 'Enter description',
  },
  referencePlaceholder: {
    owner: 'PO number, project name...',
    accountant: 'Reference / PO Number',
  },

  // Invoice Creation - Actions
  saveDraft: {
    owner: 'Save Draft',
    accountant: 'Save as Draft',
  },
  sendInvoice: {
    owner: 'Send Invoice',
    accountant: 'Approve & Send',
  },
  approveInvoice: {
    owner: 'Approve',
    accountant: 'Post Invoice',
  },
  previewPdf: {
    owner: 'Preview',
    accountant: 'Preview PDF',
  },
  readyToSend: {
    owner: 'Ready to Send?',
    accountant: 'Send Invoice',
  },

  // Invoice - Quick Add
  quickAddCustomer: {
    owner: 'Quick Add Customer',
    accountant: 'Add Customer',
  },
  quickAddCustomerDescription: {
    owner: 'Create a new customer with just a name. Add details later.',
    accountant: 'Create customer record. Additional details can be added later.',
  },
  addNewCustomer: {
    owner: '+ New Customer',
    accountant: '+ Add Customer',
  },
  customerCreated: {
    owner: 'Customer created',
    accountant: 'Customer created successfully',
  },
  vendorCreated: {
    owner: 'Vendor created',
    accountant: 'Vendor created successfully',
  },
  noCustomersFound: {
    owner: 'No customers found',
    accountant: 'No matching customers',
  },

  // Invoice - Customer Notes
  customerNotes: {
    owner: 'Notes for customer',
    accountant: 'Customer-Facing Notes',
  },
  customerNotesPlaceholder: {
    owner: 'Notes that will appear on the invoice...',
    accountant: 'Notes visible to customer',
  },

  // Invoice - Due Date Options
  dueInDays: {
    owner: 'In {days} days',
    accountant: 'Net {days}',
  },
  dueOnReceipt: {
    owner: 'Due on receipt',
    accountant: 'Due on Receipt',
  },
  dueEndOfMonth: {
    owner: 'End of month',
    accountant: 'EOM',
  },

  // Invoice - Status Messages
  invoiceSaved: {
    owner: 'Invoice saved',
    accountant: 'Invoice saved as draft',
  },
  invoiceSent: {
    owner: 'Invoice sent!',
    accountant: 'Invoice posted and sent',
  },
  invoiceApproved: {
    owner: 'Invoice approved',
    accountant: 'Invoice posted to ledger',
  },

  // Customers
  customers: {
    owner: 'Customers',
    accountant: 'Customers',
  },
  customerBalance: {
    owner: 'Amount owed',
    accountant: 'Customer Balance',
  },
  recentCustomers: {
    owner: 'Recent',
    accountant: 'Recent Customers',
  },

  // Payments
  paymentReceived: {
    owner: 'Payment received',
    accountant: 'Payment Receipt',
  },
  recordPayment: {
    owner: 'Record Payment',
    accountant: 'Receive Payment',
  },
}

// -----------------------------------------------------------------------------
// Payables (AP)
// -----------------------------------------------------------------------------

export const payablesTerms: TermDictionary = {
  // Bills - List/Summary
  unpaidBills: {
    owner: 'Bills to pay',
    accountant: 'AP Outstanding',
  },
  whoYouOwe: {
    owner: 'People you owe',
    accountant: 'Accounts Payable',
  },
  apAging: {
    owner: 'Bills due',
    accountant: 'AP Aging',
  },
  apBalance: {
    owner: 'Money you owe',
    accountant: 'AP Balance',
  },

  // Bill Creation - Page Titles
  newBill: {
    owner: 'Enter a Bill',
    accountant: 'Create Bill',
  },
  editBill: {
    owner: 'Edit Bill',
    accountant: 'Edit Bill',
  },
  billDetails: {
    owner: 'Bill Details',
    accountant: 'Bill Details',
  },

  // Bill Creation - Form Labels
  whoIsItFrom: {
    owner: 'Who is it from?',
    accountant: 'Vendor',
  },
  whatDidYouBuy: {
    owner: 'What did you buy?',
    accountant: 'Description',
  },
  howMuchBill: {
    owner: 'How much?',
    accountant: 'Amount',
  },
  includesTax: {
    owner: 'Includes tax',
    accountant: 'Tax Inclusive',
  },
  expenseCategory: {
    owner: 'Category',
    accountant: 'Expense Account',
  },
  billDate: {
    owner: 'Bill Date',
    accountant: 'Bill Date',
  },

  // Bill Creation - Placeholders
  searchVendors: {
    owner: 'Search vendors...',
    accountant: 'Search vendors...',
  },
  billDescriptionPlaceholder: {
    owner: 'e.g., Office supplies',
    accountant: 'Enter description',
  },
  billReferencePlaceholder: {
    owner: 'Vendor invoice #...',
    accountant: 'Vendor Invoice Number',
  },

  // Bill Creation - Actions
  saveBillDraft: {
    owner: 'Save Draft',
    accountant: 'Save as Draft',
  },
  saveAndPayNow: {
    owner: 'Save & Pay Now',
    accountant: 'Approve & Pay',
  },
  approveBill: {
    owner: 'Approve',
    accountant: 'Post Bill',
  },

  // Bill - Quick Add
  quickAddVendor: {
    owner: 'Quick Add Vendor',
    accountant: 'Add Vendor',
  },
  quickAddVendorDescription: {
    owner: 'Create a new vendor with just a name. Add details later.',
    accountant: 'Create vendor record. Additional details can be added later.',
  },
  addNewVendor: {
    owner: '+ New Vendor',
    accountant: '+ Add Vendor',
  },
  createVendorAndSelect: {
    owner: 'Create & Select',
    accountant: 'Create Vendor',
  },
  noVendorsFound: {
    owner: 'No vendors found',
    accountant: 'No matching vendors',
  },

  // Bill - Status Messages
  billSaved: {
    owner: 'Bill saved',
    accountant: 'Bill saved as draft',
  },
  billSavedAndPaid: {
    owner: 'Bill saved and paid!',
    accountant: 'Bill posted and payment recorded',
  },
  billApproved: {
    owner: 'Bill recorded',
    accountant: 'Bill posted to ledger',
  },
  billPaid: {
    owner: 'Bill paid!',
    accountant: 'Payment posted',
  },

  // Bill - Additional Fields
  vendorInvoiceNumber: {
    owner: 'Invoice #',
    accountant: 'Vendor Invoice Number',
  },
  vendorInvoiceNumberPlaceholder: {
    owner: 'Vendor\'s invoice number',
    accountant: 'Vendor Invoice Reference',
  },
  internalNotes: {
    owner: 'Notes',
    accountant: 'Internal Notes',
  },
  internalNotesPlaceholder: {
    owner: 'Notes for your records...',
    accountant: 'Internal memo (not visible to vendor)',
  },

  // Vendors
  vendors: {
    owner: 'Vendors',
    accountant: 'Vendors',
  },
  vendorBalance: {
    owner: 'Amount owed',
    accountant: 'Vendor Balance',
  },
  recentVendors: {
    owner: 'Recent',
    accountant: 'Recent Vendors',
  },

  // Payments
  payBill: {
    owner: 'Pay Bill',
    accountant: 'Bill Payment',
  },
  makePayment: {
    owner: 'Make Payment',
    accountant: 'Record Bill Payment',
  },

  // Receipt Capture (Mobile)
  snapReceipt: {
    owner: 'Snap Receipt',
    accountant: 'Capture Receipt',
  },
  receiptCaptured: {
    owner: 'Receipt Captured',
    accountant: 'Receipt Processed',
  },
  saveAsPending: {
    owner: 'Save as Pending',
    accountant: 'Save Draft',
  },
  matchToBank: {
    owner: 'Match to Bank',
    accountant: 'Link to Bank Transaction',
  },
  detectedFromReceipt: {
    owner: 'We found:',
    accountant: 'OCR Results:',
  },
}

// -----------------------------------------------------------------------------
// Banking & Transactions
// -----------------------------------------------------------------------------

export const bankingTerms: TermDictionary = {
  // Bank Feed
  bankFeed: {
    owner: 'Bank Transactions',
    accountant: 'Bank Feed',
  },
  bankFeedSubtitle: {
    owner: 'Quickly confirm, categorize, or park transactions to keep cash up to date.',
    accountant: 'Match, categorize, and reconcile your transactions.',
  },
  transactionsToReview: {
    owner: 'Transactions to review',
    accountant: 'Unreconciled Transactions',
  },
  unreconciledItems: {
    owner: 'Items to review',
    accountant: 'Unreconciled Items',
  },
  bankFeedBalanceFeed: {
    owner: 'Bank balance',
    accountant: 'Bank Feed',
  },
  bankFeedBalanceBooks: {
    owner: 'Books balance',
    accountant: 'System Ledger',
  },
  reviewTransactionsAction: {
    owner: 'Review Transactions',
    accountant: 'Open Bank Feed',
  },

  // Reconciliation
  reconcile: {
    owner: 'Match transactions',
    accountant: 'Reconcile',
  },
  reconciliation: {
    owner: 'Transaction matching',
    accountant: 'Bank Reconciliation',
  },
  reconciled: {
    owner: 'Matched',
    accountant: 'Reconciled',
  },

  // Transfers
  transfer: {
    owner: 'Transfer',
    accountant: 'Bank Transfer',
  },
  internalTransfer: {
    owner: 'Move money between accounts',
    accountant: 'Internal Transfer',
  },

  // Bank Accounts
  bankAccounts: {
    owner: 'Bank Accounts',
    accountant: 'Bank Accounts',
  },

  // Bank Rules
  bankRules: {
    owner: 'Auto-Categorize Rules',
    accountant: 'Bank Rules',
  },
}

// -----------------------------------------------------------------------------
// Reports
// -----------------------------------------------------------------------------

export const reportTerms: TermDictionary = {
  // P&L
  profitAndLoss: {
    owner: 'How much did you make?',
    accountant: 'Income Statement',
  },
  incomeStatement: {
    owner: 'Profit Report',
    accountant: 'Income Statement',
  },

  // Balance Sheet
  balanceSheet: {
    owner: 'What you own & owe',
    accountant: 'Balance Sheet',
  },
  financialPosition: {
    owner: 'Financial Snapshot',
    accountant: 'Statement of Financial Position',
  },

  // Cash Flow
  cashFlow: {
    owner: 'Cash Forecast',
    accountant: 'Cash Flow Statement',
  },
  cashFlowForecast: {
    owner: 'Can you pay your bills?',
    accountant: 'Cash Flow Forecast',
  },

  // Expense Report
  expenseReport: {
    owner: 'Where did your money go?',
    accountant: 'Expense Report',
  },
  spendingByCategory: {
    owner: 'Spending breakdown',
    accountant: 'Expenses by Account',
  },

  // Ledger
  generalLedger: {
    owner: 'Transaction History',
    accountant: 'General Ledger',
  },
  trialBalance: {
    owner: 'Account Summary',
    accountant: 'Trial Balance',
  },
  journalReport: {
    owner: 'Entry Log',
    accountant: 'Journal Report',
  },
}

// -----------------------------------------------------------------------------
// Navigation & Actions
// -----------------------------------------------------------------------------

export const navigationTerms: TermDictionary = {
  // Main Nav
  dashboard: {
    owner: 'Dashboard',
    accountant: 'Dashboard',
  },
  accounting: {
    owner: 'Money',
    accountant: 'Accounting',
  },
  receivables: {
    owner: 'Money In',
    accountant: 'Receivables',
  },
  payables: {
    owner: 'Money Out',
    accountant: 'Payables',
  },
  invoices: {
    owner: 'Invoices',
    accountant: 'Invoices',
  },
  bills: {
    owner: 'Bills',
    accountant: 'Bills',
  },
  banking: {
    owner: 'Bank',
    accountant: 'Banking',
  },
  reports: {
    owner: 'Reports',
    accountant: 'Reports',
  },
  settings: {
    owner: 'Settings',
    accountant: 'Settings',
  },

  // Actions
  createInvoice: {
    owner: 'Create Invoice',
    accountant: 'New Invoice',
  },
  recordSale: {
    owner: 'Record a sale',
    accountant: 'Create Invoice',
  },
  enterBill: {
    owner: 'Enter a bill',
    accountant: 'Create Bill',
  },
  recordExpense: {
    owner: 'Record expense',
    accountant: 'Create Expense Entry',
  },
  createJournalEntry: {
    owner: 'Add entry',
    accountant: 'Create Journal Entry',
  },
}

// -----------------------------------------------------------------------------
// Status & States
// -----------------------------------------------------------------------------

export const statusTerms: TermDictionary = {
  draft: {
    owner: 'Draft',
    accountant: 'Draft',
  },
  pending: {
    owner: 'Pending',
    accountant: 'Pending Approval',
  },
  approved: {
    owner: 'Approved',
    accountant: 'Posted',
  },
  posted: {
    owner: 'Recorded',
    accountant: 'Posted',
  },
  paid: {
    owner: 'Paid',
    accountant: 'Paid',
  },
  unpaid: {
    owner: 'Unpaid',
    accountant: 'Outstanding',
  },
  partiallyPaid: {
    owner: 'Partially Paid',
    accountant: 'Partially Settled',
  },
  overdue: {
    owner: 'Overdue',
    accountant: 'Past Due',
  },
  voided: {
    owner: 'Cancelled',
    accountant: 'Voided',
  },
}

// -----------------------------------------------------------------------------
// Inventory
// -----------------------------------------------------------------------------

export const inventoryTerms: TermDictionary = {
  inventory: {
    owner: 'Inventory',
    accountant: 'Inventory',
  },
  items: {
    owner: 'Products',
    accountant: 'Items',
  },
  warehouses: {
    owner: 'Locations',
    accountant: 'Warehouses',
  },
  categories: {
    owner: 'Categories',
    accountant: 'Item Categories',
  },
  stockLevels: {
    owner: 'Stock',
    accountant: 'Stock Levels',
  },
  stockReceipts: {
    owner: 'Stock Receipts',
    accountant: 'Stock Receipts',
  },
  stockStatus: {
    owner: 'Stock status',
    accountant: 'Stock status',
  },
  expectedInbound: {
    owner: 'Expected inbound',
    accountant: 'Expected inbound',
  },
  stockPending: {
    owner: 'Pending receipt',
    accountant: 'Pending receipt',
  },
  stockReceived: {
    owner: 'Stock received',
    accountant: 'Stock received',
  },
  stockNotTracked: {
    owner: 'Not tracked',
    accountant: 'Not tracked',
  },
  stockAwaitingPayment: {
    owner: 'Awaiting payment',
    accountant: 'Awaiting payment',
  },
  stockMovements: {
    owner: 'Stock History',
    accountant: 'Stock Movements',
  },
  sku: {
    owner: 'SKU',
    accountant: 'SKU',
  },
  unitCost: {
    owner: 'Cost',
    accountant: 'Unit Cost',
  },
  sellingPrice: {
    owner: 'Price',
    accountant: 'Selling Price',
  },
  reorderPoint: {
    owner: 'Low Stock Alert',
    accountant: 'Reorder Point',
  },
  inStock: {
    owner: 'In Stock',
    accountant: 'On Hand',
  },
  outOfStock: {
    owner: 'Out of Stock',
    accountant: 'Zero Stock',
  },
  lowStock: {
    owner: 'Running Low',
    accountant: 'Below Reorder Point',
  },
  adjustment: {
    owner: 'Stock Adjustment',
    accountant: 'Inventory Adjustment',
  },
  transfer: {
    owner: 'Move Stock',
    accountant: 'Stock Transfer',
  },
}

// -----------------------------------------------------------------------------
// Payroll & HR
// -----------------------------------------------------------------------------

export const payrollTerms: TermDictionary = {
  payroll: {
    owner: 'Payroll',
    accountant: 'Payroll',
  },
  employees: {
    owner: 'Team',
    accountant: 'Employees',
  },
  payrollPeriods: {
    owner: 'Pay Periods',
    accountant: 'Payroll Periods',
  },
  payslips: {
    owner: 'Payslips',
    accountant: 'Payslips',
  },
  payrollSettings: {
    owner: 'Pay Settings',
    accountant: 'Payroll Settings',
  },
  leaveRequests: {
    owner: 'Time Off',
    accountant: 'Leave Requests',
  },
  grossPay: {
    owner: 'Gross Pay',
    accountant: 'Gross Earnings',
  },
  netPay: {
    owner: 'Take Home',
    accountant: 'Net Pay',
  },
  deductions: {
    owner: 'Deductions',
    accountant: 'Deductions',
  },
  earnings: {
    owner: 'Earnings',
    accountant: 'Earnings',
  },
  baseSalary: {
    owner: 'Salary',
    accountant: 'Base Salary',
  },
  payFrequency: {
    owner: 'Pay Schedule',
    accountant: 'Pay Frequency',
  },
}

// -----------------------------------------------------------------------------
// Dashboard Widgets
// -----------------------------------------------------------------------------

export const dashboardTerms: TermDictionary = {
  // Headings
  cashPosition: {
    owner: 'Your Cash',
    accountant: 'Cash Position',
  },
  revenueThisMonth: {
    owner: 'Money in this month',
    accountant: 'Revenue MTD',
  },
  expensesThisMonth: {
    owner: 'Money out this month',
    accountant: 'Expenses MTD',
  },
  needsAttention: {
    owner: 'Needs your attention',
    accountant: 'Action Required',
  },
  quickActions: {
    owner: 'Quick Actions',
    accountant: 'Quick Actions',
  },
  recentActivity: {
    owner: 'Recent Activity',
    accountant: 'Recent Transactions',
  },

  // Balance Explainer
  balanceMatches: {
    owner: 'Your books match the bank',
    accountant: 'Bank Balance Reconciled',
  },
  balanceDifference: {
    owner: 'Your books don\'t match the bank',
    accountant: 'Unreconciled Difference',
  },
}

// -----------------------------------------------------------------------------
// Empty States & Messages
// -----------------------------------------------------------------------------

export const emptyStateTerms: TermDictionary = {
  noInvoices: {
    owner: 'No invoices yet',
    accountant: 'No invoices found',
  },
  noInvoicesDesc: {
    owner: 'Create your first invoice to get paid faster',
    accountant: 'Create an invoice to begin tracking receivables',
  },
  noBills: {
    owner: 'No bills yet',
    accountant: 'No bills found',
  },
  noBillsDesc: {
    owner: 'Enter bills as they come in to track what you owe',
    accountant: 'Enter bills to track accounts payable',
  },
  noTransactions: {
    owner: 'No transactions to review',
    accountant: 'No unreconciled transactions',
  },
  noTransactionsDesc: {
    owner: 'Connect your bank to start tracking your money',
    accountant: 'All bank transactions have been reconciled',
  },
  noCustomers: {
    owner: 'No customers yet',
    accountant: 'No customers found',
  },
  noVendors: {
    owner: 'No vendors yet',
    accountant: 'No vendors found',
  },
  noReportData: {
    owner: 'No activity in this period',
    accountant: 'No postings in this period',
  },
}

// -----------------------------------------------------------------------------
// Tooltips & Help Text
// -----------------------------------------------------------------------------

export const helpTerms: TermDictionary = {
  invoiceDateHelp: {
    owner: 'When did you make this sale?',
    accountant: 'Transaction date for accounting period determination',
  },
  dueDateHelp: {
    owner: 'When should they pay by?',
    accountant: 'Payment due date for aging calculation',
  },
  categoryHelp: {
    owner: 'What type of income or expense is this?',
    accountant: 'Select the GL account for this transaction',
  },
  taxCodeHelp: {
    owner: 'Is this taxable?',
    accountant: 'Tax code determines tax treatment and posting',
  },
  referenceHelp: {
    owner: 'Add a note for your records',
    accountant: 'External reference number for audit trail',
  },
}

// -----------------------------------------------------------------------------
// Templated Messages (with interpolation)
// -----------------------------------------------------------------------------

export const templateTerms: TermDictionary = {
  // Bank Feed
  transactionsToReviewCount: {
    owner: '{count} transactions to review',
    accountant: '{count} unreconciled transactions',
  },
  transactionMatched: {
    owner: 'Matched to {document}',
    accountant: 'Reconciled against {document}',
  },

  // Invoices
  invoiceCreated: {
    owner: 'Invoice sent to {customer}',
    accountant: 'Invoice {number} created for {customer}',
  },
  invoicePaid: {
    owner: '{customer} paid {amount}',
    accountant: 'Payment received: {amount} from {customer}',
  },
  invoiceOverdue: {
    owner: '{customer} is {days} days overdue',
    accountant: 'Invoice {number} past due by {days} days',
  },

  // Bills
  billDueSoon: {
    owner: 'Bill due in {days} days',
    accountant: 'Bill {number} due in {days} days',
  },
  billPaid: {
    owner: 'Paid {amount} to {vendor}',
    accountant: 'Payment {amount} posted to {vendor}',
  },

  // Dashboard
  profitThisMonth: {
    owner: 'You made {amount} this month',
    accountant: 'Net Income MTD: {amount}',
  },
  cashForecast: {
    owner: 'You\'ll have about {amount} in {days} days',
    accountant: 'Projected cash balance in {days} days: {amount}',
  },

  // Errors
  periodClosed: {
    owner: 'Can\'t save to {month} - it\'s been closed',
    accountant: 'Cannot post to closed period: {month}',
  },
  insufficientPermission: {
    owner: 'You don\'t have permission to do this',
    accountant: 'Insufficient permissions for this action',
  },

  itemNumber: {
    owner: 'Item {number}',
    accountant: 'Line {number}',
  },

  // Generic UI
  back: {
    owner: 'Back',
    accountant: 'Back',
  },
  cancel: {
    owner: 'Cancel',
    accountant: 'Cancel',
  },
  saveChanges: {
    owner: 'Save changes',
    accountant: 'Save changes',
  },
  edit: {
    owner: 'Edit',
    accountant: 'Edit',
  },
  duplicate: {
    owner: 'Duplicate',
    accountant: 'Duplicate',
  },
  void: {
    owner: 'Void',
    accountant: 'Void',
  },
  actions: {
    owner: 'Actions',
    accountant: 'Actions',
  },
}

// -----------------------------------------------------------------------------
// Invoice/Bill UI and Actions
// -----------------------------------------------------------------------------

export const invoiceBillTerms: TermDictionary = {
  // Generic
  delete: { owner: 'Delete', accountant: 'Delete' },
  discount: { owner: 'Discount', accountant: 'Discount' },
  internalNotes: { owner: 'Internal Notes', accountant: 'Internal Notes' },
  currency: { owner: 'Currency', accountant: 'Currency' },
  status: { owner: 'Status', accountant: 'Status' },
  details: { owner: 'Details', accountant: 'Details' },
  notes: { owner: 'Notes', accountant: 'Notes' },
  vendor: { owner: 'Vendor', accountant: 'Vendor' },
  customer: { owner: 'Customer', accountant: 'Customer' },
  date: { owner: 'Date', accountant: 'Date' },
  due: { owner: 'Due', accountant: 'Due' },
  balance: { owner: 'Balance', accountant: 'Balance' },
  amount: { owner: 'Amount', accountant: 'Amount' },
  price: { owner: 'Price', accountant: 'Price' },
  addLine: { owner: 'Add another item', accountant: 'Add Line Item' },
  selectVendor: { owner: 'Select vendor', accountant: 'Select vendor' },
  selectAccount: { owner: 'Select account', accountant: 'Select account' },
  useDefault: { owner: 'Use default', accountant: 'Use default' },
  billDate: { owner: 'Bill Date', accountant: 'Bill Date' },
  lineItems: { owner: 'Items', accountant: 'Line Items' },

  // Bill-specific
  billNumber: { owner: 'Bill #', accountant: 'Bill Number' },
  billAmount: { owner: 'Bill Amount', accountant: 'Bill Amount' },
  amountPaid: { owner: 'Amount Paid', accountant: 'Amount Paid' },
  paymentSummary: { owner: 'Payment Summary', accountant: 'Payment Summary' },
  markAsReceived: { owner: 'Mark as Received', accountant: 'Mark as Received' },
  recordPayment: { owner: 'Record Payment', accountant: 'Record Payment' },
  expenseAccount: { owner: 'Expense Account', accountant: 'Expense Account' },
  apAccount: { owner: 'AP Account', accountant: 'AP Account' },
  taxPercent: { owner: 'Tax %', accountant: 'Tax Rate' },
  discountPercent: { owner: 'Discount %', accountant: 'Discount Rate' },
  confirmDeleteBill: { owner: 'Are you sure you want to delete this bill?', accountant: 'Are you sure you want to delete this bill?' },
  searchBillPlaceholder: { owner: 'Search bill # or vendor invoice #', accountant: 'Search bill number or vendor invoice' },
  allVendors: { owner: 'All vendors', accountant: 'All vendors' },
  allStatus: { owner: 'All status', accountant: 'All status' },
  received: { owner: 'Received', accountant: 'Received' },
  billReceived: { owner: 'Bill received', accountant: 'Bill received' },
  partial: { owner: 'Partial', accountant: 'Partial' },
  cancelled: { owner: 'Cancelled', accountant: 'Cancelled' },
  searchInvoicePlaceholder: { owner: 'Search invoices…', accountant: 'Search invoices…' },

  // Invoice-specific
  invoiceNumber: { owner: 'Invoice #', accountant: 'Invoice Number' },
  invoiceAmount: { owner: 'Invoice Amount', accountant: 'Invoice Amount' },

  invoiceDetails: { owner: 'Invoice details', accountant: 'Invoice details' },
  invoiceDetailsHelper: { owner: 'Basic information about the invoice', accountant: 'Set invoice dates, references, and terms' },
  lineItemsHelper: { owner: 'Add what you sold', accountant: 'Add products/services with accounts and amounts' },
  incomeAccount: { owner: 'Income account', accountant: 'Income account' },
  amountSummary: { owner: 'Amount summary', accountant: 'Amount summary' },
  addLineItem: { owner: 'Add item', accountant: 'Add line item' },
  invoiceLocked: { owner: 'This invoice cannot be edited in its current status.', accountant: 'This invoice cannot be edited in its current status.' },
  customerInformation: { owner: 'Customer information', accountant: 'Customer information' },
  selectCustomerForInvoice: { owner: 'Select the customer for this invoice', accountant: 'Select the customer for this invoice' },
  arAccount: { owner: 'AR account', accountant: 'AR account' },
  useCompanyDefault: { owner: 'Use company default', accountant: 'Use company default' },
  invoiceDateLabel: { owner: 'Invoice Date', accountant: 'Invoice Date' },
  paymentTerms: { owner: 'Payment terms', accountant: 'Payment terms' },
  items: { owner: 'Items', accountant: 'Line Items' },
  description: { owner: 'Description', accountant: 'Description' },
  quantity: { owner: 'Qty', accountant: 'Quantity' },
  unitPrice: { owner: 'Price', accountant: 'Unit Price' },
  total: { owner: 'Total', accountant: 'Total' },
  subtotal: { owner: 'Subtotal', accountant: 'Subtotal' },
  tax: { owner: 'Tax', accountant: 'Tax' },
  paid: { owner: 'Paid', accountant: 'Paid' },
  balanceDue: { owner: 'Balance due', accountant: 'Balance due' },
  statusTimeline: { owner: 'Status timeline', accountant: 'Status timeline' },
  created: { owner: 'Created', accountant: 'Created' },
  sent: { owner: 'Sent', accountant: 'Sent' },
  viewed: { owner: 'Viewed', accountant: 'Viewed' },
  sendInvoice: { owner: 'Send to customer', accountant: 'Send to customer' },
  downloadPdf: { owner: 'Download PDF', accountant: 'Download PDF' },
  invoiceSummary: { owner: 'Invoice summary', accountant: 'Invoice summary' },
  additionalInformation: { owner: 'Additional information', accountant: 'Additional information' },
  customerNotes: { owner: 'Customer notes', accountant: 'Customer notes' },
  markAsSent: { owner: 'Mark as sent', accountant: 'Mark as sent' },
  back: { owner: 'Back', accountant: 'Back' },
  cancel: { owner: 'Cancel', accountant: 'Cancel' },
  saveChanges: { owner: 'Save changes', accountant: 'Save changes' },
  edit: { owner: 'Edit', accountant: 'Edit' },
  duplicate: { owner: 'Duplicate', accountant: 'Duplicate' },
  void: { owner: 'Void', accountant: 'Void' },
  dueDate: { owner: 'Due date', accountant: 'Due date' },
  reference: { owner: 'Reference', accountant: 'Reference' },
  days: { owner: 'days', accountant: 'days' },
  ownerMode: { owner: 'Owner Mode', accountant: 'Owner Mode' },
  accountantMode: { owner: 'Accountant Mode', accountant: 'Accountant Mode' },
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
  mode: UserMode,
  params?: Record<string, string | number>
): string {
  const entry = lexicon[key]

  if (!entry) {
    console.warn(`[Lexicon] Unknown key: "${key}"`)
    return String(key)
  }

  const term = entry[mode]

  if (params) {
    return interpolate(term, params)
  }

  return term
}
