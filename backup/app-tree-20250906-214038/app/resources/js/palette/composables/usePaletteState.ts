import { ref, type Ref } from 'vue'

export interface PaletteState {
  /** Whether the palette is currently shown */
  isOpen: Ref<boolean>
  /** User's current search query */
  query: Ref<string>
  /** Index of the highlighted suggestion */
  selected: Ref<number>
  /** Show the palette */
  open: () => void
  /** Hide the palette */
  close: () => void
}

export function usePaletteState(): PaletteState {
  const isOpen = ref(false)
  const query = ref('')
  const selected = ref(0)

  function open() {
    isOpen.value = true
  }

  function close() {
    isOpen.value = false
    selected.value = 0
    query.value = ''
  }

  return {
    isOpen,
    query,
    selected,
    open,
    close,
  }
}

export default usePaletteState
