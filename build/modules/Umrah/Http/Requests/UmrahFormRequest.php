<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\VisaGroup;
use App\Services\CompanyContextService;
use Closure;

abstract class UmrahFormRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->validateRlsContext()
            && $this->hasCompanyPermission($this->permission());
    }

    abstract protected function permission(): string;

    /**
     * A group must sell a visa, transport, or both. Shared by Store and
     * Update so the "neither" case is rejected the same way regardless of
     * which request built the transport_mode rule.
     *
     * $storedValue is the group's existing includes_visa on an edit, where
     * the field is optional. Without it an absent field reads as false and
     * an ordinary visa group being switched to self-arranged transport would
     * be rejected as selling nothing.
     */
    protected function transportSellsSomethingRule(?bool $storedValue = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($storedValue): void {
            $includesVisa = $this->has('includes_visa')
                ? $this->boolean('includes_visa')
                : ($storedValue ?? true);

            if (! $includesVisa && $value === VisaGroup::TRANSPORT_NONE) {
                $fail('A group must sell a visa, transport, or both.');
            }
        };
    }

    /**
     * The operator chooses once, for the group. Both passenger columns are
     * written from that choice rather than read from the payload, so a
     * hand-built request cannot put a passenger at odds with the group it
     * belongs to -- the mismatch that once billed self-arranged groups for a
     * bus they never bought.
     *
     * This runs in prepareForValidation() rather than passedValidation()
     * because validated() reads the validator's own copy of the data. A
     * merge after validation reaches the request and nothing else, so the
     * derived values would never arrive at the controller.
     */
    protected function deriveGroupServiceFields(): void
    {
        $serviceType = $this->boolean('includes_visa')
            ? Passenger::SERVICE_VISA_TRANSPORT
            : Passenger::SERVICE_TRANSPORT_ONLY;

        $passengers = collect($this->input('passengers', []))
            ->map(fn ($passenger) => [
                ...(is_array($passenger) ? $passenger : []),
                'service_type' => $serviceType,
                'transport_charge_amount' => 0,
            ])
            ->all();

        $this->merge([
            'passengers' => $passengers,
            'transport_items' => $this->withSeatedVehicleCounts($this->input('transport_items', [])),
        ]);
    }

    /**
     * Seats booked must cover the passengers riding. A shortfall raises the
     * vehicle count rather than refusing the group -- the operator's intent
     * is plain, and the arithmetic is not theirs to redo. It never lowers a
     * count: an operator who deliberately booked spare vehicles keeps them.
     * A fare whose service has no pax_capacity states no capacity at all, so
     * nothing is checked -- inventing one would price vehicles against a guess.
     */
    protected function withSeatedVehicleCounts(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $capacities = $this->capacitiesByFareId(array_column($items, 'transport_fare_id'));

        return collect($items)->map(function ($item) use ($capacities) {
            if (! is_array($item)) {
                return $item;
            }

            $capacity = $capacities[$item['transport_fare_id'] ?? ''] ?? null;

            if (! $capacity) {
                return $item;
            }

            $needed = (int) ceil(max((int) ($item['passenger_count'] ?? 0), 0) / $capacity);

            $item['quantity'] = max((int) ($item['quantity'] ?? 1), $needed, 1);

            return $item;
        })->all();
    }

    /**
     * One query for every fare on the payload rather than one per item --
     * the item count is operator-entered and can run to a page of vehicles.
     *
     * Non-UUID ids are dropped before the query. This runs before validation
     * has judged transport_fare_id, and Postgres raises rather than returns
     * empty when a uuid column is compared against a string that is not one
     * -- which would turn a bad payload into a 500 instead of a 422.
     *
     * @return array<string, int|null>
     */
    protected function capacitiesByFareId(array $fareIds): array
    {
        $fareIds = array_values(array_filter(
            array_unique($fareIds),
            fn ($id) => is_string($id) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) === 1,
        ));

        if ($fareIds === []) {
            return [];
        }

        return TransportFare::with('service:id,pax_capacity')
            ->whereIn('id', $fareIds)
            ->get()
            ->mapWithKeys(fn (TransportFare $fare) => [$fare->id => $fare->service?->pax_capacity])
            ->all();
    }

    protected function uniqueForCompany(string $modelClass, string $column, string $message, ?string $ignoreId = null): Closure
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return function (string $attribute, mixed $value, Closure $fail) use ($companyId, $modelClass, $column, $message, $ignoreId): void {
            if ($value === null || $value === '') {
                return;
            }

            $query = $modelClass::query()
                ->where('company_id', $companyId)
                ->where($column, $value)
                ->whereNull('deleted_at');

            if ($ignoreId !== null) {
                $query->whereKeyNot($ignoreId);
            }

            if ($query->exists()) {
                $fail($message);
            }
        };
    }

    protected function existsForCompany(string $modelClass, string $message): Closure
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return function (string $attribute, mixed $value, Closure $fail) use ($companyId, $modelClass, $message): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! $modelClass::query()
                ->where('company_id', $companyId)
                ->whereKey($value)
                ->whereNull('deleted_at')
                ->exists()) {
                $fail($message);
            }
        };
    }

    protected function activeForCompany(string $modelClass, string $message): Closure
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return function (string $attribute, mixed $value, Closure $fail) use ($companyId, $modelClass, $message): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! $modelClass::query()
                ->where('company_id', $companyId)
                ->whereKey($value)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->exists()) {
                $fail($message);
            }
        };
    }
}
