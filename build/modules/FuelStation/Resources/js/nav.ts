import type { ModuleNavConfig } from '@/navigation/types'
import type { NavGroup, NavItem } from '@/types'
import { ClipboardCheck, CreditCard, Fuel, Droplets, Gauge, HandCoins, ReceiptText, Banknote, Users, UsersRound, Truck, Warehouse, BarChart3, Package, Settings, TrendingUp, Landmark, UserCog, BookOpen, FileMinus, ArrowLeftRight, Scale, Clock } from 'lucide-vue-next'

export const fuelStationNav: ModuleNavConfig = {
  id: 'fuel_station',
  label: 'Fuel Station',
  mode: 'replace',
  isEnabled: (context) => Boolean(context.slug && context.isFuelStationCompany),
  getNavGroups: (context) => {
    const { slug, t, fuelNavigation } = context
    if (!slug || !context.isFuelStationCompany) return []
    const allowed = new Set(fuelNavigation?.allowed ?? [])
    const item = (key: string, title: string, path: string, icon: NavItem['icon'], enabled = true): NavItem[] =>
      enabled && allowed.has(key) ? [{ title, href: `/${slug}${path}`, icon }] : []

    const groups: NavGroup[] = [
      { label: 'Daily Close', items: [
        ...item('dailyClose', 'Daily Close', '/fuel/daily-close', ClipboardCheck),
        ...(!allowed.has('dailyClose') ? item('closeHistory', 'Daily Close History', '/fuel/daily-close/history', ClipboardCheck) : []),
      ] },
      { label: 'Stock', items: [
        ...item('stock', 'Stock Overview', '/stock', Warehouse, context.isInventoryEnabled),
        ...item('deliveries', 'Fuel Deliveries', '/fuel/receipts', Droplets),
        ...item('stock', 'Stock Movements', '/stock/movements', Warehouse, context.isInventoryEnabled),
        ...item('prices', 'Fuel Prices', '/fuel/rates', TrendingUp),
        // Company home contains the station product setup workspace.
        ...item('products', 'Products You Sell', '', Package),
      ] },
      { label: 'Purchases', items: [
        ...item('bills', t('bills'), '/bills', ReceiptText),
        ...item('bills', 'Bill Payments', '/bill-payments', Banknote),
        ...item('vendors', t('vendors'), '/vendors', Truck),
        ...item('settlements', 'Vendor Card Settlement', '/fuel/vendor-cards/pending', CreditCard),
        ...item('expenses', 'Record Expense', '/expenses', ReceiptText),
      ] },
      { label: 'Customers', items: [
        ...item('customers', 'All Customers', '/customers', Users),
        ...item('customers', 'Credit Customers', '/fuel/credit-customers', CreditCard),
        ...item('fuelSale', 'Record Fuel Sale', '/fuel/sales/form', Fuel),
        ...item('customers', 'Amanat Depositors', '/fuel/amanat', HandCoins),
        ...item('payments', 'Payments Received', '/payments', HandCoins),
        ...item('creditNotes', 'Credit Notes', '/credit-notes', FileMinus),
      ] },
      { label: 'Team & Partners', items: [
        ...item('employees', 'Employees', '/employees', UserCog, context.isPayrollEnabled),
        ...item('payroll', 'Payroll', '/payroll', Banknote, context.isPayrollEnabled),
        ...item('employees', 'Salary Advances', '/salary-advances', HandCoins, context.isPayrollEnabled),
        ...item('partners', 'Partners', '/partners', UsersRound),
        ...item('investors', 'Investors', '/fuel/investors', UsersRound, fuelNavigation?.hasInvestors === true),
      ] },
      { label: t('reports'), items: [
        ...item('reports', 'Station Performance', '/fuel/reports/performance', BarChart3),
        ...item('reports', 'Product Profitability', '/fuel/reports/product-profitability', Package),
        ...item('reports', 'Expenses', '/fuel/reports/expenses', ReceiptText),
        ...item('reports', t('profitAndLoss'), '/reports/profit-loss', BarChart3),
        ...item('reports', 'Trial Balance', '/reports/trial-balance', Scale),
        ...item('reports', 'Balance Sheet', '/reports/balance-sheet', Scale),
        ...item('reports', 'Receivables Aging', '/reports/receivables-aging', Clock),
        ...item('reports', 'Stock Variance & Claims', '/fuel/reports/stock-variance', TrendingUp),
        ...item('payroll', 'Salary Report', '/payroll/reports/salary', Banknote, context.isPayrollEnabled),
      ] },
      { label: 'Banking', items: [
        ...item('banking', t('bankAccounts'), '/banking/accounts', Landmark),
        ...item('banking', 'Bank Transactions', '/banking/transactions', Landmark),
        ...item('bankFeed', 'Bank Feed', '/banking/feed', ArrowLeftRight),
        ...item('bankReconciliation', 'Bank Reconciliation', '/banking/reconciliation', Scale),
      ] },
      { label: t('settings'), items: [
        ...item('settings', 'Station Settings', '/fuel/settings', Settings),
        ...item('warehouses', 'Tanks & Warehouses', '/warehouses', Warehouse, context.isInventoryEnabled),
        ...item('pumps', 'Pumps & Nozzles', '/fuel/pumps', Gauge),
        ...item('settings', 'Setup Wizard', '/fuel/onboarding', Settings),
        { title: 'Help Guide', href: `/${slug}/fuel/guide`, icon: BookOpen },
        ...item('journals', 'Advanced Accounting · Journal Entries', '/journals', BookOpen),
        ...item('accounts', t('chartOfAccounts'), '/accounts', BookOpen),
        ...item('accountSettings', 'Default Accounts', '/accounting/default-accounts', Settings),
      ] },
    ]
    return groups.filter(group => group.items.length > 0)
  },
}
