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
  Landmark,
  RefreshCcw,
  Wand2,
  Scale,
  Clock,
  ScrollText,
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
          { title: 'Trial Balance', href: `/${slug}/reports/trial-balance`, icon: Scale },
          { title: 'Balance Sheet', href: `/${slug}/reports/balance-sheet`, icon: Scale },
          { title: 'Who Owes Us', href: `/${slug}/reports/receivables-aging`, icon: Clock },
          { title: 'What We Owe', href: `/${slug}/reports/payables-aging`, icon: Clock },
          { title: 'Statements', href: `/${slug}/reports/statements`, icon: ScrollText },
          { title: 'Settings', href: `/${slug}/setup`, icon: Settings },
        ],
      },
      {
        label: 'Sales',
        items: [
          { title: 'Invoices', href: `/${slug}/invoices`, icon: FileText },
          { title: 'Consolidated Invoices', href: `/${slug}/consolidated-invoices`, icon: FileText },
          { title: t('customers'), href: `/${slug}/customers`, icon: Users },
          { title: 'Customer Categories', href: `/${slug}/customer-categories`, icon: Users },
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
