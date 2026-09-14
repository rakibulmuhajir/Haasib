<?php

namespace App\Modules\Umrah\Commands;

use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Services\UmrahCoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateImportedGroup
{
    public function __construct(public readonly string $companyId, public readonly array $data, public readonly bool $isAgent) {}

    public function handle(UmrahCoreService $core): VisaGroup
    {
        return DB::transaction(function () use ($core) {
            if (! empty($this->data['idempotency_key'])) {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$this->companyId.':group:'.$this->data['idempotency_key']]);
                $existing = VisaGroup::withTrashed()->where('company_id', $this->companyId)->where('idempotency_key', $this->data['idempotency_key'])->first();
                if ($existing) {
                    if ($existing->agent_id !== $this->data['agent_id'] || $existing->trashed() || $existing->status === VisaGroup::STATUS_CANCELLED) {
                        throw ValidationException::withMessages(['idempotency_key' => 'This form was already used for another or cancelled booking. Open a new Create Group form.']);
                    }

                    return $existing;
                }
            }
            $data = $core->resolveGroupVendors($this->companyId, $this->data, $this->isAgent);

            return $core->createGroup($this->companyId, $data);
        });
    }
}
