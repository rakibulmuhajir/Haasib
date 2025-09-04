import { computed, type Ref } from 'vue'

export interface PaletteItem {
  title: string
  action: () => void
}

export interface PaletteSuggestions {
  suggestions: Ref<PaletteItem[]>
  next: () => void
  prev: () => void
}

export function usePaletteSuggestions(
  query: Ref<string>,
  items: PaletteItem[],
  selected: Ref<number>
): PaletteSuggestions {
  const suggestions = computed(() => {
    const q = query.value.toLowerCase().trim()
    return items.filter((i) => i.title.toLowerCase().includes(q))
  })

  function next() {
    const total = suggestions.value.length
    if (total === 0) return
    selected.value = (selected.value + 1) % total
  }

  function prev() {
    const total = suggestions.value.length
    if (total === 0) return
    selected.value = (selected.value - 1 + total) % total
  }

  return {
    suggestions,
    next,
    prev,
  }
}

export default usePaletteSuggestions
