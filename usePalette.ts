import { usePaletteState } from './usePaletteState'
import {
  usePaletteSuggestions,
  type PaletteItem,
} from './usePaletteSuggestions'
import { usePaletteExecutor } from './usePaletteExecutor'

export function usePalette(items: PaletteItem[]) {
  const state = usePaletteState()
  const suggestionsApi = usePaletteSuggestions(state.query, items, state.selected)
  const executor = usePaletteExecutor(suggestionsApi.suggestions, state.selected)

  return {
    ...state,
    ...suggestionsApi,
    ...executor,
  }
}

export type { PaletteItem }
export default usePalette
