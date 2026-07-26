<?php

use App\Modules\Umrah\Models\VisaGroup;

it('does not mark a zero-value unpaid group as paid', function () {
    $group = new VisaGroup([
        'total_receivable' => 0,
        'total_paid' => 0,
        'balance' => 0,
    ]);

    expect($group->payment_status)->toBe('unpaid');
});

it('distinguishes partial and complete group payments', function () {
    $partiallyPaid = new VisaGroup([
        'total_receivable' => 100,
        'total_paid' => 40,
        'balance' => 60,
    ]);
    $paid = new VisaGroup([
        'total_receivable' => 100,
        'total_paid' => 100,
        'balance' => 0,
    ]);

    expect($partiallyPaid->payment_status)->toBe('partially_paid')
        ->and($paid->payment_status)->toBe('paid');
});
