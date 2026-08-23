<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Ticket;
use App\Modules\Umrah\Models\TicketBooking;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The bookings register and, eventually, the form and cancellation
 * flows around it. `index()` is the only method this task builds --
 * `create`, `store`, `show` and `cancel` are routed here already
 * because the plan wants the route names settled now, but each is
 * filled in by a later task and until then simply 404s.
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

    public function create(Request $request): Response
    {
        abort(404);
    }

    public function store(Request $request): RedirectResponse
    {
        abort(404);
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
