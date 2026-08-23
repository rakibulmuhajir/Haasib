<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Umrah\Commands\CreateTicketBooking;
use App\Modules\Umrah\Http\Requests\StoreTicketBookingRequest;
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
                ->orderBy('name')
                ->get(['id', 'name', 'customer_id']),
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

    public function show(Request $request, string $booking): Response
    {
        abort(404);
    }

    public function cancel(Request $request, string $ticket): RedirectResponse
    {
        abort(404);
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
}
