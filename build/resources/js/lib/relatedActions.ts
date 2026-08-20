import type { Component } from 'vue'
import {
    Boxes,
    FileMinus,
    FilePlus2,
    Package,
    Receipt,
    RotateCcw,
    Truck,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-vue-next'

/**
 * What else you might need, right where you'd need it.
 *
 * Halfway through writing an invoice you discover the customer isn't on file,
 * or the thing you're billing for has never been set up as a product. Today
 * that means abandoning the form, hunting for the right page in the header,
 * creating the record, and finding your way back to start over. The work was
 * never hard; the navigation was.
 *
 * These definitions name, per screen, the small set of adjacent things a person
 * plausibly needs from that screen. Two rules keep the list short enough to be
 * read:
 *
 *   1. Only what is adjacent to the *current* task. "Related" is not a second
 *      navigation menu -- if it belongs in the header, it does not belong here.
 *   2. Only what is possible right now. An invoice that is already settled does
 *      not offer to record a payment; a draft does not offer to credit itself.
 *      An action that would fail is worse than an action that is absent.
 *
 * An action either navigates (`href`) or hands the decision back to the page
 * (`event`). The event form exists because the best answer to "I need a
 * customer" is usually a modal on the page you are already on -- QuickAddModal
 * already does this -- and a component cannot know which modals its host owns.
 */
export interface RelatedAction {
    /** Stable identity, used as the render key and the emitted event name. */
    key: string
    /** The action, named as the thing that happens. Sentence case, a verb. */
    title: string
    /** One short clause on why you would want it. Omit rather than pad. */
    hint?: string
    icon?: Component
    /** Where it goes. Mutually exclusive with `event`. */
    href?: string
    /** Handled by the host page instead of navigating. */
    event?: boolean
    /**
     * Company roles allowed to see it. Omitted means everyone who can see the
     * screen. This mirrors nav gating rather than duplicating server policy --
     * the server still authorises the action itself.
     */
    roles?: string[]
}

export interface RelatedActionsContext {
    /** Company slug; every href is company-scoped. */
    slug: string
    /** The viewer's role in this company, from `auth.currentCompanyRole`. */
    role?: string | null
    /** Whatever facts the screen's definition reads. */
    subject?: Record<string, any>
}

type Definition = (
    context: RelatedActionsContext,
) => Array<RelatedAction | false | null | undefined>

const s = (context: RelatedActionsContext) => `/${context.slug}`

/**
 * Screens that have adjacent work worth offering. A screen with no entry here
 * renders nothing, which is the correct amount of chrome for a screen where
 * nothing else is nearby.
 */
export const relatedActionDefinitions: Record<string, Definition> = {
    'invoice.create': (context) => [
        {
            key: 'customer.create',
            title: 'New customer',
            hint: 'Bill someone not yet on file',
            icon: UserPlus,
            event: true,
        },
        {
            key: 'item.create',
            title: 'New product',
            hint: 'Add something you sell',
            icon: Package,
            href: `${s(context)}/items/create`,
        },
    ],

    'invoice.show': (context) => {
        const invoice = context.subject ?? {}
        const balance = Number(invoice.balance ?? 0)
        const status = String(invoice.status ?? '')
        const settled =
            balance <= 0 || ['paid', 'void', 'cancelled', 'draft'].includes(status)

        return [
            !settled && {
                key: 'payment.record',
                title: 'Record payment',
                hint: 'Money received against this invoice',
                icon: Wallet,
                href: `${s(context)}/payments/create?invoice_id=${invoice.id}&customer_id=${invoice.customer_id ?? ''}`,
            },
            status !== 'draft' &&
                status !== 'void' && {
                    key: 'credit-note.create',
                    title: 'Issue credit note',
                    hint: 'Reduce what is owed',
                    icon: FileMinus,
                    href: `${s(context)}/credit-notes/create?invoice_id=${invoice.id}`,
                },
            invoice.customer_id && {
                key: 'invoice.again',
                title: 'Another invoice',
                hint: 'Same customer',
                icon: FilePlus2,
                href: `${s(context)}/invoices/create?customer_id=${invoice.customer_id}`,
            },
        ]
    },

    /* Standing in front of a receipt, the two things you plausibly want are
       the customer it came from and the next document for them. Editing the
       payment is not here -- that is the page's own action, not an adjacent
       one. */
    'payment.show': (context) => {
        const payment = context.subject ?? {}
        const customerId = payment.customer?.id ?? payment.customer_id

        return [
            customerId && {
                key: 'customer.view',
                title: 'View customer',
                hint: 'Everything else they owe',
                icon: Users,
                href: `${s(context)}/customers/${customerId}`,
            },
            customerId && {
                key: 'invoice.create',
                title: 'New invoice',
                hint: 'Bill the same customer',
                icon: FilePlus2,
                href: `${s(context)}/invoices/create?customer_id=${customerId}`,
            },
        ]
    },

    'bill.create': (context) => [
        {
            key: 'vendor.create',
            title: 'New vendor',
            hint: 'Buy from someone not yet on file',
            icon: Truck,
            event: true,
        },
        {
            key: 'item.create',
            title: 'New product',
            hint: 'Add something you buy',
            icon: Package,
            href: `${s(context)}/items/create`,
        },
    ],

    'bill.show': (context) => {
        const bill = context.subject ?? {}
        const balance = Number(bill.balance ?? 0)
        const status = String(bill.status ?? '')

        return [
            balance > 0 &&
                !['void', 'cancelled', 'draft'].includes(status) && {
                    key: 'bill.pay',
                    title: 'Pay this bill',
                    hint: 'Money out against it',
                    icon: Wallet,
                    href: `${s(context)}/bill-payments/create?bill_id=${bill.id}`,
                    roles: ['owner', 'manager', 'accountant', 'super_admin'],
                },
            bill.vendor_id && {
                key: 'vendor-credit.create',
                title: 'Record vendor credit',
                hint: 'Goods returned or overcharged',
                icon: RotateCcw,
                href: `${s(context)}/vendor-credits/create?vendor_id=${bill.vendor_id}`,
            },
        ]
    },

    'customer.show': (context) => {
        const customer = context.subject ?? {}
        return [
            {
                key: 'invoice.create',
                title: 'New invoice',
                hint: 'Bill this customer',
                icon: FilePlus2,
                href: `${s(context)}/invoices/create?customer_id=${customer.id}`,
            },
            {
                key: 'payment.record',
                title: 'Record payment',
                hint: 'Money received from them',
                icon: Wallet,
                href: `${s(context)}/payments/create?customer_id=${customer.id}`,
            },
        ]
    },

    'vendor.show': (context) => {
        const vendor = context.subject ?? {}
        return [
            {
                key: 'bill.create',
                title: 'New bill',
                hint: 'Record what they invoiced you',
                icon: Receipt,
                href: `${s(context)}/bills/create?vendor_id=${vendor.id}`,
            },
        ]
    },

    /* Navigations rather than events: the payment form has no quick-add modal
       of its own, and an action that opens nothing is worse than a link. */
    'payment.create': (context) => [
        {
            key: 'customer.create',
            title: 'New customer',
            hint: 'Payer not yet on file',
            icon: UserPlus,
            href: `${s(context)}/customers/create`,
        },
        {
            key: 'customers.index',
            title: 'All customers',
            icon: Users,
            href: `${s(context)}/customers`,
        },
    ],

    'item.create': (context) => [
        {
            key: 'items.index',
            title: 'All products',
            icon: Boxes,
            href: `${s(context)}/items`,
        },
    ],
}

/**
 * Resolve one screen's actions: drop the ones its own conditions ruled out,
 * then the ones this role may not take.
 */
export function resolveRelatedActions(
    screen: string,
    context: RelatedActionsContext,
): RelatedAction[] {
    const definition = relatedActionDefinitions[screen]
    if (!definition) return []

    const role = String(context.role ?? '')

    return definition(context)
        .filter((action): action is RelatedAction => Boolean(action))
        .filter((action) => !action.roles || action.roles.includes(role))
}
