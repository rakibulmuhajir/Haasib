// resources/js/palette/composables/usePaletteKeybindings.ts
import { onMounted, onUnmounted, nextTick } from 'vue'
import type { UsePalette } from './usePalette'

export function usePaletteKeybindings(palette: UsePalette) {
  const {
    open,
    step,
    q,
    isUIList,
    uiListActionMode,
    uiListActionIndex,
    uiListActionCount,
    inputEl,
    selectedIndex,
    entitySuggestions,
    verbSuggestions,
    currentChoices,
    inlineSuggestions,
    panelItems,
    showUserPicker,
    showCompanyPicker,
    showGenericPanelPicker,
    activeFlagId,
    dashParameterMatch,
    allRequiredFilled,
    filledFlags,
    getTabCompletion,
    selectEntity,
    selectVerb,
    selectChoice,
    pickUserEmail,
    pickCompanyName,
    pickGeneric,
    completeCurrentFlag,
    execute,
    cycleToLastFilledFlag,
    handleDashParameter,
    goBack,
    resetAll,
    performUIListAction,
  } = palette

  function handleKeydown(e: KeyboardEvent) {
    if (step.value === 'fields' && !activeFlagId.value && e.key === 'Enter' && dashParameterMatch.value) {
      e.preventDefault()
      handleDashParameter()
      return
    }

    const suggestionSources = [
      { condition: step.value === 'entity', list: entitySuggestions },
      { condition: step.value === 'verb', list: verbSuggestions },
      { condition: currentChoices.value.length > 0, list: currentChoices },
      { condition: showUserPicker.value || showCompanyPicker.value || showGenericPanelPicker.value, list: panelItems },
      { condition: inlineSuggestions.value.length > 0, list: inlineSuggestions },
    ]

    const activeSource = suggestionSources.find(s => s.condition)

    if (uiListActionMode.value && isUIList.value && step.value === 'fields' && !activeFlagId.value) {
      // Navigate actions with arrow keys
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
        e.preventDefault()
        uiListActionIndex.value = Math.min(uiListActionIndex.value + 1, Math.max(0, uiListActionCount.value - 1))
        return
      } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
        e.preventDefault()
        uiListActionIndex.value = Math.max(uiListActionIndex.value - 1, 0)
        return
      } else if (e.key === 'Enter') {
        e.preventDefault()
        performUIListAction()
        return
      } else if (e.key === 'Escape') {
        e.preventDefault(); e.stopPropagation()
        uiListActionMode.value = false
        return
      }
    }

    if (e.key === 'ArrowDown') {
      e.preventDefault()
      if (activeSource) {
        selectedIndex.value = Math.min(selectedIndex.value + 1, activeSource.list.value.length - 1)
      }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      if (activeSource) {
        selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
      }
    } else if (e.key === 'Enter') {
      e.preventDefault()
      if (step.value === 'entity' && entitySuggestions.value[selectedIndex.value]) selectEntity(entitySuggestions.value[selectedIndex.value])
      else if (step.value === 'verb' && verbSuggestions.value[selectedIndex.value]) selectVerb(verbSuggestions.value[selectedIndex.value])
      else if (step.value === 'fields') {
        // In UI list mode, Enter opens action mode for the highlighted item
        if (isUIList.value && !activeFlagId.value) {
          uiListActionMode.value = true
          uiListActionIndex.value = 0
          return
        }
        if (currentChoices.value.length > 0 && selectedIndex.value < currentChoices.value.length) {
          selectChoice(currentChoices.value[selectedIndex.value])
        } else if ((showUserPicker.value || showCompanyPicker.value || showGenericPanelPicker.value) && panelItems.value[selectedIndex.value]) {
          const item = panelItems.value[selectedIndex.value]
          if (showUserPicker.value) pickUserEmail(item.value)
          else if (showCompanyPicker.value) pickCompanyName(item.value)
          else pickGeneric(item.value)
        } else if (inlineSuggestions.value.length > 0) {
          selectChoice(inlineSuggestions.value[Math.min(selectedIndex.value, inlineSuggestions.value.length - 1)].value)
        }
        else if (activeFlagId.value) completeCurrentFlag()
        else if (allRequiredFilled.value) execute()
      }
    } else if (e.key === 'Tab') {
      e.preventDefault()
      if (e.shiftKey && step.value === 'fields' && filledFlags.value.length > 0) cycleToLastFilledFlag()
      else if (step.value === 'entity') q.value = getTabCompletion.value
      else if (step.value === 'fields' && activeFlagId.value) completeCurrentFlag()
    } else if (e.key === 'Escape') {
      e.preventDefault()
      e.stopPropagation()
      if (uiListActionMode.value) {
        uiListActionMode.value = false
      } else {
        goBack()
      }
    }
  }

  return { handleKeydown }
}
