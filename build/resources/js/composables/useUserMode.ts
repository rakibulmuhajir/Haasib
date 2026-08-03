import { computed, readonly, ref } from 'vue'

export type UserMode = 'owner' | 'accountant'

const mode = ref<UserMode>('owner')

export function useUserMode() {
  const canUseAccountantMode = computed(() => false)
  const isAccountantMode = computed(() => false)
  const setMode = (_value: UserMode) => undefined
  const toggleMode = () => undefined

  return {
    mode: readonly(mode),
    isAccountantMode,
    canUseAccountantMode,
    setMode,
    toggleMode,
  }
}
