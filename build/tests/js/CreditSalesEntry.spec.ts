import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CreditSalesEntry from '../../modules/FuelStation/Resources/js/components/CreditSalesEntry.vue'

/**
 * Covers commit 0a8acf97: a row marked pending_fuel_invoice (imported from a standalone
 * fuel credit sale) renders read-only with no delete control, while a manually-added row
 * keeps its delete control.
 */

function mountEntry(rows: any[]) {
  return mount(CreditSalesEntry, {
    props: {
      modelValue: rows,
      errors: {},
      disabled: false,
    },
    global: {
      stubs: {
        EntitySearch: { template: '<input class="entity-search-stub" />' },
        QuickAddModal: { template: '<div />' },
        MoneyText: { template: '<span />' },
      },
    },
  })
}

describe('CreditSalesEntry pending_fuel_invoice rows', () => {
  it('renders a manual row with editable inputs and a delete control', () => {
    const wrapper = mountEntry([
      { customer_id: 'c1', customer_name: 'Ali', amount: 500, reference: 'Slip 1' },
    ])

    expect(wrapper.find('.entity-search-stub').exists()).toBe(true)
    expect(wrapper.find('input[type="number"]').exists()).toBe(true)
    expect(wrapper.find('button[aria-label="Remove credit sale"]').exists()).toBe(true)
  })

  it('renders a pending_fuel_invoice row read-only with no delete control', () => {
    const wrapper = mountEntry([
      {
        customer_id: 'c1',
        customer_name: 'Ali',
        amount: 500,
        reference: 'Fuel sale #1',
        invoice_id: 'inv-1',
        invoice_number: 'INV-1',
        pending_fuel_invoice: true,
      },
    ])

    // No editable customer search, no editable amount input, no delete button.
    expect(wrapper.find('.entity-search-stub').exists()).toBe(false)
    expect(wrapper.find('input[type="number"]').exists()).toBe(false)
    expect(wrapper.find('button[aria-label="Remove credit sale"]').exists()).toBe(false)

    // The customer name and amount still render as plain text.
    expect(wrapper.text()).toContain('Ali')
    expect(wrapper.text()).toContain('500')
    expect(wrapper.text()).toContain('INV-1')
  })

  it('renders a mix of rows independently -- only the manual row keeps its delete control', () => {
    const wrapper = mountEntry([
      { customer_id: 'c1', customer_name: 'Manual buyer', amount: 200, reference: '' },
      {
        customer_id: 'c2',
        customer_name: 'Fuel buyer',
        amount: 300,
        reference: '',
        pending_fuel_invoice: true,
      },
    ])

    const deleteButtons = wrapper.findAll('button[aria-label="Remove credit sale"]')
    expect(deleteButtons).toHaveLength(1)
  })
})
