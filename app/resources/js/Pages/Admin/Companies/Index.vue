<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, onMounted, watch } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import { http } from '@/lib/http'

const q = ref('')
const loading = ref(false)
const items = ref([])
const error = ref('')

async function fetchCompanies() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.get('/web/companies', { params: { q: q.value, limit: 50 } })
    items.value = data.data || []
  } catch (e) {
    error.value = 'Failed to load companies'
  } finally {
    loading.value = false
  }
}

onMounted(fetchCompanies)
watch(q, () => { const t = setTimeout(fetchCompanies, 250); return () => clearTimeout(t) })

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
      <div class="flex items-center justify-between w-full">
        <Breadcrumb :items="breadcrumbItems" />
        <Link :href="route('admin.companies.create')">
          <Button label="Create Company" icon="pi pi-plus" />
        </Link>
      </div>
    </template>

    <div class="space-y-4">
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
        :value="items" 
        :loading="loading" 
        stripedRows 
        class="w-full"
        :paginator="items.length > 10"
        :rows="10"
        :rowsPerPageOptions="[10, 25, 50]"
      >
        <Column field="name" header="Company Name">
          <template #body="slotProps">
            <div>
              <div class="font-medium">{{ slotProps.data.name }}</div>
              <div class="text-sm text-gray-500">{{ slotProps.data.slug }}</div>
            </div>
          </template>
        </Column>
        <Column field="base_currency" header="Currency" />
        <Column field="language" header="Language" />
        <Column field="locale" header="Locale" />
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
