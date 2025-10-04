<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

import Card from 'primevue/card'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

const { t } = useI18n()
const toast = useToast()

const rows = ref([
  { id: 'C-1021', company: 'Blue Ocean LLC', users: 12, status: 'Active' },
  { id: 'C-1022', company: 'Coral Reef Inc.', users: 7, status: 'Invited' },
  { id: 'C-1023', company: 'Kelp Labs', users: 3, status: 'Active' }
])

const breadcrumbItems = computed(() => ([
  { label: t('dashboard.breadcrumb.dashboard'), url: '/dashboard' }
]))

const tableColumns = computed(() => ([
  { field: 'id', header: t('dashboard.cards.companies.columns.id'), style: 'width: 120px' },
  { field: 'company', header: t('dashboard.cards.companies.columns.company') },
  { field: 'users', header: t('dashboard.cards.companies.columns.users'), style: 'width: 120px' },
  { field: 'status', header: t('dashboard.cards.companies.columns.status'), style: 'width: 140px' }
]))

function notify() {
  toast.add({
    severity: 'info',
    summary: t('dashboard.toast.summary'),
    detail: t('dashboard.toast.detail'),
    life: 18000,
  })
}
</script>

<template>
    <Head :title="t('dashboard.pageTitle')" />

    <LayoutShell>
      <div class="mx-auto max-w-7xl space-y-6">
        <PageHeader
          :title="t('dashboard.header.title')"
          :subtitle="t('dashboard.header.subtitle')"
        >
          <template #below-title>
            <Breadcrumb
              :items="breadcrumbItems"
              :home="{ label: t('dashboard.breadcrumb.home'), url: '/' }"
            />
          </template>
          <template #actions-right>
            <Button
              :label="t('dashboard.actions.toast')"
              severity="secondary"
              outlined
              @click="notify"
            />
          </template>
        </PageHeader>

        <Card>
          <template #title>
            <span class="text-[color:var(--p-text-color)]">{{ t('dashboard.cards.welcome.title') }}</span>
          </template>
          <template #content>
            <p class="mb-3 text-sm" style="color: var(--p-text-muted-color)">
              {{ t('dashboard.cards.welcome.body') }}
            </p>
            <Button :label="t('dashboard.cards.welcome.primaryAction')" />
          </template>
        </Card>

        <Card>
          <template #title>
            <span class="text-[color:var(--p-text-color)]">{{ t('dashboard.cards.companies.title') }}</span>
          </template>
          <template #content>
            <DataTable :value="rows" size="small" class="w-full">
              <Column
                v-for="column in tableColumns"
                :key="column.field"
                :field="column.field"
                :header="column.header"
                :style="column.style"
              />
            </DataTable>
            <Divider />
            <div class="flex gap-2">
              <Link :href="route('admin.companies.create')">
                <Button :label="t('dashboard.cards.companies.actions.newCompany')" severity="primary" />
              </Link>
              <Link :href="route('admin.users.create')">
                <Button :label="t('dashboard.cards.companies.actions.inviteUser')" severity="secondary" outlined />
              </Link>
            </div>
          </template>
        </Card>
      </div>
    </LayoutShell>
</template>
