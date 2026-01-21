<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Http\Requests\StoreHandoverRequest;
use App\Modules\FuelStation\Models\AttendantHandover;
use App\Modules\FuelStation\Models\Pump;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AttendantHandoverController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $handovers = AttendantHandover::where('company_id', $company->id)
            ->with(['attendant', 'pump', 'receivedBy', 'destinationBank'])
            ->orderByDesc('handover_date')
            ->paginate(50);

        // Get pending handovers summary
        $pendingHandovers = AttendantHandover::where('company_id', $company->id)
            ->where('status', AttendantHandover::STATUS_PENDING)
            ->get();

        $summary = [
            'pending_count' => $pendingHandovers->count(),
            'pending_amount' => $pendingHandovers->sum('total_amount'),
        ];

        // Get pumps and attendants for form
        $pumps = Pump::where('company_id', $company->id)
            ->where('is_active', true)
            ->get();

        // Get users who can be attendants (users with company access)
        $attendants = User::whereHas('companies', function ($query) use ($company) {
            $query->where('companies.id', $company->id);
        })->get();

        // Get bank accounts for destination
        $bankAccounts = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->where('is_active', true)
            ->get();

        return Inertia::render('FuelStation/Handovers/Index', [
            'handovers' => $handovers,
            'summary' => $summary,
            'pumps' => $pumps,
            'attendants' => $attendants,
            'bankAccounts' => $bankAccounts,
            'shifts' => AttendantHandover::getShifts(),
        ]);
    }

    public function store(StoreHandoverRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        AttendantHandover::create([
            'company_id' => $company->id,
            'attendant_id' => $data['attendant_id'],
            'handover_date' => $data['handover_date'],
            'pump_id' => $data['pump_id'] ?? null,
            'shift' => $data['shift'],
            'cash_amount' => $data['cash_amount'] ?? 0,
            'easypaisa_amount' => $data['easypaisa_amount'] ?? 0,
            'jazzcash_amount' => $data['jazzcash_amount'] ?? 0,
            'bank_transfer_amount' => $data['bank_transfer_amount'] ?? 0,
            'card_swipe_amount' => $data['card_swipe_amount'] ?? 0,
            'parco_card_amount' => $data['parco_card_amount'] ?? 0,
            'destination_bank_id' => $data['destination_bank_id'] ?? null,
            'status' => AttendantHandover::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Handover recorded successfully.');
    }

    public function show(AttendantHandover $handover): Response
    {
        $handover->load(['attendant', 'pump', 'receivedBy', 'destinationBank', 'journalEntry']);

        return Inertia::render('FuelStation/Handovers/Show', [
            'handover' => $handover,
        ]);
    }

    public function receive(AttendantHandover $handover): RedirectResponse
    {
        if (!$handover->isPending()) {
            return redirect()->back()->with('error', 'Handover has already been received.');
        }

        $handover->markAsReceived(auth()->id());

        return redirect()->back()->with('success', 'Handover marked as received.');
    }
}
