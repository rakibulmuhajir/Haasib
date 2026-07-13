<?php

use App\Modules\Umrah\Models\Voucher;

test('voucher service catalogue exposes only supported combinations', function () {
    expect(array_keys(Voucher::SERVICE_BUNDLES))->toBe([
        'visa_transport',
        'visa_transport_hotel',
        'transport',
        'transport_hotel',
        'hotel',
    ]);
});

test('hotel billing is enabled only for bundles that include hotel', function () {
    expect(Voucher::bundleIncludesHotel(Voucher::SERVICE_VISA_TRANSPORT))->toBeFalse()
        ->and(Voucher::bundleIncludesHotel(Voucher::SERVICE_TRANSPORT))->toBeFalse()
        ->and(Voucher::bundleIncludesHotel(Voucher::SERVICE_VISA_TRANSPORT_HOTEL))->toBeTrue()
        ->and(Voucher::bundleIncludesHotel(Voucher::SERVICE_TRANSPORT_HOTEL))->toBeTrue()
        ->and(Voucher::bundleIncludesHotel(Voucher::SERVICE_HOTEL))->toBeTrue();
});
