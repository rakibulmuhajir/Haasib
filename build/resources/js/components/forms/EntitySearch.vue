<script setup lang="ts">
/**
 * EntitySearch - Customer/Vendor Search with Quick Add
 *
 * A searchable dropdown for selecting customers or vendors.
 * Features recent items, debounced search, and inline quick-add.
 *
 * @see docs/plans/invoice-bill-components-spec.md
 */
import { ref, computed, watch, onMounted } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { usePage, router } from '@inertiajs/vue3'
import { useLexicon } from '@/composables/useLexicon'
import { cn } from '@/lib/utils'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ChevronsUpDown, Search, Plus, User, Building2, Check, Clock, Loader2 } from 'lucide-vue-next'

// Types
export interface Entity {
  id: string
  name: string
  email?: string | null
  phone?: string | null
  customer_number?: string
  vendor_number?: string
}

export interface EntitySearchProps {
  modelValue: string | null
  entityType: 'customer' | 'vendor'
  placeholder?: string
  recentLimit?: number
  allowQuickAdd?: boolean
  disabled?: boolean
  error?: string
  class?: string
}

// Props
const props = withDefaults(defineProps<EntitySearchProps>(), {
  recentLimit: 5,
  allowQuickAdd: true,
  disabled: false,
})

// Emits
const emit = defineEmits<{
  'update:modelValue': [id: string | null]
  'entity-selected': [entity: Entity]
  'quick-add-click': [searchQuery: string]
}>()

// Composables
const { t } = useLexicon()
const page = usePage()

// Company context
const company = computed(() => (page.props.auth as any)?.currentCompany)

// State
const open = ref(false)
const searchQuery = ref('')
const searchResults = ref<Entity[]>([])
const recentItems = ref<Entity[]>([])
const selectedEntity = ref<Entity | null>(null)
const isSearching = ref(false)
const isLoadingRecent = ref(false)

// Computed
const entityIcon = computed(() => props.entityType === 'customer' ? User : Building2)

const searchPlaceholder = computed(() => {
  return props.placeholder || (props.entityType === 'customer'
    ? t('searchCustomers')
    : t('searchVendors'))
})

const addNewLabel = computed(() => {
  return props.entityType === 'customer'
    ? t('addNewCustomer')
    : t('addNewVendor')
})

const recentLabel = computed(() => {
  return props.entityType === 'customer'
    ? t('recentCustomers')
    : t('recentVendors')
})

const noResultsText = computed(() => {
  if (!searchQuery.value) return ''
  return props.entityType === 'customer'
    ? t('noCustomersFound')
    : t('noVendorsFound')
})

// Debounced search function
const debouncedSearch = useDebounceFn(async (query: string) => {
  if (!query || query.length < 2 || !company.value) {
    searchResults.value = []
    isSearching.value = false
    return
  }

  isSearching.value = true

  try {
    const endpoint = `/${company.value.slug}/${props.entityType}s/search`
    const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}&limit=10`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (response.ok) {
      const data = await response.json()
      searchResults.value = data.results || data.data || []
    } else {
      searchResults.value = []
    }
  } catch (error) {
    console.error(`[EntitySearch] Search failed:`, error)
    searchResults.value = []
  } finally {
    isSearching.value = false
  }
}, 300)

// Load recent items
const loadRecentItems = async () => {
  if (!company.value) return

  isLoadingRecent.value = true

  try {
    const endpoint = `/${company.value.slug}/${props.entityType}s/recent`
    const response = await fetch(`${endpoint}?limit=${props.recentLimit}`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (response.ok) {
      const data = await response.json()
      recentItems.value = data.results || data.data || []
    }
  } catch (error) {
    console.error(`[EntitySearch] Failed to load recent items:`, error)
  } finally {
    isLoadingRecent.value = false
  }
}

// Load selected entity details if modelValue is set but entity is unknown
const loadSelectedEntity = async () => {
  if (!props.modelValue || !company.value) {
    selectedEntity.value = null
    return
  }

  // Check if we already have it in recent or search results
  const existing = [...recentItems.value, ...searchResults.value].find(
    (e) => e.id === props.modelValue
  )
  if (existing) {
    selectedEntity.value = existing
    return
  }

  try {
    const endpoint = `/${company.value.slug}/${props.entityType}s/${props.modelValue}`
    const response = await fetch(endpoint, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (response.ok) {
      const data = await response.json()
      selectedEntity.value = data[props.entityType] || data.data || data
    }
  } catch (error) {
    console.error(`[EntitySearch] Failed to load entity:`, error)
  }
}

// Select an entity
const selectEntity = (entity: Entity) => {
  selectedEntity.value = entity
  emit('update:modelValue', entity.id)
  emit('entity-selected', entity)
  open.value = false
  searchQuery.value = ''
  searchResults.value = []
}

// Clear selection
const clearSelection = (e: Event) => {
  e.stopPropagation()
  selectedEntity.value = null
  emit('update:modelValue', null)
}

// Handle quick add click
const handleQuickAdd = () => {
  emit('quick-add-click', searchQuery.value)
  open.value = false
}

// Handle keyboard navigation
const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    open.value = false
  }
}

// Watch search query
watch(searchQuery, (query) => {
  if (query && query.length >= 2) {
    debouncedSearch(query)
  } else {
    searchResults.value = []
    isSearching.value = false
  }
})

// Watch open state to load recent items
watch(open, (isOpen) => {
  if (isOpen && recentItems.value.length === 0) {
    loadRecentItems()
  }
  if (isOpen) {
    // Focus search input after popover opens
    setTimeout(() => {
      const input = document.querySelector('[data-entity-search-input]') as HTMLInputElement
      input?.focus()
    }, 50)
  }
})

// Watch modelValue for external changes
watch(() => props.modelValue, (newVal) => {
  if (newVal && (!selectedEntity.value || selectedEntity.value.id !== newVal)) {
    loadSelectedEntity()
  } else if (!newVal) {
    selectedEntity.value = null
  }
}, { immediate: true })

// Initialize
onMounted(() => {
  if (props.modelValue) {
    loadSelectedEntity()
  }
})
</script>

<template>
  <div :class="cn('entity-search', props.class)">
    <Popover v-model:open="open">
      <PopoverTrigger as-child>
        <Button
          variant="outline"
          role="combobox"
          :aria-expanded="open"
          :disabled="disabled"
          :class="cn(
            'w-full justify-between font-normal',
            !selectedEntity && 'text-muted-foreground',
            error && 'border-destructive focus-visible:ring-destructive/20'
          )"
        >
          <div class="flex items-center gap-2 truncate">
            <component
              :is="entityIcon"
              class="h-4 w-4 shrink-0 opacity-50"
            />
            <span v-if="selectedEntity" class="truncate">
              {{ selectedEntity.name }}
            </span>
            <span v-else class="truncate">
              {{ searchPlaceholder }}
            </span>
          </div>
          <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>

      <PopoverContent
        class="w-[--reka-popover-trigger-width] min-w-[280px] p-0"
        align="start"
        @keydown="handleKeydown"
      >
        <!-- Search Input -->
        <div class="flex items-center border-b px-3">
          <Search class="h-4 w-4 shrink-0 text-muted-foreground" />
          <Input
            v-model="searchQuery"
            data-entity-search-input
            :placeholder="searchPlaceholder"
            class="flex h-10 w-full border-0 bg-transparent py-3 text-sm outline-none focus-visible:ring-0 focus-visible:ring-offset-0"
          />
          <Loader2 v-if="isSearching" class="h-4 w-4 shrink-0 animate-spin text-muted-foreground" />
        </div>

        <div class="max-h-[300px] overflow-y-auto">
          <!-- Recent Items -->
          <div v-if="!searchQuery && recentItems.length > 0" class="py-1">
            <div class="px-2 py-1.5 text-xs font-medium text-muted-foreground flex items-center gap-1">
              <Clock class="h-3 w-3" />
              {{ recentLabel }}
            </div>
            <button
              v-for="item in recentItems"
              :key="item.id"
              type="button"
              class="relative flex w-full cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground"
              @click="selectEntity(item)"
            >
              <component :is="entityIcon" class="mr-2 h-4 w-4 opacity-50" />
              <div class="flex-1 text-left">
                <div class="font-medium">{{ item.name }}</div>
                <div v-if="item.email" class="text-xs text-muted-foreground">
                  {{ item.email }}
                </div>
              </div>
              <Check
                v-if="modelValue === item.id"
                class="h-4 w-4 text-primary"
              />
            </button>
          </div>

          <!-- Loading Recent -->
          <div
            v-else-if="!searchQuery && isLoadingRecent"
            class="py-6 text-center text-sm text-muted-foreground"
          >
            <Loader2 class="h-5 w-5 animate-spin mx-auto mb-2" />
            Loading...
          </div>

          <!-- Search Results -->
          <div v-if="searchQuery && searchResults.length > 0" class="py-1">
            <div class="px-2 py-1.5 text-xs font-medium text-muted-foreground">
              Results
            </div>
            <button
              v-for="item in searchResults"
              :key="item.id"
              type="button"
              class="relative flex w-full cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground"
              @click="selectEntity(item)"
            >
              <component :is="entityIcon" class="mr-2 h-4 w-4 opacity-50" />
              <div class="flex-1 text-left">
                <div class="font-medium">{{ item.name }}</div>
                <div v-if="item.email" class="text-xs text-muted-foreground">
                  {{ item.email }}
                </div>
              </div>
              <Check
                v-if="modelValue === item.id"
                class="h-4 w-4 text-primary"
              />
            </button>
          </div>

          <!-- No Results -->
          <div
            v-else-if="searchQuery && searchQuery.length >= 2 && !isSearching && searchResults.length === 0"
            class="py-6 text-center text-sm text-muted-foreground"
          >
            {{ noResultsText }}
          </div>

          <!-- Type to Search Hint -->
          <div
            v-else-if="searchQuery && searchQuery.length < 2"
            class="py-6 text-center text-sm text-muted-foreground"
          >
            Type at least 2 characters to search
          </div>

          <!-- Empty Recent -->
          <div
            v-else-if="!searchQuery && !isLoadingRecent && recentItems.length === 0"
            class="py-6 text-center text-sm text-muted-foreground"
          >
            Start typing to search
          </div>
        </div>

        <!-- Quick Add Option -->
        <div v-if="allowQuickAdd" class="border-t p-1">
          <button
            type="button"
            class="relative flex w-full cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground"
            @click="handleQuickAdd"
          >
            <Plus class="mr-2 h-4 w-4" />
            <span>{{ addNewLabel }}</span>
            <span
              v-if="searchQuery"
              class="ml-1 text-muted-foreground truncate max-w-[150px]"
            >
              "{{ searchQuery }}"
            </span>
          </button>
        </div>
      </PopoverContent>
    </Popover>

    <!-- Error Message -->
    <p v-if="error" class="text-sm text-destructive mt-1">
      {{ error }}
    </p>
  </div>
</template>
