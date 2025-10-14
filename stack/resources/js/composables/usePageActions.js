import { ref, reactive, readonly } from 'vue'

// Global state for page actions
const actions = ref([])
const actionsByKey = reactive(new Map())

// Helper to resolve disabled state (function or boolean)
export function resolveDisabled(action) {
  if (typeof action.disabled === 'function') {
    try {
      return action.disabled()
    } catch (error) {
      console.error('usePageActions: Error resolving disabled state for action', action.label, error)
      return false
    }
  }
  return Boolean(action.disabled)
}

export function usePageActions() {
  const setActions = (newActions) => {
    if (!Array.isArray(newActions)) {
      console.warn('usePageActions: setActions expects an array')
      return
    }
    
    // Clear existing actions
    actions.value = []
    actionsByKey.clear()
    
    // Add new actions
    newActions.forEach(action => {
      // Ensure each action has the required properties
      if (!action.label) {
        console.warn('usePageActions: Action missing label property', action)
        return
      }
      
      // Generate a unique key if not provided
      if (!action.key) {
        action.key = `${action.label}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
      }
      
      // Set default values
      const actionWithDefaults = {
        key: action.key,
        label: action.label,
        icon: action.icon || null,
        severity: action.severity || 'secondary',
        outlined: action.outlined === true,
        text: action.text === true,
        disabled: action.disabled === true,
        tooltip: action.tooltip || '',
        href: action.href || null,
        routeName: action.routeName || null,
        click: typeof action.click === 'function' ? action.click : null,
        permission: action.permission || null,
        ...action
      }
      
      actions.value.push(actionWithDefaults)
      actionsByKey.set(actionWithDefaults.key, actionWithDefaults)
    })
  }

  const addAction = (action) => {
    if (!action.label) {
      console.warn('usePageActions: Action missing label property', action)
      return
    }
    
    // Generate a unique key if not provided
    if (!action.key) {
      action.key = `${action.label}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
    }
    
    const actionWithDefaults = {
      key: action.key,
      label: action.label,
      icon: action.icon || null,
      severity: action.severity || 'secondary',
      outlined: action.outlined === true,
      text: action.text === true,
      disabled: action.disabled === true,
      tooltip: action.tooltip || '',
      href: action.href || null,
      routeName: action.routeName || null,
      click: typeof action.click === 'function' ? action.click : null,
      permission: action.permission || null,
      ...action
    }
    
    actions.value.push(actionWithDefaults)
    actionsByKey.set(actionWithDefaults.key, actionWithDefaults)
  }

  const removeAction = (keyOrAction) => {
    const key = typeof keyOrAction === 'string' ? keyOrAction : keyOrAction.key
    const index = actions.value.findIndex(action => action.key === key)
    
    if (index !== -1) {
      actions.value.splice(index, 1)
      actionsByKey.delete(key)
    }
  }

  const updateAction = (keyOrAction, updates) => {
    const key = typeof keyOrAction === 'string' ? keyOrAction : keyOrAction.key
    const action = actionsByKey.get(key)
    
    if (action) {
      Object.assign(action, updates)
      
      // Update the array as well
      const index = actions.value.findIndex(a => a.key === key)
      if (index !== -1) {
        actions.value[index] = { ...action }
      }
    }
  }

  const clearActions = () => {
    actions.value = []
    actionsByKey.clear()
  }

  const getAction = (key) => {
    return actionsByKey.get(key)
  }

  const hasAction = (key) => {
    return actionsByKey.has(key)
  }

  
  // Helper to filter actions by permission
  const getActionsByPermission = (permission) => {
    return actions.value.filter(action => {
      if (!action.permission) return true
      if (Array.isArray(action.permission)) {
        return action.permission.includes(permission)
      }
      return action.permission === permission
    })
  }

  return {
    actions: readonly(actions),
    setActions,
    addAction,
    removeAction,
    updateAction,
    clearActions,
    getAction,
    hasAction,
    getActionsByPermission
  }
}