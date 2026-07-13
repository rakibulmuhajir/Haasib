<?php

namespace App\Modules\Umrah\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\GroupTransportItem;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UmrahCoreService
{
    public function __construct(
        private GlPostingService $glPostingService,
        private TransportPricingCalculator $transportPricing,
    ) {}

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

    public function nextVoucherNumber(string $companyId): string
    {
        return $this->nextNumber($companyId, Voucher::query(), 'voucher_number', 'UVR');
    }

    public function nextHotelVendorNumber(string $companyId): string
    {
        return $this->nextNumber($companyId, HotelVendor::query(), 'vendor_number', 'HVN');
    }

    public function createGroup(string $companyId, array $data): VisaGroup
    {
        return DB::transaction(function () use ($companyId, $data) {
            $data = $this->applyServiceDefaults($companyId, $data);
            $financials = $this->calculateGroupFinancials($data, 0);
            $passengerCount = max((int) ($data['passenger_count'] ?? 0), count($data['passengers'] ?? []));
            $transportItems = $data['resolved_transport_items'] ?? [];
            $primaryTransport = $transportItems[0] ?? null;

            $group = VisaGroup::create([
                'company_id' => $companyId,
                'agent_id' => $data['agent_id'],
                'vendor_id' => $data['vendor_id'] ?? null,
                'visa_service_id' => $data['visa_service_id'] ?? null,
                'transport_service_id' => $primaryTransport['transport_service_id'] ?? ($data['transport_service_id'] ?? null),
                'driver_id' => $primaryTransport['driver_id'] ?? ($data['driver_id'] ?? null),
                'group_number' => ($data['group_number'] ?? null) ?: $this->nextGroupNumber($companyId),
                'name' => trim((string) ($data['name'] ?? '')) ?: $this->defaultGroupName($companyId, $data['agent_id'], $passengerCount),
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
                'transport_mode' => $data['transport_mode'] ?? VisaGroup::TRANSPORT_STANDARD_BUS,
                'included_bus_cost_per_passenger' => $data['included_bus_cost_per_passenger'] ?? 50,
                'included_bus_cost_deduction' => $data['included_bus_cost_deduction'] ?? 0,
                'transport_quantity' => $primaryTransport['quantity'] ?? (int) ($data['transport_quantity'] ?? 1),
                'transport_pax_capacity' => $primaryTransport['pax_capacity'] ?? ($data['transport_pax_capacity'] ?? null),
                'passenger_count' => $passengerCount,
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

            $agentCountry = Agent::where('company_id', $companyId)
                ->whereKey($group->agent_id)
                ->value('country') ?: 'Pakistan';

            foreach (($data['passengers'] ?? []) as $index => $passenger) {
                if (! trim((string) ($passenger['full_name'] ?? ''))) {
                    continue;
                }

                Passenger::create([
                    'company_id' => $companyId,
                    'visa_group_id' => $group->id,
                    'full_name' => $passenger['full_name'],
                    'passport_number' => $passenger['passport_number'] ?? null,
                    'nationality' => ! empty($passenger['nationality']) ? $passenger['nationality'] : $agentCountry,
                    'date_of_birth' => $passenger['date_of_birth'] ?? null,
                    'imported_age' => $passenger['imported_age'] ?? null,
                    'service_type' => $passenger['service_type'] ?? Passenger::SERVICE_VISA_TRANSPORT,
                    'transport_charge_amount' => round((float) ($passenger['transport_charge_amount'] ?? 0), 2),
                    'visa_status' => $passenger['visa_status'] ?? Passenger::STATUS_PENDING,
                    'sort_order' => $index,
                ]);
            }

            foreach ($transportItems as $item) {
                unset($item['pax_capacity']);
                GroupTransportItem::create([
                    ...$item,
                    'company_id' => $companyId,
                    'visa_group_id' => $group->id,
                ]);
            }

            $this->recalculateGroup($group->fresh());
            $this->postGroupSale($group->fresh());
            $this->postGroupCost($group->fresh());
            $this->recalculateAgent($group->agent_id);
            if ($group->vendor_id) {
                $this->recalculateVendor($group->vendor_id);
            }

            return $group->fresh(['agent', 'vendor', 'visaService', 'transportService', 'transportItems']);
        });
    }

    public function addPassenger(VisaGroup $group, array $data): Passenger
    {
        return DB::transaction(function () use ($group, $data) {
            $nationality = ! empty($data['nationality'])
                ? $data['nationality']
                : ($group->agent()->value('country') ?: 'Pakistan');

            $passenger = Passenger::create([
                'company_id' => $group->company_id,
                'visa_group_id' => $group->id,
                'full_name' => $data['full_name'],
                'passport_number' => $data['passport_number'] ?? null,
                'nationality' => $nationality,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'imported_age' => $data['imported_age'] ?? null,
                'service_type' => $data['service_type'] ?? Passenger::SERVICE_VISA_TRANSPORT,
                'transport_charge_amount' => round((float) ($data['transport_charge_amount'] ?? 0), 2),
                'visa_status' => $data['visa_status'] ?? Passenger::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
                'sort_order' => (int) $group->passengers()->count(),
            ]);

            if ($passenger->service_type === Passenger::SERVICE_TRANSPORT_ONLY) {
                $group->increment('transport_amount', (float) $passenger->transport_charge_amount);
            } elseif ($group->vendor_id) {
                $vendor = VisaVendor::where('company_id', $group->company_id)->find($group->vendor_id);
                if ($vendor) {
                    $pricing = $this->calculateVisaPricingFromVendor($vendor, [$passenger->toArray()], optional($group->travel_date)->toDateString(), 1);
                    $costDeduction = $group->transport_mode === VisaGroup::TRANSPORT_SPECIALIZED
                        ? min($pricing['cost'], (float) $group->included_bus_cost_per_passenger)
                        : 0;

                    $group->update([
                        'visa_sale_amount' => round((float) $group->visa_sale_amount + $pricing['sale'], 2),
                        'visa_cost_amount' => round((float) $group->visa_cost_amount + $pricing['cost'] - $costDeduction, 2),
                        'included_bus_cost_deduction' => round((float) $group->included_bus_cost_deduction + $costDeduction, 2),
                    ]);
                }
            }

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
            ->selectRaw('SUM(visa_cost_amount) as total_cost')
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
        $hotel = round((float) ($data['hotel_amount'] ?? 0), 2);
        $discount = round((float) ($data['discount_amount'] ?? 0), 2);
        $visaCost = round((float) ($data['visa_cost_amount'] ?? 0), 2);
        $transportCost = round((float) ($data['transport_cost_amount'] ?? 0), 2);
        $hotelCost = round((float) ($data['hotel_cost_amount'] ?? 0), 2);
        $cost = round($visaCost + $transportCost + $hotelCost, 2);
        $receivable = max(round($visaSale + $transport + $hotel - $discount, 2), 0);

        return [
            'visa_sale_amount' => $visaSale,
            'transport_amount' => $transport,
            'hotel_amount' => $hotel,
            'discount_amount' => $discount,
            'visa_cost_amount' => $visaCost,
            'transport_cost_amount' => $transportCost,
            'hotel_cost_amount' => $hotelCost,
            'total_receivable' => $receivable,
            'balance' => max(round($receivable - $paid, 2), 0),
            'profit' => round($receivable - $cost, 2),
        ];
    }

    public function applyVoucherHotelAccounting(Voucher $voucher, VisaGroup $group): void
    {
        if ($voucher->status !== Voucher::STATUS_APPROVED || $voucher->hotel_sale_transaction_id || $voucher->hotel_cost_transaction_id) return;

        $group->update([
            'hotel_amount' => round((float) $group->hotel_amount + (float) $voucher->hotel_sale_amount, 2),
            'hotel_cost_amount' => round((float) $group->hotel_cost_amount + (float) $voucher->hotel_cost_amount, 2),
        ]);
        $this->recalculateGroup($group->fresh());
        $this->recalculateAgent($group->agent_id);

        collect($voucher->hotel_stays)
            ->filter(fn (array $stay) => ! empty($stay['hotel_vendor_id']) && (float) ($stay['total_cost_amount'] ?? 0) > 0)
            ->groupBy('hotel_vendor_id')
            ->each(function ($stays, string $vendorId) use ($group) {
                $vendor = HotelVendor::where('company_id', $group->company_id)->find($vendorId);
                if (! $vendor) return;
                $addedCost = (float) $stays->sum(fn (array $stay) => (float) $stay['total_cost_amount']);
                $vendor->update(['total_cost' => round((float) $vendor->total_cost + $addedCost, 2), 'balance' => round((float) $vendor->balance + $addedCost, 2)]);
            });

        $company = $this->company($group->company_id);
        if ((float) $voucher->hotel_sale_amount > 0) {
            $transaction = $this->glPostingService->postBalancedTransaction([
                'company_id' => $group->company_id, 'transaction_number' => $this->transactionNumber('UHS', $voucher->id),
                'transaction_type' => 'umrah_hotel_sale', 'date' => Carbon::today(), 'currency' => $company->base_currency, 'base_currency' => $company->base_currency,
                'description' => "Hotel sale: {$voucher->voucher_number}", 'reference_type' => 'umrah.vouchers', 'reference_id' => $voucher->id,
                'metadata' => ['agent_id' => $group->agent_id, 'visa_group_id' => $group->id, 'voucher_number' => $voucher->voucher_number],
            ], [
                ['account_id' => $this->accountId($company, 'ar'), 'type' => 'debit', 'amount' => (float) $voucher->hotel_sale_amount, 'description' => "Hotel receivable {$voucher->voucher_number}"],
                ['account_id' => $this->accountId($company, 'hotel_revenue'), 'type' => 'credit', 'amount' => (float) $voucher->hotel_sale_amount, 'description' => "Hotel revenue {$voucher->voucher_number}"],
            ]);
            $voucher->update(['hotel_sale_transaction_id' => $transaction->id]);
        }
        if ((float) $voucher->hotel_cost_amount > 0) {
            $transaction = $this->glPostingService->postBalancedTransaction([
                'company_id' => $group->company_id, 'transaction_number' => $this->transactionNumber('UHC', $voucher->id),
                'transaction_type' => 'umrah_hotel_cost', 'date' => Carbon::today(), 'currency' => $company->base_currency, 'base_currency' => $company->base_currency,
                'description' => "Hotel cost: {$voucher->voucher_number}", 'reference_type' => 'umrah.vouchers', 'reference_id' => $voucher->id,
                'metadata' => ['agent_id' => $group->agent_id, 'visa_group_id' => $group->id, 'voucher_number' => $voucher->voucher_number],
            ], [
                ['account_id' => $this->accountId($company, 'hotel_cost'), 'type' => 'debit', 'amount' => (float) $voucher->hotel_cost_amount, 'description' => "Hotel cost {$voucher->voucher_number}"],
                ['account_id' => $this->accountId($company, 'ap'), 'type' => 'credit', 'amount' => (float) $voucher->hotel_cost_amount, 'description' => "Hotel payable {$voucher->voucher_number}"],
            ]);
            $voucher->update(['hotel_cost_transaction_id' => $transaction->id]);
        }
    }

    private function applyServiceDefaults(string $companyId, array $data): array
    {
        $data['transport_required'] = true;
        $data['transport_mode'] = $data['transport_mode'] ?? VisaGroup::TRANSPORT_STANDARD_BUS;
        $data['resolved_transport_items'] = [];
        $data['transport_amount'] = $this->transportOnlyPassengerCharges($data['passengers'] ?? []);
        $data['transport_cost_amount'] = 0;
        $data['included_bus_cost_deduction'] = 0;

        if (! empty($data['vendor_id'])) {
            $vendor = VisaVendor::where('company_id', $companyId)->find($data['vendor_id']);

            if ($vendor) {
                $pricing = $this->calculateVisaPricingFromVendor($vendor, $data['passengers'] ?? [], $data['travel_date'] ?? null, (int) ($data['passenger_count'] ?? 0));
                $data['visa_sale_amount'] = $pricing['sale'];
                $data['visa_cost_amount'] = $pricing['cost'];
                $data['included_bus_cost_per_passenger'] = (float) $vendor->included_bus_cost_amount;

                if ($data['transport_mode'] === VisaGroup::TRANSPORT_SPECIALIZED) {
                    $replacement = $this->transportPricing->replaceIncludedBusCost(
                        $pricing['cost'],
                        (float) $vendor->included_bus_cost_amount,
                        $pricing['passenger_count'],
                        true,
                    );
                    $data['included_bus_cost_deduction'] = $replacement['deduction'];
                    $data['visa_cost_amount'] = $replacement['adjusted_visa_cost'];
                }
            }
        }

        if ($data['transport_mode'] === VisaGroup::TRANSPORT_SPECIALIZED) {
            $items = $this->resolveTransportItems($companyId, $data['transport_items'] ?? [], $data['passengers'] ?? [], (int) ($data['passenger_count'] ?? 0));
            $data['resolved_transport_items'] = $items;
            $data['transport_amount'] = round((float) $data['transport_amount'] + array_sum(array_column($items, 'total_sale_amount')), 2);
            $data['transport_cost_amount'] = round(array_sum(array_column($items, 'total_cost_amount')), 2);
        }

        return $data;
    }

    private function transportOnlyPassengerCharges(array $passengers): float
    {
        return round((float) collect($passengers)
            ->filter(fn (array $passenger) => trim((string) ($passenger['full_name'] ?? '')) !== '')
            ->filter(fn (array $passenger) => ($passenger['service_type'] ?? Passenger::SERVICE_VISA_TRANSPORT) === Passenger::SERVICE_TRANSPORT_ONLY)
            ->sum(fn (array $passenger) => (float) ($passenger['transport_charge_amount'] ?? 0)), 2);
    }

    private function resolveTransportItems(string $companyId, array $items, array $passengers, int $fallbackPassengerCount): array
    {
        $namedPassengerCount = count(array_filter($passengers, fn (array $passenger) => trim((string) ($passenger['full_name'] ?? '')) !== ''));
        $groupPassengerCount = max($namedPassengerCount, $fallbackPassengerCount);
        $resolved = [];

        foreach ($items as $item) {
            $fare = TransportFare::where('company_id', $companyId)
                ->where('is_active', true)
                ->with(['service', 'sector', 'package'])
                ->find($item['transport_fare_id']);

            if (! $fare || ! $fare->service) {
                throw ValidationException::withMessages(['transport_items' => 'A selected transport fare is no longer available.']);
            }

            $quantity = max((int) ($item['quantity'] ?? 1), 1);
            $passengerCount = max((int) ($item['passenger_count'] ?? $groupPassengerCount), 1);
            $isHajjTerminal = ($item['terminal'] ?? 'standard') === 'hajj';
            $totals = $this->transportPricing->fareTotals($fare, $quantity, $passengerCount, $isHajjTerminal);

            $resolved[] = [
                'transport_fare_id' => $fare->id,
                'transport_service_id' => $fare->transport_service_id,
                'transport_sector_id' => $fare->transport_sector_id,
                'transport_package_id' => $fare->transport_package_id,
                'driver_id' => $item['driver_id'] ?? $fare->service->driver_id,
                'description' => $fare->name,
                'scheduled_at' => $item['scheduled_at'] ?? null,
                'terminal' => $isHajjTerminal ? 'hajj' : 'standard',
                'charging_basis' => $fare->charging_basis,
                'quantity' => $quantity,
                'passenger_count' => $passengerCount,
                'unit_sale_amount' => (float) $fare->sale_amount,
                'unit_cost_amount' => (float) $fare->cost_amount,
                'surcharge_sale_amount' => $totals['surcharge_sale_amount'],
                'surcharge_cost_amount' => $totals['surcharge_cost_amount'],
                'total_sale_amount' => $totals['total_sale_amount'],
                'total_cost_amount' => $totals['total_cost_amount'],
                'notes' => $item['notes'] ?? null,
                'pax_capacity' => $fare->service->pax_capacity,
            ];
        }

        return $resolved;
    }

    private function calculateVisaPricingFromVendor(VisaVendor $vendor, array $passengers, ?string $travelDate, int $passengerCount): array
    {
        $sale = 0.0;
        $cost = 0.0;
        $pricedPassengers = 0;
        $namedPassengers = 0;

        foreach ($passengers as $passenger) {
            if (! trim((string) ($passenger['full_name'] ?? ''))) {
                continue;
            }

            $namedPassengers++;

            if (($passenger['service_type'] ?? Passenger::SERVICE_VISA_TRANSPORT) === Passenger::SERVICE_TRANSPORT_ONLY) {
                continue;
            }

            $band = $this->ageBand($passenger['date_of_birth'] ?? null, $travelDate, $passenger['imported_age'] ?? null);
            $sale += (float) $vendor->getAttribute("{$band}_retail_amount");
            $cost += (float) $vendor->getAttribute("{$band}_cost_amount");
            $pricedPassengers++;
        }

        if ($namedPassengers === 0) {
            $pricedPassengers = max($passengerCount, 0);
            $sale = (float) $vendor->adult_retail_amount * $pricedPassengers;
            $cost = (float) $vendor->adult_cost_amount * $pricedPassengers;
        }

        return [
            'sale' => round($sale, 2),
            'cost' => round($cost, 2),
            'passenger_count' => $pricedPassengers,
        ];
    }

    private function ageBand(?string $dateOfBirth, ?string $travelDate, mixed $importedAge = null): string
    {
        if (empty($dateOfBirth)) {
            $age = is_numeric($importedAge) ? (int) $importedAge : null;

            return $age !== null && $age < 12 ? 'child' : 'adult';
        }

        $birthDate = Carbon::parse($dateOfBirth)->startOfDay();
        $referenceDate = empty($travelDate) ? now()->startOfDay() : Carbon::parse($travelDate)->startOfDay();
        $age = $birthDate->diffInYears($referenceDate);

        if ($age < 12) {
            return 'child';
        }

        return 'adult';
    }

    private function defaultGroupName(string $companyId, string $agentId, int $passengerCount): string
    {
        $agentName = Agent::where('company_id', $companyId)
            ->whereKey($agentId)
            ->value('name') ?: 'Umrah Group';

        return sprintf('%s - %d pax - %s', $agentName, $passengerCount, now()->format('Ymd His'));
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
            'hotel_revenue' => (clone $query)->where('code', '4120')->value('id') ?: (clone $query)->where('type', 'revenue')->orderBy('code')->value('id'),
            'visa_cost' => (clone $query)->where('code', '5100')->value('id')
                ?: (clone $query)->where('type', 'cogs')->orderBy('code')->value('id')
                ?: $company->expense_account_id,
            'transport_cost' => (clone $query)->where('code', '5110')->value('id')
                ?: (clone $query)->where('type', 'cogs')->orderBy('code')->value('id')
                ?: $company->expense_account_id,
            'hotel_cost' => (clone $query)->where('code', '5120')->value('id') ?: (clone $query)->where('type', 'cogs')->orderBy('code')->value('id') ?: $company->expense_account_id,
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
