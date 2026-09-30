<?php

use App\Modules\Accounting\Services\ConsolidatedInvoiceService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../FuelStation/CreditCloseFixtures.php';

/**
 * A consolidated invoice is a record of what was sent: the picked lines are saved as they were,
 * the next one shows those invoices as already sent, and the saved document cannot be changed.
 */
test('a consolidated invoice saves the picked lines, marks them sent, and cannot be changed', function () {
    $f = creditCloseFixture();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    creditClosePost($f); // the close's credit sale becomes an invoice for the customer

    $service = app(ConsolidatedInvoiceService::class);
    $rows = $service->rowsFor($f['company']->id, $f['customer']->id, '2026-09-01', '2026-09-30');
    expect($rows)->not->toBeEmpty()
        ->and($rows[0]['sent_in'])->toBeNull()
        ->and($rows[0]['item'])->toBe('') // the fixture's credit row names no fuel, so none is shown
        ->and($rows[0]['reference'])->toBe('Slip 42'); // ...and its reference on the invoice

    $id = $service->create($f['company'], [
        'customer_id' => $f['customer']->id, 'from' => '2026-09-01', 'to' => '2026-09-30', 'title' => 'Reminder',
        'keys' => [$rows[0]['key']],
        'references' => [$rows[0]['key'] => 'Slip 42'],
        'bill_to' => ['name' => '', 'attention' => 'Accounts office', 'phone' => ''],
        'billed_by' => ['name' => 'Tariq', 'designation' => 'Manager', 'phone' => '0300'],
    ], $f['user']->id);

    $doc = $service->document($f['company'], $id);
    expect($doc['number'])->toBe('CI-00001')
        ->and($doc['title'])->toBe('Reminder')
        ->and($doc['bill_to']['name'])->toBe($f['customer']->name) // blank name falls back to the customer
        ->and($doc['lines'][0]['reference'])->toBe('Slip 42')
        ->and($doc['total'])->toBe((float) $rows[0]['amount']);

    // Next time, the same invoice says where it was sent.
    $again = $service->rowsFor($f['company']->id, $f['customer']->id, '2026-09-01', '2026-09-30');
    expect($again[0]['sent_in']['number'])->toBe('CI-00001');

    // What was sent stays as it was sent.
    expect(fn () => DB::table('acct.consolidated_invoices')->where('id', $id)->update(['title' => 'Changed']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
