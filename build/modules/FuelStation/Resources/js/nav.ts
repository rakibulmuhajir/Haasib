import type { ModuleNavConfig } from '@/navigation/types'
import {
  ClipboardCheck,
  CreditCard,
  Droplets,
  Gauge,
  HandCoins,
  History,
  ReceiptText,
  Banknote,
  Users,
  UsersRound,
  Truck,
  Warehouse,
  BarChart3,
  Package,
  Settings,
  TrendingUp,
  Landmark,
  UserCog,
} from 'lucide-vue-next'

export const fuelStationNav: ModuleNavConfig = {
  id: 'fuel_station',
  label: 'Fuel Station',
  mode: 'replace',
  isEnabled: (context) => Boolean(context.slug && context.isFuelStationCompany),
  getNavGroups: (context) => {
    const { slug, mode } = context
    if (!slug || !context.isFuelStationCompany) return []

    const settingsItems = [
      { title: 'Station Settings', href: `/${slug}/fuel/settings`, icon: Settings },
      { title: 'Tanks & Warehouses', href: `/${slug}/warehouses`, icon: Warehouse },
      { title: 'Pumps & Nozzles', href: `/${slug}/fuel/pumps`, icon: Gauge },
      { title: 'Rate Changes', href: `/${slug}/fuel/rates`, icon: TrendingUp },
      { title: 'Bank Accounts', href: `/${slug}/banking/accounts`, icon: Landmark },
      { title: 'Setup Wizard', href: `/${slug}/fuel/onboarding`, icon: Settings },
      { title: 'Help Guide', href: `/${slug}/fuel/guide`, icon: Settings },
    ]

    if (mode === 'accountant') {
      settingsItems.push(
        { title: 'Default Accounts', href: `/${slug}/accounting/default-accounts`, icon: Settings },
        { title: 'Journal Entries', href: `/${slug}/journals`, icon: ReceiptText },
      )
    }

    return [
      {
        label: 'Fuel Station',
        items: [
          { title: 'Daily Close', href: `/${slug}/fuel/daily-close`, icon: ClipboardCheck },
          { title: 'Stock Management', href: `/${slug}/stock`, icon: Warehouse },
          { title: 'Products', href: `/${slug}`, icon: Package },
          {
            title: 'Purchases',
            icon: ReceiptText,
            children: [
              { title: 'Bill Payments', href: `/${slug}/bill-payments`, icon: Banknote },
              { title: 'Vendors', href: `/${slug}/vendors`, icon: Truck },
              { title: 'Fuel Deliveries', href: `/${slug}/fuel/receipts`, icon: Droplets },
              { title: 'Vendor Card Settlement', href: `/${slug}/fuel/vendor-cards/pending`, icon: CreditCard },
            ],
          },
          {
            title: 'People',
            icon: Users,
            children: [
              { title: 'Customers', href: `/${slug}/customers`, icon: Users },
              { title: 'Amanat Depositors', href: `/${slug}/fuel/amanat`, icon: HandCoins },
              { title: 'Credit Customers', href: `/${slug}/fuel/credit-customers`, icon: CreditCard },
              { title: 'Payroll Overview', href: `/${slug}/payroll`, icon: Banknote },
              { title: 'Employees', href: `/${slug}/employees`, icon: UserCog },
              { title: 'Advance Balances', href: `/${slug}/salary-advances`, icon: HandCoins },
              { title: 'Partners', href: `/${slug}/partners`, icon: UsersRound },
              { title: 'Investors', href: `/${slug}/fuel/investors`, icon: UsersRound },
            ],
          },
          {
            title: 'Reports',
            icon: BarChart3,
            children: [
              { title: 'Station Performance', href: `/${slug}/fuel/reports/performance`, icon: BarChart3 },
              { title: 'Product Profitability', href: `/${slug}/fuel/reports/product-profitability`, icon: Package },
              { title: 'Expenses', href: `/${slug}/fuel/reports/expenses`, icon: ReceiptText },
              { title: 'Daily Close History', href: `/${slug}/fuel/daily-close/history`, icon: History },
              { title: 'Profit Summary', href: `/${slug}/reports/profit-loss`, icon: BarChart3 },
              { title: 'Salary Report', href: `/${slug}/payroll/reports/salary`, icon: Banknote },
              { title: 'Stock Movements', href: `/${slug}/stock/movements`, icon: Warehouse },
              { title: 'Stock Variance & Claims', href: `/${slug}/fuel/reports/stock-variance`, icon: TrendingUp },
            ],
          },
          {
            title: 'Settings',
            icon: Settings,
            children: settingsItems,
          },
        ],
      },
    ]
  },
}
