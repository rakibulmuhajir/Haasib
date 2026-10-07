import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick, reactive } from 'vue'
import { useValueTrail, valueTrailBatchKey } from '../../resources/js/composables/useValueTrail'
import type { ValueTrail, ValueTrailNode } from '../../resources/js/types/valueTrail'
import ValueTrailTrigger from '../../resources/js/components/ValueTrailTrigger.vue'
import ValueTrailPanel from '../../resources/js/components/ValueTrailPanel.vue'
import Hint from '../../resources/js/components/Hint.vue'

const state = vi.hoisted(() => ({
    page: null as unknown,
    reload: vi.fn(),
    listeners: new Map<string, (event: { preventDefault: () => void }) => void>(),
    showError: vi.fn(),
}))
vi.mock('../../resources/js/composables/useFormFeedback', () => ({ useFormFeedback: () => ({ showError: state.showError }) }))
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => state.page,
    router: {
        reload: state.reload,
        on: (name: string, handler: (event: { preventDefault: () => void }) => void) => {
            state.listeners.set(name, handler)
            return () => state.listeners.delete(name)
        },
    },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}))
vi.mock('../../resources/js/components/MoneyText.vue', () => ({
    default: { props: ['amount'], template: '<span>{{ amount }}</span>' },
}))

const stubs = {
    Button: { template: '<button><slot /></button>' },
    TooltipProvider: { template: '<div><slot /></div>' },
    Tooltip: { template: '<div><slot /></div>' },
    TooltipTrigger: { template: '<div><slot /></div>' },
    TooltipContent: { template: '<div><slot /></div>' },
    Sheet: { template: '<div><slot /></div>' },
    SheetContent: { template: '<div><slot /></div>' },
    SheetHeader: { template: '<div><slot /></div>' },
    SheetTitle: { template: '<h2><slot /></h2>' },
    SheetDescription: { template: '<p><slot /></p>' },
}

beforeEach(() => {
    state.reload.mockClear()
    state.listeners.clear()
    state.showError.mockClear()
    state.page = reactive({ props: { auth: { preferences: { show_value_trails: true } } } })
})

describe('account-wide value trail visibility', () => {
    it('keeps contextual hints compatible and makes trail hints open the module provider', async () => {
        const plain = mount(Hint, { props: { preview: 'A contextual explanation' }, slots: { default: '500' }, global: { stubs } })
        await plain.get('button').trigger('click')
        expect(plain.text()).toContain('A contextual explanation')
        expect(plain.emitted('explore')).toBeUndefined()
        expect(plain.get('button').attributes('aria-haspopup')).toBeUndefined()
        expect(plain.find('.explanation-trigger__label').text()).toBe('500')
        plain.unmount()
        const { wrapper, controller } = mountModule()
        await wrapper.get('button').trigger('click')
        expect(controller.root.value).toBe('balance:customer')
        expect(controller.open.value).toBe(true)
        expect(controller.loading.value).toBe(true)
        expect(state.reload).toHaveBeenCalledOnce()
        wrapper.unmount()
    })
    it('opens a trail by clicking its figure and removes the control when disabled', async () => {
        const wrapper = mount(ValueTrailTrigger, { slots: { default: '20,000' }, global: { stubs } })
        await wrapper.get('button').trigger('click')
        expect(wrapper.emitted('explore')).toHaveLength(1)
        const page = state.page as { props: { auth: { preferences: { show_value_trails: boolean } } } }
        page.props.auth.preferences.show_value_trails = false
        await wrapper.vm.$nextTick()
        expect(wrapper.find('button').exists()).toBe(false)
        expect(wrapper.text()).toBe('20,000')
    })

    it('removes existing hints and their hidden detail when the preference is off', () => {
        const page = state.page as { props: { auth: { preferences: { show_value_trails: boolean } } } }
        page.props.auth.preferences.show_value_trails = false
        const wrapper = mount(Hint, { slots: { default: '500', content: 'Calculation detail' }, global: { stubs } })
        expect(wrapper.find('button').exists()).toBe(false)
        expect(wrapper.text()).toBe('500')
    })
})

function mountModule() {
    const scope = reactive({ period: 'September', value: 20 })
    let controller!: ReturnType<typeof useValueTrail>
    const wrapper = mount(defineComponent({
        setup() {
            controller = useValueTrail({
                refresh: ['balance'],
                context: () => scope.period,
                snapshot: () => scope.value,
                snapshotFromPage: (props) => props.balance,
            })
            return () => h(Hint, { trail: 'balance:customer', preview: 'Invoices less payments' }, () => '20')
        },
    }), { global: { stubs } })
    return { wrapper, controller, scope }
}

describe('core value trail request lifecycle', () => {
    it('merges branch batches, keeps cached detail and requests the next offset', () => {
        const { wrapper, controller } = mountModule()
        controller.explore('total:gross_profit')
        const data = trail()
        const first = { ...data, batch: { parent: 'profit', offset: 0, version: 'v1' }, nodes: {
            profit: { ...data.nodes.profit, children: ['sales'], children_loaded: 1, children_total: 2 },
            sales: { ...data.nodes.sales, children: [], summary: true },
        } }
        const visit = state.reload.mock.calls[0][0]
        expect(visit.headers['X-Value-Trail-Node']).toBe('total:gross_profit')
        visit.onSuccess({ props: { balance: 20, valueTrail: first } })
        visit.onFinish()
        controller.load('profit', 1)
        const more = state.reload.mock.calls[1][0]
        expect(more.headers['X-Value-Trail-Offset']).toBe('1')
        expect(more.headers['X-Value-Trail-Version']).toBe('v1')
        more.onSuccess({ props: { balance: 20, valueTrail: { ...first, batch: { parent: 'profit', offset: 1, version: 'v1' }, nodes: {
            profit: { ...data.nodes.profit, children: ['cost'], children_loaded: 2, children_total: 2 }, cost: data.nodes.cost,
        } } } })
        more.onFinish()
        expect(controller.trail.value?.nodes.profit.children).toEqual(['sales', 'cost'])
        expect(controller.trail.value?.nodes.sales.summary).toBe(true)
        wrapper.unmount()
    })
    it('loads only on demand, reuses evidence, and invalidates it when displayed values change', async () => {
        const { wrapper, controller, scope } = mountModule()
        expect(state.reload).not.toHaveBeenCalled()
        controller.explore('balance:customer')
        const visit = state.reload.mock.calls[0][0]
        expect(visit.only).toEqual(['valueTrail', 'balance', 'auth'])
        visit.onSuccess({ props: { balance: 20, valueTrail: trail() } })
        visit.onFinish()
        controller.explore('invoice:total')
        expect(state.reload).toHaveBeenCalledOnce()
        scope.value = 30
        await nextTick()
        expect(controller.trail.value).toBeNull()
        expect(controller.open.value).toBe(false)
        wrapper.unmount()
    })

    it('cancels when filters change and ignores a response from the earlier context', async () => {
        const { wrapper, controller, scope } = mountModule()
        controller.explore('balance:customer')
        const visit = state.reload.mock.calls[0][0]
        const cancel = vi.fn()
        visit.onCancelToken({ cancel })
        scope.period = 'October'
        await nextTick()
        expect(cancel).toHaveBeenCalledOnce()
        visit.onSuccess({ props: { balance: 20, valueTrail: trail() } })
        expect(controller.trail.value).toBeNull()
        wrapper.unmount()
    })

    it('supports retry after server and network errors and cleans up its listeners', () => {
        const { wrapper, controller } = mountModule()
        controller.explore('balance:customer')
        const visit = state.reload.mock.calls[0][0]
        visit.onSuccess({ props: { valueTrail: { error: 'Evidence unavailable' } } })
        visit.onFinish()
        expect(controller.error.value).toBe('Evidence unavailable')
        expect(state.showError).toHaveBeenCalledWith('Evidence unavailable')
        controller.load()
        const preventDefault = vi.fn()
        state.listeners.get('exception')!({ preventDefault })
        expect(preventDefault).toHaveBeenCalledOnce()
        expect(controller.loading.value).toBe(false)
        expect(controller.error.value).toContain('Could not load')
        wrapper.unmount()
        expect(state.listeners.size).toBe(0)
    })

    it('removes evidence and refuses requests when the user disables explanations', async () => {
        const { wrapper, controller } = mountModule()
        controller.explore('balance:customer')
        const cancel = vi.fn()
        state.reload.mock.calls[0][0].onCancelToken({ cancel })
        const page = state.page as { props: { auth: { preferences: { show_value_trails: boolean } } } }
        page.props.auth.preferences.show_value_trails = false
        await nextTick()
        expect(cancel).toHaveBeenCalledOnce()
        expect(controller.open.value).toBe(false)
        controller.explore('invoice:total')
        expect(state.reload).toHaveBeenCalledOnce()
        expect(wrapper.find('button').exists()).toBe(false)
        wrapper.unmount()
    })
})

function node(id: string, label: string, value: number, children: string[] = []): ValueTrailNode {
    return { id, label, value, unit: 'money', explanation: 'Recorded calculation', formula: null, children, source: null, estimated: false }
}

function trail(): ValueTrail {
    return {
        roots: { 'total:gross_profit': 'profit' },
        context: { start_date: '2026-09-01', end_date: '2026-09-30', product: 'petrol', group_by: 'day' },
        nodes: {
            profit: { ...node('profit', 'Gross profit', 20000, ['sales', 'cost']), formula: 'Sales − cost of sales' },
            sales: node('sales', 'Sales', 100000, ['invoice']),
            cost: { ...node('cost', 'Cost of sales', 80000), estimated: true },
            invoice: { ...node('invoice', 'Invoice sales', 100000), source: { label: 'INV-1042', date: '2026-09-16', href: '/station/invoices/1042' } },
        },
    }
}

describe('value trail navigation', () => {
    it('loads summary detail without resetting breadcrumbs and fetches more children', async () => {
        const data = trail()
        data.batch = { parent: 'profit', offset: 0, version: 'v1' }
        data.nodes.sales = { ...data.nodes.sales, children: [], summary: true }
        const load = vi.fn()
        const wrapper = mount(ValueTrailPanel, { props: { open: true, loading: false, trail: data, root: 'total:gross_profit', currency: 'PKR' },
            global: { stubs, provide: { [valueTrailBatchKey as symbol]: { load } } } })
        await wrapper.findAll('button').find((button) => button.text() === 'Sales100000')!.trigger('click')
        expect(load).toHaveBeenCalledWith('sales', 0)
        const detail = { ...data, nodes: { ...data.nodes, sales: { ...node('sales', 'Sales', 100000, ['invoice']), children_loaded: 40, children_total: 80 } } }
        await wrapper.setProps({ trail: detail })
        expect(wrapper.get('h3').text()).toBe('Sales')
        await wrapper.findAll('button').find((button) => button.text().includes('Show more'))!.trigger('click')
        expect(load).toHaveBeenLastCalledWith('sales', 40)
    })
    it('accepts another module’s context without requiring Fuel filters', () => {
        const data = trail()
        data.context = { label: 'Customer balance · INV-1042', customer_id: 'customer-1' }
        const wrapper = mount(ValueTrailPanel, { props: { open: true, loading: false, trail: data, root: 'total:gross_profit', currency: 'PKR' }, global: { stubs } })
        expect(wrapper.text()).toContain('Customer balance · INV-1042')
    })
    it('follows contributing values to their original record and returns with breadcrumbs', async () => {
        const wrapper = mount(ValueTrailPanel, { props: { open: true, loading: false, trail: trail(), root: 'total:gross_profit', currency: 'PKR' }, global: { stubs } })
        expect(wrapper.text()).toContain('Sales − cost of sales')
        await wrapper.findAll('button').find((button) => button.text().includes('Sales'))!.trigger('click')
        expect(wrapper.get('h3').text()).toBe('Sales')
        await wrapper.findAll('button').find((button) => button.text().includes('Invoice sales'))!.trigger('click')
        expect(wrapper.get('a').attributes('href')).toBe('/station/invoices/1042')
        await wrapper.findAll('button').find((button) => button.text().includes('Gross profit'))!.trigger('click')
        expect(wrapper.get('h3').text()).toBe('Gross profit')
    })

    it('shows loading, recoverable errors, and restricted sources without a record link', async () => {
        const wrapper = mount(ValueTrailPanel, { props: { open: true, loading: true, trail: null, root: 'total:gross_profit', currency: 'PKR' }, global: { stubs } })
        expect(wrapper.get('[role="status"]').text()).toContain('Loading')
        await wrapper.setProps({ loading: false, error: 'Could not load' })
        expect(wrapper.get('[role="alert"]').text()).toContain('Could not load')
        await wrapper.get('button').trigger('click')
        expect(wrapper.emitted('retry')).toHaveLength(1)
        const data = trail()
        data.nodes.invoice.source = { restricted: true }
        data.roots['total:gross_profit'] = 'invoice'
        await wrapper.setProps({ error: '', trail: data })
        expect(wrapper.text()).toContain('You do not have permission')
        expect(wrapper.find('a').exists()).toBe(false)
    })

    it('labels estimates and resets the trail when report data changes', async () => {
        const data = trail()
        data.roots['total:gross_profit'] = 'cost'
        const wrapper = mount(ValueTrailPanel, { props: { open: true, loading: false, trail: data, root: 'total:gross_profit', currency: 'PKR' }, global: { stubs } })
        expect(wrapper.text()).toContain('Estimated')
        await wrapper.setProps({ trail: trail() })
        expect(wrapper.get('h3').text()).toBe('Gross profit')
    })
})
