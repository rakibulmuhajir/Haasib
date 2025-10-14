<?php

namespace App\Support;

use App\Models\User;

class ServiceContext
{
    public function __construct(
        private readonly ?User $actingUser = null,
        private readonly ?string $companyId = null,
        private readonly ?string $idempotencyKey = null
    ) {}

    public static function forUser(?User $user, ?string $companyId = null, ?string $idempotencyKey = null): static
    {
        return new static($user, $companyId, $idempotencyKey);
    }

    public static function forSystem(?string $companyId = null, ?string $idempotencyKey = null): static
    {
        return new static(null, $companyId, $idempotencyKey);
    }

    public function getActingUser(): ?User
    {
        return $this->actingUser;
    }

    public function getCompanyId(): ?string
    {
        return $this->companyId;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function withCompanyId(?string $companyId): static
    {
        return new static($this->actingUser, $companyId, $this->idempotencyKey);
    }

    public function withIdempotencyKey(?string $idempotencyKey): static
    {
        return new static($this->actingUser, $this->companyId, $idempotencyKey);
    }
}
