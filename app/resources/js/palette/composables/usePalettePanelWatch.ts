import { watch, nextTick, type Ref } from 'vue'

interface WatchDeps {
  selectedVerb: Ref<any>
  params: Ref<Record<string, any>>
  ensureCompanyDetails: (id: any) => Promise<void>
  allRequiredFilled: Ref<boolean>
  activeFlagId: Ref<string | null>
  open: Ref<boolean>
  step: Ref<string>
}

export function usePalettePanelWatch(
  deps: WatchDeps,
  mainPanelEl: Ref<HTMLElement | null>,
  deleteConfirmInputEl: Ref<HTMLInputElement | null>
) {
  const { selectedVerb, params, ensureCompanyDetails, allRequiredFilled, activeFlagId, open, step } = deps

  watch([selectedVerb, () => params.value.company], async ([verb, company]) => {
    if (verb && (verb as any).id === 'delete' && company) {
      await ensureCompanyDetails(company as any)
      nextTick(() => deleteConfirmInputEl.value?.focus())
    }
  })

  watch([() => allRequiredFilled.value, () => activeFlagId.value, () => open.value], ([reqFilled, activeId, isOpen]) => {
    if (isOpen && step.value === 'fields' && reqFilled && !activeId) {
      nextTick(() => {
        mainPanelEl.value?.focus()
      })
    }
  })
}
