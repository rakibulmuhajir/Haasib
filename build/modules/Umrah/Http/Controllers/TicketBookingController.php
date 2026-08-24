<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Umrah\Commands\CancelTicket;
use App\Modules\Umrah\Commands\CreateTicketBooking;
use App\Modules\Umrah\Http\Requests\StoreTicketBookingRequest;
use App\Modules\Umrah\Http\Requests\StoreTicketCancellationRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Ticket;
use App\Modules\Umrah\Models\TicketBooking;
use App\Services\CompanyCurrencyOptions;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * The bookings register, the booking form, and eventually the
 * cancellation flow around it. `index()`, `create()` and `store()` are
 * built by this task -- `show` and `cancel` are routed here already
 * because the plan wants the route names settled now, but each is
 * filled in by Task 4 and until then simply 404s.
 */
class TicketBookingController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        $user = $request->user();

        $canViewAll = (bool) $user?->hasCompanyPermission(Permissions::UMRAH_TICKET_VIEW);
        $canViewOwn = (bool) $user?->hasCompanyPermission(Permissions::UMRAH_TICKET_OWN_VIEW);

        abort_unless($canViewAll || $canViewOwn, 403);

        // Own-view-only scopes to the agent record linked to this user, and
        // hides cost/commission entirely -- not just from the UI, from the
        // response, because a hidden field is still a leaked field.
        $ownAgentId = null;
        if (! $canViewAll) {
            $ownAgentId = Agent::where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->value('id');
        }

        $bookings = TicketBooking::where('company_id', $company->id)
            ->with(['customer:id,name', 'tickets'])
            ->when(! $canViewAll, fn ($query) => $ownAgentId
                ? $query->where('agent_id', $ownAgentId)
                : $query->whereRaw('1 = 0'))
            ->orderByDesc('booking_date')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $bookings->getCollection()->transform(fn (TicketBooking $booking) => $this->present($booking, $canViewAll, $company->base_currency));

        return Inertia::render('Umrah/Tickets/Index', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'bookings' => $bookings,
            'canCreate' => (bool) $user?->hasCompanyPermission(Permissions::UMRAH_TICKET_CREATE),
        ]);
    }

    /**
     * Everything the form needs to build a valid booking client-side:
     * customers and vendors to pick from, agents carrying their linked
     * `customer_id` so the page can derive the buyer from the agent
     * rather than let the two be chosen independently, and the
     * company's configured currencies for the supplier leg. The sale
     * leg is not offered a currency choice at all -- the invoice always
     * posts in the company's base currency, so the form never asks.
     */
    public function create(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        $user = $request->user();

        abort_unless((bool) $user?->hasCompanyPermission(Permissions::UMRAH_TICKET_CREATE), 403);

        return Inertia::render('Umrah/Tickets/Create', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'customers' => Customer::where('company_id', $company->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'agents' => Agent::where('company_id', $company->id)
                ->orderByName()
                ->get(['id', 'customer_id']),
            'vendors' => Vendor::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'currencies' => app(CompanyCurrencyOptions::class)->forCompany($company),
        ]);
    }

    /**
     * No arithmetic happens here -- every figure on the booking comes
     * back from CreateTicketBooking. The command's own invariants
     * (agent/customer match, supplier vendor active) are checked by
     * StoreTicketBookingRequest before this runs; anything that still
     * throws is an unexpected failure, not a validation shape the form
     * could have caught, so it becomes a flash error rather than a 500.
     */
    public function store(StoreTicketBookingRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        try {
            $booking = Bus::dispatch(new CreateTicketBooking(
                companyId: $company->id,
                customerId: $data['customer_id'],
                supplierVendorId: $data['supplier_vendor_id'],
                bookingDate: $data['booking_date'],
                pnr: $data['pnr'] ?? null,
                tickets: $data['tickets'],
                idempotencyKey: $data['idempotency_key'],
                agentId: $data['agent_id'] ?? null,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'Ticket booking could not be created. Check the details and try again.');
        }

        return redirect()
            ->route('umrah.tickets.show', ['company' => $company->slug, 'booking' => $booking->id])
            ->with('success', 'Ticket booking created.');
    }

    /**
     * The same own-view scoping as index(): a viewer holding only
     * UMRAH_TICKET_OWN_VIEW may open a booking made under their own
     * agent record and no other, and the payload never carries
     * supplier_cost_base/commission_base or the bill for them -- not
     * hidden client-side, absent from the response, same as the register.
     */
    public function show(Request $request, string $companySlug, string $booking): Response
    {
        $company = app(CurrentCompany::class)->get();
        $user = $request->user();

        $canViewAll = (bool) $user?->hasCompanyPermission(Permissions::UMRAH_TICKET_VIEW);
        $canViewOwn = (bool) $user?->hasCompanyPermission(Permissions::UMRAH_TICKET_OWN_VIEW);

        abort_unless($canViewAll || $canViewOwn, 403);

        $record = TicketBooking::where('company_id', $company->id)
            ->with(['customer:id,name', 'agent:id,customer_id', 'supplierVendor:id,name', 'invoice:id,invoice_number', 'bill:id,bill_number', 'tickets'])
            ->findOrFail($booking);

        if (! $canViewAll) {
            $ownAgentId = Agent::where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->value('id');

            abort_if(! $ownAgentId || $record->agent_id !== $ownAgentId, 403);
        }

        return Inertia::render('Umrah/Tickets/Show', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'booking' => $this->presentBooking($record, $canViewAll, $company->base_currency),
            'canCancel' => (bool) $user?->hasCompanyPermission(Permissions::UMRAH_TICKET_CANCEL),
        ]);
    }

    /**
     * The ticket is a route parameter, not a body field --
     * StoreTicketCancellationRequest copies it into `ticket_id` for
     * validation. No arithmetic happens here either: the cancellation's
     * cost is CancelTicketHandler's to compute, this only relays what
     * comes back. An already-cancelled ticket throws a RuntimeException
     * with a message meant to be read, not a 500 -- it is shown as-is.
     */
    public function cancel(StoreTicketCancellationRequest $request, string $companySlug, string $ticket): RedirectResponse
    {
        abort_unless((bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_TICKET_CANCEL), 403);

        $data = $request->validated();

        try {
            $cancellation = Bus::dispatch(new CancelTicket(
                ticketId: $ticket,
                cancellationDate: $data['cancellation_date'],
                buyerReturnsAmount: (float) $data['buyer_returns_amount'],
                supplierReturnsAmount: (float) $data['supplier_returns_amount'],
                reason: $data['reason'] ?? null,
                idempotencyKey: $data['idempotency_key'],
            ));
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'Ticket cancellation could not be completed. Check the details and try again.');
        }

        $baseCurrency = app(CurrentCompany::class)->get()->base_currency;

        return back()->with(
            'success',
            sprintf(
                'Ticket cancelled. Buyer returned %s, supplier returned %s.',
                $this->amountForFlash((float) $cancellation->buyer_returns_amount, $cancellation->buyer_returns_currency, $baseCurrency),
                $this->amountForFlash((float) $cancellation->supplier_returns_amount, $cancellation->supplier_returns_currency, $baseCurrency),
            )
        );
    }

    /**
     * The company's own currency is the unit every figure is read in
     * already, so naming it on each amount is noise -- only a foreign
     * amount says what it is. public.currencies.symbol is not consulted
     * because it is seeded equal to the code for every currency this
     * app currently handles; a lookup would cost a query and return the
     * same string. Worth revisiting if real symbols are ever seeded.
     */
    private function amountForFlash(float $amount, ?string $currency, string $baseCurrency): string
    {
        $formatted = number_format($amount, 2);

        return $currency === null || $currency === $baseCurrency
            ? $formatted
            : $currency.' '.$formatted;
    }

    /**
     * One booking's row for the register. `supplier_cost_base` and
     * `commission_base` are only present when the viewer holds the
     * full VIEW permission -- absent from the array entirely otherwise,
     * not just unrendered.
     */
    private function present(TicketBooking $booking, bool $withCosts, string $baseCurrency): array
    {
        $tickets = $booking->tickets;

        $amountBase = $tickets->sum(fn (Ticket $ticket) => (float) $ticket->gross_fare_base
            + (float) $ticket->taxes_base
            + (float) $ticket->service_fee_base
            - (float) $ticket->discount_base);

        $row = [
            'id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'booking_date' => $booking->booking_date?->toDateString(),
            'buyer' => $booking->customer?->name,
            'pnr' => $booking->pnr,
            'passenger_count' => $tickets->count(),
            'amount_base' => round($amountBase, 2),
            'currency' => $baseCurrency,
            'status' => $booking->status,
        ];

        if ($withCosts) {
            $row['supplier_cost_base'] = round((float) $tickets->sum('supplier_cost_base'), 2);
            $row['commission_base'] = round($tickets->sum(fn (Ticket $ticket) => $ticket->commissionBase()), 2);
        }

        return $row;
    }

    /**
     * A booking's own show payload. `supplier`, `bill` and each ticket's
     * `supplier_cost_base`/`commission_base` are only present for a
     * viewer holding the full VIEW permission -- absent entirely for an
     * own-view-only agent, the same rule present() applies to the
     * register.
     */
    private function presentBooking(TicketBooking $booking, bool $withCosts, string $baseCurrency): array
    {
        $data = [
            'id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'booking_date' => $booking->booking_date?->toDateString(),
            'pnr' => $booking->pnr,
            'status' => $booking->status,
            'buyer' => $booking->customer?->name,
            'agent' => $booking->agent?->name,
            'currency' => $baseCurrency,
            'invoice' => $booking->invoice ? [
                'id' => $booking->invoice->id,
                'invoice_number' => $booking->invoice->invoice_number,
            ] : null,
            'tickets' => $booking->tickets
                ->map(fn (Ticket $ticket) => $this->presentTicket($ticket, $withCosts, $baseCurrency))
                ->values()
                ->all(),
        ];

        if ($withCosts) {
            $data['supplier'] = $booking->supplierVendor?->name;
            $data['bill'] = $booking->bill ? [
                'id' => $booking->bill->id,
                'bill_number' => $booking->bill->bill_number,
            ] : null;
        }

        return $data;
    }

    /**
     * One ticket row on the show page. Same cost/commission omission
     * rule as presentBooking() and present().
     */
    private function presentTicket(Ticket $ticket, bool $withCosts, string $baseCurrency): array
    {
        $amountBase = (float) $ticket->gross_fare_base
            + (float) $ticket->taxes_base
            + (float) $ticket->service_fee_base
            - (float) $ticket->discount_base;

        $row = [
            'id' => $ticket->id,
            'passenger_name' => $ticket->passenger_name,
            'airline' => $ticket->airline,
            'route' => $ticket->route,
            'travel_date' => $ticket->travel_date?->toDateString(),
            'amount_base' => round($amountBase, 2),
            'currency' => $baseCurrency,
            'status' => $ticket->status,
        ];

        if ($withCosts) {
            $row['supplier_cost_base'] = round((float) $ticket->supplier_cost_base, 2);
            $row['commission_base'] = round($ticket->commissionBase(), 2);
        }

        return $row;
    }
}
