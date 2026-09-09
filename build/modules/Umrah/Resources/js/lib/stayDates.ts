export type StayDates = { check_in_date: string; check_out_date: string; night_count: string };

function day(value: string): number | null {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
    const parsed = Date.parse(`${value}T00:00:00Z`);
    return Number.isFinite(parsed) && new Date(parsed).toISOString().slice(0, 10) === value ? parsed : null;
}

export function nightsBetween(start: string, end: string): string {
    const a = day(start), b = day(end);
    return a !== null && b !== null && b > a ? String((b - a) / 86400000) : '';
}

function checkout(start: string, nights: string): string {
    const date = day(start), count = Number(nights);
    if (date === null || !Number.isInteger(count) || count < 1 || count > 3650) return '';
    return new Date(date + count * 86400000).toISOString().slice(0, 10);
}

export function updateStayDates(stays: StayDates[], index: number, field: keyof StayDates, value: string): void {
    const stay = stays[index];
    if (!stay) return;
    stay[field] = value;
    if (field === 'check_out_date') {
        stay.night_count = nightsBetween(stay.check_in_date, value);
    } else if (field === 'night_count' || stay.night_count) {
        stay.check_out_date = checkout(stay.check_in_date, stay.night_count);
    } else {
        stay.night_count = nightsBetween(stay.check_in_date, stay.check_out_date);
    }
    // Keep the length of subsequent stays while moving their dates forward.
    for (let i = index + 1; i < stays.length; i++) {
        const next = stays[i];
        next.night_count ||= nightsBetween(next.check_in_date, next.check_out_date);
        next.check_in_date = stays[i - 1].check_out_date;
        next.check_out_date = checkout(next.check_in_date, next.night_count);
    }
}
