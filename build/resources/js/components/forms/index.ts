/**
 * Form Components
 *
 * Shared form components for invoice/bill creation and other forms.
 * These components provide a consistent, mode-aware experience.
 *
 * @see docs/plans/invoice-bill-components-spec.md
 */

export { default as EntitySearch } from './EntitySearch.vue'
export type { Entity, EntitySearchProps } from './EntitySearch.vue'

export { default as AmountInput } from './AmountInput.vue'
export type { AmountInputProps } from './AmountInput.vue'

export { default as DueDatePicker } from './DueDatePicker.vue'
export type { DueDatePickerProps } from './DueDatePicker.vue'

export { default as TaxToggle } from './TaxToggle.vue'
export type { TaxCode, TaxToggleProps } from './TaxToggle.vue'

export { default as QuickAddModal } from './QuickAddModal.vue'
export type { QuickEntity, QuickAddModalProps } from './QuickAddModal.vue'
