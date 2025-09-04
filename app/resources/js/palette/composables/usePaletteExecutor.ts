import type { Ref } from 'vue'
import type { PaletteItem } from './usePaletteSuggestions'

export interface PaletteExecutor {
  execute: () => void
}

export function usePaletteExecutor(
  suggestions: Ref<PaletteItem[]>,
  selected: Ref<number>
): PaletteExecutor {
  function execute() {
    const item = suggestions.value[selected.value]
    if (item) item.action()
  }

  return { execute }
}

export default usePaletteExecutor
