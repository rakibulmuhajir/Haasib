<script setup lang="ts">
import UniversalLayout from '@/layouts/UniversalLayout.vue'
import { Head } from '@inertiajs/vue3'

const breadcrumbs = [
  { label: 'Dashboard', href: '/dashboard' },
  { label: 'Accounting', active: true }
]
</script>

<template>
  <Head title="Accounting Dashboard" />
  <UniversalLayout
    title="Accounting"
    subtitle="Income-side foundation"
    :breadcrumbs="breadcrumbs"
  >
    <div class="p-6 space-y-4">
      <div class="rounded-lg border p-4 bg-background">
        <h2 class="text-lg font-semibold mb-2">Accounting Module</h2>
        <p class="text-muted-foreground text-sm">Placeholder page for the Accounting module. Routes are wired and migrations applied.</p>
      </div>
    </div>
  </UniversalLayout>
</template>
