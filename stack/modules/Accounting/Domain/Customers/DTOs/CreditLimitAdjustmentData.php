<?php

namespace Modules\Accounting\Domain\Customers\DTOs;

use Carbon\Carbon;

class CreditLimitAdjustmentData
{
    public function __construct(
        public readonly float $newLimit,
        public readonly Carbon $effectiveAt,
        public readonly ?Carbon $expiresAt = null,
        public readonly string $status = 'approved',
        public readonly ?string $reason = null,
        public readonly ?int $changedByUserId = null,
        public readonly ?string $approvalReference = null,
        public readonly bool $allowBackdating = false,
        public readonly bool $autoExpireConflicts = false,
        public readonly ?int $excludeLimitId = null
    ) {}

    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            newLimit: $data['new_limit'],
            effectiveAt: Carbon::parse($data['effective_at']),
            expiresAt: isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            status: $data['status'] ?? 'approved',
            reason: $data['reason'] ?? null,
            changedByUserId: $data['changed_by_user_id'] ?? null,
            approvalReference: $data['approval_reference'] ?? null,
            allowBackdating: $data['allow_backdating'] ?? false,
            autoExpireConflicts: $data['auto_expire_conflicts'] ?? false,
            excludeLimitId: $data['exclude_limit_id'] ?? null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'new_limit' => $this->newLimit,
            'effective_at' => $this->effectiveAt->toDateTimeString(),
            'expires_at' => $this->expiresAt?->toDateTimeString(),
            'status' => $this->status,
            'reason' => $this->reason,
            'changed_by_user_id' => $this->changedByUserId,
            'approval_reference' => $this->approvalReference,
            'allow_backdating' => $this->allowBackdating,
            'auto_expire_conflicts' => $this->autoExpireConflicts,
            'exclude_limit_id' => $this->excludeLimitId,
        ];
    }

    /**
     * Validate the data
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->newLimit < 0) {
            $errors['new_limit'] = 'Credit limit amount cannot be negative';
        }

        if (! $this->allowBackdating && $this->effectiveAt->isPast()) {
            $errors['effective_at'] = 'Effective date cannot be in the past';
        }

        if ($this->expiresAt && $this->expiresAt->lte($this->effectiveAt)) {
            $errors['expires_at'] = 'Expiry date must be after the effective date';
        }

        if (! in_array($this->status, ['pending', 'approved', 'revoked'])) {
            $errors['status'] = 'Invalid status. Must be pending, approved, or revoked';
        }

        if (empty($this->changedByUserId)) {
            $errors['changed_by_user_id'] = 'User ID is required';
        }

        return $errors;
    }
}
