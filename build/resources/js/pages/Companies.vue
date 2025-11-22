<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import UniversalLayout from '@/layouts/UniversalLayout.vue'
import { Button } from '@/components/ui/button'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { useToast } from '@/components/ui/toast/use-toast'

const props = defineProps<{
    companies?: Array<any>
    activeCompanyId?: string
}>()

// State management
const activeCompanyId = ref<string | null>(props.activeCompanyId || null)

const { toast } = useToast()

const breadcrumbs = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Companies', active: true }
]

const headerActions = [
    { 
        label: 'Add Company', 
        variant: 'default' as const,
        href: '/companies/create'
    }
]

// Methods
const activateCompany = (company: any) => {
    activeCompanyId.value = company.id
    router.post(`/company/${company.id}/switch`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast({
                title: 'Company Switched',
                description: `${company.name} is now active.`,
            })
        },
        onError: () => {
            activeCompanyId.value = null
            toast({
                title: 'Activation failed',
                description: 'Unable to activate the company. Please try again.',
                variant: 'destructive',
            })
        }
    })
}

const confirmDelete = (company: any) => {
    if (confirm(`Are you sure you want to delete ${company.name}? This action cannot be undone.`)) {
        router.delete(`/companies/${company.id}`, {
            onSuccess: () => {
                // Company deleted successfully
            },
            onError: () => {
                // Handle error
            }
        })
    }
}
</script>

<template>
  <Head title="Companies" />
  
  <UniversalLayout
    title="Companies"
    subtitle="Manage your company accounts and settings"
    :breadcrumbs="breadcrumbs"
    :header-actions="headerActions"
  >
    <div class="p-6">
      <div class="mb-6">
        <h2 class="text-2xl font-semibold mb-2">Company Management</h2>
        <p class="text-gray-600">Manage all your companies and their settings.</p>
      </div>

      <div class="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Company Name</TableHead>
              <TableHead>Industry</TableHead>
              <TableHead>Country</TableHead>
              <TableHead>Currency</TableHead>
              <TableHead>Created</TableHead>
              <TableHead class="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-if="!companies || companies.length === 0">
              <TableCell colspan="6" class="text-center py-8 text-muted-foreground">
                No companies found. 
                <Link :href="'/companies/create'" class="text-blue-600 hover:underline ml-2">
                  Create your first company
                </Link>
              </TableCell>
            </TableRow>
            <TableRow v-for="company in (companies || [])" :key="company.id">
              <TableCell class="font-medium">
                <div>
                  <div class="font-semibold">{{ company.name }}</div>
                  <div class="text-sm text-muted-foreground">{{ company.email }}</div>
                </div>
              </TableCell>
              <TableCell>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                  {{ company.industry }}
                </span>
              </TableCell>
              <TableCell>{{ company.country }}</TableCell>
              <TableCell>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                  {{ company.base_currency }}
                </span>
              </TableCell>
              <TableCell>
                {{ new Date(company.created_at).toLocaleDateString() }}
              </TableCell>
              <TableCell class="text-right">
                <div class="flex justify-end gap-2">
                  <Button 
                    size="sm" 
                    variant="outline"
                    @click="router.visit(`/companies/${company.id}`)"
                  >
                    View
                  </Button>
                  <Button 
                    size="sm" 
                    variant="outline"
                    @click="router.visit(`/companies/${company.id}/edit`)"
                  >
                    Edit
                  </Button>
                  <Button 
                    size="sm" 
                    variant="outline"
                    @click="activateCompany(company)"
                    :class="{ 'bg-green-50 border-green-200 text-green-700 hover:bg-green-100': company.id === activeCompanyId }"
                  >
                    {{ company.id === activeCompanyId ? 'Active' : 'Activate' }}
                  </Button>
                  <Button 
                    size="sm" 
                    variant="destructive"
                    @click="confirmDelete(company)"
                  >
                    Delete
                  </Button>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </UniversalLayout>
</template>
