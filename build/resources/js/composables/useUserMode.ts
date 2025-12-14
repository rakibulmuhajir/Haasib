import { computed, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

export type UserMode = 'owner' | 'accountant'

const MODE_KEY = 'haasib_user_mode'
const MODE_COOKIE = 'haasib_user_mode'
const mode = ref<UserMode>('owner')
let listenersBound = false

const setCookie = (name: string, value: string, days = 365) => {
  if (typeof document === 'undefined') return
  const maxAge = days * 24 * 60 * 60
  document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`
}

const getCookie = (name: string): string | null => {
  if (typeof document === 'undefined') return null
  const prefix = `${name}=`
  const cookie = document.cookie.split('; ').find((row) => row.startsWith(prefix))
  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : null
}

const readPersistedMode = (): UserMode | null => {
  if (typeof window === 'undefined') return null
  const stored = window.localStorage.getItem(MODE_KEY) as UserMode | null
  if (stored === 'owner' || stored === 'accountant') return stored
  const cookieMode = getCookie(MODE_COOKIE) as UserMode | null
  return cookieMode === 'owner' || cookieMode === 'accountant' ? cookieMode : null
}

const persistMode = (value: UserMode) => {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(MODE_KEY, value)
  setCookie(MODE_COOKIE, value)
  window.dispatchEvent(new CustomEvent<UserMode>('haasib-mode-changed', { detail: value }))
}

const bindListeners = () => {
  if (listenersBound || typeof window === 'undefined') return

  window.addEventListener('storage', (event: StorageEvent) => {
    if (event.key === MODE_KEY && event.newValue) {
      const newMode = event.newValue as UserMode
      if (newMode === 'owner' || newMode === 'accountant') {
        mode.value = newMode
      }
    }
  })

  window.addEventListener('haasib-mode-changed', (event: Event) => {
    const detail = (event as CustomEvent<UserMode>).detail
    if (detail === 'owner' || detail === 'accountant') {
      mode.value = detail
    }
  })

  listenersBound = true
}

export function useUserMode() {
  const page = usePage()
  const canUseAccountantMode = computed(() => {
    const auth = (page.props.auth as any) || {}
    const role = auth.currentCompanyRole as string | null | undefined

    if (['owner', 'admin', 'accountant'].includes(String(role))) return true

    const userId = auth.user?.id as string | undefined
    if (userId?.startsWith('00000000-0000-0000-0000-')) return true

    // If the backend didn't provide a role but we do have a current company context,
    // allow the UI toggle (server-side permissions still gate actions).
    if (!role && auth.currentCompany && auth.user) return true

    return false
  })

  const isAccountantMode = computed(() => mode.value === 'accountant')

  const setMode = (value: UserMode) => {
    const target = value === 'accountant' && !canUseAccountantMode.value ? 'owner' : value
    mode.value = target
    persistMode(target)
  }

  const toggleMode = () => setMode(mode.value === 'owner' ? 'accountant' : 'owner')

  onMounted(() => {
    const persisted = readPersistedMode()
    if (persisted) {
      mode.value = canUseAccountantMode.value ? persisted : 'owner'
    }
    persistMode(mode.value)

    bindListeners()
  })

  return {
    mode,
    isAccountantMode,
    canUseAccountantMode,
    setMode,
    toggleMode,
  }
}
