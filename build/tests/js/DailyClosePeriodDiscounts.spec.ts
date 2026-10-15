import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DailyCloseDaySheet from '../../modules/FuelStation/Resources/js/components/DailyCloseDaySheet.vue'

const discount = { id: 'credit-1', number: 'CN-1', customer_name: 'Suthra Punjab', from: '2026-09-01', to: '2026-09-30', amount: 100, currency: 'PKR' }
function sheet(inSnapshot: boolean, discounts = [discount]) {
  return mount(DailyCloseDaySheet, {
    props: {
      companySlug: 'station', currency: 'PKR', creditRows: [], periodDiscounts: discounts,
      metadata: { total_revenue: 1000, form_input: { notes: 'Register includes a non-cash discount in receipts.' }, posting_snapshot: {
        totals: { opening_cash: 500, money_in: 1000, money_out: 0, expected_closing: 1500, closing_cash: 1500, variance: 0, total_revenue: inSnapshot ? 900 : 1000 },
        sources: inSnapshot ? { credit: { id: 'journal-1', type: 'credit_note', source_type: 'acct.credit_notes', source_id: 'credit-1', sales: -100, money_in: 0, money_out: 0 } } : {},
      } },
    },
    global: { stubs: {
      Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
      MoneyText: { props: ['amount'], template: '<span>{{ amount }}</span>' },
      Hint: { template: '<span><slot /></span>' },
    } },
  })
}

describe('non-cash discounts on the daily close', () => {
  it('labels a frozen credit once as a sales adjustment without altering the cash sections', () => {
    const wrapper = sheet(true)
    const sales = wrapper.findAll('section').find((section) => section.find('h3').text() === 'Fuel sales')!
    expect(sales.text()).toContain('Period discount · non-cash')
    expect(sales.text()).toContain('Suthra Punjab')
    expect(sales.find('a').attributes('href')).toBe('/station/credit-notes/credit-1')
    expect(wrapper.text()).not.toContain('Period discounts recorded after closing')
    for (const title of ['Money in', 'Money out']) {
      const section = wrapper.findAll('section').find((section) => section.find('h3').text() === title)!
      expect(section.text()).not.toContain('Suthra Punjab')
      expect(section.text()).not.toContain('discount')
    }
    expect(wrapper.findAll('section')[0].text()).toContain('Expected1500')
    expect(wrapper.text()).toContain('Register includes a non-cash discount in receipts.')
  })

  it('shows a later discount separately without rewriting frozen sales or cash', () => {
    const wrapper = sheet(false)
    expect(wrapper.text()).toContain('Period discounts recorded after closing · non-cash')
    expect(wrapper.text()).toContain('No cash received or paid')
    const sales = wrapper.findAll('section').find((section) => section.find('h3').text() === 'Fuel sales')!
    expect(sales.text()).not.toContain('Suthra Punjab')
    expect(sales.text()).toContain('Total sales1000')
    expect(wrapper.findAll('section')[0].text()).toContain('Expected1500')
    expect(sheet(false, []).text()).not.toContain('Period discounts')
  })
})
