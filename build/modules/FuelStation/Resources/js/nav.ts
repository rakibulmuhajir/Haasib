import type { ModuleNavConfig } from '@/navigation/types'
import {
  ClipboardCheck,
  CreditCard,
  DollarSign,
  Droplets,
  FileText,
  FolderTree,
  Gauge,
  HandCoins,
  History,
  Landmark,
  LayoutGrid,
  Layers,
  ArrowLeftRight,
  Receipt,
  ReceiptText,
  Banknote,
  Users,
  UsersRound,
  Truck,
  Warehouse,
  BarChart3,
  Fuel,
  Package,
  Settings,
  TrendingUp,
  UserCog,
  Wallet,
  PackageCheck,
} from 'lucide-vue-next'

export const fuelStationNav: ModuleNavConfig = {
  id: 'fuel_station',
  label: 'Fuel Station',
  mode: 'replace',
  isEnabled: (context) => Boolean(context.slug && context.isFuelStationCompany),
  getNavGroups: (context) => {
    const { slug, isInventoryEnabled, isPayrollEnabled } = context
    if (!slug || !context.isFuelStationCompany) return []

    const groups = [
      {
        label: 'Dashboard',
        items: [
          { title: 'Dashboard', href: `/${slug}/fuel/dashboard`, icon: LayoutGrid },
        ],
      },
      {
        label: 'Daily Operations',
        items: [
          { title: 'Daily Close', href: `/${slug}/fuel/daily-close`, icon: ClipboardCheck },
          { title: 'Daily Close History', href: `/${slug}/fuel/daily-close/history`, icon: History },
          { title: 'Handovers', href: `/${slug}/fuel/handovers`, icon: HandCoins },
          { title: 'Daily Sales Report', href: `/${slug}/fuel/reports/sales`, icon: BarChart3 },
        ],
      },
      {
        label: 'Fuel Operations',
        items: [
          { title: 'Pump Readings', href: `/${slug}/fuel/pump-readings`, icon: Gauge },
          { title: 'Tank Readings', href: `/${slug}/fuel/tank-readings`, icon: Warehouse },
          { title: 'Fuel Receipts', href: `/${slug}/fuel/receipts`, icon: Droplets },
          { title: 'Shrinkage Report', href: `/${slug}/fuel/reports/shrinkage`, icon: TrendingUp },
        ],
      },
      {
        label: 'Sales',
        items: [
          { title: 'Invoices', href: `/${slug}/invoices`, icon: FileText },
          { title: 'Credit Sales', href: `/${slug}/fuel/credit-sales`, icon: CreditCard },
          { title: 'Credit Notes', href: `/${slug}/credit-notes`, icon: Receipt },
          { title: 'Customers', href: `/${slug}/customers`, icon: Users },
        ],
      },
      {
        label: 'Collections & Payments',
        items: [
          { title: 'Customer Payments', href: `/${slug}/payments`, icon: DollarSign },
          { title: 'Amanat', href: `/${slug}/fuel/amanat`, icon: HandCoins },
          { title: 'Vendor Card Settlement', href: `/${slug}/fuel/vendor-cards/pending`, icon: CreditCard },
        ],
      },
      {
        label: 'Purchases',
        items: [
          { title: 'Bills', href: `/${slug}/bills?type=fuel`, icon: ReceiptText },
          { title: 'Bill Payments', href: `/${slug}/bill-payments`, icon: Banknote },
          { title: 'Vendor Credits', href: `/${slug}/vendor-credits`, icon: Receipt },
          { title: 'Vendors', href: `/${slug}/vendors`, icon: Truck },
        ],
      },
      {
        label: 'Banking & Finance',
        items: [
          { title: 'Bank Accounts', href: `/${slug}/banking/accounts`, icon: Landmark },
          { title: 'Journal Entries', href: `/${slug}/journals`, icon: FileText },
          { title: 'Profit Summary', href: `/${slug}/reports/profit-loss`, icon: BarChart3 },
          {
            title: 'Accounting Settings',
            icon: Settings,
            children: [
              { title: 'Fiscal Years', href: `/${slug}/fiscal-years` },
              { title: 'Default Accounts', href: `/${slug}/accounting/default-accounts` },
              { title: 'Posting Templates', href: `/${slug}/posting-templates` },
            ],
          },
        ],
      },
    ]

    if (isInventoryEnabled) {
      groups.push({
        label: 'Inventory',
        items: [
          { title: 'Items', href: `/${slug}/items`, icon: Package },
          { title: 'Stock Levels', href: `/${slug}/stock`, icon: Layers },
          { title: 'Stock Receipts', href: `/${slug}/stock/receipts`, icon: PackageCheck },
          { title: 'Stock History', href: `/${slug}/stock/movements`, icon: ArrowLeftRight },
          { title: 'Locations', href: `/${slug}/warehouses`, icon: Warehouse },
          { title: 'Categories', href: `/${slug}/item-categories`, icon: FolderTree },
        ],
      })
    }

    const peopleItems = []
    if (isPayrollEnabled) {
      peopleItems.push(
        { title: 'Employees', href: `/${slug}/employees`, icon: UserCog },
        { title: 'Salary Advances', href: `/${slug}/salary-advances`, icon: Wallet },
      )
    }
    peopleItems.push(
      { title: 'Partners', href: `/${slug}/partners`, icon: UsersRound },
      { title: 'Investors', href: `/${slug}/fuel/investors`, icon: UsersRound },
    )

    groups.push({
      label: 'People',
      items: peopleItems,
    })

    groups.push(
      {
        label: 'Station Setup',
        items: [
          { title: 'Setup Wizard', href: `/${slug}/fuel/onboarding`, icon: Settings },
          { title: 'Pumps & Nozzles', href: `/${slug}/fuel/pumps`, icon: Gauge },
          { title: 'Tanks', href: `/${slug}/warehouses`, icon: Warehouse },
          { title: 'Fuel Products', href: `/${slug}/items?category=fuel`, icon: Fuel },
          { title: 'Lubricants', href: `/${slug}/items?category=lubricant`, icon: Package },
          { title: 'Rate Changes', href: `/${slug}/fuel/rates`, icon: TrendingUp },
          { title: 'Payment Channels', href: `/${slug}/fuel/settings`, icon: CreditCard },
        ],
      },
    )

    return groups
  },
}
