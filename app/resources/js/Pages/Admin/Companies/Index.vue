<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, onMounted, watch } from 'vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
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
</script>

<template>
  <Head title="Companies" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Companies</h2>
        <Link :href="route('admin.companies.create')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Create Company</Link>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="mb-4 flex items-center gap-2">
          <TextInput v-model="q" placeholder="Search companies by name or slug…" class="w-72" />
          <PrimaryButton @click="fetchCompanies">Search</PrimaryButton>
        </div>

        <div v-if="error" class="text-red-600 dark:text-red-400 text-sm mb-2">{{ error }}</div>
        <DataTable :value="items" :loading="loading" stripedRows class="w-full">
          <Column field="name" header="Company Name">
            <template #body="slotProps">
              <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                {{ slotProps.data.name }}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ slotProps.data.slug }}
              </div>
            </template>
          </Column>
          <Column field="base_currency" header="Currency" />
          <Column field="language" header="Language" />
          <Column field="locale" header="Locale" />
          <Column header="Actions">
            <template #body="slotProps">
              <Link :href="route('admin.companies.show', slotProps.data.slug || slotProps.data.id)">
                <Button label="Manage" size="small" />
              </Link>
            </template>
          </Column>
          <template #empty>
            <div class="text-sm text-gray-500 dark:text-gray-400 px-4 py-6">No companies found.</div>
          </template>
          <template #loading>
            <div class="text-sm text-gray-500 dark:text-gray-400 px-4 py-6">Loading…</div>
          </template>
        </DataTable>
      </div>
    </div>
  </AuthenticatedLayout>

</template>
