<?php

use App\Modules\Umrah\Services\TravelReportService;

require_once __DIR__.'/TicketingFixtures.php';

/**
 * The plan's own pseudocode hits `/{company}/reports/...` with `from`/`to`
 * query params and reads `report.totals.*`. Neither exists: every Umrah
 * report -- including these three -- lives at `/{company}/umrah/reports/...`
 * (see modules/Umrah/Routes/umrah.php), takes `start`/`end`
 * (TravelReportRequest::prepareForValidation()), and returns a `summary`
 * list of {label, value, type}, not a `totals` map (see
 * TravelReportService::moneySummary()). These tests follow the code that
 * exists rather than the plan's sketch of it.
 *
 * Numeric assertions call TravelReportService::build() directly, the same
 * way the existing shared-contract tests in TravelReportsTest.php do --
 * robust to summary ordering and free of HTTP/Inertia prop-path gymnastics.
 * The permission-gating assertion goes through the real HTTP route, because
 * that is the thing actually being guaranteed.
 */
it('reports ticket sales with revenue split three ways', function () {
    $f = ticketingSeveralSoldTickets();

    $report = app(TravelReportService::class)->build(
        $f->company,
        $f->manager,
        'ticket-sales',
        ['start' => '2026-09-01', 'end' => '2026-09-30', 'per_page' => 25],
    );

    expect($report['rows'])->toHaveCount(3);

    $summary = collect($report['summary'])->keyBy('label');

    expect($summary['Commission']['value'])->toBe(19_200.00)
        ->and($summary['Service fee']['value'])->toBe(4_500.00)
        ->and($summary['Discount']['value'])->toBe(6_000.00)
        ->and($summary['Net revenue']['value'])->toBe(17_700.00);
});

it('shows supplier clearing at zero as the control figure', function () {
    $f = ticketingSeveralSoldTickets();

    // The whole point of the reconciliation report: if this is not zero,
    // an invoice and its bill have come apart. Not date-filtered -- it is
    // the 2350 account's own balance across the whole company -- so no
    // start/end is passed here.
    $report = app(TravelReportService::class)->build(
        $f->company,
        $f->manager,
        'ticket-supplier-reconciliation',
        ['start' => '2026-01-01', 'end' => '2026-12-31', 'per_page' => 25],
    );

    expect($report['clearing_balance'])->toBe(0.0);
});

it('reports each cancellation and what it cost', function () {
    $f = ticketingSeveralSoldTickets();
    ticketingCancelOneOf($f, buyerBack: 80_000, supplierBack: 85_000);

    $report = app(TravelReportService::class)->build(
        $f->company,
        $f->manager,
        'ticket-cancellations',
        ['start' => '2026-09-01', 'end' => '2026-09-30', 'per_page' => 25],
    );

    expect($report['rows'])->toHaveCount(1)
        ->and($report['rows'][0]['cost'])->toBe(-5_000.00);
});

it('keeps an agent out of the ticket reports', function () {
    $f = ticketingSeveralSoldTickets();

    $this->actingAs($f->agentUser)
        ->get("/{$f->company->slug}/umrah/reports/ticket-sales?start=2026-09-01&end=2026-09-30")
        ->assertForbidden();
});

it('lets a manager reach the ticket sales report over HTTP', function () {
    $f = ticketingSeveralSoldTickets();

    $this->actingAs($f->manager)
        ->get("/{$f->company->slug}/umrah/reports/ticket-sales?start=2026-09-01&end=2026-09-30")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Umrah/Reports/Index')
            ->where('report.key', 'ticket-sales')
            ->has('report.rows', 3));
});

it('counts a cancellation credit against the supplier bill once, not twice', function () {
    $f = ticketingSeveralSoldTickets();

    // Three bookings at 91,000 supplier cost each, then one cancelled with
    // 85,000 returned. The credit is applied to that booking's bill, which
    // is how CancelTicketHandler settles it -- paid_amount becomes
    // total_amount less the remaining balance.
    ticketingCancelOneOf($f, buyerBack: 80_000, supplierBack: 85_000);

    $report = app(TravelReportService::class)->build(
        $f->company,
        $f->manager,
        'ticket-supplier-reconciliation',
        ['start' => '2026-09-01', 'end' => '2026-09-30', 'per_page' => 25],
    );

    $row = $report['rows'][0];

    // 273,000 billed, 85,000 of it settled by credit, nothing paid in cash.
    // Outstanding is the bills' own balance. Subtracting the credit from a
    // paid figure that already contained it reported 103,000 here, and went
    // negative once the credits outgrew the cash.
    expect($row['bills_raised'])->toBe(273_000.00)
        ->and($row['vendor_credits'])->toBe(85_000.00)
        ->and($row['paid'])->toBe(0.0)
        ->and($row['outstanding'])->toBe(188_000.00);
});

it('leaves the reconciliation row adding up', function () {
    $f = ticketingSeveralSoldTickets();
    ticketingCancelOneOf($f, buyerBack: 80_000, supplierBack: 85_000);

    $report = app(TravelReportService::class)->build(
        $f->company,
        $f->manager,
        'ticket-supplier-reconciliation',
        ['start' => '2026-09-01', 'end' => '2026-09-30', 'per_page' => 25],
    );

    // The report is a reconciliation, so its columns have to reconcile:
    // billed, less what credit settled, less what cash settled, is what is
    // left. Any future column that stops obeying this is the same defect.
    foreach ($report['rows'] as $row) {
        expect(round($row['bills_raised'] - $row['vendor_credits'] - $row['paid'], 2))
            ->toBe($row['outstanding']);
    }
});
