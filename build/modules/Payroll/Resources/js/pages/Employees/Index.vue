<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import {
  Users,
  Plus,
  Eye,
  Pencil,
  Trash2,
  MoreHorizontal,
  Search,
} from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface Manager {
  id: string
  first_name: string
  last_name: string
}

interface EmployeeRow {
  id: string
  employee_number: string
  first_name: string
  last_name: string
  email: string | null
  department: string | null
  position: string | null
  employment_status: string
  employment_type: string
  is_active: boolean
  manager: Manager | null
}

interface PaginatedEmployees {
  data: EmployeeRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  company: CompanyRef
  employees: PaginatedEmployees
  filters: {
    search: string
    status: string
    department: string
  }
}>()

const search = ref(props.filters.search)

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Employees', href: `/${props.company.slug}/employees` },
]

const handleSearch = () => {
  router.get(
    `/${props.company.slug}/employees`,
    { search: search.value },
    { preserveState: true }
  )
}

const columns = [
  { key: 'employee_number', label: 'ID' },
  { key: 'name', label: 'Name' },
  { key: 'department', label: 'Department' },
  { key: 'position', label: 'Position' },
  { key: 'status', label: 'Status' },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() => {
  return props.employees.data.map((employee) => ({
    id: employee.id,
    employee_number: employee.employee_number,
    name: `${employee.first_name} ${employee.last_name}`,
    department: employee.department ?? '-',
    position: employee.position ?? '-',
    status: employee.employment_status,
    _raw: employee,
  }))
})

const handleRowClick = (row: any) => {
  router.get(`/${props.company.slug}/employees/${row.id}`)
}

const handleDelete = (id: string) => {
  if (confirm('Are you sure you want to delete this employee?')) {
    router.delete(`/${props.company.slug}/employees/${id}`)
  }
}

const getStatusVariant = (status: string) => {
  const variants: Record<string, 'success' | 'secondary' | 'destructive' | 'outline'> = {
    active: 'success',
    on_leave: 'outline',
    suspended: 'destructive',
    terminated: 'secondary',
  }
  return variants[status] || 'secondary'
}

const formatStatus = (status: string) => {
  return status.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase())
}
</script>

<template>
  <Head title="Employees" />

  <PageShell
    title="Employees"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button @click="router.get(`/${company.slug}/employees/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Add Employee
      </Button>
    </template>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap items-center gap-4">
      <div class="relative flex-1 min-w-[200px] max-w-sm">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          placeholder="Search employees..."
          class="pl-9"
          @keyup.enter="handleSearch"
        />
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-if="employees.data.length === 0"
      title="No employees yet"
      description="Add employees to manage your workforce and process payroll."
      :icon="Users"
    >
      <Button @click="router.get(`/${company.slug}/employees/create`)">
        <Plus class="mr-2 h-4 w-4" />
        Add Employee
      </Button>
    </EmptyState>

    <!-- Data Table -->
    <DataTable
      v-else
      :columns="columns"
      :data="tableData"
      :pagination="{
        currentPage: employees.current_page,
        lastPage: employees.last_page,
        perPage: employees.per_page,
        total: employees.total,
      }"
      @row-click="handleRowClick"
    >
      <template #cell-status="{ row }">
        <Badge :variant="getStatusVariant(row._raw.employment_status)">
          {{ formatStatus(row.status) }}
        </Badge>
      </template>

      <template #cell-_actions="{ row }">
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="h-8 w-8">
              <MoreHorizontal class="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem @click="router.get(`/${company.slug}/employees/${row.id}`)">
              <Eye class="mr-2 h-4 w-4" />
              View
            </DropdownMenuItem>
            <DropdownMenuItem @click="router.get(`/${company.slug}/employees/${row.id}/edit`)">
              <Pencil class="mr-2 h-4 w-4" />
              Edit
            </DropdownMenuItem>
            <DropdownMenuItem class="text-destructive" @click="handleDelete(row.id)">
              <Trash2 class="mr-2 h-4 w-4" />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </template>
    </DataTable>
  </PageShell>
</template>
