<script setup>
import { usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import { usePageActions } from '@/composables/usePageActions'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import UniversalPageHeader from '@/Components/UniversalPageHeader.vue'
import QuickLinks from '@/Components/QuickLinks.vue'

const page = usePage()
const user = page.props.auth?.user
const { actions } = usePageActions()

// Define page actions for the dashboard
const dashboardActions = [
  {
    key: 'new-company',
    label: 'New Company',
    icon: 'pi pi-plus',
    severity: 'primary',
    routeName: 'companies.create'
  },
  {
    key: 'new-invoice',
    label: 'New Invoice',
    icon: 'pi pi-plus',
    severity: 'success',
    routeName: 'invoices.create'
  },
  {
    key: 'new-customer',
    label: 'New Customer',
    icon: 'pi pi-plus',
    severity: 'info',
    routeName: 'customers.create'
  }
]

// Define quick links for the dashboard
const quickLinks = [
  {
    label: 'View All Companies',
    url: '/companies',
    icon: 'pi pi-building'
  },
  {
    label: 'Manage Invoices',
    url: '/invoices',
    icon: 'pi pi-file'
  },
  {
    label: 'Customer Management',
    url: '/customers',
    icon: 'pi pi-users'
  },
  {
    label: 'Accounting Ledger',
    url: '/ledger',
    icon: 'pi pi-book'
  },
  {
    label: 'Financial Reports',
    url: '/reporting/dashboard',
    icon: 'pi pi-chart-bar'
  }
]

// Set page actions
actions.value = dashboardActions
</script>


<template>
  <LayoutShell>
    <template #default>
      <!-- Universal Page Header -->
      <UniversalPageHeader
        title="Dashboard"
        :description="`Welcome back, ${user?.name || 'User'}!`"
        subDescription="Manage your business from one central location"
        :default-actions="dashboardActions"
        :show-search="false"
      />

      <!-- Main Content Grid -->
      <div class="content-grid-5-6">
        <!-- Left Column - Main Cards -->
        <div class="main-content">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Companies Card -->
            <div class="dashboard-card">
              <div class="dashboard-card-header">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <i class="fas fa-building text-blue-600 text-2xl"></i>
                  </div>
                  <div class="ml-5 w-0 flex-1">
                    <dl>
                      <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Companies
                      </dt>
                      <dd class="dashboard-card-title">
                        Manage your companies
                      </dd>
                    </dl>
                  </div>
                </div>
              </div>
              <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                <div class="text-sm">
                  <Link
                    href="/companies"
                    class="font-medium text-blue-700 hover:text-blue-600 dark:text-blue-400"
                  >
                    View all companies →
                  </Link>
                </div>
              </div>
            </div>

            <!-- Invoices Card -->
            <div class="dashboard-card">
              <div class="p-5">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <i class="fas fa-file-invoice text-green-600 text-2xl"></i>
                  </div>
                  <div class="ml-5 w-0 flex-1">
                    <dl>
                      <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Invoices
                      </dt>
                      <dd class="dashboard-card-title">
                        Create and manage invoices
                      </dd>
                    </dl>
                  </div>
                </div>
              </div>
              <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                <div class="text-sm">
                  <Link
                    href="/invoices"
                    class="font-medium text-blue-700 hover:text-blue-600 dark:text-blue-400"
                  >
                    Manage invoices →
                  </Link>
                </div>
              </div>
            </div>

            <!-- Customers Card -->
            <div class="dashboard-card">
              <div class="p-5">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <i class="fas fa-users text-purple-600 text-2xl"></i>
                  </div>
                  <div class="ml-5 w-0 flex-1">
                    <dl>
                      <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Customers
                      </dt>
                      <dd class="dashboard-card-title">
                        Customer management
                      </dd>
                    </dl>
                  </div>
                </div>
              </div>
              <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
                <div class="text-sm">
                  <Link
                    href="/customers"
                    class="font-medium text-blue-700 hover:text-blue-600 dark:text-blue-400"
                  >
                    View customers →
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column - Quick Links -->
        <div class="sidebar-content">
          <QuickLinks 
            :links="quickLinks" 
            title="Quick Navigation"
          />
        </div>
      </div>
    </template>
  </LayoutShell>
</template>