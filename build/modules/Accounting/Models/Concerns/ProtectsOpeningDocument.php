<?php

namespace App\Modules\Accounting\Models\Concerns;

use App\Modules\Accounting\Services\OpeningBalanceGuard;

trait ProtectsOpeningDocument
{
    public static function bootProtectsOpeningDocument(): void
    {
        static::updating(function ($document) {
            // Allocated payments legitimately change the outstanding balance, not the original principal.
            $mutableSettlement = ['paid_amount', 'balance', 'paid_at', 'updated_at', 'updated_by_user_id', 'status'];
            $financialChange = array_diff(array_keys($document->getDirty()), $mutableSettlement);
            $withdrawn = $document->isDirty('status') && in_array($document->status, ['void', 'cancelled', 'reversed', 'draft'], true);
            if ($financialChange || $withdrawn) {
                app(OpeningBalanceGuard::class)->assertMutable($document->company_id, strtolower(class_basename($document)), $document->id);
            }
        });
        static::deleting(fn ($document) => app(OpeningBalanceGuard::class)->assertMutable($document->company_id, strtolower(class_basename($document)), $document->id));
    }
}
