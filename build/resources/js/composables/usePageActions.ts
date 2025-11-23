import { ref } from 'vue'

export interface PageAction {
  key: string
  label: string
  icon?: string
  severity?: string
  routeName?: string
  action?: () => void
  show?: boolean
}

const actions = ref<PageAction[]>([])

export function usePageActions() {
  const setActions = (newActions: PageAction[]) => {
    actions.value = newActions
  }

  const addAction = (action: PageAction) => {
    actions.value.push(action)
  }

  const removeAction = (key: string) => {
    const index = actions.value.findIndex(action => action.key === key)
    if (index !== -1) {
      actions.value.splice(index, 1)
    }
  }

  const clearActions = () => {
    actions.value = []
  }

  const getAction = (key: string) => {
    return actions.value.find(action => action.key === key)
  }

  const getVisibleActions = () => {
    return actions.value.filter(action => action.show !== false)
  }

  return {
    actions,
    setActions,
    addAction,
    removeAction,
    clearActions,
    getAction,
    getVisibleActions
  }
}