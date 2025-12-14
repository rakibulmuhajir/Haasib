<script setup lang="ts">
/**
 * QuickAddModal - Inline Customer/Vendor Creation
 *
 * A modal dialog for quickly creating a customer or vendor
 * without leaving the current form. Minimal fields required.
 *
 * @see docs/plans/invoice-bill-components-spec.md
 */
import { ref, computed, watch, nextTick } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { useLexicon } from '@/composables/useLexicon'
import { useFormFeedback } from '@/composables/useFormFeedback'
import { cn } from '@/lib/utils'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { User, Building2, Loader2 } from 'lucide-vue-next'

// Types
export interface QuickEntity {
  id: string
  name: string
  email?: string | null
}

// Props
export interface QuickAddModalProps {
  open: boolean
  entityType: 'customer' | 'vendor'
  initialName?: string              // Pre-fill from search query
}

const props = withDefaults(defineProps<QuickAddModalProps>(), {
  initialName: '',
})

// Emits
const emit = defineEmits<{
  'update:open': [value: boolean]
  'created': [entity: QuickEntity]
}>()

// Composables
const { t } = useLexicon()
const { showSuccess, showError } = useFormFeedback()
const page = usePage()

// Company context
const company = computed(() => (page.props.auth as any)?.currentCompany)

// Refs
const nameInputRef = ref<HTMLInputElement | null>(null)

// Form
const form = useForm({
  name: '',
  email: '',
})

// Computed
const entityIcon = computed(() => props.entityType === 'customer' ? User : Building2)

const dialogTitle = computed(() => {
  return props.entityType === 'customer'
    ? t('quickAddCustomer')
    : t('quickAddVendor')
})

const dialogDescription = computed(() => {
  return props.entityType === 'customer'
    ? t('quickAddCustomerDescription')
    : t('quickAddVendorDescription')
})

const namePlaceholder = computed(() => {
  return props.entityType === 'customer'
    ? 'Acme Corporation'
    : 'Office Depot'
})

const submitLabel = computed(() => {
  return t('createAndSelect')
})

const isValid = computed(() => {
  return form.name.trim().length > 0
})

// Handle form submission
const handleSubmit = async () => {
  if (!company.value || !isValid.value) return

  const endpoint = `/${company.value.slug}/${props.entityType}s/quick-store`

  form.post(endpoint, {
    preserveScroll: true,
    onSuccess: (page) => {
      // Get the created entity from the response
      const flash = (page.props as any).flash
      const entity = flash?.entity || (page.props as any).entity

      if (entity) {
        emit('created', entity)
        showSuccess(
          props.entityType === 'customer'
            ? t('customerCreated')
            : t('vendorCreated')
        )
      }

      emit('update:open', false)
      resetForm()
    },
    onError: (errors) => {
      showError(errors)
    },
  })
}

// Reset form
const resetForm = () => {
  form.reset()
  form.clearErrors()
}

// Handle dialog open state change
const handleOpenChange = (open: boolean) => {
  emit('update:open', open)
  if (!open) {
    resetForm()
  }
}

// Initialize form with initial name
watch(() => props.open, (isOpen) => {
  if (isOpen) {
    form.name = props.initialName || ''
    form.email = ''
    form.clearErrors()

    // Focus name input after dialog opens
    nextTick(() => {
      setTimeout(() => {
        nameInputRef.value?.focus()
        nameInputRef.value?.select()
      }, 50)
    })
  }
})
</script>

<template>
  <Dialog :open="open" @update:open="handleOpenChange">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <component :is="entityIcon" class="h-5 w-5" />
          {{ dialogTitle }}
        </DialogTitle>
        <DialogDescription>
          {{ dialogDescription }}
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <!-- Name (Required) -->
        <div class="space-y-2">
          <Label for="quick-add-name" class="required">
            {{ t('name') }}
          </Label>
          <Input
            id="quick-add-name"
            ref="nameInputRef"
            v-model="form.name"
            :placeholder="namePlaceholder"
            :disabled="form.processing"
            :class="cn(form.errors.name && 'border-destructive')"
            autocomplete="off"
          />
          <p v-if="form.errors.name" class="text-sm text-destructive">
            {{ form.errors.name }}
          </p>
        </div>

        <!-- Email (Optional) -->
        <div class="space-y-2">
          <Label for="quick-add-email">
            {{ t('email') }}
            <span class="text-muted-foreground font-normal">({{ t('optional') }})</span>
          </Label>
          <Input
            id="quick-add-email"
            type="email"
            v-model="form.email"
            placeholder="billing@example.com"
            :disabled="form.processing"
            :class="cn(form.errors.email && 'border-destructive')"
            autocomplete="off"
          />
          <p v-if="form.errors.email" class="text-sm text-destructive">
            {{ form.errors.email }}
          </p>
        </div>

        <!-- Help Text -->
        <p class="text-sm text-muted-foreground">
          {{ t('addDetailsLater') }}
        </p>

        <DialogFooter class="gap-2 sm:gap-0">
          <Button
            type="button"
            variant="outline"
            @click="handleOpenChange(false)"
            :disabled="form.processing"
          >
            {{ t('cancel') }}
          </Button>
          <Button
            type="submit"
            :disabled="form.processing || !isValid"
          >
            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
            {{ submitLabel }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>

<style scoped>
.required::after {
  content: ' *';
  color: hsl(var(--destructive));
}
</style>
