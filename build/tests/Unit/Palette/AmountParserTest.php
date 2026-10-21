<?php

use App\Support\Palette\Commands\AmountParser;

test('reads an amount', function (string $text, float $expected) {
    expect(AmountParser::parse($text))->toBe($expected);
})->with([
    ['50000', 50000.0],
    ['50,000', 50000.0],
    ['1,234,567', 1234567.0],
    ['1,50,000', 150000.0],
    ['50k', 50000.0],
    ['50K', 50000.0],
    ['1.5k', 1500.0],
    ['1.5 lakh', 150000.0],
    ['1.5lakh', 150000.0],
    ['2 lac', 200000.0],
    ['2 Lac', 200000.0],
    ['0.5 lakh', 50000.0],
    ['250.50', 250.5],
    ['1,250.75', 1250.75],
    ['1.2345 lakh', 123450.0],
    ['1.23456 lakh', 123456.0],
    ['2 lacs', 200000.0],
    ['2lacs', 200000.0],
    ['1.5 lakhs', 150000.0],
    ['5 Lakhs', 500000.0],
]);

test('refuses what it cannot read exactly', function (string $text) {
    expect(AmountParser::parse($text))->toBeNull();
})->with([
    '', 'abc', '-50', '50.', '.5', '1e5', '0x10', '5o00', '50,00', '5,0000', '1,2,3', '12.345', '1.234 lakh0',
    '0', '0k', '50 thousand', '5 crore', '2 million', '3 m', '50kk', 'k', 'lakh', '1,000,000,000,000', '99999999999',
    '1.23456789 lakh', '50 thousands', '5 crores', '2 hundred',
]);

test('text after the amount is not an amount', function () {
    expect(AmountParser::parse('50 constructor'))->toBeNull()
        ->and(AmountParser::parse('50k meezan'))->toBeNull();
});
