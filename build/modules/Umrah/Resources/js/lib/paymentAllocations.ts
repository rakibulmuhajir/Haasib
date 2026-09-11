/** Validate before omitting empty/zero rows: invalid entries must never become credit. */
export function preparePaymentAllocations(
    groups: ReadonlyArray<{ id: string }>,
    amounts: Record<string, string | number | undefined>,
) {
    const errors: Record<string, string> = {};
    const allocations: Array<{ visa_group_id: string; base_amount: number }> = [];

    for (const group of groups) {
        const raw = String(amounts[group.id] ?? '').trim();
        if (raw === '') continue;
        const amount = Number(raw);
        if (!/^\+?(?:\d+(?:\.\d*)?|\.\d+)$/.test(raw) || !Number.isFinite(amount) || amount < 0) {
            errors[group.id] = 'Enter a valid allocation of zero or more.';
        } else if (amount > 0) {
            allocations.push({ visa_group_id: group.id, base_amount: amount });
        }
    }

    const valid = Object.keys(errors).length === 0;
    return {
        errors,
        valid,
        // Fail the entire set, never silently submit just its valid rows.
        allocations: valid ? allocations : [],
        total: valid ? allocations.reduce((sum, row) => sum + row.base_amount, 0) : 0,
    };
}
