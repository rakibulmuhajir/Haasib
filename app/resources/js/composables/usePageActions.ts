import { ref, onUnmounted } from 'vue'

export interface PageAction {
  key?: string
  label: string
  icon?: string // primeicons class e.g., 'pi pi-plus'
  severity?: string // PrimeVue Button severity
  outlined?: boolean
  text?: boolean
  tooltip?: string
  disabled?: boolean | (() => boolean)
  href?: string
  routeName?: string
  click?: () => void
}

const actions = ref<PageAction[]>([])

function resolveKey(a: PageAction, i: number) {
  return a.key || `${a.label}-${i}`
}

export function usePageActions() {
  function setActions(list: PageAction[]) {
    actions.value = list
  }

  function clearActions() {
    actions.value = []
  }

  // Optional auto-clear on unmount for callers
  onUnmounted(() => {
    // Do not forcibly clear; caller can opt-in by calling clearActions.
    // Leaving as is keeps actions when navigating within page shells if desired.
  })

  return { actions, setActions, clearActions, resolveKey }
}

export function resolveDisabled(a: PageAction): boolean {
  if (typeof a.disabled === 'function') return !!a.disabled()
  return !!a.disabled
}

