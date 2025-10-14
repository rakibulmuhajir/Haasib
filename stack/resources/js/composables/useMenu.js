import { ref, reactive, readonly, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Global menu state
const activeMenuKeys = reactive(new Set())
const menuItems = ref([])

export function useMenu(initialItems = []) {
  const page = usePage()
  
  // Set menu items
  if (Array.isArray(initialItems) && initialItems.length > 0) {
    menuItems.value = initialItems
  }

  // Generate a unique key for a menu item
  const getKey = (item) => {
    if (item.key) return item.key
    
    // Use routeName or path as key
    const baseKey = item.routeName || item.path || item.label
    
    // Sanitize key to be valid for Map/Set
    return baseKey.replace(/[^a-zA-Z0-9-_]/g, '-').toLowerCase()
  }

  // Check if a menu item is active based on current route
  const isItemActive = (item) => {
    const currentPath = page.props.url || window.location.pathname
    const currentRoute = page.props.route?.name || ''
    
    // Exact match for route name
    if (item.routeName && currentRoute === item.routeName) {
      return true
    }
    
    // Path-based matching
    if (item.path) {
      // Exact path match
      if (currentPath === item.path) {
        return true
      }
      
      // Parent path match (current path starts with item path)
      if (currentPath.startsWith(item.path) && item.path !== '/') {
        return true
      }
    }
    
    return false
  }

  // Update active menu items based on current route
  const updateActiveItems = () => {
    activeMenuKeys.clear()
    
    // Find active items recursively
    const findActiveItems = (items, parentKey = '') => {
      items.forEach(item => {
        const itemKey = getKey(item)
        const fullKey = parentKey ? `${parentKey}.${itemKey}` : itemKey
        
        if (isItemActive(item)) {
          activeMenuKeys.add(fullKey)
          
          // Also mark parent as active if this is a child item
          if (parentKey) {
            activeMenuKeys.add(parentKey)
          }
        }
        
        // Check children recursively
        if (item.children && item.children.length > 0) {
          findActiveItems(item.children, fullKey)
        }
      })
    }
    
    findActiveItems(menuItems.value)
  }

  // Initialize active items
  updateActiveItems()

  // Watch for page changes to update active menu items
  watch(() => page.props.url, updateActiveItems, { immediate: true })
  watch(() => page.props.route?.name, updateActiveItems, { immediate: true })

  // Public methods
  const setMenuItems = (items) => {
    if (Array.isArray(items)) {
      menuItems.value = items
      updateActiveItems()
    }
  }

  const addMenuItem = (item, parentKey = null) => {
    if (parentKey) {
      // Add as child of existing item
      const parent = findMenuItemByKey(parentKey)
      if (parent) {
        if (!parent.children) {
          parent.children = []
        }
        parent.children.push(item)
      }
    } else {
      // Add to root level
      menuItems.value.push(item)
    }
    updateActiveItems()
  }

  const removeMenuItem = (key) => {
    const removeFromArray = (items) => {
      const index = items.findIndex(item => getKey(item) === key)
      if (index !== -1) {
        items.splice(index, 1)
        return true
      }
      
      // Search in children
      for (const item of items) {
        if (item.children && removeFromArray(item.children)) {
          return true
        }
      }
      
      return false
    }
    
    removeFromArray(menuItems.value)
    updateActiveItems()
  }

  const findMenuItemByKey = (key) => {
    const searchItems = (items) => {
      for (const item of items) {
        if (getKey(item) === key) {
          return item
        }
        
        if (item.children) {
          const found = searchItems(item.children)
          if (found) return found
        }
      }
      return null
    }
    
    return searchItems(menuItems.value)
  }

  const isMenuActive = (item) => {
    const key = getKey(item)
    return activeMenuKeys.has(key)
  }

  const toggleMenuItem = (item) => {
    const key = getKey(item)
    
    if (activeMenuKeys.has(key)) {
      activeMenuKeys.delete(key)
    } else {
      activeMenuKeys.add(key)
    }
  }

  const expandMenuItem = (item) => {
    const key = getKey(item)
    activeMenuKeys.add(key)
  }

  const collapseMenuItem = (item) => {
    const key = getKey(item)
    activeMenuKeys.delete(key)
  }

  return {
    // State
    menuItems: readonly(menuItems),
    activeMenuKeys: readonly(activeMenuKeys),
    
    // Methods
    getKey,
    setMenuItems,
    addMenuItem,
    removeMenuItem,
    findMenuItemByKey,
    isMenuActive,
    toggleMenuItem,
    expandMenuItem,
    collapseMenuItem,
    updateActiveItems
  }
}