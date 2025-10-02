<template>
  <div>
    <!-- Only show if user can manage currencies -->
    <Button
      v-if="canManageCurrencies"
      @click="showAddCurrencyModal"
      data-test="add-currency-button"
    >
      Add Currency
    </Button>
    
    <!-- Only show system currency management to super admins -->
    <div v-if="isSuperAdmin" class="mt-4">
      <h3>System Currencies</h3>
      <Button @click="manageSystemCurrencies">
        Manage System Currencies
      </Button>
    </div>
  </div>
</template>

<script setup>
import { usePermissions } from '@/composables/usePermissions'

const { can, isSuperAdmin } = usePermissions()
const canManageCurrencies = can.manageCurrencies()

const showAddCurrencyModal = () => {
  // Show modal logic
}

const manageSystemCurrencies = () => {
  // Navigate to system currency management
}
</script>