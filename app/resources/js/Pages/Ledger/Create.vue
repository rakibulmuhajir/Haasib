<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import JournalEntryForm from '@/Components/Ledger/JournalEntryForm.vue'

const page = usePage()

// Permissions
const canCreate = computed(() => 
  page.props.auth.permissions?.['ledger.create'] ?? false
)

// Get accounts from props
const accounts = computed(() => page.props.accounts as any[] || [])

// Handle form submission
const handleSuccess = () => {
  // The form component handles the success toast
  // We can add additional logic here if needed
}

// Handle form cancellation
const handleCancel = () => {
  // Navigate back to the ledger index
  window.location.href = route('ledger.index')
}
</script>

<template>
  <Head title="Create Journal Entry" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Ledger" />
    </template>

    <template #topbar>
      <div class="flex items-center justify-between w-full">
        <Breadcrumb 
          :items="[
            { label: 'Ledger', url: route('ledger.index') },
            { label: 'Create Journal Entry' }
          ]" 
        />
      </div>
    </template>

    <div class="max-w-6xl mx-auto space-y-6">
      <PageHeader
        title="Create Journal Entry"
        subtitle="Create a new double-entry journal entry"
        :maxActions="3"
      />

      <!-- Journal Entry Form -->
      <JournalEntryForm
        :accounts="accounts"
        routeName="ledger"
        submitRoute="route('ledger.store')"
        :permissions="{
          create: canCreate,
          edit: canCreate
        }"
        @success="handleSuccess"
        @cancel="handleCancel"
      />
    </div>
  </LayoutShell>
</template>