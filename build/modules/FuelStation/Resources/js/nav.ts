import type { ModuleNavConfig } from '@/navigation/types'
import type { NavGroup, NavItem } from '@/types'
import { ClipboardCheck, CreditCard, Fuel, Droplets, HandCoins, ReceiptText, Banknote, Users, UsersRound, Truck, Warehouse, BarChart3, Package, Settings, TrendingUp, Landmark, UserCog, FileMinus, ArrowLeftRight, Scale, Clock, ScrollText, CalendarDays } from 'lucide-vue-next'

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
        // Opens the history; a new close starts from its button.
        ...(allowed.has('closeHistory')
          ? item('closeHistory', 'Daily Close', '/fuel/daily-close/history', ClipboardCheck)
          : item('dailyClose', 'Daily Close', '/fuel/daily-close', ClipboardCheck)),
      ] },
      { label: 'Stock', items: [
        // Products, prices, cost and what is on hand in one register; adjustments start there.
        ...item('products', 'Products & stock', '/fuel/products', Package),
        ...item('deliveries', 'Fuel Deliveries', '/fuel/receipts', Droplets),
        ...item('stock', 'Stock Movements', '/stock/movements', Warehouse, context.isInventoryEnabled),
        ...item('prices', 'Fuel Prices', '/fuel/rates', TrendingUp),
      ] },
      { label: 'Purchases', items: [
        ...item('bills', t('bills'), '/bills', ReceiptText),
        ...item('bills', 'Bill Payments', '/bill-payments', Banknote),
        ...item('vendors', t('vendors'), '/vendors', Truck),
        ...item('settlements', 'Vendor Card Settlement', '/fuel/vendor-cards/pending', CreditCard),
        ...item('expenses', 'Record Expense', '/expenses', ReceiptText),
      ] },
      { label: 'Customers', items: [
        // One customer list: balances, limits, discounts and statements, with Edit details
        // for contact fields. The accounting customer pages stay reachable from there.
        ...item('customers', 'Customers', '/fuel/credit-customers', Users),
        ...item('invoices', t('invoices'), '/invoices', ReceiptText),
        ...item('customers', 'Consolidated Invoices', '/consolidated-invoices', ScrollText),
        ...item('fuelSale', 'Record Fuel Sale', '/fuel/sales/form', Fuel),
        ...item('customers', 'Amanat Depositors', '/fuel/amanat', HandCoins),
        ...item('payments', 'Payments Received', '/payments', HandCoins),
        ...item('creditNotes', 'Credit Notes', '/credit-notes', FileMinus),
      ] },
      { label: 'Team & Partners', items: [
        ...item('employees', 'Employees', '/employees', UserCog, context.isPayrollEnabled),
        ...item('payroll', 'Payroll', '/payroll', Banknote, context.isPayrollEnabled),
        ...item('employees', 'Advances', '/salary-advances', HandCoins, context.isPayrollEnabled),
        ...item('partners', 'Partners', '/partners', UsersRound),
        ...item('investors', 'Investors', '/fuel/investors', UsersRound, fuelNavigation?.hasInvestors === true),
      ] },
      { label: t('reports'), items: [
        // Eight, not twelve: the rest open from inside these pages -- the stock statement is a
        // Statements tab, Tank Gains & Losses and Expenses link from Month Summary and the stock
        // statement, Trial Balance from the Balance Sheet, Journal Entries from all three
        // accounting reports.
        ...item('reports', 'Month Summary', '/fuel/daily-close/month', CalendarDays),
        ...item('reports', 'Profit by day', '/fuel/reports/performance', BarChart3),
        ...item('reports', 'Fuel Profit', '/fuel/reports/product-profitability', Package),
        ...item('reports', 'Profit & Loss', '/reports/profit-loss', BarChart3),
        ...item('reports', 'Balance Sheet', '/reports/balance-sheet', Scale),
        ...item('reports', 'Who Owes Us', '/reports/receivables-aging', Clock),
        ...item('reports', 'What We Owe', '/reports/payables-aging', Clock),
        ...item('reports', 'Statements', '/reports/statements', ScrollText),
      ] },
      { label: 'Banking', items: [
        ...item('banking', t('bankAccounts'), '/banking/accounts', Landmark),
        ...item('banking', 'Bank Transactions', '/banking/transactions', Landmark),
        ...item('bankFeed', 'Bank Feed', '/banking/feed', ArrowLeftRight),
        ...item('bankReconciliation', 'Bank Reconciliation', '/banking/reconciliation', Scale),
      ] },
      { label: t('settings'), items: [
        // Everything set up once -- company, station, accounts -- lives on the one Settings page.
        { title: t('settings'), href: `/${slug}/setup`, icon: Settings },
      ] },
    ]
    return groups.filter(group => group.items.length > 0)
  },
}
