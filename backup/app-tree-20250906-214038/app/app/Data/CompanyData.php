<?php

namespace App\Data;

use App\Models\Company;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Lazy;

class CompanyData extends Data
{
    /** @var Lazy|DataCollection<int, UserData> */
    public Lazy|DataCollection $users;

    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $base_currency,
        ?DataCollection $users = null
    ) {
        $this->users = Lazy::whenLoaded('users', $company, fn () => UserData::collection($company->users));
    }

    public static function fromModel(Company $company): self
    {
        return new self(
            id: $company->id,
            name: $company->name,
            slug: $company->slug,
            base_currency: $company->base_currency,
            users: $company->relationLoaded('users') ? UserData::collection($company->users) : null
        );
    }
}
