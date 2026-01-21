import type { ModuleNavConfig } from '@/navigation/types'
import {
  FileText,
  BookOpen,
  Users,
  DollarSign,
  Receipt,
  Banknote,
  ReceiptText,
  Truck,
  Settings,
  CircleDollarSign,
  BarChart3,
  Calendar,
  Landmark,
  RefreshCcw,
  Wand2,
} from 'lucide-vue-next'

export const accountingNav: ModuleNavConfig = {
  id: 'accounting',
  label: 'Accounting',
  isEnabled: (context) => Boolean(context.slug),
  getNavGroups: (context) => {
    const { slug, t } = context
    if (!slug) return []

    return [
      {
        label: t('accounting'),
        items: [
          { title: 'Journal Entries', href: `/${slug}/journals`, icon: FileText },
          { title: t('chartOfAccounts'), href: `/${slug}/accounts`, icon: BookOpen },
          { title: t('profitAndLoss'), href: `/${slug}/reports/profit-loss`, icon: BarChart3 },
          {
            title: 'Setup',
            icon: Settings,
            children: [
              { title: 'Default Accounts', href: `/${slug}/accounting/default-accounts`, icon: Settings },
              { title: 'Fiscal Years', href: `/${slug}/fiscal-years`, icon: Calendar },
              { title: 'Posting Templates', href: `/${slug}/posting-templates`, icon: Settings },
            ],
          },
        ],
      },
      {
        label: 'Sales',
        items: [
          { title: 'Invoices', href: `/${slug}/invoices`, icon: FileText },
          { title: t('customers'), href: `/${slug}/customers`, icon: Users },
          { title: 'Payments', href: `/${slug}/payments`, icon: DollarSign },
          { title: 'Credit Notes', href: `/${slug}/credit-notes`, icon: Receipt },
        ],
      },
      {
        label: 'Purchases',
        items: [
          { title: 'Bills', href: `/${slug}/bills`, icon: ReceiptText },
          { title: 'Bill Payments', href: `/${slug}/bill-payments`, icon: Banknote },
          { title: t('vendors'), href: `/${slug}/vendors`, icon: Truck },
          { title: 'Vendor Credits', href: `/${slug}/vendor-credits`, icon: Receipt },
        ],
      },
      {
        label: 'Banking',
        items: [
          {
            title: 'Bank',
            icon: Landmark,
            children: [
              { title: t('bankAccounts'), href: `/${slug}/banking/accounts`, icon: Landmark },
              { title: t('reconciliation'), href: `/${slug}/banking/reconciliation`, icon: RefreshCcw },
              { title: t('transactionsToReview'), href: `/${slug}/banking/feed`, icon: Receipt },
              { title: t('bankRules'), href: `/${slug}/banking/rules`, icon: Wand2 },
            ],
          },
        ],
      },
    ]
  },
}
