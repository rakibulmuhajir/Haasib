<template>
  <Dialog
    v-model:visible="visible"
    :header="'Validation Errors'"
    :style="{ width: '500px' }"
    modal
    :closable="false"
  >
    <div class="space-y-4">
      <div class="flex items-start">
        <i class="fas fa-exclamation-circle text-red-500 text-xl mt-0.5 mr-3"></i>
        <div>
          <h4 class="text-lg font-medium text-gray-900 dark:text-white">
            Please fix the following errors:
          </h4>
        </div>
      </div>

      <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
        <ul class="space-y-2">
          <li 
            v-for="(error, index) in errorList" 
            :key="index"
            class="flex items-start text-sm text-red-700 dark:text-red-300"
          >
            <i class="fas fa-times-circle mr-2 mt-0.5 flex-shrink-0"></i>
            <span>{{ error }}</span>
          </li>
        </ul>
      </div>

      <div class="text-sm text-gray-600 dark:text-gray-400">
        <i class="fas fa-info-circle mr-1"></i>
        Please correct these errors and try again.
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end">
        <Button
          label="OK"
          severity="primary"
          @click="hide"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'

interface Props {
  visible: boolean
  errors: Record<string, string> | string[]
}

interface Emits {
  (e: 'update:visible', value: boolean): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const visible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

const errorList = computed(() => {
  if (Array.isArray(props.errors)) {
    return props.errors
  }
  return Object.values(props.errors)
})

const hide = () => {
  visible.value = false
}
</script>