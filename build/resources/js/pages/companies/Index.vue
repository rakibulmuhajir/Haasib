<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import {
  Building2,
  Plus,
  ArrowRightLeft,
  Calendar,
  CheckCircle2,
  Loader2,
  Trash2,
  Users,
  Globe,
} from 'lucide-vue-next'

interface CompanyRow {
  id: string
  name: string
  slug: string
  base_currency: string
  is_active: boolean
  role: string
  created_at: string
  user_count?: number
}

const props = defineProps<{
  companies: CompanyRow[]
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Home', href: '/dashboard' },
  { title: 'Companies' },
])

const page = usePage()
const currentCompanySlug = computed(() => (page.props.auth as any)?.currentCompany?.slug || null)

const searchQuery = ref('')
const showCreateForm = ref(false)
const deleteDialogOpen = ref(false)
const companyToDelete = ref<CompanyRow | null>(null)

const createForm = useForm({
  name: '',
  base_currency: 'USD',
})

const switchForm = useForm({
  slug: '',
})

const deleteForm = useForm({})

const filteredCompanies = computed(() => {
  if (!searchQuery.value) return props.companies

  const query = searchQuery.value.toLowerCase()
  return props.companies.filter(
    (company) =>
      company.name.toLowerCase().includes(query) ||
      company.slug.toLowerCase().includes(query) ||
      company.role.toLowerCase().includes(query)
  )
})

function submitCreate() {
  createForm.base_currency = createForm.base_currency?.toUpperCase?.() ?? ''

  createForm.post('/companies', {
    onSuccess: () => {
      createForm.reset()
      showCreateForm.value = false
    },
  })
}

function submitSwitch(slug: string) {
  switchForm.slug = slug
  switchForm.post('/companies/switch', {
    preserveScroll: true,
  })
}

function confirmDelete(company: CompanyRow) {
  companyToDelete.value = company
  deleteDialogOpen.value = true
}

function handleDelete() {
  if (!companyToDelete.value) return

  deleteForm.delete(`/companies/${companyToDelete.value.id}`, {
    onSuccess: () => {
      deleteDialogOpen.value = false
      companyToDelete.value = null
    },
  })
}
</script>

<template>
  <Head title="Companies" />
  <PageShell
    title="Companies"
    description="Manage organizations you have access to"
  :icon="Building2"
  :breadcrumbs="breadcrumbs"
  searchable
  v-model:search="searchQuery"
  search-placeholder="Search companies by name, slug, or role..."
>
    <template #actions>
      <Button @click="showCreateForm = !showCreateForm" size="sm">
        <Plus class="mr-2 h-4 w-4" />
        Create Company
      </Button>
    </template>

    <!-- Create Form Card -->
    <Card v-if="showCreateForm" class="mb-6 border-zinc-200 bg-white">
      <CardHeader>
        <CardTitle>Create New Company</CardTitle>
        <CardDescription class="text-zinc-500">
          Add a new organization to your account
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <Label for="name">Company Name</Label>
            <Input
              id="name"
              v-model="createForm.name"
              placeholder="Acme Inc"
              class="border-zinc-300"
            />
            <p v-if="createForm.errors.name" class="text-xs text-red-600">
              {{ createForm.errors.name }}
            </p>
          </div>
          <div class="space-y-2">
            <Label for="base_currency">Base Currency</Label>
            <Input
              id="base_currency"
              v-model="createForm.base_currency"
              @update:modelValue="
                (value) => (createForm.base_currency = String(value ?? '').toUpperCase())
              "
              placeholder="USD"
              maxlength="3"
              class="border-zinc-300 uppercase"
            />
            <p v-if="createForm.errors.base_currency" class="text-xs text-red-600">
              {{ createForm.errors.base_currency }}
            </p>
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <Button variant="ghost" @click="showCreateForm = false" :disabled="createForm.processing">
            Cancel
          </Button>
          <Button @click="submitCreate" :disabled="createForm.processing">
            <Loader2 v-if="createForm.processing" class="mr-2 h-4 w-4 animate-spin" />
            Create Company
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- Empty State -->
    <EmptyState
      v-if="filteredCompanies.length === 0"
      :icon="Building2"
      title="No companies found"
      :description="searchQuery ? 'Try adjusting your search terms' : 'Get started by creating your first company'"
    >
      <template #actions>
        <Button v-if="!searchQuery" @click="showCreateForm = true" size="sm">
          <Plus class="mr-2 h-4 w-4" />
          Create Company
        </Button>
      </template>
    </EmptyState>

    <!-- Companies Grid -->
    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <Card
        v-for="company in filteredCompanies"
        :key="company.id"
        class="border-zinc-200 bg-white transition-all hover:border-zinc-300 hover:shadow-md"
      >
        <CardHeader class="space-y-2 pb-3">
          <div class="flex items-start justify-between">
            <CardTitle class="flex items-center gap-2 text-zinc-900">
              <Building2 class="h-4 w-4 text-zinc-500" />
              <span class="truncate">{{ company.name }}</span>
            </CardTitle>
            <Badge :variant="company.is_active ? 'default' : 'secondary'" class="shrink-0">
              {{ company.is_active ? 'Active' : 'Inactive' }}
            </Badge>
          </div>
          <p class="text-sm text-zinc-500">{{ company.slug }}</p>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-3 text-sm">
            <div class="flex items-center justify-between">
              <span class="text-zinc-500">Role</span>
              <Badge variant="outline" class="capitalize">{{ company.role }}</Badge>
            </div>
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-1.5 text-zinc-500">
                <Globe class="h-3.5 w-3.5" />
                Currency
              </span>
              <span class="font-mono font-medium text-zinc-900">{{ company.base_currency }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-1.5 text-zinc-500">
                <Users class="h-3.5 w-3.5" />
                Users
              </span>
              <span class="font-medium text-zinc-900">{{ company.user_count || 0 }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-zinc-500">Created</span>
              <div class="flex items-center gap-1.5 text-zinc-700">
                <Calendar class="h-3.5 w-3.5" />
                <span>{{ new Date(company.created_at).toLocaleDateString() }}</span>
              </div>
            </div>
          </div>
          <Separator class="bg-zinc-100" />
          <div class="flex items-center gap-2">
            <Button
              size="sm"
              :variant="currentCompanySlug === company.slug ? 'default' : 'secondary'"
              :disabled="switchForm.processing || currentCompanySlug === company.slug"
              @click="submitSwitch(company.slug)"
              class="flex-1"
            >
              <CheckCircle2 v-if="currentCompanySlug === company.slug" class="mr-2 h-3.5 w-3.5" />
              <ArrowRightLeft v-else class="mr-2 h-3.5 w-3.5" />
              {{ currentCompanySlug === company.slug ? 'Active' : 'Switch' }}
            </Button>
            <Button
              size="sm"
              variant="ghost"
              @click="confirmDelete(company)"
              :disabled="company.role !== 'owner'"
              class="text-red-600 hover:text-red-700 hover:bg-red-50"
              aria-label="Delete company"
            >
              <Trash2 class="h-3.5 w-3.5" />
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Delete Confirmation Dialog -->
    <ConfirmDialog
      v-model:open="deleteDialogOpen"
      title="Delete Company"
      :description="`Are you sure you want to delete ${companyToDelete?.name}? This action cannot be undone and will permanently delete all associated data.`"
      confirm-text="Delete Company"
      variant="destructive"
      :loading="deleteForm.processing"
      @confirm="handleDelete"
    />
  </PageShell>
</template>
