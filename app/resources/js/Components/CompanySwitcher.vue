<script setup>
import { ref, onMounted, computed, watchEffect } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { http, withCsrf } from '@/lib/http'
import Dropdown from 'primevue/dropdown'

const page = usePage()
const companies = ref([])
const currentCompanyId = computed(() => page.props.auth.companyId)
const isSuperAdmin = computed(() => page.props.auth?.isSuperAdmin || false)
const selectedCompany = ref(null)

// Prepare options for the dropdown
const dropdownOptions = computed(() => {
  const options = [...companies.value]

  // Add option to remove company context for super admins
  if (isSuperAdmin.value) {
    options.unshift({
      id: null,
      name: '🌐 Global View (Super Admin)',
      description: 'Remove company context to perform system-wide duties'
    })
  }

  return options
})

// Find the current selection
const currentValue = computed(() => {
  if (!currentCompanyId.value && isSuperAdmin.value) {
    return dropdownOptions.value.find(opt => opt.id === null)
  }
  return dropdownOptions.value.find(opt => opt.id === currentCompanyId.value)
})

// Update selectedCompany when currentValue changes
watchEffect(() => {
  selectedCompany.value = currentValue.value?.id ?? null
})

onMounted(async () => {
  try {
    // This endpoint returns all companies for a superadmin,
    // and associated companies for a regular user.
    const { data } = await http.get('/web/companies')
    companies.value = data.data
    // console.log('🔍 [DEBUG] Loaded companies:', companies.value)
    // console.log('🔍 [DEBUG] Current company ID from page props:', currentCompanyId.value)
    // console.log('🔍 [DEBUG] Current company from session:', page.props.auth?.currentCompany)
  } catch (e) {
    console.error('🔍 [DEBUG] Error loading companies:', e)
    companies.value = []
  }
})

async function switchCompany(event) {
  const companyId = event.value
  console.log('🔍 [DEBUG] Switching to company:', companyId)
  console.log('🔍 [DEBUG] Current company ID:', currentCompanyId.value)
  console.log('🔍 [DEBUG] Available companies:', companies.value)

  // If removing company context
  if (companyId === null && isSuperAdmin.value) {
    console.log('🔍 [DEBUG] Attempting to clear company context')
    try {
      const response = await http.post('/company/clear-context', {}, { headers: withCsrf() })
      console.log('🔍 [DEBUG] Clear context response:', response.data)

      router.visit(window.location.pathname, {
        method: 'get',
        preserveState: false,
        preserveScroll: false,
        only: ['auth']
      })
    } catch (e) {
      console.error('🔍 [DEBUG] Error clearing company context:', e)
      console.error('🔍 [DEBUG] Error response:', e.response?.data)
    }
    return
  }

  if (!companyId || companyId === currentCompanyId.value) return

  try {
    const response = await http.post(`/company/${companyId}/switch`, {}, { headers: withCsrf() })
    console.log('🔍 [DEBUG] Switch response:', response.data)

    // Use Inertia's router to visit the current page with fresh data
    // This will trigger a fresh server request and update all props
    router.visit(window.location.pathname, {
      method: 'get',
      preserveState: false,
      preserveScroll: false,
      only: ['auth'] // Only refresh the auth data
    })
  } catch (e) {
    console.error('🔍 [DEBUG] Error switching company:', e)
    console.error('🔍 [DEBUG] Error response:', e.response?.data)
  }
}
</script>

<template>
  <Dropdown
    v-model="selectedCompany"
    :options="dropdownOptions"
    optionLabel="name"
    optionValue="id"
    :placeholder="isSuperAdmin ? 'Select Company or Global View' : 'Select Company'"
    @update:model-value="switchCompany({ value: $event })"
    class="company-switcher"
  >
    <template #option="slotProps">
      <div v-if="slotProps.option.id === null" class="flex items-center">
        <span class="text-lg mr-2">🌐</span>
        <div>
          <div class="font-medium">{{ slotProps.option.name }}</div>
          <div class="text-xs text-gray-500">{{ slotProps.option.description }}</div>
        </div>
      </div>
      <div v-else>
        {{ slotProps.option.name }}
      </div>
    </template>
    <template #value="slotProps">
      <div v-if="slotProps.value && slotProps.value.id === null" class="flex items-center">
        <span class="text-lg mr-2">🌐</span>
        <span>Global View</span>
      </div>
      <div v-else-if="slotProps.value">
        {{ slotProps.value.name }}
      </div>
      <span v-else>{{ isSuperAdmin ? 'Select Company or Global View' : 'Select Company' }}</span>
    </template>
  </Dropdown>
</template>

<style scoped>
.company-switcher {
  min-width: 280px;
}

.company-switcher :deep(.p-dropdown) {
  border: 1px solid var(--p-content-border-color, var(--surface-border));
  background-color: var(--p-content-background, var(--surface-card));
  color: var(--p-text-color, var(--text-color));
  border-radius: 0.375rem;
  height: 2.5rem;
  padding: 0 0.75rem;
}

.company-switcher :deep(.p-dropdown:hover) {
  border-color: var(--p-primary-color, var(--primary-color));
}

.company-switcher :deep(.p-dropdown:focus) {
  outline: none;
  box-shadow: 0 0 0 2px var(--p-primary-color, var(--primary-color));
}

.company-switcher :deep(.p-dropdown-label) {
  color: var(--p-text-color, var(--text-color));
}

.company-switcher :deep(.p-dropdown-trigger) {
  color: var(--p-text-muted-color, var(--text-color-secondary));
}
</style>
