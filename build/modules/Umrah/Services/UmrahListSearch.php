<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;

/** Search only within an already authorized group/voucher query. */
class UmrahListSearch
{
    public function pattern(string $text): string
    {
        return '%'.addcslashes(trim($text), '\\%_').'%';
    }

    public function incoming(Builder $query, string $companyId, string $pattern, ?string $agentId): void
    {
        $query->orWhereHas('vouchers', fn ($voucher) => $voucher
            ->where('company_id', $companyId)
            ->when($agentId !== null, fn ($owned) => $owned->where('agent_id', $agentId))
            ->whereIn('status', [Voucher::STATUS_DRAFT, Voucher::STATUS_APPROVED])
            ->whereNull('superseded_by_voucher_id')
            ->where(fn ($current) => $current->where('status', Voucher::STATUS_APPROVED)->orWhereNull('amends_voucher_id'))
            ->whereHas('passengers', fn ($passenger) => $passenger
                ->where('umrah.passengers.company_id', $companyId)
                ->where(fn ($match) => $match->where('full_name', 'ilike', $pattern)
                    ->orWhere('passport_number', 'ilike', $pattern))));
    }
}
