<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useCompanyContext } from '@/composables/useCompanyContext'

const props = defineProps({
  isSlim: {
    type: Boolean,
    default: false
  }
})

const {
  currentCompany,
  userCompanies,
  switchToCompany,
  getCompanyAvatarData,
  formatCompanyDisplay
} = useCompanyContext()

const hasCompanies = computed(() => userCompanies.value.length > 0)

// Filter companies for dropdown
const availableCompanies = computed(() => {
  return userCompanies.value.filter(company =>
    !currentCompany.value || company.id !== currentCompany.value.id
  )
})

const handleCompanySwitch = async (company) => {
  await switchToCompany(company.id)
}
</script>

<template>
  <div v-if="hasCompanies" class="sidebar-company-switcher">
    <!-- Current Company Display -->
    <div v-if="currentCompany && !isSlim" class="current-company">
      <div class="flex items-center gap-2">
        <div
          class="company-avatar w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold"
          :class="getCompanyAvatarData(currentCompany).color"
        >
          {{ getCompanyAvatarData(currentCompany).label }}
        </div>
        <div class="flex-1 min-w-0">
          <div class="company-name text-sm font-medium truncate">
            {{ currentCompany.name }}
          </div>
          <div class="company-role text-xs text-gray-500 dark:text-gray-400 truncate">
            {{ currentCompany.userRole }}
          </div>
        </div>
      </div>

      <!-- Company Switch Dropdown -->
      <div v-if="availableCompanies.length > 0" class="company-dropdown mt-2">
        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 px-3">
          Switch Company
        </div>
        <div class="company-list">
          <button
            v-for="company in availableCompanies"
            :key="company.id"
            @click="handleCompanySwitch(company)"
            class="company-option w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-800 rounded transition-colors"
          >
            <div
              class="company-avatar w-6 h-6 rounded-full flex items-center justify-center text-white text-xs font-bold"
              :class="getCompanyAvatarData(company).color"
            >
              {{ getCompanyAvatarData(company).label }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="company-name text-sm truncate">
                {{ company.name }}
              </div>
              <div class="company-role text-xs text-gray-500 dark:text-gray-400 truncate">
                {{ company.userRole }}
              </div>
            </div>
          </button>
        </div>
      </div>
    </div>

    <!-- Slim Mode: Show just the avatar -->
    <div v-else-if="currentCompany && isSlim" class="current-company-slim">
      <div
        class="company-avatar flex items-center justify-center text-white text-sm font-bold mx-auto shadow-md"
        :class="getCompanyAvatarData(currentCompany).color"
        v-tooltip.right="formatCompanyDisplay(currentCompany)"
      >
        {{ getCompanyAvatarData(currentCompany).label }}
      </div>
    </div>

    <!-- No Company State -->
    <div v-else-if="!currentCompany" class="no-company">
      <Link
        v-if="!isSlim"
        href="/companies/create"
        class="create-company-btn flex items-center gap-2 px-3 py-2 text-sm bg-primary text-primary-contrast rounded hover:bg-primary-600 transition-colors"
      >
        <i class="pi pi-plus"></i>
        <span>Create Company</span>
      </Link>

      <Link
        v-else
        href="/companies/create"
        class="create-company-btn w-12 h-12 rounded-2xl bg-primary text-primary-contrast flex items-center justify-center mx-auto hover:bg-primary-600 transition-colors shadow-md"
        v-tooltip.right="'Create Company'"
      >
        <i class="pi pi-plus"></i>
      </Link>
    </div>
  </div>
</template>

<style scoped>
.sidebar-company-switcher {
  /* Additional spacing if needed */
}

.current-company {
  /* Current company display styles */
}

.company-avatar {
  width: 48px;
  height: 48px;
  border-radius: 16px;
}

.company-name {
  font-weight: 500;
}

.company-role {
  text-transform: capitalize;
}

.company-dropdown {
  border-top: 1px solid var(--surface-border);
  padding-top: 0.5rem;
  margin-top: 0.5rem;
}

.company-option {
  border: none;
  background: transparent;
  cursor: pointer;
  transition: all 0.2s ease;
}

.company-option:hover {
  transform: translateX(2px);
}

.create-company-btn {
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.create-company-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>
