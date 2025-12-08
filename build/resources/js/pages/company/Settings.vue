<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import PageShell from '@/components/PageShell.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Settings,
  Users,
  Building,
  Shield,
  Calculator,
  CreditCard,
  FileText,
  ChevronRight,
} from 'lucide-vue-next'

interface Company {
  id: string
  name: string
  slug: string
  industry?: string
  country?: string
  base_currency: string
  is_active: boolean
  created_at: string
  current_user_role: string
  can_manage_company: boolean
  can_manage_users: boolean
}

const page = usePage()
const props = page.props as any

const company = ref<Company>(props.company)

const settingsSections = [
  {
    title: 'General',
    description: 'Company information, logo, and basic settings',
    icon: Building,
    href: '#', // Will be implemented later
    color: 'text-blue-600',
  },
  {
    title: 'Users & Permissions',
    description: 'Manage team members and their access levels',
    icon: Users,
    href: `/${company.value.slug}/users`,
    color: 'text-green-600',
    disabled: !company.value.can_manage_users,
  },
  {
    title: 'Tax Settings',
    description: 'Configure VAT, tax rates, and tax compliance',
    icon: Calculator,
    href: `/${company.value.slug}/tax/settings`,
    color: 'text-purple-600',
    badge: {
      text: 'New',
      variant: 'default' as const,
    },
  },
  {
    title: 'Accounting',
    description: 'Chart of accounts, fiscal years, and accounting periods',
    icon: CreditCard,
    href: '#', // Will be implemented when GL core is ready
    color: 'text-orange-600',
    disabled: true, // Not implemented yet
  },
  {
    title: 'Security',
    description: 'Security settings and two-factor authentication',
    icon: Shield,
    href: '#', // Will be implemented later
    color: 'text-red-600',
    disabled: true, // Not implemented yet
  },
]

const quickActions = [
  {
    title: 'Enable Saudi VAT',
    description: 'Quick setup for 15% Saudi Arabia VAT compliance',
    icon: Calculator,
    href: `/${company.value.slug}/tax/settings`,
    variant: 'default' as const,
    action: 'enable-saudi-vat',
    condition: true, // Always show for Saudi companies
  },
  {
    title: 'Manage Tax Rates',
    description: 'Configure different tax rates and jurisdictions',
    icon: Calculator,
    href: `/${company.value.slug}/tax/settings`,
    variant: 'outline' as const,
  },
  {
    title: 'View Documentation',
    description: 'Learn how to set up taxes and compliance',
    icon: FileText,
    href: '/docs/tax-management',
    variant: 'outline' as const,
  },
]

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency === 'SAR' ? 'SAR' : 'USD',
    minimumFractionDigits: 2,
  }).format(amount)
}

const getRoleDisplayName = (role: string) => {
  const roleNames: Record<string, string> = {
    owner: 'Owner',
    admin: 'Administrator',
    accountant: 'Accountant',
    member: 'Member',
  }
  return roleNames[role] || role
}

const getRoleBadgeVariant = (role: string) => {
  const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    owner: 'default',
    admin: 'secondary',
    accountant: 'outline',
    member: 'secondary',
  }
  return variants[role] || 'secondary'
}
</script>

<template>
  <Head title="Company Settings" />

  <PageShell>
    <div class="max-w-7xl mx-auto">
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">{{ company.name }} Settings</h1>
        <p class="text-gray-600 mt-2">Manage your company configuration, users, and settings.</p>
      </div>
      <!-- Company Overview -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Building class="w-5 h-5" />
            {{ company.name }}
          </CardTitle>
          <CardDescription>
            Company overview and your current role in this organization
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <div class="text-sm font-medium text-gray-500">Industry</div>
              <div class="text-sm">{{ company.industry || 'Not specified' }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">Country</div>
              <div class="text-sm">{{ company.country || 'Not specified' }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">Base Currency</div>
              <div class="text-sm">{{ company.base_currency }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">Status</div>
              <div class="flex items-center gap-2">
                <Badge :variant="company.is_active ? 'default' : 'secondary'">
                  {{ company.is_active ? 'Active' : 'Inactive' }}
                </Badge>
              </div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">Created</div>
              <div class="text-sm">{{ new Date(company.created_at).toLocaleDateString() }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">Your Role</div>
              <div>
                <Badge :variant="getRoleBadgeVariant(company.current_user_role)">
                  {{ getRoleDisplayName(company.current_user_role) }}
                </Badge>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Quick Actions -->
      <Card>
        <CardHeader>
          <CardTitle>Quick Actions</CardTitle>
          <CardDescription>
            Common tasks and quick setup options for your company
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="action in quickActions" :key="action.title">
              <Link :href="action.href">
                <Card class="hover:shadow-md transition-shadow cursor-pointer">
                  <CardContent class="p-4">
                    <div class="flex items-start space-x-3">
                      <div class="p-2 rounded-lg bg-gray-100">
                        <component :is="action.icon" class="w-5 h-5 text-gray-600" />
                      </div>
                      <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-medium">{{ action.title }}</h4>
                        <p class="text-sm text-gray-500 mt-1">{{ action.description }}</p>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </Link>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Settings Sections -->
      <div class="space-y-6">
        <div>
          <h2 class="text-lg font-semibold">Settings</h2>
          <p class="text-sm text-gray-600 mt-1">Configure different aspects of your company</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Card v-for="section in settingsSections" :key="section.title"
                :class="{ 'opacity-50 cursor-not-allowed': section.disabled }">
            <Link :href="section.disabled ? '#' : section.href">
              <CardContent class="p-6">
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-3">
                    <div class="p-2 rounded-lg bg-gray-100">
                      <component :is="section.icon" :class="`w-5 h-5 ${section.color}`" />
                    </div>
                    <div>
                      <h3 class="font-medium">{{ section.title }}</h3>
                      <p class="text-sm text-gray-500 mt-1">{{ section.description }}</p>
                    </div>
                  </div>
                  <div class="flex items-center space-x-2">
                    <Badge v-if="section.badge" :variant="section.badge.variant">
                      {{ section.badge.text }}
                    </Badge>
                    <ChevronRight class="w-4 h-4 text-gray-400" />
                  </div>
                </div>
              </CardContent>
            </Link>
          </Card>
        </div>
      </div>

      <!-- Tax Settings Spotlight -->
      <Card class="bg-purple-50 border-purple-200">
        <CardHeader>
          <CardTitle class="text-purple-900">
            <Calculator class="w-5 h-5 inline mr-2" />
            Tax Management Setup
          </CardTitle>
          <CardDescription class="text-purple-700">
            Configure VAT compliance and tax settings for your business
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg p-4 border border-purple-200">
              <h4 class="font-medium text-purple-900">Saudi VAT (15%)</h4>
              <p class="text-sm text-purple-700 mt-1">
                Configure standard Saudi Arabia VAT rates and registration numbers
              </p>
            </div>
            <div class="bg-white rounded-lg p-4 border border-purple-200">
              <h4 class="font-medium text-purple-900">Multiple Tax Rates</h4>
              <p class="text-sm text-purple-700 mt-1">
                Support for different tax jurisdictions and compound tax calculations
              </p>
            </div>
            <div class="bg-white rounded-lg p-4 border border-purple-200">
              <h4 class="font-medium text-purple-900">Exemptions</h4>
              <p class="text-sm text-purple-700 mt-1">
                Handle zero-rated supplies and tax-exempt customers/vendors
              </p>
            </div>
          </div>
          <div class="pt-2">
            <Link :href="`/${company.slug}/tax/settings`">
              <Button variant="default" class="bg-purple-600 hover:bg-purple-700">
                <Calculator class="w-4 h-4 mr-2" />
                Configure Tax Settings
              </Button>
            </Link>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>