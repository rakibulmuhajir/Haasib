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
    if (!company) return
    // Always ensure company details are available when a company is selected,
    // so UI can render the company name instead of UUID for any verb.
    await ensureCompanyDetails(company as any)
    // For delete, still focus the confirm input once details load.
    if (verb && (verb as any).id === 'delete') {
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
