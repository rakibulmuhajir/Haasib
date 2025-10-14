/**
 * Page Layout Helpers
 * 
 * Provides standardized helpers for common page layout patterns including:
 * - Breadcrumb generation
 * - Page header configurations  
 * - Common action presets
 * - Topbar layout patterns
 */

import { computed, h, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Select from 'primevue/select'

export interface BreadcrumbItem {
  label: string
  url?: string
  icon?: string
}

export interface PageHeaderConfig {
  title: string
  subtitle?: string
  maxActions?: number
  slots?: {
    actions?: () => any
    [key: string]: () => any
  }
}

export interface ActionConfig {
  key: string
  label: string
  icon: string
  severity: 'primary' | 'secondary' | 'success' | 'info' | 'warning' | 'danger' | 'contrast'
  click?: () => void
  disabled?: () => boolean
  outlined?: boolean
  text?: boolean
  badge?: string
}

export interface PageConfig {
  module: string
  entity: string
  action?: 'index' | 'create' | 'edit' | 'show'
  title?: string
  subtitle?: string
  searchPlaceholder?: string
  searchQuery?: ref<string>
  actions?: ActionConfig[]
  customBreadcrumbs?: BreadcrumbItem[]
}

/**
 * Generate standard breadcrumbs for a module/entity/action structure
 */
export function useModuleBreadcrumbs(config: {
  module: string
  entity: string
  action?: 'index' | 'create' | 'edit' | 'show'
  customItems?: BreadcrumbItem[]
}): BreadcrumbItem[] {
  const items: BreadcrumbItem[] = []

  // Module level
  items.push({
    label: config.module.charAt(0).toUpperCase() + config.module.slice(1),
    url: `/${config.module.toLowerCase()}`,
    icon: config.module.toLowerCase()
  })

  // Entity level
  if (config.action !== 'index' || config.entity !== config.module) {
    items.push({
      label: config.entity.charAt(0).toUpperCase() + config.entity.slice(1),
      url: `/${config.module.toLowerCase()}/${config.entity.toLowerCase()}`,
      icon: config.entity.toLowerCase()
    })
  }

  // Action level
  if (config.action && config.action !== 'index') {
    const actionLabels = {
      create: 'Create',
      edit: 'Edit',
      show: 'Details'
    }
    items.push({
      label: actionLabels[config.action],
      icon: config.action
    })
  }

  // Add custom items
  if (config.customItems) {
    items.push(...config.customItems)
  }

  return items
}

/**
 * Common action presets for standard CRUD operations
 */
export const actionPresets = {
  create: (label: string, routeName: string, options?: Partial<ActionConfig>): ActionConfig => ({
    key: 'create',
    label,
    icon: 'pi pi-plus',
    severity: 'primary',
    click: () => router.visit(route(routeName)),
    ...options
  }),

  edit: (label: string = 'Edit', routeName?: string, id?: string | number): ActionConfig => ({
    key: 'edit',
    label,
    icon: 'pi pi-pencil',
    severity: 'secondary',
    click: routeName && id ? () => router.visit(route(routeName, id)) : undefined,
    outlined: true
  }),

  delete: (label: string = 'Delete', callback?: () => void): ActionConfig => ({
    key: 'delete',
    label,
    icon: 'pi pi-trash',
    severity: 'danger',
    click: callback,
    outlined: true
  }),

  export: (label: string = 'Export', callback?: () => void): ActionConfig => ({
    key: 'export',
    label,
    icon: 'pi pi-download',
    severity: 'secondary',
    click: callback,
    outlined: true
  }),

  refresh: (label: string = 'Refresh', callback?: () => void): ActionConfig => ({
    key: 'refresh',
    label,
    icon: 'pi pi-refresh',
    severity: 'secondary',
    click: callback
  }),

  save: (label: string = 'Save', callback?: () => void): ActionConfig => ({
    key: 'save',
    label,
    icon: 'pi pi-check',
    severity: 'primary',
    click: callback
  }),

  cancel: (label: string = 'Cancel', routeOrCallback?: string | (() => void)): ActionConfig => ({
    key: 'cancel',
    label,
    icon: 'pi pi-times',
    severity: 'secondary',
    click: typeof routeOrCallback === 'string' 
      ? () => router.visit(routeOrCallback) 
      : routeOrCallback,
    outlined: true
  })
}

/**
 * Create a standardized page configuration for common page types
 */
export function usePageConfig(config: PageConfig) {
  // Generate breadcrumbs
  const breadcrumbs = computed(() => {
    if (config.customBreadcrumbs) {
      return config.customBreadcrumbs
    }
    return useModuleBreadcrumbs({
      module: config.module,
      entity: config.entity,
      action: config.action
    })
  })

  // Generate page header
  const pageHeader = computed((): PageHeaderConfig => {
    const title = config.title || (
      config.action === 'create' ? `Create ${config.entity}` :
      config.action === 'edit' ? `Edit ${config.entity}` :
      config.action === 'show' ? `${config.entity} Details` :
      config.entity.charAt(0).toUpperCase() + config.entity.slice(1)
    )

    const subtitle = config.subtitle || (
      config.action === 'index' ? `Manage your ${config.entity.toLowerCase()}` :
      config.action === 'create' ? `Add a new ${config.entity.toLowerCase().slice(0, -1)}` :
      config.action === 'edit' ? `Update ${config.entity.toLowerCase().slice(0, -1)} information` :
      `View ${config.entity.toLowerCase().slice(0, -1)} details`
    )

    const headerConfig: PageHeaderConfig = {
      title,
      subtitle,
      maxActions: 5
    }

    // Add search slot for index pages
    if (config.action === 'index' && config.searchPlaceholder && config.searchQuery) {
      headerConfig.slots = {
        actions: () => h('div', { class: 'p-input-icon-left' }, [
          h('i', { class: 'fas fa-search' }),
          h(InputText, {
            modelValue: config.searchQuery!.value,
            'onUpdate:modelValue': (v: string) => config.searchQuery!.value = v,
            placeholder: config.searchPlaceholder,
            class: 'w-64',
            onKeyup: (e: KeyboardEvent) => {
              if (e.key === 'Enter') {
                // Trigger search - parent should handle this
              }
            }
          })
        ])
      }
    }

    return headerConfig
  })

  // Standard actions for different page types
  const standardActions = computed((): ActionConfig[] => {
    const baseRoute = `${config.module.toLowerCase()}.${config.entity.toLowerCase()}`
    
    switch (config.action) {
      case 'index':
        return [
          actionPresets.create(`Create ${config.entity}`, `${baseRoute}.create`),
          ...(config.actions || [])
        ]
      
      case 'create':
      case 'edit':
        return [
          actionPresets.save(),
          actionPresets.cancel(`/${config.module.toLowerCase()}/${config.entity.toLowerCase()}`),
          ...(config.actions || [])
        ]
      
      case 'show':
        return [
          actionPresets.edit(undefined, `${baseRoute}.edit`),
          actionPresets.delete(),
          ...(config.actions || [])
        ]
      
      default:
        return config.actions || []
    }
  })

  return {
    breadcrumbs,
    pageHeader,
    standardActions
  }
}

/**
 * Helper for creating filter configurations
 */
export function useFilterConfig<T extends Record<string, any>>(initialFilters: T) {
  const filters = ref<T>(initialFilters)
  const activeFiltersCount = computed(() => {
    return Object.values(filters.value).filter(v => v !== '' && v !== null && v !== undefined).length
  })

  const hasActiveFilters = computed(() => activeFiltersCount.value > 0)

  const clearFilters = () => {
    filters.value = { ...initialFilters }
  }

  const applyFilters = (routeName: string, preserveState = true, preserveScroll = true) => {
    const params: Record<string, any> = {}
    
    Object.entries(filters.value).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        params[key] = value
      }
    })

    router.visit(route(routeName, params), {
      preserveState,
      preserveScroll
    })
  }

  return {
    filters,
    activeFiltersCount,
    hasActiveFilters,
    clearFilters,
    applyFilters
  }
}

/**
 * Standard topbar layout component
 */
export function StandardTopbar(props: {
  breadcrumbs: BreadcrumbItem[]
  actions?: any[]
}) {
  return h('div', { class: 'flex items-center justify-between w-full' }, [
    h('div', { class: 'flex-1' }, [
      // Breadcrumb component would be rendered here
      // This assumes Breadcrumb component is available globally
    ]),
    props.actions ? h('div', { class: 'flex items-center gap-2' }, props.actions) : null
  ])
}