import { test } from 'node:test';
import assert from 'node:assert/strict';
import { nightsBetween, updateStayDates } from '../modules/Umrah/Resources/js/lib/stayDates.ts';

const stay = (start = '', end = '', nights = '') => ({ check_in_date: start, check_out_date: end, night_count: nights });
test('nights calculate checkout and cascade while keeping later durations', () => {
    const stays = [stay('2026-10-01', '', ''), stay('', '', '4'), stay('', '', '3')];
    updateStayDates(stays, 0, 'night_count', '5');
    assert.deepEqual(stays.map(s => [s.check_in_date, s.check_out_date]), [['2026-10-01', '2026-10-06'], ['2026-10-06', '2026-10-10'], ['2026-10-10', '2026-10-13']]);
    updateStayDates(stays, 0, 'check_out_date', '2026-10-08');
    assert.equal(stays[0].night_count, '7');
    assert.equal(stays[2].check_out_date, '2026-10-15');
});
test('nights can be entered before check-in', () => {
    const stays = [stay()];
    updateStayDates(stays, 0, 'night_count', '5');
    assert.equal(stays[0].check_out_date, '');
    updateStayDates(stays, 0, 'check_in_date', '2026-12-29');
    assert.equal(stays[0].check_out_date, '2027-01-03');
});
test('invalid zero negative and fractional nights never manufacture a date', () => {
    for (const value of ['', '0', '-1', '1.5', 'bad', '3651']) {
        const stays = [stay('2026-10-01', '2026-10-06', '5'), stay('2026-10-06', '', '')];
        updateStayDates(stays, 0, 'night_count', value);
        assert.equal(stays[0].check_out_date, '');
        assert.equal(stays[1].check_in_date, '');
    }
});
test('calendar math handles leap years and rejects reversed or invalid dates', () => {
    assert.equal(nightsBetween('2028-02-28', '2028-03-01'), '2');
    assert.equal(nightsBetween('2026-02-30', '2026-03-05'), '');
    assert.equal(nightsBetween('2026-10-02', '2026-10-01'), '');
    assert.equal(nightsBetween('2026-10-01', '2026-10-01'), '');
});
