import { ref, computed, watch } from 'vue'
import { useDynamicPageActions } from './useDynamicPageActions'

export function useBulkSelection(initialItems = [], itemType = 'items') {
  const items = ref(initialItems)
  const selectedItems = ref([])
  const selectAll = ref(false)
  const { addBulkActions, setActions } = useDynamicPageActions()

  // Computed properties
  const isIndeterminate = computed(() => {
    return selectedItems.value.length > 0 && selectedItems.value.length < items.value.length
  })

  const selectedCount = computed(() => selectedItems.value.length)

  const hasSelection = computed(() => selectedItems.value.length > 0)

  // Methods
  const toggleItemSelection = (item) => {
    const index = selectedItems.value.findIndex(selected => selected.id === item.id)
    if (index > -1) {
      selectedItems.value.splice(index, 1)
    } else {
      selectedItems.value.push(item)
    }
  }

  const isItemSelected = (item) => {
    return selectedItems.value.some(selected => selected.id === item.id)
  }

  const toggleSelectAll = () => {
    if (selectAll.value) {
      selectedItems.value = []
    } else {
      selectedItems.value = [...items.value]
    }
  }

  const clearSelection = () => {
    selectedItems.value = []
    selectAll.value = false
  }

  const selectAllItems = () => {
    selectedItems.value = [...items.value]
    selectAll.value = true
  }

  const updatePageActions = () => {
    if (selectedItems.value.length > 0) {
      const bulkActions = addBulkActions(selectedItems.value, itemType)
      setActions(bulkActions)
    } else {
      // Clear actions and return to default page actions
      const { initializeActions } = useDynamicPageActions()
      initializeActions()
    }
  }

  const updateItems = (newItems) => {
    items.value = newItems
    // Filter selected items to only include items that still exist
    selectedItems.value = selectedItems.value.filter(selected =>
      items.value.some(item => item.id === selected.id)
    )
  }

  // Watch for items changes and update selectAll state
  watch(items, () => {
    if (selectedItems.value.length === items.value.length && items.value.length > 0) {
      selectAll.value = true
    } else {
      selectAll.value = false
    }
  }, { deep: true })

  // Watch for selectedItems changes
  watch(selectedItems, () => {
    if (selectedItems.value.length === items.value.length && items.value.length > 0) {
      selectAll.value = true
    } else {
      selectAll.value = false
    }
    updatePageActions()
  }, { deep: true })

  // Checkbox column definition for DataTable
  const getSelectionColumn = () => {
    return {
      selectionMode: 'multiple',
      headerStyle: 'width: 3rem',
      style: 'width: 3rem',
      exportable: false
    }
  }

  return {
    selectedItems,
    selectedCount,
    hasSelection,
    isIndeterminate,
    selectAll,
    toggleItemSelection,
    isItemSelected,
    toggleSelectAll,
    clearSelection,
    selectAllItems,
    updatePageActions,
    updateItems,
    getSelectionColumn
  }
}