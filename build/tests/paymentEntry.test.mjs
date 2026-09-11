import { test } from 'node:test';
import assert from 'node:assert/strict';
import { preparePaymentAllocations } from '../modules/Umrah/Resources/js/lib/paymentAllocations.ts';
import { localDateInput } from '../resources/js/lib/datetime.ts';

const groups = [{ id: 'a' }, { id: 'b' }];

for (const value of ['-1', '-0.01', 'bad', '1x', 'NaN', 'Infinity', '1e309', '0x10', '1,000', '.', Number.NaN, Number.POSITIVE_INFINITY]) {
    test(`rejects invalid allocation ${String(value)} without submitting valid siblings`, () => {
        const result = preparePaymentAllocations(groups, { a: value, b: '5' });
        assert.equal(result.valid, false);
        assert.ok(result.errors.a);
        assert.deepEqual(result.allocations, []);
        assert.equal(result.total, 0);
    });
}

test('blank and zero rows intentionally leave payment unallocated', () => {
    for (const value of ['', '  ', undefined, '0', '0.00', 0]) {
        const result = preparePaymentAllocations(groups, { a: value });
        assert.equal(result.valid, true);
        assert.deepEqual(result.allocations, []);
        assert.equal(result.total, 0);
    }
});

test('positive allocations retain group identity and amounts', () => {
    const result = preparePaymentAllocations(groups, { a: ' 10.50 ', b: '.25' });
    assert.equal(result.valid, true);
    assert.deepEqual(result.allocations, [
        { visa_group_id: 'a', base_amount: 10.5 },
        { visa_group_id: 'b', base_amount: 0.25 },
    ]);
    assert.equal(result.total, 10.75);
});

test('correcting input or selecting another party removes stale errors', () => {
    assert.equal(preparePaymentAllocations(groups, { a: '-1' }).valid, false);
    assert.equal(preparePaymentAllocations(groups, { a: '1' }).valid, true);
    assert.equal(preparePaymentAllocations([{ id: 'b' }], { a: '-1' }).valid, true);
});

test('payment date uses local calendar across UTC midnight and year boundaries', () => {
    const originalTimezone = process.env.TZ;
    try {
        process.env.TZ = 'Asia/Karachi';
        assert.equal(localDateInput(new Date('2026-09-10T19:30:00Z')), '2026-09-11');
        assert.equal(localDateInput(new Date('2026-12-31T19:30:00Z')), '2027-01-01');
        process.env.TZ = 'America/Los_Angeles';
        assert.equal(localDateInput(new Date('2026-09-11T02:30:00Z')), '2026-09-10');
        assert.equal(localDateInput(new Date('2027-01-01T02:30:00Z')), '2026-12-31');
    } finally {
        if (originalTimezone === undefined) delete process.env.TZ;
        else process.env.TZ = originalTimezone;
    }
});
