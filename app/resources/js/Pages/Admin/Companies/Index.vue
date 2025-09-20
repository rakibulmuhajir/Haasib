<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, onMounted, computed, watch } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Tag from 'primevue/tag'
import { usePageActions } from '@/composables/usePageActions.js'
import { useApiList } from '@/composables/useApiList.js'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { http } from '@/lib/http'

const q = ref('')
const selectedCompanies = ref([])
const confirm = useConfirm()
const toast = useToast()
const { items, loading, error, fetch: fetchCompanies } = useApiList('/web/companies', {
  query: q,
  initialParams: { limit: 50 }
})

const { setActions } = usePageActions()

const hasSelected = computed(() => selectedCompanies.value.length > 0)
const hasInactive = computed(() => selectedCompanies.value.some(c => !c.is_active))
const hasActive = computed(() => selectedCompanies.value.some(c => c.is_active))

// Bulk actions
async function bulkActivate() {
  confirm.require({
    message: `Are you sure you want to activate ${selectedCompanies.value.length} selected compan${selectedCompanies.value.length > 1 ? 'ies' : 'y'}?`,
    header: 'Confirm Activate',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Yes, activate them',
    rejectLabel: 'Cancel',
    accept: async () => {
      try {
        await Promise.all(
          selectedCompanies.value.map(company => 
            http.patch(`/web/companies/${company.slug}/activate`)
          )
        )
        toast.add({ 
          severity: 'success', 
          summary: 'Success', 
          detail: `${selectedCompanies.value.length} compan${selectedCompanies.value.length > 1 ? 'ies have' : 'y has'} been activated`,
          life: 3000 
        })
        selectedCompanies.value = []
        fetchCompanies()
      } catch (e) {
        toast.add({ 
          severity: 'error', 
          summary: 'Error', 
          detail: 'Failed to activate companies',
          life: 3000 
        })
      }
    }
  })
}

async function bulkDeactivate() {
  confirm.require({
    message: `Are you sure you want to deactivate ${selectedCompanies.value.length} selected compan${selectedCompanies.value.length > 1 ? 'ies' : 'y'}? Users will not be able to access these companies.`,
    header: 'Confirm Deactivate',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Yes, deactivate them',
    rejectLabel: 'Cancel',
    accept: async () => {
      try {
        await Promise.all(
          selectedCompanies.value.map(company => 
            http.patch(`/web/companies/${company.slug}/deactivate`)
          )
        )
        toast.add({ 
          severity: 'success', 
          summary: 'Success', 
          detail: `${selectedCompanies.value.length} compan${selectedCompanies.value.length > 1 ? 'ies have' : 'y has'} been deactivated`,
          life: 3000 
        })
        selectedCompanies.value = []
        fetchCompanies()
      } catch (e) {
        toast.add({ 
          severity: 'error', 
          summary: 'Error', 
          detail: 'Failed to deactivate companies',
          life: 3000 
        })
      }
    }
  })
}

onMounted(() => {
  fetchCompanies()
  updateActions()
})

function updateActions() {
  const actions = [
    { key: 'create', label: 'Create Company', icon: 'pi pi-plus', severity: 'primary', click: () => router.visit(route('admin.companies.create')) },
  ]
  
  if (hasSelected.value) {
    if (hasActive.value) {
      actions.push({
        key: 'deactivate', 
        label: `Deactivate Selected (${selectedCompanies.value.length})`, 
        icon: 'pi pi-ban', 
        severity: 'warning', 
        click: bulkDeactivate
      })
    }
    if (hasInactive.value) {
      actions.push({
        key: 'activate', 
        label: `Activate Selected (${selectedCompanies.value.length})`, 
        icon: 'pi pi-check', 
        severity: 'success', 
        click: bulkActivate
      })
    }
  }
  
  setActions(actions)
}

// Watch for selection changes
watch([selectedCompanies, items], () => {
  updateActions()
})

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Admin', url: '/admin', icon: 'settings' },
  { label: 'Companies', url: '/admin/companies', icon: 'companies' }
])
</script>

<template>
  <Head title="Companies" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Admin Panel" />
    </template>

    <template #topbar>
      <Breadcrumb :items="breadcrumbItems" />
    </template>

    <div class="space-y-4">
      <PageHeader title="Companies" subtitle="Manage all companies in the system" />
      <div class="flex items-center gap-2">
        <InputText
          v-model="q"
          placeholder="Search companies by name or slug…"
          class="w-96"
          @keyup.enter="fetchCompanies"
        />
        <Button label="Search" @click="fetchCompanies" />
      </div>

      <div v-if="error" class="p-3 rounded bg-red-50 text-red-700 border border-red-200">{{ error }}</div>

      <DataTable
        v-model:selection="selectedCompanies"
        :value="items"
        :loading="loading"
        stripedRows
        class="w-full"
        :paginator="items.length > 10"
        :rows="10"
        :rowsPerPageOptions="[10, 25, 50]"
        dataKey="id"
      >
        <Column selectionMode="multiple" headerStyle="width: 3rem" />
        <Column field="name" header="Company Name">
          <template #body="slotProps">
            <div class="flex items-center gap-2">
              <div>
                <div class="font-medium">{{ slotProps.data.name }}</div>
                <div class="text-sm text-gray-500">{{ slotProps.data.slug }}</div>
              </div>
              <Tag 
                v-if="!slotProps.data.is_active" 
                severity="danger" 
                value="Inactive" 
                size="small"
              />
            </div>
          </template>
        </Column>
        <Column field="base_currency" header="Currency" />
        <Column field="language" header="Language" />
        <Column field="locale" header="Locale" />
        <Column field="status" header="Status">
          <template #body="slotProps">
            <Tag 
              :severity="slotProps.data.is_active ? 'success' : 'danger'"
              :value="slotProps.data.is_active ? 'Active' : 'Inactive'"
              size="small"
            />
          </template>
        </Column>
        <Column header="Actions">
          <template #body="slotProps">
            <Link :href="route('admin.companies.show', slotProps.data.slug || slotProps.data.id)">
              <Button label="Manage" size="small" icon="pi pi-settings" />
            </Link>
          </template>
        </Column>
        <template #empty>
          <div class="text-center py-8 text-gray-500">No companies found.</div>
        </template>
        <template #loading>
          <div class="text-center py-8 text-gray-500">Loading…</div>
        </template>
      </DataTable>
    </div>
  </LayoutShell>
</template>
