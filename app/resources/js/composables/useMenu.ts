import { ref, onUnmounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import type { MenuItem } from '@/Components/Sidebar/SidebarMenuItem.vue'

/**
 * A reactive set containing the keys of all active menu items.
 * An item is "active" if it is the current page or an ancestor of the current page.
 */
const activeMenuKeys = ref(new Set<string>())

/**
 * A composable to manage the active state of the sidebar menu.
 * It listens to Inertia navigation events to update the active menu items.
 *
 * @param {MenuItem[]} menu - The entire menu structure.
 */
export function useMenu(menu: MenuItem[]) {
  /**
   * Generates a unique key for a menu item.
   */
  const getKey = (item: MenuItem) => item.routeName || item.path || item.label

  /**
   * Recursively finds the path of keys from the root to the active item.
   */
  const findActivePath = (items: MenuItem[], currentRouteName: string): string[] => {
    for (const item of items) {
      const key = getKey(item)
      const isActive = currentRouteName === item.routeName || currentRouteName.startsWith(`${item.routeName}.`)

      if (isActive) return [key]

      if (item.children) {
        const childPath = findActivePath(item.children, currentRouteName)
        if (childPath.length > 0) return [key, ...childPath]
      }
    }
    return []
  }

  const removeListener = router.on('navigate', (event) => {
    const currentRouteName = event.detail.page.props.ziggy?.name || ''
    const allItems = menu.flatMap(section => section.items)
    const activePath = findActivePath(allItems, currentRouteName)
    activeMenuKeys.value = new Set(activePath)
  })

  onUnmounted(removeListener)

  return { activeMenuKeys, getKey }
}
