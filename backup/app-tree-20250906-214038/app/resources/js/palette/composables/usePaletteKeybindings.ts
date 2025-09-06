// resources/js/palette/composables/usePaletteKeybindings.ts
import { onMounted, onUnmounted, nextTick } from 'vue'
import type { UsePalette } from './usePalette'

export function usePaletteKeybindings(palette: UsePalette) {
  const {
    open,
    step,
    q,
    showResults,
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
    editFilledFlag,
    selectFlag,
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
    // For direct action hotkeys
    highlightedItem,
    quickAssignToCompany,
    setActiveCompany,
    quickAssignUserToCompany,
    loadCompanyMembers,
    startVerb,
    selectedEntity,
    selectedVerb,
    stashParams,
    parseAndStartFromInput,
  } = palette

  function handleKeydown(e: KeyboardEvent) {
    if (step.value === 'fields' && !activeFlagId.value && e.key === 'Enter' && dashParameterMatch.value) {
      e.preventDefault()
      handleDashParameter()
      return
    }

    // Summary number hotkeys (1..9) while on fields step and not editing a flag
    if (/^[1-9]$/.test(e.key) && step.value === 'fields' && !activeFlagId.value) {
      const idx = parseInt(e.key, 10) - 1
      const verb = selectedVerb.value
      const fieldsForHotkeys = verb ? (verb.fields || []).filter((f: any) => f.id !== 'password_confirm') : []
      if (verb && fieldsForHotkeys.length && idx >= 0 && idx < fieldsForHotkeys.length) {
        e.preventDefault(); e.stopPropagation()
        const flag = fieldsForHotkeys[idx]
        if ((filledFlags.value || []).some(f => f.id === flag.id)) {
          editFilledFlag(flag.id)
        } else {
          selectFlag(flag.id)
        }
        return
      }
    }

    // 1..9 (or Ctrl+1..9) trigger next/related verbs when results panel is visible
    if (showResults.value && selectedEntity.value && step.value !== 'fields') {
      const allowSingle = String(q.value || '').trim() === ''
      const isNumKey = /^[1-9]$/.test(e.key)
      const withCtrl = e.ctrlKey && !e.metaKey && !e.altKey && isNumKey
      const direct = !e.metaKey && !e.altKey && !e.shiftKey && isNumKey && allowSingle
      if (direct || withCtrl) {
        const num = parseInt(e.key, 10)
        const verbs = (selectedEntity.value.verbs || [])
        const idx = num - 1
        if (verbs[idx]) {
          e.preventDefault()
          const eid = selectedEntity.value.id
          const vid = verbs[idx].id
          startVerb(eid, vid, (stashParams as any).value || {})
          return
        }
      }
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

    // Direct action hotkeys for highlighted items in UI lists
    if (isUIList.value && step.value === 'fields' && !activeFlagId.value) {
      const item = highlightedItem.value
      const allowSingle = String(q.value || '').trim() === ''
      if (item) {
        const key = e.key.toLowerCase()
        const withCtrl = e.ctrlKey && !e.metaKey && !e.altKey
        const direct = !e.metaKey && !e.altKey && !e.shiftKey && allowSingle
        if (direct || withCtrl) {
          const id = (item.meta && (item.meta.id || item.meta.email)) || item.value
          if (showCompanyPicker.value) {
            if (key === 'a') { e.preventDefault(); quickAssignToCompany(id); return }
            if (key === 's') { e.preventDefault(); setActiveCompany(id); return }
            if (key === 'd') { e.preventDefault(); startVerb('company', 'delete', { company: id }); return }
            if (key === 'v') { e.preventDefault(); loadCompanyMembers(id); return }
          } else if (showUserPicker.value) {
            if (key === 'a') { e.preventDefault(); quickAssignUserToCompany(id); return }
            if (key === 'x') { e.preventDefault(); startVerb('user', 'delete', { email: id }); return }
            if (key === 'e') { e.preventDefault(); startVerb('user', 'update', { email: id }); return }
          }
        }
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
      // Power: Cmd/Ctrl+Enter → parse and execute immediately if complete
      if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey) {
        if (parseAndStartFromInput({ executeIfComplete: true })) return
      }
      // Prefer freeform parse first, so power users aren't forced into suggestions
      if (parseAndStartFromInput()) return
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
