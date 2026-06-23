<?php

namespace App\Modules\Umrah\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaService;
use App\Modules\Umrah\Models\VisaVendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UmrahCoreService
{
    public function __construct(private GlPostingService $glPostingService) {}

    public function nextAgentNumber(string $companyId): string
    {
        return $this->nextNumber($companyId, Agent::query(), 'agent_number', 'AGT');
    }

    public function nextVendorNumber(string $companyId): string
    {
        return $this->nextNumber($companyId, VisaVendor::query(), 'vendor_number', 'UVN');
    }

    public function nextGroupNumber(string $companyId): string
    {
        return $this->nextNumber($companyId, VisaGroup::query(), 'group_number', 'UGR');
    }

    public function nextPaymentNumber(string $companyId): string
    {
        return $this->nextNumber($companyId, GroupPayment::query(), 'payment_number', 'UPM');
    }

    public function createGroup(string $companyId, array $data): VisaGroup
    {
        return DB::transaction(function () use ($companyId, $data) {
            $data = $this->applyServiceDefaults($companyId, $data);
            $financials = $this->calculateGroupFinancials($data, 0);

            $group = VisaGroup::create([
                'company_id' => $companyId,
                'agent_id' => $data['agent_id'],
                'vendor_id' => $data['vendor_id'] ?? null,
                'vehicle_type_id' => $data['vehicle_type_id'] ?? null,
                'visa_service_id' => $data['visa_service_id'] ?? null,
                'transport_service_id' => $data['transport_service_id'] ?? null,
                'group_number' => ($data['group_number'] ?? null) ?: $this->nextGroupNumber($companyId),
                'name' => $data['name'],
                'status' => $data['status'] ?? VisaGroup::STATUS_DRAFT,
                'travel_date' => $data['travel_date'] ?? null,
                'flight_info' => [
                    'airline' => $data['flight_airline'] ?? null,
                    'number' => $data['flight_number'] ?? null,
                    'notes' => $data['flight_notes'] ?? null,
                ],
                'hotel_info' => [
                    'makkah' => $data['hotel_makkah'] ?? null,
                    'madinah' => $data['hotel_madinah'] ?? null,
                    'notes' => $data['hotel_notes'] ?? null,
                ],
                'transport_required' => (bool) ($data['transport_required'] ?? false),
                'transport_quantity' => (int) ($data['transport_quantity'] ?? 0),
                'passenger_count' => max((int) ($data['passenger_count'] ?? 0), count($data['passengers'] ?? [])),
                'visa_sale_amount' => $financials['visa_sale_amount'],
                'transport_amount' => $financials['transport_amount'],
                'discount_amount' => $financials['discount_amount'],
                'visa_cost_amount' => $financials['visa_cost_amount'],
                'transport_cost_amount' => $financials['transport_cost_amount'],
                'total_receivable' => $financials['total_receivable'],
                'total_paid' => 0,
                'balance' => $financials['balance'],
                'profit' => $financials['profit'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach (($data['passengers'] ?? []) as $index => $passenger) {
                if (! trim((string) ($passenger['full_name'] ?? ''))) {
                    continue;
                }

                Passenger::create([
                    'company_id' => $companyId,
                    'visa_group_id' => $group->id,
                    'full_name' => $passenger['full_name'],
                    'passport_number' => $passenger['passport_number'] ?? null,
                    'nationality' => $passenger['nationality'] ?? null,
                    'visa_status' => $passenger['visa_status'] ?? Passenger::STATUS_PENDING,
                    'sort_order' => $index,
                ]);
            }

            $this->recalculateGroup($group->fresh());
            $this->postGroupSale($group->fresh());
            $this->postGroupCost($group->fresh());
            $this->recalculateAgent($group->agent_id);
            if ($group->vendor_id) {
                $this->recalculateVendor($group->vendor_id);
            }

            return $group->fresh(['agent', 'vendor', 'vehicleType', 'visaService', 'transportService']);
        });
    }

    public function addPassenger(VisaGroup $group, array $data): Passenger
    {
        return DB::transaction(function () use ($group, $data) {
            $passenger = Passenger::create([
                'company_id' => $group->company_id,
                'visa_group_id' => $group->id,
                'full_name' => $data['full_name'],
                'passport_number' => $data['passport_number'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'visa_status' => $data['visa_status'] ?? Passenger::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
                'sort_order' => (int) $group->passengers()->count(),
            ]);

            $this->recalculateGroup($group->fresh());
            $this->recalculateAgent($group->agent_id);

            return $passenger;
        });
    }

    public function addPayment(VisaGroup $group, array $data): GroupPayment
    {
        return DB::transaction(function () use ($group, $data) {
            $group = $group->fresh();
            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }

            if ($amount > ((float) $group->balance + 0.01)) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot be more than the remaining group balance.']);
            }

            $payment = GroupPayment::create([
                'company_id' => $group->company_id,
                'visa_group_id' => $group->id,
                'agent_id' => $group->agent_id,
                'account_id' => $data['account_id'] ?? null,
                'payment_number' => $data['payment_number'] ?: $this->nextPaymentNumber($group->company_id),
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->postAgentPayment($payment->fresh(['group']));
            $this->recalculateGroup($group->fresh());
            $this->recalculateAgent($group->agent_id);

            return $payment;
        });
    }

    public function recalculateGroup(VisaGroup $group): VisaGroup
    {
        $passengerCount = $group->passengers()->count();
        $paid = (float) $group->payments()->sum('amount');
        $financials = $this->calculateGroupFinancials($group->toArray(), $paid);

        $group->update([
            'passenger_count' => max($passengerCount, (int) $group->passenger_count),
            'total_receivable' => $financials['total_receivable'],
            'total_paid' => $paid,
            'balance' => $financials['balance'],
            'profit' => $financials['profit'],
        ]);

        return $group->fresh();
    }

    public function recalculateAgent(string $agentId): void
    {
        $agent = Agent::find($agentId);
        if (! $agent) {
            return;
        }

        $groups = VisaGroup::where('company_id', $agent->company_id)
            ->where('agent_id', $agent->id)
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED);

        $agent->update([
            'total_receivable' => (float) (clone $groups)->sum('total_receivable'),
            'total_paid' => (float) (clone $groups)->sum('total_paid'),
            'balance' => (float) (clone $groups)->sum('balance'),
        ]);
    }

    public function recalculateVendor(string $vendorId): void
    {
        $vendor = VisaVendor::find($vendorId);
        if (! $vendor) {
            return;
        }

        $totalCost = VisaGroup::where('company_id', $vendor->company_id)
            ->where('vendor_id', $vendor->id)
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
            ->selectRaw('SUM(visa_cost_amount + transport_cost_amount) as total_cost')
            ->value('total_cost');

        $vendor->update([
            'total_cost' => (float) $totalCost,
            'balance' => (float) $totalCost - (float) $vendor->total_paid,
        ]);
    }

    private function calculateGroupFinancials(array $data, float $paid): array
    {
        $visaSale = round((float) ($data['visa_sale_amount'] ?? 0), 2);
        $transport = round((float) ($data['transport_amount'] ?? 0), 2);
        $discount = round((float) ($data['discount_amount'] ?? 0), 2);
        $visaCost = round((float) ($data['visa_cost_amount'] ?? 0), 2);
        $transportCost = round((float) ($data['transport_cost_amount'] ?? 0), 2);
        $cost = round($visaCost + $transportCost, 2);
        $receivable = max(round($visaSale + $transport - $discount, 2), 0);

        return [
            'visa_sale_amount' => $visaSale,
            'transport_amount' => $transport,
            'discount_amount' => $discount,
            'visa_cost_amount' => $visaCost,
            'transport_cost_amount' => $transportCost,
            'total_receivable' => $receivable,
            'balance' => max(round($receivable - $paid, 2), 0),
            'profit' => round($receivable - $cost, 2),
        ];
    }

    private function applyServiceDefaults(string $companyId, array $data): array
    {
        if (! empty($data['visa_service_id'])) {
            $service = VisaService::where('company_id', $companyId)->find($data['visa_service_id']);

            if ($service) {
                $data['vendor_id'] = empty($data['vendor_id']) ? $service->vendor_id : $data['vendor_id'];
                $data['visa_sale_amount'] = $this->defaultAmount($data, 'visa_sale_amount', (float) $service->retail_amount);
                $data['visa_cost_amount'] = $this->defaultAmount($data, 'visa_cost_amount', (float) $service->cost_amount);
            }
        }

        if (! empty($data['transport_service_id'])) {
            $service = TransportService::where('company_id', $companyId)->find($data['transport_service_id']);

            if ($service) {
                $data['transport_required'] = true;
                $data['vehicle_type_id'] = empty($data['vehicle_type_id']) ? $service->vehicle_type_id : $data['vehicle_type_id'];
                $data['transport_amount'] = $this->defaultAmount($data, 'transport_amount', (float) $service->default_sale_amount);
                $data['transport_cost_amount'] = $this->defaultAmount($data, 'transport_cost_amount', (float) $service->default_cost_amount);
            }
        }

        return $data;
    }

    private function defaultAmount(array $data, string $key, float $default): float|string|int|null
    {
        return array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== ''
            ? $data[$key]
            : $default;
    }

    private function nextNumber(string $companyId, $query, string $column, string $prefix): string
    {
        $latest = $query
            ->where('company_id', $companyId)
            ->where($column, 'like', "{$prefix}-%")
            ->orderByDesc($column)
            ->value($column);

        $next = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%05d', $prefix, $next);
    }

    private function postGroupSale(VisaGroup $group): void
    {
        if ($group->sale_transaction_id || (float) $group->total_receivable <= 0) {
            return;
        }

        $company = $this->company($group->company_id);
        $arAccountId = $this->accountId($company, 'ar');
        $visaRevenueAccountId = $this->accountId($company, 'visa_revenue');
        $transportRevenueAccountId = $this->accountId($company, 'transport_revenue');

        [$netVisaRevenue, $netTransportRevenue] = $this->netRevenueAmounts($group);

        $entries = [[
            'account_id' => $arAccountId,
            'type' => 'debit',
            'amount' => (float) $group->total_receivable,
            'description' => "Agent receivable for {$group->group_number}",
        ]];

        if ($netVisaRevenue > 0) {
            $entries[] = [
                'account_id' => $visaRevenueAccountId,
                'type' => 'credit',
                'amount' => $netVisaRevenue,
                'description' => "Visa revenue for {$group->group_number}",
            ];
        }

        if ($netTransportRevenue > 0) {
            $entries[] = [
                'account_id' => $transportRevenueAccountId,
                'type' => 'credit',
                'amount' => $netTransportRevenue,
                'description' => "Transport revenue for {$group->group_number}",
            ];
        }

        $transaction = $this->glPostingService->postBalancedTransaction([
            'company_id' => $group->company_id,
            'transaction_number' => $this->transactionNumber('UVS', $group->id),
            'transaction_type' => 'umrah_group_sale',
            'date' => Carbon::today(),
            'currency' => $company->base_currency,
            'base_currency' => $company->base_currency,
            'description' => "Umrah group sale: {$group->group_number} - {$group->name}",
            'reference_type' => 'umrah.visa_groups',
            'reference_id' => $group->id,
            'metadata' => [
                'agent_id' => $group->agent_id,
                'group_number' => $group->group_number,
                'visa_sale_amount' => (float) $group->visa_sale_amount,
                'transport_amount' => (float) $group->transport_amount,
                'discount_amount' => (float) $group->discount_amount,
            ],
        ], $entries);

        $group->update(['sale_transaction_id' => $transaction->id]);
    }

    private function postGroupCost(VisaGroup $group): void
    {
        $visaCost = (float) $group->visa_cost_amount;
        $transportCost = (float) $group->transport_cost_amount;
        $totalCost = round($visaCost + $transportCost, 2);

        if ($group->cost_transaction_id || $totalCost <= 0) {
            return;
        }

        $company = $this->company($group->company_id);
        $visaCostAccountId = $this->accountId($company, 'visa_cost');
        $transportCostAccountId = $this->accountId($company, 'transport_cost');
        $apAccountId = $this->accountId($company, 'ap');

        $entries = [];

        if ($visaCost > 0) {
            $entries[] = [
                'account_id' => $visaCostAccountId,
                'type' => 'debit',
                'amount' => $visaCost,
                'description' => "Visa cost for {$group->group_number}",
            ];
        }

        if ($transportCost > 0) {
            $entries[] = [
                'account_id' => $transportCostAccountId,
                'type' => 'debit',
                'amount' => $transportCost,
                'description' => "Transport cost for {$group->group_number}",
            ];
        }

        $entries[] = [
            'account_id' => $apAccountId,
            'type' => 'credit',
            'amount' => $totalCost,
            'description' => "Vendor payable for {$group->group_number}",
        ];

        $transaction = $this->glPostingService->postBalancedTransaction([
            'company_id' => $group->company_id,
            'transaction_number' => $this->transactionNumber('UVC', $group->id),
            'transaction_type' => 'umrah_visa_cost',
            'date' => Carbon::today(),
            'currency' => $company->base_currency,
            'base_currency' => $company->base_currency,
            'description' => "Umrah visa cost: {$group->group_number} - {$group->name}",
            'reference_type' => 'umrah.visa_groups',
            'reference_id' => $group->id,
            'metadata' => [
                'agent_id' => $group->agent_id,
                'vendor_id' => $group->vendor_id,
                'group_number' => $group->group_number,
                'visa_cost_amount' => $visaCost,
                'transport_cost_amount' => $transportCost,
            ],
        ], $entries);

        $group->update(['cost_transaction_id' => $transaction->id]);
    }

    private function postAgentPayment(GroupPayment $payment): void
    {
        if ($payment->transaction_id) {
            return;
        }

        $company = $this->company($payment->company_id);
        $depositAccountId = $payment->account_id ?: $this->accountId($company, 'bank_or_cash');
        $arAccountId = $this->accountId($company, 'ar');

        $transaction = $this->glPostingService->postBalancedTransaction([
            'company_id' => $payment->company_id,
            'transaction_number' => $this->transactionNumber('UPY', $payment->id),
            'transaction_type' => 'umrah_agent_payment',
            'date' => $payment->payment_date,
            'currency' => $company->base_currency,
            'base_currency' => $company->base_currency,
            'description' => "Agent payment {$payment->payment_number}",
            'reference_type' => 'umrah.group_payments',
            'reference_id' => $payment->id,
            'metadata' => [
                'agent_id' => $payment->agent_id,
                'visa_group_id' => $payment->visa_group_id,
                'payment_number' => $payment->payment_number,
                'method' => $payment->method,
            ],
        ], [
            [
                'account_id' => $depositAccountId,
                'type' => 'debit',
                'amount' => (float) $payment->amount,
                'description' => "Payment received {$payment->payment_number}",
            ],
            [
                'account_id' => $arAccountId,
                'type' => 'credit',
                'amount' => (float) $payment->amount,
                'description' => "Reduce agent receivable {$payment->payment_number}",
            ],
        ]);

        $payment->update(['transaction_id' => $transaction->id]);
    }

    /**
     * @return array{0:float,1:float}
     */
    private function netRevenueAmounts(VisaGroup $group): array
    {
        $visa = (float) $group->visa_sale_amount;
        $transport = (float) $group->transport_amount;
        $discount = (float) $group->discount_amount;

        $netVisa = max(round($visa - min($discount, $visa), 2), 0);
        $remainingDiscount = max(round($discount - $visa, 2), 0);
        $netTransport = max(round($transport - $remainingDiscount, 2), 0);

        return [$netVisa, $netTransport];
    }

    private function accountId(Company $company, string $role): string
    {
        $query = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at');

        $accountId = match ($role) {
            'ar' => $company->ar_account_id ?: (clone $query)->where('subtype', 'accounts_receivable')->orderBy('code')->value('id'),
            'ap' => $company->ap_account_id ?: (clone $query)->where('subtype', 'accounts_payable')->orderBy('code')->value('id'),
            'visa_revenue' => (clone $query)->where('code', '4100')->value('id')
                ?: $company->income_account_id
                ?: (clone $query)->where('type', 'revenue')->orderBy('code')->value('id'),
            'transport_revenue' => (clone $query)->where('code', '4110')->value('id')
                ?: (clone $query)->where('type', 'revenue')->orderBy('code')->value('id'),
            'visa_cost' => (clone $query)->where('code', '5100')->value('id')
                ?: (clone $query)->where('type', 'cogs')->orderBy('code')->value('id')
                ?: $company->expense_account_id,
            'transport_cost' => (clone $query)->where('code', '5110')->value('id')
                ?: (clone $query)->where('type', 'cogs')->orderBy('code')->value('id')
                ?: $company->expense_account_id,
            'bank_or_cash' => $company->bank_account_id
                ?: (clone $query)->whereIn('subtype', ['bank', 'cash'])->orderByRaw("CASE WHEN subtype = 'bank' THEN 0 ELSE 1 END")->orderBy('code')->value('id'),
            default => null,
        };

        if (! $accountId) {
            throw ValidationException::withMessages([
                'accounts' => "Required accounting account is missing for {$role}. Review the Umrah company chart of accounts.",
            ]);
        }

        return $accountId;
    }

    private function company(string $companyId): Company
    {
        return Company::findOrFail($companyId);
    }

    private function transactionNumber(string $prefix, string $id): string
    {
        return $prefix . '-' . strtoupper(substr(str_replace('-', '', $id), -12));
    }
}
