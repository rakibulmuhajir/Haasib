<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import UniversalLayout from '@/layouts/UniversalLayout.vue'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import CompanyUsers from '@/components/CompanyUsers.vue'

const props = defineProps<{
    company: {
        id: string
        name: string
        email: string
        industry: string
        country: string
        base_currency: string
        created_at: string
        updated_at: string
    }
}>()

const breadcrumbs = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Companies', href: '/companies' },
    { label: 'Company Details', active: true }
]

const headerActions = [
    { 
        label: 'Edit Company', 
        variant: 'outline' as const,
        href: `/companies/${props.company.id}/edit`
    },
    { 
        label: 'Back to Companies', 
        variant: 'default' as const,
        href: '/companies'
    }
]
</script>

<template>
  <Head :title="`${props.company.name} - Company Details`" />
  
  <UniversalLayout
    :title="props.company.name"
    subtitle="Company details and information"
    :breadcrumbs="breadcrumbs"
    :header-actions="headerActions"
  >
    <div class="p-6">
      <Tabs default-value="details" class="w-full">
        <TabsList class="grid w-full grid-cols-2">
          <TabsTrigger value="details">Company Details</TabsTrigger>
          <TabsTrigger value="users">Users</TabsTrigger>
        </TabsList>
        
        <TabsContent value="details" class="mt-6">
          <div class="bg-white dark:bg-gray-800 rounded-lg border p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h3 class="text-lg font-semibold mb-4">Company Information</h3>
                <dl class="space-y-2">
                  <div class="flex justify-between">
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Name:</dt>
                    <dd>{{ props.company.name }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Email:</dt>
                    <dd>{{ props.company.email }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Industry:</dt>
                    <dd>{{ props.company.industry }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Country:</dt>
                    <dd>{{ props.company.country }}</dd>
                  </div>
                </dl>
              </div>
              
              <div>
                <h3 class="text-lg font-semibold mb-4">System Information</h3>
                <dl class="space-y-2">
                  <div class="flex justify-between">
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Currency:</dt>
                    <dd>{{ props.company.base_currency }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Created:</dt>
                    <dd>{{ new Date(props.company.created_at).toLocaleDateString() }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Last Updated:</dt>
                    <dd>{{ new Date(props.company.updated_at).toLocaleDateString() }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="font-medium text-gray-600 dark:text-gray-400">Company ID:</dt>
                    <dd class="font-mono text-sm">{{ props.company.id }}</dd>
                  </div>
                </dl>
              </div>
            </div>
          </div>
        </TabsContent>
        
        <TabsContent value="users" class="mt-6">
          <CompanyUsers :company-id="props.company.id" />
        </TabsContent>
      </Tabs>
    </div>
  </UniversalLayout>
</template>