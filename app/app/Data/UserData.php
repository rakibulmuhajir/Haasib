<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?string $role,
    ) {}

    /**
     * Create a UserData object from a User model.
     * Note: This expects the 'pivot' relationship to be loaded
     * if you need the role.
     */
    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $user->pivot?->role,
        );
    }
}
