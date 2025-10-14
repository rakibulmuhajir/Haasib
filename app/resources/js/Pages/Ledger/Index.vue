<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import { computed, ref, onUnmounted } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import LedgerEntriesTable from '@/Components/Ledger/LedgerEntriesTable.vue'

const props = defineProps({
  entries: Object,
  filters: Object,
})

const page = usePage()

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Ledger', url: '/ledger', icon: 'book' },
])

// Permissions
const canView = computed(() => 
  page.props.auth.permissions?.['ledger.view'] ?? false
)
const canCreate = computed(() => 
  page.props.auth.permissions?.['ledger.create'] ?? false
)
const canEdit = computed(() => 
  page.props.auth.permissions?.['ledger.edit'] ?? false
)
const canDelete = computed(() => 
  page.props.auth.permissions?.['ledger.delete'] ?? false
)
</script>

<template>
  <Head title="Journal Entries" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Ledger" />
    </template>

    <template #topbar>
      <div class="flex items-center justify-between w-full">
        <Breadcrumb :items="breadcrumbItems" />
      </div>
    </template>

    <div class="space-y-6">
      <PageHeader
        title="Journal Entries"
        subtitle="View and manage your accounting journal entries"
        :maxActions="5"
      />

      <!-- Journal Entries Table -->
      <LedgerEntriesTable
        :entries="entries"
        :filters="filters"
        routeName="ledger"
        :permissions="{
          view: canView,
          create: canCreate,
          edit: canEdit,
          delete: canDelete
        }"
        :bulkActions="{
          post: canEdit,
          void: canDelete
        }"
      />
    </div>
  </LayoutShell>
</template>