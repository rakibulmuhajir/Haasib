/**
 * The allocation-splitting logic behind payments/Show.vue's receipt lines, extracted so
 * it can be unit tested without mounting the whole page (which pulls in LedgerDocument,
 * DropdownMenu and other Shadcn-heavy components). See commit f4811b97 -- an allocation
 * with a null invoice IS the on-account credit: money the buyer paid that is held against
 * future invoices, stored as a payment_allocations row with a null invoice_id so the
 * ledger invariant (allocations sum to the payment) still holds. It must be split out here
 * rather than counted as applied, or it reads as "Applied to invoice" and the unapplied
 * figure computes to zero.
 */

export interface AllocationInvoice {
    id: string;
    invoice_number: string;
    currency: string;
}

export interface PaymentAllocation {
    id: string;
    invoice_id: string | null;
    invoice?: AllocationInvoice | null;
    amount_allocated: number;
    base_amount_allocated: number;
}

/**
 * Allocation amounts are stored in invoice currency, while a receipt is printed in
 * payment currency. Use the base allocation when a base-currency payment settles a
 * foreign-currency invoice so the receipt still reconciles.
 */
export function allocationDisplayAmount(
    allocation: PaymentAllocation,
    paymentCurrency: string,
): number {
    return allocation.invoice?.currency && allocation.invoice.currency !== paymentCurrency
        ? Number(allocation.base_amount_allocated)
        : Number(allocation.amount_allocated);
}

/** Allocations actually applied to an invoice -- excludes the on-account credit rows. */
export function appliedAllocations(
    allocations: PaymentAllocation[],
): PaymentAllocation[] {
    return allocations.filter((allocation) => allocation.invoice);
}

export function allocatedTotal(
    allocations: PaymentAllocation[],
    paymentCurrency: string,
): number {
    return appliedAllocations(allocations).reduce(
        (sum, allocation) => sum + allocationDisplayAmount(allocation, paymentCurrency),
        0,
    );
}

/**
 * The stored null-invoice rows, plus any residual on an older payment written before the
 * remainder was recorded as a row at all.
 */
export function unappliedAmount(
    allocations: PaymentAllocation[],
    paymentAmount: number,
    paymentCurrency: string,
): number {
    return Math.max(0, Number(paymentAmount) - allocatedTotal(allocations, paymentCurrency));
}
